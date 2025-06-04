<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
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
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check rate limiting
    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 300) {
        $error = "Too many login attempts. Please try again in 5 minutes.";
    } else {
        $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST["password"]);

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $sql = "SELECT id, email, password, is_active, last_login FROM users WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    if (!$row['is_active']) {
                        $error = "Your account has been deactivated. Please contact support.";
                    } elseif (password_verify($password, $row['password'])) {
                        // Successful login
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['login_attempts'] = 0;
                        
                        // Update last login
                        $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("i", $row['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        session_regenerate_id(true);
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid email or password.";
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt'] = time();
                    }
                } else {
                    $error = "Invalid email or password.";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                }
                $stmt->close();
            } else {
                $error = "Database error. Please try again.";
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
    <title>AddWise - Sign In</title>
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
        <!-- Left Side - Welcome Section -->
        <div class="welcome-section" style="display: flex; align-items: center; justify-content: center;">
            <div class="welcome-content" style="width:100%;">
                <h1 class="welcome-title" style="font-size:2rem; text-align:center; margin:0 auto;">
                    Welcome to AddWise! Please sign in to continue.
                </h1>
            </div>
        </div>
        
        <!-- Right Side - Form Section -->
        <div class="form-section">
            <div class="form-header">
                <h2 class="form-title">Sign In</h2>
                <p class="form-subtitle">Welcome back! Please enter your details</p>
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
            
            <!-- Google Sign-in Button -->
            <a href="#" class="google-btn" onclick="signInWithGoogle()" role="button" aria-label="Sign in with Google">
                <svg class="google-icon" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <form method="POST" action="" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input 
                            type="email" 
                            id="email"
                            name="email" 
                            class="form-input"
                            placeholder="Enter your email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required 
                            autocomplete="email"
                        >
                    </div>
                    <div class="field-error" id="email-error"></div>
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
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                    <div class="field-error" id="password-error"></div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span>Sign In</span>
                </button>
            </form>
            
            <div class="footer-links">
                <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Enhanced JavaScript functionality
        class LoginForm {
            constructor() {
                this.form = document.getElementById('loginForm');
                this.submitBtn = document.getElementById('submitBtn');
                this.emailInput = document.getElementById('email');
                this.passwordInput = document.getElementById('password');
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupValidation();
                this.autoFocus();
                this.animateStats();
            }

            setupEventListeners() {
                // Form submission
                this.form.addEventListener('submit', (e) => this.handleSubmit(e));
                
                // Real-time validation
                this.emailInput.addEventListener('blur', () => this.validateEmail());
                this.passwordInput.addEventListener('blur', () => this.validatePassword());
                
                // Input improvements
                this.emailInput.addEventListener('input', () => this.clearError('email'));
                this.passwordInput.addEventListener('input', () => this.clearError('password'));
                
                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => this.handleKeyboard(e));
            }

            setupValidation() {
                // Email validation
                this.emailInput.addEventListener('input', () => {
                    if (this.emailInput.value && this.isValidEmail(this.emailInput.value)) {
                        this.emailInput.classList.remove('error');
                    }
                });

                // Password validation
                this.passwordInput.addEventListener('input', () => {
                    if (this.passwordInput.value.length >= 6) {
                        this.passwordInput.classList.remove('error');
                    }
                });
            }

            validateEmail() {
                const email = this.emailInput.value.trim();
                
                if (!email) {
                    this.showFieldError('email', 'Email is required');
                    return false;
                } else if (!this.isValidEmail(email)) {
                    this.showFieldError('email', 'Please enter a valid email address');
                    return false;
                } else {
                    this.clearFieldError('email');
                    return true;
                }
            }

            validatePassword() {
                const password = this.passwordInput.value;
                
                if (!password) {
                    this.showFieldError('password', 'Password is required');
                    return false;
                } else if (password.length < 6) {
                    this.showFieldError('password', 'Password must be at least 6 characters');
                    return false;
                } else {
                    this.clearFieldError('password');
                    return true;
                }
            }

            isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            showFieldError(field, message) {
                const input = document.getElementById(field);
                const errorElement = document.getElementById(field + '-error');
                
                input.classList.add('error');
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }

            clearFieldError(field) {
                const input = document.getElementById(field);
                const errorElement = document.getElementById(field + '-error');
                
                input.classList.remove('error');
                errorElement.classList.remove('show');
            }

            clearError(field) {
                const input = document.getElementById(field);
                if (input.classList.contains('error')) {
                    input.classList.remove('error');
                    this.clearFieldError(field);
                }
            }

            handleSubmit(e) {
                const emailValid = this.validateEmail();
                const passwordValid = this.validatePassword();
                
                if (!emailValid || !passwordValid) {
                    e.preventDefault();
                    return;
                }
                
                this.setLoadingState(true);
            }

            setLoadingState(loading) {
                if (loading) {
                    this.submitBtn.classList.add('loading');
                    this.submitBtn.disabled = true;
                } else {
                    this.submitBtn.classList.remove('loading');
                    this.submitBtn.disabled = false;
                }
            }

            autoFocus() {
                if (!this.emailInput.value) {
                    this.emailInput.focus();
                } else if (!this.passwordInput.value) {
                    this.passwordInput.focus();
                }
            }

            handleKeyboard(e) {
                // Ctrl/Cmd + Enter to submit
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    this.form.dispatchEvent(new Event('submit'));
                }
            }

            animateStats() {
                const stats = document.querySelectorAll('.stat-number');
                stats.forEach(stat => {
                    const finalValue = stat.textContent;
                    if (finalValue.includes('K')) {
                        this.animateNumber(stat, 0, parseInt(finalValue) * 1000, 'K+');
                    } else if (finalValue.includes('%')) {
                        this.animateNumber(stat, 0, parseFloat(finalValue), '%');
                    }
                });
            }

            animateNumber(element, start, end, suffix) {
                const duration = 2000;
                const startTime = performance.now();
                
                const animate = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    
                    const current = start + (end - start) * progress;
                    
                    if (suffix === 'K+') {
                        element.textContent = Math.floor(current / 1000) + 'K+';
                    } else if (suffix === '%') {
                        element.textContent = current.toFixed(1) + '%';
                    } else {
                        element.textContent = Math.floor(current) + suffix;
                    }
                    
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    }
                };
                
                requestAnimationFrame(animate);
            }
        }

        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Google Sign-in
        function signInWithGoogle() {
            const btn = event.target.closest('.google-btn');
            const originalText = btn.innerHTML;
            
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<div style="width: 16px; height: 16px; border: 2px solid #ccc; border-top-color: #333; border-radius: 50%; animation: spin 1s linear infinite;"></div> Connecting...';
            
            setTimeout(() => {
                alert('Google Sign-in integration needed. Please implement Google OAuth 2.0.');
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                btn.innerHTML = originalText;
            }, 1500);
        }

        // Auto-hide messages
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

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            new LoginForm();
            autoHideMessages();
        });

        // Form persistence (remember form data)
        window.addEventListener('beforeunload', function() {
            const email = document.getElementById('email').value;
            if (email) {
                localStorage.setItem('rememberedEmail', email);
            }
        });

        // Restore form data
        window.addEventListener('load', function() {
            const rememberedEmail = localStorage.getItem('rememberedEmail');
            if (rememberedEmail && !document.getElementById('email').value) {
                document.getElementById('email').value = rememberedEmail;
            }
        });

        // Prevent back navigation to login page if logged in
        <?php if (isset($_SESSION['user_id'])): ?>
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            location.replace('dashboard.php');
        };
        <?php endif; ?>
    </script>
</body>
</html>