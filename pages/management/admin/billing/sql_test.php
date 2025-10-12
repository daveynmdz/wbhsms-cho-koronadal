<?php
// SQL Test - Verify billing system database queries work correctly
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

echo "<h2>Billing System SQL Tests</h2>";
echo "<p><strong>Testing Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Admin billing overview recent activity query
echo "<h3>Test 1: Admin Billing Overview - Recent Activity</h3>";
try {
    $stmt = $pdo->query("
        SELECT 
            r.receipt_id,
            r.receipt_number,
            r.payment_date,
            r.amount_paid,
            r.payment_method,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            CONCAT(e.first_name, ' ', e.last_name) as cashier_name
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN employees e ON r.received_by_employee_id = e.employee_id
        ORDER BY r.payment_date DESC
        LIMIT 5
    ");
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Recent activity query executed successfully</p>";
    echo "<p><strong>Results:</strong> Found " . count($recent_activity) . " recent transactions</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Billing management dashboard query
echo "<h3>Test 2: Billing Management - Today's Collections</h3>";
$today = date('Y-m-d');
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as transactions_today,
            COALESCE(SUM(amount_paid), 0) as total_collected,
            COUNT(DISTINCT r.billing_id) as bills_paid
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        WHERE DATE(r.payment_date) = ?
    ");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Today's collections query executed successfully</p>";
    echo "<p><strong>Results:</strong></p>";
    echo "<ul>";
    echo "<li>Transactions today: " . $today_stats['transactions_today'] . "</li>";
    echo "<li>Total collected: ₱" . number_format($today_stats['total_collected'], 2) . "</li>";
    echo "<li>Bills paid: " . $today_stats['bills_paid'] . "</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: Print receipt main query
echo "<h3>Test 3: Print Receipt - Receipt Data Query</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            b.billing_id,
            b.billing_date,
            b.total_amount as invoice_total,
            b.discount_amount,
            b.philhealth_coverage,
            b.payment_status,
            b.notes as billing_notes,
            p.patient_id,
            p.first_name,
            p.last_name,
            p.middle_name,
            p.date_of_birth,
            p.address,
            p.phone_number,
            e.first_name as cashier_first_name,
            e.last_name as cashier_last_name
        FROM receipts r
        JOIN billing b ON r.billing_id = b.billing_id
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN employees e ON r.received_by_employee_id = e.employee_id
        LIMIT 1
    ");
    $stmt->execute();
    $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Receipt data query executed successfully</p>";
    echo "<p><strong>Results:</strong> " . ($receipt_data ? "Sample receipt data retrieved successfully" : "No receipt data found (normal if no receipts exist)") . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Database table structure verification
echo "<h3>Test 4: Database Structure Verification</h3>";
try {
    // Check receipts table structure
    $stmt = $pdo->query("DESCRIBE receipts");
    $receipts_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_received_by_employee_id = false;
    $has_billing_id = false;
    
    foreach ($receipts_columns as $column) {
        if ($column['Field'] == 'received_by_employee_id') {
            $has_received_by_employee_id = true;
        }
        if ($column['Field'] == 'billing_id') {
            $has_billing_id = true;
        }
    }
    
    if ($has_received_by_employee_id) {
        echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> receipts table has 'received_by_employee_id' column</p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>ERROR:</strong> receipts table missing 'received_by_employee_id' column</p>";
    }
    
    if ($has_billing_id) {
        echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> receipts table has 'billing_id' column</p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>ERROR:</strong> receipts table missing 'billing_id' column</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Check if there are any receipts in the system
echo "<h3>Test 5: Data Availability Check</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as receipt_count FROM receipts");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as billing_count FROM billing");
    $billing_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Data Status:</strong></p>";
    echo "<ul>";
    echo "<li>Total receipts in system: " . $count['receipt_count'] . "</li>";
    echo "<li>Total billing records: " . $billing_count['billing_count'] . "</li>";
    echo "</ul>";
    
    if ($count['receipt_count'] == 0) {
        echo "<p style='color: orange;'>⚠️ <strong>NOTE:</strong> No receipts found. This is normal for a new system.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>Quick Navigation</h3>";
echo "<p><a href='../dashboard.php'>← Back to Admin Dashboard</a></p>";
echo "<p><a href='billing_overview.php'>→ Try Billing Overview</a></p>";
echo "<p><a href='../../cashier/billing/billing_management.php'>→ Try Billing Management</a></p>";
echo "<p><a href='../../cashier/billing/print_receipt.php'>→ Try Print Receipt</a></p>";
?>