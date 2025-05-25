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

    // Fetch today's appointments
    $query = "SELECT COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $todaysAppointments = $result->fetch_assoc()['total'];

    // Fetch pending requests
    $query = "SELECT COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingRequests = $result->fetch_assoc()['total'];

    // Fetch total patients
    $query = "SELECT COUNT(DISTINCT patient_id) AS total FROM appointments WHERE doctor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalPatients = $result->fetch_assoc()['total'];

    // Fetch completed appointments today
    $query = "SELECT COUNT(*) AS total FROM appointments WHERE doctor_id = ? AND status = 'Completed' AND DATE(appointment_date) = CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $completedToday = $result->fetch_assoc()['total'];

    // Fetch upcoming appointments
    $query = "SELECT a.appointment_date, a.appointment_time, u.full_name AS patient_name, a.status
              FROM appointments a
              JOIN users u ON a.patient_id = u.user_id
              WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE()
              ORDER BY a.appointment_date, a.appointment_time
              LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $upcomingAppointments = $stmt->get_result();
} catch (Exception $e) {
    error_log("Error fetching doctor dashboard data: " . $e->getMessage());
    error_log("Error fetching notifications: " . $e->getMessage());
    $unreadCount = 0;
    $notifications = null;
    $todaysAppointments = $pendingRequests = $totalPatients = $completedToday = 0;
    $upcomingAppointments = null;
} 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Dashboard</title>
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Grand+Hotel&family=Jost:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&family=Winky+Sans:ital,wght@0,300..900;1,300..900&display=swap');


        /* Dashboard CSS with Icons and Clean Layout */
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

        /* Sidebar (unchanged from your original) */
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
            flex: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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


        .user-dropdown {
            display: flex;
            align-items: center;
            /* Removed margin-left: 15px; to fix spacing */
        }

        .user-dropdown img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 30px;
            width: 100%;
        }

        /* Welcome Section */
        .welcome-section {
            margin-bottom: 24px;
        }

        .welcome-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .welcome-header p {
            color: #666;
            font-size: 14px;
        }

        /* Stats Grid with Icons */
        .stats-grid {
            margin: 25px 0;
        }

        .stat-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .stat-card {
            flex: 1;
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e0e7f1;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-text {
            flex-grow: 1;
        }

        .stat-text h3 {
            color: #718096;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
        }

        /* Icon Color Classes */
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

        /* Upcoming Appointments */
        .upcoming-box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e0e7f1;
        }

        .upcoming-box h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
        }

        .appointment-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            font-weight: 600;
            padding: 10px 0;
            border-bottom: 1px solid #e0e7f1;
            margin-bottom: 10px;
            color: #1f2937;
        }

        .appointment-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            color: #1f2937;
        }

        .no-appointments {
            text-align: center;
            padding: 20px 0;
            color: #666;
        }

        /* Footer */
        .dashboard-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e7f1;
        }

        .doctor-signature {
            font-weight: 600;
            color: #1f2937;
        }

        .footer-links a {
            color: #3498db;
            text-decoration: none;
            margin-left: 15px;
            font-size: 14px;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            /* Default layout works fine */
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

            .menu-toggle {
                display: block;
            }

            .stat-row {
                flex-direction: column;
            }
            
            .stat-content {
                gap: 10px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .appointment-header,
            .appointment-item {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .content {
                padding: 20px 15px;
            }

            .top-bar {
                padding: 12px 15px;
            }

            .welcome-header h1 {
                font-size: 20px;
            }
        
        }
    </style>
</head>

<body data-user-id="<?php echo $doctorId; ?>">
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
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-header">
                        <h1>Welcome back, <?php echo "Dr. " . htmlspecialchars($doctorProfile['first_name'] . " " . $doctorProfile['last_name']); ?></h1>
                        <p><?php echo date("l, F j, Y"); ?></p>
                    </div>
                </div>

                <!-- Stats Cards With Icons -->
                <div class="stats-grid">
                    <div class="stat-row">
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon blue">
                                    <i class="far fa-calendar"></i>
                                </div>
                                <div class="stat-text">
                                    <h3>Today's Appointments</h3>
                                    <div class="stat-number"><?php echo $todaysAppointments; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon yellow">
                                    <i class="far fa-clock"></i>
                                </div>
                                <div class="stat-text">
                                    <h3>Pending Requests</h3>
                                    <div class="stat-number"><?php echo $pendingRequests; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-row">
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon green">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div class="stat-text">
                                    <h3>Total Patients</h3>
                                    <div class="stat-number"><?php echo $totalPatients; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-icon purple">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="stat-text">
                                    <h3>Completed Today</h3>
                                    <div class="stat-number"><?php echo $completedToday; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Appointments Section -->
                <div class="upcoming-box">
                    <h2>Upcoming Appointments</h2>
                    <div class="appointment-header">
                        <span>Date</span>
                        <span>Time</span>
                        <span>Patient</span>
                        <span>Status</span>
                    </div>
                    <?php if ($upcomingAppointments && $upcomingAppointments->num_rows > 0): ?>
                        <?php while ($appointment = $upcomingAppointments->fetch_assoc()): ?>
                            <div class="appointment-item">
                                <span><?php echo date("M j, Y", strtotime($appointment['appointment_date'])); ?></span>
                                <span><?php echo date("g:i A", strtotime($appointment['appointment_time'])); ?></span>
                                <span><?php echo htmlspecialchars($appointment['patient_name']); ?></span>
                                <span><?php echo htmlspecialchars($appointment['status']); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-appointments">No upcoming appointments.</p>
                    <?php endif; ?>
                </div>
            </div><!-- End of content -->
        </div><!-- End of main-content -->
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

    </script>
    <script src="assets/js/notifications.js"></script>
</body>

</html>