<?php
session_start();

// Redirect to super admin dashboard if already logged in
if (isset($_SESSION['super_admin_id'])) {
    header("Location: super-admin-dashboard.php");
    exit();
}

// Enhanced security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "addwise";

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Database connection error. Please try again later.");
}

$error = "";
$success = "";

// Rate limiting
if (!isset($_SESSION['super_admin_login_attempts'])) {
    $_SESSION['super_admin_login_attempts'] = 0;
    $_SESSION['super_admin_last_attempt'] = time();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check rate limiting
    if ($_SESSION['super_admin_login_attempts'] >= 3 && (time() - $_SESSION['super_admin_last_attempt']) < 300) {
        $error = "Too many login attempts. Please try again in 5 minutes.";
    } else {
        $username = filter_var(trim($_POST["username"]), FILTER_SANITIZE_STRING);
        $password = trim($_POST["password"]);

        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            // Hardcoded super admin check
            if ($username === "admin" && $password === "admin123") {
                // Successful login
                $_SESSION['super_admin_id'] = 1;
                $_SESSION['super_admin_username'] = "admin";
                $_SESSION['super_admin_login_time'] = time();
                $_SESSION['super_admin_login_attempts'] = 0;
                
                session_regenerate_id(true);
                header("Location: super-admin-dashboard.php");
                exit();
            } else {
                // Check in super_admins table
                $sql = "SELECT id, username, password, is_active FROM super_admins WHERE username = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        if (!$row['is_active']) {
                            $error = "Your account has been deactivated. Please contact support.";
                        } elseif (password_verify($password, $row['password'])) {
                            // Successful login
                            $_SESSION['super_admin_id'] = $row['id'];
                            $_SESSION['super_admin_username'] = $row['username'];
                            $_SESSION['super_admin_login_time'] = time();
                            $_SESSION['super_admin_login_attempts'] = 0;
                            
                            // Update last login
                            $update_sql = "UPDATE super_admins SET last_login = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("i", $row['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            session_regenerate_id(true);
                            header("Location: super-admin-dashboard.php");
                            exit();
                        } else {
                            $error = "Invalid username or password.";
                            $_SESSION['super_admin_login_attempts']++;
                            $_SESSION['super_admin_last_attempt'] = time();
                        }
                    } else {
                        $error = "Invalid username or password.";
                        $_SESSION['super_admin_login_attempts']++;
                        $_SESSION['super_admin_last_attempt'] = time();
                    }
                    $stmt->close();
                } else {
                    $error = "Database error. Please try again.";
                }
            }
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
    <title>AddWise - Super Admin Login</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        /* Left Side - Welcome Section */
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

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .brand-name {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .features-list {
            list-style: none;
            margin-bottom: 40px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 1rem;
            opacity: 0.95;
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        .stats-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Right Side - Form Section */
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

        /* Enhanced Form Styling */
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

        /* Input Icons */
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

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
            border-radius: var(--radius-sm);
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--text-secondary);
        }

        /* Submit Button */
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

        /* Loading State */
        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .submit-btn.loading span {
            opacity: 0;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Google Button */
        .google-btn {
            width: 100%;
            padding: 12px 20px;
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .google-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--text-secondary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .google-icon {
            width: 18px;
            height: 18px;
        }

        /* Divider */
        .divider {
            text-align: center;
            margin: 24px 0;
            position: relative;
            color: var(--text-light);
            font-size: 13px;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-light);
        }

        .divider span {
            background: var(--bg-primary);
            padding: 0 16px;
        }

        /* Messages */
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Additional Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }

        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-password:hover {
            color: var(--primary-dark);
        }

        /* Footer Links */
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

        /* Validation Messages */
        .field-error {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .field-error.show {
            display: block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                max-width: 500px;
            }

            .welcome-section {
                padding: 40px 30px;
                min-height: 300px;
            }

            .welcome-title {
                font-size: 2rem;
            }

            .stats-container {
                gap: 20px;
            }

            .form-section {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .welcome-section {
                padding: 30px 20px;
            }

            .form-section {
                padding: 30px 20px;
            }

            .welcome-title {
                font-size: 1.8rem;
            }

            .form-title {
                font-size: 1.5rem;
            }

            .stats-container {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Dark mode support */
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

            body {
                background: #111827;
            }
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus indicators */
        .google-btn:focus-visible,
        .form-input:focus-visible,
        .submit-btn:focus-visible,
        .forgot-password:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Left Back Button -->
        
        <!-- Left Side - Welcome Section -->
        <div class="welcome-section" style="display: flex; align-items: center; justify-content: center;">
            <div class="welcome-content" style="width:100%;">
                <h1 class="welcome-title" style="font-size:2rem; text-align:center; margin:0 auto;">
                    Welcome, Super Admin!
                </h1>
                <p class="welcome-subtitle" style="text-align:center;">
                    Manage users, settings, and more with full control over the AddWise platform.
                </p>
            </div>
        </div>
        <!-- Right Side - Form Section -->
        <div class="form-section">
            <div class="form-header">
                <h1 class="form-title">Super Admin Login</h1>
                <p class="form-subtitle">Enter your credentials to access the admin panel</p>
            </div>

            <?php if ($error): ?>
                <div class="message error">
                    <span>⚠️</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success">
                    <span>✅</span>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input 
                            type="text" 
                            id="username"
                            name="username" 
                            class="form-input"
                            placeholder="Enter your username"
                            required 
                            autocomplete="username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            id="password"
                            name="password" 
                            class="form-input"
                            placeholder="Enter your password"
                            required 
                            autocomplete="current-password"
                        >
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    Sign In
                </button>
                 <div class="footer-links">
                Not an super admin? <a href="login.php">Go to User Login</a>
            </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = form.querySelector('.submit-btn');

            form.addEventListener('submit', function(e) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Signing in...';
            });
        });
    </script>
</body>
</html>