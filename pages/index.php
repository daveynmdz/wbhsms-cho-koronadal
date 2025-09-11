<?php
// Main entry point for the website
include_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koronadal City Health Office</title>
    <link rel="stylesheet" href="css/index.css">
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
            <a href="patient/login/patientLogin.php"><button type="button">Get Started</button></a>
        </div>
    </div>
</body>
</html>
