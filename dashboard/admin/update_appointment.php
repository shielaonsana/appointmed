<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $appointment_id = intval($_POST['appointment_id']);

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    }

    if ($action === 'toggle_status') {
        $status = $_POST['status']; // Expecting 'Pending' or 'Completed'
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $stmt->bind_param("si", $status, $appointment_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    }

    // Add logic for 'edit' if needed
}
?>
