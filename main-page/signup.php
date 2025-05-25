<?php
session_start();
require_once '../config/database.php';

function get_value($field, $default = '') {
    global $form_data;
    return isset($form_data[$field]) ? htmlspecialchars($form_data[$field]) : $default;
}

// Initialize variables
$errors = [];
if (!isset($_SESSION['form_data'])) {
    $_SESSION['form_data'] = [];
}
$form_data = $_SESSION['form_data'];

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    if (empty($_POST['fullname'])) {
        $errors['fullname'] = 'Full name is required';
    }

    if (empty($_POST['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($_POST['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($_POST['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    if ($_POST['password'] !== $_POST['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (!isset($_POST['terms'])) {
        $errors['terms'] = 'You must agree to the terms';
    }

    // Handle profile image
    $profile_image = 'images/default.png'; // Default image
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        // Directory for uploaded images
        $upload_dir = ($_POST['account_type'] == 'Doctor') ? "../images/doctors/" : "../images/patients/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create the directory if it doesn't exist
        }
        $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png']; // Allowed file types

        if (in_array($file_ext, $allowed)) {
            $profile_image = uniqid() . '.' . $file_ext; // Generate a unique filename
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $profile_image);
        } else {
            $errors['profile_image'] = 'Invalid file type. Only JPG, JPEG, and PNG are allowed.';
        }
    }

    // If no errors, process the form
    if (empty($errors)) {
        try {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Insert user into database
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, account_type, profile_image, phone_number, date_of_birth, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", 
                $_POST['fullname'],
                $_POST['email'],
                $password_hash,
                $_POST['account_type'],
                $profile_image,
                $_POST['phone_number'],
                $_POST['date_of_birth'],
                $_POST['gender']
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = "Account created successfully! Please login.";
                header("Location: login.php");
                exit();
            } else {
                throw new Exception("Error creating account");
            }

        } catch (Exception $e) {
            $errors['database'] = "Error creating account: " . $e->getMessage();
        }
    }

    // Store form data for repopulation
    $_SESSION['form_data'] = $_POST;
    $_SESSION['errors'] = $errors;

    if (!empty($errors)) {
        header("Location: signup.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Your existing head content -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup | AppointMed</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="my_logo">
    <style>
        /* Signup Form Styles */
        .signup-container {
            max-width: 450px;
            margin: 40px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .signup-header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .signup-header p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            color: #333;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #3498db;
        }

        .form-group .input-wrapper {
            position: relative;
        }

        .form-group .input-wrapper i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #999;
        }

        .input-wrapper #toggle-password,
        .input-wrapper #toggle-confirm-password {

            margin-left: 340px;
        }

        .form-group .input-wrapper input {
            padding-left: 40px;
        }

        .form-group .input-wrapper .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
        }

        .password-requirements {
            margin-top: 5px;
            margin-left: 5px;
            margin-top: 10px;
        }

        .requirement {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: #777;
            margin-bottom: 3px;
        }

        .requirement i {
            margin-right: 5px;
            font-size: 10px;
        }

        .requirement.valid {
            color: #2ecc71;
        }

        .requirement.invalid {
            color: #e74c3c;
        }

        .account-type {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .account-type label {
            display: flex;
            align-items: center;
            cursor: pointer;

        }

        .account-type input {
            margin-right: 10px;

        }

        .terms {

            margin-bottom: 20px;
            font-size: 13px;
        }

        .terms input {
            margin-right: 10px;
            margin-top: 2px;
        }

        .terms a {
            color: #3498db;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .signup-btn {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .signup-btn:hover {
            background-color: #2980b9;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Error styling */
        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        input.error {
            border-color: #e74c3c;
        }

        /* Profile Image Upload Styles */
        .image-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 2px dashed #ddd;
            margin-top: 10px;
            overflow: hidden;
            display: none;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        input[type="file"] {
            padding: 8px 40px;
        }

        /* Add this to your existing CSS */
        .error-messages {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success-message {
            background-color: #dcfce7;
            border: 1px solid #22c55e;
            color: #166534;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        /* Media queries for responsive design */

        /* For desktop and large tablets - 1024px and below */
        @media screen and (max-width: 1024px) {
            .signup-container {
                max-width: 400px;
                margin: 30px auto;
            }

            .input-wrapper #toggle-password,
            .input-wrapper #toggle-confirm-password {
                margin-left: 290px;
            }
        }

        /* For tablets - 768px and below */
        @media screen and (max-width: 768px) {
            .signup-container {
                max-width: 90%;
                padding: 25px;
                margin: 25px auto;
            }

            .signup-header h1 {
                font-size: 22px;
            }

            .input-wrapper #toggle-password,
            .input-wrapper #toggle-confirm-password {
                margin-left: 590px;
            }

        }

        /* For small tablets and large phones - 600px and below */
        @media screen and (max-width: 600px) {
            .signup-container {
                max-width: 95%;
                padding: 20px;
                margin: 20px auto;
                box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
            }

            .signup-header {
                margin-bottom: 20px;
            }

            .signup-header h1 {
                font-size: 20px;
            }

            .signup-header p {
                font-size: 13px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group input {
                padding: 10px 15px;
            }

            .account-type {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* For small phones - 480px and below */
        @media screen and (max-width: 480px) {

            .signup-container {
                max-width: 100%;
                margin: 30px 30px;
                padding: 15px;
                border-radius: 8px;
            }

            .signup-header h1 {
                font-size: 18px;
            }

            .signup-header p {
                font-size: 12px;
            }

            .input-wrapper #toggle-password,
            .input-wrapper #toggle-confirm-password {
                margin-left: 290px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input {
                font-size: 13px;
                padding: 8px 15px;
            }

            .terms {
                font-size: 12px;
            }

            .signup-btn {
                padding: 10px;
                font-size: 14px;
            }

            .login-link {
                font-size: 13px;
            }

            .password-requirements {
                font-size: 11px;
            }

            .requirement {
                font-size: 11px;
            }

            .form-group .account-type label {
                margin-left: -290px;
                padding: 0px 290px;
            }

        }



    </style>
</head>
<body>

    <?php if (isset($_SESSION['errors'])): ?>
        <div class="error-messages">
            <?php 
            foreach ($_SESSION['errors'] as $error) {
                echo "<p>" . htmlspecialchars($error) . "</p>";
            }
            unset($_SESSION['errors']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-message">
            <?php 
            echo htmlspecialchars($_SESSION['success']);
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <header>
        <nav class="navbar">
            <div class="logo">
                <img src="images/logo.png" alt="AppointMed Logo">
                <span>Appoint<span class="highlight">Med</span></span>
            </div>
            <!-- Hamburger Icon -->
            <div class="menu-toggle" id="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
            <div class="nav-container" id="nav-container">
                <ul class="nav-links">
                    <li><a href="index.html">Home</a></li>
                    <li><a href="doctors.html">Doctors</a></li>
                    <li><a href="services.html">Services</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="contacts.html">Contact</a></li>
                </ul>
                <div class="auth-buttons">
                    <a href="login.php" class="login">Login</a>
                    <a href="signup.php" class="signup">Signup</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="signup-container">
            <div class="signup-header">
                <h1>Create Your Account</h1>
                <p>Join appointmed to easily book appointments with top doctors and access personalized medical services.</p>
            </div>
            
            <!-- Form with both PHP and JavaScript handling -->
            <form id="signup-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">

                <!-- Profile Image Field -->
                <div class="form-group">
                    <label for="profile_image">Profile Picture</label>
                    <div class="input-wrapper">
                        <i class="fas fa-image"></i>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" class="<?php echo isset($errors['profile_image']) ? 'error' : ''; ?>">
                    </div>
                    <?php if (isset($errors['profile_image'])): ?>
                        <div class="error-message"><?php echo $errors['profile_image']; ?></div>
                    <?php endif; ?>
                    <div class="image-preview" id="imagePreview"></div>
                </div>

                <!-- Full Name Field -->
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="fullname" name="fullname" value="<?php echo get_value('fullname'); ?>" placeholder="Enter your full name">
                    </div>
                    <?php if (isset($errors['fullname'])): ?>
                        <div class="error-message"><?php echo $errors['fullname']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Email Field -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" value="<?php echo get_value('email'); ?>" placeholder="Enter your email address">
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-message"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Phone Number Field -->
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone_number" value="<?php echo get_value('phone_number'); ?>" placeholder="Enter your phone number">
                    </div>
                    <?php if (isset($errors['phone_number'])): ?>
                        <div class="error-message"><?php echo $errors['phone_number']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Date of Birth Field -->
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar"></i>
                        <input type="date" id="dob" name="date_of_birth" value="<?php echo get_value('date_of_birth'); ?>">
                    </div>
                </div>

                <!-- Gender Field -->
                <div class="form-group">
                    <label>Gender</label>
                    <div class="account-type">
                        <label>
                            <input type="radio" name="gender" value="Male" <?php echo (get_value('gender', 'Male') === 'Male') ? 'checked' : ''; ?>> Male
                        </label>
                        <label>
                            <input type="radio" name="gender" value="Female" <?php echo (get_value('gender') === 'Female') ? 'checked' : ''; ?>> Female
                        </label>
                        <label>
                            <input type="radio" name="gender" value="Other" <?php echo (get_value('gender') === 'Other') ? 'checked' : ''; ?>> Other
                        </label>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Create a password">
                        <i class="fas fa-eye toggle-password" id="toggle-password"></i>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="error-message"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                    <div class="password-requirements">
                        <div class="requirement" id="length-req">
                            <i class="fa-regular fa-circle"></i> At least 8 characters
                        </div>
                        <div class="requirement" id="uppercase-req">
                            <i class="fa-regular fa-circle"></i> At least one uppercase letter
                        </div>
                        <div class="requirement" id="lowercase-req">
                            <i class="fa-regular fa-circle"></i> At least one lowercase letter
                        </div>
                        <div class="requirement" id="number-req">
                            <i class="fa-regular fa-circle"></i> At least one number
                        </div>
                    </div>
                </div>

                <!-- Confirm Password Field -->
                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password">
                        <i class="fas fa-eye toggle-password" id="toggle-confirm-password"></i>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="error-message"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>

                <!-- Account Type -->
                <div class="form-group">
                    <label>Account Type</label>
                    <div class="account-type">
                        <label>
                            <input type="radio" name="account_type" value="Patient" <?php echo (get_value('account_type', 'Patient') === 'Patient') ? 'checked' : ''; ?>> Patient
                        </label>
                        <label>
                            <input type="radio" name="account_type" value="Doctor" <?php echo (get_value('account_type') === 'Doctor') ? 'checked' : ''; ?>> Doctor
                        </label>
                        <label>
                            <input type="radio" name="account_type" value="Admin" <?php echo (get_value('account_type') === 'Admin') ? 'checked' : ''; ?>> Admin
                        </label>
                    </div>
                </div>

                <!-- Terms Checkbox -->
                <div class="terms">
                    <input type="checkbox" id="terms" name="terms" <?php echo isset($form_data['terms']) ? 'checked' : ''; ?>>
                    <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                </div>
                <?php if (isset($errors['terms'])): ?>
                    <div class="error-message"><?php echo $errors['terms']; ?></div>
                <?php endif; ?>

                <!-- Submit Button -->
                <button type="submit" class="signup-btn">Create Account</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-logo">
                <img src="images/logo.png" alt="AppointMed Logo" class="footer-logo-img">
                <p class="footer-tagline">Your trusted healthcare appointment system, connecting patients with the best
                    medical professionals.</p>
                <div class="social-icons">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.html">Home</a></li>
                    <li><a href="doctors.html">Doctors</a></li>
                    <li><a href="services.html">Services</a></li>
                    <li><a href="contacts.html">Contacts</a></li>
                </ul>
            </div>

            <div class="footer-services">
                <h3>Services</h3>
                <ul>
                    <li>Cardiology</li>
                    <li>Neurology</li>
                    <li>Pediatrics</li>
                    <li>Dermatology</li>
                    <li>Laboratory Services</li>
                    <li>Pharmacy</li>
                </ul>
            </div>

            <div class="footer-contact">
                <h3>Contact Us</h3>
                <ul class="contact-info">
                    <li><i class="fas fa-map-marker-alt"></i> 123 Healthcare Ave, Medical Center</li>
                    <li><i class="fas fa-phone"></i> +639 9876 5432</li>
                    <li><i class="fas fa-envelope"></i>info@appointmed.com</li>
                    <li><i class="fas fa-clock"></i> Mon-Fri: 8:00 AM - 7:00 PM</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="copyright">
                © 2025 AppointMed. All rights reserved.
            </div>

        </div>
    </footer>

    <script>
    // Password toggle functionality
    document.getElementById('toggle-password').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    document.getElementById('toggle-confirm-password').addEventListener('click', function() {
        const confirmInput = document.getElementById('confirm-password');
        const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Password validation
    const passwordInput = document.getElementById('password');
    const lengthReq = document.getElementById('length-req');
    const uppercaseReq = document.getElementById('uppercase-req');
    const lowercaseReq = document.getElementById('lowercase-req');
    const numberReq = document.getElementById('number-req');

    passwordInput.addEventListener('input', validatePassword);

    function validatePassword() {
        const password = passwordInput.value;

        // Validate length
        if (password.length >= 8) {
            lengthReq.classList.add('valid');
            lengthReq.classList.remove('invalid');
            lengthReq.querySelector('i').classList.replace('fa-circle', 'fa-check-circle');
        } else {
            lengthReq.classList.remove('valid');
            lengthReq.classList.add('invalid');
            lengthReq.querySelector('i').classList.replace('fa-check-circle', 'fa-circle');
        }

        // Validate uppercase
        if (/[A-Z]/.test(password)) {
            uppercaseReq.classList.add('valid');
            uppercaseReq.classList.remove('invalid');
            uppercaseReq.querySelector('i').classList.replace('fa-circle', 'fa-check-circle');
        } else {
            uppercaseReq.classList.remove('valid');
            uppercaseReq.classList.add('invalid');
            uppercaseReq.querySelector('i').classList.replace('fa-check-circle', 'fa-circle');
        }

        // Validate lowercase
        if (/[a-z]/.test(password)) {
            lowercaseReq.classList.add('valid');
            lowercaseReq.classList.remove('invalid');
            lowercaseReq.querySelector('i').classList.replace('fa-circle', 'fa-check-circle');
        } else {
            lowercaseReq.classList.remove('valid');
            lowercaseReq.classList.add('invalid');
            lowercaseReq.querySelector('i').classList.replace('fa-check-circle', 'fa-circle');
        }

        // Validate number
        if (/[0-9]/.test(password)) {
            numberReq.classList.add('valid');
            numberReq.classList.remove('invalid');
            numberReq.querySelector('i').classList.replace('fa-circle', 'fa-check-circle');
        } else {
            numberReq.classList.remove('valid');
            numberReq.classList.add('invalid');
            numberReq.querySelector('i').classList.replace('fa-check-circle', 'fa-circle');
        }
    }

    // Form validation
    function validateForm() {
        let isValid = true;
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm-password');
        const termsCheckbox = document.getElementById('terms');

        // Validate fullname
        if (fullnameInput.value.trim() === '') {
            document.getElementById('fullname-error').style.display = 'block';
            fullnameInput.classList.add('error');
            isValid = false;
        }

        // Validate email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value)) {
            document.getElementById('email-error').style.display = 'block';
            emailInput.classList.add('error');
            isValid = false;
        }

        // Validate password
        const password = passwordInput.value;
        const isPasswordValid = password.length >= 8 &&
                               /[A-Z]/.test(password) &&
                               /[a-z]/.test(password) &&
                               /[0-9]/.test(password);

        if (!isPasswordValid) {
            document.getElementById('password-error').style.display = 'block';
            passwordInput.classList.add('error');
            isValid = false;
        }

        // Validate confirm password
        if (confirmInput.value !== passwordInput.value) {
            document.getElementById('confirm-password-error').style.display = 'block';
            confirmInput.classList.add('error');
            isValid = false;
        }

        // Validate terms
        if (!termsCheckbox.checked) {
            document.getElementById('terms-error').style.display = 'block';
            isValid = false;
        }

        return isValid;


        // Validate profile image
        const profileImage = document.getElementById('profile_image');
        if (profileImage.files.length > 0) {
            const file = profileImage.files[0];
            const fileType = file.type;
            const validImageTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            
            if (!validImageTypes.includes(fileType)) {
                alert('Please upload a valid image file (JPEG, PNG, or JPG)');
                isValid = false;
            }

            if (file.size > 5000000) { // 5MB limit
                alert('Image file size must be less than 5MB');
                isValid = false;
            }
        }

        return isValid;
    }


    // Clear errors on input
    document.getElementById('fullname').addEventListener('input', function() {
        document.getElementById('fullname-error').style.display = 'none';
        this.classList.remove('error');
    });

    document.getElementById('email').addEventListener('input', function() {
        document.getElementById('email-error').style.display = 'none';
        this.classList.remove('error');
    });

    document.getElementById('confirm-password').addEventListener('input', function() {
        document.getElementById('confirm-password-error').style.display = 'none';
        this.classList.remove('error');
    });

    document.getElementById('terms').addEventListener('change', function() {
        document.getElementById('terms-error').style.display = 'none';
    });

    document.getElementById('profile_image').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        preview.style.display = 'block';

        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
   });
    </script>
</body>
</html>