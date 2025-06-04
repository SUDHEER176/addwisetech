<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

session_start();

$host = "localhost";
$user = "root";
$password = "";
$dbname = "addwise";
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection error.");
}
$conn->set_charset("utf8mb4");

$error = "";
$success = "";

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

$email = $_SESSION['reset_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = trim($_POST["otp"]);
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    if (empty($otp) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT otp_code, otp_expires FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($db_otp, $db_expires);
        if ($stmt->fetch()) {
            $stmt->close(); // Close after fetching, before any possible return/exit
            if ($otp !== $db_otp) {
                $error = "Invalid OTP code.";
            } elseif (strtotime($db_expires) < time()) {
                $error = "OTP has expired. Please request a new one.";
            } else {
                // Update password and clear OTP
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expires = NULL WHERE email = ?");
                $update->bind_param("ss", $hashed_password, $email);
                if ($update->execute()) {
                    $success = "Password reset successful! You can now <a href='login.php'>sign in</a>.";
                    unset($_SESSION['reset_email']);
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
                $update->close();
            }
        } else {
            $stmt->close();
            $error = "Invalid request.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AddWise - Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --success-color: #059669;
            --error-color: #dc2626;
            --warning-color: #d97706;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-tertiary: #f3f4f6;
            --border-color: #d1d5db;
            --border-light: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            display: flex;
            background: var(--bg-primary);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            border: 1px solid var(--border-light);
        }
        .welcome-section {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .welcome-content {
            position: relative;
            z-index: 2;
        }
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        .form-section {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .form-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .form-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.875rem;
        }
        .input-wrapper {
            position: relative;
        }
        .form-input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s ease;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-input:valid:not(:placeholder-shown) {
            border-color: var(--success-color);
        }
        .form-input.error {
            border-color: var(--error-color);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 16px;
            pointer-events: none;
            transition: color 0.2s ease;
        }
        .input-wrapper:focus-within .input-icon {
            color: var(--primary-color);
        }
        .submit-btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .submit-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }
        .success {
            color: #065f46;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }
        .footer-links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
            color: var(--text-secondary);
            font-size: 14px;
        }
        .footer-links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .footer-links a:hover {
            color: var(--primary-dark);
        }
        .field-error {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
        .field-error.show {
            display: block;
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; max-width: 500px; }
            .welcome-section { padding: 40px 30px; min-height: 300px; }
            .welcome-title { font-size: 2rem; }
            .form-section { padding: 40px 30px; }
        }
        @media (max-width: 480px) {
            body { padding: 10px; }
            .welcome-section { padding: 30px 20px; }
            .form-section { padding: 30px 20px; }
            .welcome-title { font-size: 1.8rem; }
            .form-title { font-size: 1.5rem; }
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #f9fafb;
                --text-secondary: #d1d5db;
                --text-light: #9ca3af;
                --bg-primary: #1f2937;
                --bg-secondary: #374151;
                --bg-tertiary: #4b5563;
                --border-color: #4b5563;
                --border-light: #374151;
            }
            body { background: #111827; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Side - Welcome Section -->
        <div class="welcome-section" style="display: flex; align-items: center; justify-content: center;">
            <div class="welcome-content" style="width:100%;">
                <h1 class="welcome-title" style="font-size:2rem; text-align:center; margin:0 auto;">
                    Enter the OTP sent to your email and set a new password.
                </h1>
            </div>
        </div>
        <!-- Right Side - Form Section -->
        <div class="form-section">
            <div class="form-header">
                <h2 class="form-title">Reset Password</h2>
                <p class="form-subtitle">Check your email for the OTP code.</p>
            </div>
            <?php if ($error): ?>
                <div class="message error">
                    <span>⚠️</span>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="message success">
                    <span>✅</span>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if (!$success): ?>
            <form method="POST" action="" id="resetForm" novalidate>
                <div class="form-group">
                    <label for="otp" class="form-label">OTP Code</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔑</span>
                        <input 
                            type="text" 
                            id="otp"
                            name="otp" 
                            class="form-input"
                            placeholder="Enter OTP"
                            required 
                            autocomplete="one-time-code"
                        >
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            id="new_password"
                            name="new_password" 
                            class="form-input"
                            placeholder="Enter new password"
                            required 
                            autocomplete="new-password"
                        >
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            id="confirm_password"
                            name="confirm_password" 
                            class="form-input"
                            placeholder="Confirm new password"
                            required 
                            autocomplete="new-password"
                        >
                    </div>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span>Reset Password</span>
                </button>
            </form>
            <?php endif; ?>
            <div class="footer-links">
                <p>Back to <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>
    <script>
        function autoHideMessages() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    msg.style.transform = 'translateY(-10px)';
                    setTimeout(() => msg.remove(), 300);
                }, 5000);
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            autoHideMessages();
        });
    </script>
</body>
</html>