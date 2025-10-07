<?php
// debug_services.php - Check available services in database

require_once '../../../config/db.php';

echo "<h1>🔍 Service Debug Information</h1>";

echo "<h2>1. All Available Services</h2>";
$stmt = $conn->prepare("SELECT service_id, name, description FROM services ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
$services = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($services) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Service Name</th><th>Description</th></tr>";
    foreach ($services as $service) {
        echo "<tr>";
        echo "<td>" . $service['service_id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($service['name']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($service['description']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No services found in database<br>";
}

echo "<h2>2. Primary Care Service Check</h2>";
$stmt = $conn->prepare("SELECT * FROM services WHERE name = ?");
$test_service = 'Primary Care';
$stmt->bind_param("s", $test_service);
$stmt->execute();
$result = $stmt->get_result();
$primary_care = $result->fetch_assoc();
$stmt->close();

if ($primary_care) {
    echo "✅ 'Primary Care' service found:<br>";
    echo "  - ID: " . $primary_care['service_id'] . "<br>";
    echo "  - Name: " . $primary_care['name'] . "<br>";
    echo "  - Description: " . $primary_care['description'] . "<br>";
} else {
    echo "❌ 'Primary Care' service not found<br>";
    echo "Checking for similar names...<br>";
    
    $stmt = $conn->prepare("SELECT * FROM services WHERE name LIKE '%primary%' OR name LIKE '%care%' OR name LIKE '%consultation%'");
    $stmt->execute();
    $result = $stmt->get_result();
    $similar = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if ($similar) {
        echo "Similar services found:<br>";
        foreach ($similar as $service) {
            echo "  - " . htmlspecialchars($service['name']) . "<br>";
        }
    } else {
        echo "No similar services found.<br>";
    }
}

echo "<h2>3. Primary Care Check</h2>";
$stmt = $conn->prepare("SELECT * FROM services WHERE name = ?");
$test_service = 'Primary Care';
$stmt->bind_param("s", $test_service);
$stmt->execute();
$result = $stmt->get_result();
$general_consultation = $result->fetch_assoc();
$stmt->close();

if ($general_consultation) {
    echo "✅ 'Primary Care' service found:<br>";
    echo "  - ID: " . $general_consultation['service_id'] . "<br>";
    echo "  - Name: " . $general_consultation['name'] . "<br>";
    echo "  - Description: " . $general_consultation['description'] . "<br>";
} else {
    echo "❌ 'Primary Care' service not found<br>";
}

echo "<h2>4. Recommended Fix</h2>";
if ($primary_care && $general_consultation) {
    echo "✅ Both 'Primary Care' and 'Primary Care' service found - JavaScript should work correctly<br>";
} elseif ($primary_care) {
    echo "✅ Primary Care service exists - JavaScript should work correctly<br>";
} else {
    echo "❌ 'Primary Care' service not found - database may need service setup<br>";
}

?>