<?php
session_start();

// Check if there's a flash message in the session
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);  // Clear the flash message after it's used
} else {
    // Default message if no flash is set
    $flash = ['type' => 'fail', 'msg' => 'Unexpected error occurred.'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Password Reset Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --brand: #007bff;
            --brand-600: #0056b3;
            --text: #03045e;
            --muted: #6c757d;
            --border: #ced4da;
            --surface: #ffffff;
            --shadow: 0 8px 20px rgba(0, 0, 0, .15);
            --focus-ring: 0 0 0 3px rgba(0, 123, 255, .25);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: #f8fafc;
            color: #222;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-image: url('https://ik.imagekit.io/wbhsmslogo/Blue%20Minimalist%20Background.png?updatedAt=1752410073954');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 14px 0
        }

        .logo {
            width: 100px;
            height: auto
        }

        .homepage {
            min-height: 100vh;
            display: grid;
            place-items: start center;
            padding: 160px 16px 40px
        }

        .form-box {
            width: 100%;
            min-width: 350px;
            max-width: 600px;
            background: var(--surface);
            border-radius: 16px;
            padding: 20px 22px 26px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative
        }


        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
            padding: 36px 32px 28px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        .success {
            color: #006400;
        }

        .fail {
            color: #b91c1c;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            margin-top: 18px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            background: #007bff;
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.13s;
            text-decoration: none;
        }

        .btn:hover {
            background: #0056b3;
        }

        .contact-info {
            margin: 22px 0 10px;
            text-align: left;
            font-size: 1.07em;
        }

        .contact-info .row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .contact-info .row i {
            font-size: 1.3em;
            color: #007bff;
            min-width: 28px;
            text-align: center;
        }

        .countdown {
            font-size: 1.09em;
            color: #1e40af;
            margin-top: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo-container">
            <a href="../../index.php" tabindex="0">
                <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128" alt="CHO Koronadal Logo" />
            </a>
        </div>
    </header>

    <section class="homepage">
        <div class="container">
            <?php if ($flash['type'] === 'success'): ?>
                <div class="icon success"><i class="fa-solid fa-circle-check"></i></div>
                <h2 class="success">Password Changed Successfully!</h2>
                <p>Your password has been updated. You can now log in with your new password.</p>
                <div class="countdown">
                    Redirecting to login page in <span id="timer">10</span> seconds...
                </div>
                <a href="patient_login.php" class="btn" id="backBtn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
                <script>
                    let seconds = 10;
                    const timerSpan = document.getElementById('timer');
                    const loginUrl = 'patient_login.php';
                    const countdown = setInterval(() => {
                        seconds--;
                        timerSpan.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(countdown);
                            window.location.href = loginUrl;
                        }
                    }, 1000);
                    document.getElementById('backBtn').addEventListener('click', function(e) {
                        window.location.href = loginUrl;
                    });
                </script>
            <?php else: ?>
                <div class="icon fail"><i class="fa-solid fa-circle-xmark"></i></div>
                <h2 class="fail">Password Change Failed</h2>
                <p>We're sorry, but your password could not be changed.</p>
                <p>For further assistance, please contact your administrator.</p>
                <div class="contact-info">
                    <div class="row">
                        <i class="fa-solid fa-phone"></i>
                        <span>(083) 228 2293</span>
                    </div>
                    <div class="row">
                        <i class="fa-solid fa-envelope"></i>
                        <span>cityhealthofficeofkoronadal@gmail.com</span>
                    </div>
                </div>
                <a href="patient_login.php" class="btn" id="failBackBtn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
                <script>
                    document.getElementById('failBackBtn').addEventListener('click', function(e) {
                        window.location.href = 'patient_login.php';
                    });
                </script>
            <?php endif; ?>
        </div>
    </section>
</body>

</html>
