<?php
session_start();

require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Patient') {
    header("Location: ../../main-page/login.php");
    exit();
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

// Fetch doctors from the database
$doctors = [];
try {
    $query = "SELECT d.doctor_id, d.user_id, d.first_name, d.last_name, 
                     d.specialization, d.sub_specialties, d.availability,
                     u.profile_image
              FROM doctor_details d
              JOIN users u ON d.user_id = u.user_id
              ORDER BY d.last_name ASC, d.first_name ASC";
    
    $result = $conn->query($query);
    $doctors = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}

// Function to parse doctor availability
function parseDoctorAvailability($availabilityJson) {
    $defaultAvailability = [
        'working_days' => ['Monday', 'Wednesday', 'Friday'],
        'working_hours' => [
            'start_time' => '08:00',
            'end_time' => '17:00'
        ],
        'break_time' => [
            'start_time' => '',
            'duration' => ''
        ]
    ];
    
    if (empty($availabilityJson)) {
        return $defaultAvailability;
    }
    
    $availability = json_decode($availabilityJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($availability)) {
        return $defaultAvailability;
    }
    
    // Merge data without considering 'enabled' flag
    $merged = array_merge($defaultAvailability, $availability);
    
    // Always ensure break_time structure exists
    if (!isset($merged['break_time'])) {
        $merged['break_time'] = $defaultAvailability['break_time'];
    }
    
    return $merged;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Doctor</title>
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

        .red {
            background-color: rgba(187, 72, 72, 0.2);
            color: #e53e3e;
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

        .page-container {
            display: flex;
            gap: 30px;
            padding: 0px;
        }

        .sidebars {
            width: 200px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            height: fit-content;
        }

        .sidebars h3 {
            margin-bottom: 15px;
            color: #1F2937;
            font-size: 20px;
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #333;
        }

        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .noDoctorsMessage {
            display: none;
            margin-top: 20px;
            font-weight: 500px;
        }

        .doctors-content {
            flex: 1;
        }

        .doctors-cardss {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
        }

        .doctors-cards {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 50px;
        }

        .doctor-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            width: 300px;
            text-align: left;
            transition: transform 0.3s ease;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
        }

        .doctor-card img {
            width: 100%;
            height: auto;
            object-fit: cover;
            aspect-ratio: 1 / 1;
        }

        .doctor-info {
            padding: 20px;
        }

        .doctor-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .specialty {
            color: #3498db;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .rating {
            margin-bottom: 8px;
            color: #ffc107;
            font-size: 20px;
        }

        .rating span {
            color: #666;
            font-size: 15px;
        }

        .availability {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .btn-appoint {
            display: inline-block;
            text-align: center;
            background: #3498db;
            color: #fff;
            padding: 10px 0;
            width: 100%;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .btn-appoint:hover {
            background: #2980b9;
        }

        .hidden {
            display: none;
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
                <a href="search.php" class="nav-item active">
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
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search doctor">
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
                        <h1>Search Doctor</h1>
                    </div>
                </div>

                <div class="page-container">
                    <!-- Sidebar Filters -->
                    <div class="sidebars">
                        <h3>Filter By</h3>

                        <div class="filter-group">
                            <label for="specialtyFilter">Specialty</label>
                            <select id="specialtyFilter">
                                <option value="">All Specialties</option>
                                <option value="Cardiology">Cardiology</option>
                                <option value="Neurology">Neurology</option>
                                <option value="Pediatrics">Pediatrics</option>
                                <option value="Dermatology">Dermatology</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="availabilityFilter">Availability</label>
                            <select id="availabilityFilter">
                                <option value="">All Days</option>
                                <option value="Mon">Monday</option>
                                <option value="Tue">Tuesday</option>
                                <option value="Wed">Wednesday</option>
                                <option value="Thu">Thursday</option>
                                <option value="Fri">Friday</option>
                                <option value="Sat">Saturday</option>
                                <option value="Sun">Sunday</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="ratingFilter">Rating</label>
                            <select id="ratingFilter">
                                <option value="">All Ratings</option>
                                <option value="5">★★★★★</option>
                                <option value="4">★★★★</option>
                                <option value="3">★★★</option>
                                <option value="2">★★</option>
                                <option value="1">★</option>
                            </select>
                        </div>

                        <div id="noDoctorsMessage" class="noDoctorsMessage">
                            No available doctors.
                        </div>
                    </div>

                    <!-- Doctors List -->
                    <div class="doctors-content">
                        <div class="doctors-cardss">
                            <?php if (!empty($doctors)): ?>
                                <?php foreach ($doctors as $doctor): ?>
                                    <?php
                                    $availability = parseDoctorAvailability($doctor['availability'] ?? '');
                                    $workingDays = $availability['working_days'];
                                    $workingHours = $availability['working_hours'];
                                    
                                    // Generate random rating and reviews for demo
                                    $rating = rand(4, 5);
                                    $reviews = rand(100, 500);
                                    
                                    $imagePath = !empty($doctor['profile_image']) ? 
                                        '../../images/doctors/' . $doctor['profile_image'] : 
                                        '../../images/doctors/default.png';
                                    
                                    // Convert full day names to short codes
                                    $dayMap = [
                                        'Monday' => 'Mon',
                                        'Tuesday' => 'Tue',
                                        'Wednesday' => 'Wed',
                                        'Thursday' => 'Thu',
                                        'Friday' => 'Fri',
                                        'Saturday' => 'Sat',
                                        'Sunday' => 'Sun'
                                    ];
                                    $shortDays = array_map(function($day) use ($dayMap) {
                                        return $dayMap[$day] ?? $day;
                                    }, $workingDays);
                                    ?>
                                    
                                    <div class="doctor-card" 
                                        data-specialty="<?= htmlspecialchars($doctor['specialization']) ?>"
                                        data-availability="<?= htmlspecialchars(implode(',', $shortDays)) ?>"
                                        data-rating="<?= $rating ?>">
                                        
                                        <img src="<?= htmlspecialchars($imagePath) ?>" 
                                            alt="Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>">
                                        
                                        <div class="doctor-info">
                                            <h3>Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?></h3>
                                            <p class="specialty"><?= htmlspecialchars($doctor['specialization']) ?></p>
                                            
                                            <div class="rating">
                                                <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?>
                                                <span>(<?= $reviews ?> reviews)</span>
                                            </div>
                                            
                                            <p class="availability">
                                                <i class="fas fa-calendar-alt"></i> Available: <?= implode(', ', $workingDays) ?><br>
                                                <i class="fas fa-clock"></i> Hours: <?= date("g:i A", strtotime($workingHours['start_time'])) ?> - <?= date("g:i A", strtotime($workingHours['end_time'])) ?>
                                                <?php if (!empty($availability['break_time']['start_time']) && !empty($availability['break_time']['duration'])): ?>
                                                    <br><i class="fas fa-coffee"></i> Break: <?= date("g:i A", strtotime($availability['break_time']['start_time'])) ?> (<?= $availability['break_time']['duration'] ?> mins)
                                                <?php endif; ?>
                                            </p>
                                            
                                            <a href="book.php?doctor_id=<?= $doctor['doctor_id'] ?>" class="btn-appoint">
                                                Book Appointment
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-doctors-message">
                                    <p>No doctors found. Please check back later.</p>
                                </div>
                            <?php endif; ?>
                        </div> <!-- End of doctors-cardss -->
                    </div> <!-- End of doctors-content -->


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

        const specialtyFilter = document.getElementById('specialtyFilter');
        const availabilityFilter = document.getElementById('availabilityFilter');
        const ratingFilter = document.getElementById('ratingFilter');
        const doctorCards = document.querySelectorAll('.doctor-card');
        const noDoctorsMessage = document.getElementById('noDoctorsMessage');

        function filterDoctors() {
            const selectedSpecialty = specialtyFilter.value;
            const selectedAvailability = availabilityFilter.value;
            const selectedRating = ratingFilter.value;

            let anyVisible = false;

            doctorCards.forEach(card => {
                const specialty = card.getAttribute('data-specialty');
                const availability = card.getAttribute('data-availability');
                const rating = card.getAttribute('data-rating');

                const specialtyMatch = !selectedSpecialty || specialty === selectedSpecialty;
                const availabilityMatch = !selectedAvailability || availability.includes(selectedAvailability);
                const ratingMatch = !selectedRating || rating === selectedRating;

                if (specialtyMatch && availabilityMatch && ratingMatch) {
                    card.style.display = 'block';
                    anyVisible = true;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show or hide the "No available doctors" message
            noDoctorsMessage.style.display = anyVisible ? 'none' : 'block';
        }

        // Get search input element
        const searchInput = document.querySelector('.search-input');

        // Modified filter function with search functionality
        function filterDoctors() {
            const selectedSpecialty = specialtyFilter.value;
            const selectedAvailability = availabilityFilter.value;
            const selectedRating = ratingFilter.value;
            const searchQuery = searchInput.value.trim().toLowerCase();

            let anyVisible = false;

            doctorCards.forEach(card => {
                const specialty = card.getAttribute('data-specialty');
                const availability = card.getAttribute('data-availability');
                const rating = card.getAttribute('data-rating');
                const name = card.querySelector('.doctor-info h3').textContent.toLowerCase();
                const cardSpecialty = specialty.toLowerCase();

                const specialtyMatch = !selectedSpecialty || specialty === selectedSpecialty;
                const availabilityMatch = !selectedAvailability || availability.includes(selectedAvailability);
                const ratingMatch = !selectedRating || rating === selectedRating;
                const searchMatch = !searchQuery ||
                    name.includes(searchQuery) ||
                    cardSpecialty.includes(searchQuery);

                if (specialtyMatch && availabilityMatch && ratingMatch && searchMatch) {
                    card.style.display = 'block';
                    anyVisible = true;
                } else {
                    card.style.display = 'none';
                }
            });

            noDoctorsMessage.style.display = anyVisible ? 'none' : 'block';
        }

        // Add event listener for search input
        searchInput.addEventListener('input', filterDoctors);

        specialtyFilter.addEventListener('change', filterDoctors);
        availabilityFilter.addEventListener('change', filterDoctors);
        ratingFilter.addEventListener('change', filterDoctors);

        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const specialty = urlParams.get('specialty');
            const dateParam = urlParams.get('date');

            if (specialty && dateParam) {
                const date = new Date(dateParam);
                const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                const targetDay = days[date.getDay()];

                let anyVisible = false;

                document.querySelectorAll('.doctor-card').forEach(card => {
                    const cardSpecialty = card.dataset.specialty;
                    const availability = card.dataset.availability.split(',').map(d => d.trim());

                    if (cardSpecialty === specialty && availability.includes(targetDay)) {
                        card.style.display = 'block';
                        anyVisible = true;
                    } else {
                        card.style.display = 'none';
                    }
                });

                noDoctorsMessage.style.display = anyVisible ? 'none' : 'block';
            }
        });


    </script>
</body>

</html>