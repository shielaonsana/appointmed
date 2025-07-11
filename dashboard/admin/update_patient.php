<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone_number'];
    $dob = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $zip = $_POST['zip_code'];

    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone_number=?, date_of_birth=?, gender=?, address=?, city=?, state=?, zip_code=? WHERE user_id=?");
    $stmt->bind_param("sssssssssi", $full_name, $email, $phone, $dob, $gender, $address, $city, $state, $zip, $user_id);

    if ($stmt->execute()) {
        header("Location: patients.php?update=success");
        exit();
    } else {
        die("Update failed: " . $stmt->error);
    }
}
?>

