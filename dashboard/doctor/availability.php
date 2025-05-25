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

   // Fetch doctor's availability data
    $query = "SELECT availability FROM doctor_details WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $availability = json_decode($row['availability'], true);
    } else {
        $availability = null; // No availability data found
    }

    // Handle form submission
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $availability = json_encode([
            'working_hours' => [
                'start_time' => $_POST['start_time'] ?? '08:00',
                'end_time' => $_POST['end_time'] ?? '17:00',
            ],
            'working_days' => $_POST['working_days'] ?? [],
            'appointment_duration' => $_POST['appointment_duration'] ?? 30,
            'break_time' => [
                'enabled' => isset($_POST['break_time_enabled']),
                'start_time' => $_POST['break_start_time'] ?? '12:00',
                'duration' => $_POST['break_duration'] ?? 30,
            ],
        ]);

        $query = "UPDATE doctor_details SET availability = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $availability, $doctorId);

        if ($stmt->execute()) {
            // Pass a success flag to the frontend
            echo "<script>localStorage.setItem('availabilityUpdated', 'true');</script>";
        } else {
            echo "<p>Error updating availability: " . $stmt->error . "</p>";
        }
    }


    
} catch (Exception $e) {
    error_log("Error fetching or updating availability: " . $e->getMessage());
    $availability = null;

    error_log("Error fetching doctor dashboard data: " . $e->getMessage());

} 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability</title>
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

        .availability-container {
            width: 100%;
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .settings-section {
            margin-bottom: 24px;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
        }

        .toggle-switch {
            position: relative;
            width: 46px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e2e8f0;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #3498db;
        }

        input:checked+.slider:before {
            transform: translateX(22px);
        }

        .time-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 8px;
        }

        .time-field {
            flex: 1;
        }

        .time-field p {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .time-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #1a202c;
            font-size: 14px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23718096' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
        }

        .working-days {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: px;
        }

        .day-checkbox {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            width: 14%;
        }

        .day-checkbox p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }

        .checkbox-container {
            width: 36px;
            height: 36px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            transition: all 0.3s ease;
            position: relative;
        }

        .checkbox-container.checked {
            background-color: #3498db;
            border-color: #3498db;
        }

        .checkbox-container.checked i {
            color: white;
            opacity: 1;
        }

        .checkbox-container i {
            font-size: 18px;
            color: transparent;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .save-btn {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .save-btn:hover {
            background-color: #2980b9;
        }

        .save-btn i {
            font-size: 16px;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4); /* Black background with opacity */
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 30%;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-in-out;
        }

        .modal-content h2 {
            color: #3498db;
            margin-bottom: 10px;
        }

        .modal-content p {
            color: #555;
            font-size: 16px;
        }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .working-days {
                gap: 10px;
            }

            .time-selector {
                gap: 15px;
            }
        }

        @media (max-width: 992px) {
            .content {
                padding: 25px;
            }

            .availability-container {
                padding: 20px;
            }

            .working-days {
                gap: 8px;
            }
        }

        @media (max-width: 768px) {
            .container {
                position: relative;
            }

            .sidebar {
                left: -260px;
                transition: all 0.3s;
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

            .user-dropdown span {
                display: none;
            }

            .working-days {
                flex-wrap: wrap;
                gap: 12px;
                justify-content: flex-start;
            }

            .day-checkbox {
                width: 14%;
            }

            .logo-text {
                font-size: 20px;
            }
        }

        @media (max-width: 576px) {
            .content {
                padding: 20px 15px;
            }

            .availability-container {
                padding: 16px;
            }

            .time-selector {
                flex-direction: column;
                gap: 12px;
            }

            .day-checkbox {
                width: 12%;
            }

            .day-checkbox p {
                font-size: 12px;
            }

            .checkbox-container {
                width: 28px;
                height: 28px;
            }

            .section-title h3 {
                font-size: 15px;
            }

            .time-select {
                font-size: 13px;
            }

            .save-btn {
                font-size: 14px;
                padding: 10px;
            }

            .top-bar {
                padding: 12px 20px;
            }

            .user-dropdown img {
                width: 32px;
                height: 32px;
            }

            .icon-btn {
                width: 36px;
                height: 36px;
            }
        }

        /* Additional Mobile Menu Handling */
        @media (min-width: 769px) {
            .sidebar {
                left: 0 !important;
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
                <a href="availability.php" class="nav-item active">
                    <i class="far fa-clock"></i>
                    <span>Availability</span>
                </a>
                <a href="patients.php" class="nav-item">
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
                    $baseImagePath = '../../images/doctors/';
                    $imageFile = $doctorProfile['profile_image'] ?? 'default.png';
                    $fullPath = $baseImagePath . $imageFile;
                    
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
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" 
                            alt="<?php echo htmlspecialchars($doctorProfile['full_name'] ?? 'Doctor'); ?>">
                        <span><?php echo "Dr. " . htmlspecialchars($doctorProfile['first_name'] . " " . $doctorProfile['last_name']); ?></span>
                    </div>
                </div>                    
            </div>

            <div class="content">
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1>Availability Settings</h1>
                    </div>
                </div>

                <form method="POST" action="availability.php">
                    <div class="availability-container">
                        <!-- Working Hours -->
                        <div class="settings-section">
                            <div class="section-title">
                                <h3>Working Hours</h3>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="working-hours-toggle" name="working_hours_enabled" <?php echo isset($availability['working_hours']) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="time-selector">
                                <div class="time-field">
                                    <p>Start Time</p>
                                    <select class="time-select" name="start_time" id="start-time">
                                        <option value="08:00">07:00 AM</option>
                                        <option value="07:00">08:00 AM</option>
                                        <option value="09:00">09:00 AM</option>
                                    </select>
                                </div>
                                <div class="time-field">
                                    <p>End Time</p>
                                    <select class="time-select" name="end_time" id="end-time">
                                        <option value="17:00">04:00 PM</option>
                                        <option value="16:00">05:00 PM</option>
                                        <option value="18:00">06:00 PM</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Working Days -->
                        <div class="settings-section">
                            <div class="section-title">
                                <h3>Working Days</h3>
                            </div>
                            <div class="working-days">
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Mon</p>
                                    <input type="checkbox" name="working_days[]" value="Monday" hidden>
                                </div>
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Tue</p>
                                    <input type="checkbox" name="working_days[]" value="Tuesday" hidden>
                                </div>
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Wed</p>
                                    <input type="checkbox" name="working_days[]" value="Wednesday" hidden>
                                </div>
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Thu</p>
                                    <input type="checkbox" name="working_days[]" value="Thursday" hidden>
                                </div>
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Fri</p>
                                    <input type="checkbox" name="working_days[]" value="Friday" hidden>
                                </div>
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Sat</p>
                                    <input type="checkbox" name="working_days[]" value="Saturday" hidden>
                                </div>
                                <div class="day-checkbox">
                                    <div class="checkbox-container">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <p>Sun</p>
                                    <input type="checkbox" name="working_days[]" value="Sunday" hidden>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <div class="section-title">
                                <h3>Appointment Duration</h3>
                            </div>
                            <select class="time-select" name="appointment_duration" id="appointment-duration">
                                <option value="15">15 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">60 minutes</option>
                            </select>
                        </div>

                        <div class="settings-section">
                            <div class="section-title">
                                <h3>Break Time</h3>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="break_time_enabled" id="break-time-toggle" <?php echo isset($availability['break_time']['enabled']) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="time-selector">
                                <div class="time-field">
                                    <p>Start Time</p>
                                    <select class="time-select" name="break_start_time" id="break-start">
                                        <option value="12:00">11:00 AM</option>
                                        <option value="13:00">12:00 PM</option>
                                        <option value="11:00">01:00 PM</option>
                                    </select>
                                </div>
                                <div class="time-field">
                                    <p>Duration</p>
                                    <select class="time-select" name="break_duration" id="break-duration">
                                        <option value="30">30 minutes</option>
                                        <option value="45">45 minutes</option>
                                        <option value="60">60 minutes</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <button type="submit" class="save-btn">
                            <i class="fas fa-save"></i>
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="success-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h2>Success</h2>
            <p>Availability updated successfully!</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            // Day checkboxes interaction
            document.querySelectorAll('.day-checkbox').forEach(dayCheckbox => {
                dayCheckbox.addEventListener('click', function () {
                    const checkboxContainer = this.querySelector('.checkbox-container');
                    const hiddenInput = this.querySelector('input[type="checkbox"]');
                    checkboxContainer.classList.toggle('checked');
                    hiddenInput.checked = !hiddenInput.checked;
                });
            });

            // Toggle switch functionality
            function handleToggle(toggle, selects) {
                const enabled = toggle.checked;
                selects.forEach(select => {
                    select.disabled = !enabled;
                });
            }

            // Working Hours toggle
            const workingHoursToggle = document.querySelector('#working-hours-toggle');
            const workingHoursSelects = document.querySelectorAll('.working-hours-select');
            workingHoursToggle.addEventListener('change', () => handleToggle(workingHoursToggle, workingHoursSelects));
            handleToggle(workingHoursToggle, workingHoursSelects); // Initial state

            // Break Time toggle
            const breakTimeToggle = document.querySelector('#break-time-toggle');
            const breakTimeSelects = document.querySelectorAll('.break-time-select');
            breakTimeToggle.addEventListener('change', () => handleToggle(breakTimeToggle, breakTimeSelects));
            handleToggle(breakTimeToggle, breakTimeSelects); // Initial state

            // Modal Elements
            const modal = document.getElementById('success-modal');
            const closeBtn = document.querySelector('.close-btn');

            // Check if availability was updated
            if (localStorage.getItem('availabilityUpdated') === 'true') {
                modal.style.display = 'block';
                localStorage.removeItem('availabilityUpdated');
            }

            // Close the modal
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
    <script src="assets/js/notifications.js"></script>
</body>

</html>