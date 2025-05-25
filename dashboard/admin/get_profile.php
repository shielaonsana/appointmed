<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

$query = "SELECT full_name, email, phone, address, profile_photo 
          FROM users-admin 
          WHERE user_id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($profile = $result->fetch_assoc()) {
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