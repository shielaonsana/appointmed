<?php
session_start();

require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../../main-page/login.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);



if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ✅ Edit doctor logic goes here
    if (isset($_POST['edit_doctor']) && isset($_POST['doctor_id'])) {
        $doctorId = intval($_POST['doctor_id']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = $_POST['phone_number'];
        $dob = $_POST['date_of_birth'];
        $gender = $_POST['gender'];

        $specialization = $_POST['specialization'];
        $subSpecialties = $_POST['sub_specialties'];
        $experience = $_POST['years_of_experience'];
        $license = $_POST['medical_license_number'];
        $npi = $_POST['npi_number'];
        $education = $_POST['education_and_training'];
        $availability = $_POST['availability'] ?? null;

        if (!empty($availability)) {
            json_decode($availability);
            if (json_last_error() !== JSON_ERROR_NONE) {
                die("Invalid JSON format in availability.");
            }
        }

        // Get user_id for this doctor
        $getUser = $conn->prepare("SELECT user_id FROM doctor_details WHERE doctor_id = ?");
        $getUser->bind_param("i", $doctorId);
        $getUser->execute();
        $userRes = $getUser->get_result();
        if ($userRes->num_rows === 0) {
            die("Doctor not found.");
        }
        $userId = $userRes->fetch_assoc()['user_id'];
        $fullName = $firstName . ' ' . $lastName;

        // Update users
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ?, date_of_birth = ?, gender = ? WHERE user_id = ?");
        $stmt->bind_param("sssssi", $fullName, $email, $phone, $dob, $gender, $userId);
        $stmt->execute();

        // Update doctor_details
        $stmt2 = $conn->prepare("UPDATE doctor_details SET first_name = ?, last_name = ?, specialization = ?, sub_specialties = ?, years_of_experience = ?, medical_license_number = ?, npi_number = ?, education_and_training = ?, availability = ? WHERE doctor_id = ?");
        $stmt2->bind_param("sssssssssi", $firstName, $lastName, $specialization, $subSpecialties, $experience, $license, $npi, $education, $availability, $doctorId);
        $stmt2->execute();

        header("Location: doctors.php?status=updated");
        exit();
    }

    // ✅ Add doctor logic goes here
    elseif (isset($_POST['add_doctor'])) {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $fullName = $firstName . ' ' . $lastName;
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $phone = $_POST['phone_number'] ?? null;
        $dob = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;

        $specialization = $_POST['specialization'];
        $subSpecialties = $_POST['sub_specialties'] ?? null;
        $experience = $_POST['years_of_experience'] ?? null;
        $license = $_POST['medical_license_number'] ?? null;
        $npi = $_POST['npi_number'] ?? null;
        $education = $_POST['education_and_training'] ?? null;
        $availability = $_POST['availability'] ?? null;

        if (!empty($availability)) {
            json_decode($availability);
            if (json_last_error() !== JSON_ERROR_NONE) {
                die("Invalid JSON format in availability.");
            }
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone_number, date_of_birth, gender, password_hash, account_type) VALUES (?, ?, ?, ?, ?, ?, 'Doctor')");
        $stmt->bind_param("ssssss", $fullName, $email, $phone, $dob, $gender, $passwordHash);

        if ($stmt->execute()) {
            $userId = $stmt->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO doctor_details (
                user_id, first_name, last_name, specialization, sub_specialties, years_of_experience, 
                medical_license_number, npi_number, education_and_training, availability
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isssssssss", $userId, $firstName, $lastName, $specialization,
                $subSpecialties, $experience, $license, $npi, $education, $availability);

            if ($stmt2->execute()) {
                header("Location: doctors.php?status=success");
                exit();
            } else {
                echo "Failed to insert into doctor_details: " . $stmt2->error;
            }
        } else {
            echo "Failed to insert into users: " . $stmt->error;
        }
    }
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

// fetch doctors

$doctors = [];
$doctorQuery = "
   SELECT d.doctor_id, d.first_name, d.last_name, d.specialization, 
       u.email, u.profile_image, u.is_active,
       COUNT(a.patient_id) AS patient_count
FROM doctor_details d
JOIN users u ON d.user_id = u.user_id
LEFT JOIN appointments a ON a.doctor_id = d.doctor_id
GROUP BY d.doctor_id
";
$doctorStmt = $conn->prepare($doctorQuery);
$doctorStmt->execute();
$doctorResult = $doctorStmt->get_result();

while ($row = $doctorResult->fetch_assoc()) {
    $doctors[] = $row;
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors</title>
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

        /* Doctor Management Table Styles */
        .doctor-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }

        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-doctor-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .add-doctor-btn:hover {
            background-color: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px 20px;
            color: #64748b;
            font-weight: 500;
            border-bottom: 1px solid #e0e7f1;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid #e0e7f1;
            color: #1f2937;
        }

        .checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .doctor-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .doctor-info {
            display: flex;
            align-items: center;
        }

        .doctor-details {
            display: flex;
            flex-direction: column;
        }

        .doctor-name {
            font-weight: 500;
            color: #1f2937;
        }

        .doctor-email {
            font-size: 13px;
            color: #64748b;
        }

        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }

        .act {
            background-color: #e6f7e9;
            color: #3dbb65;
        }

        .inactive {
            background-color: #f1f1f1;
            color: #64748b;
        }

        .action-icons {
            display: flex;
            gap: 10px;
            color: #64748b;
        }

        .action-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .action-icon:hover {
            background-color: #f0f5ff;
            color: #3498db;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
        }

        .pagination-info {
            font-weight: 400;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid #e0e7f1;
            background-color: #fff;
            transition: all 0.3s;
        }

        .page-btn.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }

        .page-btn:hover:not(.active) {
            background-color: #f0f5ff;
        }

        .prev-btn,
        .next-btn {
            color: #64748b;
        }
        /*
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
        }*/

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            overflow-y: auto; /* or scroll if you want */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none;  /* IE 10+ */
        }

        .modal-content::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .modal-content h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1f2937;
        }

        .modal-content form input,
        .modal-content form select,
        .modal-content form textarea {
            width: 100%;
            padding: 10px 14px;
            margin-bottom: 15px;
            border: 1px solid #e0e7f1;
            border-radius: 8px;
            background-color: #f8fafc;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .modal-content form input:focus,
        .modal-content form select:focus,
        .modal-content form textarea:focus {
            border-color: #3498db;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        .modal-content form textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-content input[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .modal-content input[type="submit"]:hover {
            background-color: #2980b9;
        }

        .modal-content button[type="button"] {
            margin-top: 10px;
            background: none;
            border: none;
            color: #64748b;
            font-size: 14px;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.3s;
        }

        .modal-content button[type="button"]:hover {
            background-color: #f0f5ff;
            color: #3498db;
        }


        /* Responsive Design */
        @media (max-width: 1200px) {
            /* Stats already in 2 columns by default */
        }

        @media (max-width: 992px) {
            .search-container {
                max-width: 300px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 240px;
            }

            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .search-container {
                max-width: none;
                flex-grow: 1;
            }

            .user-name-display {
                display: none;
            }

            .welcome-text h1 {
                font-size: 20px;
            }
        }

        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 20px 15px;
            }

            .top-bar {
                padding: 12px 15px;
            }

            .search-input {
                padding: 6px 8px 6px 30px;
            }

            .search-container i {
                left: 8px;
            }

            .welcome-text h1 {
                font-size: 18px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-number {
                font-size: 28px;
            }

            .icon-btn {
                width: 36px;
                height: 36px;
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
                <a href="doctors.php" class="nav-item active">
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
                <a href="setting.php" class="nav-item">
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
            <a href="#" class="logout-btn">
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
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="doctor-search" placeholder="Search doctors">


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
                        <h1>Doctors</h1>
                    </div>
                    <button class="add-doctor-btn">
                        <i class="fas fa-plus"></i>
                        Add Doctor
                    </button>
                </div>

                <!-- Doctor Management Interface -->
                <div class="doctor-container">

                    <!-- Doctor Table -->
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Specialty</th>
                                <th>Patients</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doc): 
                                $docFullName = 'Dr. ' . htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']);
                                $docEmail = htmlspecialchars($doc['email']);
                                $docSpecialty = htmlspecialchars($doc['specialization']);
                                $docImage = !empty($doc['profile_image']) ? '../../images/doctors/' . $doc['profile_image'] : 'images/doctor-1.png';
                                $status = (isset($doc['is_active']) && $doc['is_active'] == 1) ? 'Active' : 'Inactive';

                            ?>
                            <tr class="doctor-row" data-name="<?php echo strtolower($doc['first_name'] . ' ' . $doc['last_name']); ?>" data-doctor-id="<?php echo $doc['doctor_id']; ?>" data-status="<?php echo $status; ?>">
                                <td>
                                    <div class="doctor-info">
                                        <div class="doctor-avatar">
                                            <img src="<?php echo $docImage; ?>" alt="<?php echo $docFullName; ?>">
                                        </div>
                                        <div class="doctor-details">
                                            <span class="doctor-name"><?php echo $docFullName; ?></span>
                                            <span class="doctor-email"><?php echo $docEmail; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $docSpecialty; ?></td>
                                <td><?php echo $doc['patient_count']; ?></td>
                                <td>
                                    <span class="status <?php echo ($status === 'Active') ? 'act' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-icons">
                                        <div class="action-icon btn-toggle-status" title="Toggle Status"><i class="fas fa-eye"></i></div> 
                                        <div class="action-icon btn-edit-doctor" title="Edit Doctor"><i class="fas fa-pen"></i></div>
                                        <div class="action-icon btn-delete-doctor" title="Delete Doctor"><i class="fas fa-trash"></i></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing all the doctors
                        </div>
                        <div class="pagination-controls">
                            <button class="page-btn prev-btn">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="page-btn active">1</button>
                            <button class="page-btn next-btn">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="add-doctor-modal" style="display:none;" class="modal-overlay">
                <div class="modal-content">
                    <h2 id="doctor-modal-title">Add New Doctor</h2>
                    <form id="add-doctor-form" method="post">
                        <input type="text" name="first_name" placeholder="First Name" required><br>
                        <input type="text" name="last_name" placeholder="Last Name" required><br>
                        <input type="email" name="email" placeholder="Email" required><br>
                        <div class="password-wrapper" style="position: relative; display: flex; align-items: center;">
                            <input type="password" name="password" id="password" placeholder="Password" required style="flex: 1; padding-right: 40px;">
                            <i class="fa fa-eye" id="togglePassword" style="position: absolute; top: 13px; right: 13px; cursor: pointer; color: #64748b;"></i>
                        </div><br>

                        <input type="text" name="phone_number" placeholder="Phone Number"><br>
                        <input type="date" name="date_of_birth" placeholder="Date of Birth"><br>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select><br>
                        <input type="text" name="specialization" placeholder="Specialization" required><br>
                        <input type="text" name="sub_specialties" placeholder="Sub-specialties"><br>
                        <input type="number" name="years_of_experience" placeholder="Years of Experience"><br>
                        <input type="text" name="medical_license_number" placeholder="Medical License Number"><br>
                        <input type="text" name="npi_number" placeholder="NPI Number"><br>
                        <textarea name="education_and_training" placeholder="Education and Training"></textarea><br>
                        <input type="submit" name="add_doctor" id="doctor-modal-button" value="Add Doctor">

                        <input type="hidden" name="doctor_id" id="doctor_id">
                        <input type="hidden" name="edit_doctor" value="1" >

                    </form>
                    <button type="button" onclick="document.getElementById('add-doctor-modal').style.display='none'">Close</button>
                </div>
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

        // search doctors
         document.getElementById('doctor-search').addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.doctor-row');

        rows.forEach(row => {
            const doctorName = row.dataset.name;
            if (doctorName.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    document.querySelector('.add-doctor-btn').addEventListener('click', function () {
        document.getElementById('add-doctor-modal').style.display = 'flex';
    });

    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");

    togglePassword.addEventListener("click", function () {
        const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
        passwordInput.setAttribute("type", type);
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
    });

    //

    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            const doctorId = row.dataset.doctorId;
            fetch('doctor_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'toggle_status', doctor_id: doctorId})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const statusText = data.newIsActive == 1 ? 'Active' : 'Inactive';
                    const statusSpan = row.querySelector('.status');

                    // Update text
                    statusSpan.textContent = statusText;

                    // Remove old classes and add new one
                    statusSpan.classList.remove('act', 'inactive');
                    statusSpan.classList.add(statusText === 'Active' ? 'act' : 'inactive');

                    // Optionally update row dataset status if you use it elsewhere
                    row.dataset.status = statusText;
                } else {
                    alert(data.error || 'Failed to toggle status');
                }
            })
        });
    });

    document.querySelectorAll('.btn-delete-doctor').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Are you sure you want to delete this doctor?')) return;
            const row = btn.closest('tr');
            const doctorId = row.dataset.doctorId;
            fetch('doctor_actions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'delete_doctor', doctor_id: doctorId})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                } else {
                    alert(data.error || 'Failed to delete doctor');
                }
            }).catch(() => alert('Network error'));
        });
    });


    document.querySelectorAll('.btn-edit-doctor').forEach(button => {
        button.addEventListener('click', function () {
            const row = this.closest('.doctor-row');
            const doctorId = row.dataset.doctorId;

            // Assuming you store all needed data in the row's dataset or via AJAX
            const name = row.querySelector('.doctor-name').innerText.replace('Dr. ', '').split(' ');
            const email = row.querySelector('.doctor-email').innerText;
            const specialty = row.children[1].innerText;

            // Set modal fields
            document.querySelector('#add-doctor-form [name="first_name"]').value = name[0] || '';
            document.querySelector('#add-doctor-form [name="last_name"]').value = name[1] || '';
            document.querySelector('#add-doctor-form [name="email"]').value = email;
            document.querySelector('#add-doctor-form [name="specialization"]').value = specialty;   

            // Set hidden fields
            document.querySelector('#add-doctor-form [name="edit_doctor"]').value = 1;
            document.querySelector('#add-doctor-form [name="doctor_id"]').value = doctorId;

            // Hide password field in edit mode
            document.querySelector('#password').required = false;
            document.querySelector('#password').parentElement.style.display = 'none';

            // Show modal
            document.getElementById('add-doctor-modal').style.display = 'block';
        });
    });

   
    document.addEventListener("DOMContentLoaded", function () {
        const editButtons = document.querySelectorAll('.btn-edit-doctor');
        const modalTitle = document.getElementById('doctor-modal-title');
        const modalButton = document.getElementById('doctor-modal-button');
        

        editButtons.forEach(button => {
            button.addEventListener('click', function () {
                // Change modal title and button text
                modalTitle.textContent = 'Edit Doctor';
                modalButton.value = 'Update Doctor';

                // Also change the button name to match edit form submission
                modalButton.name = 'edit_doctor';

                // (Optional) Set the doctor ID in a hidden input
                document.getElementById('doctor_id').value = this.closest('tr').dataset.doctorId;

                // Show the modal (depends on your modal framework)
                document.querySelector('#doctor-modal').style.display = 'block';
                
            });
        });

        // Reset modal on "Add Doctor" button click
        document.querySelector('.add-doctor-btn').addEventListener('click', function () {
            modalTitle.textContent = 'Add New Doctor';
            modalButton.value = 'Add Doctor';
            modalButton.name = 'add_doctor';

            // Reset form and hidden inputs if needed
            document.getElementById('doctor-form').reset();
            document.getElementById('doctor_id').value = '';
        });
    });






    </script>
</body>

</html>