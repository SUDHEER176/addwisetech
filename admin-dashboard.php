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

// Device management class
class DeviceManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function generateDevices($quantity, $admin_id) {
        if (!is_numeric($quantity) || $quantity <= 0 || $quantity > 100) {
            return ['success' => false, 'message' => 'Please enter a valid quantity (1-100).'];
        }
        
        $devices = [];
        $success_count = 0;
        
        for ($i = 0; $i < $quantity; $i++) {
            $code = 'DV' . strtoupper(bin2hex(random_bytes(4)));
            $sql = "INSERT INTO devices (device_code, status, created_by, created_at) VALUES (?, 'unassigned', ?, NOW())";
            $stmt = $this->db->prepare($sql);
            
            if ($stmt && $stmt->bind_param("si", $code, $admin_id) && $stmt->execute()) {
                $devices[] = $code;
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            return ['success' => true, 'message' => "Successfully generated {$success_count} device codes.", 'devices' => $devices];
        } else {
            return ['success' => false, 'message' => 'Failed to generate device codes.'];
        }
    }
    
    public function getAllDevices() {
        $sql = "SELECT d.*, u.email as user_email 
                FROM devices d 
                LEFT JOIN users u ON d.user_id = u.id 
                ORDER BY d.created_at DESC";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    public function getAssignedDevices() {
        $sql = "SELECT d.id, d.device_code, d.latitude, d.longitude, d.location_updated_at, u.email as user_email 
                FROM devices d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.status IN ('assigned', 'active') 
                ORDER BY d.created_at DESC";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getDeviceStats() {
        $stats = [
            'total' => 0,
            'assigned' => 0,
            'unassigned' => 0,
            'active' => 0
        ];
        
        $sql = "SELECT status, COUNT(*) as count FROM devices GROUP BY status";
        $result = $this->db->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stats['total'] += $row['count'];
                if ($row['status'] === 'assigned' || $row['status'] === 'active') {
                    $stats['assigned'] += $row['count'];
                }
                if ($row['status'] === 'unassigned') {
                    $stats['unassigned'] = $row['count'];
                }
                if ($row['status'] === 'active') {
                    $stats['active'] = $row['count'];
                }
            }
        }
        
        return $stats;
    }
}

// User management class
class UserManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function getActiveUsers() {
        $sql = "SELECT COUNT(*) as count FROM users WHERE is_active = 1";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_assoc()['count'] : 0;
    }
}

// Initialize classes
$database = new Database();
$deviceManager = new DeviceManager($database);
$userManager = new UserManager($database);

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
        $result = $deviceManager->generateDevices($quantity, $_SESSION['admin_id']);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
}

// Get data for display
$devices = $deviceManager->getAllDevices();
$assigned_devices = $deviceManager->getAssignedDevices();
$device_stats = $deviceManager->getDeviceStats();
$active_users = $userManager->getActiveUsers();

// Close database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AddWise - Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.blue::before { background: #3b82f6; }
        .stat-card.green::before { background: #10b981; }
        .stat-card.yellow::before { background: #f59e0b; }
        .stat-card.purple::before { background: #8b5cf6; }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .stat-info h3 {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .stat-icon.blue { background: #3b82f6; }
        .stat-icon.green { background: #10b981; }
        .stat-icon.yellow { background: #f59e0b; }
        .stat-icon.purple { background: #8b5cf6; }
        
        .stat-change {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
        }
        
        .stat-change.positive {
            color: #10b981;
        }
        
        .stat-change.neutral {
            color: #64748b;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 32px;
            margin-bottom: 32px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
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
        }
        
        .card-icon.blue { background: #3b82f6; }
        .card-icon.green { background: #10b981; }
        .card-icon.purple { background: #8b5cf6; }
        
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
        
        /* Map Container */
        .map-container {
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
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
        
        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .activity-indicator.active { background: #10b981; }
        .activity-indicator.assigned { background: #3b82f6; }
        .activity-indicator.unassigned { background: #64748b; }
        
        .activity-content {
            flex: 1;
            min-width: 0;
        }
        
        .activity-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .activity-subtitle {
            font-size: 12px;
            color: #64748b;
        }
        
        .activity-status {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .activity-status.active {
            background: #dcfce7;
            color: #166534;
        }
        
        .activity-status.assigned {
            background: #dbeafe;
            color: #1d4ed8;
        }
        
        .activity-status.unassigned {
            background: #f1f5f9;
            color: #475569;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
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
                grid-template-columns: 1fr;
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
                <a href="admin-dashboard.php" class="nav-link active">
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
                <a href="admin-users.php" class="nav-link">
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
                <h1 class="page-title">Dashboard</h1>
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
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3>Total Devices</h3>
                            <div class="stat-value"><?php echo $device_stats['total']; ?></div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                +12% vs last month
                            </div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3>Assigned Devices</h3>
                            <div class="stat-value"><?php echo $device_stats['assigned']; ?></div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                +8% vs last month
                            </div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card yellow">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3>Unassigned Devices</h3>
                            <div class="stat-value"><?php echo $device_stats['unassigned']; ?></div>
                            <div class="stat-change neutral">
                                <i class="fas fa-minus"></i>
                                No change
                            </div>
                        </div>
                        <div class="stat-icon yellow">
                            <i class="fas fa-map-pin"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3>Active Users</h3>
                            <div class="stat-value"><?php echo $active_users; ?></div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                +3% vs last month
                            </div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Live Device Tracking -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon blue">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <div class="card-title">Live Device Tracking</div>
                            <div class="card-subtitle">Monitor device locations in real-time</div>
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="map-container" id="map"></div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon green">
                            <i class="fas fa-activity"></i>
                        </div>
                        <div>
                            <div class="card-title">Recent Activity</div>
                            <div class="card-subtitle">Latest device updates</div>
                        </div>
                    </div>
                    <div class="card-content">
                        <?php foreach (array_slice($devices, 0, 5) as $device): ?>
                            <div class="activity-item">
                                <div class="activity-indicator <?php echo $device['status']; ?>"></div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($device['device_code']); ?></div>
                                    <div class="activity-subtitle">
                                        <?php echo $device['user_email'] ? htmlspecialchars($device['user_email']) : 'Unassigned'; ?>
                                    </div>
                                </div>
                                <div class="activity-status <?php echo $device['status']; ?>">
                                    <?php echo ucfirst($device['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Device Generation -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon purple">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div>
                        <div class="card-title">Generate Device Codes</div>
                        <div class="card-subtitle">Create new device codes for assignment</div>
                    </div>
                </div>
                <div class="card-content">
                    <form method="POST" style="max-width: 400px;">
                        <div class="form-group">
                            <label class="form-label" for="quantity">Number of Codes (1-100)</label>
                            <input type="number" id="quantity" name="quantity" class="form-input" min="1" max="100" value="1" required>
                        </div>
                        <button type="submit" name="generate" class="btn">
                            <i class="fas fa-plus"></i>
                            Generate Codes
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        let map = L.map('map').setView([20.5937, 78.9629], 5);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Add device markers
        <?php foreach ($assigned_devices as $device): ?>
            <?php if ($device['latitude'] && $device['longitude']): ?>
                L.marker([<?php echo $device['latitude']; ?>, <?php echo $device['longitude']; ?>])
                    .addTo(map)
                    .bindPopup('<?php echo htmlspecialchars($device['device_code']); ?><br><?php echo htmlspecialchars($device['user_email']); ?>');
            <?php endif; ?>
        <?php endforeach; ?>
        
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