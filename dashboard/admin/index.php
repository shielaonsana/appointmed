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

// Ensure patient_id is set
if (!isset($adminProfile['admin_id']) || $adminProfile['admin_id'] == 0) {
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

// Fetch doctors counts for stats
$sql = "SELECT COUNT(*) as total FROM doctor_details";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalDoctors = $row['total'];

// Fetch patients counts for stats
$sql = "SELECT COUNT(*) as total FROM patients";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalPatients = $row['total'];

// Fetch appointments counts for stats
$sql = "SELECT COUNT(*) as total FROM appointments";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalAppointments = $row['total'];



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin's Dashboard</title>
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

        .welcome-text p {
            color: #666;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border: 1px solid #e2e8f0;
        }

        .stat-info h3 {
            color: #718096;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }

        .stat-change,
        .stat-rate {
            font-size: 12px;
            display: flex;
            align-items: center;
        }

        .stat-change i {
            margin-right: 4px;
        }

        .positive {
            color: #38a169;
        }

        .negative {
            color: #e53e3e;
        }

        .stat-rate {
            color: #718096;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .blue {
            background-color: rgba(66, 153, 225, 0.2);
            color: #3182ce;
        }

        .yellow {
            background-color: rgba(236, 201, 75, 0.2);
            color: #d69e2e;
        }

        .green {
            background-color: rgba(72, 187, 120, 0.2);
            color: #38a169;
        }

        .purple {
            background-color: rgba(159, 122, 234, 0.2);
            color: #805ad5;
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

        /* Add these styles to your existing CSS */
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            margin-top: 10px;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid #e0e7f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1f2937;
        }

        .notification-header button {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            font-size: 14px;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #e0e7f1;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .notification-item:hover {
            background-color: #f8fafc;
        }

        .notification-item.unread {
            background-color: #f0f5ff;
        }

        .notification-item .notification-title {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .notification-item .notification-message {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .notification-item .notification-time {
            color: #999;
            font-size: 12px;
        }

        .notification-item .notification-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 8px;
        }

        .notification-type.appointment {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .notification-type.system {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }

        .notification-type.alert {
            background-color: #ffebee;
            color: #c62828;
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
                <a href="index.php" class="nav-item active">
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
                    <div class="icon-btn" id="notificationBtn">
                        <i class="far fa-bell"></i>
                        <span class="notification-badge" id="notificationCount">0</span>
                    </div>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button id="markAllRead">Mark all as read</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <!-- Notifications will be loaded here -->
                        </div>
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
                        <h1>Welcome back, <?php echo htmlspecialchars($adminProfile['first_name'] ?? 'Admin'); ?>!</h1>
                        <p><?php echo date('l, F j, Y'); ?></p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Doctors</h3>
                            <div class="stat-number"><?php echo $totalDoctors; ?></div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Total Patients</h3>
                            <div class="stat-number"><?php echo $totalPatients; ?></div>
                        </div>
                        <div class="stat-icon yellow">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Today's Appointments</h3>
                            <div class="stat-number"><?php echo $totalAppointments; ?></div>
                        </div>
                        <div class="stat-icon green">
                            <i class="far fa-calendar"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>System Status</h3>
                            <div class="stat-number">Healthy</div>
                            <div class="stat-rate">
                                All systems operational
                            </div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-check"></i>
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

        // Add this JavaScript to your existing script
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notificationList');
            const notificationCount = document.getElementById('notificationCount');
            const markAllReadBtn = document.getElementById('markAllRead');

            // Toggle notification dropdown
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
                if (notificationDropdown.classList.contains('active')) {
                    loadNotifications();
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
                    notificationDropdown.classList.remove('active');
                }
            });

            // Load notifications
            function loadNotifications() {
                fetch('get_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        notificationList.innerHTML = '';
                        notificationCount.textContent = data.unreadCount;

                        data.notifications.forEach(notification => {
                            const notificationItem = document.createElement('div');
                            notificationItem.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
                            notificationItem.innerHTML = `
                                <div class="notification-title">
                                    <span class="notification-type ${notification.type}">${notification.type}</span>
                                    ${notification.title}
                                </div>
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${notification.created_at}</div>
                            `;
                            notificationList.appendChild(notificationItem);
                        });
                    })
                    .catch(error => console.error('Error loading notifications:', error));
            }

            // Mark all as read
            markAllReadBtn.addEventListener('click', function() {
                fetch('mark_all_read.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                    }
                })
                .catch(error => console.error('Error marking notifications as read:', error));
            });

            // Load notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
    </script>
</body>

</html>