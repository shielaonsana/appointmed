<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "appointment_system";

// Create connection with error reporting
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode([
        'error' => 'Database connection failed',
        'details' => $conn->connect_error
    ]));
}

// Set charset to avoid encoding issues
$conn->set_charset("utf8mb4");

?>
