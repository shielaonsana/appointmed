<?php
session_start();

// Initialize message variables
$error = '';
$success = '';

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Patient') {
    header("Location: ../../main-page/login.php");
    exit();
}

require_once '../../config/database.php';
$patientId = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Password Change Handling
        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            // Validate fields not empty
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception("All password fields are required!");
            }

            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("User not found!");
            }
            $user = $result->fetch_assoc();
            if (!password_verify($currentPassword, $user['password_hash'])) {
                throw new Exception("Current password is incorrect!");
            }
            // Check if new password is same as current
            if (password_verify($newPassword, $user['password_hash'])) {
                throw new Exception("New password must be different from current password!");
            }
            // Validate password match
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords don't match!");
            }
            // Validate password strength
            if (strlen($newPassword) < 8) {
                throw new Exception("Password must be at least 8 characters");
            }
            if (!preg_match("#[0-9]+#", $newPassword)) {
                throw new Exception("Password must contain at least one number");
            }
            if (!preg_match("#[A-Z]+#", $newPassword)) {
                throw new Exception("Password must contain an uppercase letter");
            }
            if (!preg_match("#[a-z]+#", $newPassword)) {
                throw new Exception("Password must contain a lowercase letter");
            }
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashedPassword, $patientId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update password");
            }
            $success = 'Password updated successfully!';
        }

        // Account Deletion Handling
        if (isset($_POST['delete_account'])) {
            $password = $_POST['delete_password'];
            if (empty($password)) {
                throw new Exception("Password is required to confirm deletion");
            }
            // Verify password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("User not found!");
            }
            $user = $result->fetch_assoc();
            if (!password_verify($password, $user['password_hash'])) {
                throw new Exception("Incorrect password!");
            }
            // Get patient_id for this user
            $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $patientRow = $result->fetch_assoc();
            $realPatientId = $patientRow ? $patientRow['patient_id'] : null;
            // Delete related records first to maintain referential integrity
            if ($realPatientId) {
                // Delete appointments for this patient
                $stmt = $conn->prepare("DELETE FROM appointments WHERE patient_id = ?");
                $stmt->bind_param("i", $realPatientId);
                $stmt->execute();
            }
            // Now delete from patients, notifications, and users
            $stmt = $conn->prepare("DELETE FROM patients WHERE user_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $conn->commit();
            session_destroy();
            header("Location: ../../main-page/login.php?account_deleted=1");
            exit();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Fetch patient's complete profile data
$user_id = $_SESSION['user_id'];
$query = "SELECT u.user_id, u.full_name, u.profile_image, u.account_type, 
                 u.email, u.phone_number, u.gender, u.date_of_birth,
                 p.first_name, p.last_name, p.date_of_birth AS patient_dob
          FROM users u
          LEFT JOIN patients p ON u.user_id = p.user_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $patientProfile = $result->fetch_assoc();

    // Handle name splitting if not in patient_details
    if (empty($patientProfile['first_name']) || empty($patientProfile['last_name'])) {
        $nameParts = explode(' ', $patientProfile['full_name'], 2);
        $patientProfile['first_name'] = $nameParts[0];
        $patientProfile['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
    }

    // Handle profile image path
    $baseImagePath = '../../images/patients/'; // Verify this matches your directory structure
    $imageFile = !empty($patientProfile['profile_image']) ? $patientProfile['profile_image'] : 'default.png';
    $imagePath = $baseImagePath . $imageFile;
    
    // Fallback to default if image doesn't exist
    if (!file_exists($imagePath)) {
        $imagePath = $baseImagePath . 'default.png';
    }

    // Format other fields
    $dateOfBirth = !empty($patientProfile['patient_dob']) ? $patientProfile['patient_dob'] : $patientProfile['date_of_birth'];
    $displayDOB = ($dateOfBirth == '0000-00-00' || empty($dateOfBirth)) 
        ? 'Not specified' 
        : date('F j, Y', strtotime($dateOfBirth));
    
    $gender = !empty($patientProfile['gender']) ? htmlspecialchars($patientProfile['gender']) : 'Not specified';
    $phone = !empty($patientProfile['phone_number']) ? htmlspecialchars($patientProfile['phone_number']) : 'Not specified';
    $email = !empty($patientProfile['email']) ? htmlspecialchars($patientProfile['email']) : 'Not specified';
    $fullName = htmlspecialchars($patientProfile['first_name'] . ' ' . $patientProfile['last_name']);
    $firstName = htmlspecialchars($patientProfile['first_name']);
    
} else {
    // Fallback values if no user found (shouldn't happen for logged-in users)
    $imagePath = 'images/patients/default.png';
    $fullName = 'Patient';
    $firstName = 'Patient';
    $accountType = 'Patient';
    $displayDOB = 'Not specified';
    $gender = 'Not specified';
    $phone = 'Not specified';
    $email = 'Not specified';
}

// After PHP logic for $error and $success, add modal triggers
if ($error) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showErrorModal('".addslashes($error)."');
        });
    </script>";
}
if ($success) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showSuccessModal('".addslashes($success)."');
        });
    </script>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Grand+Hotel&family=Jost:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Winky+Sans:ital,wght@0,300..900;1,300..900&display=swap');


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;

        }

        body {
            background-color: #f5f9ff;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background-color: white;
            border-right: 1px solid #e0e7f1;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            width: 40px;
            height: auto;
            margin-top: 7px;
            margin-left: 20px;
        }

        .logo-text {
            color: #3498db;
            font-weight: bold;
            font-style: italic;
            font-size: 24px;
            margin-left: 5px;
        }

        .nav-menu {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #1f2937;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item.active {
            background-color: #f0f5ff;
            color: #3498db;
            border-left: 3px solid #3498db;
            font-weight: 500;
        }

        .nav-item:hover:not(.active) {
            background-color: #f8fafc;
            color: #3498db;
        }

        .nav-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
            color: #3498db;
        }

        .user-profile {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-top: 1px solid #e0e7f1;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e5e7eb;
            margin-right: 10px;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex-grow: 1;
        }

        .user-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 14px;
        }

        .user-role {
            color: #666;
            font-size: 12px;
        }

        .logout-btn {
            display: block;
            margin: 15px 20px;
            padding: 10px;
            border: 1px solid #ef4444;
            border-radius: 6px;
            color: #ef4444;
            background-color: white;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background-color: #fef2f2;
        }

        .logout-btn i {
            margin-right: 8px;
        }

        .main-content {
            flex-grow: 1;
            margin-left: 260px;
        }

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            background-color: white;
            border-bottom: 1px solid #e0e7f1;
        }

        .menu-toggle {
            display: none;
            font-size: 20px;
            color: #3498db;
            cursor: pointer;
            margin-right: 15px;
        }

        .search-container {
            flex-grow: 1;
            max-width: 500px;
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
        }

        .search-input {
            width: 100%;
            padding: 8px 10px 8px 35px;
            border: 1px solid #e0e7f1;
            border-radius: 20px;
            background-color: #f8fafc;
            outline: none;
            transition: all 0.3s;
        }

        .search-input:focus {
            background-color: white;
            border-color: #bfdbfe;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .notification-container {
            display: flex;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            position: relative;
            color: #3498db;
            background-color: transparent;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background-color: #f0f5ff;
            color: #3498db;
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            margin-left: 15px;
        }

        .user-dropdown img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .content {
            padding: 30px;
        }

        /* Welcome Section */
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .welcome-text h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        /* Settings Styles */
        .settings-container {
            display: block;
            width: 100%;
        }

        .settings-content {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 24px;

            margin: 0 auto;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .settings-section h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e0e7f1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e7f1;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #bfdbfe;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-outline {
            background-color: white;
            border: 1px solid #e0e7f1;
            color: #4b5563;
        }

        .btn-outline:hover {
            background-color: #f8fafc;
        }

        .btn-danger {
            background-color: white;
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .btn-danger:hover {
            background-color: #fef2f2;
        }

        .btn i {
            margin-right: 6px;
        }

        .password-rules {
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .password-requirements {
            margin-top: 5px;
            margin-left: 5px;
            margin-top: 10px;
        }

        .requirement {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #777;
            margin-bottom: 3px;
        }

        .requirement i {
            margin-right: 5px;
            font-size: 10px;
        }

        .requirement.valid, .requirement.valid i {
            color: #2ecc71 !important;
        }
        .requirement.invalid, .requirement.invalid i {
            color: #e74c3c !important;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .danger-zone {
            margin-top: 40px;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #fee2e2;
            background-color: #fef2f2;
        }

        .danger-zone h3 {
            color: #b91c1c;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .danger-zone p {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .hidden {
            display: none;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s linear 0.25s, opacity 0.25s;
        }

        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
            transition-delay: 0s;
        }

        .modal {
            width: 440px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .modal-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e0e7f1;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #b91c1c;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-body p {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .modal-actions {
            padding: 16px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 1px solid #e0e7f1;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
        }

        .password-field-wrapper {
            position: relative;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .form-row {
                flex-wrap: wrap;
            }

            .form-col {
                flex: 1 1 45%;
                min-width: 300px;
            }

            .settings-nav-item {
                padding: 15px 15px;
                font-size: 14px;
            }

            .content {
                padding: 25px;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                left: -260px;
                transition: all 0.3s;
                z-index: 1000;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .user-dropdown span {
                display: none;
            }

            .settings-content {
                padding: 20px;
            }

            .form-col {
                flex: 1 1 100%;
                min-width: auto;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 15px 20px;
            }

            .content {
                padding: 20px;
            }

            .welcome-text h1 {
                font-size: 22px;
            }

            .avatar-upload {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .specialty-tags {
                gap: 8px;
            }

            .settings-nav {
                flex-wrap: wrap;
            }

            .settings-nav-item {
                flex: 1 1 50%;
                text-align: center;
                padding: 12px 10px;
                font-size: 13px;
            }

            .form-actions {
                flex-direction: column-reverse;
                gap: 8px;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                padding: 12px 15px;
            }

            .content {
                padding: 15px;
            }

            .welcome-text h1 {
                font-size: 20px;
            }

            .settings-nav-item {
                flex: 1 1 100%;
                font-size: 12.5px;
                padding: 10px 5px;
            }

            .user-profile {
                padding: 15px;
            }

            .logout-btn {
                margin: 15px;
            }

            .notification-container .icon-btn {
                width: 35px;
                height: 35px;
            }

            .user-dropdown img {
                width: 32px;
                height: 32px;
            }

            .form-control {
                font-size: 13px;
            }

            .form-group label {
                font-size: 13px;
            }

            .specialty-tag {
                font-size: 12px;
                padding: 4px 10px;
            }

            .current-avatar {
                width: 80px;
                height: 80px;
            }
        }

        .alert-danger {
            color: #b91c1c;
            background: #fee2e2;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .alert-success {
            color: #16a34a;
            background: #f0fdf4;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
    </style>
</head>


<body>
    <div class="container">
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <a href="index.php">
                    <img src="images/logo.png" alt="Logo">
                </a>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Search Doctor</span>
                </a>
                <a href="book.php" class="nav-item">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Book Appointment</span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="far fa-clock"></i>
                    <span>Appointment</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Profile</span>
                </a>
                <a href="setting.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo $fullName; ?></div>
                    <div class="user-role">Patient</div>
                </div>
            </div>
            <a href="../../main-page/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Sign Out
            </a>
        </div>
        <div class="main-content">
            <div class="top-bar">
                <div class="menu-toggle" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="search-container">

                </div>
                <div class="notification-container">
                    <div class="icon-btn">
                        <i class="far fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                    <div class="user-dropdown">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($fullName); ?>">
                        <span class="user-name-display"><?php echo $fullName; ?></span>
                    </div>
                </div>
            </div>
            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1>Account Settings</h1>
                    </div>
                </div>

                <!-- Settings Container -->
                <div class="settings-container">
                    <?php if ($error): ?>
                        <div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <!-- Settings Content -->
                    <div class="settings-content">
                        <div class="settings-section">
                            <h2>Change Password</h2>
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">
                                <div class="form-group">
                                    <label for="current-password">Current Password</label>
                                    <div class="password-field-wrapper">
                                        <input type="password" id="current-password" name="current_password" class="form-control" required>
                                        <button type="button" class="password-toggle" data-target="current-password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="new-password">New Password</label>
                                    <div class="password-field-wrapper">
                                        <input type="password" id="new-password" name="new_password" class="form-control" required>
                                        <button type="button" class="password-toggle" data-target="new-password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-group">
                                        <label>Password Requirements:</label>
                                        <ul id="password-requirements" style="list-style:none; padding-left:0;">
                                            <li id="req-length"><i class="fa-regular fa-circle"></i> At least 8 characters</li>
                                            <li id="req-uppercase"><i class="fa-regular fa-circle"></i> At least one uppercase letter</li>
                                            <li id="req-lowercase"><i class="fa-regular fa-circle"></i> At least one lowercase letter</li>
                                            <li id="req-number"><i class="fa-regular fa-circle"></i> At least one number</li>
                                        </ul>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm-password">Confirm New Password</label>
                                        <div class="password-field-wrapper">
                                            <input type="password" id="confirm-password" name="confirm_password" class="form-control" required>
                                            <button type="button" class="password-toggle"
                                                data-target="confirm-password">
                                                <i class="far fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i>
                                            Update Password
                                        </button>
                                        <button type="reset" class="btn btn-outline">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                    </div>
                            </form>
                        </div>

                        <!-- Danger Zone -->
                        <div class="danger-zone">
                            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                            <p>Deleting your account will permanently remove all your data, including appointments,
                                patient records, and personal information. This action cannot be undone.</p>
                            <form id="delete-account-form" method="POST">
                                <input type="hidden" name="delete_account" value="1">
                                <div class="form-group">
                                    <label for="delete-account-password">Enter your password to confirm</label>
                                    <div class="password-field-wrapper">
                                        <input type="password" id="delete-account-password" name="delete_password" class="form-control" required>
                                        <button type="button" class="password-toggle" data-target="delete-account-password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> Delete Account
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div id="confirmDeleteModal" class="modal-overlay" style="display:none;">
        <div class="modal">
            <div class="modal-header" style="background-color: #fef2f2; border-bottom-color: #fecaca;">
                <h3 style="color: #b91c1c;"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete your account? This action cannot be undone and will remove all your data.</p>
            </div>
            <div class="modal-actions">
                <button id="cancelDeleteBtn" class="btn btn-outline">Cancel</button>
                <button id="confirmDeleteBtn" class="btn btn-danger">Delete Account</button>
            </div>
        </div>
    </div>

    <!-- Add modals before </body> -->
    <div id="passwordErrorModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Error</h3>
            </div>
            <div class="modal-body">
                <p id="modalErrorMessage"></p>
            </div>
            <div class="modal-actions">
                <button id="closeModalBtn" class="btn btn-primary">OK</button>
            </div>
        </div>
    </div>
    <div id="successModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header" style="background-color: #f0fdf4; border-bottom-color: #bbf7d0;">
                <h3 style="color: #16a34a;"><i class="fas fa-check-circle"></i> Success</h3>
            </div>
            <div class="modal-body">
                <p id="successMessage"></p>
            </div>
            <div class="modal-actions">
                <button id="closeSuccessModalBtn" class="btn btn-primary">OK</button>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar functionality
        document.getElementById('menu-toggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        // Close sidebar when clicking outside on small screens
        document.addEventListener('click', function (event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menu-toggle');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                !menuToggle.contains(event.target) &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });

        // Adjust sidebar on window resize
        window.addEventListener('resize', function () {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });

        // Password visibility toggle
        const passwordToggles = document.querySelectorAll('.password-toggle');
        if (passwordToggles.length) {
            passwordToggles.forEach(button => {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    if (passwordInput) {
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                        } else {
                            passwordInput.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                        }
                    }
                });
            });
        }

        // Password form submission
        const changePasswordForm = document.querySelector('form input[name="change_password"]')?.form;
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', function (e) {
                const currentPassword = document.getElementById('current-password')?.value;
                const newPassword = document.getElementById('new-password')?.value;
                const confirmPassword = document.getElementById('confirm-password')?.value;
                // Final validation before submission
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showErrorModal("New passwords don't match!");
                    return;
                }
                else if (newPassword === currentPassword) {
                    e.preventDefault();
                    showErrorModal('New password must be different from current password!');
                    return;
                }
                const valid = newPassword.length >= 8 && /[A-Z]/.test(newPassword) && /[a-z]/.test(newPassword) && /[0-9]/.test(newPassword);
                if (!valid) {
                    e.preventDefault();
                    showErrorModal('Password does not meet the requirements!');
                    return;
                }
            });
        }

        // Delete account form submission (robust modal logic)
        document.getElementById('delete-account-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const password = document.getElementById('delete-account-password').value;
            if (!password) {
                showErrorModal("Please enter your password to confirm deletion");
                return;
            }
            // Remove any existing modal
            const existingModal = document.getElementById('confirmDeleteModal');
            if (existingModal) existingModal.remove();

            // Show confirmation modal
            const confirmModal = document.createElement('div');
            confirmModal.className = 'modal-overlay active';
            confirmModal.id = 'confirmDeleteModal';
            confirmModal.innerHTML = `
                <div class="modal">
                    <div class="modal-header" style="background-color: #fef2f2; border-bottom-color: #fecaca;">
                        <h3 style="color: #b91c1c;"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to permanently delete your account? This action cannot be undone and will remove all your data.</p>
                        <p><strong>Please confirm your password:</strong></p>
                        <div class="form-group">
                            <div class="password-field-wrapper">
                                <input type="password" id="confirm-delete-password" class="form-control" required>
                                <button type="button" class="password-toggle" data-target="confirm-delete-password">
                                    <i class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button id="cancelDeleteBtn" class="btn btn-outline">Cancel</button>
                        <button id="confirmDeleteBtn" class="btn btn-danger">Delete Account</button>
                    </div>
                </div>
            `;
            document.body.appendChild(confirmModal);

            // Password toggle for modal
            confirmModal.querySelector('.password-toggle').addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });

            // Cancel button
            confirmModal.querySelector('#cancelDeleteBtn').addEventListener('click', function() {
                confirmModal.remove();
            });

            // Confirm button
            confirmModal.querySelector('#confirmDeleteBtn').addEventListener('click', function() {
                const confirmedPassword = document.getElementById('confirm-delete-password').value;
                if (confirmedPassword !== password) {
                    showErrorModal("Password confirmation does not match");
                    return;
                }
                // If passwords match, submit the form for real
                confirmModal.remove();
                document.getElementById('delete-account-form').submit();
            });
        });

        // Modal close button (if present)
        const closeModalBtn = document.getElementById('closeModalBtn');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                const modal = document.getElementById('passwordErrorModal');
                if (modal) modal.classList.remove('active');
            });
        }

        // Also close modal when clicking outside
        const modalOverlay = document.querySelector('.modal-overlay');
        if (modalOverlay) {
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        }

        // Success modal close button (if present)
        const closeSuccessModalBtn = document.getElementById('closeSuccessModalBtn');
        if (closeSuccessModalBtn) {
            closeSuccessModalBtn.addEventListener('click', function() {
                const modal = document.getElementById('successModal');
                if (modal) modal.classList.remove('active');
            });
        }

        // Also close success modal when clicking outside
        const allModalOverlays = document.querySelectorAll('.modal-overlay');
        if (allModalOverlays.length) {
            allModalOverlays.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('active');
                    }
                });
            });
        }

        // Modal functions for error/success
        function showErrorModal(message) {
            document.getElementById('modalErrorMessage').textContent = message;
            document.getElementById('passwordErrorModal').classList.add('active');
        }
        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.add('active');
        }
        if (document.getElementById('closeModalBtn')) {
            document.getElementById('closeModalBtn').addEventListener('click', function() {
                document.getElementById('passwordErrorModal').classList.remove('active');
            });
        }
        if (document.getElementById('closeSuccessModalBtn')) {
            document.getElementById('closeSuccessModalBtn').addEventListener('click', function() {
                document.getElementById('successModal').classList.remove('active');
            });
        }
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>