<?php
// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php-error.log');

// Start session and check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../main-page/login.php");
    exit();
}

require_once '../../config/database.php';

// Verify database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$doctorId = $_SESSION['user_id'];

// Initialize variables
$success_message = '';
$error_message = '';
$doctorProfile = [];
$subSpecialties = [];

try {

    // Fetch notifications for the doctor
    $query = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $unreadCount = $result->fetch_assoc()['unread_count'];

    // Fetch recent notifications (last 5)
    $query = "SELECT message, type, created_at FROM notifications 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $notifications = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    // Fetch doctor's profile data (single query)
    $query = "SELECT u.full_name, u.profile_image, u.email, u.phone_number, 
                 u.date_of_birth, u.gender, u.address, u.city, u.state, u.zip_code,
                 d.first_name, d.last_name, d.specialization, d.sub_specialties,
                 d.years_of_experience, d.medical_license_number, d.npi_number, 
                 d.education_and_training
          FROM users u
          LEFT JOIN doctor_details d ON u.user_id = d.user_id
          WHERE u.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
    $doctorProfile = $result->fetch_assoc();

        // Check if first_name and last_name are missing in doctor_details
        if (empty($doctorProfile['first_name']) || empty($doctorProfile['last_name'])) {
            // Split full_name into first_name and last_name
            $fullName = $doctorProfile['full_name'];
            $nameParts = explode(' ', $fullName, 2); // Split into two parts
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

            // Insert or update doctor_details with user_id, first_name, and last_name
            $query = "INSERT INTO doctor_details (user_id, first_name, last_name, specialization) 
                    VALUES (?, ?, ?, 'Specialization Not Set')
                    ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $doctorId, $firstName, $lastName);
            $stmt->execute();

            // Update the profile array
            $doctorProfile['first_name'] = $firstName;
            $doctorProfile['last_name'] = $lastName;
        }
    }

} catch (Exception $e) {
    $error_message = 'Error fetching profile data: ' . $e->getMessage();
    $notifications = [];
    $unreadCount = 0;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Handle photo removal
        if (isset($_POST['remove_photo'])) {
            $defaultImage = 'default.png';
            $uploadDir = '../../images/doctors/';
            
            if ($doctorProfile['profile_image'] && $doctorProfile['profile_image'] !== $defaultImage) {
                if (file_exists($uploadDir . $doctorProfile['profile_image'])) {
                    unlink($uploadDir . $doctorProfile['profile_image']);
                }
            }
            
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
            $stmt->bind_param("si", $defaultImage, $doctorId);
            $stmt->execute();
            
            $success_message = 'Profile photo removed successfully';
        }
        // Handle photo upload
        elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../images/doctors/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['profile_image']['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                // Delete old image if not default
                if ($doctorProfile['profile_image'] && $doctorProfile['profile_image'] !== 'default.png') {
                    if (file_exists($uploadDir . $doctorProfile['profile_image'])) {
                        unlink($uploadDir . $doctorProfile['profile_image']);
                    }
                }
                
                // Generate unique filename
                $fileName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                    $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $fileName, $doctorId);
                    $stmt->execute();
                    
                    $success_message = 'Profile photo updated successfully';
                }
            } else {
                throw new Exception('Only JPG, PNG, and GIF images are allowed');
            }
        }
        // Handle profile update
        elseif (isset($_POST['first_name'])) {
            // Process sub-specialties
            $subSpecialties = [];
            if (!empty($_POST['sub_specialties'])) {
                $subSpecialties = array_map('trim', explode(',', $_POST['sub_specialties']));
            }
            $subSpecialtiesStr = implode(',', $subSpecialties);

            // Prepare all values before binding
            $phone = $_POST['phone'] ?? null;
            $date_of_birth = $_POST['date_of_birth'] ?? null;
            $gender = $_POST['gender'] ?? null;
            $address = $_POST['address'] ?? null;
            $city = $_POST['city'] ?? null;
            $state = $_POST['state'] ?? null;
            $zip_code = $_POST['zip_code'] ?? null;

            // Update users table
            $full_name = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
            $userQuery = "UPDATE users SET 
                full_name = ?,
                email = ?, 
                phone_number = ?, 
                date_of_birth = ?, 
                gender = ?, 
                address = ?, 
                city = ?, 
                state = ?, 
                zip_code = ? 
                WHERE user_id = ?";

            $stmt = $conn->prepare($userQuery);
            $stmt->bind_param("sssssssssi", 
                $full_name,
                $_POST['email'],
                $phone,
                $date_of_birth,
                $gender,
                $address,
                $city,
                $state,
                $zip_code,
                $doctorId
            );
            $stmt->execute();
            
            $specialization = $_POST['specialization'] ?? 'cardiology';
            $subSpecialtiesStr = implode(',', $subSpecialties);
            $years_of_experience = $_POST['years_of_experience'] ?? null;
            $medical_license_number = $_POST['medical_license_number'] ?? null;
            $npi_number = $_POST['npi_number'] ?? null;
            $education_and_training = $_POST['education_and_training'] ?? null;

            $doctorQuery = "UPDATE doctor_details SET 
                first_name = ?, 
                last_name = ?, 
                specialization = ?, 
                sub_specialties = ?, 
                years_of_experience = ?, 
                medical_license_number = ?, 
                npi_number = ?, 
                education_and_training = ? 
                WHERE user_id = ?";

            $stmt = $conn->prepare($doctorQuery);
            $stmt->bind_param("ssssisssi",
                $_POST['first_name'],
                $_POST['last_name'],
                $specialization,
                $subSpecialtiesStr,
                $years_of_experience,
                $medical_license_number,
                $npi_number,
                $education_and_training,
                $doctorId
            );
            $stmt->execute();
            
            $success_message = 'Profile updated successfully';
        }

        $conn->commit();
        
        // Refresh the page to show updated data
        header("Location: profile.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Define image path
$baseImagePath = '../../images/doctors/';
$imageFile = $doctorProfile['profile_image'] ?? 'default.png';
$fullPath = $baseImagePath . $imageFile;
$imageSrc = (file_exists($fullPath)) ? $fullPath : $baseImagePath . 'default.png';
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Grand+Hotel&family=Jost:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Winky+Sans:ital,wght@0,300..900;1,300..900&display=swap');


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
            padding: 15px 30px;
            background-color: white;
            border-bottom: 1px solid #e0e7f1;
            gap: 20px; 
        }

        .top-bar-right-group {
            display: flex;
            align-items: center;
            gap: 15px; 
            margin-left: auto; 
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
            gap: 10px;
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

        /* Profile Settings Styles */
        .profile-settings-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .settings-nav {
            display: flex;
            border-bottom: 1px solid #e0e7f1;
            background-color: #f8fafc;
        }

        .settings-nav-item {
            padding: 15px 20px;
            font-size: 15px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 2px solid transparent;
        }

        .settings-nav-item.active {
            color: #3498db;
            border-bottom: 2px solid #3498db;
            background-color: white;
        }

        .settings-nav-item:hover:not(.active) {
            color: #475569;
            background-color: #f0f5ff;
        }

        .settings-content {
            padding: 25px;
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            color: #1f2937;
            background-color: #f9fafb;
            border: 1px solid #e0e7f1;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            background-color: white;
            border-color: #bfdbfe;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
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
            color: #64748b;
            border: 1px solid #e0e7f1;
        }

        .btn-outline:hover {
            background-color: #f8fafc;
            color: #475569;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e7f1;
        }

        .avatar-upload {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e0e7f1;
            margin-right: 20px;
        }

        .avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .avatar-input {
            display: none;
        }



        .specialty-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .specialty-tag {
            background-color: #f0f5ff;
            color: #3498db;
            border: 1px solid #bfdbfe;
            border-radius: 30px;
            padding: 5px 12px;
            font-size: 13px;
            display: flex;
            align-items: center;
        }

        .specialty-tag i {
            margin-left: 6px;
            font-size: 11px;
            cursor: pointer;
        }

        /* Alert Styles */
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            animation: fadeIn 0.3s;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 10px;
        }

        .alert .close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            line-height: 1;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Loading Spinner */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <a href="">
                    <img src="images/logo.png" alt="">
                </a>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fa-regular fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="availability.php" class="nav-item">
                    <i class="far fa-clock"></i>
                    <span>Availability</span>
                </a>
                <a href="patients.php" class="nav-item">
                   <i class="fa-solid fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Profile</span>
                </a>
                <a href="setting.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php
                    // Define base path - adjust this to your actual server path
                    $baseImagePath = '../../images/doctors/';
                    
                    // Get image filename from database
                    $imageFile = $doctorProfile['profile_image'] ?? 'default.png';
                    
                    // Full path to check
                    $fullPath = $baseImagePath . $imageFile;
                    
                    // Verify file exists, otherwise use default
                    if (!empty($doctorProfile['profile_image']) && file_exists($fullPath)) {
                        $imageSrc = $fullPath;
                    } else {
                        $imageSrc = $baseImagePath . 'default.png';
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imageSrc); ?>" 
                        alt="<?php echo htmlspecialchars($doctorProfile['full_name'] ?? 'Doctor'); ?>">
                </div>
                <div class="user-info">
                    <div class="user-name">
                        <?php echo "Dr. " . htmlspecialchars($doctorProfile['first_name'] . " " . $doctorProfile['last_name']); ?>
                    </div>
                    <div class="user-role"><?php echo htmlspecialchars($doctorProfile['specialization'] ?? 'Specialization Not Set'); ?></div>
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

                <div class="top-bar-right-group">
                    <div class="notification-container">
                        <div class="icon-btn" id="notificationBtn">
                            <i class="far fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4>Notifications</h4>
                                <?php if ($unreadCount > 0): ?>
                                    <a href="#" id="markAllRead">Mark all as read</a>
                                <?php endif; ?>
                            </div>
                            <div class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-item empty">No notifications</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                            data-id="<?php echo $notification['id']; ?>">
                                            <div class="notification-icon">
                                                <i class="fas fa-<?php echo $notification['type'] === 'appointment' ? 'calendar-check' : 'exclamation-circle'; ?>"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="notification-footer">
                                <a href="notifications.php">View all notifications</a>
                            </div>
                        </div>
                    </div>

                    <div class="user-dropdown">
                            <?php
                            // Define base path - adjust this to your actual server path
                            $baseImagePath = '../../images/doctors/';
                            
                            // Get image filename from database
                            $imageFile = $doctorProfile['profile_image'] ?? 'default.png';
                            
                            // Full path to check
                            $fullPath = $baseImagePath . $imageFile;
                            
                            // Verify file exists, otherwise use default
                            if (!empty($doctorProfile['profile_image']) && file_exists($fullPath)) {
                                $imageSrc = $fullPath;
                            } else {
                                $imageSrc = $baseImagePath . 'default.png';
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imageSrc); ?>" 
                                alt="<?php echo htmlspecialchars($doctorProfile['full_name'] ?? 'Doctor'); ?>">

                            <span><?php echo "Dr. " . htmlspecialchars($doctorProfile['full_name'] ?? 'Doctor'); ?></span>
                    </div>
                </div>
            </div>
            <div class="content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-text">
                    <h1>Edit Profile</h1>
                </div>
            </div>

            <!-- Display messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Settings Container -->
            <div class="profile-settings-container">
                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <div class="settings-nav-item active" data-target="personal-info">Personal Information</div>
                    <div class="settings-nav-item" data-target="professional-info">Professional Information</div>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <form method="POST" action="profile.php" enctype="multipart/form-data" id="profileForm">
                        <!-- Personal Information Section -->
                        <div id="personal-info" class="settings-section active">
                            <div class="avatar-upload">
                                <img src="<?php echo htmlspecialchars($imageSrc); ?>" class="current-avatar">
                                <div class="avatar-actions">
                                    <label for="avatar-input" class="btn btn-outline">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </label>
                                    <input type="file" id="avatar-input" name="profile_image" class="avatar-input" accept="image/*">
                                    <button type="submit" name="remove_photo" class="btn btn-outline" style="color: #ef4444;">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="first-name">First Name</label>
                                        <input readonly type="text" id="first-name" name="first_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($doctorProfile['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="last-name">Last Name</label>
                                        <input readonly type="text" id="last-name" name="last_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($doctorProfile['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($doctorProfile['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($doctorProfile['phone_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="dob">Date of Birth</label>
                                        <input type="date" id="dob" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($doctorProfile['date_of_birth'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="gender">Gender</label>
                                        <select id="gender" name="gender" class="form-control">
                                            <option value="male" <?php echo (isset($doctorProfile['gender'])) && $doctorProfile['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo (isset($doctorProfile['gender'])) && $doctorProfile['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo (isset($doctorProfile['gender'])) && $doctorProfile['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                            <option value="prefer-not" <?php echo (isset($doctorProfile['gender'])) && $doctorProfile['gender'] === 'prefer-not' ? 'selected' : ''; ?>>Prefer not to say</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" class="form-control" 
                                    value="<?php echo htmlspecialchars($doctorProfile['address'] ?? ''); ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="city">City</label>
                                        <input type="text" id="city" name="city" class="form-control" 
                                            value="<?php echo htmlspecialchars($doctorProfile['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="state">State</label>
                                        <input type="text" id="state" name="state" class="form-control" 
                                            value="<?php echo htmlspecialchars($doctorProfile['state'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="zip">ZIP Code</label>
                                        <input type="text" id="zip" name="zip_code" class="form-control" 
                                            value="<?php echo htmlspecialchars($doctorProfile['zip_code'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </div>

                        <!-- Professional Information Section -->
                        <div id="professional-info" class="settings-section">
                            <div class="form-group">
                                <label for="specialization">Primary Specialization</label>
                                <select id="specialization" name="specialization" class="form-control">
                                    <option value="Cardiology" <?php echo (isset($doctorProfile['specialization']) && $doctorProfile['specialization'] === 'cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                    <option value="Neurology" <?php echo (isset($doctorProfile['specialization']) && $doctorProfile['specialization'] === 'neurology') ? 'selected' : ''; ?>>Neurology</option>
                                    <option value="Pediatrics" <?php echo (isset($doctorProfile['specialization']) && $doctorProfile['specialization'] === 'pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                    <option value="Dermatology" <?php echo (isset($doctorProfile['specialization']) && $doctorProfile['specialization'] === 'dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subspecialties">Sub-specialties</label>
                                <input type="text" id="subspecialties" name="sub_specialties" class="form-control" 
                                    placeholder="Type and press Enter to add" 
                                    value="<?php echo htmlspecialchars(implode(', ', $subSpecialties)); ?>">

                                <div class="specialty-tags" id="specialtyTagsContainer">
                                    <?php foreach ($subSpecialties as $specialty): ?>
                                        <div class="specialty-tag">
                                            <?php echo htmlspecialchars($specialty); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="experience">Years of Experience</label>
                                <input type="number" id="experience" name="years_of_experience" class="form-control" 
                                    value="<?php echo htmlspecialchars($doctorProfile['years_of_experience'] ?? ''); ?>" min="0" max="70">
                            </div>

                            <div class="form-group">
                                <label for="medical-license">Medical License Number</label>
                                <input type="text" id="medical-license" name="medical_license_number" class="form-control" 
                                    value="<?php echo htmlspecialchars($doctorProfile['medical_license_number'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="npi">NPI Number</label>
                                <input type="text" id="npi" name="npi_number" class="form-control" 
                                    value="<?php echo htmlspecialchars($doctorProfile['npi_number'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="education">Education & Training</label>
                                <textarea id="education" name="education_and_training" class="form-control"><?php echo htmlspecialchars($doctorProfile['education_and_training'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        // Tab switching
        document.querySelectorAll('.settings-nav-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.settings-nav-item, .settings-section').forEach(el => {
                    el.classList.remove('active');
                });
                this.classList.add('active');
                document.getElementById(this.dataset.target).classList.add('active');
            });
        });

        // Image preview
        document.getElementById('avatar-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.current-avatar').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Sub-specialties handling
        const subspecialtiesInput = document.getElementById('subspecialties');
        const specialtyTagsContainer = document.getElementById('specialtyTagsContainer');
        const profileForm = document.getElementById('profileForm');

        function updateSubSpecialties() {
            const tags = Array.from(document.querySelectorAll('.specialty-tag')).map(tag => tag.textContent.trim());
            subspecialtiesInput.value = tags.join(', ');
        }

        // Add new specialty when Enter is pressed
        subspecialtiesInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.value.trim();
                if (value) {
                    const tag = document.createElement('div');
                    tag.className = 'specialty-tag';
                    tag.textContent = value;
                    specialtyTagsContainer.appendChild(tag);
                    this.value = '';
                    updateSubSpecialties();
                    
                    // Auto-submit the form after adding a tag
                    setTimeout(() => {
                        profileForm.submit();
                    }, 300);
                }
            }
        });

        // Initialize existing tags
        document.querySelectorAll('.specialty-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                this.remove();
                updateSubSpecialties();
                
                // Auto-submit the form after removing a tag
                setTimeout(() => {
                    profileForm.submit();
                }, 300);
            });
        });
    });
    </script>
    <script src="assets/js/notifications.js"></script>
</body>

</html>