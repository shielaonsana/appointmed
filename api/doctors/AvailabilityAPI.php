<?php
class AvailabilityAPI {
    private $conn;
    private $doctorId;

    public function __construct($conn, $doctorId) {
        $this->conn = $conn;
        $this->doctorId = $doctorId;
    }

    // GET /api/doctors/{id}/availability
    public function getAvailability() {
        try {
            $query = "SELECT id, day_of_week, start_time, end_time, is_available 
                     FROM doctor_availability 
                     WHERE doctor_id = ?
                     ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->doctorId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $availability = [];
            while ($row = $result->fetch_assoc()) {
                $availability[] = $row;
            }
            
            return sendResponse($availability);
        } catch (Exception $e) {
            handleError($e->getMessage(), 500);
        }
    }

    // PUT /api/doctors/{id}/availability
    public function updateAvailability($data) {
        try {
            $this->conn->begin_transaction();

            // First, clear existing availability
            $query = "DELETE FROM doctor_availability WHERE doctor_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->doctorId);
            $stmt->execute();

            // Insert new availability
            $query = "INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) 
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);

            foreach ($data as $slot) {
                $stmt->bind_param("isssi", 
                    $this->doctorId,
                    $slot['day_of_week'],
                    $slot['start_time'],
                    $slot['end_time'],
                    $slot['is_available']
                );
                $stmt->execute();
            }

            $this->conn->commit();
            return sendResponse(['message' => 'Availability updated successfully']);
        } catch (Exception $e) {
            $this->conn->rollback();
            handleError($e->getMessage(), 500);
        }
    }

    // GET /api/doctors/{id}/availability/slots
    public function getAvailableSlots($date) {
        try {
            // Get day of week from date
            $dayOfWeek = date('l', strtotime($date));
            
            // Get availability for that day
            $query = "SELECT start_time, end_time 
                     FROM doctor_availability 
                     WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("is", $this->doctorId, $dayOfWeek);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return sendResponse(['slots' => [], 'message' => 'No availability for this day']);
            }

            // Get booked appointments for that date
            $query = "SELECT appointment_time 
                     FROM appointments 
                     WHERE doctor_id = ? AND DATE(appointment_date) = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("is", $this->doctorId, $date);
            $stmt->execute();
            $bookedSlots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Generate available time slots
            $availability = $result->fetch_assoc();
            $slots = $this->generateTimeSlots(
                $availability['start_time'],
                $availability['end_time'],
                $bookedSlots
            );
            
            return sendResponse(['slots' => $slots]);
        } catch (Exception $e) {
            handleError($e->getMessage(), 500);
        }
    }

    private function generateTimeSlots($startTime, $endTime, $bookedSlots) {
        $slots = [];
        $current = strtotime($startTime);
        $end = strtotime($endTime);
        $interval = 30 * 60; // 30 minutes in seconds

        while ($current < $end) {
            $timeSlot = date('H:i:s', $current);
            if (!in_array($timeSlot, array_column($bookedSlots, 'appointment_time'))) {
                $slots[] = $timeSlot;
            }
            $current += $interval;
        }

        return $slots;
    }
}
