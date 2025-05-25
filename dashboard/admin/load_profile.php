<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get user profile from database
$query = "SELECT first_name, last_name, email, phone, address, profile_photo 
          FROM users 
          WHERE user_id = ?";
          
$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo json_encode(['error' => 'Failed to prepare statement: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($profile = $result->fetch_assoc()) {
    // Update session with latest data
    $_SESSION['first_name'] = $profile['first_name'];
    $_SESSION['last_name'] = $profile['last_name'];
    $_SESSION['email'] = $profile['email'];
    
    echo json_encode([
        'success' => true,
        'profile' => $profile
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Profile not found'
    ]);
}
?> 