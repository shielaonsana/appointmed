<?php
session_start();

require_once '../../config/database.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Patient') {
    header("Location: ../../main-page/login.php");
    exit();
}

// Fetch patient's complete profile data
$user_id = $_SESSION['user_id'];
$query = "SELECT u.user_id, u.full_name, u.profile_image, u.account_type, 
                 u.email, u.phone_number, u.gender, u.date_of_birth,
                 p.first_name, p.last_name, p.date_of_birth AS patient_dob,
                 p.patient_id
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
    $baseImagePath = '../../images/patients/';
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
    $patient_id = $patientProfile['patient_id'];
    
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
    $patient_id = null;
}

// Fetch appointments for this patient
$appointments = [];
if ($patient_id) {
    $query = "SELECT a.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, 
                 d.specialization, u.profile_image AS doctor_image
          FROM appointments a
          JOIN doctor_details d ON a.doctor_id = d.doctor_id
          JOIN users u ON d.user_id = u.user_id
          WHERE a.patient_id = ?
          ORDER BY a.appointment_date DESC";


    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
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

        .appointments-container {
            max-width: 100%;
            background-color: #f5f9ff;
            border-radius: 12px;
        }

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            background-color: white;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e7f1;
            overflow: hidden;
        }

        .filter-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .filter-tab.active {
            color: #3498db;
            border-bottom: 2px solid #3498db;
            background-color: #f0f5ff;
        }

        .filter-tab:hover:not(.active) {
            background-color: #f8fafc;
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

        /* Enhanced Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1001;
            backdrop-filter: blur(2px);
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .modal-content {
            background-color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: slideInUp 0.3s ease-out;
        }

        .modal-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 24px;
            position: relative;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-header .close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            cursor: pointer;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .modal-header .close:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-50%) rotate(90deg);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-body p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .form-field {
            margin-bottom: 20px;
        }

        .form-field label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            background-color: #fafafa;
        }

        .form-field input:focus,
        .form-field select:focus,
        .form-field textarea:focus {
            outline: none;
            border-color: #3498db;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-field textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .modal-actions .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .modal-actions .btn-outline {
            background-color: #f8fafc;
            color: #64748b;
            border: 2px solid #e2e8f0;
        }

        .modal-actions .btn-outline:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e1;
            color: #475569;
        }

        .modal-actions .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: 2px solid transparent;
        }

        .modal-actions .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f99);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        /* Enhanced No Appointments Section */
        .no-appointments {
            text-align: center;
            padding: 60px 40px;
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
            display: none;
        }

        .no-appointments::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2980b9, #8e44ad);
        }

        .no-appointments-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .no-appointments-icon i {
            font-size: 36px;
            color: #3498db;
        }

        .no-appointments-icon::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(142, 68, 173, 0.1));
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }

        .no-appointments h3 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .no-appointments p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 32px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .no-appointments .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .no-appointments .btn:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f99);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }

        .no-appointments .btn i {
            font-size: 16px;
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

        /* Legacy styles for backward compatibility */
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
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

            .notification-container {
                gap: 8px;
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

            .filter-tabs {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .filter-tab {
                flex: 1 0 33%;
                min-width: 120px;
                padding: 10px;
                font-size: 13px;
            }

            /* Modal responsive styles */
            .modal-content {
                width: 95%;
                max-width: none;
                margin: 20px;
                transform: translate(-50%, -50%);
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-header h3 {
                font-size: 18px;
                padding-right: 40px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-actions {
                flex-direction: column-reverse;
                gap: 10px;
            }
            
            .modal-actions .btn {
                width: 100%;
                min-width: auto;
            }
            
            .no-appointments {
                padding: 40px 24px;
                margin: 0 10px;
            }
            
            .no-appointments-icon {
                width: 70px;
                height: 70px;
                margin-bottom: 20px;
            }
            
            .no-appointments-icon i {
                font-size: 30px;
            }
            
            .no-appointments h3 {
                font-size: 20px;
            }
            
            .no-appointments p {
                font-size: 14px;
                margin-bottom: 24px;
            }
            
            .no-appointments .btn {
                padding: 12px 24px;
                font-size: 15px;
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

            .notification-badge {
                width: 15px;
                height: 15px;
                font-size: 9px;
            }

            .patient-avatar {
                width: 40px;
                height: 40px;
                margin-right: 12px;
            }

            .status-badge {
                position: absolute;
                top: 15px;
                right: 15px;
                margin-bottom: 0;
            }

            .patient-info {
                flex-direction: row;
                align-items: center;
                margin-bottom: 10px;
            }

            .appointment-details {
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
                flex: 1;
                padding: 8px;
                font-size: 13px;
            }

            .patient-name {
                font-size: 15px;
            }

            .patient-details,
            .time-slot,
            .appointment-type {
                font-size: 13px;
            }

            .welcome-text h1 {
                font-size: 20px;
            }

            .filter-tab {
                font-size: 12px;
                padding: 8px 5px;
            }

            /* Mobile modal adjustments */
            .modal-content {
                width: calc(100% - 20px);
                margin: 10px;
            }
            
            .modal-header {
                padding: 16px;
            }
            
            .modal-body {
                padding: 16px;
            }
            
            .form-field input,
            .form-field select,
            .form-field textarea {
                padding: 10px 12px;
            }
            
            .modal-actions .btn {
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>


<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <a href="">
                    <img src="images/logo.png" alt="Logo">
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
                <a href="appointments.php" class="nav-item active">
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

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="menu-toggle" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search appointments...">
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

            <!-- Content Area -->
            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1>My Appointments</h1>
                    </div>
                    <button class="add-doctor-btn" onclick="window.location.href='book.php'">
                        <i class="fas fa-plus"></i>
                        Book New Appointment
                    </button>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <div class="filter-tab active" data-filter="all">All Appointments</div>
                    <div class="filter-tab" data-filter="upcoming">Upcoming</div>
                    <div class="filter-tab" data-filter="completed">Completed</div>
                    <div class="filter-tab" data-filter="cancelled">Cancelled</div>
                </div>

                <!-- No Appointments Message -->
                <div class="no-appointments" id="noAppointments" style="<?php echo empty($appointments) ? 'display: block;' : 'display: none;' ?>">
                    <div class="no-appointments-icon">
                        <i class="far fa-calendar-times"></i>
                    </div>
                    <h3>No appointments found</h3>
                    <p>You don't have any appointments yet. Book your first appointment now!</p>
                    <a href="book.php" class="btn">
                        <i class="fas fa-plus"></i> 
                        Book Appointment
                    </a>
                </div>

                <!-- Appointments Container -->
                <div class="appointments-container">
                    <?php foreach ($appointments as $appointment): 
                        // Determine appointment status
                        $now = new DateTime();
                        $appointmentDate = new DateTime($appointment['appointment_date']);
                        $status = $appointment['status'];
                        
                        if ($status === 'Cancelled') {
                            $statusClass = 'cancelled';
                        } elseif ($status === 'Completed') {
                            $statusClass = 'completed';
                        } elseif ($appointmentDate > $now) {
                            $statusClass = 'upcoming';
                        } else {
                            $statusClass = 'completed';
                        }
                        
                        // Format appointment time
                        $timeSlot = $appointmentDate->format('H:i A');
                        $endTime = clone $appointmentDate;
                        $endTime->add(new DateInterval('PT30M')); // Assuming 30 min appointments
                        $timeSlotDisplay = $appointmentDate->format('H:i') . ' - ' . $endTime->format('H:i A');
                        
                        // Doctor info
                        $doctorName = htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']);
                        $doctorImage = !empty($appointment['doctor_image']) ? 
                            '../../images/doctors/' . $appointment['doctor_image'] : 
                            '../../images/doctors/default.png';
                    ?>
                    <div class="appointment-card" data-status="<?php echo $statusClass; ?>" data-doctor-id="<?php echo $appointment['doctor_id']; ?>">
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($statusClass); ?></span>
                        <div class="patient-info">
                            <div class="patient-avatar">
                                <img src="<?php echo $doctorImage; ?>" alt="<?php echo $doctorName; ?>">
                            </div>
                            <div>
                                <div class="patient-name">Dr. <?php echo $doctorName; ?></div>
                                <div class="patient-details"><?php echo $appointment['specialization']; ?></div>
                            </div>
                        </div>
                        <div class="appointment-details">
                            <div class="time-slot">
                                <i class="far fa-clock"></i>
                                <?php echo $appointmentDate->format('F j, Y') . ' at ' . $timeSlotDisplay; ?>
                            </div>
                            <div class="appointment-type">
                                <i class="fas fa-stethoscope"></i>
                                <?php echo htmlspecialchars($appointment['reason']); ?>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <?php if ($statusClass === 'upcoming'): ?>
                                <button class="btn btn-outline cancel-btn" data-id="<?php echo $appointment['appointment_id']; ?>">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                                <button class="btn btn-primary reschedule-btn" data-id="<?php echo $appointment['appointment_id']; ?>">
                                    <i class="far fa-calendar-alt"></i>
                                    Reschedule
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="window.location.href='book.php?doctor_id=<?php echo $appointment['doctor_id']; ?>'">
                                    <i class="far fa-calendar-alt"></i>
                                    Book Again
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reschedule Appointment</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="rescheduleForm">
                    <input type="hidden" id="rescheduleAppointmentId" value="">
                    <div class="form-field">
                        <label for="newAppointmentDate">New Date</label>
                        <input type="date" id="newAppointmentDate" required>
                    </div>
                    <div class="form-field">
                        <label for="newAppointmentTime">New Time</label>
                        <select id="newAppointmentTime" required>
                            <option value="08:00">08:00 AM</option>
                            <option value="08:30">08:30 AM</option>
                            <option value="09:00">09:00 AM</option>
                            <option value="09:30">09:30 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="10:30">10:30 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="11:30">11:30 AM</option>
                            <option value="13:00">01:00 PM</option>
                            <option value="13:30">01:30 PM</option>
                            <option value="14:00">02:00 PM</option>
                            <option value="14:30">02:30 PM</option>
                            <option value="15:00">03:00 PM</option>
                            <option value="15:30">03:30 PM</option>
                            <option value="16:00">04:00 PM</option>
                            <option value="16:30">04:30 PM</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="rescheduleReason">Reason for Rescheduling (Optional)</label>
                        <textarea id="rescheduleReason" name="rescheduleReason" rows="3" placeholder="Please let us know why you're rescheduling..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" id="cancelReschedule">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Appointment</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this appointment? This action cannot be undone.</p>
                <form id="cancelForm">
                    <input type="hidden" id="cancelAppointmentId" value="">
                    <div class="form-field">
                        <label for="cancelReason">Reason for Cancellation (Optional)</label>
                        <textarea id="cancelReason" rows="3" placeholder="Please let us know why you're cancelling..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" id="dontCancel">No, Keep Appointment</button>
                        <button type="submit" class="btn btn-primary">Yes, Cancel Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.main-content').classList.toggle('expanded');
        });

        // Close sidebar when clicking outside on small screens
        document.addEventListener('click', function(event) {
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

        // Search functionality
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const cards = document.querySelectorAll('.appointment-card');
                let visibleCount = 0;
                
                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    const doctorName = card.querySelector('.patient-name').textContent.toLowerCase();
                    const appointmentType = card.querySelector('.appointment-type').textContent.toLowerCase();
                    const timeSlot = card.querySelector('.time-slot').textContent.toLowerCase();
                    
                    const match = text.includes(searchTerm) ||
                        doctorName.includes(searchTerm) ||
                        appointmentType.includes(searchTerm) ||
                        timeSlot.includes(searchTerm);
                    
                    card.style.display = match ? 'block' : 'none';
                    if (match) visibleCount++;
                });
                
                document.getElementById('noAppointments').style.display = 
                    visibleCount === 0 ? 'block' : 'none';
            });
        }

        // Filter functionality for appointments
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const cards = document.querySelectorAll('.appointment-card');
                let visibleCount = 0;
                
                cards.forEach(card => {
                    const status = card.getAttribute('data-status');
                    
                    if (filter === 'all' || filter === status) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                document.getElementById('noAppointments').style.display = 
                    visibleCount === 0 ? 'block' : 'none';
            });
        });

        async function showRescheduleModal(appointmentId) {
            try {
                // Fetch current appointment data
                const response = await fetch(`get_appointment.php?id=${appointmentId}`);
                const appointment = await response.json();
                
                // Populate modal with existing data
                document.getElementById('newAppointmentDate').value = appointment.date.split(' ')[0];
                document.getElementById('newAppointmentTime').value = appointment.time;
                
                // Store notes in a hidden field
                const notesInput = document.createElement('input');
                notesInput.type = 'hidden';
                notesInput.id = 'reschedule-notes';
                notesInput.name = 'notes';
                notesInput.value = appointment.notes || '';
                document.getElementById('rescheduleForm').appendChild(notesInput);
                
                // Populate available time slots
                populateTimeSlots({
                    working_hours: appointment.working_hours,
                    working_days: appointment.working_days,
                    appointment_duration: appointment.working_hours && appointment.working_hours.appointment_duration ? appointment.working_hours.appointment_duration : 30
                });
                
                // Show modal
                document.getElementById('rescheduleModal').style.display = 'block';
                
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load appointment details');
            }
        }

        function populateTimeSlots(availability) {
            const timeSelect = document.getElementById('newAppointmentTime');
            timeSelect.innerHTML = '';
            
            console.log('Availability data:', availability); // Debug log

            // Check if availability needs parsing
            if (typeof availability === 'string') {
                try {
                    availability = JSON.parse(availability);
                } catch (e) {
                    console.error('Failed to parse availability JSON:', e);
                    timeSelect.innerHTML = '<option value="" disabled selected>Error parsing availability</option>';
                    return;
                }
            }

            // Check if we have the expected structure
            if (!availability.working_hours || !availability.working_days) {
                console.error('Unexpected availability structure:', availability);
                timeSelect.innerHTML = '<option value="" disabled selected>Invalid availability format</option>';
                return;
            }

            // Generate time slots based on working hours and days
            const timeSlots = generateTimeSlotsFromWorkingHours(availability);
            console.log('Generated time slots:', timeSlots); // Debug log

            if (timeSlots.length === 0) {
                timeSelect.innerHTML = '<option value="" disabled selected>No available time slots</option>';
                return;
            }

            // Add available time slots to select
            timeSlots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.display_time;
                timeSelect.appendChild(option);
            });

            // Set up date change handler
            document.getElementById('newAppointmentDate').addEventListener('change', function() {
                const selectedDate = this.value;
                if (!selectedDate) return;

                timeSelect.innerHTML = '<option value="" disabled selected>Loading times...</option>';
                timeSelect.disabled = true;

                // Check if selected day is a working day
                const dayOfWeek = new Date(selectedDate).toLocaleString('en-US', { weekday: 'long' });
                const isWorkingDay = availability.working_days.includes(dayOfWeek);

                if (!isWorkingDay) {
                    timeSelect.innerHTML = '<option value="" disabled selected>Doctor not available this day</option>';
                    timeSelect.disabled = false;
                    return;
                }

                // Filter slots for the selected day
                const filteredSlots = generateTimeSlotsFromWorkingHours(availability);
                
                timeSelect.innerHTML = '';
                if (filteredSlots.length === 0) {
                    timeSelect.innerHTML = '<option value="" disabled selected>No availability for this day</option>';
                } else {
                    filteredSlots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.time;
                        option.textContent = slot.display_time;
                        timeSelect.appendChild(option);
                    });
                }
                
                timeSelect.disabled = false;
            });
        }

        function generateTimeSlotsFromWorkingHours(availability) {
            const timeSlots = [];
            const startTime = availability.working_hours.start_time;
            const endTime = availability.working_hours.end_time;
            const duration = parseInt(availability.appointment_duration) || 30; // Default to 30 mins
            
            // Convert times to minutes since midnight for easier calculation
            const [startHour, startMin] = startTime.split(':').map(Number);
            const [endHour, endMin] = endTime.split(':').map(Number);
            
            let startTotal = startHour * 60 + startMin;
            const endTotal = endHour * 60 + endMin;
            
            // Generate time slots
            while (startTotal + duration <= endTotal) {
                const hours = Math.floor(startTotal / 60);
                const mins = startTotal % 60;
                const timeString = `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
                
                timeSlots.push({
                    time: timeString,
                    display_time: formatTimeForDisplay(timeString)
                });
                
                startTotal += duration;
                
                // Handle break time if enabled
                if (availability.break_time?.enabled) {
                    const [breakHour, breakMin] = availability.break_time.start_time.split(':').map(Number);
                    const breakStart = breakHour * 60 + breakMin;
                    const breakEnd = breakStart + (parseInt(availability.break_time.duration) || 60);
                    
                    if (startTotal >= breakStart && startTotal < breakEnd) {
                        startTotal = breakEnd;
                    }
                }
            }
            
            return timeSlots;
        }

        function formatTimeForDisplay(timeString) {
            const [hours, minutes] = timeString.split(':');
            const hourNum = parseInt(hours, 10);
            const ampm = hourNum >= 12 ? 'PM' : 'AM';
            const displayHour = hourNum % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        }

        // Reschedule functionality
        document.querySelectorAll('.reschedule-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const appointmentId = this.getAttribute('data-id');
                const doctorId = this.closest('.appointment-card').getAttribute('data-doctor-id');
                
                document.getElementById('rescheduleAppointmentId').value = appointmentId;
                
                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                const dateInput = document.getElementById('newAppointmentDate');
                dateInput.min = today;
                dateInput.value = today;
                
                // Clear previous time slots
                const timeSelect = document.getElementById('newAppointmentTime');
                timeSelect.innerHTML = '<option value="" disabled selected>Loading available times...</option>';
                
                // Show loading state
                timeSelect.disabled = true;
                
                try {
                    await showRescheduleModal(appointmentId, doctorId);
                } catch (error) {
                    console.error('Error:', error);
                    timeSelect.innerHTML = '<option value="" disabled selected>Error loading availability</option>';
                } finally {
                    timeSelect.disabled = false;
                }
            });
        });

        // Cancel functionality
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-id');
                document.getElementById('cancelAppointmentId').value = appointmentId;
                document.getElementById('cancelModal').style.display = 'block';
            });
        });

        // Close modals
        document.querySelectorAll('.close, #cancelReschedule, #dontCancel').forEach(el => {
            el.addEventListener('click', function() {
                document.getElementById('rescheduleModal').style.display = 'none';
                document.getElementById('cancelModal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                document.getElementById('rescheduleModal').style.display = 'none';
                document.getElementById('cancelModal').style.display = 'none';
            }
        });

        // Handle reschedule form submission
        document.getElementById('rescheduleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
    
            const formData = new FormData(this);
            const appointmentId = document.getElementById('rescheduleAppointmentId').value;
            const newDate = document.getElementById('newAppointmentDate').value;
            const newTime = document.getElementById('newAppointmentTime').value;
            const rescheduleReason = document.getElementById('rescheduleReason').value;
            formData.append('appointment_id', appointmentId);
            formData.append('newAppointmentDate', newDate);
            formData.append('newAppointmentTime', newTime);
            formData.append('notes', rescheduleReason);
            
            try {
                const response = await fetch('update_appointment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Appointment rescheduled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to reschedule appointment');
            }
        });

        // Handle cancel form submission
        document.getElementById('cancelForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const appointmentId = document.getElementById('cancelAppointmentId').value;
            const reason = document.getElementById('cancelReason').value;
            
            try {
                const response = await fetch('update_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'cancel',
                        appointment_id: appointmentId,
                        reason: reason
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Appointment cancelled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to cancel appointment');
            }
        });
    </script>

</body>

</html>