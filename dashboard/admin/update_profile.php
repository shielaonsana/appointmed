<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$response = ['success' => false, 'message' => ''];

try {
    // Get user ID from session
    $userId = $_SESSION['user_id'];
    
    // Handle file upload if a new photo is provided
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_photo']['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }
        
        if ($_FILES['profile_photo']['size'] > $maxFileSize) {
            throw new Exception('File size too large. Maximum size is 5MB.');
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = 'images/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory.');
            }
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $newFilename = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFilename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadPath)) {
            // Update profile photo in database
            $query = "UPDATE users SET profile_photo = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception('Failed to prepare photo update statement: ' . $conn->error);
            }
            $stmt->bind_param("si", $newFilename, $userId);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update profile photo: ' . $stmt->error);
            }
        } else {
            throw new Exception('Failed to upload file. Error: ' . error_get_last()['message']);
        }
    }
    
    // Update other profile information
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email)) {
        throw new Exception('First name, last name, and email are required.');
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format.');
    }
    
    // Check if email is already taken by another user
    $checkEmailQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($checkEmailQuery);
    if ($stmt === false) {
        throw new Exception('Failed to prepare email check statement: ' . $conn->error);
    }
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Email is already taken by another user.');
    }

    // First check if the user exists
    $checkUserQuery = "SELECT user_id FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($checkUserQuery);
    if ($stmt === false) {
        throw new Exception('Failed to prepare user check statement: ' . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // User doesn't exist, create new user
        $query = "INSERT INTO users (user_id, first_name, last_name, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception('Failed to prepare insert statement: ' . $conn->error);
        }
        $stmt->bind_param("isssss", $userId, $firstName, $lastName, $email, $phone, $address);
    } else {
        // User exists, update user
        $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception('Failed to prepare update statement: ' . $conn->error);
        }
        $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $address, $userId);
    }
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
        $response['first_name'] = $firstName;
        $response['last_name'] = $lastName;
        
        // Update session data
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['email'] = $email;
    } else {
        throw new Exception('Failed to update profile information: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Profile Update Error: ' . $e->getMessage());
}

echo json_encode($response);
?> 