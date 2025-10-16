<?php
// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Server-side role enforcement - Only lab technicians and admins can upload results
if (!isset($_SESSION['employee_id']) || ($_SESSION['role'] !== 'laboratory_tech' && $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    exit('Not authorized');
}

$lab_order_item_id = $_GET['lab_order_item_id'] ?? null;

if (!$lab_order_item_id) {
    http_response_code(400);
    exit('Lab order item ID is required');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result_text = $_POST['result_text'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        // Create uploads directory if it doesn't exist
        $uploadsDir = $root_path . '/uploads/lab_results';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $conn->begin_transaction();

        $result_file = null;
        
        // Handle file upload if provided
        if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['result_file'];
            
            // Validate file type (PDF only)
            $allowedTypes = ['application/pdf'];
            $fileType = $file['type'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileType, $allowedTypes) || $fileExtension !== 'pdf') {
                throw new Exception('Only PDF files are allowed.');
            }
            
            // Validate file size (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('File size must be less than 10MB.');
            }
            
            // Generate unique filename
            $result_file = 'lab_result_' . $lab_order_item_id . '_' . time() . '.pdf';
            $targetPath = $uploadsDir . '/' . $result_file;
            
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Failed to upload file.');
            }
        }

        // Update lab order item
        $updateSql = "UPDATE lab_order_items 
                      SET result = ?, result_file = ?, result_date = NOW(), 
                          uploaded_by_employee_id = ?, remarks = ?, status = 'completed', 
                          updated_at = NOW()
                      WHERE item_id = ?";
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ssisi", $result_text, $result_file, $_SESSION['employee_id'], $remarks, $lab_order_item_id);
        $updateStmt->execute();

        if ($updateStmt->affected_rows === 0) {
            throw new Exception('Lab order item not found or no changes made.');
        }

        // Update overall lab order status
        $orderSql = "SELECT lab_order_id FROM lab_order_items WHERE item_id = ?";
        $orderStmt = $conn->prepare($orderSql);
        $orderStmt->bind_param("i", $lab_order_item_id);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $orderData = $orderResult->fetch_assoc();
        
        if ($orderData) {
            // Calculate overall status based on individual item statuses
            $statusSql = "SELECT 
                            COUNT(*) as total_items,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_items,
                            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_items
                          FROM lab_order_items 
                          WHERE lab_order_id = ?";
            
            $statusStmt = $conn->prepare($statusSql);
            $statusStmt->bind_param("i", $orderData['lab_order_id']);
            $statusStmt->execute();
            $statusResult = $statusStmt->get_result();
            $statusData = $statusResult->fetch_assoc();
            
            $overall_status = 'pending';
            if ($statusData['completed_items'] == $statusData['total_items']) {
                $overall_status = 'completed';
            } elseif ($statusData['completed_items'] > 0) {
                $overall_status = 'partial';
            } elseif ($statusData['cancelled_items'] == $statusData['total_items']) {
                $overall_status = 'cancelled';
            }
            
            // Update overall order status (check if column exists first)
            $columnCheck = $conn->query("SHOW COLUMNS FROM lab_orders LIKE 'overall_status'");
            if ($columnCheck->num_rows > 0) {
                $updateOrderSql = "UPDATE lab_orders SET overall_status = ?, updated_at = NOW() WHERE lab_order_id = ?";
                $updateOrderStmt = $conn->prepare($updateOrderSql);
                $updateOrderStmt->bind_param("si", $overall_status, $orderData['lab_order_id']);
                $updateOrderStmt->execute();
            } else {
                // Fallback: update basic status column if overall_status doesn't exist
                $updateOrderSql = "UPDATE lab_orders SET status = ?, updated_at = NOW() WHERE lab_order_id = ?";
                $updateOrderStmt = $conn->prepare($updateOrderSql);
                $updateOrderStmt->bind_param("si", $overall_status, $orderData['lab_order_id']);
                $updateOrderStmt->execute();
            }
        }

        $conn->commit();
        
        $_SESSION['lab_message'] = 'Lab result uploaded successfully.';
        $_SESSION['lab_message_type'] = 'success';
        
        // Return JSON response for AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Lab result uploaded successfully.']);
            exit();
        }
        
        header('Location: ../lab_management.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        
        // Clean up uploaded file if there was an error
        if (isset($result_file) && file_exists($uploadsDir . '/' . $result_file)) {
            unlink($uploadsDir . '/' . $result_file);
        }
        
        $_SESSION['lab_message'] = 'Error uploading result: ' . $e->getMessage();
        $_SESSION['lab_message_type'] = 'error';
        
        // Return JSON response for AJAX requests
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        
        header('Location: ../lab_management.php');
        exit();
    }
}

// Fetch lab order item details
$itemSql = "SELECT loi.item_id as lab_order_item_id, loi.lab_order_id, loi.test_type, loi.status,
                   loi.special_instructions, loi.result, loi.result_file,
                   lo.patient_id, p.first_name, p.last_name, p.middle_name, p.username as patient_id_display
            FROM lab_order_items loi
            LEFT JOIN lab_orders lo ON loi.lab_order_id = lo.lab_order_id
            LEFT JOIN patients p ON lo.patient_id = p.patient_id
            WHERE loi.item_id = ?";

$itemStmt = $conn->prepare($itemSql);
$itemStmt->bind_param("i", $lab_order_item_id);
$itemStmt->execute();
$itemResult = $itemStmt->get_result();
$item = $itemResult->fetch_assoc();

