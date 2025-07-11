<?php
session_start();
require_once '../../config/database.php';

// Security check - only Admins can fetch
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['doctor_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Doctor ID required']);
    exit;
}

$doctorId = intval($_GET['doctor_id']);

$stmt = $conn->prepare("
    SELECT d.doctor_id, d.first_name, d.last_name, d.specialization, d.sub_specialties, 
           d.years_of_experience, d.medical_license_number, d.npi_number, d.education_and_training, d.availability,
           u.email, u.phone_number, u.date_of_birth, u.gender, u.profile_image, u.is_active
    FROM doctor_details d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.doctor_id = ?
");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Doctor not found']);
    exit;
}

$doctor = $result->fetch_assoc();

// Sanitize and format availability JSON if exists
if (!empty($doctor['availability'])) {
    $doctor['availability'] = json_decode($doctor['availability'], true);
} else {
    $doctor['availability'] = [];
}

header('Content-Type: application/json');
echo json_encode($doctor);
