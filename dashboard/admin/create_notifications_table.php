<?php
require_once '../../config/database.php';

// Create notifications table if it doesn't exist
$query = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($query)) {
    echo "Notifications table created or already exists successfully";
} else {
    echo "Error creating notifications table: " . $conn->error;
}
?> 