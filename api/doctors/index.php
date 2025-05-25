<?php
session_start();
require_once '../config.php';
require_once 'DoctorAPI.php';
require_once 'AvailabilityAPI.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Doctor') {
    handleError('Unauthorized', 401);
}

$doctorId = $_SESSION['user_id'];

// Initialize APIs
$doctorApi = new DoctorAPI($conn, $doctorId);
$availabilityApi = new AvailabilityAPI($conn, $doctorId);

// Route API requests
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

switch ($method) {
    case 'GET':
        switch ($endpoint) {
            case 'profile':
                $doctorApi->getProfile();
                break;
            case 'availability':
                $availabilityApi->getAvailability();
                break;
            case 'availability/slots':
                $date = $_GET['date'] ?? date('Y-m-d');
                $availabilityApi->getAvailableSlots($date);
                break;
            default:
                handleError('Endpoint not found', 404);
        }
        break;

    case 'PUT':
        switch ($endpoint) {
            case 'profile':
                $data = json_decode(file_get_contents('php://input'), true);
                $doctorApi->updateProfile($data);
                break;
            case 'availability':
                $data = json_decode(file_get_contents('php://input'), true);
                $availabilityApi->updateAvailability($data);
                break;
            default:
                handleError('Endpoint not found', 404);
        }
        break;

    case 'POST':
        if ($endpoint === 'profile/image') {
            $doctorApi->updateProfileImage($_FILES['profile_image']);
        }
        break;

    case 'DELETE':
        if ($endpoint === 'profile/image') {
            $doctorApi->removeProfileImage();
        }
        break;

    default:
        handleError('Method not allowed', 405);
}
