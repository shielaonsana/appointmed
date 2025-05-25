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
    
} catch (Exception $e) {
    error_log("Error fetching doctor dashboard data: " . $e->getMessage());
    $doctorProfile = [
        'full_name' => 'Doctor',
        'profile_image' => 'default.png',
        'specialization' => 'Specialization Not Set'
    ];
    $notifications = [];
    $unreadCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments</title>
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

        .appointments-container {
            max-width: 100%;
            background-color: #f5f9ff;
            border-radius: 12px;
        }

        .appointment-card {
            background-color: white;
            border: 1px solid #e0e7f1;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            position: relative;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .completed {
            background-color: #dcfce7;
            color: #16a34a;
        }

        .upcoming {
            background-color: #dbeafe;
            color: #2563eb;
        }

        .cancelled {
            background-color: #fee2e2;
            color: #dc2626;
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

        .patient-details {
            color: #666;
            font-size: 14px;
        }

        .appointment-details {
            margin-left: 52px;
        }

        .time-slot,
        .appointment-type {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .time-slot i,
        .appointment-type i {
            width: 16px;
            margin-right: 8px;
            color: #3498db;
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

        .btn-outline {
            border: 1px solid #e0e7f1;
            background-color: white;
            color: #64748b;
        }

        .btn-outline:hover {
            background-color: #f8fafc;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
        }

        .notes-textarea {
            width: 100%;
            height: 200px;
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #e0e7f1;
            border-radius: 6px;
            resize: vertical;
        }

        .time-slot-selector {
            margin: 20px 0;
        }

        .time-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e7f1;
            border-radius: 6px;
            margin-top: 10px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }


        /* Responsive Styles */
        @media (max-width: 1200px) {
            .content {
                padding: 25px;
            }

            .patient-name {
                font-size: 15px;
            }

            .patient-details,
            .time-slot,
            .appointment-type {
                font-size: 13px;
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

            .appointment-details {
                margin-left: 0;
                margin-top: 12px;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 15px 20px;
            }

            .action-buttons {
                flex-direction: column;
                margin-left: 0;
            }

            .btn {
                width: 100%;
            }

            .patient-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .patient-avatar {
                margin-bottom: 10px;
            }
        }

        @media (max-width: 576px) {
            .content {
                padding: 15px;
            }

            .top-bar {
                padding: 12px 15px;
            }

            .search-container {
                max-width: 100%;
            }

            .patient-avatar {
                width: 40px;
                /* Restore original size */
                height: 40px;
                margin-right: 12px;
                /* Maintain spacing */
            }

            .status-badge {
                position: absolute;
                /* Keep badge in original position */
                top: 15px;
                right: 15px;
                margin-bottom: 0;
                /* Remove previous bottom margin */
            }

            .patient-info {
                flex-direction: row;
                /* Keep inline layout */
                align-items: center;
                margin-bottom: 10px;
            }

            .appointment-details {
                margin-left: 52px;
                /* Restore original indentation */
                margin-top: 0;
            }

            .action-buttons {
                margin-left: 52px;
                /* Restore original indentation */
                flex-direction: row;
                /* Keep buttons inline */
                gap: 8px;
            }

            .btn {
                width: auto;
                flex: 1;
                /* Maintain equal button widths */
                padding: 8px;
                font-size: 13px;
                /* Slightly smaller text */
            }

            .patient-name {
                font-size: 15px;
                /* Slightly reduced size */
            }

            .patient-details,
            .time-slot,
            .appointment-type {
                font-size: 13px;
                /* Consistent text size */
            }

            .welcome-text h1 {
                font-size: 20px;
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
                <a href="appointments.php" class="nav-item active">
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
                    <div class="user-name">
                        <?php echo "Dr. " . htmlspecialchars($doctorProfile['full_name'] ?? 'Doctor'); ?>
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
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search patients, appointments...">

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
                        <h1>Today's Appointments</h1>
                    </div>
                </div>
                <div class="appointments-container">

                    <!-- Jennifer Adams -->
                    <div class="appointment-card">
                        <span class="status-badge completed">Completed</span>
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Jennifer Adams">
                            </div>
                            <div>
                                <div class="patient-name">Jennifer Adams</div>
                                <div class="patient-details">39 years old, Female</div>
                            </div>
                        </div>
                        <div class="appointment-details">
                            <div class="time-slot">
                                <i class="far fa-clock"></i>
                                08:00 - 08:30 AM
                            </div>
                            <div class="appointment-type">
                                <i class="fas fa-stethoscope"></i>
                                Consultation - Hypertension
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-outline">
                                <i class="far fa-file-alt"></i>
                                View Notes
                            </a>
                            <a href="#" class="btn btn-primary">
                                <i class="far fa-calendar-alt"></i>
                                Reschedule
                            </a>
                        </div>
                    </div>

                    <!-- Michael Wilson -->
                    <div class="appointment-card">
                        <span class="status-badge completed">Completed</span>
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Michael Wilson">
                            </div>
                            <div>
                                <div class="patient-name">Michael Wilson</div>
                                <div class="patient-details">68 years old, Male</div>
                            </div>
                        </div>
                        <div class="appointment-details">
                            <div class="time-slot">
                                <i class="far fa-clock"></i>
                                10:00 - 10:30 AM
                            </div>
                            <div class="appointment-type">
                                <i class="fas fa-stethoscope"></i>
                                Follow-up - Post Surgery
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-outline">
                                <i class="far fa-file-alt"></i>
                                View Notes
                            </a>
                            <a href="#" class="btn btn-primary">
                                <i class="far fa-calendar-alt"></i>
                                Reschedule
                            </a>
                        </div>
                    </div>

                    <!-- Patricia Lee -->
                    <div class="appointment-card">
                        <span class="status-badge completed">Completed</span>
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Patricia Lee">
                            </div>
                            <div>
                                <div class="patient-name">Patricia Lee</div>
                                <div class="patient-details">55 years old, Female</div>
                            </div>
                        </div>
                        <div class="appointment-details">
                            <div class="time-slot">
                                <i class="far fa-clock"></i>
                                13:00 - 13:30 PM
                            </div>
                            <div class="appointment-type">
                                <i class="fas fa-stethoscope"></i>
                                Check-up - Annual
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-outline">
                                <i class="far fa-file-alt"></i>
                                View Notes
                            </a>
                            <a href="#" class="btn btn-primary">
                                <i class="far fa-calendar-alt"></i>
                                Reschedule
                            </a>
                        </div>
                    </div>

                    <!-- Thomas Garcia -->
                    <div class="appointment-card">
                        <span class="status-badge upcoming">Upcoming</span>
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Thomas Garcia">
                            </div>
                            <div>
                                <div class="patient-name">Thomas Garcia</div>
                                <div class="patient-details">47 years old, Male</div>
                            </div>
                        </div>
                        <div class="appointment-details">
                            <div class="time-slot">
                                <i class="far fa-clock"></i>
                                7:00 -8:30 PM
                            </div>
                            <div class="appointment-type">
                                <i class="fas fa-stethoscope"></i>
                                Consultation - Chest Pain
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-outline cancel-btn">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                            <a href="#" class="btn btn-primary checkin-btn">
                                <i class="fas fa-check"></i>
                                Check In
                            </a>
                        </div>
                    </div>

                    <!-- Elizabeth Martin -->
                    <div class="appointment-card">
                        <span class="status-badge upcoming">Upcoming</span>
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="images/doctor-1.png" alt="Elizabeth Martin">
                            </div>
                            <div>
                                <div class="patient-name">Elizabeth Martin</div>
                                <div class="patient-details">61 years old, Female</div>
                            </div>
                        </div>
                        <div class="appointment-details">
                            <div class="time-slot">
                                <i class="far fa-clock"></i>
                                16:00 - 16:30 PM
                            </div>
                            <div class="appointment-type">
                                <i class="fas fa-stethoscope"></i>
                                Emergency - Arrhythmia
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="#" class="btn btn-outline cancel-btn">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                            <a href="#" class="btn btn-primary checkin-btn">
                                <i class="fas fa-check"></i>
                                Check In
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <div id="notesModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Medical Notes</h3>
            <div class="notes-content" id="notesContent"></div>
            <textarea id="editNotes" class="notes-textarea" placeholder="Add notes..."></textarea>
            <button class="btn btn-primary" id="saveNotes">Save Changes</button>
        </div>
    </div>

    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Reschedule Appointment</h3>
            <div class="time-slot-selector">
                <label>Select New Time Slot:</label>
                <select id="newTimeSlot" class="time-select">
                    <option value="08:00 - 08:30 AM">08:00 - 08:30 AM</option>
                    <option value="09:00 - 09:30 AM">09:00 - 09:30 AM</option>
                    <option value="10:00 - 10:30 AM">10:00 - 10:30 AM</option>
                    <option value="11:00 - 11:30 AM">11:00 - 11:30 AM</option>
                    <option value="13:00 - 13:30 PM">13:00 - 13:30 PM</option>
                    <option value="14:00 - 14:30 PM">14:00 - 14:30 PM</option>
                    <option value="15:00 - 15:30 PM">15:00 - 15:30 PM</option>
                    <option value="16:00 - 16:30 PM">16:00 - 16:30 PM</option>
                </select>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" id="cancelReschedule">Cancel</button>
                <button class="btn btn-primary" id="confirmReschedule">Confirm</button>
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

        // View Notes Logic
        document.querySelectorAll('.btn-outline').forEach(button => {
            if (button.textContent.includes('View Notes')) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modal = document.getElementById('notesModal');
                    const notesContent = e.target.closest('.appointment-card').dataset.notes || '';
                    document.getElementById('editNotes').value = notesContent;
                    modal.style.display = 'block';
                });
            }
        });

        // Close modal logic
        document.querySelectorAll('.close, .modal').forEach(element => {
            element.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal') || e.target.classList.contains('close')) {
                    document.getElementById('notesModal').style.display = 'none';
                }
            });
        });

        // Save notes logic
        document.getElementById('saveNotes').addEventListener('click', () => {
            const notes = document.getElementById('editNotes').value;
            // Here you would typically send to server
            document.getElementById('notesModal').style.display = 'none';
        });

        // Reschedule Functionality
        let currentAppointmentCard = null;

        document.querySelectorAll('.btn-primary').forEach(button => {
            if (button.textContent.includes('Reschedule')) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentAppointmentCard = e.target.closest('.appointment-card');
                    document.getElementById('rescheduleModal').style.display = 'block';
                });
            }
        });

        // Confirm Reschedule
        document.getElementById('confirmReschedule').addEventListener('click', () => {
            if (currentAppointmentCard) {
                const newTime = document.getElementById('newTimeSlot').value;
                currentAppointmentCard.querySelector('.time-slot').innerHTML = `
            <i class="far fa-clock"></i>
            ${newTime}
        `;
                closeRescheduleModal();
            }
        });

        // Close Modal Functions
        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').style.display = 'none';
            currentAppointmentCard = null;
        }

        document.querySelectorAll('#rescheduleModal .close, #cancelReschedule').forEach(element => {
            element.addEventListener('click', closeRescheduleModal);
        });

        // Close modal when clicking outside
        window.onclick = function (event) {
            const rescheduleModal = document.getElementById('rescheduleModal');
            if (event.target === rescheduleModal) {
                closeRescheduleModal();
            }
        };

        document.querySelectorAll('.cancel-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const appointmentCard = e.target.closest('.appointment-card');
                appointmentCard.remove();
            });
        });

        // Check-In Functionality (updated)
        document.querySelectorAll('.checkin-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const appointmentCard = e.target.closest('.appointment-card');
                const statusBadge = appointmentCard.querySelector('.status-badge');
                const actionButtons = appointmentCard.querySelector('.action-buttons');

                // Update to completed style
                statusBadge.classList.remove('upcoming');
                statusBadge.classList.add('completed');
                statusBadge.textContent = 'Completed';

                // Update action buttons to match completed state
                actionButtons.innerHTML = `
            <a href="#" class="btn btn-outline">
                <i class="far fa-file-alt"></i>
                View Notes
            </a>
            <a href="#" class="btn btn-primary">
                <i class="far fa-calendar-alt"></i>
                Reschedule
            </a>
        `;

                // Re-attach event listeners
                attachNotesListener(actionButtons.querySelector('.btn-outline'));
                attachRescheduleListener(actionButtons.querySelector('.btn-primary'));
            });
        });
    </script>

    <script src="assets/js/notifications.js"></script>
</body>

</html>