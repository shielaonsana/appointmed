<?php
session_start();
require_once '../config/database.php';

// Add connection check
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Initialize error array
    $errors = [];

    // Validate full name
    if (empty($_POST['fullname'])) {
        $errors[] = "Full name is required";
    } elseif (strlen($_POST['fullname']) < 2) {
        $errors[] = "Full name must be at least 2 characters";
    }

    // Validate email
    if (empty($_POST['email'])) {
        $errors[] = "Email is required";
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $check_email->bind_param("s", $_POST['email']);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = "Email already exists";
        }
    }

    // Validate password
    if (empty($_POST['password'])) {
        $errors[] = "Password is required";
    } elseif (strlen($_POST['password']) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    // Check for validation errors
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: signup.php");
        exit();
    }

    // Add this to process_signup.php before handling file upload
    $target_dir = "../uploads/profile_images/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $full_name = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $account_type = 'Patient'; // Default to Patient

    // Handle profile image upload
    $profile_image = 'default.png';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        // Validate image file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $errors[] = "Only JPG, JPEG & PNG files are allowed";
            $_SESSION['errors'] = $errors;
            header("Location: signup.php");
            exit();
        }

        if ($_FILES['profile_image']['size'] > $max_size) {
            $errors[] = "File size must be less than 5MB";
            $_SESSION['errors'] = $errors;
            header("Location: signup.php");
            exit();
        }

        $target_dir = "../uploads/profile_images/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
        $profile_image = uniqid() . '.' . $file_extension;
        
        if (!move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_dir . $profile_image)) {
            $errors[] = "Failed to upload image";
            $_SESSION['errors'] = $errors;
            header("Location: signup.php");
            exit();
        }
    }

    // Insert new user if no errors
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (full_name, email, password_hash, account_type, profile_image) 
            VALUES (?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $full_name, $email, $password_hash, $account_type, $profile_image);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! Please login.";
        header("Location: login.php");
    } else {
        $errors[] = "Registration failed: " . $stmt->error;
        $_SESSION['errors'] = $errors;
        header("Location: signup.php");
    }
    
    $stmt->close();
    $conn->close();
    exit();
}
?>