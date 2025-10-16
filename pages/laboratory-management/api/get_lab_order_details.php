<?php
// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Server-side role enforcement
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['admin', 'laboratory_tech', 'doctor', 'nurse'])) {
    http_response_code(403);
    exit('Not authorized');
}

$lab_order_id = $_GET['lab_order_id'] ?? null;

if (!$lab_order_id) {
    http_response_code(400);
    exit('Lab order ID is required');
}

// Check authorization based on role
$canUploadResults = $_SESSION['role'] === 'laboratory_tech' || $_SESSION['role'] === 'admin';
$canUpdateStatus = $canUploadResults;

// Fetch lab order details
$orderSql = "SELECT lo.lab_order_id, lo.patient_id, lo.order_date, lo.status, lo.overall_status,
                    lo.ordered_by_employee_id, lo.remarks, lo.appointment_id, lo.consultation_id, lo.visit_id,
                    p.first_name, p.last_name, p.middle_name, p.username as patient_id_display,
                    e.first_name as ordered_by_first_name, e.last_name as ordered_by_last_name
             FROM lab_orders lo
             LEFT JOIN patients p ON lo.patient_id = p.patient_id
             LEFT JOIN employees e ON lo.ordered_by_employee_id = e.employee_id
             WHERE lo.lab_order_id = ?";

$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param("i", $lab_order_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();

if (!$order) {
    http_response_code(404);
    exit('Lab order not found');
}

// Fetch lab order items
$itemsSql = "SELECT loi.lab_order_item_id, loi.test_type, loi.status, loi.result,
                    loi.result_file, loi.result_date, loi.special_instructions, loi.remarks,
                    loi.uploaded_by_employee_id, loi.created_at, loi.updated_at,
                    e.first_name as uploaded_by_first_name, e.last_name as uploaded_by_last_name
             FROM lab_order_items loi
             LEFT JOIN employees e ON loi.uploaded_by_employee_id = e.employee_id
             WHERE loi.lab_order_id = ?
             ORDER BY loi.created_at ASC";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $lab_order_id);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$patientName = trim($order['first_name'] . ' ' . $order['middle_name'] . ' ' . $order['last_name']);
$orderedBy = trim($order['ordered_by_first_name'] . ' ' . $order['ordered_by_last_name']);
?>

<style>
    .order-details {
        padding: 20px;
    }

    .order-header {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .order-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
    }

    .info-label {
        font-weight: bold;
        color: #03045e;
    }

    .info-value {
        color: #666;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .items-table th,
    .items-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
        font-size: 0.9em;
    }

    .items-table th {
        background-color: #f8f9fa;
        font-weight: bold;
        color: #03045e;
    }

    .items-table tr:hover {
        background-color: #f5f5f5;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        text-transform: uppercase;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-in_progress {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .status-completed {
        background-color: #d4edda;
        color: #155724;
    }

    .status-cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }

    .action-btn {
        padding: 6px 10px;
        margin: 2px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.8em;
        transition: all 0.3s;
    }

    .btn-upload {
        background-color: #28a745;
        color: white;
    }

    .btn-download {
        background-color: #17a2b8;
        color: white;
    }

    .btn-status {
        background-color: #ffc107;
        color: #212529;
    }

    .action-btn:hover {
        opacity: 0.8;
        transform: translateY(-1px);
    }

    .instructions {
        font-style: italic;
        color: #666;
        font-size: 0.85em;
    }

    .order-actions {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
        text-align: right;
    }

    .btn-primary {
        background-color: #03045e;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        margin-left: 10px;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .test-details {
        max-width: 300px;
        word-wrap: break-word;
    }

    @media (max-width: 768px) {
        .order-info {
            grid-template-columns: 1fr;
        }
        
        .items-table {
            font-size: 0.8em;
        }
        
        .items-table th,
        .items-table td {
            padding: 8px;
        }
    }
</style>

