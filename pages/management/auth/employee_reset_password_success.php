<?php
// Employee password reset success page
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Include employee session configuration
require_once __DIR__ . '/../../../config/session/employee_session.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Redirect if already logged in
if (!empty($_SESSION['employee_id'])) {
    $role = strtolower($_SESSION['role']);
    header('Location: ../' . $role . '/dashboard.php');
    exit;
}

// Handle flash messages
$sessionFlash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flash = $sessionFlash;

// Auto redirect after 10 seconds
$autoRedirect = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful - CHO Employee Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/login.css">
    <style>
        /* Success styling */
        .success-icon {
            font-size: 4rem;
            color: #16a34a;
            text-align: center;
            margin-bottom: 20px;
        }

        .success-message {
            text-align: center;
            color: #16a34a;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .countdown {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin: 15px 0;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }

        .success-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 30px 25px;
            margin: 20px 0;
        }

        .tips {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .tips h4 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 0.95rem;
        }

        .tips ul {
            margin: 5px 0 0 0;
            padding-left: 20px;
        }

        .tips li {
            color: #374151;
            font-size: 0.85rem;
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="logo-container" role="banner">
            <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128"
                alt="City Health Office Koronadal logo" width="100" height="100" decoding="async" />
        </div>
    </header>

    <main class="homepage" id="main-content">
        <section class="login-box" aria-labelledby="success-title">
            <h1 id="success-title" class="visually-hidden">Password Reset Successful</h1>

            <div class="success-box">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h2 style="text-align: center; color: #16a34a; margin-bottom: 15px;">
                    Password Reset Successful!
                </h2>
                
                <p class="success-message">
                    Your password has been successfully updated. You can now log in with your new password.
                </p>

                <?php if ($autoRedirect): ?>
                <div id="countdown" class="countdown">
                    Redirecting to login page in <span id="timer">10</span> seconds...
                </div>
                <?php endif; ?>

                <div class="action-buttons" style="align-items: center; justify-content: center;">
                    <a href="employee_login.php" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </a>
                </div>
            </div>

            <div class="tips">
                <h4><i class="fas fa-lightbulb"></i> Security Tips</h4>
                <ul>
                    <li>Keep your password secure and don't share it with anyone</li>
                    <li>Use a unique password that you don't use elsewhere</li>
                    <li>Consider using a password manager</li>
                    <li>If you suspect unauthorized access, change your password immediately</li>
                </ul>
            </div>
        </section>
    </main>

    <script>
        <?php if ($autoRedirect): ?>
        // Auto redirect countdown
        (function() {
            let timeLeft = 10;
            const timerElement = document.getElementById('timer');
            
            function updateTimer() {
                timerElement.textContent = timeLeft;
                timeLeft--;
                
                if (timeLeft < 0) {
                    window.location.href = 'employee_login.php';
                } else {
                    setTimeout(updateTimer, 1000);
                }
            }
            
            updateTimer();
        })();
        <?php endif; ?>

        // Show flash message if available
        <?php if ($flash && $flash['msg']): ?>
        (function() {
            // Create temporary snackbar for flash message
            const snackbar = document.createElement('div');
            snackbar.id = 'temp-snackbar';
            snackbar.style.cssText = `
                position: fixed;
                left: 50%;
                bottom: 24px;
                transform: translateX(-50%);
                min-width: 260px;
                max-width: 92vw;
                padding: 12px 16px;
                border-radius: 10px;
                background: <?= $flash['type'] === 'error' ? '#dc2626' : '#16a34a' ?>;
                color: #fff;
                box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
                z-index: 9999;
                font: 600 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
                opacity: 0;
                transition: opacity .25s ease;
            `;
            
            snackbar.textContent = <?= json_encode($flash['msg']) ?>;
            document.body.appendChild(snackbar);
            
            // Show and auto-hide
            setTimeout(() => { snackbar.style.opacity = '1'; }, 100);
            setTimeout(() => { 
                snackbar.style.opacity = '0';
                setTimeout(() => snackbar.remove(), 250);
            }, 4000);
        })();
        <?php endif; ?>
    </script>
</body>
</html>