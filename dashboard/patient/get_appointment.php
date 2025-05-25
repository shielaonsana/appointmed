<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/database.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Patient') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Appointment ID is required']);
    exit();
}

$appointmentId = intval($_GET['id']);
$userId = $_SESSION['user_id'];

// Get patient ID for the current user
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    http_response_code(404);
    echo json_encode(['error' => 'Patient not found']);
    exit();
}

// Fetch appointment details
$query = "SELECT a.*, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                 d.specialization, d.availability
          FROM appointments a
          JOIN doctor_details d ON a.doctor_id = d.doctor_id
          WHERE a.appointment_id = ? AND a.patient_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointmentId, $patient['patient_id']);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    http_response_code(404);
    echo json_encode(['error' => 'Appointment not found']);
    exit();
}

// Defensive: handle missing or invalid availability JSON
$availability = [];
if (!empty($appointment['availability'])) {
    $availability = json_decode($appointment['availability'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid availability JSON: ' . json_last_error_msg()]);
        exit();
    }
}
$working_hours = $availability['working_hours'] ?? null;
$working_days = $availability['working_days'] ?? null;

// Format the response
$response = [
    'id' => $appointment['appointment_id'],
    'date' => $appointment['appointment_date'],
    'time' => date('H:i', strtotime($appointment['appointment_date'])),
    'doctor' => [
        'id' => $appointment['doctor_id'],
        'name' => $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'],
        'specialization' => $appointment['specialization']
    ],
    'notes' => $appointment['reason'],
    'status' => $appointment['status'],
    'working_hours' => $working_hours,
    'working_days' => $working_days
];

// Send the response
header('Content-Type: application/json');
echo json_encode($response);
?> 