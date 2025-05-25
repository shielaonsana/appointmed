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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
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

        .no-slots {
            text-align: center;
            padding: 20px;
            grid-column: 1 / -1;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 10px 0;
        }

        .no-slots i {
            font-size: 2rem;
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .no-slots h4 {
            color: #343a40;
            margin-bottom: 5px;
        }

        .no-slots p {
            color: #6c757d;
            margin-bottom: 15px;
        }

        .no-slots .suggestion {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }

        /* Loading animation */
        .loading-slots {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        /* Animated no slots message */
        .no-slots-animated {
            text-align: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .no-slots-icon {
            font-size: 2.5rem;
            color: #ff6b6b;
            margin-bottom: 15px;
        }

        .no-slots-animated h4 {
            color: #343a40;
            margin-bottom: 5px;
        }

        .no-slots-animated p {
            color: #6c757d;
        }

        .available-days {
            margin: 15px 0;
        }

        .available-days p {
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .day-bubbles {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .day-bubble {
            background: #3498db;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .day-bubble.current-day {
            background: #e74c3c;
        }

        .btn-change-date {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 10px;
            transition: background-color 0.3s;
        }

        .btn-change-date:hover {
            background-color: #2980b9;
        }

        .btn-change-date i {
            margin-right: 5px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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


        /*book*/

        .booking-container {
            max-width: 100%;
            margin: 0px auto;
            background-color: #fff;
            border-radius: 10px;
            padding: 2rem;
        }

        /* Header styles */
        header {
            margin-bottom: 2rem;
        }


        /* Progress steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #e0e0e0;
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 20%;
        }

        .step span {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e0e0e0;
            color: #fff;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .step p {
            font-size: 0.85rem;
            color: #666;
            text-align: center;
        }

        .step.active span {
            background-color: #3498db;
        }

        .step.active p {
            color: #3498db;
            font-weight: 500;
        }

        /* Booking step sections */
        .booking-step {
            display: none;
        }

        .booking-step.active {
            display: block;
        }

        .booking-step h2 {
            font-size: 20px;
            color: #1f2937;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        /* Specialty cards */
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }

        .search-box input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
        }

        .search-box .clear-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            display: none;
        }

        .specialty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .specialty-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .specialty-card:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .specialty-card.selected {
            border-color: #3498db;
            background-color: rgba(52, 152, 219, 0.05);
        }

        .card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.1);
            margin-right: 1rem;
        }

        .card-icon i {
            font-size: 1.5rem;
            color: #3498db;
        }

        .card-content h3 {
            font-size: 16px;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }

        .card-content p {
            font-size: 0.85rem;
            color: #666;
        }

        .popular-tag {
            position: absolute;
            top: -10px;
            right: 10px;
            background-color: #e74c3c;
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
        }

        /* Doctor selection */
        .doctor-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .doctor-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .doctor-card:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .doctor-card.selected {
            border-color: #3498db;
            background-color: rgba(52, 152, 219, 0.05);
        }

        .doctor-card img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
        }

        .doctor-info h3 {
            font-size: 16px;
            margin-bottom: 0.25rem;
        }

        .doctor-info .rating {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
            font-size: 13px;
            color: #666;
        }

        .doctor-info .rating i {
            color: #f1c40f;
            margin-right: 5px;
        }

        .doctor-info p {
            font-size: 13px;

            color: #666;
        }

        /* Date and time selection */
        .date-selection h2 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .calendar {
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .nav-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
        }

        .month-year {
            font-size: 16px;
            font-weight: 500;
            color: #1f2937;
        }

        .weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .weekdays div {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-gap: 5px;
            padding: 0.5rem;
        }

        .day {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 40px;
            border-radius: 100%;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .day:hover:not(.empty):not(.disabled) {
            background-color: #e0e0e0;
        }

        .day.selected {
            background-color: #3498db;
            color: white;
        }

        .day.today {
            border: 1px solid #3498db;
            font-weight: 700;
        }

        .day.disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .day.empty {
            cursor: default;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 0.8rem;
            margin-bottom: 2rem;
            transition: opacity 0.3s ease;
        }

        .time-slot {
            padding: 0.6rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
            font-size: 0.9rem;
            background: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .time-slot:hover {
            border-color: #3498db;
        }

        .time-slot.selected {
            background-color: #3498db;
            border-color: #3498db;
            color: white;
        }

        .time-slot.disabled {
            color: #ccc;
            cursor: not-allowed;
            background-color: #f8f9fa;
        }

        /* Appointment Details (Step 3) */
        .appointment-summary-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .appointment-summary-card .change-link {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .appointment-field {
            margin-bottom: 1.5rem;
        }

        .appointment-field label {
            display: block;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .appointment-field textarea,
        .appointment-field input,
        .appointment-field select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
        }

        .appointment-field textarea:focus,
        .appointment-field input:focus,
        .appointment-field select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .appointment-field textarea {
            resize: vertical;
            min-height: 100px;
        }

        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
        }

        .radio-option input[type="radio"] {
            margin-right: 0.5rem;
            width: auto;
        }

        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: #3498db;
        }

        .file-upload i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #999;
        }

        .file-upload p {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .file-upload .supported-formats {
            font-size: 0.8rem;
            color: #999;
        }

        .file-upload-status {
            margin-top: 1rem;
            padding: 0.5rem;
            border-radius: 4px;
            display: none;
        }

        /* File list styles */
        #file-list {
            margin-top: 1rem;
            text-align: left;
        }

        .file-list-header {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .file-list-items {
            border: 1px solid #eee;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #eee;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-grow: 1;
            min-width: 0;
        }

        .file-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-grow: 1;
        }

        .file-size {
            color: #666;
            font-size: 0.9em;
        }

        .remove-file {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 0.25rem;
            margin-left: 0.5rem;
        }

        .remove-file:hover {
            color: #c0392b;
        }

        /* Progress bar styles */
        #progress-container {
            width: 100%;
            background-color: #f1f1f1;
            margin-top: 1rem;
            border-radius: 4px;
            overflow: hidden;
            display: none;
        }

        #progress-bar {
            height: 6px;
            background-color: #4CAF50;
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Status messages */
        #upload-status {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: 4px;
            display: none;
        }

        .upload-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .upload-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .browse-btn {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
        }

        .browse-btn:hover {
            background-color: #e0e0e0;
        }

        /* Patient Information (Step 4) */
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-column {
            flex: 1;
        }

        .form-field {
            margin-bottom: 1.5rem;
        }

        .form-field label {
            display: block;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .form-field input,
        .form-field select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
        }

        .form-field input:focus,
        .form-field select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        #current-medications {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
            min-height: 100px;
            resize: vertical;
        }

        #current-medications:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .medical-history {
            margin-bottom: 1.5rem;
        }

        .medical-history h3 {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
        }

        .checkbox-option input[type="checkbox"] {
            margin-right: 0.5rem;
            width: auto;
        }

        .save-info-option {
            display: flex;
            align-items: center;
            margin-top: 1.5rem;
        }

        .save-info-option input[type="checkbox"] {
            margin-right: 0.5rem;
            width: auto;
        }

        /* Confirmation (Step 5) */
        .confirmation-section {
            margin-bottom: 2rem;
        }

        .confirmation-section h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row {
            display: flex;
            margin-bottom: 0.8rem;
        }

        .info-label {
            width: 40%;
            color: #666;
            font-size: 0.9rem;
        }

        .info-value {
            width: 60%;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .important-notes {
            margin-bottom: 2rem;
        }

        .important-notes h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .note-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }

        .note-item i {
            color: #3498db;
            margin-right: 0.8rem;
            margin-top: 0.2rem;
        }

        .confirmation-checkbox {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .confirmation-checkbox input[type="checkbox"] {
            margin-right: 0.8rem;
            margin-top: 0.2rem;
        }

        /* Navigation buttons */
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-back,
        .btn-next,
        .btn-confirm {
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-back {
            background: none;
            border: 1px solid #ddd;
            color: #666;
        }

        .btn-back:hover {
            background-color: #f8f9fa;
        }

        .btn-next,
        .btn-confirm {
            background-color: #3498db;
            border: none;
            color: white;
        }

        .btn-next:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .btn-next:hover:not(:disabled),
        .btn-confirm:hover {
            background-color: #2980b9;
        }

        .btn-confirm {
            background-color: #2ecc71;
        }

        .btn-confirm:hover {
            background-color: #27ae60;
        }

        /* Loading and empty states */
        .loading-spinner, .no-doctors, .error-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
            grid-column: 1 / -1;
        }

        .loading-spinner i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #3498db;
        }

        .no-doctors i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #666;
        }

        .error-message i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #e74c3c;
        }

        .doctor-card .experience {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .booking-container {
                padding: 1.5rem;
                margin: 1rem;
            }

            .specialty-grid,
            .doctor-selection {
                grid-template-columns: 1fr;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .step p {
                display: none;
            }

            .progress-steps::before {
                top: 15px;
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
                <a href="book.php" class="nav-item active">
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
                        <h1>Book Appointment</h1>
                    </div>
                </div>
                <div class="booking-container">
                    <header>
                        <div class="progress-steps">
                            <div class="step active" data-step="1">
                                <span>1</span>
                                <p>Specialty</p>
                            </div>
                            <div class="step" data-step="2">
                                <span>2</span>
                                <p>Doctor & Time</p>
                            </div>
                            <div class="step" data-step="3">
                                <span>3</span>
                                <p>Details</p>
                            </div>
                            <div class="step" data-step="4">
                                <span>4</span>
                                <p>Patient Info</p>
                            </div>
                            <div class="step" data-step="5">
                                <span>5</span>
                                <p>Confirm</p>
                            </div>
                        </div>
                    </header>

                    <main>
                        <!-- Step 1: Specialty Selection -->
                        <section class="booking-step active" id="step1">
                            <h2>Select a Medical Specialty</h2>

                            <!-- Enhanced Search Box -->
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search specialties..." id="specialtySearch">
                                <button class="clear-search" aria-label="Clear search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <!-- Enhanced Specialty Grid -->
                            <div class="specialty-grid">
                                <div class="specialty-card" data-specialty="Cardiology">
                                    <div class="card-icon">
                                        <i class="fas fa-heartbeat"></i>
                                    </div>
                                    <div class="card-content">
                                        <h3>Cardiology</h3>
                                        <p>Heart and cardiovascular system</p>
                                    </div>
                                </div>

                                <div class="specialty-card" data-specialty="Neurology">
                                    <div class="card-icon">
                                        <i class="fas fa-brain"></i>
                                    </div>
                                    <div class="card-content">
                                        <h3>Neurology</h3>
                                        <p>Brain and nervous system</p>
                                    </div>
                                </div>

                                <div class="specialty-card" data-specialty="Pediatrics">
                                    <div class="card-icon">
                                        <i class="fas fa-baby"></i>
                                    </div>
                                    <div class="card-content">
                                        <h3>Pediatrics</h3>
                                        <p>Medical care for infants and children</p>
                                    </div>
                                </div>

                                <div class="specialty-card" data-specialty="Dermatology">
                                    <div class="card-icon">
                                        <i class="fas fa-allergies"></i>
                                    </div>
                                    <div class="card-content">
                                        <h3>Dermatology</h3>
                                        <p>Skin, hair and nail health</p>
                                        <div class="popular-tag">Trending</div>
                                    </div>
                                </div>

                                <div class="specialty-card" data-specialty="Laboratory">
                                    <div class="card-icon">
                                        <i class="fas fa-vials"></i>
                                    </div>
                                    <div class="card-content">
                                        <h3>Laboratory Services</h3>
                                        <p>Diagnostic testing and analysis</p>
                                        <div class="popular-tag">Coming Soon!</div>
                                    </div>
                                </div>

                                <div class="specialty-card" data-specialty="Pharmacy">
                                    <div class="card-icon">
                                        <i class="fas fa-prescription-bottle-alt"></i>
                                    </div>
                                    <div class="card-content">
                                        <h3>Pharmacy</h3>
                                        <p>Medicines and prescription support</p>
                                        <div class="popular-tag">Coming Soon!</div>
                                    </div>
                                </div>
                            </div>

                            <div class="navigation-buttons">
                                <button class="btn-next" disabled>Continue</button>
                            </div>
                        </section>

                        <!-- Step 2: Doctor & Time Selection -->
                        <section class="booking-step" id="step2">
                            <h2>Select Doctor and Appointment Time</h2>
                            <div class="doctor-selection">

                            </div>

                            <div class="date-selection">
                                <h2>Select Date</h2>
                                <div class="calendar">
                                    <div class="calendar-header">
                                        <button class="nav-btn prev-month">&lt;</button>
                                        <h4 class="month-year">May 2025</h4>
                                        <button class="nav-btn next-month">&gt;</button>
                                    </div>
                                    <div class="weekdays">
                                        <div>Sun</div>
                                        <div>Mon</div>
                                        <div>Tue</div>
                                        <div>Wed</div>
                                        <div>Thu</div>
                                        <div>Fri</div>
                                        <div>Sat</div>
                                    </div>
                                    <div class="days-grid"></div>
                                </div>

                                <h2>Available Times</h2>
                                <div class="time-slots">
                                    <button class="time-slot">9:00 AM</button>
                                    <button class="time-slot">9:30 AM</button>
                                    <button class="time-slot">10:00 AM</button>
                                    <button class="time-slot">10:30 AM</button>
                                    <button class="time-slot">11:00 AM</button>
                                    <button class="time-slot">11:30 AM</button>
                                    <button class="time-slot">1:00 PM</button>
                                    <button class="time-slot">1:30 PM</button>
                                    <button class="time-slot">2:00 PM</button>
                                    <button class="time-slot">2:30 PM</button>
                                    <button class="time-slot">3:00 PM</button>
                                    <button class="time-slot">3:30 PM</button>
                                    <button class="time-slot">4:00 PM</button>
                                    <button class="time-slot">4:30 PM</button>
                                    <button class="time-slot">5:00 PM</button>
                                </div>
                            </div>

                            <div class="navigation-buttons">
                                <button class="btn-back">Back</button>
                                <button class="btn-next">Continue</button>
                            </div>
                        </section>

                        <!-- Step 3: Appointment Details -->
                        <section class="booking-step" id="step3">
                            <h2>Appointment Details</h2>

                            <div class="appointment-summary-card">
                                <div class="doctor-time-info">
                                    <h3>Dr. Sarah Johnson - Cardiologist</h3>
                                    <p>Thursday, April 25, 2025 at 11:00 AM</p>
                                </div>
                                <a href="#" class="change-link">Change</a>
                            </div>

                            <div class="appointment-field">
                                <label for="visit-reason">Reason for Visit</label>
                                <textarea id="visit-reason"
                                    placeholder="Please describe your symptoms or reason for this appointment..."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-column">
                                    <div class="appointment-field">
                                        <label for="insurance-provider">Insurance Provider</label>
                                        <select id="insurance-provider">
                                            <option value="" selected disabled>Select provider</option>
                                            <option value="blue-cross">Blue Cross Blue Shield</option>
                                            <option value="aetna">Aetna</option>
                                            <option value="cigna">Cigna</option>
                                            <option value="united">UnitedHealthcare</option>
                                            <option value="medicare">Medicare</option>
                                            <option value="medicaid">Medicaid</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="appointment-field">
                                        <label for="policy-number">Policy Number</label>
                                        <input type="text" id="policy-number" placeholder="Enter your policy number">
                                    </div>
                                </div>
                            </div>

                            <div class="appointment-field">
                                <label>Is this your first visit?</label>
                                <div class="radio-group">
                                    <div class="radio-option">
                                        <input type="radio" id="first-visit-yes" name="first-visit" value="yes">
                                        <label for="first-visit-yes">Yes</label>
                                    </div>
                                    <div class="radio-option">
                                        <input type="radio" id="first-visit-no" name="first-visit" value="no">
                                        <label for="first-visit-no">No</label>
                                    </div>
                                </div>
                            </div>

                            <div class="appointment-field">
                                <label for="additional-notes">Additional Notes (Optional)</label>
                                <textarea id="additional-notes"
                                    placeholder="Any additional information you'd like to share with the doctor..."></textarea>
                            </div>

                            <div class="appointment-field">
                                <label>Upload Documents (Optional)</label>
                                <div class="file-upload" id="drop-area">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Drag and drop files here, or <span id="browse-text">click to browse</span></p>
                                    <div class="supported-formats">Supported formats: PDF, JPG, PNG (Max 10MB)</div>
                                    <input type="file" id="file-input" multiple accept=".pdf,.jpg,.jpeg,.png"
                                        style="display:none;">
                                </div>
                                <div id="file-list"></div>
                                <div id="progress-container">
                                    <div id="progress-bar"></div>
                                </div>
                            </div>

                            <div class="navigation-buttons">
                                <button class="btn-back">Previous</button>
                                <button class="btn-next">Continue</button>
                            </div>
                        </section>

                        <!-- Step 4: Patient Information -->
                        <section class="booking-step" id="step4">
                            <h2>Patient Information</h2>

                            <div class="form-row">
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="first-name">First Name</label>
                                        <input type="text" id="first-name" placeholder="Enter first name">
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="last-name">Last Name</label>
                                        <input type="text" id="last-name" placeholder="Enter last name">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="date-of-birth">Date of Birth</label>
                                        <input type="date" id="date-of-birth" placeholder="MM/DD/YYYY">
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="gender">Gender</label>
                                        <select id="gender">
                                            <option value="" selected disabled>Select gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="non-binary">Non-binary</option>
                                            <option value="other">Other</option>
                                            <option value="prefer-not-to-say">Prefer not to say</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" placeholder="Enter email address">
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" placeholder="Enter phone number">
                                    </div>
                                </div>
                            </div>

                            <div class="form-field">
                                <label for="address">Address</label>
                                <input type="text" id="address" placeholder="Street address">
                            </div>

                            <div class="form-row">
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="city">City</label>
                                        <input type="text" id="city" placeholder="City">
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="state">State</label>
                                        <input type="text" id="state" placeholder="State">
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="zip">Zip Code</label>
                                        <input type="text" id="zip" placeholder="Zip code">
                                    </div>
                                </div>
                            </div>

                            <div class="medical-history">
                                <h3>Medical History</h3>
                                <p>Select all that apply:</p>
                                <div class="checkbox-grid">
                                    <div class="checkbox-option">
                                        <input type="checkbox" id="high-blood-pressure">
                                        <label for="high-blood-pressure">High Blood Pressure</label>
                                    </div>
                                    <div class="checkbox-option">
                                        <input type="checkbox" id="diabetes">
                                        <label for="diabetes">Diabetes</label>
                                    </div>
                                    <div class="checkbox-option">
                                        <input type="checkbox" id="heart-disease">
                                        <label for="heart-disease">Heart Disease</label>
                                    </div>
                                    <div class="checkbox-option">
                                        <input type="checkbox" id="asthma">
                                        <label for="asthma">Asthma</label>
                                    </div>
                                    <div class="checkbox-option">
                                        <input type="checkbox" id="cancer">
                                        <label for="cancer">Cancer</label>
                                    </div>
                                    <div class="checkbox-option">
                                        <input type="checkbox" id="allergies">
                                        <label for="allergies">Allergies</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-field">
                                <label for="current-medications">Current Medications</label>
                                <textarea id="current-medications"
                                    placeholder="List any medications you are currently taking..."></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="emergency-contact">Emergency Contact Name</label>
                                        <input type="text" id="emergency-contact" placeholder="Full name">
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-field">
                                        <label for="emergency-relationship">Relationship</label>
                                        <input type="text" id="emergency-relationship" placeholder="Relationship">
                                    </div>
                                </div>
                            </div>

                            <div class="form-field">
                                <label for="emergency-phone">Emergency Contact Phone</label>
                                <input type="tel" id="emergency-phone" placeholder="Phone number">
                            </div>

                            <div class="save-info-option">
                                <input type="checkbox" id="save-info" checked>
                                <label for="save-info">Save this information for future appointments</label>
                            </div>

                            <div class="navigation-buttons">
                                <button class="btn-back">Previous</button>
                                <button class="btn-next">Continue</button>
                            </div>
                        </section>

                        <!-- Step 5: Confirmation -->
                        <section class="booking-step" id="step5">
                            <h2>Review & Confirm</h2>

                            <div class="confirmation-section">
                                <h3>Appointment Summary</h3>
                                <div class="info-row">
                                    <div class="info-label">Doctor:</div>
                                    <div class="info-value" id="confirm-doctor"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Specialty:</div>
                                    <div class="info-value" id="confirm-specialty"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date & Time:</div>
                                    <div class="info-value" id="confirm-datetime"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Reason for Visit:</div>
                                    <div class="info-value" id="confirm-reason"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Insurance Provider:</div>
                                    <div class="info-value" id="confirm-insurance-provider"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Policy Number:</div>
                                    <div class="info-value" id="confirm-policy-number"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">First Visit:</div>
                                    <div class="info-value" id="confirm-first-visit"></div>
                                </div>
                                <div class="info-row" id="uploaded-files-row">
                                    <div class="info-label">Uploaded Files:</div>
                                    <div class="info-value" id="confirm-uploaded-files"></div>
                                </div>
                            </div>

                            <div class="confirmation-section">
                                <h3>Patient Information</h3>
                                <div class="info-row">
                                    <div class="info-label">Name:</div>
                                    <div class="info-value" id="confirm-patient-name"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date of Birth:</div>
                                    <div class="info-value" id="confirm-dob"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Gender:</div>
                                    <div class="info-value" id="confirm-gender"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Email:</div>
                                    <div class="info-value" id="confirm-email"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value" id="confirm-phone"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Address:</div>
                                    <div class="info-value" id="confirm-address"></div>
                                </div>
                            </div>

                            <div class="confirmation-section">
                                <h3>Medical History</h3>
                                <div class="info-row">
                                    <div class="info-label">Conditions:</div>
                                    <div class="info-value" id="confirm-conditions"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Current Medications:</div>
                                    <div class="info-value" id="confirm-medications"></div>
                                </div>
                            </div>

                            <div class="confirmation-section">
                                <h3>Emergency Contact</h3>
                                <div class="info-row">
                                    <div class="info-label">Name:</div>
                                    <div class="info-value" id="confirm-emergency-name"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Relationship:</div>
                                    <div class="info-value" id="confirm-emergency-relationship"></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Phone:</div>
                                    <div class="info-value" id="confirm-emergency-phone"></div>
                                </div>
                            </div>

                            <div class="important-notes">
                                <h3>Important Notes</h3>
                                <div class="note-item">
                                    <i class="fas fa-circle-info"></i>
                                    <div>Please arrive 15 minutes before your scheduled appointment time.</div>
                                </div>
                                <div class="note-item">
                                    <i class="fas fa-circle-info"></i>
                                    <div>Bring your insurance card and a valid photo ID.</div>
                                </div>
                                <div class="note-item">
                                    <i class="fas fa-circle-info"></i>
                                    <div>If you need to cancel or reschedule, please do so at least 24 hours in advance.
                                    </div>
                                </div>
                            </div>

                            <div class="confirmation-checkbox">
                                <input type="checkbox" id="confirm-info">
                                <label for="confirm-info">I confirm that all the information provided is accurate and
                                    complete.</label>
                            </div>

                            <div class="navigation-buttons">
                                <button class="btn-back">Previous</button>
                                <button class="btn-confirm">Confirm Appointment</button>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </div>
    </div>

   <script>
    // Global Variables
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
    let currentStep = 1;
    let selectedSpecialty = null;
    let selectedDoctorId = null;
    let selectedDoctorName = null;
    let selectedDate = null;
    let selectedTime = null;
    let selectedDoctorAvailability = null;
    let uploadedFiles = [];
    let appointmentDuration = 30; // Default fallback in minutes

    // DOM Content Loaded
    document.addEventListener('DOMContentLoaded', function() {
        initSidebar();
        initBookingSystem();
    });

    // 1. SIDEBAR FUNCTIONALITY
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menu-toggle');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !menuToggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        });
    }

    // 2. BOOKING SYSTEM CORE
    function initBookingSystem() {
        initStepNavigation();
        initSpecialtySelection();
        initCalendar();
        initFileUpload();
    }

    // 3. STEP NAVIGATION
    function initStepNavigation() {
        const steps = document.querySelectorAll('.booking-step');
        const progressSteps = document.querySelectorAll('.progress-steps .step');
        const nextButtons = document.querySelectorAll('.btn-next');
        const backButtons = document.querySelectorAll('.btn-back');
        const confirmButton = document.querySelector('.btn-confirm');

        function updateActiveStep(stepNumber) {
            steps.forEach(step => step.classList.remove('active'));
            progressSteps.forEach(step => step.classList.remove('active'));
            document.getElementById(`step${stepNumber}`).classList.add('active');

            progressSteps.forEach(step => {
                if (parseInt(step.getAttribute('data-step')) <= stepNumber) {
                    step.classList.add('active');
                }
            });

            currentStep = stepNumber;
        }

        nextButtons.forEach(button => {
            button.addEventListener('click', async function() {
                if (currentStep === 1) {
                    const success = await loadDoctorsBySpecialty();
                    if (success) updateActiveStep(2);
                } 
                else if (currentStep === 2) {
                    if (!validateStep2()) return;
                    updateAppointmentSummary();
                    updateActiveStep(3);
                }
                else if (currentStep === 4) {
                    updateConfirmationPage();
                    updateActiveStep(5);
                }
                else if (currentStep < 5) {
                    updateActiveStep(currentStep + 1);
                }
            });
        });

        backButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (currentStep > 1) updateActiveStep(currentStep - 1);
            });
        });

        if (confirmButton) {
            confirmButton.addEventListener('click', submitAppointmentData);
        }
    }

    function validateStep2() {
        if (!selectedDoctorId) {
            alert('Please select a doctor');
            return false;
        }
        if (!selectedDate) {
            alert('Please select a date');
            return false;
        }
        if (!selectedTime) {
            alert('Please select a time slot');
            return false;
        }
        return true;
    }

    // 4. SPECIALTY SELECTION
    function initSpecialtySelection() {
        const specialtyCards = document.querySelectorAll('.specialty-card');
        const specialtySearch = document.getElementById('specialtySearch');
        const clearSearchBtn = document.querySelector('.clear-search');

        specialtyCards.forEach(card => {
            card.addEventListener('click', function() {
                specialtyCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedSpecialty = this.getAttribute('data-specialty');
                document.querySelector('#step1 .btn-next').disabled = false;
            });
        });

        if (specialtySearch) {
            specialtySearch.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                specialtyCards.forEach(card => {
                    const matches = card.getAttribute('data-specialty').toLowerCase().includes(term) ||
                                card.querySelector('h3').textContent.toLowerCase().includes(term) ||
                                card.querySelector('p').textContent.toLowerCase().includes(term);
                    card.style.display = matches ? 'flex' : 'none';
                });
                clearSearchBtn.style.display = this.value ? 'block' : 'none';
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                specialtySearch.value = '';
                specialtyCards.forEach(card => card.style.display = 'flex');
                this.style.display = 'none';
            });
        }
    }

    // 5. DOCTOR SELECTION
    async function loadDoctorsBySpecialty() {
        if (!selectedSpecialty) {
            alert('Please select a specialty first');
            return false;
        }

        const container = document.querySelector('.doctor-selection');
        
        container.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading doctors...</p>
            </div>`;

        try {
            const response = await fetch(`get_doctors.php?specialty=${encodeURIComponent(selectedSpecialty)}`);
            if (!response.ok) throw new Error('Failed to fetch doctors');
            
            const doctors = await response.json();
            displayDoctors(doctors);
            return true;
        } catch (error) {
            console.error('Error:', error);
            container.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Failed to load doctors. Please try again.</p>
                </div>`;
            return false;
        }
    }

    function displayDoctors(doctors) {
        const container = document.querySelector('.doctor-selection');
        container.innerHTML = '';

        if (!doctors || doctors.length === 0) {
            container.innerHTML = `
                <div class="no-doctors">
                    <i class="fas fa-user-md"></i>
                    <p>No doctors available for ${selectedSpecialty}</p>
                </div>`;
            return;
        }

        doctors.forEach(doctor => {
            let imageUrl = '';
            
            if (doctor.profile_image) {
                if (doctor.profile_image.startsWith('http')) {
                    imageUrl = doctor.profile_image;
                } else if (doctor.profile_image.startsWith('images/')) {
                    imageUrl = `../../${doctor.profile_image}`;
                } else {
                    imageUrl = `../../images/doctors/${doctor.profile_image}`;
                }
            } else {
                imageUrl = '../../images/doctors/default.png';
            }

            let availability = {
                working_hours: { start_time: "08:00", end_time: "17:00" },
                working_days: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                appointment_duration: "30",
                break_time: { enabled: false }
            };
            
            try {
                if (doctor.availability) {
                    if (typeof doctor.availability === 'object') {
                        availability = doctor.availability;
                    } else if (typeof doctor.availability === 'string') {
                        availability = JSON.parse(doctor.availability);
                    }
                }
            } catch (e) {
                console.error('Error processing availability:', e);
            }

            const card = document.createElement('div');
            card.className = 'doctor-card';
            card.dataset.doctorId = doctor.doctor_id;
            
            card.innerHTML = `
                <div class="doctor-image-container">
                    <img src="${imageUrl}" 
                        alt="Dr. ${doctor.first_name} ${doctor.last_name}"
                        onerror="this.src='../../images/doctors/default.png'">
                </div>
                <div class="doctor-info">
                    <h3>Dr. ${doctor.first_name} ${doctor.last_name}</h3>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <span>${doctor.rating || '5.0'} (${doctor.review_count || '0'} reviews)</span>
                    </div>
                    <p class="specialty">${doctor.specialization}</p>
                    ${doctor.years_of_experience ? 
                        `<p class="experience"><i class="fas fa-briefcase"></i> ${doctor.years_of_experience} years experience</p>` 
                        : ''}
                </div>
            `;

            card.addEventListener('click', function() {
                document.querySelectorAll('.doctor-card').forEach(c => {
                    c.classList.remove('selected');
                });
                this.classList.add('selected');
                selectedDoctorId = this.dataset.doctorId;
                selectedDoctorName = `Dr. ${doctor.first_name} ${doctor.last_name}`;
                selectedDoctorAvailability = availability;
                appointmentDuration = parseInt(availability.appointment_duration) || 30;
                
                if (selectedDate) {
                    updateTimeSlotsForSelectedDate();
                }
            });

            container.appendChild(card);
        });
    }

    // 6. CALENDAR & TIME SLOTS
    function initCalendar() {
        if (!document.querySelector('.calendar')) return;

        const today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();

        function updateCalendar() {
            const daysGrid = document.querySelector('.days-grid');
            daysGrid.innerHTML = '';

            document.querySelector('.month-year').textContent = `${monthNames[currentMonth]} ${currentYear}`;

            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                daysGrid.appendChild(createDayElement('', 'empty'));
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = createDayElement(day, 'day');
                const currentDate = new Date(currentYear, currentMonth, day);
                
                if (currentDate < new Date(today.getFullYear(), today.getMonth(), today.getDate())) {
                    dayElement.classList.add('disabled');
                } else {
                    dayElement.addEventListener('click', function() {
                        document.querySelectorAll('.day').forEach(d => d.classList.remove('selected'));
                        this.classList.add('selected');
                        selectedDate = `${monthNames[currentMonth]} ${day}, ${currentYear}`;
                        updateTimeSlotsForSelectedDate();
                    });
                }

                if (currentYear === today.getFullYear() && currentMonth === today.getMonth() && day === today.getDate()) {
                    dayElement.classList.add('today');
                }

                daysGrid.appendChild(dayElement);
            }
        }

        function createDayElement(content, className) {
            const day = document.createElement('div');
            day.className = className;
            day.textContent = content;
            return day;
        }

        document.querySelector('.prev-month').addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            updateCalendar();
        });

        document.querySelector('.next-month').addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            updateCalendar();
        });

        updateCalendar();
    }

    function updateTimeSlotsForSelectedDate() {
        const timeSlotsContainer = document.querySelector('.time-slots');
        timeSlotsContainer.innerHTML = '';
        
        if (!selectedDoctorAvailability || !selectedDate) {
            timeSlotsContainer.innerHTML = `
                <div class="no-slots">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Please select a doctor and date</p>
                </div>`;
            return;
        }
        
        // Parse the selected date (e.g., "May 21, 2025")
        const dateParts = selectedDate.split(' ');
        if (dateParts.length !== 3) {
            console.error('Invalid date format:', selectedDate);
            return;
        }

        const monthName = dateParts[0];
        const dayNumber = parseInt(dateParts[1].replace(',', ''));
        const year = parseInt(dateParts[2]);
        const monthIndex = monthNames.indexOf(monthName);
        
        if (monthIndex === -1) {
            console.error('Invalid month name:', monthName);
            return;
        }

        const dateObj = new Date(year, monthIndex, dayNumber);
        const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
        
        // Check if doctor works on this day
        if (!selectedDoctorAvailability.working_days.includes(dayName)) {
            timeSlotsContainer.innerHTML = `
                <div class="no-slots">
                    <i class="fas fa-calendar-times"></i>
                    <h4>Not Available on ${dayName}s</h4>
                    <p>Dr. ${selectedDoctorName.replace('Dr. ', '')} doesn't see patients on ${dayName}s</p>
                    
                    <div class="available-days">
                        <p>Available days:</p>
                        <div class="day-bubbles">
                            ${selectedDoctorAvailability.working_days.map(day => `
                                <span class="day-bubble ${day === dayName ? 'current-day' : ''}">
                                    ${day.substring(0, 3)}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                </div>`;
            return;
        }
        
        // Generate time slots
        const startTime = selectedDoctorAvailability.working_hours.start_time || '08:00';
        const endTime = selectedDoctorAvailability.working_hours.end_time || '17:00';
        const slots = generateTimeSlots(startTime, endTime, appointmentDuration);
        
        // Apply break time if enabled
        const finalSlots = selectedDoctorAvailability.break_time?.enabled ?
            filterBreakTimeSlots(slots, selectedDoctorAvailability.break_time) :
            slots;
        
        // Display available slots
        if (finalSlots.length === 0) {
            timeSlotsContainer.innerHTML = `
                <div class="no-slots">
                    <i class="fas fa-clock"></i>
                    <h4>Fully Booked</h4>
                    <p>No available time slots for ${dayName}</p>
                </div>`;
            return;
        }
        
        finalSlots.forEach(slot => {
            const slotElement = document.createElement('button');
            slotElement.className = 'time-slot';
            slotElement.textContent = slot;
            slotElement.addEventListener('click', function() {
                document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                this.classList.add('selected');
                selectedTime = this.textContent;
            });
            timeSlotsContainer.appendChild(slotElement);
        });
    }

    function generateTimeSlots(start, end, duration) {
        const slots = [];
        const [startHour, startMinute] = start.split(':').map(Number);
        const [endHour, endMinute] = end.split(':').map(Number);
        
        let currentHour = startHour;
        let currentMinute = startMinute;
        
        while (currentHour < endHour || (currentHour === endHour && currentMinute < endMinute)) {
            const period = currentHour >= 12 ? 'PM' : 'AM';
            const displayHour = currentHour > 12 ? currentHour - 12 : currentHour;
            const timeString = `${displayHour}:${currentMinute.toString().padStart(2, '0')} ${period}`;
            
            slots.push(timeString);
            
            currentMinute += duration;
            if (currentMinute >= 60) {
                currentMinute -= 60;
                currentHour += 1;
            }
        }
        
        return slots;
    }

    function filterBreakTimeSlots(slots, breakTime) {
        if (!breakTime.enabled) return slots;
        
        const [breakStartHour, breakStartMinute] = breakTime.start_time.split(':').map(Number);
        const breakDuration = parseInt(breakTime.duration) || 60;
        const breakEndHour = breakStartHour + Math.floor(breakDuration / 60);
        const breakEndMinute = breakStartMinute + (breakDuration % 60);
        
        return slots.filter(slot => {
            const timePart = slot.split(' ')[0];
            const [slotHour, slotMinute] = timePart.split(':').map(Number);
            const isPM = slot.includes('PM') && slotHour !== 12;
            const slot24Hour = isPM ? slotHour + 12 : slotHour;
            
            return !(
                (slot24Hour > breakStartHour || (slot24Hour === breakStartHour && slotMinute >= breakStartMinute)) &&
                (slot24Hour < breakEndHour || (slot24Hour === breakEndHour && slotMinute < breakEndMinute))
            );
        });
    }

    // 7. APPOINTMENT SUMMARY
    function updateAppointmentSummary() {
        const summaryCard = document.querySelector('.appointment-summary-card .doctor-time-info');
        
        if (selectedDoctorName && selectedDate && selectedTime) {
            summaryCard.innerHTML = `
                <h3>${selectedDoctorName} - ${selectedSpecialty || 'Specialty'}</h3>
                <p>${selectedDate} at ${selectedTime}</p>
            `;
        }
    }

    // 8. FILE UPLOAD
    function initFileUpload() {
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('file-input');
        const fileList = document.getElementById('file-list');

        dropArea.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', handleFiles);
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
            dropArea.addEventListener(event, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(event => {
            dropArea.addEventListener(event, highlight, false);
        });

        ['dragleave', 'drop'].forEach(event => {
            dropArea.addEventListener(event, unhighlight, false);
        });

        dropArea.addEventListener('drop', handleDrop, false);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight() {
            dropArea.classList.add('highlight');
        }

        function unhighlight() {
            dropArea.classList.remove('highlight');
        }

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            handleFiles({ target: { files } });
        }

        async function handleFiles(e) {
            const files = Array.from(e.target.files);
            const validFiles = files.filter(file => 
                file.size <= 10 * 1024 * 1024 && 
                ['application/pdf', 'image/jpeg', 'image/png'].includes(file.type)
            );

            if (validFiles.length > 0) {
                uploadedFiles = [...uploadedFiles, ...validFiles];
                updateFileList();
            }
        }

        function updateFileList() {
            fileList.innerHTML = uploadedFiles.length ? `
                <div class="file-list-header">Selected Files:</div>
                <div class="file-list-items">
                    ${uploadedFiles.map((file, index) => `
                        <div class="file-item">
                            <div class="file-info">
                                <i class="fas fa-file"></i>
                                <span class="file-name">${file.name}</span>
                                <span class="file-size">(${(file.size / 1024 / 1024).toFixed(2)}MB)</span>
                            </div>
                            <button class="remove-file" data-index="${index}">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `).join('')}
                </div>
            ` : '';

            document.querySelectorAll('.remove-file').forEach(btn => {
                btn.addEventListener('click', function() {
                    uploadedFiles.splice(parseInt(this.dataset.index), 1);
                    updateFileList();
                });
            });
        }
    }

    // 9. CONFIRMATION
    function updateConfirmationPage() {
        // Appointment details
        document.getElementById('confirm-doctor').textContent = selectedDoctorName || 'Not selected';
        document.getElementById('confirm-specialty').textContent = selectedSpecialty || 'Not selected';
        document.getElementById('confirm-datetime').textContent = 
            `${selectedDate || 'Not selected'} at ${selectedTime || 'Not selected'}`;
        document.getElementById('confirm-reason').textContent = 
            document.getElementById('visit-reason').value || 'Not specified';
        
        // Insurance info
        const insuranceProvider = document.getElementById('insurance-provider');
        document.getElementById('confirm-insurance-provider').textContent = 
            insuranceProvider.options[insuranceProvider.selectedIndex]?.text || 'Not specified';
        document.getElementById('confirm-policy-number').textContent = 
            document.getElementById('policy-number').value || 'Not specified';
        
        // First visit
        const firstVisit = document.querySelector('input[name="first-visit"]:checked');
        document.getElementById('confirm-first-visit').textContent = 
            firstVisit?.nextElementSibling.textContent || 'Not specified';
        
        // Uploaded files
        const filesList = uploadedFiles.map(file => file.name).join(', ');
        document.getElementById('confirm-uploaded-files').textContent = 
            filesList || 'None';
        
        // Patient info
        document.getElementById('confirm-patient-name').textContent = 
            `${document.getElementById('first-name').value} ${document.getElementById('last-name').value}`;
        document.getElementById('confirm-dob').textContent = 
            document.getElementById('date-of-birth').value || 'Not specified';
        document.getElementById('confirm-gender').textContent = 
            document.getElementById('gender').options[document.getElementById('gender').selectedIndex]?.text || 'Not specified';
        document.getElementById('confirm-email').textContent = 
            document.getElementById('email').value || 'Not specified';
        document.getElementById('confirm-phone').textContent = 
            document.getElementById('phone').value || 'Not specified';
        document.getElementById('confirm-address').textContent = 
            `${document.getElementById('address').value || ''}, ${document.getElementById('city').value || ''}, ${document.getElementById('state').value || ''} ${document.getElementById('zip').value || ''}`.trim();
        
        // Medical history
        const conditions = [];
        if (document.getElementById('high-blood-pressure').checked) conditions.push('High Blood Pressure');
        if (document.getElementById('diabetes').checked) conditions.push('Diabetes');
        if (document.getElementById('heart-disease').checked) conditions.push('Heart Disease');
        if (document.getElementById('asthma').checked) conditions.push('Asthma');
        if (document.getElementById('cancer').checked) conditions.push('Cancer');
        if (document.getElementById('allergies').checked) conditions.push('Allergies');
        document.getElementById('confirm-conditions').textContent = 
            conditions.join(', ') || 'None reported';
        
        document.getElementById('confirm-medications').textContent = 
            document.getElementById('current-medications').value || 'None';
        
        // Emergency contact
        document.getElementById('confirm-emergency-name').textContent = 
            document.getElementById('emergency-contact').value || 'Not specified';
        document.getElementById('confirm-emergency-relationship').textContent = 
            document.getElementById('emergency-relationship').value || 'Not specified';
        document.getElementById('confirm-emergency-phone').textContent = 
            document.getElementById('emergency-phone').value || 'Not specified';
    }

    async function submitAppointmentData() {
        try {
            // Require confirmation checkbox
            if (!document.getElementById('confirm-info').checked) {
                alert('You must confirm that all the information provided is accurate and complete.');
                return;
            }
            // Let's go back to using FormData since your PHP is set up for it
            const formData = new FormData();
            
            // Appointment details
            formData.append('doctor_id', selectedDoctorId);
            formData.append('date', selectedDate);
            formData.append('time', selectedTime);
            formData.append('specialty', selectedSpecialty);
            formData.append('reason', document.getElementById('visit-reason').value || '');
            
            // Patient info
            formData.append('first_name', document.getElementById('first-name').value || '');
            formData.append('last_name', document.getElementById('last-name').value || '');
            formData.append('date_of_birth', document.getElementById('date-of-birth').value || '');
            formData.append('gender', document.getElementById('gender').value || '');
            formData.append('email', document.getElementById('email').value || '');
            formData.append('phone', document.getElementById('phone').value || '');
            formData.append('address', document.getElementById('address').value || '');
            formData.append('city', document.getElementById('city').value || '');
            formData.append('state', document.getElementById('state').value || '');
            formData.append('zip', document.getElementById('zip').value || '');
            
            // Medical info
            formData.append('high_blood_pressure', document.getElementById('high-blood-pressure').checked);
            formData.append('diabetes', document.getElementById('diabetes').checked);
            formData.append('heart_disease', document.getElementById('heart-disease').checked);
            formData.append('asthma', document.getElementById('asthma').checked);
            formData.append('cancer', document.getElementById('cancer').checked);
            formData.append('allergies', document.getElementById('allergies').checked);
            formData.append('current_medications', document.getElementById('current-medications').value || '');
            
            // Emergency contact info
            formData.append('emergency-contact', document.getElementById('emergency-contact').value || '');
            formData.append('emergency-relationship', document.getElementById('emergency-relationship').value || '');
            formData.append('emergency-phone', document.getElementById('emergency-phone').value || '');
            
            // Insurance info
            formData.append('insurance_provider', document.getElementById('insurance-provider').value || '');
            formData.append('policy_number', document.getElementById('policy-number').value || '');
            formData.append('first_visit', document.querySelector('input[name="first-visit"]:checked')?.value || 'no');
            formData.append('additional_notes', document.getElementById('additional-notes').value || '');
            
            // Append any files
            if (uploadedFiles && uploadedFiles.length > 0) {
                uploadedFiles.forEach((file, index) => {
                    formData.append(`documents[]`, file);
                });
            }

            // Validate required fields
            if (!selectedDoctorId || !selectedDate || !selectedTime || !document.getElementById('visit-reason').value) {
                alert('Please complete all required fields');
                return;
            }

            console.log('Submitting appointment data');
            
            // Send data as FormData
            const response = await fetch('book_appointment.php', {
                method: 'POST',
                body: formData
            });

            // Handle the response as text first to handle both JSON and HTML errors
            const responseText = await response.text();
            let result;
            
            try {
                // Try to parse as JSON
                result = JSON.parse(responseText);
            } catch (e) {
                // If not valid JSON, it's probably an HTML error page
                console.error('Server response is not valid JSON:', responseText);
                throw new Error('Server error: Please check the PHP logs');
            }
            
            if (result.success) {
                alert('Appointment confirmed! Thank you.');
                // Redirect to appointments page
                window.location.href = 'appointments.php';
            } else {
                throw new Error(result.message || 'Booking failed');
            }
            
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to book appointment: ' + error.message);
        }
    }
</script>

</body>

</html>