<?php
/**
 * Queueing - Public Display Selector Page
 * Purpose: Launcher interface for all public display pages for admins to open on separate monitors
 */

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Check if role is authorized for admin functions only
if (strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../management/admin/dashboard.php');
    exit();
}

require_once $root_path . '/config/db.php';

// Define the 6 relevant station types and their display file mappings
$station_types = [
    'triage' => [
        'name' => 'Triage Station',
        'icon' => 'fas fa-user-md',
        'file' => 'public_display_triage.php'
    ],
    'consultation' => [
        'name' => 'Consultation',
        'icon' => 'fas fa-stethoscope', 
        'file' => 'public_display_consultation.php'
    ],
    'lab' => [
        'name' => 'Laboratory',
        'icon' => 'fas fa-microscope',
        'file' => 'public_display_lab.php'
    ],
    'pharmacy' => [
        'name' => 'Pharmacy',
        'icon' => 'fas fa-pills',
        'file' => 'public_display_pharmacy.php'
    ],
    'billing' => [
        'name' => 'Billing',
        'icon' => 'fas fa-file-invoice-dollar',
        'file' => 'public_display_billing.php'
    ],
    'document' => [
        'name' => 'Document Processing',
        'icon' => 'fas fa-file-alt',
        'file' => 'public_display_document.php'
    ]
];

// Fetch stations data with assignments for today
$today = date('Y-m-d');
$station_types_list = "'" . implode("','", array_keys($station_types)) . "'";

$query = "
    SELECT 
        s.station_id,
        s.station_name,
        s.station_type,
        s.is_open,
        s.is_active,
        sv.name as service_name,
        CONCAT(e.first_name, ' ', e.last_name) as assigned_employee,
        r.role_name as employee_role,
        asch.schedule_id
    FROM stations s
    LEFT JOIN services sv ON s.service_id = sv.service_id
    LEFT JOIN assignment_schedules asch ON s.station_id = asch.station_id 
        AND asch.start_date <= ? 
        AND (asch.end_date IS NULL OR asch.end_date >= ?)
        AND asch.is_active = 1
    LEFT JOIN employees e ON asch.employee_id = e.employee_id
    LEFT JOIN roles r ON e.role_id = r.role_id
    WHERE s.station_type IN ($station_types_list)
        AND s.is_active = 1
    ORDER BY 
        FIELD(s.station_type, 'triage', 'consultation', 'lab', 'pharmacy', 'billing', 'document')
";

$stmt = $pdo->prepare($query);
$stmt->execute([$today, $today]);
$stations_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stations_data = [];

foreach ($stations_result as $row) {
    $stations_data[$row['station_type']] = $row;
}

// Set active page for sidebar highlighting
$activePage = 'queue_management';

