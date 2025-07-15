<?php
session_start();

// Security check
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

// Database configuration
class Database {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $dbname = "addwise";
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log($e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function close() {
        $this->conn->close();
    }
}

// User management class
class UserManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function getAllUsers() {
        $sql = "SELECT u.*, 
                       COUNT(d.id) as device_count,
                       MAX(d.location_updated_at) as last_activity
                FROM users u 
                LEFT JOIN devices d ON u.id = d.user_id 
                GROUP BY u.id 
                ORDER BY u.created_at DESC";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    public function getUserStats() {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'with_devices' => 0
        ];
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                    COUNT(DISTINCT d.user_id) as with_devices
                FROM users u 
                LEFT JOIN devices d ON u.id = d.user_id";
        $result = $this->db->query($sql);
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats = [
                'total' => (int)$row['total'],
                'active' => (int)$row['active'],
                'inactive' => (int)$row['inactive'],
                'with_devices' => (int)$row['with_devices']
            ];
        }
        
        return $stats;
    }
    
    public function toggleUserStatus($user_id) {
        if (!is_numeric($user_id)) {
            return ['success' => false, 'message' => 'Invalid user ID.'];
        }
        
        $sql = "UPDATE users SET is_active = NOT is_active WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt && $stmt->bind_param("i", $user_id) && $stmt->execute()) {
            return ['success' => true, 'message' => 'User status updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update user status.'];
        }
    }
    
    public function getUserDevices($user_id) {
        $sql = "SELECT device_code, status, assigned_at, latitude, longitude, location_updated_at 
                FROM devices 
                WHERE user_id = ? 
                ORDER BY assigned_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}

// Initialize classes
$database = new Database();
$userManager = new UserManager($database);

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        $result = $userManager->toggleUserStatus($user_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
}

// Get data for display
$users = $userManager->getAllUsers();
$user_stats = $userManager->getUserStats();

