<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$doctor_id = intval($_POST['doctor_id'] ?? 0);

if (!$doctor_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid doctor ID']);
    exit;
}

switch ($action) {
    case 'toggle_status':
        // Fetch current is_active status from users table via doctor_id join
        $stmt = $conn->prepare("SELECT u.user_id, u.account_type, u.is_active 
                                FROM doctor_details d 
                                JOIN users u ON d.user_id = u.user_id 
                                WHERE d.doctor_id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Doctor not found']);
            exit;
        }
        
        $doctor = $res->fetch_assoc();

        // Toggle is_active: 1 => 0, 0 => 1
        $newIsActive = ($doctor['is_active'] == 1) ? 0 : 1;

        // Update is_active in users table
        $update = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $update->bind_param("ii", $newIsActive, $doctor['user_id']);

        if ($update->execute()) {
            echo json_encode(['success' => true, 'newIsActive' => $newIsActive]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update status']);
        }
        break;


    case 'delete_doctor':
        // Find user_id from doctor_id
        $stmt = $conn->prepare("SELECT user_id FROM doctor_details WHERE doctor_id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Doctor not found']);
            exit;
        }
        $user = $res->fetch_assoc();

        // Delete from doctor_details and users table (use transaction)
        $conn->begin_transaction();
        try {
            $delDoctor = $conn->prepare("DELETE FROM doctor_details WHERE doctor_id = ?");
            $delDoctor->bind_param("i", $doctor_id);
            $delDoctor->execute();

            $delUser = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delUser->bind_param("i", $user['user_id']);
            $delUser->execute();

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete doctor']);
        }
        break;

    

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
