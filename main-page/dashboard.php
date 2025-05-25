<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'appointment_system';

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user details
$stmt = $conn->prepare("SELECT full_name, email, account_type FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Store user details in session
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['account_type'] = $user['account_type'];
$_SESSION['email'] = $user['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | AppointMed</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Main Container */
        .dashboard-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Header Section */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        
        .welcome-section {
            margin-bottom: 20px;
        }
        
        .greeting {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .greeting span {
            color: #3498db;
            font-weight: 600;
        }
        
        .welcome-text {
            color: #666;
            font-size: 16px;
        }
        
        .user-badge {
            background-color: #e8f4fc;
            color: #3498db;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .user-badge i {
            margin-right: 8px;
        }
        
        /* Cards Section */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .card-icon.profile {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .card-icon.account {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .card-icon.help {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .card-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .card-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .card-link {
            display: inline-block;
            color: #3498db;
            font-weight: 500;
            text-decoration: none;
            font-size: 14px;
        }
        
        .card-link:hover {
            text-decoration: underline;
        }
        
        /* User Info Section */
        .info-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cards-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Reuse your header from login.php -->
    <header>
        <nav class="navbar">
            <div class="logo">
                <img src="images/logo.png" alt="AppointMed Logo">
                <span>Appoint<span class="highlight">Med</span></span>
            </div>
            <div class="nav-container" id="nav-container">
                <ul class="nav-links">
                    <li><a href="index.html">Home</a></li>
                    <li><a href="doctors.html">Doctors</a></li>
                    <li><a href="profile.php" class="active">Profile</a></li>
                </ul>
                <div class="auth-buttons">
                    <a href="logout.php" class="logout">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="dashboard-header">
                <div class="welcome-section">
                    <h1 class="greeting">Welcome back, <span><?php echo htmlspecialchars($user['full_name']); ?></span></h1>
                    <p class="welcome-text">Here's what's happening with your account today</p>
                </div>
                <span class="user-badge">
                    <i class="fas fa-user-tag"></i>
                    <?php echo htmlspecialchars($user['account_type']); ?> Account
                </span>
            </div>
            
            <!-- Quick Action Cards -->
            <div class="cards-container">
                <div class="card">
                    <div class="card-icon profile">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h3 class="card-title">Complete Your Profile</h3>
                    <p class="card-text">Add more details to help us personalize your experience.</p>
                    <a href="profile.php" class="card-link">Update profile →</a>
                </div>
                
                <div class="card">
                    <div class="card-icon account">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="card-title">Account Security</h3>
                    <p class="card-text">Update your password and manage account security settings.</p>
                    <a href="security.php" class="card-link">Security settings →</a>
                </div>
                
                <div class="card">
                    <div class="card-icon help">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3 class="card-title">Need Help?</h3>
                    <p class="card-text">Visit our help center or contact our support team.</p>
                    <a href="help.php" class="card-link">Get help →</a>
                </div>
            </div>
            
            <!-- User Information Section -->
            <div class="info-section">
                <h2 class="section-title"><i class="fas fa-info-circle"></i> Your Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Account Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['account_type']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value"><?php echo date('F Y'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Coming Soon Section -->
            <div class="info-section">
                <h2 class="section-title"><i class="fas fa-calendar-plus"></i> Appointments</h2>
                <div style="text-align: center; padding: 30px 0;">
                    <i class="fas fa-calendar" style="font-size: 50px; color: #3498db; margin-bottom: 20px;"></i>
                    <h3 style="color: #333; margin-bottom: 10px;">Appointments Coming Soon</h3>
                    <p style="color: #666; max-width: 500px; margin: 0 auto;">
                        We're working on the appointments system. Soon you'll be able to book and manage
                        your medical appointments directly from here.
                    </p>
                    <a href="doctors.html" style="display: inline-block; margin-top: 20px; 
                       padding: 10px 20px; background-color: #3498db; color: white; 
                       border-radius: 5px; text-decoration: none;">Browse Doctors</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Reuse your footer from login.php -->
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
        // Mobile menu toggle (reuse from your existing code)
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('nav-container').classList.toggle('active');
        });
        
        // Add active class to current nav item
        document.querySelectorAll('.nav-links a').forEach(link => {
            if (link.href === window.location.href) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>