<?php
/**
 * Encounter History Screen - Clinical Encounter Module
 * CHO Koronadal Healthcare Management System
 */

// Include configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/mock/mock_config.php';

// Check if user is logged in and has appropriate role
if (!is_employee_logged_in() || !in_array(get_employee_session('employee_role'), ['doctor', 'nurse', 'admin'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$employee_name = get_employee_session('employee_name');
$employee_role = get_employee_session('employee_role');

// Mock encounter history data
$mock_encounters = [
    [
        'encounter_id' => 1,
        'patient_id' => 1,
        'patient_name' => 'Juan Dela Cruz',
        'encounter_date' => '2024-12-10 09:30:00',
        'doctor_name' => 'Dr. John Smith',
        'primary_diagnosis' => 'Hypertension',
        'status' => 'completed',
        'priority' => 'standard',
        'chief_complaint' => 'Persistent headache for 3 days',
        'follow_up_date' => null
    ],
    [
        'encounter_id' => 2,
        'patient_id' => 2,
        'patient_name' => 'Maria Garcia',
        'encounter_date' => '2024-12-09 14:15:00',
        'doctor_name' => 'Dr. John Smith',
        'primary_diagnosis' => 'Upper Respiratory Infection',
        'status' => 'pending_followup',
        'priority' => 'standard',
        'chief_complaint' => 'Cough and cold symptoms',
        'follow_up_date' => '2024-12-16'
    ],
    [
        'encounter_id' => 3,
        'patient_id' => 3,
        'patient_name' => 'Pedro Mendoza',
        'encounter_date' => '2024-12-08 11:45:00',
        'doctor_name' => 'Dr. John Smith',
        'primary_diagnosis' => 'Diabetes Mellitus Type 2',
        'status' => 'referred',
        'priority' => 'urgent',
        'chief_complaint' => 'High blood sugar levels',
        'follow_up_date' => null
    ],
    [
        'encounter_id' => 4,
        'patient_id' => 1,
        'patient_name' => 'Juan Dela Cruz',
        'encounter_date' => '2024-12-05 10:20:00',
        'doctor_name' => 'Dr. Jane Doe',
        'primary_diagnosis' => 'Routine Checkup',
        'status' => 'completed',
        'priority' => 'standard',
        'chief_complaint' => 'Annual physical examination',
        'follow_up_date' => null
    ],
    [
        'encounter_id' => 5,
        'patient_id' => 2,
        'patient_name' => 'Maria Garcia',
        'encounter_date' => '2024-12-03 16:30:00',
        'doctor_name' => 'Dr. John Smith',
        'primary_diagnosis' => 'Immunization',
        'status' => 'completed',
        'priority' => 'standard',
        'chief_complaint' => 'Vaccination appointment',
        'follow_up_date' => null
    ]
];

// Mock statistics
$stats = [
    'total_encounters' => count($mock_encounters),
    'completed' => count(array_filter($mock_encounters, fn($e) => $e['status'] === 'completed')),
    'pending_followup' => count(array_filter($mock_encounters, fn($e) => $e['status'] === 'pending_followup')),
    'referred' => count(array_filter($mock_encounters, fn($e) => $e['status'] === 'referred')),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encounter History - CHO Koronadal</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../assets/css/clinical-encounter/clinical-encounter.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../../../includes/sidebar_' . $employee_role . '.php'; ?>
    
    <div class="homepage">
        <!-- Include Topbar -->
        <?php 
        include '../../../includes/topbar.php';
        renderTopbar([
            'title' => 'Clinical Encounters',
            'back_url' => '../dashboard.php',
            'user_type' => 'employee'
        ]);
        ?>
        
        <div class="clinical-encounter-container">
            <div class="encounter-header">
                <h1><i class="fas fa-history"></i> Clinical Encounter History</h1>
                <p>View and manage past clinical encounters and patient consultations</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #03045e; margin-bottom: 8px;">
                            <?= $stats['total_encounters'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase;">Total Encounters</div>
                    </div>
                </div>
                
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #28a745; margin-bottom: 8px;">
                            <?= $stats['completed'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase;">Completed</div>
                    </div>
                </div>
                
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #ffc107; margin-bottom: 8px;">
                            <?= $stats['pending_followup'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase;">Pending Follow-up</div>
                    </div>
                </div>
                
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 20px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #17a2b8; margin-bottom: 8px;">
                            <?= $stats['referred'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase;">Referred</div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter Section -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-search"></i> Search & Filter Encounters
                </div>
                <div class="card-body">
                    <div class="search-filter-section">
                        <div class="search-box">
                            <input type="text" 
                                   id="searchEncounters" 
                                   class="form-control" 
                                   placeholder="Search by patient name, diagnosis, or encounter ID..."
                                   style="padding-left: 40px;">
                            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                        </div>
                        
                        <div class="filter-group">
                            <select id="statusFilter" class="form-control">
                                <option value="">All Status</option>
                                <option value="completed">Completed</option>
                                <option value="pending_followup">Pending Follow-up</option>
                                <option value="referred">Referred</option>
                                <option value="pending_results">Pending Results</option>
                            </select>
                            
                            <select id="doctorFilter" class="form-control">
                                <option value="">All Doctors</option>
                                <option value="Dr. John Smith">Dr. John Smith</option>
                                <option value="Dr. Jane Doe">Dr. Jane Doe</option>
                            </select>
                            
                            <input type="date" id="dateFilter" class="form-control">
                            
                            <button type="button" id="resetFiltersBtn" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="encounter-card">
                <div class="card-body" style="padding: 15px 25px;">
                    <div class="btn-group" style="margin: 0;">
                        <a href="triage.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> New Triage
                        </a>
                        <a href="doctor-encounter.php" class="btn btn-primary">
                            <i class="fas fa-user-md"></i> New Encounter
                        </a>
                        <button type="button" id="exportEncountersBtn" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                        <button type="button" id="printReportBtn" class="btn btn-outline">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Encounters Table -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Clinical Encounters
                    <span id="encounterCount" style="margin-left: auto; font-size: 0.9rem; opacity: 0.9;">
                        Showing <?= count($mock_encounters) ?> encounters
                    </span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div style="overflow-x: auto;">
                        <table id="encountersTable" class="encounters-table">
                            <thead>
                                <tr>
                                    <th>Encounter ID</th>
                                    <th>Date & Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Primary Diagnosis</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Follow-up</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mock_encounters as $encounter): ?>
                                <tr data-encounter-id="<?= $encounter['encounter_id'] ?>" 
                                    data-status="<?= $encounter['status'] ?>"
                                    data-doctor="<?= $encounter['doctor_name'] ?>"
                                    data-date="<?= date('Y-m-d', strtotime($encounter['encounter_date'])) ?>">
                                    <td>
                                        <strong>E<?= str_pad($encounter['encounter_id'], 4, '0', STR_PAD_LEFT) ?></strong>
                                    </td>
                                    <td>
                                        <div><?= date('M d, Y', strtotime($encounter['encounter_date'])) ?></div>
                                        <small style="color: #666;"><?= date('h:i A', strtotime($encounter['encounter_date'])) ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?= $encounter['patient_name'] ?></div>
                                        <small style="color: #666;">ID: P<?= str_pad($encounter['patient_id'], 4, '0', STR_PAD_LEFT) ?></small>
                                    </td>
                                    <td><?= $encounter['doctor_name'] ?></td>
                                    <td>
                                        <div style="font-weight: 500;"><?= $encounter['primary_diagnosis'] ?></div>
                                        <small style="color: #666;"><?= substr($encounter['chief_complaint'], 0, 40) ?>...</small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $encounter['status'] === 'completed' ? 'completed' : ($encounter['status'] === 'referred' ? 'referred' : 'pending') ?>">
                                            <?= ucwords(str_replace('_', ' ', $encounter['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $priority_class = $encounter['priority'] === 'urgent' ? 'status-referred' : 'status-completed';
                                        ?>
                                        <span class="status-badge <?= $priority_class ?>">
                                            <?= ucfirst($encounter['priority']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $encounter['follow_up_date'] ? date('M d, Y', strtotime($encounter['follow_up_date'])) : '-' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" style="margin: 0; gap: 5px;">
                                            <button type="button" 
                                                    class="btn btn-primary" 
                                                    style="padding: 6px 10px; font-size: 0.8rem;"
                                                    onclick="viewEncounter(<?= $encounter['encounter_id'] ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($employee_role === 'doctor' || $employee_role === 'admin'): ?>
                                            <button type="button" 
                                                    class="btn btn-warning" 
                                                    style="padding: 6px 10px; font-size: 0.8rem;"
                                                    onclick="editEncounter(<?= $encounter['encounter_id'] ?>)"
                                                    title="Edit Encounter">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="btn btn-secondary" 
                                                    style="padding: 6px 10px; font-size: 0.8rem;"
                                                    onclick="printEncounter(<?= $encounter['encounter_id'] ?>)"
                                                    title="Print Record">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            
                                            <?php if ($encounter['status'] === 'pending_followup'): ?>
                                            <button type="button" 
                                                    class="btn btn-success" 
                                                    style="padding: 6px 10px; font-size: 0.8rem;"
                                                    onclick="scheduleFollowup(<?= $encounter['patient_id'] ?>)"
                                                    title="Schedule Follow-up">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div style="padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
                        <button type="button" class="btn btn-outline" style="margin: 0 5px;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <span style="margin: 0 15px; color: #666;">Page 1 of 1</span>
                        <button type="button" class="btn btn-outline" style="margin: 0 5px;">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Encounter Details Modal -->
    <div id="encounterDetailsModal" class="modal-overlay">
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header">
                <span>Encounter Details</span>
                <button type="button" class="modal-close" onclick="closeModal('encounterDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="encounterDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Include JavaScript -->
    <script src="../../../assets/js/clinical-encounter/encounter-history.js"></script>
    
    <script>
        // Page-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Encounter History page loaded');
            
            // Search functionality
            const searchInput = document.getElementById('searchEncounters');
            const statusFilter = document.getElementById('statusFilter');
            const doctorFilter = document.getElementById('doctorFilter');
            const dateFilter = document.getElementById('dateFilter');
            
            function filterEncounters() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedStatus = statusFilter.value;
                const selectedDoctor = doctorFilter.value;
                const selectedDate = dateFilter.value;
                
                const rows = document.querySelectorAll('#encountersTable tbody tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const patientName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const diagnosis = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                    const encounterId = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                    
                    const matchesSearch = !searchTerm || 
                        patientName.includes(searchTerm) || 
                        diagnosis.includes(searchTerm) || 
                        encounterId.includes(searchTerm);
                    
                    const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
                    const matchesDoctor = !selectedDoctor || row.dataset.doctor === selectedDoctor;
                    const matchesDate = !selectedDate || row.dataset.date === selectedDate;
                    
                    if (matchesSearch && matchesStatus && matchesDoctor && matchesDate) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                document.getElementById('encounterCount').textContent = `Showing ${visibleCount} encounters`;
            }
            
            // Add event listeners for filtering
            searchInput.addEventListener('input', filterEncounters);
            statusFilter.addEventListener('change', filterEncounters);
            doctorFilter.addEventListener('change', filterEncounters);
            dateFilter.addEventListener('change', filterEncounters);
            
            // Reset filters
            document.getElementById('resetFiltersBtn').addEventListener('click', function() {
                searchInput.value = '';
                statusFilter.value = '';
                doctorFilter.value = '';
                dateFilter.value = '';
                filterEncounters();
            });
            
            // Export functionality
            document.getElementById('exportEncountersBtn').addEventListener('click', function() {
                alert('Export functionality would generate CSV/Excel file with encounter data.\n\nIn a real application, this would process the filtered data and generate a downloadable report.');
            });
            
            // Print report
            document.getElementById('printReportBtn').addEventListener('click', function() {
                window.print();
            });
        });
        
        // Encounter action functions
        function viewEncounter(encounterId) {
            // In a real application, this would load encounter details from the server
            const modalContent = document.getElementById('encounterDetailsContent');
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-file-medical" style="font-size: 3rem; color: #03045e; margin-bottom: 15px;"></i>
                    <h3>Encounter E${String(encounterId).padStart(4, '0')}</h3>
                    <p>Detailed encounter information would be loaded here.</p>
                    <div style="margin-top: 20px;">
                        <button class="btn btn-primary" onclick="editEncounter(${encounterId})">
                            <i class="fas fa-edit"></i> Edit Encounter
                        </button>
                        <button class="btn btn-secondary" onclick="closeModal('encounterDetailsModal')">
                            Close
                        </button>
                    </div>
                </div>
            `;
            openModal('encounterDetailsModal');
        }
        
        function editEncounter(encounterId) {
            window.location.href = `doctor-encounter.php?encounter_id=${encounterId}`;
        }
        
        function printEncounter(encounterId) {
            alert(`Print encounter E${String(encounterId).padStart(4, '0')}\n\nIn a real application, this would generate a printable encounter report.`);
        }
        
        function scheduleFollowup(patientId) {
            alert(`Schedule follow-up for Patient P${String(patientId).padStart(4, '0')}\n\nIn a real application, this would open the appointment scheduling interface.`);
        }
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>