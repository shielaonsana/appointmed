<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/database.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $conn->begin_transaction();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Get patient ID
        $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        
        if (!$patient) {
            throw new Exception("Patient not found");
        }
        
        if (isset($_POST['appointment_id'])) {
            // Handle rescheduling
            $appointmentId = intval($_POST['appointment_id']);
            $newDate = $_POST['newAppointmentDate'];
            $newTime = $_POST['newAppointmentTime'];
            $notes = $_POST['notes'] ?? '';

            // Validate date and time
            $newDateTime = date('Y-m-d H:i:s', strtotime("$newDate $newTime"));
            if ($newDateTime === false || $newDateTime < date('Y-m-d H:i:s')) {
                throw new Exception("Invalid date or time");
            }

            // Get current appointment data
            $stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND patient_id = ?");
            $stmt->bind_param("ii", $appointmentId, $patient['patient_id']);
            $stmt->execute();
            $appointment = $stmt->get_result()->fetch_assoc();
            
            if (!$appointment) {
                throw new Exception("Appointment not found");
            }

            // Update the appointment
            $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, reason = ?, status = 'Upcoming' WHERE appointment_id = ? AND patient_id = ?");
            $stmt->bind_param("ssii", $newDateTime, $notes, $appointmentId, $patient['patient_id']);
            $stmt->execute();

            // Log the activity with improved details
            $old_status = $appointment['status'];
            $new_status = 'Upcoming';
            $old_date = $appointment['appointment_date'];
            $new_date = $newDateTime;
            $change_reason = $notes;
            $stmt = $conn->prepare("INSERT INTO appointment_activity (appointment_id, activity_type, old_status, new_status, old_date, new_date, changed_by, change_reason) VALUES (?, 'rescheduled', ?, ?, ?, ?, 'patient', ?)");
            $stmt->bind_param("isssss", $appointmentId, $old_status, $new_status, $old_date, $new_date, $change_reason);
            $stmt->execute();

        } elseif (isset($input['action']) && $input['action'] === 'cancel') {
            // Handle cancellation
            $appointmentId = intval($input['appointment_id']);
            $reason = $input['reason'] ?? '';

            // Get current appointment data
            $stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND patient_id = ?");
            $stmt->bind_param("ii", $appointmentId, $patient['patient_id']);
            $stmt->execute();
            $appointment = $stmt->get_result()->fetch_assoc();
            
            if (!$appointment) {
                throw new Exception("Appointment not found");
            }

            // Update the appointment status
            $status = 'Cancelled';
            $stmt = $conn->prepare("UPDATE appointments SET status = ?, cancellation_reason = ? WHERE appointment_id = ? AND patient_id = ?");
            $stmt->bind_param("ssii", $status, $reason, $appointmentId, $patient['patient_id']);
            $stmt->execute();

            // Log the activity with improved details
            $stmt = $conn->prepare("INSERT INTO appointment_activity (appointment_id, activity_type, old_status, new_status, old_date, new_date, changed_by, change_reason) VALUES (?, 'cancelled', ?, ?, ?, ?, 'patient', ?)");
            $stmt->bind_param("ssssss", $appointmentId, $appointment['status'], $status, $appointment['appointment_date'], $appointment['appointment_date'], $reason);
            $stmt->execute();
        } else {
            throw new Exception("Invalid request");
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Appointment updated successfully']);
        
    } else {
        throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    http_response_code($e->getMessage() === "Method not allowed" ? 405 : 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("Appointment Update Error: " . $e->getMessage());
}
?>