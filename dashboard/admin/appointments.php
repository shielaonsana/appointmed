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

// Fetch appointments with patient and doctor info
$query = "
    SELECT 
    a.appointment_id,
    a.appointment_date,
    a.status,
    p.first_name AS patient_first_name,
    p.last_name AS patient_last_name,
    p.patient_id,
    u_p.profile_image AS patient_image,
    d.first_name AS doctor_first_name,
    d.last_name AS doctor_last_name,
    d.specialization,
    u_d.profile_image AS doctor_image
FROM appointments a
JOIN patients p ON a.patient_id = p.patient_id
JOIN users u_p ON u_p.user_id = p.user_id
JOIN doctor_details d ON a.doctor_id = d.doctor_id
JOIN users u_d ON u_d.user_id = d.user_id
ORDER BY a.appointment_date DESC

";
$result = $conn->query($query);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments</title>
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

        /* Appointment Management Table Styles */
        .appointment-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-appointment-btn {
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

        .add-appointment-btn:hover {
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

        .patient-avatar img,
        .doctor-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .patient-info,
        .doctor-info {
            display: flex;
            align-items: center;
        }

        .patient-details,
        .doctor-details {
            display: flex;
            flex-direction: column;
        }

        .patient-name,
        .doctor-name {
            font-weight: 500;
            color: #1f2937;
        }

        .patient-id,
        .doctor-specialty {
            font-size: 13px;
            color: #64748b;
        }

        .date-time {
            display: flex;
            flex-direction: column;
        }

        .date {
            font-weight: 500;
        }

        .time {
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

        .completed {
            background-color: #e6f7e9;
            color: #3dbb65;
        }

        .upcoming {
            background-color: #fff2e0;
            color: #f59e0b;
        }

        .cancelled {
            background-color: #fee2e2;
            color: #ef4444;
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

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 100;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .appointment-search-filter {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .appointment-search-box {
                width: 100%;
            }

            .appointment-action-buttons {
                justify-content: space-between;
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
                <a href="doctors.php" class="nav-item">
                    <i class="fa-solid fa-user-doctor"></i>
                    <span>Doctors</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="appointments.php" class="nav-item active">
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
                    <input type="text" class="search-input" id="searchInput" placeholder="Search appointments">
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
                        <h1>Appointments</h1>
                    </div>
                </div>

                <!-- Appointment Management Interface -->
                <div class="appointment-container">

                    <!-- Appointment Table -->
                    <table>
                        <thead>
                            <tr>

                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Appointment 1 -->
                            <?php while ($row = $result->fetch_assoc()): 

                            ?>
                                
                            <tr>
                                <td>
                                    <div class="patient-info">
                                        <div class="patient-avatar">
                                            <?php 
                                            $patientImage = !empty($row['patient_image']) ? $row['patient_image'] : 'default.png';
                                            ?>
                                            <img src="../../images/patients/<?php echo htmlspecialchars($patientImage); ?>" alt="">
                                        </div>
                                        <div class="patient-details">
                                            <span class="patient-name"><?php echo htmlspecialchars($row['patient_first_name'] . ' ' . $row['patient_last_name']); ?></span>
                                            <span class="patient-id">ID: P-<?php echo $row['patient_id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="doctor-info">
                                        <div class="doctor-avatar">
                                            <?php 
                                            $doctorImage = !empty($row['doctor_image']) ? $row['doctor_image'] : 'default.png';
                                            ?>
                                            <img src="../../images/doctors/<?php echo htmlspecialchars($doctorImage); ?>" alt="">
                                        </div>
                                        <div class="doctor-details">
                                            <span class="doctor-name">Dr. <?php echo htmlspecialchars($row['doctor_first_name'] . ' ' . $row['doctor_last_name']); ?></span>
                                            <span class="doctor-specialty"><?php echo htmlspecialchars($row['specialization']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-time">
                                        <span class="date"><?php echo date('F j, Y', strtotime($row['appointment_date'])); ?></span>
                                        <span class="time"><?php echo date('h:i A', strtotime($row['appointment_date'])); ?></span>
                                    </div>
                                </td>
                                <td><span class="status <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                                <td>
                                    <div class="action-icons">
                                        <div class="action-icon"><i class="fas fa-eye"></i></div>
                                        <div class="action-icon"><i class="fas fa-pen"></i></div>
                                        <div class="action-icon"><i class="fas fa-trash"></i></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing all the appointment
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

        //search
        document.getElementById('searchInput').addEventListener('input', function () {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const patientName = row.querySelector('.patient-name')?.textContent.toLowerCase() || '';
            const doctorName = row.querySelector('.doctor-name')?.textContent.toLowerCase() || '';
            const specialization = row.querySelector('.doctor-specialty')?.textContent.toLowerCase() || '';
            const date = row.querySelector('.date')?.textContent.toLowerCase() || '';
            const status = row.querySelector('.status')?.textContent.toLowerCase() || '';

            const matches = 
                patientName.includes(searchValue) ||
                doctorName.includes(searchValue) ||
                specialization.includes(searchValue) ||
                date.includes(searchValue) ||
                status.includes(searchValue);

            row.style.display = matches ? '' : 'none';
        });
    });


    </script>
</body>

</html>