<?php
session_start();

// Get username from session (set during OTP verification)
$username = isset($_SESSION['registration_username']) ? htmlspecialchars($_SESSION['registration_username']) : null;

// Clear the session data since registration is complete
if (isset($_SESSION['registration_username'])) {
    unset($_SESSION['registration_username']);
}
if (isset($_SESSION['registration_otp'])) {
    unset($_SESSION['registration_otp']);
}
if (isset($_SESSION['registration_data'])) {
    unset($_SESSION['registration_data']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registration Success</title>
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

        * {
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

        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
            padding: 36px 32px 28px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        .success {
            color: #006400;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #006400;
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

        .credential-box {
            margin: 18px 0;
            padding: 14px;
            border: 2px dashed #007bff;
            border-radius: 8px;
            background: #f0f9ff;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3a8a;
        }

        .countdown {
            font-size: 1.05em;
            color: #1e40af;
            margin-top: 12px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo-container">
            <img class="logo" src="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128" alt="CHO Koronadal Logo" />
        </div>
    </header>

    <section class="homepage">
        <div class="container">
            <?php if ($username): ?>
                <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
                <h2 class="success">Registration Successful!</h2>
                <p>Your account has been created successfully. Please use the following username to log in:</p>
                <div class="credential-box">
                    <i class="fa-solid fa-user"></i> <?= $username ?>
                </div>
                <div class="countdown">
                    Redirecting to login page in <span id="timer">10</span> seconds...
                </div>
                <a href="../auth/patient_login.php" class="btn" id="backBtn">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Back to Login
                </a>
                <script>
                    let seconds = 10;
                    const timerSpan = document.getElementById('timer');
                    const loginUrl = '../auth/patient_login.php';
                    const countdown = setInterval(() => {
                        seconds--;
                        timerSpan.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(countdown);
                            window.location.href = loginUrl;
                        }
                    }, 1000);
                    document.getElementById('backBtn').addEventListener('click', () => {
                        window.location.href = loginUrl;
                    });
                </script>
            <?php else: ?>
                <div class="icon" style="color:#b91c1c"><i class="fa-solid fa-circle-xmark"></i></div>
                <h2 style="color:#b91c1c">Registration Failed</h2>
                <p>We could not retrieve your username. Please try registering again.</p>
                <a href="patient_registration.php" class="btn">
                    <i class="fa-solid fa-user-plus"></i> Try Again
                </a>
            <?php endif; ?>
        </div>
    </section>
</body>

</html>