<?php
// book_appointment.php - Patient Appointment Booking

// Start output buffering at the very beginning
ob_start();

// Set error reporting for debugging but don't display errors in production
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Never show errors to users in production
ini_set('log_errors', '1');      // Log errors for debugging

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));

// Load configuration first
require_once $root_path . '/config/env.php';

// Then load session management
require_once $root_path . '/config/session/patient_session.php';

// Ensure session is properly started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, bounce to login
if (!isset($_SESSION['patient_id'])) {
    ob_clean(); // Clear output buffer before redirect
    header('Location: ../auth/patient_login.php');
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please contact administrator.");
}

$patient_id = $_SESSION['patient_id'];
$message = '';
$error = '';

// Fetch patient information including priority status
$patient_info = null;
try {
    $stmt = $conn->prepare("
        SELECT p.*, b.barangay_name, b.barangay_id
        FROM patients p
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_info = $result->fetch_assoc();
    
    // Calculate priority level based on PWD/Senior status
    if ($patient_info) {
        $patient_info['priority_level'] = ($patient_info['isPWD'] || $patient_info['isSenior']) ? 1 : 2;
        $patient_info['priority_description'] = ($patient_info['priority_level'] == 1) ? 'Priority Patient' : 'Regular Patient';
        
        // Determine specific priority reason
        $priority_reasons = [];
        if ($patient_info['isPWD']) $priority_reasons[] = 'Person with Disability';
        if ($patient_info['isSenior']) $priority_reasons[] = 'Senior Citizen';
        $patient_info['priority_reasons'] = $priority_reasons;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch patient information: " . $e->getMessage();
}

// Fetch active referrals for this patient
$active_referrals = [];
try {
    // DEBUG: Show which patient we're querying for
    error_log("DEBUG - Booking page querying referrals for patient_id: " . $patient_id);
    
    // Check if database connection is working
    if (!$conn) {
        throw new Exception("Database connection is null");
    }
    
    if ($conn->connect_error) {
        throw new Exception("Database connection error: " . $conn->connect_error);
    }
    
    // First, let's get ALL referrals to debug what's in the database
    $query = "
        SELECT r.referral_id, r.referral_num, r.referral_reason, r.destination_type,
               r.referred_to_facility_id, r.external_facility_name, r.status, r.service_id,
               f.name as facility_name, f.type as facility_type,
               s.name as service_name, s.description as service_description
        FROM referrals r
        LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
        LEFT JOIN services s ON r.service_id = s.service_id
        WHERE r.patient_id = ?
        ORDER BY r.referral_date DESC
    ";
    
    error_log("DEBUG - SQL Query: " . $query);
    error_log("DEBUG - Patient ID parameter: " . $patient_id);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $patient_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    $all_referrals = $result->fetch_all(MYSQLI_ASSOC);
    
    // DEBUG: Log what we found
    error_log("DEBUG - Found " . count($all_referrals) . " total referrals for patient " . $patient_id);
    foreach ($all_referrals as $ref) {
        error_log("DEBUG - Referral: " . $ref['referral_num'] . " Status: " . ($ref['status'] ?? 'NULL'));
    }
    
    // Filter for active ones (case-insensitive)
    $active_referrals = array_filter($all_referrals, function($ref) {
        return !isset($ref['status']) || 
               $ref['status'] === null || 
               strtolower(trim($ref['status'])) === 'active';
    });
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching referrals: " . $e->getMessage());
}

// Fetch available services for the frontend
$services = [];
try {
    $stmt = $conn->prepare("SELECT service_id, name, description FROM services ORDER BY name");
    $stmt->execute();
    $result = $stmt->get_result();
    $services = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // Ignore errors for services
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <style>
        /* Base Layout */
        .homepage {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .page-header {
            margin-bottom: 1.5rem;
        }
        
        .back-btn {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #6c757d;
            color: white;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .booking-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }

        .booking-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .booking-header h1 {
            color: #0077b6;
            margin-bottom: 0.5rem;
        }

        .booking-header p {
            color: #6c757d;
            margin: 0;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0 3rem 0;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 15px;
            border: 1px solid #e9ecef;
        }

        .step {
            display: flex;
            align-items: center;
            flex-direction: column;
            text-align: center;
            min-width: 120px;
            padding: 0.5rem;
            opacity: 0.5;
            transition: all 0.3s ease;
            border-radius: 10px;
        }

        .step.active {
            opacity: 1;
            background: rgba(0, 119, 182, 0.1);
        }

        .step.completed {
            opacity: 1;
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }

        .step-number {
            background: #e9ecef;
            color: #6c757d;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: #0077b6;
            color: white;
            transform: scale(1.1);
        }

        .step.completed .step-number {
            background: #28a745;
            color: white;
            transform: scale(1.1);
        }

        .step.completed .step-number::before {
            content: '✓';
        }

        .step-text {
            font-weight: 600;
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .form-section {
            display: none;
            animation: slideIn 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .facility-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .facility-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 2rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 180px;
        }

        .facility-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
            transition: all 0.3s ease;
        }

        .facility-card:hover {
            background: #e3f2fd;
            border-color: #0077b6;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 119, 182, 0.2);
        }

        .facility-card:hover::before {
            background: linear-gradient(135deg, #0077b6, #023e8a);
        }

        .facility-card.selected {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border-color: #023e8a;
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 119, 182, 0.4);
        }

        .facility-card.selected::before {
            background: rgba(255, 255, 255, 0.3);
        }

        .facility-card .icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            color: #0077b6;
            transition: all 0.3s ease;
        }

        .facility-card.selected .icon {
            color: white;
            transform: scale(1.1);
        }

        .facility-card h3 {
            margin-bottom: 0.75rem;
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .facility-card p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.8;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #0077b6;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
            background: white;
            transform: translateY(-1px);
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .referral-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .referral-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: transparent;
            transition: all 0.3s ease;
        }

        .referral-card:hover {
            background: #e3f2fd;
            border-color: #0077b6;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.15);
        }
        
        .referral-card:hover::before {
            background: linear-gradient(135deg, #0077b6, #023e8a);
        }

        .referral-card.selected {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border-color: #023e8a;
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 119, 182, 0.3);
        }
        
        .referral-card.selected::before {
            background: rgba(255, 255, 255, 0.3);
        }

        .referral-number {
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .referral-number::before {
            content: '';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #0077b6;
        }
        
        .referral-card.selected .referral-number::before {
            color: white;
        }

        .referral-reason {
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .referral-facility {
            font-size: 0.9rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .referral-facility::before {
            content: '';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .time-slot {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .time-slot:hover {
            background: #e3f2fd;
            border-color: #0077b6;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.15);
        }

        .time-slot.selected {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border-color: #023e8a;
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0, 119, 182, 0.3);
        }

        .time-slot.unavailable {
            background: #f8d7da;
            border-color: #f1b2b7;
            color: #721c24;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .time-slot.unavailable:hover {
            background: #f8d7da;
            border-color: #f1b2b7;
            transform: none;
            box-shadow: none;
        }

        .slot-time {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .slot-availability {
            font-size: 0.8rem;
            opacity: 0.8;
            line-height: 1.2;
        }

        .time-slot.selected .slot-time {
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .time-slot.selected .slot-availability {
            opacity: 0.9;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 3rem;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1.5rem 0 1rem 0;
            border-top: 2px solid #f8f9fa;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            min-width: 140px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 119, 182, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .btn:disabled:hover {
            transform: none !important;
        }
        
        .btn:disabled::before {
            display: none;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid transparent;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alert i {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #b8daff);
            color: #0c5460;
            border-color: #b8daff;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f1b2b7);
            color: #721c24;
            border-color: #f1b2b7;
        }

        .hidden {
            display: none;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .homepage {
                padding: 0.5rem;
            }
            
            .booking-container {
                padding: 1rem;
                margin: 0.5rem 0;
                border-radius: 15px;
            }
            
            .back-btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            .btn-text {
                display: none;
            }
            
            .booking-header h1 {
                font-size: 1.5rem;
            }
            
            .booking-header p {
                font-size: 0.9rem;
            }
            
            .step-indicator {
                padding: 0.5rem;
                margin: 1rem 0 2rem 0;
                gap: 0.5rem;
            }
            
            .step {
                min-width: 80px;
                padding: 0.25rem;
            }
            
            .step-number {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
            
            .step-text {
                font-size: 0.8rem;
            }
            
            .facility-selection {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin: 1rem 0;
            }
            
            .facility-card {
                padding: 1.5rem 1rem;
                min-height: 160px;
            }
            
            .facility-card .icon {
                font-size: 3rem;
            }
            
            .facility-card h3 {
                font-size: 1.2rem;
            }
            
            .facility-card p {
                font-size: 0.9rem;
            }
            
            .time-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
                max-width: 400px;
            }
            
            .time-slot {
                padding: 0.5rem 0.25rem;
            }
            
            .slot-time {
                font-size: 0.9rem;
            }
            
            .slot-availability {
                font-size: 0.7rem;
            }
            
            .navigation-buttons {
                flex-direction: column;
                gap: 0.75rem;
                margin-top: 2rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
                justify-content: center;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-control {
                padding: 0.75rem;
                font-size: 1rem;
            }
            
            .referral-card {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .referral-number {
                font-size: 1rem;
            }
            
            .alert {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .patient-header-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem 1rem;
                gap: 1rem;
            }
            
            .patient-avatar {
                font-size: 3rem;
            }
            
            .patient-details h3 {
                font-size: 1.4rem;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .detail-card {
                min-height: auto;
            }
            
            .card-content {
                padding: 1rem;
                min-height: 100px;
            }
            
            .main-info {
                font-size: 1.1rem;
            }
            
            .sub-info {
                font-size: 0.9rem;
            }
            

            
            .modal-content {
                margin: 10% 1rem;
                max-width: none;
            }
            
            .modal-header {
                padding: 1rem;
            }
            
            .modal-header h3 {
                font-size: 1.2rem;
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-footer {
                padding: 0.75rem 1rem;
            }
        }
        
        /* Extra small screens */
        @media (max-width: 480px) {
            .homepage {
                padding: 1.5rem;
            }
            
            .booking-container {
                padding: 1.25rem;
                border-radius: 10px;
            }
            
            .step-indicator {
                flex-direction: column;
                align-items: center;
                gap: 0.75rem;
            }
            
            .step {
                flex-direction: row;
                min-width: auto;
                width: 100%;
                max-width: 200px;
                justify-content: center;
            }
            
            .step-number {
                margin-right: 0.5rem;
                margin-bottom: 0;
            }
            
            .facility-card {
                padding: 1rem;
                min-height: 140px;
            }
            
            .facility-card .icon {
                font-size: 2.5rem;
            }
            
            .facility-card h3 {
                font-size: 1.1rem;
                margin-bottom: 0.5rem;
            }
            
            .time-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
                max-width: 320px;
            }
            
            .modal-content {
                margin: 5% 0.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Top Navigation Bar -->
    <header class="topbar" disabled>
        <div>
            <a href="../dashboard.php" class="topbar-logo" style="pointer-events: none; cursor: default;">
                <picture>
                    <source media="(max-width: 600px)"
                        srcset="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128">
                    <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527"
                        alt="City Health Logo" class="responsive-logo" />
                </picture>
            </a>
        </div>
        <div class="topbar-title" style="color: #ffffff;">Book Appointment</div>
        <div class="topbar-userinfo">
            <div class="topbar-usertext">
                <strong style="color: #ffffff;">
                    <?= htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['last_name']) ?>
                </strong><br>
                <small style="color: #ffffff;">Patient</small>
            </div>
            <img src="../../../vendor/photo_controller.php?patient_id=<?= urlencode($patient_id) ?>" alt="User Profile"
                class="topbar-userphoto"
                onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';" />
        </div>
    </header>

    <section class="homepage">
        
        <!-- Go Back Button -->
        <div class="page-header">
            <a href="appointments.php" class="btn btn-secondary back-btn">
                <i class="fas fa-arrow-left"></i> <span class="btn-text">Back to Appointments</span>
            </a>
        </div>

        <div class="booking-container">
            <div class="booking-header">
                <h1><i class="fas fa-calendar-check"></i> Book Appointment</h1>
                <p>Schedule your healthcare appointment with ease</p>
            </div>


            <?php if ($patient_info && $patient_info['priority_level'] == 1): ?>
            <!-- Priority Patient Status Banner -->
            <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 1rem 1.5rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: rgba(255, 255, 255, 0.2); padding: 0.75rem; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-star" style="font-size: 1.5rem;"></i>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.3rem; font-weight: 600;">
                            <?php echo $patient_info['priority_description']; ?>
                        </h3>
                        <p style="margin: 0; font-size: 0.95rem; opacity: 0.9;">
                            You have priority access as a 
                            <?php echo implode(' and ', $patient_info['priority_reasons']); ?>.
                            You may have access to reserved time slots and priority scheduling.
                        </p>
                    </div>
                    <div style="text-align: center; background: rgba(255, 255, 255, 0.15); padding: 0.5rem 1rem; border-radius: 25px;">
                        <div style="font-size: 0.8rem; opacity: 0.8;">Priority Level</div>
                        <div style="font-size: 1.4rem; font-weight: bold;"><?php echo $patient_info['priority_level']; ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step-1">
                    <div class="step-number">1</div>
                    <div class="step-text">Select Facility</div>
                </div>
                <div class="step" id="step-2">
                    <div class="step-number">2</div>
                    <div class="step-text">Choose Service</div>
                </div>
                <div class="step" id="step-3">
                    <div class="step-number">3</div>
                    <div class="step-text">Select Date & Time</div>
                </div>
                <div class="step" id="step-4">
                    <div class="step-number">4</div>
                    <div class="step-text">Confirmation</div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Step 1: Facility Selection -->
            <div class="form-section active" id="section-1">
                <h3 style="margin-bottom: 2rem;">Step 1: Select Healthcare Facility</h3>
                
                <div class="facility-selection">
                    <div class="facility-card" data-type="bhc" onclick="selectFacility('bhc')">
                        <div class="icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h3>Barangay Health Center</h3>
                        <p>Primary care services in your locality<br><small>No referral required</small></p>
                    </div>
                    
                    <div class="facility-card" data-type="dho" onclick="selectFacility('dho')">
                        <div class="icon">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <h3>District Health Office</h3>
                        <p>Secondary care services<br><small>Referral required</small></p>
                    </div>
                    
                    <div class="facility-card" data-type="cho" onclick="selectFacility('cho')">
                        <div class="icon">
                            <i class="fas fa-hospital-alt"></i>
                        </div>
                        <h3>City Health Office</h3>
                        <p>Tertiary care services<br><small>Referral required</small></p>
                    </div>
                </div>

                <div id="facility-info" class="alert alert-info hidden">
                    <i class="fas fa-info-circle"></i>
                    <span id="facility-info-text"></span>
                </div>
            </div>

            <!-- Step 2: Service/Referral Selection -->
            <div class="form-section" id="section-2">
                 <h3 style="margin-bottom: 2rem;">Step 2: Select Service</h3>
                
                <!-- For BHC -->
                <div id="bhc-service" class="hidden">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Primary care service is automatically selected for Barangay Health Center appointments.
                    </div>
                    <div class="form-group">
                        <label>Service:</label>
                        <input type="text" class="form-control" value="Primary Care" readonly>
                    </div>
                </div>

                <!-- For DHO/CHO - Referral Selection -->
                <div id="referral-selection" class="hidden">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Please select an active referral to proceed with your appointment.
                    </div>
                    
                    <div id="referral-list">
                        <!-- Referrals will be loaded here -->
                    </div>

                    <div id="no-referrals" class="hidden">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            You don't have any active referrals for this facility type. Please obtain a referral first.
                        </div>
                        <div class="alert alert-info" style="margin-top: 1rem;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Need help?</strong> 
                            <a href="debug_referrals.php" target="_blank" style="color: #0077b6; text-decoration: underline;">
                                Click here to check your referral status
                            </a>
                            or contact your healthcare provider for a new referral.
                        </div>
                    </div>
                </div>

                <div id="selected-service-info" class="hidden">
                    <div class="form-group">
                        <label>Selected Service:</label>
                        <input type="text" id="service-display" class="form-control" readonly>
                    </div>
                </div>
            </div>

            <!-- Step 3: Date & Time Selection -->
            <div class="form-section" id="section-3">
                 <h3 style="margin-bottom: 2rem;">Step 3: Select Date & Time</h3>
                
                <div class="form-group">
                    <label for="appointment-date">Appointment Date: <span style="color: #dc3545;">*</span></label>
                    <input type="date" id="appointment-date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Same-day appointments available until 4:00 PM. Select a date between today and <?php echo date('M j, Y', strtotime('+30 days')); ?>. Weekend appointments are not available.
                    </small>
                </div>

                <div class="form-group">
                    <label>Available Time Slots: <span style="color: #dc3545;">*</span></label>
                    <div class="alert alert-info" id="time-slot-instruction" style="font-size: 0.9rem;">
                        <i class="fas fa-clock"></i> 
                        Please select a service first to see available time slots. 
                        Same-day appointments are available from 8:00 AM to 4:00 PM. Government facilities provide continuous service without lunch break interruptions.
                    </div>
                    <div id="time-slots" class="time-grid">
                        <!-- Time slots will be loaded here -->
                    </div>
                </div>
            </div>

            <!-- Step 4: Confirmation -->
            <div class="form-section" id="section-4">
                <h3 style="margin-bottom: 2rem;">Step 4: Confirm Your Appointment</h3>
                
                <div id="appointment-summary">
                    <!-- Summary will be loaded here -->
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="navigation-buttons">
                <button type="button" class="btn btn-secondary" id="prev-btn" onclick="previousStep()" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                
                <button type="button" class="btn btn-primary" id="next-btn" onclick="nextStep()" disabled>
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                
                <button type="button" class="btn btn-primary hidden" id="confirm-btn" onclick="submitAppointment()">
                    <i class="fas fa-check"></i> Book Appointment
                </button>
            </div>
        </div>
    </section>



    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                <h3><i class="fas fa-check-circle"></i> Appointment Confirmed!</h3>
            </div>
            <div class="modal-body">
                <div id="success-details">
                    <!-- Success details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="window.location.href='appointments.php'">
                    <i class="fas fa-calendar-check"></i> Go to Appointments & Referrals
                </button>
            </div>
        </div>
    </div>

    <!-- Warning/Error Modal -->
    <div id="warningModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
                <h3><i class="fas fa-exclamation-triangle"></i> <span id="warning-title">Booking Error</span></h3>
                <button type="button" class="close" onclick="closeWarningModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="warning-details">
                    <!-- Warning details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeWarningModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="warning-action-btn" onclick="closeWarningModal()" style="display: none;">
                    <i class="fas fa-arrow-right"></i> <span id="warning-action-text">Continue</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentStep = 1;
        let selectedFacility = null;
        let selectedReferral = null;
        let selectedService = null;
        let selectedDate = null;
        let selectedTime = null;
        let activeReferrals = <?php echo json_encode($active_referrals); ?>;
        let patientInfo = <?php echo json_encode($patient_info); ?>;
        let availableServices = <?php echo json_encode($services); ?>;
        
        // Debug output
        console.log('Active Referrals:', activeReferrals);
        console.log('Total active referrals found:', activeReferrals.length);
        console.log('Patient Info:', patientInfo);
        
        // Additional debugging for empty referrals
        if (activeReferrals.length === 0) {
            console.warn('⚠️ No active referrals found for patient');
            console.log('Patient ID from session:', patientInfo.patient_id || 'not available');
            console.log('Consider checking:');
            console.log('1. Patient has referrals in database');
            console.log('2. Referrals have status = "active" or NULL');
            console.log('3. Database connection is working');
            console.log('4. Use debug_referrals.php to diagnose');
        }

        // Initialize the form
        document.addEventListener('DOMContentLoaded', function() {
            updateStepVisibility();
            
            // Handle any media elements that might cause play() errors
            const mediaElements = document.querySelectorAll('audio, video');
            mediaElements.forEach(element => {
                // Prevent auto-play issues
                element.muted = true;
                element.autoplay = false;
                
                // Handle play() promise rejections
                if (element.play && typeof element.play === 'function') {
                    const originalPlay = element.play.bind(element);
                    element.play = function() {
                        const playPromise = originalPlay();
                        if (playPromise !== undefined) {
                            playPromise.catch(error => {
                                // Silently handle auto-play failures
                                console.debug('Media play prevented by browser policy:', error);
                            });
                        }
                        return playPromise;
                    };
                }
            });
        });

        function selectFacility(facilityType) {
            // Remove previous selection
            document.querySelectorAll('.facility-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Select current facility
            document.querySelector(`[data-type="${facilityType}"]`).classList.add('selected');
            selectedFacility = facilityType;

            // Show facility info
            const infoDiv = document.getElementById('facility-info');
            const infoText = document.getElementById('facility-info-text');
            
            let infoMessage = '';
            switch(facilityType) {
                case 'bhc':
                    infoMessage = `You selected Barangay Health Center in ${patientInfo.barangay_name}. Primary care services will be available.`;
                    break;
                case 'dho':
                    infoMessage = 'You selected District Health Office. You need an active referral to proceed.';
                    break;
                case 'cho':
                    infoMessage = 'You selected City Health Office. You need an active referral to proceed.';
                    break;
            }

            infoText.textContent = infoMessage;
            infoDiv.classList.remove('hidden');

            // Enable next button
            document.getElementById('next-btn').disabled = false;
        }

        function nextStep() {
            if (validateCurrentStep()) {
                currentStep++;
                updateStepVisibility();
                
                if (currentStep === 2) {
                    loadServiceOptions();
                } else if (currentStep === 3) {
                    setupDateAndTimeSelection();
                } else if (currentStep === 4) {
                    showAppointmentSummary();
                }
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepVisibility();
            }
        }

        function validateCurrentStep() {
            switch(currentStep) {
                case 1:
                    return selectedFacility !== null;
                case 2:
                    if (selectedFacility === 'bhc') {
                        selectedService = 'Primary Care';
                        return true;
                    } else {
                        return selectedReferral !== null;
                    }
                case 3:
                    return selectedDate !== null && selectedTime !== null;
                default:
                    return true;
            }
        }

        function updateStepVisibility() {
            // Update step indicators
            for (let i = 1; i <= 4; i++) {
                const step = document.getElementById(`step-${i}`);
                const section = document.getElementById(`section-${i}`);
                
                if (i < currentStep) {
                    step.className = 'step completed';
                } else if (i === currentStep) {
                    step.className = 'step active';
                } else {
                    step.className = 'step';
                }

                section.classList.toggle('active', i === currentStep);
            }

            // Update navigation buttons
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const confirmBtn = document.getElementById('confirm-btn');

            prevBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
            
            if (currentStep === 4) {
                nextBtn.classList.add('hidden');
                confirmBtn.classList.remove('hidden');
            } else {
                nextBtn.classList.remove('hidden');
                confirmBtn.classList.add('hidden');
                nextBtn.disabled = !validateCurrentStep();
            }
        }

        function loadServiceOptions() {
            if (selectedFacility === 'bhc') {
                document.getElementById('bhc-service').classList.remove('hidden');
                document.getElementById('referral-selection').classList.add('hidden');
                selectedService = 'Primary Care';
                
                // Auto-fill date with today's date for same-day booking
                autoFillAppointmentDate();
            } else {
                document.getElementById('bhc-service').classList.add('hidden');
                document.getElementById('referral-selection').classList.remove('hidden');
                
                // Load referrals
                loadReferrals();
            }
        }

        function loadReferrals() {
            const referralList = document.getElementById('referral-list');
            const noReferrals = document.getElementById('no-referrals');
            
            // Filter referrals based on facility type
            let relevantReferrals = activeReferrals.filter(referral => {
                if (selectedFacility === 'dho') {
                    return referral.facility_type === 'District Health Office' || referral.destination_type === 'external';
                } else if (selectedFacility === 'cho') {
                    return referral.facility_type === 'City Health Office' || referral.destination_type === 'external';
                } else if (selectedFacility === 'bhc') {
                    return referral.facility_type === 'Barangay Health Center' || referral.destination_type === 'external';
                }
                return false;
            });

            if (relevantReferrals.length === 0) {
                referralList.innerHTML = '';
                noReferrals.classList.remove('hidden');
            } else {
                noReferrals.classList.add('hidden');
                
                let html = '';
                relevantReferrals.forEach(referral => {
                    const facilityName = referral.facility_name || referral.external_facility_name || 'External Facility';
                    // Use service name if available, otherwise fall back to referral reason
                    const serviceName = referral.service_name || referral.referral_reason;
                    const serviceId = referral.service_id || null;
                    // Properly escape the service name for JavaScript
                    const escapedServiceName = serviceName.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
                    html += `
                        <div class="referral-card" onclick="selectReferral(${referral.referral_id}, '${escapedServiceName}', ${serviceId})">
                            <div class="referral-number">Referral #${referral.referral_num}</div>
                            <div class="referral-reason">${referral.referral_reason}</div>
                            <div class="referral-facility">Referred to: ${facilityName}</div>
                        </div>
                    `;
                });
                
                referralList.innerHTML = html;
            }
        }

        function selectReferral(referralId, serviceName, serviceId) {
            // Remove previous selection
            document.querySelectorAll('.referral-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Select current referral
            event.target.closest('.referral-card').classList.add('selected');
            selectedReferral = referralId;
            selectedService = serviceName; // Send service name to backend (what it expects)

            // Show selected service
            document.getElementById('selected-service-info').classList.remove('hidden');
            document.getElementById('service-display').value = serviceName; // Display service name to user

            // Auto-fill date with today's date for same-day booking
            autoFillAppointmentDate();

            // Enable next button
            document.getElementById('next-btn').disabled = false;
        }

        function autoFillAppointmentDate() {
            const dateInput = document.getElementById('appointment-date');
            const today = new Date();
            const currentHour = today.getHours();
            
            // Determine the appropriate date to auto-fill
            let appointmentDate = new Date(today);
            
            // If it's past 4 PM or weekend, move to next available weekday
            if (currentHour >= 16 || isWeekend(today)) {
                appointmentDate = getNextAvailableWeekday(today);
            }
            
            // Auto-fill the date field
            dateInput.value = appointmentDate.toISOString().split('T')[0];
            selectedDate = dateInput.value;
            
            // Load time slots for the auto-filled date
            loadTimeSlots();
        }

        function getNextAvailableWeekday(date) {
            const nextDay = new Date(date);
            nextDay.setDate(nextDay.getDate() + 1);
            
            // Keep moving to next day until we find a weekday
            while (isWeekend(nextDay)) {
                nextDay.setDate(nextDay.getDate() + 1);
            }
            
            return nextDay;
        }

        function isWeekend(date) {
            const dayOfWeek = date.getDay();
            return dayOfWeek === 0 || dayOfWeek === 6; // Sunday = 0, Saturday = 6
        }

        function setupDateAndTimeSelection() {
            const dateInput = document.getElementById('appointment-date');
            
            // Clear any previous selections when changing date
            dateInput.addEventListener('change', function() {
                selectedTime = null;
                
                // Remove time confirmation if exists
                const confirmationDiv = document.getElementById('time-confirmation');
                if (confirmationDiv) {
                    confirmationDiv.remove();
                }
                
                // Update next button state
                document.getElementById('next-btn').disabled = true;
                
                // Load time slots for new date
                loadTimeSlots();
            });
            
            // Set minimum date to today (for same-day booking)
            const today = new Date();
            dateInput.min = today.toISOString().split('T')[0];

            // Set maximum date to 30 days from now
            const maxDate = new Date();
            maxDate.setDate(maxDate.getDate() + 30);
            dateInput.max = maxDate.toISOString().split('T')[0];
            
            // Add input validation to prevent weekend selection
            dateInput.addEventListener('input', function() {
                const selectedDateObj = new Date(this.value);
                if (isWeekend(selectedDateObj)) {
                    // Use professional warning modal instead of alert
                    showWarningModal(
                        'Weekend Not Available', 
                        'Weekend appointments are not available. Please select a weekday (Monday - Friday).',
                        'warning'
                    );
                    this.value = '';
                    selectedDate = null;
                    
                    // Clear time slots
                    const timeSlotsContainer = document.getElementById('time-slots');
                    timeSlotsContainer.innerHTML = '';
                    
                    // Show instruction again
                    const instructionDiv = document.getElementById('time-slot-instruction');
                    if (instructionDiv) instructionDiv.style.display = 'block';
                }
            });
        }

        function loadTimeSlots() {
            selectedDate = document.getElementById('appointment-date').value;
            
            if (!selectedDate) {
                // Show instruction if no date selected
                const instructionDiv = document.getElementById('time-slot-instruction');
                if (instructionDiv) instructionDiv.style.display = 'block';
                return;
            }

            // Hide instruction message
            const instructionDiv = document.getElementById('time-slot-instruction');
            if (instructionDiv) instructionDiv.style.display = 'none';

            const timeSlotsContainer = document.getElementById('time-slots');
            timeSlotsContainer.innerHTML = '<div style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading available slots...</div>';

            // Generate time slots from 8 AM to 4 PM (1-hour intervals)
            const timeSlots = [];
            for (let hour = 8; hour <= 16; hour++) {
                const time24 = `${hour.toString().padStart(2, '0')}:00`;
                const time12 = formatTime12Hour(hour);
                timeSlots.push({ time24, time12, hour });
            }

            // Check if we have a simple setup or need to fetch from server
            if (typeof fetchSlotAvailability === 'function') {
                fetchSlotAvailability(timeSlots);
            } else {
                // Simple implementation without server-side availability check
                displayTimeSlots(timeSlots, {});
            }
        }

        function fetchSlotAvailability(timeSlots) {
            // Make AJAX call to check availability
            fetch('check_slot_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date: selectedDate,
                    service: selectedService,
                    facility_type: selectedFacility
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                displayTimeSlots(timeSlots, data.availability || {});
            })
            .catch(error => {
                console.error('Error fetching slot availability:', error);
                // Fallback: show all slots as available with a note
                displayTimeSlots(timeSlots, generateFallbackAvailability(timeSlots));
            });
        }

        function generateFallbackAvailability(timeSlots) {
            // Generate random availability for demonstration purposes
            const availability = {};
            timeSlots.forEach(slot => {
                // Random number of bookings (0-15 out of 20 max)
                availability[slot.time24] = Math.floor(Math.random() * 16);
            });
            return availability;
        }

        function displayTimeSlots(timeSlots, availability = {}) {
            const timeSlotsContainer = document.getElementById('time-slots');
            let html = '';

            // Check if it's weekend
            const appointmentDate = new Date(selectedDate);
            const dayOfWeek = appointmentDate.getDay();
            const isWeekendDay = dayOfWeek === 0 || dayOfWeek === 6;

            if (isWeekendDay) {
                html = `
                    <div style="text-align: center; padding: 2rem; color: #6c757d;">
                        <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <div>Appointments are not available on weekends.</div>
                        <div style="font-size: 0.9rem; margin-top: 0.5rem;">Please select a weekday.</div>
                    </div>
                `;
            } else {
                // Check if it's past appointment time for today
                const today = new Date();
                const isToday = appointmentDate.toDateString() === today.toDateString();
                const currentHour = today.getHours();
                const currentMinutes = today.getMinutes();
                
                // If it's today and past 4 PM, show notice
                if (isToday && currentHour >= 16) {
                    const nextAvailableDate = getNextAvailableWeekday(today);
                    const nextDateFormatted = nextAvailableDate.toLocaleDateString('en-US', {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    html = `
                        <div style="text-align: center; padding: 2rem; color: #856404; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
                            <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <div><strong>Today's appointments are no longer available.</strong></div>
                            <div style="font-size: 0.9rem; margin-top: 0.5rem;">The facility stops accepting appointments after 4:00 PM.</div>
                            <div style="font-size: 0.9rem; margin-top: 0.5rem;">Next available day: <strong>${nextDateFormatted}</strong></div>
                            <button onclick="autoFillAppointmentDate()" class="btn btn-primary" style="margin-top: 1rem; font-size: 0.9rem; padding: 0.5rem 1rem;">
                                <i class="fas fa-calendar-alt"></i> Select Next Available Date
                            </button>
                        </div>
                    `;
                } else {

                    timeSlots.forEach(slot => {
                        const bookings = availability[slot.time24] || 0;
                        const maxSlots = 20; // Maximum appointments per slot
                        const availableSlots = maxSlots - bookings;
                        const isPriorityReserved = (slot.hour === 8 || slot.hour === 9); // Reserve 8-9 AM for priority
                        
                        // Check if slot is available
                        let isAvailable = availableSlots > 0;
                        
                        // For regular patients, check if slot is priority-reserved and nearly full
                        if (patientInfo.priority_level === 2 && isPriorityReserved && availableSlots <= 5) {
                            isAvailable = false;
                        }
                        
                        // For today: disable past time slots (more precise with current time)
                        if (isToday) {
                            if (slot.hour < currentHour || (slot.hour === currentHour && currentMinutes > 0)) {
                                isAvailable = false;
                            }
                        }
                        
                        let statusText = '';
                        let statusClass = '';
                        let priorityIndicator = '';
                        
                        // Priority indicators
                        if (isPriorityReserved && patientInfo.priority_level === 1) {
                            priorityIndicator = '<i class="fas fa-star" style="color: #ffd700; font-size: 0.7rem; margin-left: 0.25rem;" title="Priority time slot"></i>';
                        } else if (isPriorityReserved && patientInfo.priority_level === 2 && availableSlots <= 5) {
                            priorityIndicator = '<i class="fas fa-lock" style="color: #dc3545; font-size: 0.7rem; margin-left: 0.25rem;" title="Reserved for priority patients"></i>';
                        }
                        
                        if (isToday && slot.hour < currentHour) {
                            statusText = 'Past Time';
                            statusClass = 'unavailable';
                        } else if (isToday && slot.hour === currentHour && currentMinutes > 0) {
                            statusText = 'Time Passed';
                            statusClass = 'unavailable';
                        } else if (!isAvailable && isPriorityReserved && patientInfo.priority_level === 2) {
                            statusText = 'Priority Reserved';
                            statusClass = 'unavailable';
                        } else if (!isAvailable) {
                            statusText = 'Fully Booked';
                            statusClass = 'unavailable';
                        } else {
                            statusText = `${availableSlots} slots left`;
                            statusClass = '';
                        }

                        html += `
                            <div class="time-slot ${statusClass}" 
                                 onclick="${isAvailable ? `selectTimeSlot('${slot.time24}', '${slot.time12}')` : ''}"
                                 data-time="${slot.time24}"
                                 title="${isAvailable ? 'Click to select this time slot' : statusText}">
                                <div class="slot-time">${slot.time12}${priorityIndicator}</div>
                                <div class="slot-availability">${statusText}</div>
                            </div>
                        `;
                    });
                }
            }

            timeSlotsContainer.innerHTML = html;
        }

        function selectTimeSlot(time24, time12) {
            // Remove previous selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });

            // Select current slot
            const selectedSlot = document.querySelector(`[data-time="${time24}"]`);
            if (selectedSlot && !selectedSlot.classList.contains('unavailable')) {
                selectedSlot.classList.add('selected');
                selectedTime = time24;

                // Show confirmation of selection
                showTimeSlotConfirmation(time12);

                // Enable next button
                document.getElementById('next-btn').disabled = false;
            }
        }

        function showTimeSlotConfirmation(time12) {
            // Create or update a confirmation message
            let confirmationDiv = document.getElementById('time-confirmation');
            if (!confirmationDiv) {
                confirmationDiv = document.createElement('div');
                confirmationDiv.id = 'time-confirmation';
                confirmationDiv.className = 'alert alert-info';
                confirmationDiv.style.marginTop = '1rem';
                document.getElementById('time-slots').parentNode.appendChild(confirmationDiv);
            }
            
            confirmationDiv.innerHTML = `
                <i class="fas fa-clock"></i> 
                <strong>Selected Time:</strong> ${time12} on ${new Date(selectedDate).toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}
            `;
        }

        function formatTime12Hour(hour) {
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:00 ${ampm}`;
        }

        function showAppointmentSummary() {
            const summaryContainer = document.getElementById('appointment-summary');
            
            let facilityName = '';
            switch(selectedFacility) {
                case 'bhc':
                    facilityName = `Barangay Health Center - ${patientInfo.barangay_name}`;
                    break;
                case 'dho':
                    facilityName = 'District Health Office';
                    break;
                case 'cho':
                    facilityName = 'City Health Office';
                    break;
            }

            const appointmentDate = new Date(selectedDate).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            const appointmentTime = formatTime12Hour(parseInt(selectedTime.split(':')[0]));

            // Get referral information if available
            let referralInfo = '';
            if (selectedReferral) {
                const referral = activeReferrals.find(r => r.referral_id === selectedReferral);
                if (referral) {
                    referralInfo = `
                        <div class="summary-item">
                            <div class="summary-label">Referral Number</div>
                            <div class="summary-value highlight">#${referral.referral_num}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Referral Reason</div>
                            <div class="summary-value">${referral.referral_reason}</div>
                        </div>
                    `;
                }
            }

            summaryContainer.innerHTML = `
                <div class="appointment-summary-container">
                    <!-- Patient Info Header -->
                    <div class="patient-header-card">
                        <div class="patient-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="patient-details">
                            <h3>${patientInfo.first_name} ${patientInfo.middle_name || ''} ${patientInfo.last_name}</h3>
                            <p>Patient ID: ${patientInfo.username}</p>
                            ${patientInfo.priority_level === 1 ? `
                                <div class="priority-badge">
                                    <i class="fas fa-star"></i>
                                    ${patientInfo.priority_description}
                                </div>
                            ` : `
                                <div class="regular-badge">
                                    <i class="fas fa-user"></i>
                                    Regular Patient
                                </div>
                            `}
                        </div>
                    </div>

                    <!-- Appointment Details Cards -->
                    <div class="details-grid">
                        <!-- Facility & Service Card -->
                        <div class="detail-card facility-card">
                            <div class="card-header">
                                <i class="fas fa-hospital"></i>
                                <span>Healthcare Facility</span>
                            </div>
                            <div class="card-content">
                                <div class="main-info">${facilityName}</div>
                                <div class="sub-info">
                                    <i class="fas fa-stethoscope"></i>
                                    Service: ${selectedService}
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time Card -->
                        <div class="detail-card datetime-card">
                            <div class="card-header">
                                <i class="fas fa-calendar-check"></i>
                                <span>Appointment Schedule</span>
                            </div>
                            <div class="card-content">
                                <div class="main-info">${appointmentDate}</div>
                                <div class="sub-info">
                                    <i class="fas fa-clock"></i>
                                    ${appointmentTime}
                                </div>
                            </div>
                        </div>

                        <!-- Referral Card -->
                        <div class="detail-card referral-card">
                            <div class="card-header">
                                <i class="fas fa-file-medical"></i>
                                <span>Referral Information</span>
                            </div>
                            <div class="card-content">
                                ${selectedFacility === 'bhc' ? `
                                    <div class="main-info" style="color: #28a745;">
                                        <i class="fas fa-check-circle"></i>
                                        No Referral Required
                                    </div>
                                    <div class="sub-info">
                                        <i class="fas fa-info-circle"></i>
                                        Direct consultation available
                                    </div>
                                ` : selectedReferral ? `
                                    <div class="main-info">Referral #${activeReferrals.find(r => r.referral_id == selectedReferral)?.referral_num || selectedReferral}</div>
                                    <div class="sub-info">
                                        <i class="fas fa-stethoscope"></i>
                                        ${activeReferrals.find(r => r.referral_id == selectedReferral)?.referral_reason || 'Referral service'}
                                    </div>
                                ` : `
                                    <div class="main-info" style="color: #ffc107;">
                                        <i class="fas fa-exclamation-circle"></i>
                                        Referral Required
                                    </div>
                                    <div class="sub-info">
                                        <i class="fas fa-file-medical"></i>
                                        Please present referral document
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>



                    <!-- Important Notes -->
                    <div class="notes-card">
                        <div class="notes-header">
                            <i class="fas fa-info-circle"></i>
                            <span>Important Reminders</span>
                        </div>
                        <div class="notes-content">
                            <div class="note-item">
                                <i class="fas fa-id-card"></i>
                                <span>Bring a valid government-issued ID</span>
                            </div>
                            <div class="note-item">
                                <i class="fas fa-clock"></i>
                                <span>Arrive 15 minutes before your appointment time</span>
                            </div>
                            ${selectedReferral ? `
                            <div class="note-item">
                                <i class="fas fa-file-medical"></i>
                                <span>Present your referral document at the facility</span>
                            </div>
                            ` : ''}
                            <div class="note-item">
                                <i class="fas fa-mobile-alt"></i>
                                <span>Bring your appointment confirmation</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }



        function submitAppointment() {
            // Disable the button to prevent double submission
            const confirmBtn = event.target;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';

            // Prepare appointment data
            const appointmentData = {
                facility_type: selectedFacility,
                referral_id: selectedReferral,
                service: selectedService,
                appointment_date: selectedDate,
                appointment_time: selectedTime,
                priority_level: patientInfo.priority_level,
                priority_description: patientInfo.priority_description,
                priority_reasons: patientInfo.priority_reasons
            };

            // Submit the appointment
            fetch('submit_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(appointmentData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        throw new Error('Server returned invalid response. Please check server logs.');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showSuccessModal(data);
                } else {
                    // Show professional warning modal instead of alert
                    showWarningModal('Appointment Booking Error', data.message, 'error');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Book Appointment';
                }
            })
            .catch(error => {
                console.error('Appointment booking error:', error);
                let errorMessage = 'An error occurred while booking your appointment. Please try again.';
                let errorType = 'error';
                
                if (error.message.includes('Server returned invalid response')) {
                    errorMessage = 'Server error occurred. Please check server logs or contact support if the problem persists.';
                    errorType = 'server-error';
                } else if (error.message.includes('HTTP error')) {
                    errorMessage = 'Connection error. Please check your internet connection and try again.';
                    errorType = 'connection-error';
                }
                
                // Show professional warning modal instead of alert
                showWarningModal('Connection Error', errorMessage, errorType);
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> Book Appointment';
            });
        }

        function showSuccessModal(data) {
            const modal = document.getElementById('successModal');
            const detailsContainer = document.getElementById('success-details');

            // Determine email status message
            let emailStatusHtml = '';
            if (data.email_sent) {
                emailStatusHtml = `
                    <div class="summary-item">
                        <div class="summary-label">Email Confirmation</div>
                        <div class="summary-value" style="color: #28a745;">
                            <i class="fas fa-check-circle"></i> Sent to ${data.patient_email || patientInfo.email}
                        </div>
                    </div>
                `;
            } else {
                emailStatusHtml = `
                    <div class="summary-item">
                        <div class="summary-label">Email Notification</div>
                        <div class="summary-value" style="color: #dc3545;">
                            <i class="fas fa-exclamation-triangle"></i> ${data.email_message || 'Could not send email'}
                        </div>
                    </div>
                `;
            }

            // Prepare queue information display
            let queueInfoHtml = '';
            if (data.has_queue && data.queue_number) {
                queueInfoHtml = `
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-list-ol"></i>
                            Queue Information
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Queue Number</div>
                                <div class="summary-value highlight" style="font-size: 1.3rem; color: #0077b6; font-weight: bold;">#${data.queue_number}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Queue Type</div>
                                <div class="summary-value">${data.queue_type ? data.queue_type.charAt(0).toUpperCase() + data.queue_type.slice(1) : 'Consultation'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Priority Level</div>
                                <div class="summary-value">${data.priority_level === 'priority' ? '⭐ Priority' : '👤 Regular'}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Status</div>
                                <div class="summary-value">Waiting</div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (data.has_queue === false) {
                queueInfoHtml = `
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Queue Status
                        </div>
                        <div class="alert alert-warning" style="margin: 0;">
                            <i class="fas fa-info-circle"></i>
                            ${data.queue_message || 'Queue number could not be assigned'}
                        </div>
                    </div>
                `;
            }

            detailsContainer.innerHTML = `
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 1rem;"></i>
                    <h4 style="color: #28a745; margin-bottom: 0.5rem;">Appointment Successfully Booked!</h4>
                    <p style="color: #6c757d;">Your appointment has been confirmed and scheduled.</p>
                </div>

                <div class="referral-summary-card">
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-ticket-alt"></i>
                            Appointment Reference
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Appointment ID</div>
                                <div class="summary-value highlight">${data.appointment_id}</div>
                            </div>
                            ${emailStatusHtml}
                        </div>
                    </div>
                    ${queueInfoHtml}
                </div>

                ${!data.email_sent ? `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Email Notice:</strong> Your appointment confirmation email could not be sent automatically. 
                        Please save your appointment ID <strong>${data.appointment_id}</strong> for your records. 
                        You can also contact the facility to confirm your appointment.
                    </div>
                ` : `
                    <div class="alert alert-success">
                        <i class="fas fa-envelope-check"></i>
                        <strong>Email Sent:</strong> A detailed appointment confirmation has been sent to your email address. 
                        Please check your inbox and spam folder.
                    </div>
                `}

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important:</strong> Please bring a valid ID and your appointment reference when you visit the facility. 
                    ${data.has_queue && data.queue_number ? `Present your queue number <strong>#${data.queue_number}</strong> for faster check-in. ` : ''}
                    If you have a referral, please also bring your referral document.
                </div>
            `;

            modal.style.display = 'block';
        }

        function showWarningModal(title, message, type = 'warning') {
            const modal = document.getElementById('warningModal');
            const titleElement = document.getElementById('warning-title');
            const detailsContainer = document.getElementById('warning-details');
            const actionBtn = document.getElementById('warning-action-btn');
            const actionText = document.getElementById('warning-action-text');

            // Set modal title
            titleElement.textContent = title;

            // Determine icon and styling based on type
            let iconClass = 'fas fa-exclamation-triangle';
            let iconColor = '#ffc107';
            let suggestions = '';

            switch(type) {
                case 'error':
                    iconClass = 'fas fa-exclamation-circle';
                    iconColor = '#dc3545';
                    suggestions = getErrorSuggestions(message);
                    break;
                case 'server-error':
                    iconClass = 'fas fa-server';
                    iconColor = '#dc3545';
                    suggestions = `
                        <div class="alert alert-info" style="margin-top: 1rem;">
                            <h6><i class="fas fa-lightbulb"></i> Troubleshooting Steps:</h6>
                            <ul style="margin: 0.5rem 0 0 1rem;">
                                <li>Check if the server is running properly</li>
                                <li>Verify database connection</li>
                                <li>Check server error logs</li>
                                <li>Contact system administrator if issue persists</li>
                            </ul>
                        </div>
                    `;
                    break;
                case 'connection-error':
                    iconClass = 'fas fa-wifi';
                    iconColor = '#dc3545';
                    suggestions = `
                        <div class="alert alert-info" style="margin-top: 1rem;">
                            <h6><i class="fas fa-lightbulb"></i> What you can do:</h6>
                            <ul style="margin: 0.5rem 0 0 1rem;">
                                <li>Check your internet connection</li>
                                <li>Refresh the page and try again</li>
                                <li>Try using a different browser</li>
                                <li>Contact support if the problem continues</li>
                            </ul>
                        </div>
                    `;
                    break;
                default:
                    suggestions = getErrorSuggestions(message);
            }

            detailsContainer.innerHTML = `
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <i class="${iconClass}" style="font-size: 3rem; color: ${iconColor}; margin-bottom: 1rem;"></i>
                    <p style="font-size: 1.1rem; color: #333; margin: 0;">${message}</p>
                </div>
                ${suggestions}
            `;

            // Show action button for certain error types
            if (type === 'duplicate-appointment') {
                actionBtn.style.display = 'inline-flex';
                actionText.textContent = 'Select Different Date';
                actionBtn.onclick = function() {
                    closeWarningModal();
                    // Navigate back to date selection
                    currentStep = 3;
                    updateStepVisibility();
                };
            } else {
                actionBtn.style.display = 'none';
            }

            modal.style.display = 'block';
        }

        function getErrorSuggestions(message) {
            if (message.includes('already have an appointment')) {
                return `
                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <h6><i class="fas fa-lightbulb"></i> What you can do:</h6>
                        <ul style="margin: 0.5rem 0 0 1rem;">
                            <li>Select a different date for your appointment</li>
                            <li>Check your existing appointments in the dashboard</li>
                            <li>Cancel existing appointment if you need to reschedule</li>
                            <li>Contact the facility for assistance</li>
                        </ul>
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-primary btn-sm" onclick="goToStep3()">
                                <i class="fas fa-calendar-alt"></i> Choose Different Date
                            </button>
                            <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="margin-left: 0.5rem;">
                                <i class="fas fa-list"></i> View My Appointments
                            </a>
                        </div>
                    </div>
                `;
            } else if (message.includes('referral')) {
                return `
                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <h6><i class="fas fa-lightbulb"></i> Referral Help:</h6>
                        <ul style="margin: 0.5rem 0 0 1rem;">
                            <li>Contact your healthcare provider for a new referral</li>
                            <li>Ensure your referral is active and valid</li>
                            <li>Try booking for Barangay Health Center (no referral needed)</li>
                        </ul>
                        <div style="margin-top: 1rem;">
                            <a href="debug_referrals.php" target="_blank" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Check Referral Status
                            </a>
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <h6><i class="fas fa-info-circle"></i> Need Help?</h6>
                        <p style="margin: 0.5rem 0;">If this error continues, please contact support or try again later.</p>
                    </div>
                `;
            }
        }

        function closeWarningModal() {
            document.getElementById('warningModal').style.display = 'none';
        }

        function goToStep3() {
            closeWarningModal();
            currentStep = 3;
            updateStepVisibility();
            // Clear the selected date to force user to pick a new one
            document.getElementById('appointment-date').value = '';
            selectedDate = null;
            selectedTime = null;
            // Clear time slots
            document.getElementById('time-slots').innerHTML = '';
            // Show instruction again
            const instructionDiv = document.getElementById('time-slot-instruction');
            if (instructionDiv) instructionDiv.style.display = 'block';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Additional modal close handlers
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }

        // Keyboard support for modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });
    </script>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            max-width: 600px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            border-radius: 0 0 15px 15px;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Button sizes */
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        /* Warning modal specific styles */
        #warningModal .modal-header {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
        }

        #warningModal .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        #warningModal .alert-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1565c0;
        }

        #warningModal .alert h6 {
            color: #1565c0;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        #warningModal ul {
            color: #424242;
        }

        #warningModal .btn {
            margin: 0.25rem;
        }

        /* Summary Card Styles */
        .referral-summary-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .summary-section {
            margin-bottom: 1.5rem;
        }

        .summary-section:last-child {
            margin-bottom: 0;
        }

        .summary-title {
            font-weight: 700;
            color: #0077b6;
            font-size: 1.1em;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-title i {
            background: #e3f2fd;
            padding: 0.5rem;
            border-radius: 8px;
            color: #0077b6;
            font-size: 0.9em;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .summary-label {
            font-size: 0.85em;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 1.05em;
            color: #333;
            font-weight: 500;
            word-wrap: break-word;
        }

        .summary-value.highlight {
            color: #0077b6;
            font-weight: 600;
        }

        /* Enhanced Appointment Summary Styles */
        .appointment-summary-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .patient-header-card {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 119, 182, 0.3);
        }

        .patient-avatar {
            font-size: 4rem;
            opacity: 0.9;
        }

        .patient-details h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .patient-details p {
            margin: 0 0 1rem 0;
            opacity: 0.8;
            font-size: 0.95rem;
        }

        .priority-badge {
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .regular-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%; /* Ensure all cards fill available height */
        }

        .detail-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 2px solid #0077b6;
            color: #0077b6;
            font-weight: 600;
            min-height: 60px; /* Consistent header height */
        }

        .card-header i {
            font-size: 1.2rem;
            width: 20px; /* Fixed width for consistent alignment */
            text-align: center;
        }

        .card-content {
            padding: 1.5rem;
            flex: 1; /* Fill remaining space */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Center content vertically */
            min-height: 120px; /* Minimum content height for consistency */
        }

        .main-info {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.75rem;
            line-height: 1.4;
            text-align: center; /* Center align main info */
        }

        .sub-info {
            color: #6c757d;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center; /* Center align sub info */
            gap: 0.5rem;
        }

        .sub-info i {
            color: #0077b6;
            width: 16px;
            text-align: center;
        }

        .facility-card .card-header {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            color: #28a745;
            border-bottom-color: #28a745;
        }

        .datetime-card .card-header {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-bottom-color: #856404;
        }

        .referral-card .card-header {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1976d2;
            border-bottom-color: #1976d2;
        }



        .notes-card {
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px solid #6c757d;
            overflow: hidden;
        }

        .notes-header {
            background: #6c757d;
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .notes-content {
            padding: 1.5rem;
        }

        .note-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: #495057;
            font-size: 0.95rem;
        }

        .note-item:last-child {
            margin-bottom: 0;
        }

        .note-item i {
            color: #0077b6;
            width: 20px;
            text-align: center;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .patient-header-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }

            .patient-avatar {
                font-size: 3rem;
            }

            .patient-details h3 {
                font-size: 1.5rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .card-content {
                padding: 1rem;
            }
        }
    </style>
</body>
</html>