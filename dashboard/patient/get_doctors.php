<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    // Check if we're requesting a specific doctor by ID
    if (isset($_GET['doctor_id'])) {
        $doctorId = $conn->real_escape_string($_GET['doctor_id']);
        
        $query = "SELECT d.doctor_id, d.first_name, d.last_name, d.specialization, 
                         d.years_of_experience, u.profile_image, d.availability
                  FROM doctor_details d
                  JOIN users u ON d.user_id = u.user_id
                  WHERE d.doctor_id = '$doctorId'";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $doctor = $result->fetch_assoc();
        
        if (!$doctor) {
            throw new Exception('Doctor not found');
        }
        
        // Process availability
        $doctor['availability'] = json_decode($doctor['availability'], true);
        $doctor['rating'] = '5.0';
        $doctor['review_count'] = rand(50, 200);
        
        echo json_encode($doctor);
        
    } 
    // Otherwise handle specialty search
    elseif (isset($_GET['specialty'])) {
        $specialty = $conn->real_escape_string($_GET['specialty']);
        
        $query = "SELECT d.doctor_id, d.first_name, d.last_name, d.specialization, 
                         d.years_of_experience, u.profile_image, d.availability
                  FROM doctor_details d
                  JOIN users u ON d.user_id = u.user_id
                  WHERE d.specialization = '$specialty'";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $row['availability'] = json_decode($row['availability'], true);
            $row['rating'] = '5.0';
            $row['review_count'] = rand(50, 200);
            $doctors[] = $row;
        }
        
        echo json_encode($doctors);
    } 
    else {
        throw new Exception('Either specialty or doctor_id parameter is required');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>