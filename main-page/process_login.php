<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Query to fetch user details
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify the password
        if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['account_type'] = $user['account_type'];

            // Redirect based on account type
            switch ($user['account_type']) {
                case 'Patient':
                    header("Location: ../dashboard/patient/index.php");
                    break;
                case 'Doctor':
                    header("Location: ../dashboard/doctor/index.php");
                    break;
                case 'Admin':
                    header("Location: ../dashboard/admin/index.html");
                    break;
                default:
                    header("Location: ../main-page/login.php?error=invalid_role");
                    break;
            }
            exit();
        } else {
            // Invalid password
            echo "Invalid email or password.";
        }
    } else {
        // User not found
        echo "Invalid email or password.";
    }
}
?>