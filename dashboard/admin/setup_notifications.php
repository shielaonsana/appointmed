<?php
require_once '../../config/database.php';

// Drop the existing notifications table if it exists
$conn->query("DROP TABLE IF EXISTS notifications");

// Create notifications table without foreign key constraint
$createTable = "CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($createTable)) {
    echo "✅ Notifications table created successfully<br>";
    
    // Add some test notifications
    $testNotifications = [
        [
            'user_id' => 1,
            'message' => 'Welcome to the healthcare system!',
            'type' => 'system',
            'is_read' => 0
        ],
        [
            'user_id' => 1,
            'message' => 'New appointment request received',
            'type' => 'appointment',
            'is_read' => 0
        ],
        [
            'user_id' => 1,
            'message' => 'System maintenance scheduled',
            'type' => 'system',
            'is_read' => 0
        ]
    ];
    
    $query = "INSERT INTO notifications (user_id, message, type, is_read) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    foreach ($testNotifications as $notification) {
        $stmt->bind_param("issi", 
            $notification['user_id'],
            $notification['message'],
            $notification['type'],
            $notification['is_read']
        );
        $stmt->execute();
    }
    
    echo "✅ Test notifications added successfully<br>";
} else {
    echo "❌ Error creating notifications table: " . $conn->error . "<br>";
}
?> 