<div class="order-details">
    <div class="order-header">
        <h4>Lab Order #<?= $order['lab_order_id'] ?></h4>
        <div class="order-info">
            <div>
                <div class="info-item">
                    <span class="info-label">Patient:</span>
                    <span class="info-value"><?= htmlspecialchars($patientName) ?> (ID: <?= htmlspecialchars($order['patient_id_display']) ?>)</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value"><?= date('M d, Y g:i A', strtotime($order['order_date'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ordered By:</span>
                    <span class="info-value"><?= htmlspecialchars($orderedBy) ?></span>
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Overall Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= $order['overall_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['overall_status'])) ?>
                        </span>
                    </span>
                </div>
                <?php if ($order['appointment_id']): ?>
                <div class="info-item">
                    <span class="info-label">Appointment ID:</span>
                    <span class="info-value"><?= $order['appointment_id'] ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['consultation_id']): ?>
                <div class="info-item">
                    <span class="info-label">Consultation ID:</span>
                    <span class="info-value"><?= $order['consultation_id'] ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($order['remarks']): ?>
        <div class="info-item">
            <span class="info-label">Remarks:</span>
            <span class="info-value"><?= htmlspecialchars($order['remarks']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <h5>Lab Test Items</h5>
    <table class="items-table">
        <thead>
            <tr>
                <th>Test Type</th>
                <th>Status</th>
                <th>Special Instructions</th>
                <th>Result</th>
                <th>Uploaded By</th>
                <th>Result Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $itemsResult->fetch_assoc()): ?>
            <?php
                $uploadedBy = $item['uploaded_by_first_name'] ? 
                             trim($item['uploaded_by_first_name'] . ' ' . $item['uploaded_by_last_name']) : 
                             'N/A';
            ?>
            <tr>
                <td class="test-details">
                    <strong><?= htmlspecialchars($item['test_type']) ?></strong>
                </td>
                <td>
                    <span class="status-badge status-<?= $item['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                    </span>
                </td>
                <td class="instructions">
                    <?= htmlspecialchars($item['special_instructions']) ?>
                </td>
                <td class="test-details">
                    <?= $item['result'] ? htmlspecialchars($item['result']) : 'Pending' ?>
                </td>
                <td><?= htmlspecialchars($uploadedBy) ?></td>
                <td>
                    <?= $item['result_date'] ? date('M d, Y', strtotime($item['result_date'])) : 'N/A' ?>
                </td>
                <td>
                    <?php if ($item['result_file'] && file_exists($root_path . '/uploads/lab_results/' . $item['result_file'])): ?>
                        <button class="action-btn btn-download" onclick="downloadResult('<?= htmlspecialchars($item['result_file']) ?>')">
                            <i class="fas fa-download"></i> Download
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($canUploadResults && $item['status'] !== 'completed'): ?>
                        <button class="action-btn btn-upload" onclick="uploadItemResult(<?= $item['lab_order_item_id'] ?>)">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($canUpdateStatus): ?>
                        <button class="action-btn btn-status" onclick="updateItemStatus(<?= $item['lab_order_item_id'] ?>, '<?= $item['status'] ?>')">
                            <i class="fas fa-edit"></i> Status
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="order-actions">
        <button class="btn-secondary" onclick="closeModal('orderDetailsModal')">Close</button>
        <?php if ($canUpdateStatus): ?>
        <button class="btn-primary" onclick="updateOrderStatus(<?= $order['lab_order_id'] ?>, '<?= $order['overall_status'] ?>')">
            Update Order Status
        </button>
        <?php endif; ?>
    </div>
</div>

