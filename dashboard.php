<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];
$user_name = '';
$user_email = '';
$user_avatar = '';

// Get user data
$stmt = $db->prepare("SELECT name, email, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_email, $user_avatar);
$stmt->fetch();
$stmt->close();

// Generate initials if no avatar
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return $initials ?: 'U';
}

$initials = getInitials($user_name);

// Get device statistics
$total_devices = 0;
$online_devices = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ? AND status = 'assigned'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($total_devices);
    $stmt->fetch();
    $stmt->close();

    $stmt = $db->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ? AND status = 'assigned' AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($online_devices);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    // Handle error silently
}

$currentDate = new DateTime();
$timeOfDay = $currentDate->format('H') < 12 ? 'Morning' : ($currentDate->format('H') < 18 ? 'Afternoon' : 'Evening');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern AddWise Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header Styles */
        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-section {
            position: relative;
        }

        .user-btn {
            background: none;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .user-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            transform: translateY(-1px);
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 0.875rem;
            color: white;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-email {
            font-size: 0.75rem;
            color: #64748b;
        }

        .dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            background: white;
            min-width: 14rem;
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            z-index: 1000;
            overflow: hidden;
            padding: 0.25rem;
        }

        .dropdown-item {
            color: #475569;
            padding: 0.75rem 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background: #f8fafc;
            color: #3b82f6;
            transform: translateX(4px);
        }

        .dropdown-item.logout {
            color: #dc2626;
        }

        .dropdown-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Main Container */
        .container {
            max-width: 87rem;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border-radius: 1.5rem;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border-radius: 1.5rem;
        }

        .welcome-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .welcome-text h1 {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .welcome-info {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0.75rem;
            padding: 1rem;
            backdrop-filter: blur(10px);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #3b82f6; }
        .stat-icon.green { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }
        .stat-icon.purple { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); color: #9333ea; }
        .stat-icon.orange { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #ea580c; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.75rem;
            font-weight: 600;
            color: #16a34a;
        }

        /* Card Grid */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-icon.blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #3b82f6; }
        .card-icon.green { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: #64748b;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            border: 1px solid #f1f5f9;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table th {
            background: #f8fafc;
            color: #475569;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f8fafc;
            color: #475569;
            font-size: 0.875rem;
        }

        .table tr:hover {
            background: #f8fafc;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        /* Device Code Styling */
        .device-code {
            font-weight: 600;
            color: #1e293b;
            font-family: 'Monaco', 'Menlo', monospace;
        }

        /* Status Badges */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-online {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-offline {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
        }

        .status-online .status-dot { background: #16a34a; }
        .status-offline .status-dot { background: #dc2626; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            margin-right: 0.5rem;
        }

        .btn:last-child {
            margin-right: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }

        .btn-outline {
            background: white;
            color: #3b82f6;
            border: 1px solid #3b82f6;
        }

        .btn-outline:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.3);
        }

        .btn-large {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            width: 100%;
            justify-content: center;
            margin-bottom: 0.75rem;
        }

        .btn-large:last-child {
            margin-bottom: 0;
        }

        /* Scanner Actions */
        .scanner-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .file-upload {
            position: relative;
        }

        .file-upload-btn {
            background: white;
            color: #3b82f6;
            border: 2px dashed #cbd5e1;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
        }

        .file-upload-btn:hover {
            border-color: #3b82f6;
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        /* Map Container */
        .map-container {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .map-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .map-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .map-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-indicator {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: #94a3b8;
        }

        .status-indicator.active {
            background: #16a34a;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .distance-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #0e7490;
            font-size: 0.875rem;
            font-weight: 600;
            background: #ecfeff;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }

        .map-content {
            padding: 1.5rem;
        }

        #userMap {
            height: 24rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1.5rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            min-width: 400px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid #e2e8f0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
        }

        #qrcode {
            display: inline-block;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-icon {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
            opacity: 0.5;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .empty-text {
            font-size: 0.875rem;
        }

        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Real-time Update Indicator */
        .update-indicator {
            position: fixed;
            top: 5rem;
            right: 1rem;
            background: #16a34a;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1001;
        }

        .update-indicator.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .card-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .welcome-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .welcome-info {
                width: 100%;
            }

            .user-info {
                display: none;
            }

            .modal-content {
                min-width: 90%;
                margin: 1rem;
                padding: 1.5rem;
            }

            .map-header {
                flex-direction: column;
                align-items: stretch;
            }

            .map-controls {
                justify-content: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }

            .btn:last-child {
                margin-bottom: 0;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-text h1 {
                font-size: 1.5rem;
            }

            .welcome-text p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Real-time Update Indicator -->
    <div id="updateIndicator" class="update-indicator">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
        </svg>
        Location updated
    </div>

    <!-- Header -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <div class="logo-text">AddWise</div>
        </div>
        
        <div class="user-section">
            <button class="user-btn" onclick="toggleDropdown()">
                <div class="user-avatar">
                    <?php if ($user_avatar): ?>
                        <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="<?php echo htmlspecialchars($user_name); ?>">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M7 10l5 5 5-5z"/>
                </svg>
            </button>
            
            <div class="dropdown" id="userDropdown">
                <a href="profile.php" class="dropdown-item">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    Profile
                </a>
                <a href="settings.php" class="dropdown-item">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                    </svg>
                    Settings
                </a>
                <a href="logout.php" class="dropdown-item logout">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h1>Good <?php echo $timeOfDay; ?>, <?php echo htmlspecialchars($user_name); ?>!</h1>
                    <p>Manage your devices and monitor your inventory</p>
                </div>
                <div class="welcome-info">
                    <div class="info-item">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                        <span><?php echo $currentDate->format('M d, Y'); ?></span>
                    </div>
                    <div class="info-item">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                            <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                        </svg>
                        <span><?php echo $currentDate->format('H:i'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM7 4h10v16H7V4z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $total_devices; ?></div>
                        <div class="stat-label">Total Devices</div>
                    </div>
                </div>
                <div class="stat-change">+2 this week</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $online_devices; ?></div>
                        <div class="stat-label">Online</div>
                    </div>
                </div>
                <div class="stat-change"><?php echo $total_devices > 0 ? round(($online_devices / $total_devices) * 100) : 0; ?>% uptime</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value" id="trackedCount">0</div>
                        <div class="stat-label">Tracked</div>
                    </div>
                </div>
                <div class="stat-change">Real-time</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 11H7v6h2v-6zm4 0h-2v6h2v-6zm4 0h-2v6h2v-6zm2.5-9H18V0h-2v2H8V0H6v2H4.5C3.11 2 2 3.11 2 4.5v15C2 20.89 3.11 22 4.5 22h15c1.39 0 2.5-1.11 2.5-2.5v-15C22 3.11 20.89 2 19.5 2zM20 19.5c0 .28-.22.5-.5.5h-15c-.28 0-.5-.22-.5-.5v-15c0-.28.22-.5.5-.5h15c.28 0 .5.22.5.5v15z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value">98%</div>
                        <div class="stat-label">Activity</div>
                    </div>
                </div>
                <div class="stat-change">+5% from last month</div>
            </div>
        </div>
        
        <!-- Main Cards -->
        <div class="card-grid">
            <!-- My Devices Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon blue">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM7 4h10v16H7V4z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="card-title">My Devices</div>
                        <div class="card-subtitle">Manage your assigned devices</div>
                    </div>
                </div>
                
                <div class="card-content">
                    <?php
                    try {
                        $stmt = $db->prepare("SELECT d.* FROM devices d WHERE d.user_id = ? AND d.status = 'assigned' ORDER BY d.assigned_at DESC");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $devices = [];
                        while ($row = $result->fetch_assoc()) {
                            $devices[] = $row;
                        }
                        $stmt->close();

                        if (count($devices) > 0) {
                            echo '<div class="table-container">';
                            echo '<table class="table">';
                            echo '<thead><tr><th>Device Code</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($devices as $device) {
                                $status_class = 'status-offline';
                                $status_text = 'Offline';
                                
                                if (isset($device['last_seen']) && $device['last_seen']) {
                                    $last_seen = strtotime($device['last_seen']);
                                    $time_diff = time() - $last_seen;
                                    if ($time_diff < 300) {
                                        $status_class = 'status-online';
                                        $status_text = 'Online';
                                    }
                                }
                                
                                echo '<tr>';
                                echo '<td><span class="device-code">' . htmlspecialchars($device['device_code']) . '</span></td>';
                                echo '<td><span id="status-' . htmlspecialchars($device['device_code']) . '" class="status ' . $status_class . '"><span class="status-dot"></span>' . $status_text . '</span></td>';
                                echo '<td>' . (isset($device['assigned_at']) ? date('M d, Y', strtotime($device['assigned_at'])) : 'N/A') . '</td>';
                                echo '<td>
                                        <div class="action-buttons">
                                            <button onclick="showQR(\'' . htmlspecialchars($device['device_code']) . '\')" class="btn btn-outline">
                                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M3 11h8V3H3v8zm2-6h4v4H5V5zM13 3v8h8V3h-8zm6 6h-4V5h4v4zM3 21h8v-8H3v8zm2-6h4v4H5v-4z"/>
                                                </svg>
                                                QR Code
                                            </button>
                                            <button onclick="trackDevice(\'' . htmlspecialchars($device['device_code']) . '\')" class="btn btn-primary">
                                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                                </svg>
                                                Track
                                            </button>
                                        </div>
                                    </td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                            echo '</div>';
                        } else {
                            echo '<div class="empty-state">
                                    <div class="empty-icon">
                                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM7 4h10v16H7V4z"/>
                                        </svg>
                                    </div>
                                    <div class="empty-title">No devices assigned</div>
                                    <div class="empty-text">Start by scanning a device QR code</div>
                                  </div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="empty-state">
                                <div class="empty-icon">
                                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </div>
                                <div class="empty-title">Error loading devices</div>
                                <div class="empty-text">Please try again later</div>
                              </div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Add Device Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon green">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="card-title">Add New Device</div>
                        <div class="card-subtitle">Scan or upload QR code</div>
                    </div>
                </div>
                
                <div class="card-content">
                    <div class="scanner-actions">
                        <button onclick="startScan()" class="btn btn-primary btn-large">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/>
                            </svg>
                            Scan QR Code
                        </button>
                        
                        <div class="file-upload">
                            <label for="qrFileInput" class="file-upload-btn">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                                </svg>
                                Upload QR Image
                            </label>
                            <input type="file" id="qrFileInput" accept="image/*" class="file-input">
                        </div>
                        
                        <video id="preview" style="display:none; width: 100%; border-radius: 0.5rem; border: 1px solid #e2e8f0;"></video>
                    </div>
                </div>
            </div>
        </div>

        <!-- Device Location Map -->
        <div class="map-container">
            <div class="map-header">
                <div class="card-header" style="margin-bottom: 0; padding: 0;">
                    <div class="card-icon purple">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="card-title">Device Location</div>
                        <div class="card-subtitle">Real-time tracking</div>
                    </div>
                </div>
                
                <div class="map-controls">
                    <div class="map-status">
                        <span class="status-indicator" id="trackingIndicator"></span>
                        <span id="locationStatus">Select a device to start tracking</span>
                    </div>
                    <div class="distance-display" id="distanceDisplay" style="display: none;">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3,11H5V13H3V11M11,5H13V9H11V5M9,11H13V15H11V13H9V11M15,11H17V13H15V11M19,9H21V13H19V9M6.5,1L12,6.5L17.5,1L19,2.5L13.5,8L19,13.5L17.5,15L12,9.5L6.5,15L5,13.5L10.5,8L5,2.5L6.5,1Z"/>
                        </svg>
                        <span id="distanceText">0 km</span>
                    </div>
                    <button id="stopTrackBtn" class="btn btn-danger" style="display: none;">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 6h12v12H6z"/>
                        </svg>
                        Stop Tracking
                    </button>
                </div>
            </div>
            
            <div class="map-content">
                <div id="userMap"></div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Device QR Code</h3>
            <div id="qrcode"></div>
            <button onclick="closeQR()" class="btn btn-primary">Close</button>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        let userMap, userMarker, startMarker, trackInterval = null, trackingDeviceCode = null, routeLine = null;
        let lastKnownPositions = new Map(); // Store last known positions for distance calculation
        let totalDistance = 0;
        let historyMarkers = []; // Store history location markers

        // Initialize map
        function initUserMap() {
            try {
                userMap = L.map('userMap').setView([20.5937, 78.9629], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(userMap);
                
                console.log('Map initialized successfully');
            } catch (error) {
                console.error('Error initializing map:', error);
                document.getElementById('locationStatus').textContent = 'Error initializing map';
            }
        }

        // Haversine formula to calculate distance between two lat/lng points in km
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in kilometers
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Show update indicator
        function showUpdateIndicator() {
            const indicator = document.getElementById('updateIndicator');
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }

        // Update device location with real-time tracking
        function updateLocation(deviceCode) {
            if (!userMap) {
                console.error('Map not initialized');
                return;
            }

            fetch('api-device-location.php?device_code=' + encodeURIComponent(deviceCode))
                .then(res => res.json())
                .then(data => {
                    const status = document.getElementById('locationStatus');
                    const indicator = document.getElementById('trackingIndicator');
                    const distanceDisplay = document.getElementById('distanceDisplay');
                    const distanceText = document.getElementById('distanceText');
                    
                    if (data.success && data.latitude && data.longitude) {
                        const lat = parseFloat(data.latitude);
                        const lng = parseFloat(data.longitude);
                        
                        // Calculate distance if we have a previous position
                        if (lastKnownPositions.has(deviceCode)) {
                            const lastPos = lastKnownPositions.get(deviceCode);
                            // Only show update indicator if the position has changed
                            if (lastPos.lat !== lat || lastPos.lng !== lng) {
                                const distance = calculateDistance(lastPos.lat, lastPos.lng, lat, lng);
                                totalDistance += distance;
                                
                                // Update distance display
                                distanceText.textContent = totalDistance.toFixed(2) + ' km';
                                distanceDisplay.style.display = 'flex';
                                
                                // Show update indicator
                                showUpdateIndicator();
                            }
                        }
                        
                        // Store current position
                        lastKnownPositions.set(deviceCode, { lat, lng });
                        
                        // Update or create markers
                        if (!userMarker) {
                            // Create end marker (current position)
                            userMarker = L.marker([lat, lng], {
                                icon: L.divIcon({
                                    className: 'custom-marker',
                                    html: '<div style="background: #ef4444; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                    iconSize: [20, 20],
                                    iconAnchor: [10, 10]
                                })
                            }).addTo(userMap);
                            userMarker.bindPopup(`
                                <div style="text-align: center;">
                                    <b>${deviceCode}</b><br>
                                    <span style="color: #ef4444;">● Current Location</span><br>
                                    Lat: ${lat.toFixed(6)}<br>
                                    Lng: ${lng.toFixed(6)}<br>
                                    Updated: ${data.updated_at || 'Now'}
                                </div>
                            `);
                            
                            // Create start marker if this is the first position
                            if (!startMarker) {
                                startMarker = L.marker([lat, lng], {
                                    icon: L.divIcon({
                                        className: 'custom-marker',
                                        html: '<div style="background: #16a34a; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                        iconSize: [20, 20],
                                        iconAnchor: [10, 10]
                                    })
                                }).addTo(userMap);
                                startMarker.bindPopup(`
                                    <div style="text-align: center;">
                                        <b>${deviceCode}</b><br>
                                        <span style="color: #16a34a;">● Start Location</span><br>
                                        Lat: ${lat.toFixed(6)}<br>
                                        Lng: ${lng.toFixed(6)}<br>
                                        Started: ${data.updated_at || 'Now'}
                                    </div>
                                `);
                            }
                        } else {
                            // Update end marker position
                            userMarker.setLatLng([lat, lng]);
                            userMarker.getPopup().setContent(`
                                <div style="text-align: center;">
                                    <b>${deviceCode}</b><br>
                                    <span style="color: #ef4444;">● Current Location</span><br>
                                    Lat: ${lat.toFixed(6)}<br>
                                    Lng: ${lng.toFixed(6)}<br>
                                    Distance: ${totalDistance.toFixed(2)} km<br>
                                    Updated: ${data.updated_at || 'Now'}
                                </div>
                            `);
                        }
                        
                        // Update route line
                        if (routeLine && startMarker) {
                            const startPos = startMarker.getLatLng();
                            const endPos = [lat, lng];
                            routeLine.setLatLngs([startPos, endPos]);
                        } else if (startMarker) {
                            const startPos = startMarker.getLatLng();
                            routeLine = L.polyline([startPos, [lat, lng]], {
                                color: '#3b82f6',
                                weight: 4,
                                opacity: 0.8,
                                dashArray: '10, 5'
                            }).addTo(userMap);
                        }
                        
                        // Fit map to show both markers
                        if (startMarker && userMarker) {
                            const group = new L.featureGroup([startMarker, userMarker]);
                            userMap.fitBounds(group.getBounds().pad(0.1));
                        } else {
                            userMap.setView([lat, lng], 15);
                        }
                        
                        status.textContent = `Tracking ${deviceCode}`;
                        indicator.classList.add('active');
                        
                        // Update tracked count
                        document.getElementById('trackedCount').textContent = '1';

                        // Update status badge in the table
                        const statusElem = document.getElementById('status-' + deviceCode);
                        if (statusElem) {
                            statusElem.classList.remove('status-online', 'status-offline');
                            statusElem.classList.add('status-online');
                            statusElem.innerHTML = '<span class="status-dot"></span>Online';
                        }
                    } else {
                        status.textContent = `No location data found for ${deviceCode}`;
                        indicator.classList.remove('active');
                        clearMapMarkers();

                        // Set status to offline if no location data
                        const statusElem = document.getElementById('status-' + deviceCode);
                        if (statusElem) {
                            statusElem.classList.remove('status-online', 'status-offline');
                            statusElem.classList.add('status-offline');
                            statusElem.innerHTML = '<span class="status-dot"></span>Offline';
                        }
                    }

                    // After updating the current position and status, redraw the full route
                    drawDeviceRoute(deviceCode);
                })
                .catch(error => {
                    console.error('Error fetching location:', error);
                    document.getElementById('locationStatus').textContent = 'Error fetching location data';
                    document.getElementById('trackingIndicator').classList.remove('active');

                    // Set status to offline on error
                    const statusElem = document.getElementById('status-' + deviceCode);
                    if (statusElem) {
                        statusElem.classList.remove('status-online', 'status-offline');
                        statusElem.classList.add('status-offline');
                        statusElem.innerHTML = '<span class="status-dot"></span>Offline';
                    }
                });
        }

        // Fetch complete route and draw it
        function drawDeviceRoute(deviceCode) {
            fetch('api-device-location.php?device_code=' + encodeURIComponent(deviceCode) + '&route=true')
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.route) && data.route.length > 0) {
                        // Clear existing markers and route
                        clearMapMarkers();

                        // Calculate total distance for the complete route
                        totalDistance = 0;
                        const latlngs = [];

                        // Add a marker for each update point (history)
                        historyMarkers = [];
                        data.route.forEach((point, index) => {
                            const lat = parseFloat(point.latitude);
                            const lng = parseFloat(point.longitude);
                            latlngs.push([lat, lng]);

                            // Add a marker for each update point
                            const marker = L.circleMarker([lat, lng], {
                                radius: 6,
                                color: '#6366f1',
                                fillColor: '#6366f1',
                                fillOpacity: 0.7
                            }).addTo(userMap).bindPopup(
                                `<div style="text-align:center;">
                                    <b>${deviceCode}</b><br>
                                    Lat: ${lat.toFixed(6)}<br>
                                    Lng: ${lng.toFixed(6)}<br>
                                    Time: ${point.updated_at || 'Unknown'}
                                </div>`
                            );
                            historyMarkers.push(marker);

                            if (index > 0) {
                                const prevLat = parseFloat(data.route[index - 1].latitude);
                                const prevLng = parseFloat(data.route[index - 1].longitude);
                                totalDistance += calculateDistance(prevLat, prevLng, lat, lng);
                            }
                        });

                        // Draw route line
                        routeLine = L.polyline(latlngs, {
                            color: '#3b82f6',
                            weight: 4,
                            opacity: 0.8
                        }).addTo(userMap);

                        // Add start marker (green)
                        const firstPoint = data.route[0];
                        startMarker = L.marker([parseFloat(firstPoint.latitude), parseFloat(firstPoint.longitude)], {
                            icon: L.divIcon({
                                className: 'custom-marker',
                                html: '<div style="background: #16a34a; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            })
                        }).addTo(userMap);
                        startMarker.bindPopup(`
                            <div style="text-align: center;">
                                <b>${deviceCode}</b><br>
                                <span style="color: #16a34a;">● Start Location</span><br>
                                Lat: ${parseFloat(firstPoint.latitude).toFixed(6)}<br>
                                Lng: ${parseFloat(firstPoint.longitude).toFixed(6)}<br>
                                Time: ${firstPoint.updated_at || 'Unknown'}
                            </div>
                        `);

                        // Add end marker (red)
                        const lastPoint = data.route[data.route.length - 1];
                        userMarker = L.marker([parseFloat(lastPoint.latitude), parseFloat(lastPoint.longitude)], {
                            icon: L.divIcon({
                                className: 'custom-marker',
                                html: '<div style="background: #ef4444; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            })
                        }).addTo(userMap);
                        userMarker.bindPopup(`
                            <div style="text-align: center;">
                                <b>${deviceCode}</b><br>
                                <span style="color: #ef4444;">● End Location</span><br>
                                Lat: ${parseFloat(lastPoint.latitude).toFixed(6)}<br>
                                Lng: ${parseFloat(lastPoint.longitude).toFixed(6)}<br>
                                Distance: ${totalDistance.toFixed(2)} km<br>
                                Time: ${lastPoint.updated_at || 'Unknown'}
                            </div>
                        `);

                        // Fit map to route
                        userMap.fitBounds(routeLine.getBounds().pad(0.1));

                        // Update distance display
                        document.getElementById('distanceText').textContent = totalDistance.toFixed(2) + ' km';
                        document.getElementById('distanceDisplay').style.display = 'flex';

                        // Store last position for future calculations
                        lastKnownPositions.set(deviceCode, {
                            lat: parseFloat(lastPoint.latitude),
                            lng: parseFloat(lastPoint.longitude)
                        });

                        document.getElementById('locationStatus').textContent = `Tracking ${deviceCode} - Route loaded`;
                        document.getElementById('trackingIndicator').classList.add('active');
                        document.getElementById('trackedCount').textContent = '1';
                    } else {
                        // No route data, try single location
                        updateLocation(deviceCode);
                    }
                })
                .catch(error => {
                    console.error('Error fetching route:', error);
                    // Fallback to single location update
                    updateLocation(deviceCode);
                });
        }

        // Clear all map markers and route
        function clearMapMarkers() {
            if (userMarker && userMap) {
                userMap.removeLayer(userMarker);
                userMarker = null;
            }
            if (startMarker && userMap) {
                userMap.removeLayer(startMarker);
                startMarker = null;
            }
            if (routeLine && userMap) {
                userMap.removeLayer(routeLine);
                routeLine = null;
            }
            // Remove all history markers
            if (historyMarkers && userMap) {
                historyMarkers.forEach(marker => userMap.removeLayer(marker));
                historyMarkers = [];
            }
        }

        // Start tracking device
        function trackDevice(deviceCode) {
            if (!userMap) {
                alert('Map not initialized. Please refresh the page.');
                return;
            }
            
            trackingDeviceCode = deviceCode;
            totalDistance = 0;
            
            // Show controls
            document.getElementById('stopTrackBtn').style.display = 'flex';
            
            // Initial route draw
            drawDeviceRoute(deviceCode);
            
            // Set up real-time updates
            if (trackInterval) clearInterval(trackInterval);
            trackInterval = setInterval(() => {
                updateLocation(deviceCode);
            }, 5000); // Update every 5 seconds
        }

        // Stop tracking
        function stopTracking() {
            if (trackInterval) clearInterval(trackInterval);
            trackingDeviceCode = null;
            totalDistance = 0;
            
            const status = document.getElementById('locationStatus');
            const indicator = document.getElementById('trackingIndicator');
            const stopBtn = document.getElementById('stopTrackBtn');
            const distanceDisplay = document.getElementById('distanceDisplay');
            
            status.textContent = 'Select a device to start tracking';
            indicator.classList.remove('active');
            stopBtn.style.display = 'none';
            distanceDisplay.style.display = 'none';
            
            // Clear map
            clearMapMarkers();
            
            // Reset tracked count
            document.getElementById('trackedCount').textContent = '0';
            
            // Clear stored positions
            lastKnownPositions.clear();
        }

        // QR Code functions
        function showQR(code) {
            const modal = document.getElementById('qrModal');
            const qrcodeDiv = document.getElementById('qrcode');
            
            qrcodeDiv.innerHTML = '';
            modal.style.display = 'block';
            
            try {
                new QRCode(qrcodeDiv, {
                    text: code,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (error) {
                console.error('Error generating QR code:', error);
                qrcodeDiv.innerHTML = '<p style="color: #dc2626;">Error generating QR code</p>';
            }
        }

        function closeQR() {
            const modal = document.getElementById('qrModal');
            const qrcodeDiv = document.getElementById('qrcode');
            qrcodeDiv.innerHTML = '';
            modal.style.display = 'none';
        }

        // Device assignment with location
        function assignDeviceWithLocation(device_code) {
            if (!navigator.geolocation) {
                alert("Geolocation is not supported by your browser.");
                return;
            }
            
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                fetch('assign-device.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'device_code=' + encodeURIComponent(device_code) +
                          '&latitude=' + encodeURIComponent(lat) +
                          '&longitude=' + encodeURIComponent(lng)
                })
                .then(response => response.text())
                .then(result => {
                    alert(result);
                    if (result.includes('success')) {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error assigning device:', error);
                    alert('Error assigning device. Please try again.');
                });
            }, function(error) {
                console.error('Geolocation error:', error);
                alert("Unable to get your location. Please allow location access.");
            });
        }

        // QR Scanner functions
        function startScan() {
            const preview = document.getElementById('preview');
            preview.style.display = 'block';
            
            const html5QrCode = new Html5Qrcode("preview");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                qrCodeMessage => {
                    html5QrCode.stop().then(() => {
                        preview.style.display = 'none';
                        assignDeviceWithLocation(qrCodeMessage);
                    }).catch(err => {
                        console.error('Error stopping scanner:', err);
                    });
                },
                errorMessage => {
                    // Handle scan errors silently
                }
            ).catch(err => {
                console.error('Error starting scanner:', err);
                alert("Unable to access camera. Please try uploading an image instead.");
                preview.style.display = 'none';
            });
        }

        // Dropdown toggle
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        // File upload QR scanner
        document.getElementById('qrFileInput').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const html5QrCode = new Html5Qrcode("preview");
            html5QrCode.scanFile(file, true)
                .then(qrCodeMessage => {
                    assignDeviceWithLocation(qrCodeMessage);
                })
                .catch(err => {
                    console.error('Error scanning file:', err);
                    alert("No QR code found in the image or error processing file.");
                });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const userBtn = document.querySelector('.user-btn');
            
            if (!userBtn.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('qrModal');
            if (event.target === modal) {
                closeQR();
            }
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            
            setTimeout(() => {
                initUserMap();
            }, 100);
            
            document.getElementById('stopTrackBtn').addEventListener('click', stopTracking);
            
            console.log('Initialization complete');
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (trackInterval) {
                clearInterval(trackInterval);
            }
        });
    </script>
</body>
</html>