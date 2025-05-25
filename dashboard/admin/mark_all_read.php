<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $query = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        throw new Exception("Failed to prepare update statement: " . $conn->error);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    } else {
        throw new Exception("Failed to mark notifications as read: " . $stmt->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 