<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SESSION['account_type'] !== 'Patient') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Patients only']);
    exit();
}

// Get POST data
$data = $_POST;

// Validate required fields
$required = ['doctor_id', 'date', 'time', 'reason', 'first_name', 'last_name'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Get or create patient record
    $patient_id = getOrCreatePatient($conn, $_SESSION['user_id'], $data);
    
    // 2. Create appointment
    $appointment_id = createAppointment($conn, $patient_id, $data);
    
    // 3. Log creation activity
    logAppointmentActivity($conn, $appointment_id, 'created', 'Upcoming', null, 'patient', 'Appointment booked');
    
    // 4. Save medical info
    saveMedicalInfo($conn, $patient_id, $data);
    
    // 5. Save insurance info
    saveInsuranceInfo($conn, $patient_id, $data);
    
    // 6. Save emergency contact info
    saveEmergencyContact($conn, $patient_id, $data);
    
    // 7. Handle file uploads
    if (isset($_FILES['documents'])) {
        handleFileUploads($conn, $patient_id, $appointment_id);
    }
    
    // 8. Create notification
    $doctor_name = getDoctorName($conn, $data['doctor_id']);
    createNotification($conn, 
        $_SESSION['user_id'], 
        "Appointment Booked", 
        "Your appointment with Dr. {$doctor_name} is confirmed for {$data['date']} at {$data['time']}",
        'appointment',
        $appointment_id
    );
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'appointment_id' => $appointment_id,
        'message' => 'Appointment booked successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Appointment Booking Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to book appointment: ' . $e->getMessage()]);
    exit();
}

// Helper functions
function getOrCreatePatient($conn, $user_id, $data) {
    // Check if patient exists
    $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['patient_id'];
    }
    
    // Create new patient record
    $stmt = $conn->prepare("INSERT INTO patients (
        user_id, first_name, last_name, date_of_birth, gender, 
        email, phone_number, address, city, state, zip_code
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("issssssssss", 
        $user_id,
        $data['first_name'],
        $data['last_name'],
        $data['date_of_birth'] ?? null,
        $data['gender'] ?? null,
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['city'] ?? null,
        $data['state'] ?? null,
        $data['zip'] ?? null
    );
    
    $stmt->execute();
    return $stmt->insert_id;
}

function createAppointment($conn, $patient_id, $data) {
    $datetime = date('Y-m-d H:i:s', strtotime("{$data['date']} {$data['time']}"));
    if ($datetime === false) {
        throw new Exception("Invalid date/time format");
    }
    
    $stmt = $conn->prepare("INSERT INTO appointments (
        patient_id, doctor_id, appointment_date, reason, status, 
        first_visit, notes
    ) VALUES (?, ?, ?, ?, 'Upcoming', ?, ?)");
    
    $first_visit = isset($data['first_visit']) && $data['first_visit'] === 'yes' ? 1 : 0;
    $notes = $data['additional_notes'] ?? '';
    
    $stmt->bind_param("iissis", 
        $patient_id,
        $data['doctor_id'],
        $datetime,
        $data['reason'],
        $first_visit,
        $notes
    );
    
    $stmt->execute();
    return $stmt->insert_id;
}

function logAppointmentActivity($conn, $appointment_id, $activity_type, $new_status, $old_status = null, $changed_by, $notes = '') {
    $stmt = $conn->prepare("INSERT INTO appointment_activity (
        appointment_id, activity_type, old_status, new_status, 
        changed_by, change_reason
    ) VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("isssss", 
        $appointment_id,
        $activity_type,
        $old_status,
        $new_status,
        $changed_by,
        $notes
    );
    
    $stmt->execute();
}

function saveMedicalInfo($conn, $patient_id, $data) {
    $conditions = [];
    $conditions_list = ['high_blood_pressure', 'diabetes', 'heart_disease', 'asthma', 'cancer', 'allergies'];
    
    foreach ($conditions_list as $condition) {
        if (!empty($data[$condition]) && $data[$condition] !== 'false') {
            $conditions[] = ucfirst(str_replace('_', ' ', $condition));
        }
    }
    
    $conditions_str = implode(', ', $conditions);
    $allergies_text = $data['allergies_text'] ?? '';
    $medications = $data['current_medications'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO patient_medical_info (
        patient_id, allergies, chronic_conditions, current_medications
    ) VALUES (?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        allergies = VALUES(allergies),
        chronic_conditions = VALUES(chronic_conditions),
        current_medications = VALUES(current_medications)");
    
    $stmt->bind_param("isss", 
        $patient_id,
        $allergies_text,
        $conditions_str,
        $medications
    );
    
    $stmt->execute();
}

function saveInsuranceInfo($conn, $patient_id, $data) {
    if (empty($data['insurance_provider'])) return;
    
    $provider = $data['insurance_provider'];
    $policy_number = $data['policy_number'] ?? '';
    $is_primary = 1;
    
    $stmt = $conn->prepare("INSERT INTO patient_insurance (
        patient_id, provider, policy_number, is_primary
    ) VALUES (?, ?, ?, ?)");
    
    $stmt->bind_param("issi",
        $patient_id,
        $provider,
        $policy_number,
        $is_primary
    );
    
    $stmt->execute();
}

function saveEmergencyContact($conn, $patient_id, $data) {
    if (empty($data['emergency-contact'])) return;
    
    $full_name = $data['emergency-contact'];
    $relationship = $data['emergency-relationship'] ?? '';
    $phone = $data['emergency-phone'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO emergency_contacts (
        patient_id, full_name, relationship, phone
    ) VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        relationship = VALUES(relationship),
        phone = VALUES(phone)");
    
    $stmt->bind_param("isss",
        $patient_id,
        $full_name,
        $relationship,
        $phone
    );
    
    $stmt->execute();
}

function handleFileUploads($conn, $patient_id, $appointment_id) {
    $uploadDir = '../../uploads/patient_documents/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
        $fileName = $_FILES['documents']['name'][$key];
        $fileType = $_FILES['documents']['type'][$key];
        $fileSize = $_FILES['documents']['size'][$key];
        
        // Validate file
        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            continue;
        }
        
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($fileType, $allowedTypes)) {
            continue;
        }
        
        // Generate unique filename
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueName = uniqid('doc_') . '.' . $extension;
        $targetPath = $uploadDir . $uniqueName;
        
        if (move_uploaded_file($tmp_name, $targetPath)) {
            $stmt = $conn->prepare("INSERT INTO patient_documents (
                patient_id, appointment_id, file_name, file_path, document_type, uploaded_at
            ) VALUES (?, ?, ?, ?, 'Other', NOW())");
            
            $stmt->bind_param("iiss",
                $patient_id,
                $appointment_id,
                $fileName,
                $uniqueName
            );
            
            $stmt->execute();
        }
    }
}

function getDoctorName($conn, $doctor_id) {
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM doctor_details WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['name'] : 'Unknown Doctor';
}

function createNotification($conn, $user_id, $title, $message, $type, $reference_id) {
    $stmt = $conn->prepare("INSERT INTO notifications (
        user_id, message, type, is_read, created_at
    ) VALUES (?, ?, ?, 0, NOW())");
    
    $stmt->bind_param("iss",
        $user_id,
        $message,
        $type
    );
    
    $stmt->execute();
}
?>