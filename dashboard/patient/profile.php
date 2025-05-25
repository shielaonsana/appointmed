<?php
session_start();

require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Patient') {
    header("Location: ../../main-page/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$successMsg = $errorMsg = '';

// Handle profile update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip = $_POST['zip'] ?? '';
    $remove_image = isset($_POST['remove_image']);
    $profile_image = '';

    $baseImagePath = '../../images/patients/';
    $defaultImage = 'default.png';

    // Get current image
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentImage = $defaultImage;
    if ($row = $result->fetch_assoc()) {
        $currentImage = $row['profile_image'] ?: $defaultImage;
    }

    // Handle image removal
    if ($remove_image) {
        if ($currentImage !== $defaultImage && file_exists($baseImagePath . $currentImage)) {
            unlink($baseImagePath . $currentImage);
        }
        $profile_image = $defaultImage;
    }
    // Handle image upload
    elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['profile_image']['tmp_name'];
        $fileName = uniqid('profile_') . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $targetPath = $baseImagePath . $fileName;
        if (move_uploaded_file($tmpName, $targetPath)) {
            if ($currentImage !== $defaultImage && file_exists($baseImagePath . $currentImage)) {
                unlink($baseImagePath . $currentImage);
            }
            $profile_image = $fileName;
        } else {
            $errorMsg = 'Failed to upload image.';
            $profile_image = $currentImage;
        }
    } else {
        $profile_image = $currentImage;
    }

    // Update users table
    $full_name = $first_name . ' ' . $last_name;
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ?, gender = ?, date_of_birth = ?, address = ?, city = ?, state = ?, zip_code = ?, profile_image = ? WHERE user_id = ?");
    $stmt->bind_param("ssssssssssi", $full_name, $email, $phone, $gender, $dob, $address, $city, $state, $zip, $profile_image, $user_id);
    $stmt->execute();

    // Update patients table
    $stmt = $conn->prepare("UPDATE patients SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, email = ?, phone_number = ?, address = ?, city = ?, state = ?, zip_code = ? WHERE user_id = ?");
    $stmt->bind_param("ssssssssssi", $first_name, $last_name, $dob, $gender, $email, $phone, $address, $city, $state, $zip, $user_id);
    $stmt->execute();

    // Optionally update session variables if you use them
    $_SESSION['full_name'] = $full_name;
    $_SESSION['profile_image'] = $profile_image;

    $successMsg = 'Profile updated successfully!';
}

