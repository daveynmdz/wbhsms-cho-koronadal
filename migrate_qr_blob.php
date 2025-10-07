<?php
/**
 * QR Code Database Migration Script
 * Converts qr_code_path column from VARCHAR to LONGBLOB
 */

require_once __DIR__ . '/config/db.php';

echo "<h2>QR Code Database Migration</h2>";

try {
    // Step 1: Clear existing QR code path values
    echo "<h3>Step 1: Clearing existing QR code paths...</h3>";
    $result = $conn->query("UPDATE appointments SET qr_code_path = NULL WHERE qr_code_path IS NOT NULL");
    if ($result) {
        echo "✅ Cleared existing QR code paths (affected rows: " . $conn->affected_rows . ")<br>";
    } else {
        throw new Exception("Failed to clear existing paths: " . $conn->error);
    }
    
    // Step 2: Alter column to LONGBLOB
    echo "<h3>Step 2: Converting column to LONGBLOB...</h3>";
    $result = $conn->query("ALTER TABLE appointments MODIFY COLUMN qr_code_path LONGBLOB NULL COMMENT 'QR code image data stored as binary BLOB'");
    if ($result) {
        echo "✅ Successfully converted qr_code_path to LONGBLOB<br>";
    } else {
        throw new Exception("Failed to alter column: " . $conn->error);
    }
    
    // Step 3: Verify changes
    echo "<h3>Step 3: Verifying changes...</h3>";
    $result = $conn->query("DESCRIBE appointments");
    if ($result) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'qr_code_path') {
                echo "<tr style='background-color: #ffffcc;'>";
            } else {
                echo "<tr>";
            }
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "✅ Column structure verified<br>";
    } else {
        throw new Exception("Failed to describe table: " . $conn->error);
    }
    
    // Step 4: Check appointment counts
    echo "<h3>Step 4: Checking appointment data...</h3>";
    $result = $conn->query("SELECT COUNT(*) as total_appointments, COUNT(qr_code_path) as appointments_with_qr FROM appointments");
    if ($result && $row = $result->fetch_assoc()) {
        echo "Total appointments: " . $row['total_appointments'] . "<br>";
        echo "Appointments with QR data: " . $row['appointments_with_qr'] . "<br>";
        echo "✅ Data verification complete<br>";
    }
    
    echo "<h3>✅ Migration completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Update AppointmentQRGenerator to store BLOB data</li>";
    echo "<li>Create QR image serving endpoint</li>";
    echo "<li>Update success modal and email system</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h3>❌ Migration failed!</h3>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<p>Please check the database connection and try again.</p>";
}

$conn->close();
?>