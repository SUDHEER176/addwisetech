<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

// Get user devices
function getUserDevices($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "SELECT device_code, status, assigned_at, latitude, longitude, location_updated_at 
            FROM devices 
            WHERE user_id = ? 
            ORDER BY assigned_at DESC";
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        $database->close();
        return ['success' => false, 'message' => 'Database query error'];
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $devices = [];
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
    
    $database->close();
    return [
        'success' => true,
        'devices' => $devices
    ];
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid user ID is required']);
        exit();
    }
    
    $user_id = (int)$_GET['user_id'];
    $result = getUserDevices($user_id);
    
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>