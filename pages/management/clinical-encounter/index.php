<?php
/**
 * Clinical Encounter Module - Main Index
 * CHO Koronadal Healthcare Management System
 */

// Include configuration (use mock for frontend development)
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/mock/mock_config.php';
require_once $root_path . '/mock/mock_session.php';

// Check if user is logged in
if (!is_employee_logged_in()) {
    header('Location: ../../auth/login.php');
    exit;
}

$employee_name = get_employee_session('employee_name');
$employee_role = get_employee_session('employee_role');

// Mock statistics for dashboard
$stats = [
    'today_encounters' => 12,
    'pending_triage' => 5,
    'completed_today' => 8,
    'pending_followup' => 3
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical Encounter Module - CHO Koronadal</title>
    
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
            'title' => 'Clinical Encounter Module',
            'back_url' => '../dashboard.php',
            'user_type' => 'employee'
        ]);
        ?>
        
        <div class="clinical-encounter-container">
            <div class="encounter-header">
                <h1><i class="fas fa-stethoscope"></i> Clinical Encounter Module</h1>
                <p>Comprehensive patient care documentation and management system</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 25px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #03045e; margin-bottom: 8px;">
                            <?= $stats['today_encounters'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 8px;">Today's Encounters</div>
                        <div style="font-size: 0.8rem; color: #28a745;">
                            <i class="fas fa-arrow-up"></i> +3 from yesterday
                        </div>
                    </div>
                </div>
                
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 25px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #ffc107; margin-bottom: 8px;">
                            <?= $stats['pending_triage'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 8px;">Pending Triage</div>
                        <div style="font-size: 0.8rem; color: #dc3545;">
                            <i class="fas fa-clock"></i> Needs attention
                        </div>
                    </div>
                </div>
                
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 25px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #28a745; margin-bottom: 8px;">
                            <?= $stats['completed_today'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 8px;">Completed Today</div>
                        <div style="font-size: 0.8rem; color: #28a745;">
                            <i class="fas fa-check-circle"></i> On track
                        </div>
                    </div>
                </div>
                
                <div class="encounter-card" style="margin-bottom: 0;">
                    <div class="card-body" style="text-align: center; padding: 25px;">
                        <div style="font-size: 2.5rem; font-weight: bold; color: #17a2b8; margin-bottom: 8px;">
                            <?= $stats['pending_followup'] ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 8px;">Pending Follow-up</div>
                        <div style="font-size: 0.8rem; color: #17a2b8;">
                            <i class="fas fa-calendar-alt"></i> Schedule required
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Navigation Cards -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-th-large"></i> Clinical Encounter Workflows
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px;">
                        
                        <!-- Triage Assessment -->
                        <div class="workflow-card" style="border: 2px solid #28a745; border-radius: 12px; padding: 25px; background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <div style="background: #28a745; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px;">
                                    <i class="fas fa-stethoscope"></i>
                                </div>
                                <div>
                                    <h3 style="margin: 0; color: #155724;">Triage Assessment</h3>
                                    <p style="margin: 4px 0 0 0; color: #666; font-size: 0.9rem;">Initial patient evaluation</p>
                                </div>
                            </div>
                            
                            <p style="color: #155724; margin-bottom: 20px; line-height: 1.5;">
                                Perform initial patient assessment, record vital signs, document chief complaint, and assign priority levels for optimal patient flow.
                            </p>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 8px;"><strong>Key Features:</strong></div>
                                <ul style="margin: 0; padding-left: 18px; font-size: 0.85rem; color: #666;">
                                    <li>Patient identity verification</li>
                                    <li>Vital signs documentation</li>
                                    <li>Priority assignment system</li>
                                    <li>Real-time validation</li>
                                </ul>
                            </div>
                            
                            <a href="triage.php" class="btn btn-success" style="width: 100%; justify-content: center; margin-bottom: 10px;">
                                <i class="fas fa-plus"></i> Start New Triage
                            </a>
                            
                            <?php if ($employee_role === 'nurse' || $employee_role === 'admin'): ?>
                            <div style="font-size: 0.8rem; color: #28a745; text-align: center;">
                                <i class="fas fa-user-check"></i> Available for your role
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Doctor Encounter -->
                        <div class="workflow-card" style="border: 2px solid #03045e; border-radius: 12px; padding: 25px; background: linear-gradient(135deg, #e8f4f8 0%, #ffffff 100%);">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <div style="background: #03045e; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px;">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <h3 style="margin: 0; color: #03045e;">Doctor Encounter</h3>
                                    <p style="margin: 4px 0 0 0; color: #666; font-size: 0.9rem;">Clinical consultation & documentation</p>
                                </div>
                            </div>
                            
                            <p style="color: #03045e; margin-bottom: 20px; line-height: 1.5;">
                                Comprehensive patient consultation interface with diagnosis, treatment planning, prescription management, and clinical decision support.
                            </p>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 8px;"><strong>Key Features:</strong></div>
                                <ul style="margin: 0; padding-left: 18px; font-size: 0.85rem; color: #666;">
                                    <li>Clinical assessment forms</li>
                                    <li>Prescription management</li>
                                    <li>Lab test ordering</li>
                                    <li>Referral system</li>
                                </ul>
                            </div>
                            
                            <a href="doctor-encounter.php" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: 10px;">
                                <i class="fas fa-plus"></i> New Consultation
                            </a>
                            
                            <?php if ($employee_role === 'doctor' || $employee_role === 'admin'): ?>
                            <div style="font-size: 0.8rem; color: #03045e; text-align: center;">
                                <i class="fas fa-user-check"></i> Available for your role
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Encounter History -->
                        <div class="workflow-card" style="border: 2px solid #6c757d; border-radius: 12px; padding: 25px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <div style="background: #6c757d; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px;">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div>
                                    <h3 style="margin: 0; color: #495057;">Encounter History</h3>
                                    <p style="margin: 4px 0 0 0; color: #666; font-size: 0.9rem;">View & manage past encounters</p>
                                </div>
                            </div>
                            
                            <p style="color: #495057; margin-bottom: 20px; line-height: 1.5;">
                                Comprehensive view of all clinical encounters with advanced search, filtering, and reporting capabilities for efficient record management.
                            </p>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 8px;"><strong>Key Features:</strong></div>
                                <ul style="margin: 0; padding-left: 18px; font-size: 0.85rem; color: #666;">
                                    <li>Advanced search & filters</li>
                                    <li>Sortable encounter table</li>
                                    <li>Export capabilities</li>
                                    <li>Follow-up tracking</li>
                                </ul>
                            </div>
                            
                            <a href="encounter-history.php" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-bottom: 10px;">
                                <i class="fas fa-list"></i> View All Encounters
                            </a>
                            
                            <div style="font-size: 0.8rem; color: #6c757d; text-align: center;">
                                <i class="fas fa-users"></i> Available to all staff
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-bolt"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="btn-group">
                        <a href="triage.php?urgent=1" class="btn btn-danger">
                            <i class="fas fa-exclamation-triangle"></i> Emergency Triage
                        </a>
                        <a href="encounter-history.php?filter=pending_followup" class="btn btn-warning">
                            <i class="fas fa-calendar-check"></i> Follow-up Due
                        </a>
                        <a href="encounter-history.php?filter=today" class="btn btn-primary">
                            <i class="fas fa-calendar-day"></i> Today's Encounters
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="generateReport()">
                            <i class="fas fa-chart-bar"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="encounter-card">
                <div class="card-header">
                    <i class="fas fa-clock"></i> Recent Activity
                </div>
                <div class="card-body">
                    <div style="display: grid; gap: 15px;">
                        <div style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                            <div style="background: #28a745; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #28a745;">Triage Completed</div>
                                <div style="font-size: 0.9rem; color: #666;">Patient Juan Dela Cruz assessed - Priority: Standard</div>
                                <div style="font-size: 0.8rem; color: #999;">5 minutes ago by Alice Johnson</div>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #03045e;">
                            <div style="background: #03045e; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #03045e;">Consultation Completed</div>
                                <div style="font-size: 0.9rem; color: #666;">Maria Garcia - Diagnosis: Upper Respiratory Infection</div>
                                <div style="font-size: 0.8rem; color: #999;">15 minutes ago by Dr. John Smith</div>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ffc107;">
                            <div style="background: #ffc107; color: #212529; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #856404;">Follow-up Scheduled</div>
                                <div style="font-size: 0.9rem; color: #666;">Pedro Mendoza - Next visit: December 16, 2024</div>
                                <div style="font-size: 0.8rem; color: #999;">30 minutes ago by Dr. John Smith</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Page-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Clinical Encounter Module loaded');
            console.log('Current user:', '<?= $employee_name ?> (<?= $employee_role ?>)');
            
            // Auto-refresh statistics every 30 seconds
            setInterval(refreshStats, 30000);
        });
        
        function refreshStats() {
            // In a real application, this would fetch updated statistics from the server
            console.log('Refreshing encounter statistics...');
        }
        
        function generateReport() {
            alert('Generate Clinical Encounter Report\n\nThis feature would open a comprehensive reporting interface allowing you to:\n\n• Select date ranges\n• Filter by departments or doctors\n• Choose report formats (PDF, Excel)\n• Include statistical analysis\n• Export encounter summaries');
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+T for new triage
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                window.location.href = 'triage.php';
            }
            
            // Ctrl+E for new encounter
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'doctor-encounter.php';
            }
            
            // Ctrl+H for history
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = 'encounter-history.php';
            }
        });
    </script>
</body>
</html>