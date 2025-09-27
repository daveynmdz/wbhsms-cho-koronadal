<?php
// book_appointment.php - Patient Appointment Booking
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['patient_id'])) {
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

// Fetch patient information
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
    $stmt->close();
} catch (Exception $e) {
    $error = "Failed to fetch patient information: " . $e->getMessage();
}

// Fetch active referrals for this patient
$active_referrals = [];
try {
    $stmt = $conn->prepare("
        SELECT r.referral_id, r.referral_num, r.referral_reason, r.destination_type,
               r.referred_to_facility_id, r.external_facility_name,
               f.name as facility_name, f.facility_type
        FROM referrals r
        LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
        WHERE r.patient_id = ? AND r.status = 'active'
        ORDER BY r.referral_date DESC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $active_referrals = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // Ignore errors for referrals
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
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <style>
        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .booking-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
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
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .step {
            display: flex;
            align-items: center;
            margin: 0 1rem;
            opacity: 0.5;
            transition: opacity 0.3s;
        }

        .step.active {
            opacity: 1;
        }

        .step.completed {
            opacity: 1;
            color: #28a745;
        }

        .step-number {
            background: #e9ecef;
            color: #6c757d;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .step.active .step-number {
            background: #0077b6;
            color: white;
        }

        .step.completed .step-number {
            background: #28a745;
            color: white;
        }

        .step.completed .step-number::before {
            content: '✓';
        }

        .step-text {
            font-weight: 500;
            white-space: nowrap;
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
            margin-bottom: 2rem;
        }

        .facility-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .facility-card:hover {
            background: #e3f2fd;
            border-color: #0077b6;
            transform: translateY(-2px);
        }

        .facility-card.selected {
            background: #0077b6;
            color: white;
            border-color: #023e8a;
        }

        .facility-card .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #0077b6;
        }

        .facility-card.selected .icon {
            color: white;
        }

        .facility-card h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .facility-card p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #0077b6;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }

        .referral-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .referral-card:hover {
            background: #e3f2fd;
            border-color: #0077b6;
        }

        .referral-card.selected {
            background: #0077b6;
            color: white;
            border-color: #023e8a;
        }

        .referral-number {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .referral-reason {
            margin-bottom: 0.5rem;
        }

        .referral-facility {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .time-slot {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .time-slot:hover {
            background: #e3f2fd;
            border-color: #0077b6;
        }

        .time-slot.selected {
            background: #0077b6;
            color: white;
            border-color: #023e8a;
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
        }

        .slot-availability {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #b8daff;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .hidden {
            display: none;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .facility-selection {
                grid-template-columns: 1fr;
            }

            .step {
                margin: 0.5rem 0.5rem;
            }

            .step-text {
                font-size: 0.85rem;
            }

            .time-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 0.5rem;
            }

            .navigation-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <?php
    $activePage = 'appointments';
    include '../../../includes/sidebar_patient.php';
    ?>

    <section class="content-wrapper">
        <div class="booking-container">
            <div class="booking-header">
                <h1><i class="fas fa-calendar-check"></i> Book Appointment</h1>
                <p>Schedule your healthcare appointment with ease</p>
            </div>

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
                <h3>Step 1: Select Healthcare Facility</h3>
                
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
                <h3>Step 2: Select Service</h3>
                
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
                <h3>Step 3: Select Date & Time</h3>
                
                <div class="form-group">
                    <label for="appointment-date">Appointment Date:</label>
                    <input type="date" id="appointment-date" class="form-control" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>

                <div class="form-group">
                    <label>Available Time Slots:</label>
                    <div id="time-slots" class="time-grid">
                        <!-- Time slots will be loaded here -->
                    </div>
                </div>
            </div>

            <!-- Step 4: Confirmation -->
            <div class="form-section" id="section-4">
                <h3>Step 4: Confirm Your Appointment</h3>
                
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
                
                <button type="button" class="btn btn-primary hidden" id="confirm-btn" onclick="confirmAppointment()">
                    <i class="fas fa-check"></i> Confirm Appointment
                </button>
            </div>
        </div>
    </section>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-check"></i> Confirm Appointment</h3>
                <button type="button" class="close" onclick="closeModal('confirmationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="confirmation-summary">
                    <!-- Detailed summary will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('confirmationModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitAppointment()">
                    <i class="fas fa-check"></i> Confirm Booking
                </button>
            </div>
        </div>
    </div>

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
                <button type="button" class="btn btn-primary" onclick="window.location.href='../dashboard.php'">
                    <i class="fas fa-home"></i> Go to Dashboard
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

        // Initialize the form
        document.addEventListener('DOMContentLoaded', function() {
            updateStepVisibility();
            setupDateInput();
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
                    return referral.facility_type === 'DHO' || referral.destination_type === 'external';
                } else if (selectedFacility === 'cho') {
                    return referral.facility_type === 'CHO' || referral.destination_type === 'external';
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
                    html += `
                        <div class="referral-card" onclick="selectReferral(${referral.referral_id}, '${referral.referral_reason}')">
                            <div class="referral-number">Referral #${referral.referral_num}</div>
                            <div class="referral-reason">${referral.referral_reason}</div>
                            <div class="referral-facility">Referred to: ${facilityName}</div>
                        </div>
                    `;
                });
                
                referralList.innerHTML = html;
            }
        }

        function selectReferral(referralId, reason) {
            // Remove previous selection
            document.querySelectorAll('.referral-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Select current referral
            event.target.closest('.referral-card').classList.add('selected');
            selectedReferral = referralId;
            selectedService = reason;

            // Show selected service
            document.getElementById('selected-service-info').classList.remove('hidden');
            document.getElementById('service-display').value = reason;

            // Enable next button
            document.getElementById('next-btn').disabled = false;
        }

        function setupDateAndTimeSelection() {
            const dateInput = document.getElementById('appointment-date');
            dateInput.addEventListener('change', loadTimeSlots);
            
            // Set minimum date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            dateInput.min = tomorrow.toISOString().split('T')[0];
        }

        function loadTimeSlots() {
            selectedDate = document.getElementById('appointment-date').value;
            
            if (!selectedDate) return;

            const timeSlotsContainer = document.getElementById('time-slots');
            timeSlotsContainer.innerHTML = '<div style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Loading available slots...</div>';

            // Generate time slots from 8 AM to 4 PM
            const timeSlots = [];
            for (let hour = 8; hour < 16; hour++) {
                const time24 = `${hour.toString().padStart(2, '0')}:00`;
                const time12 = formatTime12Hour(hour);
                timeSlots.push({ time24, time12 });
            }

            // Fetch availability for each slot
            fetchSlotAvailability(timeSlots);
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
            .then(response => response.json())
            .then(data => {
                displayTimeSlots(timeSlots, data.availability || {});
            })
            .catch(error => {
                console.error('Error:', error);
                displayTimeSlots(timeSlots, {});
            });
        }

        function displayTimeSlots(timeSlots, availability) {
            const timeSlotsContainer = document.getElementById('time-slots');
            let html = '';

            timeSlots.forEach(slot => {
                const bookings = availability[slot.time24] || 0;
                const isAvailable = bookings < 20;
                const availableSlots = 20 - bookings;

                html += `
                    <div class="time-slot ${isAvailable ? '' : 'unavailable'}" 
                         onclick="${isAvailable ? `selectTimeSlot('${slot.time24}', '${slot.time12}')` : ''}"
                         data-time="${slot.time24}">
                        <div>${slot.time12}</div>
                        <div class="slot-availability">
                            ${isAvailable ? `${availableSlots} slots left` : 'Fully booked'}
                        </div>
                    </div>
                `;
            });

            timeSlotsContainer.innerHTML = html;
        }

        function selectTimeSlot(time24, time12) {
            // Remove previous selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });

            // Select current slot
            document.querySelector(`[data-time="${time24}"]`).classList.add('selected');
            selectedTime = time24;

            // Enable next button
            document.getElementById('next-btn').disabled = false;
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

            summaryContainer.innerHTML = `
                <div class="referral-summary-card">
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="fas fa-calendar-check"></i>
                            Appointment Details
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Patient</div>
                                <div class="summary-value">${patientInfo.first_name} ${patientInfo.middle_name || ''} ${patientInfo.last_name}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Facility</div>
                                <div class="summary-value">${facilityName}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Service</div>
                                <div class="summary-value">${selectedService}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Date</div>
                                <div class="summary-value">${appointmentDate}</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Time</div>
                                <div class="summary-value">${appointmentTime}</div>
                            </div>
                            ${selectedReferral ? `
                            <div class="summary-item">
                                <div class="summary-label">Referral</div>
                                <div class="summary-value">Required (Selected)</div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        function confirmAppointment() {
            showAppointmentSummary();
            
            // Show confirmation modal
            document.getElementById('confirmationModal').style.display = 'block';
            document.getElementById('confirmation-summary').innerHTML = document.getElementById('appointment-summary').innerHTML;
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
                appointment_time: selectedTime
            };

            // Submit the appointment
            fetch('submit_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(appointmentData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('confirmationModal');
                    showSuccessModal(data);
                } else {
                    alert('Error: ' + data.message);
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while booking your appointment. Please try again.');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
            });
        }

        function showSuccessModal(data) {
            const modal = document.getElementById('successModal');
            const detailsContainer = document.getElementById('success-details');

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
                            <div class="summary-item">
                                <div class="summary-label">Email Notification</div>
                                <div class="summary-value">Sent to ${patientInfo.email}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Important:</strong> Please bring a valid ID and your appointment reference when you visit the facility. 
                    If you have a referral, please also bring your referral document.
                </div>
            `;

            modal.style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function setupDateInput() {
            // Disable weekends (optional - based on facility policy)
            const dateInput = document.getElementById('appointment-date');
            dateInput.addEventListener('input', function() {
                const selectedDate = new Date(this.value);
                const dayOfWeek = selectedDate.getDay();
                
                // Optional: Disable weekends (0 = Sunday, 6 = Saturday)
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    alert('Please select a weekday for your appointment.');
                    this.value = '';
                    selectedDate = null;
                }
            });
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
    </style>
</body>
</html>