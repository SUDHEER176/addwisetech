<?php
require_once 'auth.php';

// Prevent browser from caching this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f8fafc;
            margin: 0;
        }
        .navbar {
            background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            box-shadow: 0 2px 8px rgba(37,99,235,0.08);
        }
        .navbar .brand {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .navbar .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .navbar .user-email {
            font-size: 1rem;
            font-weight: 500;
            margin-right: 12px;
            color: #e0e7ef;
        }
        .logout-btn {
            padding: 8px 18px;
            background: #fff;
            color: #2563eb;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .logout-btn:hover {
            background: #2563eb;
            color: #fff;
            border: 1px solid #fff;
        }
        .main-content {
            padding: 40px 20px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                height: auto;
                padding: 12px 10px;
            }
            .navbar .nav-links {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                width: 100%;
            }
            .main-content {
                padding: 20px 5px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="brand">AddWise</div>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
                <a href="logout.php"><button class="logout-btn">Logout</button></a>
            <?php else: ?>
                <a href="login.php"><button class="logout-btn">Sign In</button></a>
                <a href="signup.php"><button class="logout-btn">Register</button></a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="main-content">
        <h2>Welcome to your Dashboard!</h2>
         
    </div>

   
</body>
</html>