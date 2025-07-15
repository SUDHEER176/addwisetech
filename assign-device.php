<?php
session_start();
require_once 'config.php';

$device_code = $_POST['device_code'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;

if (!$device_code || !$user_id) {
    echo "Invalid request";
    exit;
}

// Check if device is already assigned
$stmt = $pdo->prepare("SELECT status FROM devices WHERE device_code = ?");
$stmt->execute([$device_code]);
$status = $stmt->fetchColumn();
if ($status === 'assigned') {
    echo "Device is already assigned to another user.";
    exit;
}

// Assign device to user and update location if provided
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE devices SET user_id = ?, status = 'assigned', assigned_at = NOW() WHERE device_code = ?");
    $stmt->execute([$user_id, $device_code]);

    if ($latitude && $longitude) {
        $stmt2 = $pdo->prepare("UPDATE devices SET latitude = ?, longitude = ?, location_updated_at = NOW() WHERE device_code = ?");
        $stmt2->execute([$latitude, $longitude, $device_code]);
    }

    $pdo->commit();
    echo "Device assigned successfully (success)";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error assigning device: " . $e->getMessage();
}
?>