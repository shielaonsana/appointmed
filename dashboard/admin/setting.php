<?php
session_start();

require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../../main-page/login.php");
    exit();
}


// Fetch patient's complete profile data
$user_id = $_SESSION['user_id'];
$query = "SELECT u.user_id, u.full_name, u.profile_image, u.account_type, 
                 u.email, u.phone_number, u.gender, u.date_of_birth,
                 a.first_name, a.last_name, a.date_of_birth AS admin_dob,
                 a.admin_id, a.address AS admin_address, a.city AS admin_city, a.state AS admin_state, a.zip_code AS admin_zip
          FROM users u
          LEFT JOIN admins a ON u.user_id = a.user_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();



if ($result->num_rows > 0) {
    $adminProfile = $result->fetch_assoc();

    // Auto-create patient row if missing
    if (empty($adminProfile['admin_id'])) {
        // Get user info
        $firstName = $adminProfile['first_name'] ?? '';
        $lastName = $adminProfile['last_name'] ?? '';
        $dob = $adminProfile['date_of_birth'] ?? '';
        $gender = $adminProfile['gender'] ?? '';
        $email = $adminProfile['email'] ?? '';
        $phone = $adminProfile['phone_number'] ?? '';
        $address = $adminProfile['admin_address'] ?? '';
        $city = $adminProfile['admin_city'] ?? '';
        $state = $adminProfile['admin_state'] ?? '';
        $zip = $adminProfile['admin_zip'] ?? '';
        // Check if patient row exists
        $check = $conn->prepare("SELECT admin_id FROM admins WHERE user_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $checkResult = $check->get_result();
        if ($checkResult->num_rows === 0) {
            // Insert new patient row
            $insert = $conn->prepare("INSERT INTO admins (user_id, first_name, last_name, date_of_birth, gender, email, phone_number, address, city, state, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("issssssssss", $user_id, $firstName, $lastName, $dob, $gender, $email, $phone, $address, $city, $state, $zip);
            $insert->execute();
        }
        // Re-fetch patient profile
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $adminProfile = $result->fetch_assoc();
    }

    // Handle name splitting if not in patient_details
    if (empty($adminProfile['first_name']) || empty($adminProfile['last_name'])) {
        $nameParts = explode(' ', $adminProfile['full_name'], 2);
        $adminProfile['first_name'] = $nameParts[0];
        $adminProfile['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
    }

    // Handle profile image path
    $baseImagePath = '../../images/admins/'; // Verify this matches your directory structure
    $imageFile = !empty($adminProfile['profile_image']) ? $adminProfile['profile_image'] : 'default.png';
    $imagePath = $baseImagePath . $imageFile;
    
    // Fallback to default if image doesn't exist
    if (!file_exists($imagePath)) {
        $imagePath = $baseImagePath . 'default.png';
    }

    // Format other fields
    $dateOfBirth = !empty($adminProfile['admin_dob']) ? $adminProfile['admin_dob'] : $adminProfile['date_of_birth'];
    $displayDOB = ($dateOfBirth == '0000-00-00' || empty($dateOfBirth)) 
        ? 'Not specified' 
        : date('F j, Y', strtotime($dateOfBirth));
    
    $gender = !empty($adminProfile['gender']) ? htmlspecialchars($adminProfile['gender']) : 'Not specified';
    $phone = !empty($adminProfile['phone_number']) ? htmlspecialchars($adminProfile['phone_number']) : 'Not specified';
    $email = !empty($adminProfile['email']) ? htmlspecialchars($adminProfile['email']) : 'Not specified';
    $fullName = htmlspecialchars($adminProfile['first_name'] . ' ' . $adminProfile['last_name']);
    $firstName = htmlspecialchars($adminProfile['first_name']);
    
} else {
    // Fallback values if no user found (shouldn't happen for logged-in users)
    $imagePath = 'images/admins/default.png';
    $fullName = 'Admin';
    $firstName = 'Admin';
    $accountType = 'Admin';
    $displayDOB = 'Not specified';
    $gender = 'Not specified';
    $phone = 'Not specified';
    $email = 'Not specified';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match!";
    } else {
        // Fetch current hashed password from DB
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $hashedPassword = $row['password_hash'];

            if (password_verify($currentPassword, $hashedPassword)) {
                // Hash and update new password
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $update->bind_param("si", $newHashedPassword, $user_id);
                if ($update->execute()) {
                    $success = "Password updated successfully!";
                } else {
                    $error = "Failed to update password. Try again.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $deletePassword = $_POST['delete_password'] ?? '';

    // Fetch current hashed password from DB for verification
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $hashedPassword = $row['password_hash'];

        if (password_verify($deletePassword, $hashedPassword)) {
            // Delete from admins table
            $delAdmin = $conn->prepare("DELETE FROM admins WHERE user_id = ?");
            $delAdmin->bind_param("i", $user_id);
            $delAdmin->execute();

            // Delete from users table
            $delUser = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delUser->bind_param("i", $user_id);
            $delUser->execute();

            // Add any other related deletions here (appointments, records, etc.)

            // Destroy session and redirect to login page
            session_destroy();

            header("Location: ../../main-page/login.php?account_deleted=1");
            exit();
        } else {
            $error = "Password is incorrect. Account not deleted.";
        }
    } else {
        $error = "User not found.";
    }
}


// Ensure patient_id is set
if (empty($adminProfile['admin_id'])) {
    // Debugging output
    error_log('User ID: ' . $user_id);
    $check = $conn->prepare("SELECT * FROM admins WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkResult->num_rows === 0) {
        die('Patient row was not created. Please check your database and user_id.');
    } else {
        $row = $checkResult->fetch_assoc();
        die('Patient row exists but join failed. Patient row: ' . print_r($row, true));
    }
}
$admin_id = $adminProfile['admin_id'];

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

        .requirement.valid {
            color: #2ecc71;
        }

        .requirement.invalid {
            color: #e74c3c;
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
    </style>
</head>


<body>
    <div class="container">
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <a href="index.php">
                    <img src="images/logo.png" alt="">
                </a>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="doctors.php" class="nav-item">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Doctors</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fa-regular fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fa-solid fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="setting.php" class="nav-item  active">
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
                    <div class="user-role">Admin</div>
                </div>
            </div>
            <a href="../../main-page/logout.php#" class="logout-btn">
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
                    <!-- Settings Content -->
                    <div class="settings-content">
                        <div class="settings-section">
                            <h2>Change Password</h2>
                            <form id="change-password-form" method="POST" action="">
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
                                        <input type="password" id="new-password" name="new_password"class="form-control" required>
                                        <button type="button" class="password-toggle" data-target="new-password">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-rules">
                                        <div class="password-requirements">
                                            <div class="requirement" id="length-req">
                                                <i class="fa-regular fa-circle"></i> At least 8 characters
                                            </div>
                                            <div class="requirement" id="uppercase-req">
                                                <i class="fa-regular fa-circle"></i> At least one uppercase letter
                                            </div>
                                            <div class="requirement" id="lowercase-req">
                                                <i class="fa-regular fa-circle"></i> At least one lowercase letter
                                            </div>
                                            <div class="requirement" id="number-req">
                                                <i class="fa-regular fa-circle"></i> At least one number
                                            </div>
                                        </div>

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
                            <form id="delete-account-form" method="POST" style="display:none;">
                                <input type="hidden" name="delete_account" value="1" />
                                <input type="hidden" name="delete_password" id="delete-password-input" />
                            </form>
                            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                            <p>Deleting your account will permanently remove all your data, including appointments,
                                patient records, and personal information. This action cannot be undone.</p>
                            <button id="delete-account-btn" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i>
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div id="delete-account-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete Account</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action cannot be undone and all your data will be
                    permanently removed.</p>
                <div class="form-group">
                    <label for="delete-password">Enter your password to confirm</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="delete-password" class="form-control" required>
                        <button type="button" class="password-toggle" data-target="delete-password">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button id="cancel-delete" class="btn btn-outline">Cancel</button>
                <button id="confirm-delete" class="btn btn-danger">Delete Account</button>
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
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function () {
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
        });

        // Password form submission
        document.getElementById('change-password-form').addEventListener('submit', function (e) {
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const passwordRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return;
            }

            if (!passwordRegex.test(newPassword)) {
                e.preventDefault();
                alert('Password does not meet the requirements!');
                return;
            }

            // ✅ Let form submit if all checks pass
        });


        // Delete account modal
        const deleteAccountBtn = document.getElementById('delete-account-btn');
        const deleteAccountModal = document.getElementById('delete-account-modal');
        const cancelDeleteBtn = document.getElementById('cancel-delete');
        const confirmDeleteBtn = document.getElementById('confirm-delete');

        deleteAccountBtn.addEventListener('click', function () {
            deleteAccountModal.classList.add('active');
        });

        cancelDeleteBtn.addEventListener('click', function () {
            deleteAccountModal.classList.remove('active');
            document.getElementById('delete-password').value = '';
        });

        confirmDeleteBtn.addEventListener('click', function () {
            const password = document.getElementById('delete-password').value;

            if (!password) {
                alert('Please enter your password to confirm!');
                return;
            }

            // Set password in hidden input and submit form
            document.getElementById('delete-password-input').value = password;
            document.getElementById('delete-account-form').submit();
        });


        // Password validation
        const newPasswordInput = document.getElementById('new-password');
        const lengthReq = document.getElementById('length-req');
        const uppercaseReq = document.getElementById('uppercase-req');
        const lowercaseReq = document.getElementById('lowercase-req');
        const numberReq = document.getElementById('number-req');

        newPasswordInput.addEventListener('input', validatePassword);

        function validatePassword() {
            const password = newPasswordInput.value;

            // Check length
            if (password.length >= 8) {
                lengthReq.classList.add('valid');
                lengthReq.classList.remove('invalid');
                lengthReq.querySelector('i').classList.remove('fa-circle');
                lengthReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                lengthReq.classList.remove('valid');
                lengthReq.classList.add('invalid');
                lengthReq.querySelector('i').classList.remove('fa-check-circle');
                lengthReq.querySelector('i').classList.add('fa-circle');
            }

            // Check uppercase
            if (/[A-Z]/.test(password)) {
                uppercaseReq.classList.add('valid');
                uppercaseReq.classList.remove('invalid');
                uppercaseReq.querySelector('i').classList.remove('fa-circle');
                uppercaseReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                uppercaseReq.classList.remove('valid');
                uppercaseReq.classList.add('invalid');
                uppercaseReq.querySelector('i').classList.remove('fa-check-circle');
                uppercaseReq.querySelector('i').classList.add('fa-circle');
            }

            // Check lowercase
            if (/[a-z]/.test(password)) {
                lowercaseReq.classList.add('valid');
                lowercaseReq.classList.remove('invalid');
                lowercaseReq.querySelector('i').classList.remove('fa-circle');
                lowercaseReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                lowercaseReq.classList.remove('valid');
                lowercaseReq.classList.add('invalid');
                lowercaseReq.querySelector('i').classList.remove('fa-check-circle');
                lowercaseReq.querySelector('i').classList.add('fa-circle');
            }

            // Check number
            if (/[0-9]/.test(password)) {
                numberReq.classList.add('valid');
                numberReq.classList.remove('invalid');
                numberReq.querySelector('i').classList.remove('fa-circle');
                numberReq.querySelector('i').classList.add('fa-check-circle');
            } else {
                numberReq.classList.remove('valid');
                numberReq.classList.add('invalid');
                numberReq.querySelector('i').classList.remove('fa-check-circle');
                numberReq.querySelector('i').classList.add('fa-circle');
            }
        }

    </script>
</body>

</html>

<?php if (isset($success)): ?>
<script>
    alert("<?= $success ?>");
</script>
<?php endif; ?>

<?php if (isset($error)): ?>
<script>
    alert("<?= $error ?>");
</script>
<?php endif; ?>
