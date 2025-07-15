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

// Device tracking class
class DeviceTracker {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function getTrackableDevices() {
        $sql = "SELECT d.id, d.device_code, d.latitude, d.longitude, d.location_updated_at, u.email as user_email 
                FROM devices d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.status IN ('assigned', 'active') 
                ORDER BY d.location_updated_at DESC";
        $result = $this->db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    public function getDeviceLocation($device_code) {
        $sql = "SELECT device_code, latitude, longitude, location_updated_at 
                FROM devices 
                WHERE device_code = ? AND latitude IS NOT NULL AND longitude IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $device_code);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
}

// Initialize classes
$database = new Database();
$deviceTracker = new DeviceTracker($database);

// Get trackable devices
$trackable_devices = $deviceTracker->getTrackableDevices();

// Close database connection
$database->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking - AddWise Admin</title>
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
            justify-content: space-between;
        }
        
        .card-header-left {
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
            background: #3b82f6;
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
        
        /* Tracking Controls */
        .tracking-controls {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 16px;
            align-items: end;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s;
        }
        
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
        
        .btn-danger {
            background: #ef4444;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .tracking-status {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tracking-status.active {
            background: #dcfce7;
            color: #166534;
        }
        
        .tracking-status.inactive {
            background: #f1f5f9;
            color: #64748b;
        }
        
        /* Map Container */
        .map-container {
            height: 500px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        /* Device Info */
        .device-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .device-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }
        
        /* Device List */
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
            transition: all 0.2s ease;
        }
        
        .device-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .device-item.active {
            background: #dbeafe;
            border-color: #3b82f6;
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
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .device-user {
            font-size: 12px;
            color: #64748b;
        }
        
        .device-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
            
            .tracking-controls {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .user-details {
                display: none;
            }
            
            .map-container {
                height: 300px;
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
                <a href="admin-users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </div>
            <div class="nav-item">
                <a href="admin-tracking.php" class="nav-link active">
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
                <h1 class="page-title">Live Tracking</h1>
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
            <!-- Tracking Controls -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <div class="card-title">Device Location Tracking</div>
                            <div class="card-subtitle">Monitor and track device locations in real-time</div>
                        </div>
                    </div>
                    <div class="tracking-status inactive" id="trackingStatus">
                        Tracking: Inactive
                    </div>
                </div>
                <div class="card-content">
                    <div class="tracking-controls">
                        <div class="form-group">
                            <label class="form-label" for="deviceSelect">Select Device to Track</label>
                            <select id="deviceSelect" class="form-select">
                                <option value="">Choose a device...</option>
                                <?php foreach ($trackable_devices as $device): ?>
                                    <option value="<?php echo htmlspecialchars($device['device_code']); ?>" 
                                            data-lat="<?php echo $device['latitude']; ?>" 
                                            data-lng="<?php echo $device['longitude']; ?>"
                                            data-user="<?php echo htmlspecialchars($device['user_email']); ?>">
                                        <?php echo htmlspecialchars($device['device_code']); ?> 
                                        (<?php echo htmlspecialchars($device['user_email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button id="startTrackingBtn" class="btn btn-success">
                            <i class="fas fa-play"></i>
                            Start Tracking
                        </button>
                        <button id="stopTrackingBtn" class="btn btn-danger" style="display: none;">
                            <i class="fas fa-stop"></i>
                            Stop Tracking
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Map -->
            <div class="card">
                <div class="card-content">
                    <div class="map-container" id="map"></div>
                    
                    <div class="device-info" id="deviceInfo" style="display: none;">
                        <h4>
                            <i class="fas fa-info-circle"></i>
                            Device Information
                        </h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Device Code</span>
                                <span class="info-value" id="infoDeviceCode">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Assigned User</span>
                                <span class="info-value" id="infoUserEmail">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Latitude</span>
                                <span class="info-value" id="infoLatitude">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Longitude</span>
                                <span class="info-value" id="infoLongitude">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value" id="infoLastUpdated">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value" id="infoStatus">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Available Devices -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div>
                            <div class="card-title">Trackable Devices</div>
                            <div class="card-subtitle"><?php echo count($trackable_devices); ?> devices available for tracking</div>
                        </div>
                    </div>
                </div>
                <div class="card-content">
                    <div class="device-list">
                        <?php if (empty($trackable_devices)): ?>
                            <div style="text-align: center; padding: 40px; color: #64748b;">
                                <i class="fas fa-mobile-alt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                <p>No trackable devices found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($trackable_devices as $device): ?>
                                <div class="device-item" data-device-code="<?php echo htmlspecialchars($device['device_code']); ?>">
                                    <div class="device-details">
                                        <div class="device-indicator"></div>
                                        <div>
                                            <div class="device-code"><?php echo htmlspecialchars($device['device_code']); ?></div>
                                            <div class="device-user"><?php echo htmlspecialchars($device['user_email']); ?></div>
                                        </div>
                                    </div>
                                    <div class="device-actions">
                                        <?php if ($device['latitude'] && $device['longitude']): ?>
                                            <button class="btn btn-sm" onclick="showDeviceOnMap('<?php echo htmlspecialchars($device['device_code']); ?>', <?php echo $device['latitude']; ?>, <?php echo $device['longitude']; ?>, '<?php echo htmlspecialchars($device['user_email']); ?>')">
                                                <i class="fas fa-map-marker-alt"></i>
                                                View on Map
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: #64748b;">No location data</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Global variables
        let map, currentMarker, trackingInterval;
        let isTracking = false;
        let currentDevice = null;
        
        // Initialize map
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            setupEventListeners();
        });
        
        function initMap() {
            // Initialize map centered on India
            map = L.map('map').setView([20.5937, 78.9629], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // DO NOT add any static markers here - only show live tracking marker
        }
        
        function setupEventListeners() {
            const deviceSelect = document.getElementById('deviceSelect');
            const startBtn = document.getElementById('startTrackingBtn');
            const stopBtn = document.getElementById('stopTrackingBtn');
            
            startBtn.addEventListener('click', function() {
                const selectedDevice = deviceSelect.value;
                if (selectedDevice) {
                    startTracking(selectedDevice);
                } else {
                    alert('Please select a device to track');
                }
            });
            
            stopBtn.addEventListener('click', stopTracking);
            
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
        }
        
        function startTracking(deviceCode) {
            if (isTracking) {
                stopTracking();
            }
            
            currentDevice = deviceCode;
            isTracking = true;
            
            // Update UI
            updateTrackingStatus('Active - Tracking ' + deviceCode);
            document.getElementById('startTrackingBtn').style.display = 'none';
            document.getElementById('stopTrackingBtn').style.display = 'inline-flex';
            
            // Highlight selected device
            highlightDevice(deviceCode);
            
            // Clear any existing markers
            if (currentMarker) {
                map.removeLayer(currentMarker);
                currentMarker = null;
            }
            
            // Initial location update
            updateDeviceLocation(deviceCode);
            
            // Set up periodic updates every 5 seconds
            trackingInterval = setInterval(() => {
                updateDeviceLocation(deviceCode);
            }, 5000);
        }
        
        function stopTracking() {
            if (trackingInterval) {
                clearInterval(trackingInterval);
                trackingInterval = null;
            }
            
            isTracking = false;
            currentDevice = null;
            
            // Clear marker
            if (currentMarker) {
                map.removeLayer(currentMarker);
                currentMarker = null;
            }
            
            // Update UI
            updateTrackingStatus('Tracking: Inactive');
            document.getElementById('startTrackingBtn').style.display = 'inline-flex';
            document.getElementById('stopTrackingBtn').style.display = 'none';
            document.getElementById('deviceInfo').style.display = 'none';
            
            // Remove highlight
            document.querySelectorAll('.device-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Reset map view
            map.setView([20.5937, 78.9629], 5);
        }
        
        function updateTrackingStatus(status) {
            const statusElement = document.getElementById('trackingStatus');
            statusElement.textContent = status;
            
            if (status.includes('Inactive')) {
                statusElement.className = 'tracking-status inactive';
            } else {
                statusElement.className = 'tracking-status active';
            }
        }
        
        function updateDeviceLocation(deviceCode) {
            fetch(`api-device-location.php?device_code=${encodeURIComponent(deviceCode)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.latitude && data.longitude) {
                        const lat = parseFloat(data.latitude);
                        const lng = parseFloat(data.longitude);
                        
                        // Remove existing marker
                        if (currentMarker) {
                            map.removeLayer(currentMarker);
                        }
                        
                        // Create new live tracking marker with red color
                        currentMarker = L.marker([lat, lng], {
                            icon: L.icon({
                                iconUrl: 'data:image/svg+xml;base64,' + btoa(`
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ef4444" width="32" height="32">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                `),
                                iconSize: [32, 32],
                                iconAnchor: [16, 32],
                                popupAnchor: [0, -32]
                            })
                        }).addTo(map);
                        
                        currentMarker.bindPopup(`
                            <strong>🔴 LIVE: ${deviceCode}</strong><br>
                            Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}<br>
                            Updated: ${new Date(data.updated_at).toLocaleString()}
                        `).openPopup();
                        
                        // Center map on device location
                        map.setView([lat, lng], 15, { animate: true });
                        
                        // Update device info
                        updateDeviceInfo(deviceCode, lat, lng, data.updated_at);
                    } else {
                        console.log('No location data available for device:', deviceCode);
                        // Remove marker if no data
                        if (currentMarker) {
                            map.removeLayer(currentMarker);
                            currentMarker = null;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching location:', error);
                });
        }
        
        function updateDeviceInfo(deviceCode, lat, lng, updatedAt) {
            const deviceSelect = document.getElementById('deviceSelect');
            const selectedOption = deviceSelect.querySelector(`option[value="${deviceCode}"]`);
            const userEmail = selectedOption ? selectedOption.dataset.user : 'Unknown';
            
            document.getElementById('infoDeviceCode').textContent = deviceCode;
            document.getElementById('infoUserEmail').textContent = userEmail;
            document.getElementById('infoLatitude').textContent = lat.toFixed(6);
            document.getElementById('infoLongitude').textContent = lng.toFixed(6);
            document.getElementById('infoLastUpdated').textContent = new Date(updatedAt).toLocaleString();
            document.getElementById('infoStatus').textContent = 'Active Tracking';
            document.getElementById('deviceInfo').style.display = 'block';
        }
        
        function showDeviceOnMap(deviceCode, lat, lng, userEmail) {
            // Stop current tracking if active
            if (isTracking) {
                stopTracking();
            }
            
            // Clear any existing markers
            if (currentMarker) {
                map.removeLayer(currentMarker);
            }
            
            // Center map on device location
            map.setView([lat, lng], 15);
            
            // Add new marker
            currentMarker = L.marker([lat, lng]).addTo(map);
            currentMarker.bindPopup(`
                <strong>${deviceCode}</strong><br>
                User: ${userEmail}<br>
                Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}
            `).openPopup();
            
            // Update device selector
            document.getElementById('deviceSelect').value = deviceCode;
            
            // Highlight device in list
            highlightDevice(deviceCode);
        }
        
        function highlightDevice(deviceCode) {
            document.querySelectorAll('.device-item').forEach(item => {
                item.classList.remove('active');
                if (item.dataset.deviceCode === deviceCode) {
                    item.classList.add('active');
                }
            });
        }
        
        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('mobileOverlay').classList.remove('active');
            }
        });
    </script>
</body>
</html>