$current_datetime = date('F j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Display Launcher | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Public Display Selector specific styles - MATCHING CHO THEME */
        .public-display-container {
            /* CHO Theme Variables - Matching dashboard.php */
            --cho-primary: #0077b6;
            --cho-primary-dark: #03045e;
            --cho-secondary: #6c757d;
            --cho-success: #2d6a4f;
            --cho-info: #17a2b8;
            --cho-warning: #ffc107;
            --cho-danger: #d00000;
            --cho-light: #f8f9fa;
            --cho-border: #dee2e6;
            --cho-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --cho-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --cho-border-radius: 0.5rem;
            --cho-border-radius-lg: 1rem;
            --cho-transition: all 0.3s ease;
        }

        /* Breadcrumb Navigation - exactly matching dashboard */
        .public-display-container .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--cho-secondary);
        }

        .public-display-container .breadcrumb a {
            color: var(--cho-primary);
            text-decoration: none;
            transition: var(--cho-transition);
        }

        .public-display-container .breadcrumb a:hover {
            color: var(--cho-primary-dark);
            text-decoration: underline;
        }

        /* Page header styling - exactly matching dashboard */
        .public-display-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            padding: 25px;
            border-radius: var(--cho-border-radius-lg);
            box-shadow: var(--cho-shadow-lg);
        }

        .public-display-container .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .public-display-container .page-header .refresh-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 20px;
            border-radius: var(--cho-border-radius);
            cursor: pointer;
            transition: var(--cho-transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .public-display-container .page-header .refresh-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-2px);
        }

        /* Intro text styling */
        .public-display-container .intro-text {
            background: var(--cho-light);
            padding: 20px;
            border-radius: var(--cho-border-radius);
            border-left: 4px solid var(--cho-primary);
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
        }

        /* Station Cards Grid Layout */
        .stations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .station-card {
            background: white;
            border: 2px solid var(--cho-border);
            border-radius: var(--cho-border-radius-lg);
            padding: 30px;
            box-shadow: var(--cho-shadow);
            transition: var(--cho-transition);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 220px;
        }

        .station-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--cho-shadow-lg);
            border-color: var(--cho-primary);
        }

        .station-icon {
            font-size: 48px;
            color: var(--cho-primary);
            margin-bottom: 20px;
            display: block;
        }

        .station-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--cho-primary-dark);
            margin: 0 0 25px 0;
            text-align: center;
            line-height: 1.3;
        }

        /* Open Display Button */
        .open-display-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: var(--cho-border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--cho-transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .open-display-btn:hover {
            background: linear-gradient(135deg, var(--cho-primary-dark) 0%, #001d3d 100%);
            transform: translateY(-2px);
            box-shadow: var(--cho-shadow-lg);
            color: white;
            text-decoration: none;
        }

        /* Footer styling */
        .footer-info {
            text-align: center;
            color: var(--cho-secondary);
            font-size: 14px;
            padding: 20px;
            border-top: 2px solid var(--cho-light);
            background: #fafafa;
            border-radius: var(--cho-border-radius);
        }

        /* Responsive design - matching dashboard */
        @media (max-width: 768px) {
            .stations-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .public-display-container .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .station-card {
                padding: 25px 20px;
                min-height: 200px;
            }
            
            .station-icon {
                font-size: 40px;
            }
            
            .station-name {
                font-size: 20px;
            }
        }

        @media (max-width: 576px) {
            .stations-grid {
                grid-template-columns: 1fr;
            }
            
            .station-card {
                padding: 25px 15px;
                min-height: 180px;
            }
            
            .station-name {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/sidebar_admin.php'; ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Include topbar -->
            <?php include '../../includes/topbar.php'; ?>
            
            <div class="card">
                <div class="public-display-container">
                    <!-- Breadcrumb Navigation - matching dashboard -->
                    <div class="breadcrumb" style="margin-top: 50px;">
                        <a href="../management/admin/dashboard.php">Admin Dashboard</a>
                        <span>›</span>
                        <a href="dashboard.php">Queue Management Dashboard</a>
                        <span>›</span>
                        <span>Public Display Launcher</span>
                    </div>

                    <!-- Page Header with Action Buttons -->
                    <div class="page-header">
                        <h1>
                            <i class="fas fa-tv"></i>
                            Public Display Launcher
                        </h1>
                        <div style="display: flex; gap: 10px;">
                            <a href="javascript:void(0)" class="refresh-btn" onclick="openAllDisplays()">
                                <i class="fas fa-external-link-alt"></i>Open All Displays
                            </a>
                            <a href="javascript:void(0)" class="refresh-btn" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt"></i>Refresh Status
                            </a>
                        </div>
                    </div>

                    <!-- Intro Text -->
                    <div class="intro-text">
                        <strong>Select a display to open on a monitor.</strong> Active stations show their current status below. 
                        Each display opens in a new window/tab for use on separate monitors in waiting areas.
                    </div>

                    <!-- Stations Grid -->
                    <div class="stations-grid">
                        <?php foreach ($station_types as $type => $config): ?>
                            <div class="station-card">
                                <i class="station-icon <?php echo $config['icon']; ?>"></i>
                                <h3 class="station-name"><?php echo htmlspecialchars($config['name']); ?></h3>
                                <a href="#" onclick="openPublicDisplay('<?php echo $config['file']; ?>', '<?php echo $config['name']; ?>'); return false;" class="open-display-btn">
                                    <i class="fas fa-external-link-alt"></i>
                                    Open Display
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer Info -->
                    <div class="footer-info">
                        <i class="fas fa-clock"></i>
                        Last updated: <?php echo $current_datetime; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Track opened display windows
        let openDisplayWindows = {};
        
        // Function to open public displays in popup windows
        function openPublicDisplay(displayFile, stationName) {
            // Check if this display is already open
            if (openDisplayWindows[displayFile] && !openDisplayWindows[displayFile].closed) {
                openDisplayWindows[displayFile].focus();
                showAlert(`${stationName} display is already open`, 'info');
                return;
            }
            
            // Calculate optimal window size for different screen sizes
            const screenWidth = window.screen.width;
            const screenHeight = window.screen.height;
            
            let windowWidth, windowHeight;
            
            // Responsive window sizing
            if (screenWidth <= 768) {
                // Mobile/small screens - fullscreen
                windowWidth = screenWidth;
                windowHeight = screenHeight;
            } else if (screenWidth <= 1024) {
                // Tablets - 90% of screen
                windowWidth = Math.floor(screenWidth * 0.9);
                windowHeight = Math.floor(screenHeight * 0.9);
            } else {
                // Desktop - optimized for public displays (larger)
                windowWidth = Math.floor(screenWidth * 0.85);
                windowHeight = Math.floor(screenHeight * 0.85);
            }
            
            // Center the window
            const left = Math.floor((screenWidth - windowWidth) / 2);
            const top = Math.floor((screenHeight - windowHeight) / 2);
            
            // Window features optimized for public displays
            const windowFeatures = [
                `width=${windowWidth}`,
                `height=${windowHeight}`,
                `left=${left}`,
                `top=${top}`,
                'resizable=yes',
                'scrollbars=auto',
                'toolbar=no',
                'menubar=no',
                'location=no',
                'status=no',
                'titlebar=yes'
            ].join(',');
            
            try {
                // Open the public display window
                const displayWindow = window.open(
                    displayFile,
                    `PublicDisplay_${displayFile.replace('.php', '')}`,
                    windowFeatures
                );
                
                if (displayWindow) {
                    // Store reference to the window
                    openDisplayWindows[displayFile] = displayWindow;
                    
                    // Focus on the new window
                    displayWindow.focus();
                    
                    // Set window title after load
                    displayWindow.addEventListener('load', function() {
                        try {
                            displayWindow.document.title = `${stationName} Queue Display - CHO Koronadal`;
                        } catch (e) {
                            // Cross-origin restriction, ignore
                        }
                    });
                    
                    // Handle window close event
                    displayWindow.addEventListener('beforeunload', function() {
                        console.log(`${stationName} display window closing`);
                        delete openDisplayWindows[displayFile];
                        updateDisplayButtonState(displayFile, false);
                    });
                    
                    // Show success message
                    showAlert(`${stationName} display opened successfully`, 'success');
                    
                    // Update button appearance to show active state
                    updateDisplayButtonState(displayFile, true);
                    
                } else {
                    throw new Error('Popup was blocked');
                }
                
            } catch (error) {
                console.error(`Error opening ${stationName} display:`, error);
                showAlert(`Could not open ${stationName} display. Please check popup settings and try again.`, 'error');
            }
        }
        
        // Function to update button appearance based on display state
        function updateDisplayButtonState(displayFile, isOpen) {
            const displayButton = document.querySelector(`a[onclick*="${displayFile}"]`);
            if (displayButton) {
                if (isOpen) {
                    displayButton.style.borderColor = '#28a745';
                    displayButton.style.backgroundColor = '#f8fff9';
                    displayButton.style.transform = 'scale(0.98)';
                } else {
                    displayButton.style.borderColor = '';
                    displayButton.style.backgroundColor = '';
                    displayButton.style.transform = '';
                }
            }
        }
        
        // Function to show alert messages with enhanced DOM insertion
        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                max-width: 500px;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                font-weight: 500;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                background: ${type === 'success' ? '#d1edda' : type === 'error' ? '#f8d7da' : type === 'warning' ? '#fff3cd' : '#d1ecf1'};
                color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : type === 'warning' ? '#856404' : '#0c5460'};
                border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : type === 'warning' ? '#ffeaa7' : '#bee5eb'};
            `;
            
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'}" style="margin-right: 10px;"></i>
                ${message}
                <button type="button" style="
                    background: none;
                    border: none;
                    font-size: 18px;
                    font-weight: bold;
                    color: inherit;
                    cursor: pointer;
                    float: right;
                    margin-left: 15px;
                    padding: 0;
                    line-height: 1;
                " onclick="this.parentElement.remove();">&times;</button>
            `;
            
            // Add to body
            document.body.appendChild(alertDiv);
            
            // Animate in
            setTimeout(() => {
                alertDiv.style.opacity = '1';
                alertDiv.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto-remove alert after 6 seconds
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }, 6000);
        }
        
        // Check display status periodically and clean up closed windows
        setInterval(function() {
            Object.keys(openDisplayWindows).forEach(displayFile => {
                if (openDisplayWindows[displayFile] && openDisplayWindows[displayFile].closed) {
                    delete openDisplayWindows[displayFile];
                    updateDisplayButtonState(displayFile, false);
                }
            });
        }, 5000);
        
        // Open all displays function (enhanced sequential opening to bypass popup blockers)
        function openAllDisplays() {
            // Show instructions dialog
            const userConfirmed = confirm(
                "This will open all 6 station displays one by one.\n\n" +
                "For best results:\n" +
                "• Allow popups when prompted by your browser\n" +
                "• Click 'Always allow' if asked\n" +
                "• Wait for each window to open before the next one\n\n" +
                "Click 'OK' to start the process."
            );
            
            if (!userConfirmed) {
                showAlert('Operation cancelled by user', 'info');
                return;
            }
            
            const displayFiles = [
                { file: 'public_display_triage.php', name: 'Triage Station' },
                { file: 'public_display_consultation.php', name: 'Consultation' },
                { file: 'public_display_lab.php', name: 'Laboratory' },
                { file: 'public_display_pharmacy.php', name: 'Pharmacy' },
                { file: 'public_display_billing.php', name: 'Billing' },
                { file: 'public_display_document.php', name: 'Document Processing' }
            ];
            
            let currentIndex = 0;
            let successCount = 0;
            let failureCount = 0;
            
            // Show progress alert
            showAlert('Starting to open displays... Please allow popups when prompted.', 'info');
            
            function openNextDisplay() {
                if (currentIndex >= displayFiles.length) {
                    // All done - show final results
                    setTimeout(() => {
                        if (successCount === displayFiles.length) {
                            showAlert(`🎉 All ${successCount} displays opened successfully!`, 'success');
                        } else if (successCount > 0) {
                            showAlert(`✅ ${successCount} displays opened, ❌ ${failureCount} failed. You may need to enable popups.`, 'warning');
                        } else {
                            showAlert('❌ No displays could be opened. Please enable popups in your browser and try again.', 'error');
                        }
                    }, 500);
                    return;
                }
                
                const display = displayFiles[currentIndex];
                currentIndex++;
                
                try {
                    // Check if this display is already open
                    if (openDisplayWindows[display.file] && !openDisplayWindows[display.file].closed) {
                        openDisplayWindows[display.file].focus();
                        successCount++;
                        showAlert(`${display.name} was already open - focused existing window`, 'info');
                        // Continue to next display after short delay
                        setTimeout(openNextDisplay, 800);
                        return;
                    }
                    
                    // Calculate window positioning with offset
                    const screenWidth = window.screen.width;
                    const screenHeight = window.screen.height;
                    const windowWidth = Math.floor(screenWidth * 0.75);
                    const windowHeight = Math.floor(screenHeight * 0.75);
                    
                    // Smart positioning to avoid overlap
                    const offsetX = ((currentIndex - 1) % 3) * 40; // 3 columns
                    const offsetY = Math.floor((currentIndex - 1) / 3) * 40; // 2 rows
                    const left = Math.floor((screenWidth - windowWidth) / 2) + offsetX;
                    const top = Math.floor((screenHeight - windowHeight) / 2) + offsetY;
                    
                    const windowFeatures = [
                        `width=${windowWidth}`,
                        `height=${windowHeight}`,
                        `left=${left}`,
                        `top=${top}`,
                        'resizable=yes',
                        'scrollbars=auto',
                        'toolbar=no',
                        'menubar=no',
                        'location=no',
                        'status=no',
                        'titlebar=yes'
                    ].join(',');
                    
                    // Open the display window with unique name to avoid conflicts
                    const displayWindow = window.open(
                        display.file,
                        `CHO_Display_${display.file.replace('.php', '').replace('public_display_', '')}_${Date.now()}`,
                        windowFeatures
                    );
                    
                    if (displayWindow) {
                        // Success
                        openDisplayWindows[display.file] = displayWindow;
                        
                        // Set title and handle events
                        displayWindow.addEventListener('load', function() {
                            try {
                                displayWindow.document.title = `${display.name} Queue Display - CHO Koronadal`;
                            } catch (e) {
                                // Cross-origin restriction, ignore
                            }
                        });
                        
                        displayWindow.addEventListener('beforeunload', function() {
                            delete openDisplayWindows[display.file];
                            updateDisplayButtonState(display.file, false);
                        });
                        
                        updateDisplayButtonState(display.file, true);
                        successCount++;
                        
                        showAlert(`✅ ${display.name} opened (${successCount}/${displayFiles.length})`, 'success');
                        
                        // Continue to next display after successful opening
                        setTimeout(openNextDisplay, 1200);
                        
                    } else {
                        // Popup was blocked
                        failureCount++;
                        showAlert(`❌ ${display.name} blocked by popup blocker (${currentIndex}/${displayFiles.length})`, 'error');
                        
                        // Continue to next display even if this one failed
                        setTimeout(openNextDisplay, 1000);
                    }
                    
                } catch (error) {
                    // Error occurred
                    console.error(`Error opening ${display.name} display:`, error);
                    failureCount++;
                    showAlert(`❌ Error opening ${display.name}: ${error.message}`, 'error');
                    
                    // Continue to next display even if this one failed
                    setTimeout(openNextDisplay, 1000);
                }
            }
            
            // Start the sequential opening process
            setTimeout(openNextDisplay, 500);
        }
    </script>
</body>
</html>