<script>
    function downloadResult(filename) {
        window.open(`../api/download_lab_result.php?file=${filename}`, '_blank');
    }

    function uploadItemResult(labOrderItemId) {
        closeModal('orderDetailsModal');
        setTimeout(() => {
            uploadResult(labOrderItemId);
        }, 100);
    }

    function updateItemStatus(labOrderItemId, currentStatus) {
        const statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        const currentIndex = statuses.indexOf(currentStatus);
        
        let options = '';
        statuses.forEach(status => {
            const selected = status === currentStatus ? 'selected' : '';
            options += `<option value="${status}" ${selected}>${status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ')}</option>`;
        });

        const modalContent = `
            <div style="padding: 20px;">
                <h4>Update Test Status</h4>
                <form onsubmit="submitStatusUpdate(event, ${labOrderItemId})">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Status:</label>
                        <select id="newStatus" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            ${options}
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Remarks (Optional):</label>
                        <textarea id="statusRemarks" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" rows="3"></textarea>
                    </div>
                    <div style="text-align: right;">
                        <button type="button" class="btn-secondary" onclick="closeModal('statusUpdateModal')" style="margin-right: 10px;">Cancel</button>
                        <button type="submit" class="btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        `;

        // Create and show status update modal
        let statusModal = document.getElementById('statusUpdateModal');
        if (!statusModal) {
            statusModal = document.createElement('div');
            statusModal.id = 'statusUpdateModal';
            statusModal.className = 'modal';
            statusModal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    ${modalContent}
                </div>
            `;
            document.body.appendChild(statusModal);
        } else {
            statusModal.querySelector('.modal-content').innerHTML = modalContent;
        }
        
        statusModal.style.display = 'block';
    }

    function submitStatusUpdate(event, labOrderItemId) {
        event.preventDefault();
        const newStatus = document.getElementById('newStatus').value;
        const remarks = document.getElementById('statusRemarks').value;

        fetch('../api/update_lab_item_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lab_order_item_id: labOrderItemId,
                status: newStatus,
                remarks: remarks
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('statusUpdateModal');
                // Refresh the order details
                viewOrderDetails(<?= $order['lab_order_id'] ?>);
                if (typeof showAlert === 'function') {
                    showAlert('Test status updated successfully', 'success');
                }
            } else {
                alert('Error updating status: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating status');
        });
    }

    function updateOrderStatus(labOrderId, currentStatus) {
        const statuses = ['pending', 'in_progress', 'completed', 'cancelled', 'partial'];
        const currentIndex = statuses.indexOf(currentStatus);
        
        let options = '';
        statuses.forEach(status => {
            const selected = status === currentStatus ? 'selected' : '';
            options += `<option value="${status}" ${selected}>${status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ')}</option>`;
        });

        const modalContent = `
            <div style="padding: 20px;">
                <h4>Update Order Status</h4>
                <form onsubmit="submitOrderStatusUpdate(event, ${labOrderId})">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Overall Status:</label>
                        <select id="newOrderStatus" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            ${options}
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Remarks (Optional):</label>
                        <textarea id="orderStatusRemarks" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" rows="3"></textarea>
                    </div>
                    <div style="text-align: right;">
                        <button type="button" class="btn-secondary" onclick="closeModal('orderStatusUpdateModal')" style="margin-right: 10px;">Cancel</button>
                        <button type="submit" class="btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        `;

        // Create and show order status update modal
        let orderStatusModal = document.getElementById('orderStatusUpdateModal');
        if (!orderStatusModal) {
            orderStatusModal = document.createElement('div');
            orderStatusModal.id = 'orderStatusUpdateModal';
            orderStatusModal.className = 'modal';
            orderStatusModal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    ${modalContent}
                </div>
            `;
            document.body.appendChild(orderStatusModal);
        } else {
            orderStatusModal.querySelector('.modal-content').innerHTML = modalContent;
        }
        
        orderStatusModal.style.display = 'block';
    }

    function submitOrderStatusUpdate(event, labOrderId) {
        event.preventDefault();
        const newStatus = document.getElementById('newOrderStatus').value;
        const remarks = document.getElementById('orderStatusRemarks').value;

        fetch('../api/update_lab_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lab_order_id: labOrderId,
                overall_status: newStatus,
                remarks: remarks
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('orderStatusUpdateModal');
                // Refresh the order details
                viewOrderDetails(labOrderId);
                if (typeof showAlert === 'function') {
                    showAlert('Order status updated successfully', 'success');
                }
            } else {
                alert('Error updating order status: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating order status');
        });
    }
</script>