// Fetch patient's complete profile data (again, for display)
$query = "SELECT u.user_id, u.full_name, u.profile_image, u.account_type, 
                 u.email, u.phone_number, u.gender, u.date_of_birth,
                 p.first_name, p.last_name, p.date_of_birth AS patient_dob,
                 p.address, p.city, p.state, p.zip_code
          FROM users u
          LEFT JOIN patients p ON u.user_id = p.user_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $patientProfile = $result->fetch_assoc();
    $firstName = htmlspecialchars($patientProfile['first_name'] ?? '');
    $lastName = htmlspecialchars($patientProfile['last_name'] ?? '');
    $email = htmlspecialchars($patientProfile['email'] ?? '');
    $phone = htmlspecialchars($patientProfile['phone_number'] ?? '');
    $dob = htmlspecialchars($patientProfile['patient_dob'] ?? $patientProfile['date_of_birth'] ?? '');
    $gender = htmlspecialchars($patientProfile['gender'] ?? '');
    $address = htmlspecialchars($patientProfile['address'] ?? '');
    $city = htmlspecialchars($patientProfile['city'] ?? '');
    $state = htmlspecialchars($patientProfile['state'] ?? '');
    $zip = htmlspecialchars($patientProfile['zip_code'] ?? '');
    $imageFile = !empty($patientProfile['profile_image']) ? $patientProfile['profile_image'] : 'default.png';
    $imagePath = '../../images/patients/' . $imageFile;
    if (!file_exists($imagePath)) {
        $imagePath = '../../images/patients/default.png';
    }
    $fullName = htmlspecialchars($firstName . ' ' . $lastName);
} else {
    $imagePath = '../../images/patients/default.png';
    $firstName = $lastName = $email = $phone = $dob = $gender = $address = $city = $state = $zip = '';
    $fullName = 'Patient';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
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
                <a href="search.php" class="nav-item">
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
                <a href="profile.php" class="nav-item  active">
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
                        <h1>Edit Profile</h1>
                    </div>
                </div>

                <!-- Profile Settings Container -->
                <div class="profile-settings-container">
                    <!-- Settings Navigation -->
                    <div class="settings-nav">
                        <div class="settings-nav-item active" data-target="personal-info">Personal Information</div>
                    </div>

                    <!-- Settings Content -->
                    <div class="settings-content">
                        <!-- Personal Information Section -->
                        <div id="personal-info" class="settings-section active">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="avatar-upload">
                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Patient" class="current-avatar">
                                    <div class="avatar-actions">
                                        <label for="avatar-input" class="btn btn-outline">
                                            <i class="fas fa-camera"></i> Change Photo
                                        </label>
                                        <input type="file" id="avatar-input" name="profile_image" class="avatar-input" accept="image/*">
                                        <button class="btn btn-outline" name="remove_image" value="1" style="color: #ef4444;">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="first-name">First Name</label>
                                            <input type="text" id="first-name" name="first_name" class="form-control" value="<?php echo $firstName; ?>">
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="last-name">Last Name</label>
                                            <input type="text" id="last-name" name="last_name" class="form-control" value="<?php echo $lastName; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input type="email" id="email" name="email" class="form-control" value="<?php echo $email; ?>">
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="phone">Phone Number</label>
                                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $phone; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="dob">Date of Birth</label>
                                            <input type="date" id="dob" name="dob" class="form-control" value="<?php echo $dob; ?>">
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="gender">Gender</label>
                                            <select id="gender" name="gender" class="form-control">
                                                <option value="male" <?php if ($gender === 'male') echo 'selected'; ?>>Male</option>
                                                <option value="female" <?php if ($gender === 'female') echo 'selected'; ?>>Female</option>
                                                <option value="other" <?php if ($gender === 'other') echo 'selected'; ?>>Other</option>
                                                <option value="prefer-not" <?php if ($gender === 'prefer-not') echo 'selected'; ?>>Prefer not to say</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address" class="form-control" value="<?php echo $address; ?>">
                                </div>
                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="city">City</label>
                                            <input type="text" id="city" name="city" class="form-control" value="<?php echo $city; ?>">
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="state">State</label>
                                            <input type="text" id="state" name="state" class="form-control" value="<?php echo $state; ?>">
                                        </div>
                                    </div>
                                    <div class="form-col">
                                        <div class="form-group">
                                            <label for="zip">ZIP Code</label>
                                            <input type="text" id="zip" name="zip" class="form-control" value="<?php echo $zip; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button class="btn btn-outline" type="reset">Cancel</button>
                                    <button class="btn btn-primary" type="submit">Save Changes</button>
                                </div>
                                <?php if ($successMsg): ?>
                                    <div style="color: green; margin-top: 10px;"> <?php echo $successMsg; ?> </div>
                                <?php elseif ($errorMsg): ?>
                                    <div style="color: red; margin-top: 10px;"> <?php echo $errorMsg; ?> </div>
                                <?php endif; ?>
                            </form>
                            
                        </div>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        document.getElementById('menu-toggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        // Close sidebar when clicking outside on mobile
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

        // Responsive Sidebar Handling
        window.addEventListener('resize', function () {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });

        // Tab Switching Functionality
        const navItems = document.querySelectorAll('.settings-nav-item');
        if (navItems.length) {
            navItems.forEach(item => {
                item.addEventListener('click', function () {
                    // Switch active tabs
                    document.querySelectorAll('.settings-nav-item, .settings-section').forEach(el => {
                        el.classList.remove('active');
                    });
                    this.classList.add('active');
                    document.getElementById(this.dataset.target).classList.add('active');
                });
            });
        }

        // Avatar Upload Functionality
        const avatarInput = document.getElementById('avatar-input');
        const currentAvatar = document.querySelector('.current-avatar');
        const removeAvatarBtn = document.querySelector('.avatar-actions button');
        if (avatarInput) {
            avatarInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        currentAvatar.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        if (removeAvatarBtn && currentAvatar && avatarInput) {
            removeAvatarBtn.addEventListener('click', function (e) {
                e.preventDefault();
                currentAvatar.src = 'images/doctor-1.png'; // Default avatar
                avatarInput.value = '';
            });
        }

        // Specialty Tags Functionality
        const subspecialtiesInput = document.getElementById('subspecialties');
        const tagsContainer = document.querySelector('.specialty-tags');
        function createTag(text) {
            const tag = document.createElement('div');
            tag.className = 'specialty-tag';
            tag.innerHTML = `${text} <i class="fas fa-times"></i>`;
            tag.querySelector('i').addEventListener('click', () => tag.remove());
            return tag;
        }
        if (subspecialtiesInput && tagsContainer) {
            subspecialtiesInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && this.value.trim()) {
                    e.preventDefault();
                    tagsContainer.appendChild(createTag(this.value.trim()));
                    this.value = '';
                }
            });
        }
        // Initialize existing tags
        if (tagsContainer) {
            tagsContainer.querySelectorAll('.specialty-tag i').forEach(icon => {
                icon.addEventListener('click', function () {
                    this.parentElement.remove();
                });
            });
        }

        // Form Handling and Validation
        const btnPrimaryList = document.querySelectorAll('.btn-primary');
        if (btnPrimaryList.length) {
            btnPrimaryList.forEach(button => {
                button.addEventListener('click', async function (e) {
                    const form = this.closest('.settings-section');
                    // Simple Validation Example
                    if (form && form.id === 'personal-info') {
                        const email = form.querySelector('#email');
                        if (email && !validateEmail(email.value)) {
                            alert('Please enter a valid email address');
                            e.preventDefault(); // Only prevent if validation fails
                            return;
                        }
                    }
                    // Let the form submit normally so PHP can handle the update
                });
            });
        }

        // Cancel Button Functionality
        const btnOutlineList = document.querySelectorAll('.btn-outline');
        if (btnOutlineList.length) {
            btnOutlineList.forEach(button => {
                if (button.textContent.includes('Cancel')) {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();
                        const section = this.closest('.settings-section');
                        if (section) {
                            section.querySelectorAll('input, select, textarea').forEach(field => {
                                field.value = field.defaultValue;
                            });
                        }
                        // Reset avatar
                        if (currentAvatar && avatarInput) {
                            currentAvatar.src = 'images/doctor-1.png';
                            avatarInput.value = '';
                        }
                        // Reset tags
                        if (tagsContainer) {
                            tagsContainer.innerHTML = `
                            <div class="specialty-tag">
                                Interventional Cardiology <i class="fas fa-times"></i>
                            </div>
                            <div class="specialty-tag">
                                Electrophysiology <i class="fas fa-times"></i>
                            </div>
                        `;
                            // Reinitialize tag remove buttons
                            tagsContainer.querySelectorAll('.specialty-tag i').forEach(icon => {
                                icon.addEventListener('click', function () {
                                    this.parentElement.remove();
                                });
                            });
                        }
                    });
                }
            });
        }

        // Validation Helpers
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validatePhone(phone) {
            return /^\(\d{3}\) \d{3}-\d{4}$/.test(phone);
        }
    </script>
</body>

</html>