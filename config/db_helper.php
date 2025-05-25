<?php
require_once 'database.php';

class DatabaseHelper {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function executeQuery($query, $params = [], $types = "") {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            return $stmt->get_result();
            
        } catch (Exception $e) {
            error_log("Query execution error: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }

    public function getPatientAppointments($patientId) {
        $query = "SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC";
        return $this->executeQuery($query, [$patientId], "i");
    }

    public function getPatientProfile($userId) {
    $query = "SELECT 
                u.user_id, 
                u.email,
                u.profile_image,
                u.account_type,
                CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) AS full_name,
                p.date_of_birth,
                p.gender,
                p.address,
                p.phone,
                p.emergency_contact_name,
                p.emergency_contact_phone
              FROM users u 
              LEFT JOIN patient_profiles p ON u.user_id = p.user_id 
              WHERE u.user_id = ? AND u.account_type = 'patient'";
    $result = $this->executeQuery($query, [$userId], "i");
    return $result;
    }

    public function getAdminProfile($userId) {
    $query = "SELECT 
                u.user_id, 
                u.email,
                u.profile_image,
                u.account_type,
                CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, '')) AS full_name,
                a.date_of_birth,
                a.gender,
                a.address,
                a.phone,
                a.emergency_contact_name,
                a.emergency_contact_phone
              FROM users u 
              LEFT JOIN admin_profiles a ON u.user_id = a.user_id 
              WHERE u.user_id = ? AND u.account_type = 'admin'";
    $result = $this->executeQuery($query, [$userId], "i");
    return $result;
}

public function getAppointmentStats($userId) {
    $query = "SELECT 
                COUNT(*) as total_appointments,
                SUM(CASE WHEN appointment_date >= CURDATE() AND status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_appointments,
                SUM(CASE WHEN (appointment_date < CURDATE() OR status = 'completed') THEN 1 ELSE 0 END) as past_appointments
              FROM appointments 
              WHERE patient_id = ?";
    $result = $this->executeQuery($query, [$userId], "i");
    return $result->fetch_assoc(); // Return as associative array directly
}

    public function updatePatientProfile($userId, $data) {
        $query = "UPDATE patient_profiles SET 
            first_name = ?,
            last_name = ?,
            date_of_birth = ?,
            gender = ?,
            address = ?,
            phone = ?,
            emergency_contact_name = ?,
            emergency_contact_phone = ?
            WHERE user_id = ?";
        
        return $this->executeQuery(
            $query, 
            [
                $data['first_name'],
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['address'],
                $data['phone'],
                $data['emergency_contact_name'],
                $data['emergency_contact_phone'],
                $userId
            ],
            "ssssssssi"
        );
    }

    public function createAppointment($patientId, $doctorId, $date, $time, $reason) {
        $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason) 
                 VALUES (?, ?, ?, ?, ?)";
        return $this->executeQuery($query, [$patientId, $doctorId, $date, $time, $reason], "iisss");
    }

    public function cancelAppointment($appointmentId, $patientId) {
        $query = "UPDATE appointments SET status = 'cancelled' 
                 WHERE appointment_id = ? AND patient_id = ?";
        return $this->executeQuery($query, [$appointmentId, $patientId], "ii");
    }

    public function getUpcomingAppointments($patientId) {
        $query = "SELECT a.*, d.first_name as doctor_first_name, d.last_name as doctor_last_name 
                 FROM appointments a
                 LEFT JOIN doctor_profiles d ON a.doctor_id = d.user_id
                 WHERE a.patient_id = ? 
                 AND a.appointment_date >= CURDATE()
                 AND a.status = 'scheduled'
                 ORDER BY a.appointment_date, a.appointment_time";
        return $this->executeQuery($query, [$patientId], "i");
    }

    // Transaction handling methods
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }

    public function commitTransaction() {
        $this->conn->commit();
    }

    public function rollbackTransaction() {
        $this->conn->rollback();
    }
}



?>