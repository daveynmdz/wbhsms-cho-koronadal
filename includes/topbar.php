<?php
/**
 * Reusable Topbar Component
 * 
 * Usage Examples:
 * 
 * For Employee Pages:
 * include '../../../includes/topbar.php';
 * renderTopbar([
 *     'title' => 'Create New Referral',
 *     'back_url' => 'referrals_management.php',
 *     'user_type' => 'employee'
 * ]);
 * 
 * For Patient Pages:
 * include '../../../includes/topbar.php';
 * renderTopbar([
 *     'title' => 'Edit Patient Profile',
 *     'back_url' => '../dashboard.php',
 *     'user_type' => 'patient'
 * ]);
 */

function formatRoleForDisplay($role) {
    $role = strtolower(trim($role));
    switch($role) {
        case 'admin':
            return 'Administrator';
        case 'doctor':
            return 'Doctor';
        case 'nurse':
            return 'Nurse';
        case 'bhw':
            return 'Barangay Health Worker';
        case 'dho':
            return 'District Health Officer';
        case 'records_officer':
            return 'Records Officer';
        case 'cashier':
            return 'Cashier';
        case 'laboratory_tech':
            return 'Laboratory Technician';
        case 'pharmacist':
            return 'Pharmacist';
        case 'patient':
            return 'Patient';
        default:
            // Fallback: replace underscores with spaces and capitalize each word
            return ucwords(str_replace('_', ' ', $role));
    }
}

function formatUserName($firstName, $lastName, $middleName = '') {
    $firstName = trim(ucfirst(strtolower($firstName)));
    $lastName = trim(ucfirst(strtolower($lastName)));
    $middleName = $middleName ? ' ' . trim(ucfirst(strtolower($middleName))) : '';
    
    return $firstName . $middleName . ' ' . $lastName;
}

function renderTopbar($options = []) {
    // Default options
    $defaults = [
        'title' => 'CHO Koronadal',
        'back_url' => 'dashboard.php',
        'user_type' => 'employee', // 'employee' or 'patient'
        'logo_clickable' => false,
        'css_path' => '../../../assets/css/topbar.css', // Relative path to topbar.css
        'vendor_path' => '../../../../vendor/' // Relative path to vendor directory
    ];
    
    $config = array_merge($defaults, $options);
    
    // Determine user information based on user type
    if ($config['user_type'] === 'patient') {
        // Patient user info
        $user_id = $_SESSION['patient_id'] ?? null;
        $first_name = $_SESSION['first_name'] ?? '';
        $last_name = $_SESSION['last_name'] ?? '';
        $middle_name = $_SESSION['middle_name'] ?? '';
        $role = 'Patient';
        $photo_param = 'patient_id';
    } else {
        // Employee user info
        $user_id = $_SESSION['employee_id'] ?? null;
        $first_name = $_SESSION['employee_first_name'] ?? '';
        $last_name = $_SESSION['employee_last_name'] ?? '';
        $middle_name = $_SESSION['employee_middle_name'] ?? '';
        $role = formatRoleForDisplay($_SESSION['role'] ?? '');
        $photo_param = 'employee_id';
    }
    
    // Format user name
    $user_name = formatUserName($first_name, $last_name, $middle_name);
    
    // Build logo link
    $logo_link = $config['logo_clickable'] ? $config['back_url'] : '#';
    $logo_style = $config['logo_clickable'] ? '' : 'pointer-events: none; cursor: default;';
    
    // Render the topbar HTML
    echo <<<HTML
    <!-- Top Bar -->
    <header class="topbar">
        <div>
            <a href="{$logo_link}" class="topbar-logo" style="{$logo_style}">
                <picture>
                    <source media="(max-width: 600px)"
                        srcset="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128">
                    <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527"
                        alt="City Health Logo" class="responsive-logo" />
                </picture>
            </a>
        </div>
        <div class="topbar-title" style="color: #ffffff;">{$config['title']}</div>
        <div class="topbar-userinfo">
            <div class="topbar-usertext">
                <strong style="color: #ffffff;">
                    {$user_name}
                </strong><br>
                <small style="color: #ffffff;">{$role}</small>
            </div>
            <img src="{$config['vendor_path']}photo_controller.php?{$photo_param}={$user_id}" alt="User Profile"
                class="topbar-userphoto"
                onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';" />
        </div>
    </header>
HTML;
}

/**
 * Render Back/Cancel Button with Modal
 * 
 * @param array $options Configuration options
 */
function renderBackButton($options = []) {
    $defaults = [
        'back_url' => 'dashboard.php',
        'button_text' => '← Back / Cancel',
        'modal_title' => 'Cancel Changes?',
        'modal_message' => 'Are you sure you want to go back/cancel? Unsaved changes will be lost.',
        'confirm_text' => 'Yes, Cancel',
        'stay_text' => 'Stay'
    ];
    
    $config = array_merge($defaults, $options);
    
    echo <<<HTML
    <div class="edit-profile-toolbar-flex">
        <button type="button" class="btn btn-cancel floating-back-btn" id="backCancelBtn">{$config['button_text']}</button>
        
        <!-- Custom Back/Cancel Confirmation Modal -->
        <div id="backCancelModal" class="custom-modal" style="display:none;">
            <div class="custom-modal-content">
                <h3>{$config['modal_title']}</h3>
                <p>{$config['modal_message']}</p>
                <div class="custom-modal-actions">
                    <button type="button" class="btn btn-danger" id="modalCancelBtn">{$config['confirm_text']}</button>
                    <button type="button" class="btn btn-secondary" id="modalStayBtn">{$config['stay_text']}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Back/Cancel modal logic
            const backBtn = document.getElementById('backCancelBtn');
            const modal = document.getElementById('backCancelModal');
            const modalCancel = document.getElementById('modalCancelBtn');
            const modalStay = document.getElementById('modalStayBtn');
            
            if (backBtn && modal && modalCancel && modalStay) {
                backBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                });
                
                modalCancel.addEventListener('click', function() {
                    modal.style.display = 'none';
                    window.location.href = "{$config['back_url']}";
                });
                
                modalStay.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.style.display = 'none';
                });
            }
        });
    </script>
HTML;
}

/**
 * Render Snackbar Notification System
 * 
 * Call this function to add snackbar support to any page
 */
function renderSnackbar() {
    echo <<<HTML
    <!-- Snackbar notification -->
    <div id="snackbar" style="display:none;position:fixed;left:50%;bottom:40px;transform:translateX(-50%);background:#323232;color:#fff;padding:1em 2em;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.18);font-size:1.1em;z-index:99999;opacity:0;transition:opacity 0.3s;">
        <span id="snackbar-text"></span>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Snackbar logic
HTML;
    
    if (isset($_SESSION['snackbar_message'])) {
        $message = json_encode($_SESSION['snackbar_message']);
        echo <<<HTML
            var snackbar = document.getElementById('snackbar');
            var snackbarText = document.getElementById('snackbar-text');
            if (snackbar && snackbarText) {
                snackbarText.textContent = {$message};
                snackbar.style.display = 'block';
                setTimeout(function() {
                    snackbar.style.opacity = '1';
                }, 100);
                setTimeout(function() {
                    snackbar.style.opacity = '0';
                    setTimeout(function() {
                        snackbar.style.display = 'none';
                    }, 400);
                }, 4000);
            }
HTML;
        unset($_SESSION['snackbar_message']);
    }
    
    echo <<<HTML
        });
    </script>
HTML;
}
?>