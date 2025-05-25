<?php
require_once '../config.php';

class DoctorAPI {
    private $conn;
    private $doctorId;

    public function __construct($conn, $doctorId) {
        $this->conn = $conn;
        $this->doctorId = $doctorId;
    }

    // GET /api/doctors/{id}/profile
    public function getProfile() {
        try {
            $query = "SELECT u.full_name, u.profile_image, u.email, u.phone, u.date_of_birth, u.gender, 
                             u.address, u.city, u.state, u.zip_code,
                             d.first_name, d.last_name, d.specialization, d.sub_specialties, 
                             d.years_of_experience, d.medical_license_number, d.npi_number, 
                             d.education_and_training
                      FROM users u
                      JOIN doctor_details d ON u.user_id = d.user_id
                      WHERE u.user_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->doctorId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                return sendResponse($result->fetch_assoc());
            }
            
            handleError("Doctor not found", 404);
        } catch (Exception $e) {
            handleError($e->getMessage(), 500);
        }
    }

    // PUT /api/doctors/{id}/profile
    public function updateProfile($data) {
        try {
            $this->conn->begin_transaction();

            // Update users table
            $query = "UPDATE users SET 
                     first_name = ?, last_name = ?, email = ?, phone = ?, 
                     date_of_birth = ?, gender = ?, address = ?, city = ?, 
                     state = ?, zip_code = ? 
                     WHERE user_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "ssssssssssi",
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['date_of_birth'],
                $data['gender'],
                $data['address'],
                $data['city'],
                $data['state'],
                $data['zip_code'],
                $this->doctorId
            );
            $stmt->execute();

            // Update doctor_details table
            $query = "UPDATE doctor_details SET 
                     specialization = ?, sub_specialties = ?, 
                     years_of_experience = ?, medical_license_number = ?, 
                     npi_number = ?, education_and_training = ? 
                     WHERE user_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "ssisssi",
                $data['specialization'],
                $data['sub_specialties'],
                $data['years_of_experience'],
                $data['medical_license_number'],
                $data['npi_number'],
                $data['education_and_training'],
                $this->doctorId
            );
            $stmt->execute();

            $this->conn->commit();
            return sendResponse(['message' => 'Profile updated successfully']);
        } catch (Exception $e) {
            $this->conn->rollback();
            handleError($e->getMessage(), 500);
        }
    }

    // POST /api/doctors/{id}/profile/image
    public function updateProfileImage($file) {
        try {
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                handleError('Invalid file upload');
            }

            $uploadDir = '../../images/doctors/';
            $fileName = basename($file['name']);
            $targetFilePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                $query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("si", $fileName, $this->doctorId);
                $stmt->execute();

                return sendResponse(['message' => 'Profile image updated successfully']);
            }

            handleError('Error uploading file');
        } catch (Exception $e) {
            handleError($e->getMessage(), 500);
        }
    }

    // DELETE /api/doctors/{id}/profile/image
    public function removeProfileImage() {
        try {
            $defaultImage = 'default.png';
            $query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("si", $defaultImage, $this->doctorId);
            $stmt->execute();

            return sendResponse(['message' => 'Profile image removed successfully']);
        } catch (Exception $e) {
            handleError($e->getMessage(), 500);
        }
    }
}
