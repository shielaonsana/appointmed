<?php
session_start();

// Check if the user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Doctor') {
    header("Location: ../../main-page/login.php");
    exit();
}

require_once '../../config/database.php';

$doctorId = $_SESSION['user_id']; // Logged-in doctor's user_id

try {

    // Fetch doctor's profile data (single query)
    $query = "SELECT u.full_name, u.profile_image, d.first_name, d.last_name, d.specialization 
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
    else 
    {
        echo "<p>No data found for doctor ID: " . htmlspecialchars($doctorId) . "</p>";
        $doctorProfile = [
            'first_name' => 'Doctor',
            'last_name' => '',
            'specialization' => 'Specialization Not Set',
            'profile_image' => 'default.png'
        ];
    }
    
} catch (Exception $e) {
    error_log("Error fetching doctor dashboard data: " . $e->getMessage());
    
    $todaysAppointments = $pendingRequests = $totalPatients = $completedToday = 0;
    $upcomingAppointments = null;

    $notifications = [];
    $unreadCount = 0;
} 
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients</title>
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="assets/css/notifications.css">
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

        .patients-container {
            max-width: 100%;
            background-color: #f5f9ff;
            border-radius: 12px;
        }

        .patient-card {
            background-color: white;
            border: 1px solid #e0e7f1;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            position: relative;
        }

        .patient-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 12px;
        }

        .patient-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .patient-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 16px;
        }

        .patient-age {
            color: #666;
            font-size: 14px;
        }

        .patient-details {
            margin-left: 52px;
        }

        .last-visit,
        .reason {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .action-buttons {
            margin-left: 52px;
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .btn {
            flex: 1;
            padding: 8px;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #3498dbd8;
        }

        .btn i {
            margin-right: 6px;
        }

        /* Enhanced Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content {
            background: #ffffff;
            margin: 2% auto;
            padding: 32px;
            width: 85%;
            max-width: 900px;
            border-radius: 20px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
        }

        .close {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(241, 245, 249, 0.8);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close:hover {
            color: #ef4444;
            background: rgba(241, 245, 249, 1);
            transform: rotate(90deg);
        }

        /* Premium Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            z-index: 1000;
            animation: modalEntrance 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            overflow: hidden;

        }




        @keyframes modalEntrance {
            0% {
                opacity: 0;
                transform: scale(0.96) translateY(20px);
            }

            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-content {
            background: linear-gradient(145deg, #ffffff 0%, #f8faff 100%);
            margin: 2% auto;
            padding: 40px;
            width: 85%;
            max-width: 880px;
            border-radius: 24px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            font-family: 'Outfit', sans-serif;
            scrollbar-width: none;
            /* Firefox */
            -ms-overflow-style: none;
            /* IE/Edge */
            overflow-y: auto;
            /* Keep this to maintain scrolling */
            -webkit-overflow-scrolling: touch;
            /* Smooth scrolling on iOS */
        }

        .modal-content::-webkit-scrollbar {
            display: none;
            /* Chrome/Safari/Opera */
        }

        .close {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 22px;
            color: #94a3b8;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(241, 245, 249, 0.8);
            backdrop-filter: blur(4px);
        }

        .close:hover {
            color: #ef4444;
            transform: rotate(90deg);
            background: rgba(241, 245, 249, 1);
        }

        .modal-section {
            margin: 0px 0;
            padding: 0;
        }

        .modal-section ul {
            list-style-type: none;
            padding-left: 0;
        }

        .modal-section li {
            position: relative;
            padding-left: 1.2rem;
            margin: 8px 0;
        }

        .modal-section h2 {
            color: #0f172a;
            font-weight: 700;
            margin-top: 28px;
            margin-bottom: 28px;
            font-size: 1.6rem;
            position: relative;
            padding-left: 24px;
            letter-spacing: -0.5px;
        }

        .modal-section h2::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 70%;
            width: 4px;
            background: linear-gradient(45deg, #3498db, #60a5fa);
            border-radius: 4px;
        }

        .demographics-grid {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 36px;
            align-items: start;
        }

        .profile-photo {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(52, 152, 219, 0.15);
        }

        .profile-photo img {
            width: 100%;
            height: auto;
            display: block;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
        }

        .demographics-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .demographics-info p {
            margin: 0;
            padding: 16px;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 12px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(226, 232, 240, 0.3);
            display: flex;
            gap: 12px;
            align-items: center;
            transition: all 0.2s ease;
        }

        .demographics-info p:hover {
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(52, 152, 219, 0.1);
        }

        .demographics-info strong {
            color: #1e293b;
            font-weight: 600;
            min-width: 90px;
            display: inline-block;
            font-size: 0.95em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;

        }

        .info-grid div {
            padding: 24px;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 16px;
            border: 1px solid rgba(226, 232, 240, 0.3);
            backdrop-filter: blur(4px);
            transition: all 0.2s ease;
        }

        .info-grid div:hover {
            background: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(52, 152, 219, 0.08);
        }

        .info-grid h3 {
            color: #1e293b;
            margin-bottom: 16px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .info-grid h3 i {
            color: #3498db;
            font-size: 1.2em;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 8px;
            padding: 6px;
        }

        .columns {
            margin-top: 28px;
            margin-bottom: 28px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 36px;
            background: rgba(248, 250, 252, 0.6);
            padding: 32px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(226, 232, 240, 0.3);
        }

        .columns:hover {
            background: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 6px 24px rgba(52, 152, 219, 0.08);
        }


        .timeline {
            position: relative;
            padding-left: 24px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 4px;
            top: 0;
            height: 100%;
            width: 2px;
            background: rgba(52, 152, 219, 0.1);
        }

        .timeline-item {
            position: relative;
            margin: 28px 0;
            padding: 24px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
        }

        .timeline-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 32px rgba(52, 152, 219, 0.1);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -28px;
            top: 24px;
            width: 12px;
            height: 12px;
            background: #3498db;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #3498db;
        }

        .timeline-date {
            color: #3498db;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-actions {
            display: flex;
            gap: 16px;
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid rgba(226, 232, 240, 0.5);
        }

        .modal-actions .btn {
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            border: none;
        }


        @media (max-width: 768px) {
            .modal-content {
                padding: 28px;
                width: 95%;
            }

            .demographics-grid {
                grid-template-columns: 1fr;
            }

            .demographics-info {
                grid-template-columns: 1fr;
            }

            .columns {
                 margin-top: 28px;
                grid-template-columns: 1fr;
                padding: 24px;
            }

            .timeline-item {
                padding: 20px;
                margin: 24px 0;
            }

            .modal-actions {
                flex-direction: column;
            }
        }


        /* Responsive Styles */
        @media (max-width: 1200px) {
            .content {
                padding: 25px;
            }

            .patient-details {
                margin-left: 45px;
            }
        }

        @media (max-width: 992px) {
            .content {
                padding: 20px;
            }

            .search-container {
                max-width: 400px;
            }

            .user-dropdown span {
                display: none;
            }

            .patient-avatar {
                width: 35px;
                height: 35px;
            }

            .patient-name {
                font-size: 15px;
            }

            .modal-body {
                grid-template-columns: repeat(2, 1fr);
                /* Two columns on medium screens */
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
                transition: 0.3s;
                z-index: 100;
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

            .top-bar {
                padding: 15px 20px;
            }

            .search-container {
                max-width: calc(100% - 100px);
            }

            .patient-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .patient-details {
                margin-left: 0;
                margin-top: 10px;
            }

            .action-buttons {
                margin-left: 0;
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .modal-body {
                grid-template-columns: 1fr;
                /* Single column on mobile */
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                flex-wrap: nowrap;
                gap: 10px;
                padding: 12px 15px;
            }

            .search-container {
                max-width: 100%;
                order: 2;
            }

            .menu-toggle {
                order: 1;
                margin-right: 10px;
            }

            .notification-container {
                order: 3;
                margin-left: 0;
            }

            .content {
                padding: 15px;
            }

            .welcome-text h1 {
                font-size: 20px;
            }

            .patient-card {
                padding: 15px;
            }

            .patient-info {
                flex-direction: row;
                align-items: center;
                margin-bottom: 12px;
            }

            .patient-avatar {
                width: 40px;
                height: 40px;
                margin-right: 12px;
                margin-bottom: 0;
            }

            .patient-details {
                margin-left: 52px;
                margin-top: 0;
            }

            .action-buttons {
                margin-left: 52px;
                flex-direction: row;
                gap: 8px;
            }

            .btn {
                width: auto;
                padding: 8px 12px;
                font-size: 13px;
            }

            .patient-age,
            .last-visit p,
            .reason p {
                font-size: 13px;
            }

            .icon-btn {
                width: 35px;
                height: 35px;
            }

            .user-dropdown img {
                width: 32px;
                height: 32px;
            }

            .patient-name {
                font-size: 15px;
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
                <a href="patients.php" class="nav-item active">
                   <i class="fa-solid fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="profile.php" class="nav-item">
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
                    <div class="user-name"><?php echo "Dr. " . htmlspecialchars($doctorProfile['full_name'] ?? 'Doctor'); ?></div>
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
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search patient...">

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
                        <h1>Patients</h1>
                    </div>
                </div>
                <div class="patients-container">

                    <!-- Jennifer Adams -->
                    <div class="patient-card">

                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Jennifer Adams">
                            </div>
                            <div>
                                <div class="patient-name">Jennifer Adams</div>
                                <div class="patient-age">39 years old, Female</div>
                            </div>
                        </div>
                        <div class="patient-details">
                            <div class="last-visit">
                                <p>Last Visit: 2025-04-03</p>
                            </div>
                            <div class="reason">
                                <p>Reason: Check-up</p>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-primary">
                                <i class="far fa-file-alt"></i>
                                View Details
                            </a>
                        </div>
                    </div>

                    <!-- Jennifer Adams -->
                    <div class="patient-card">

                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Jennifer Adams">
                            </div>
                            <div>
                                <div class="patient-name">Jennifer Adams</div>
                                <div class="patient-age">39 years old, Female</div>
                            </div>
                        </div>
                        <div class="patient-details">
                            <div class="last-visit">
                                <p>Last Visit: 2025-04-03</p>
                            </div>
                            <div class="reason">
                                <p>Reason: Check-up</p>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-primary">
                                <i class="far fa-file-alt"></i>
                                View Details
                            </a>
                        </div>
                    </div>

                    <!-- Jennifer Adams -->
                    <div class="patient-card">

                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Jennifer Adams">
                            </div>
                            <div>
                                <div class="patient-name">Jennifer Adams</div>
                                <div class="patient-age">39 years old, Female</div>
                            </div>
                        </div>
                        <div class="patient-details">
                            <div class="last-visit">
                                <p>Last Visit: 2025-04-03</p>
                            </div>
                            <div class="reason">
                                <p>Reason: Check-up</p>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-primary">
                                <i class="far fa-file-alt"></i>
                                View Details
                            </a>
                        </div>
                    </div>



                </div>
            </div>
        </div>

        
        <div id="patientModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>

                <!-- Core Demographics -->
                <div class="modal-section">
                    <h2>Patient Profile</h2>
                    <div class="demographics-grid">
                        <div class="profile-photo">
                            <img src="images/doctor-1.png" alt="Patient Photo">
                        </div>
                        <div class="demographics-info">
                            <p><strong>Name:</strong> Jennifer Adams</p>
                            <p><strong>Date of Birth:</strong> 1985-04-15 (39 years)</p>
                            <p><strong>Gender:</strong> Female</p>
                            <p><strong>Phone:</strong> (555) 123-4567</p>
                            <p><strong>Email:</strong> j.adams@email.com</p>
                            <p><strong>Address:</strong> 123 Main St, Cityville, ST 12345</p>
                        </div>
                    </div>
                </div>

                <!-- Medical Information -->
                <div class="modal-section">
                    <h2>Medical Information</h2>
                    <div class="info-grid">
                        <div>
                            <h3>Allergies</h3>
                            <ul>
                                <li>Penicillin (Severe)</li>
                                <li>Shellfish</li>
                            </ul>
                        </div>
                        <div>
                            <h3>Current Medications</h3>
                            <ul>
                                <li>Lisinopril 10mg - Daily</li>
                                <li>Metformin 500mg - Twice daily</li>
                            </ul>
                        </div>
                        <div>
                            <h3>Medical History</h3>
                            <ul>
                                <li>Type 2 Diabetes (2018-present)</li>
                                <li>Appendectomy (2002)</li>
                            </ul>
                        </div>
                        <div>
                            <h3>Vital Signs</h3>
                            <ul>
                                <li>BP: 120/80 mmHg</li>
                                <li>BMI: 24.1</li>
                                <li>Heart Rate: 72 bpm</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Insurance & Emergency Contacts -->
                <div class="modal-section columns">
                    <div>
                        <h2>Insurance Information</h2>
                        <p><strong>Provider:</strong> HealthCare Plus</p>
                        <p><strong>Policy #:</strong> HC-123456789</p>
                        <p><strong>Group #:</strong> GRP-9876</p>
                    </div>
                    <div>
                        <h2>Emergency Contacts</h2>
                        <p><strong>Name:</strong> John Adams (Spouse)</p>
                        <p><strong>Phone:</strong> (555) 765-4321</p>
                        <p><strong>Email:</strong> j.adams@email.com</p>
                    </div>
                </div>

                <!-- Visit History -->
                <div class="modal-section">
                    <h2>Visit History & Appointments</h2>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date">2025-04-03</div>
                            <div class="timeline-content">
                                <h4>Annual Check-up</h4>
                                <p>Diagnosis: Stable condition</p>
                                <p>Treatment: Maintain current regimen</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-date">2024-10-15</div>
                            <div class="timeline-content">
                                <h4>Follow-up Visit</h4>
                                <p>Adjusted medication dosage</p>
                            </div>
                        </div>
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

            // Search functionality
            const searchInput = document.querySelector('.search-input');
            const appointmentCards = document.querySelectorAll('.appointment-card');

            function filterAppointments(searchTerm) {
                const term = searchTerm.toLowerCase().trim();

                appointmentCards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    const patientName = card.querySelector('.patient-name').textContent.toLowerCase();
                    const appointmentType = card.querySelector('.appointment-type').textContent.toLowerCase();
                    const timeSlot = card.querySelector('.time-slot').textContent.toLowerCase();
                    const patientDetails = card.querySelector('.patient-details').textContent.toLowerCase();

                    const match = text.includes(term) ||
                        patientName.includes(term) ||
                        appointmentType.includes(term) ||
                        timeSlot.includes(term) ||
                        patientDetails.includes(term);

                    card.style.display = match ? 'block' : 'none';
                });
            }

            // Add debounce function to limit search frequency
            function debounce(func, timeout = 300) {
                let timer;
                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => { func.apply(this, args); }, timeout);
                };
            }

            const searchAppointments = debounce((e) => filterAppointments(e.target.value));

            // Add event listener
            searchInput.addEventListener('input', searchAppointments);

            // Modal Handling
            const modal = document.getElementById('patientModal');
            const closeBtn = document.querySelector('.close');
            const viewDetailsBtns = document.querySelectorAll('.btn-primary');

            viewDetailsBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    modal.style.display = 'block';
                });
            });

            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

        </script>
        <script src="assets/js/notifications.js"></script>
</body>

</html>