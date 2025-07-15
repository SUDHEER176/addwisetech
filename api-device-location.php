<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

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
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection error']);
            exit();
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function close() {
        $this->conn->close();
    }
}

// Get device location
function getDeviceLocation($device_code) {
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "SELECT device_code, latitude, longitude, location_updated_at 
            FROM device_locations 
            WHERE device_code = ? 
            ORDER BY location_updated_at DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        $database->close();
        return ['success' => false, 'message' => 'Database query error'];
    }
    
    $stmt->bind_param("s", $device_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $database->close();
        return [
            'success' => true,
            'device_code' => $row['device_code'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'updated_at' => $row['location_updated_at']
        ];
    } else {
        $database->close();
        return ['success' => false, 'message' => 'Device not found or no location data'];
    }
}

// Get device route
function getDeviceRoute($device_code) {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "SELECT latitude, longitude, location_updated_at 
            FROM device_locations 
            WHERE device_code = ? 
            ORDER BY location_updated_at ASC";
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        $database->close();
        return ['success' => false, 'message' => 'Database query error'];
    }

    $stmt->bind_param("s", $device_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $route = [];
    while ($row = $result->fetch_assoc()) {
        $route[] = [
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'updated_at' => $row['location_updated_at']
        ];
    }

    $database->close();

    if (count($route) > 0) {
        return ['success' => true, 'route' => $route];
    } else {
        return ['success' => false, 'message' => 'No route data found for this device'];
    }
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['device_code']) || empty($_GET['device_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Device code is required']);
        exit();
    }
    
    $device_code = trim($_GET['device_code']);
    
    // Check if route is requested
    if (isset($_GET['route']) && $_GET['route'] === 'true') {
        $result = getDeviceRoute($device_code);
    } else {
        $result = getDeviceLocation($device_code);
    }
    
    echo json_encode($result);
    exit();
}

// Handle POST request for updating device location
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $device_code = isset($input['device_code']) ? trim($input['device_code']) : '';
    $latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
    $longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;

    if (!$device_code || $latitude === null || $longitude === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'device_code, latitude, and longitude are required']);
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();

    // Insert new location
    $sql = "INSERT INTO device_locations (device_code, latitude, longitude) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query error']);
        $database->close();
        exit();
    }

    $stmt->bind_param("sdd", $device_code, $latitude, $longitude);
    $success = $stmt->execute();

    if ($success) {
        // Fetch the latest location for this device
        $sql2 = "SELECT latitude, longitude, location_updated_at FROM device_locations WHERE device_code = ? ORDER BY location_updated_at DESC LIMIT 1";
        $stmt2 = $db->prepare($sql2);
        $stmt2->bind_param("s", $device_code);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();

        // Calculate active status (active if updated within last 5 minutes)
        $last_update = strtotime($row['location_updated_at']);
        $is_active = (time() - $last_update) < 300; // 5 minutes

        echo json_encode([
            'success' => true,
            'device_code' => $device_code,
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'status' => $is_active ? 'active' : 'inactive'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Device not found or insert failed']);
    }
    $database->close(); // Only close after all queries
    exit();
}

// If not GET or POST
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit();
?>