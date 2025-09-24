<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CHO Koronadal — Patient Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Reuse your existing styles -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>

    <!-- Include the sidebar -->
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'dashboard';
    include '../includes/sidebar_patient.php';
    ?>

    <!-- Main content area -->
    <section class='homepage'>
        <div class="profile-heading-bar"
            style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1em;margin-bottom:1.5em;">
            <h1 style="margin:0;font-size:2.2em;letter-spacing:1px;">APPOINTMENTS & REFFERALS</h1>
            <div class="utility-btn-group" style="display:flex;gap:0.7em;flex-wrap:wrap;">
                <button class="utility-btn" onclick="downloadPatientFile()" title="Download Patient File"
                    style="background:#2980b9;color:#fff;border:none;padding:0.6em 1.2em;border-radius:6px;font-weight:600;display:flex;align-items:center;gap:0.5em;box-shadow:0 2px 8px rgba(41,128,185,0.08);cursor:pointer;transition:background 0.18s;">
                        <i class="fas fa-calendar-plus"></i> <span class="hide-on-mobile">Create Appointment</span>
                    </button>
                <button class="utility-btn" onclick="downloadPatientID()" title="Download Patient ID Card"
                    style="background:#16a085;color:#fff;border:none;padding:0.6em 1.2em;border-radius:6px;font-weight:600;display:flex;align-items:center;gap:0.5em;box-shadow:0 2px 8px rgba(22,160,133,0.08);cursor:pointer;transition:background 0.18s;">
                    <i class="fas fa-id-card"></i> <span class="hide-on-mobile">Download ID Card</span>
                </button>
                <button class="utility-btn" onclick="openUserSettings()" title="User Settings"
                    style="background:#f39c12;color:#fff;border:none;padding:0.6em 1.2em;border-radius:6px;font-weight:600;display:flex;align-items:center;gap:0.5em;box-shadow:0 2px 8px rgba(243,156,18,0.08);cursor:pointer;transition:background 0.18s;">
                    <i class="fas fa-cog"></i> <span class="hide-on-mobile">User Settings</span>
                </button>
            </div>
        </div>
    </section>
</body>
</html>