<?php
require_once dirname(__DIR__) . '/config/db.php';

echo "APPOINTMENTS TABLE STRUCTURE:\n";
echo "============================\n";

$result = $pdo->query('DESCRIBE appointments');
foreach($result as $row) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}

echo "\nSAMPLE APPOINTMENT DATA:\n";
echo "========================\n";

$result = $pdo->query('SELECT * FROM appointments LIMIT 1');
$sample = $result->fetch(PDO::FETCH_ASSOC);
if ($sample) {
    foreach($sample as $key => $value) {
        echo "$key: $value\n";
    }
} else {
    echo "No appointments found\n";
}
?>