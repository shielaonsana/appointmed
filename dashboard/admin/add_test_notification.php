<?php
session_start();
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Create a test notification
    $message = "This is a test notification " . date('H:i:s');
    $type = 'system';
    
    $sql = "INSERT INTO notifications (message, type, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ss", $message, $type);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Test notification added successfully'
            ]);
        } else {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
    } else {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 