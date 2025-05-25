<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Debug information
$debug = [
    'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
    'session' => $_SESSION,
    'errors' => []
];

try {
    // Get all notifications for now (we'll filter by user_id later)
    $notificationsQuery = "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($notificationsQuery);
    
    if ($result === false) {
        throw new Exception("Failed to query notifications: " . $conn->error);
    }

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'type' => $row['type'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }

    // Count unread notifications
    $unreadCount = count(array_filter($notifications, function($n) {
        return !$n['is_read'];
    }));

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => $notifications,
        'debug' => $debug
    ]);

} catch (Exception $e) {
    $debug['errors'][] = $e->getMessage();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debug
    ]);
}
?> 