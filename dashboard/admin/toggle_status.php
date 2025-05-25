<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    // Fetch current is_active value
    $stmt = $conn->prepare("SELECT is_active FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($isActive);
    $stmt->fetch();
    $stmt->close();

    // Toggle value: 1 becomes 0, 0 becomes 1
    $newStatus = $isActive ? 0 : 1;

    // Update the is_active field
    $updateStmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $updateStmt->bind_param("ii", $newStatus, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    // Respond with updated status text
    echo json_encode([
        'is_active' => $newStatus,
        'status_text' => $newStatus ? 'Active' : 'Inactive'
    ]);
}
?>
