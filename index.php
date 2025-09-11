<?php
// Main entry point for the website

// --- ENV Loader Section ---
// This block checks for .env.local first, then .env.
// Put your database credentials in .env.local for local dev, .env for production.

include_once __DIR__ . '/config/env.php';

// Try to load .env.local first; if not, load .env
if (file_exists(__DIR__ . '/.env.local')) {
    // LOCALHOST ENVIRONMENT
    loadEnv(__DIR__ . '/.env.local');
} elseif (file_exists(__DIR__ . '/.env')) {
    // PRODUCTION/DEPLOYED ENVIRONMENT
    loadEnv(__DIR__ . '/.env');
}

// --- End ENV Loader Section ---

include_once __DIR__ . '/config/db.php';

// --- Database Connection Section ---
// Use environment variables for connection

$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : '3306';
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : '';
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "Connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
// --- End Database Connection Section ---
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koronadal City Health Office</title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>

<body>
    <div class="max-w-600 card mt-1">
        <h1 class="text-center">Koronadal City Health Office (CHO)</h1>
        <p>The City Health Office (CHO) of Koronadal has long been the primary healthcare provider for the city, but it was only in 2022 that it established its Main District building, making its facilities relatively new.</p>
        <p>CHO operates across three districts—<strong>Main</strong>, <strong>Concepcion</strong>, and <strong>GPS</strong>—each covering 8 to 10 barangays to ensure accessible healthcare for all residents.</p>
        <h2>Services Offered at CHO Main District</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Konsulta</td>
                    <td>Free basic healthcare services</td>
                </tr>
                <tr>
                    <td>Dental Services</td>
                    <td>Tooth extraction and other dental care</td>
                </tr>
                <tr>
                    <td>TB DOTS</td>
                    <td>Tuberculosis Directly Observed Treatment, Short-course</td>
                </tr>
                <tr>
                    <td>Tetanus & Vaccines</td>
                    <td>Administration of vaccines (flu, pneumonia, etc.)</td>
                </tr>
                <tr>
                    <td>HEMS (911)</td>
                    <td>Emergency Medical Services</td>
                </tr>
                <tr>
                    <td>Family Planning</td>
                    <td>Consultation and services for family planning</td>
                </tr>
                <tr>
                    <td>Animal Bite Treatment</td>
                    <td>Rabies prevention and treatment for animal bites</td>
                </tr>
            </tbody>
        </table>
        <div class="alert alert-success text-center">
            <strong>CHO Main District:</strong> Modern facilities, accessible healthcare, and a wide range of essential services for Koronadal City.
        </div>
        <div class="text-center">
            <a href="pages/auth/patient_login.php"><button type="button">Get Started</button></a>
        </div>
    </div>
</body>

</html>