<?php
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("
        SELECT d.*, u.email, u.phone_number, u.date_of_birth, u.gender 
        FROM doctor_details d
        JOIN users u ON d.user_id = u.user_id
        WHERE d.doctor_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($doctor = $result->fetch_assoc()) {
        echo json_encode($doctor);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Doctor not found"]);
    }
}
