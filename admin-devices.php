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
            $code = 'DV' . strtoupper(bin2hex(random_bytes(7)));
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
    
    public function assignDevice($device_id, $user_id) {
        if (!is_numeric($device_id) || !is_numeric($user_id)) {
            return ['success' => false, 'message' => 'Invalid device or user selection.'];
        }
        
        // Check if device is available
        $check_sql = "SELECT id FROM devices WHERE id = ? AND status = 'unassigned'";
        $check_stmt = $this->db->prepare($check_sql);
        $check_stmt->bind_param("i", $device_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Device is no longer available for assignment.'];
        }
        
        // Assign device
        $sql = "UPDATE devices SET user_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt && $stmt->bind_param("ii", $user_id, $device_id) && $stmt->execute()) {
            return ['success' => true, 'message' => 'Device successfully assigned to user.'];
        } else {
            return ['success' => false, 'message' => 'Failed to assign device.'];
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
    
    public function getUnassignedDevices() {
        $sql = "SELECT id, device_code FROM devices WHERE status = 'unassigned' ORDER BY created_at DESC";
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
        $sql = "SELECT id, email FROM users WHERE is_active = 1 ORDER BY email ASC";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
    
    if (isset($_POST['assign'])) {
        $device_id = filter_var($_POST['device_id'], FILTER_VALIDATE_INT);
        $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        $result = $deviceManager->assignDevice($device_id, $user_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    }
}

// Get data for display
$devices = $deviceManager->getAllDevices();
$unassigned_devices = $deviceManager->getUnassignedDevices();
$users = $userManager->getActiveUsers();
$device_stats = $deviceManager->getDeviceStats();

// Close database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devices - AddWise Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
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
        
        .stat-value.blue { color: #3b82f6; }
        .stat-value.green { color: #10b981; }
        .stat-value.yellow { color: #f59e0b; }
        
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
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
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
        
        .form-input,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus,
        .form-select:focus {
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
        
        .device-code {
            font-family: 'Monaco', 'Menlo', monospace;
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
        
        .status-assigned {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-unassigned {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-active {
            background: #dbeafe;
            color: #1d4ed8;
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
            max-width: 400px;
            width: 90%;
            text-align: center;
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
            margin-bottom: 16px;
        }
        
        .qr-container {
            margin: 24px 0;
            display: flex;
            justify-content: center;
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
            
            .form-grid {
                grid-template-columns: 1fr;
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
                <a href="admin-devices.php" class="nav-link active">
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
                <h1 class="page-title">Devices</h1>
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
                    <div class="stat-value blue"><?php echo $device_stats['total']; ?></div>
                    <div class="stat-label">Total Devices</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value green"><?php echo $device_stats['assigned']; ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value yellow"><?php echo $device_stats['unassigned']; ?></div>
                    <div class="stat-label">Available</div>
                </div>
            </div>
            
            <!-- Forms Grid -->
            <div class="form-grid">
                <!-- Generate Device Codes -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon blue">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <div class="card-title">Generate Device Codes</div>
                            <div class="card-subtitle">Create new device codes for assignment</div>
                        </div>
                    </div>
                    <div class="card-content">
                        <form method="POST">
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
                 
            
            <!-- Devices Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon purple">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <div class="card-title">All Devices</div>
                        <div class="card-subtitle"><?php echo count($devices); ?> devices total</div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Device Code</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Created</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($devices)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                            <i class="fas fa-mobile-alt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                            <br>No devices found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($devices as $device): ?>
                                        <tr>
                                            <td>
                                                <span class="device-code"><?php echo htmlspecialchars($device['device_code']); ?></span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $device['status']; ?>">
                                                    <?php echo ucfirst($device['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $device['user_email'] ? htmlspecialchars($device['user_email']) : '-'; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <i class="fas fa-calendar" style="color: #64748b; font-size: 12px;"></i>
                                                    <?php echo date('M j, Y', strtotime($device['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($device['latitude'] && $device['longitude']): ?>
                                                    <div style="font-size: 12px;">
                                                        <div><?php echo number_format($device['latitude'], 4); ?>, <?php echo number_format($device['longitude'], 4); ?></div>
                                                        <?php if ($device['location_updated_at']): ?>
                                                            <div style="color: #64748b; margin-top: 2px;">
                                                                <?php echo date('M j, H:i', strtotime($device['location_updated_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #64748b; font-size: 12px;">No location data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <button onclick="showQR('<?php echo htmlspecialchars($device['device_code']); ?>')" class="btn btn-sm">
                                                        <i class="fas fa-qrcode"></i>
                                                        QR
                                                    </button>
                                                    <?php if ($device['latitude'] && $device['longitude']): ?>
                                                        <button onclick="viewLocation(<?php echo $device['latitude']; ?>, <?php echo $device['longitude']; ?>, '<?php echo htmlspecialchars($device['device_code']); ?>')" class="btn btn-sm btn-success">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            Map
                                                        </button>
                                                    <?php endif; ?>
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
    
    <!-- QR Code Modal -->
    <div class="modal" id="qrModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeQR()">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="modal-title">Device QR Code</h3>
            <div class="qr-container" id="qrContainer"></div>
            <div style="margin-bottom: 16px;">
                <strong id="qrDeviceCode"></strong>
            </div>
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button onclick="downloadQR()" class="btn">
                    <i class="fas fa-download"></i>
                    Download
                </button>
                <button onclick="closeQR()" class="btn" style="background: #64748b;">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        let currentQRCode = null;
        let currentDeviceCode = '';
        
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
        
        // QR Code functions
        function showQR(deviceCode) {
            currentDeviceCode = deviceCode;
            const modal = document.getElementById('qrModal');
            const container = document.getElementById('qrContainer');
            const codeElement = document.getElementById('qrDeviceCode');

            // Clear previous QR code
            container.innerHTML = '';
            codeElement.textContent = deviceCode;

            // Generate new QR code
            try {
                // Use the global QRCode constructor from qrcodejs
                currentQRCode = new QRCode(container, {
                    text: deviceCode,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });

                // Wait for QR code to render before showing modal
                setTimeout(() => {
                    modal.classList.add('active');
                }, 100); // Small delay to ensure QR is rendered
            } catch (error) {
                console.error('Error generating QR code:', error);
                container.innerHTML = '<p style="color: #ef4444;">Error generating QR code</p>';
                modal.classList.add('active');
            }
        }
        
        function closeQR() {
            const modal = document.getElementById('qrModal');
            modal.classList.remove('active');
            
            // Clear QR code
            setTimeout(() => {
                document.getElementById('qrContainer').innerHTML = '';
                currentQRCode = null;
            }, 300);
        }
        
        function downloadQR() {
            // Try to get canvas first, fallback to img
            const canvas = document.querySelector('#qrContainer canvas');
            if (canvas) {
                const link = document.createElement('a');
                link.download = `device-${currentDeviceCode}-qr.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            } else {
                // Fallback for browsers that render QR as img
                const img = document.querySelector('#qrContainer img');
                if (img) {
                    const link = document.createElement('a');
                    link.download = `device-${currentDeviceCode}-qr.png`;
                    link.href = img.src;
                    link.click();
                } else {
                    alert('QR code image not found.');
                }
            }
        }
        
        function viewLocation(lat, lng, deviceCode) {
            // Open tracking page with device pre-selected
            window.open(`admin-tracking.php?device=${encodeURIComponent(deviceCode)}&lat=${lat}&lng=${lng}`, '_blank');
        }
        
        // Close modal when clicking outside
        document.getElementById('qrModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQR();
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