if (!$item) {
    http_response_code(404);
    exit('Lab order item not found');
}

$patientName = trim($item['first_name'] . ' ' . $item['middle_name'] . ' ' . $item['last_name']);
?>

<style>
    .upload-form {
        padding: 20px;
        max-width: 600px;
        margin: 0 auto;
    }

    .form-header {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .form-header h4 {
        margin: 0 0 10px 0;
        color: #03045e;
    }

    .patient-info {
        font-size: 0.9em;
        color: #666;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-weight: bold;
        color: #03045e;
        margin-bottom: 5px;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 0.9em;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: #03045e;
        box-shadow: 0 0 5px rgba(3, 4, 94, 0.2);
    }

    .file-upload-area {
        border: 2px dashed #ddd;
        border-radius: 5px;
        padding: 30px;
        text-align: center;
        background-color: #f9f9f9;
        transition: border-color 0.3s;
        cursor: pointer;
    }

    .file-upload-area:hover {
        border-color: #03045e;
    }

    .file-upload-area.dragover {
        border-color: #03045e;
        background-color: rgba(3, 4, 94, 0.1);
    }

    .upload-icon {
        font-size: 2em;
        color: #03045e;
        margin-bottom: 10px;
    }

    .upload-text {
        color: #666;
        margin-bottom: 10px;
    }

    .file-info {
        font-size: 0.8em;
        color: #999;
    }

    .selected-file {
        margin-top: 10px;
        padding: 10px;
        background-color: #e8f5e8;
        border-radius: 5px;
        border: 1px solid #28a745;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9em;
        transition: all 0.3s;
    }

    .btn-primary {
        background-color: #03045e;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0218A7;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #545b62;
    }

    .instructions-box {
        background-color: #e8f4f8;
        border: 1px solid #bee5eb;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .instructions-title {
        font-weight: bold;
        color: #0c5460;
        margin-bottom: 5px;
    }

    .instructions-text {
        color: #0c5460;
        font-size: 0.9em;
    }

    .alert {
        padding: 12px 16px;
        margin-bottom: 20px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
</style>

<div class="upload-form">
    <div class="form-header">
        <h4>Upload Lab Result</h4>
        <div class="patient-info">
            <strong>Patient:</strong> <?= htmlspecialchars($patientName) ?> (ID: <?= htmlspecialchars($item['patient_id_display']) ?>)<br>
            <strong>Test:</strong> <?= htmlspecialchars($item['test_type']) ?>
        </div>
    </div>

    <?php if ($item['special_instructions']): ?>
    <div class="instructions-box">
        <div class="instructions-title">Special Instructions:</div>
        <div class="instructions-text"><?= htmlspecialchars($item['special_instructions']) ?></div>
    </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <span>Only PDF files are accepted. Maximum file size: 10MB.</span>
    </div>

    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="form-group">
            <label class="form-label">Result Text</label>
            <textarea name="result_text" class="form-control" rows="4" 
                      placeholder="Enter test results, findings, or observations..." required><?= htmlspecialchars($item['result'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Upload Result File (PDF)</label>
            <div class="file-upload-area" onclick="document.getElementById('result_file').click()" 
                 ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="upload-text">
                    Click to upload or drag and drop PDF file here
                </div>
                <div class="file-info">
                    PDF files only, maximum 10MB
                </div>
            </div>
            <input type="file" id="result_file" name="result_file" accept=".pdf,application/pdf" 
                   style="display: none;" onchange="handleFileSelect(event)">
            <div id="selectedFile" class="selected-file" style="display: none;"></div>
        </div>

        <div class="form-group">
            <label class="form-label">Additional Remarks (Optional)</label>
            <textarea name="remarks" class="form-control" rows="3" 
                      placeholder="Any additional notes or observations..."></textarea>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('uploadResultModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Result
            </button>
        </div>
    </form>
</div>

<script>
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            showSelectedFile(file);
        }
    }

    function handleDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const uploadArea = event.currentTarget;
        uploadArea.classList.remove('dragover');
        
        const files = event.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type === 'application/pdf') {
                document.getElementById('result_file').files = files;
                showSelectedFile(file);
            } else {
                alert('Only PDF files are allowed.');
            }
        }
    }

    function handleDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.add('dragover');
    }

    function handleDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.remove('dragover');
    }

    function showSelectedFile(file) {
        const selectedFileDiv = document.getElementById('selectedFile');
        const fileSize = (file.size / (1024 * 1024)).toFixed(2);
        
        selectedFileDiv.innerHTML = `
            <i class="fas fa-file-pdf" style="color: #dc3545; margin-right: 10px;"></i>
            <strong>${file.name}</strong> (${fileSize} MB)
            <button type="button" style="float: right; background: none; border: none; color: #dc3545; cursor: pointer;" onclick="clearSelectedFile()">
                <i class="fas fa-times"></i>
            </button>
        `;
        selectedFileDiv.style.display = 'block';
    }

    function clearSelectedFile() {
        document.getElementById('result_file').value = '';
        document.getElementById('selectedFile').style.display = 'none';
    }

    // Handle form submission
    document.getElementById('uploadForm').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        
        fetch('', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('uploadResultModal');
                if (typeof showAlert === 'function') {
                    showAlert(data.message, 'success');
                }
                // Refresh the page or update the UI
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            alert('Error uploading file. Please try again.');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
</script>