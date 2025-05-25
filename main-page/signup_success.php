<?php
// Start session
session_start();

// Check if registration was successful
if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    header("Location: signup.php");
    exit();
}

// Clear the success flag
unset($_SESSION['registration_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful | AppointMed</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .success-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            text-align: center;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #4CAF50;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .success-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Include your header from signup.php -->
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
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Registration Successful!</h1>
            <p>Thank you for creating an account with AppointMed. You can now log in using your credentials.</p>
            <a href="login.php" class="success-btn">Go to Login Page</a>
        </div>
    </main>

    <!-- Include your footer from signup.php -->
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
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>