// Close database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - AddWise Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-header .logo-icon {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: white;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            margin: 4px 16px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .nav-link.active {
            background: #3b82f6;
            color: white;
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            background: transparent;
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 20px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        
        .menu-toggle:hover {
            background: #f1f5f9;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-btn {
            position: relative;
            background: none;
            border: none;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .notification-btn:hover {
            background: #f1f5f9;
        }
        
        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .user-details p {
            font-size: 12px;
            color: #64748b;
        }
        
        /* Content Area */
        .content {
            padding: 32px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-value.purple { color: #8b5cf6; }
        .stat-value.green { color: #10b981; }
        .stat-value.red { color: #ef4444; }
        .stat-value.blue { color: #3b82f6; }
        
        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 24px 24px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            background: #8b5cf6;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .card-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }
        
        .card-content {
            padding: 24px;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        
        .table tr:hover {
            background: #f8fafc;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .user-email {
            font-weight: 600;
            color: #1e293b;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #10b981;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: #ef4444;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-secondary {
            background: #64748b;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        
        .modal-close:hover {
            background: #f1f5f9;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
        }
        
        .device-list {
            display: grid;
            gap: 12px;
        }
        
        .device-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .device-details {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .device-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }
        
        .device-code {
            font-family: 'Monaco', 'Menlo', monospace;
            font-weight: 600;
            color: #1e293b;
        }
        
        .device-status {
            font-size: 12px;
            color: #64748b;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 16px 20px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            
            .user-details {
                display: none;
            }
        }
        
        /* Mobile Overlay */
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        @media (max-width: 768px) {
            .mobile-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h2>AddWise Admin</h2>
            </div>
        </div>
        
        <div class="nav-menu">
            <div class="nav-item">
                <a href="admin-dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="admin-devices.php" class="nav-link">
                    <i class="fas fa-mobile-alt"></i>
                    Devices
                </a>
            </div>
            <div class="nav-item">
                <a href="admin-users.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </div>
            <div class="nav-item">
                <a href="admin-tracking.php" class="nav-link">
                    <i class="fas fa-map-marker-alt"></i>
                    Live Tracking
                </a>
            </div>
            <div class="nav-item">
                <a href="admin-settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Users</h1>
            </div>
            
            <div class="header-right">
                <button class="notification-btn">
                    <i class="fas fa-bell" style="font-size: 18px; color: #64748b;"></i>
                    <span class="notification-badge"></span>
                </button>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <h4>Admin User</h4>
                        <p>admin@addwise.com</p>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <main class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value purple"><?php echo $user_stats['total']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value green"><?php echo $user_stats['active']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value red"><?php echo $user_stats['inactive']; ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value blue"><?php echo $user_stats['with_devices']; ?></div>
                    <div class="stat-label">With Devices</div>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="card-title">User Management</div>
                        <div class="card-subtitle"><?php echo count($users); ?> users total</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th>Devices</th>
                                    <th>Joined</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                            <br>No users found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 32px; height: 32px; background: #8b5cf6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                                                        <?php echo strtoupper(substr($user['email'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                        <div style="font-size: 12px; color: #64748b;">ID: <?php echo $user['id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-weight: 600;"><?php echo $user['device_count']; ?></span>
                                                    <?php if ($user['device_count'] > 0): ?>
                                                        <button onclick="showUserDevices(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')" class="btn btn-secondary" style="padding: 4px 8px; font-size: 10px;">
                                                            <i class="fas fa-eye"></i>
                                                            View
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <i class="fas fa-calendar" style="color: #64748b; font-size: 12px;"></i>
                                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($user['last_activity']): ?>
                                                    <div style="font-size: 12px;">
                                                        <div><?php echo date('M j, Y', strtotime($user['last_activity'])); ?></div>
                                                        <div style="color: #64748b;"><?php echo date('H:i', strtotime($user['last_activity'])); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #64748b; font-size: 12px;">No activity</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="toggle_status" class="btn <?php echo $user['is_active'] ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?')">
                                                            <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- User Devices Modal -->
    <div class="modal" id="userDevicesModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeUserDevicesModal()">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="modal-title" id="modalUserTitle">User Devices</h3>
            <div id="userDevicesList">
                <!-- Devices will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
        });
        
        mobileOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            mobileOverlay.classList.remove('active');
        });
        
        // User devices modal
        function showUserDevices(userId, userEmail) {
            const modal = document.getElementById('userDevicesModal');
            const title = document.getElementById('modalUserTitle');
            const devicesList = document.getElementById('userDevicesList');
            
            title.textContent = `Devices for ${userEmail}`;
            devicesList.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            modal.classList.add('active');
            
            // Fetch user devices
            fetch(`api-user-devices.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.devices.length > 0) {
                        let html = '<div class="device-list">';
                        data.devices.forEach(device => {
                            html += `
                                <div class="device-item">
                                    <div class="device-details">
                                        <div class="device-indicator"></div>
                                        <div>
                                            <div class="device-code">${device.device_code}</div>
                                            <div class="device-status">Status: ${device.status} • Assigned: ${new Date(device.assigned_at).toLocaleDateString()}</div>
                                            ${device.latitude && device.longitude ? 
                                                `<div class="device-status">Location: ${parseFloat(device.latitude).toFixed(4)}, ${parseFloat(device.longitude).toFixed(4)}</div>` : 
                                                '<div class="device-status">No location data</div>'
                                            }
                                        </div>
                                    </div>
                                    ${device.latitude && device.longitude ? 
                                        `<button onclick="viewDeviceLocation(${device.latitude}, ${device.longitude}, '${device.device_code}')" class="btn">
                                            <i class="fas fa-map-marker-alt"></i>
                                            View on Map
                                        </button>` : ''
                                    }
                                </div>
                            `;
                        });
                        html += '</div>';
                        devicesList.innerHTML = html;
                    } else {
                        devicesList.innerHTML = '<div style="text-align: center; padding: 40px; color: #64748b;"><i class="fas fa-mobile-alt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i><br>No devices assigned</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching user devices:', error);
                    devicesList.innerHTML = '<div style="text-align: center; padding: 20px; color: #ef4444;">Error loading devices</div>';
                });
        }
        
        function closeUserDevicesModal() {
            const modal = document.getElementById('userDevicesModal');
            modal.classList.remove('active');
        }
        
        function viewDeviceLocation(lat, lng, deviceCode) {
            // Open tracking page with device location
            window.open(`admin-tracking.php?device=${encodeURIComponent(deviceCode)}&lat=${lat}&lng=${lng}`, '_blank');
        }
        
        // Close modal when clicking outside
        document.getElementById('userDevicesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserDevicesModal();
            }
        });
        
        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>