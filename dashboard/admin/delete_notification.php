<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_POST['notification_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Notification ID is required'
    ]);
    exit;
}

$notificationId = $_POST['notification_id'];

try {
    $query = "DELETE FROM notifications WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        throw new Exception("Failed to prepare delete statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $notificationId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    } else {
        throw new Exception("Failed to delete notification: " . $stmt->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 