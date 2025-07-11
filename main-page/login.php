<?php
session_start();
require_once '../config/database.php';

// Check for remember me cookie
if (isset($_COOKIE['remember_email']) && !isset($_SESSION['user_id'])) {
    $_SESSION['login_email'] = $_COOKIE['remember_email'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['account_type'] = $user['account_type'];
        
        // Set remember me cookie if checked
        if ($remember) {
            $cookie_expiry = time() + (30 * 24 * 60 * 60); // 30 days
            setcookie('remember_email', $email, $cookie_expiry, '/');
        } else {
            // Clear remember me cookie if not checked
            if (isset($_COOKIE['remember_email'])) {
                setcookie('remember_email', '', time() - 3600, '/');
            }
        }
        
        // Redirect based on account type
        switch($user['account_type']) {
            case 'Patient':
                header("Location: ../dashboard/patient/index.php");
                break;
            case 'Doctor':
                header("Location: ../dashboard/doctor/index.php");
                break;
            case 'Admin':
                header("Location: ../dashboard/admin/index.php");
                break;
        }
        exit();
    } else {
        $_SESSION['login_error'] = "Incorrect email or password";
        $_SESSION['login_email'] = $email;
        header("Location: login.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | AppointMed</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" href="images/logo.png" type="my_logo">
  <style>
    main {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: calc(100vh - 60px);
      padding: 20px;
      background-color: #f8f9fa;
    }

    .login-container {
      width: 100%;
      max-width: 450px;
      margin: 0 auto;
    }

    .login-card {
      background-color: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      padding: 40px;
      text-align: center;
    }

    .login-card h1 {
      font-size: 28px;
      font-weight: 700;
      color: #333;
      margin-bottom: 10px;
    }

    .subtitle {
      color: #666;
      font-size: 15px;
      margin-bottom: 30px;
    }

    .login-form {
      text-align: left;
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #333;
      margin-bottom: 8px;
    }

    .password-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .forgot-password {
      font-size: 13px;
      color: #3498db;
      text-decoration: none;
      font-weight: 500;
    }

    .forgot-password:hover {
      text-decoration: underline;
    }

    .input-with-icon {
      position: relative;
    }

    .input-with-icon input {
      width: 100%;
      padding: 12px 40px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s ease;
    }

    .input-with-icon input:focus {
      outline: none;
      border-color: #3498db;
      box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
    }

    .input-with-icon i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }

    .input-with-icon .toggle-password {
      left: auto;
      right: 14px;
      cursor: pointer;
    }

    .remember-me {
      margin-bottom: 24px;
    }

    .remember-me input {
      margin-right: 5px;
      width: 14px;
      height: 14px;
      cursor: pointer;
      vertical-align: middle;
      align-items: center;
    }

    .remember-me label {
      font-size: 14px;
      color: #666;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      margin: 0;
    }

    .login-btn {
      width: 100%;
      padding: 12px;
      background-color: #3498db;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .login-btn:hover {
      background-color: #2980b9;
    }

    .signup-prompt {
      margin-top: 24px;
      font-size: 14px;
      color: #666;
    }

    .signup-prompt a {
      color: #3498db;
      font-weight: 600;
      text-decoration: none;
      margin-left: 5px;
    }

    .signup-prompt a:hover {
      text-decoration: underline;
    }

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      overflow: auto;
    }
    
    .modal-content {
      background-color: #fff;
      margin: 15% auto;
      padding: 25px;
      border-radius: 8px;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      text-align: center;
      position: relative;
    }
    
    .modal-icon {
      font-size: 50px;
      color: #e74c3c;
      margin-bottom: 15px;
    }
    
    .modal-title {
      font-size: 22px;
      font-weight: 600;
      margin-bottom: 10px;
      color: #333;
    }
    
    .modal-message {
      font-size: 16px;
      color: #666;
      margin-bottom: 20px;
    }
    
    .modal-btn {
      background-color: #3498db;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 10px 20px;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    
    .modal-btn:hover {
      background-color: #2980b9;
    }
    
    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      font-size: 22px;
      color: #aaa;
      cursor: pointer;
    }
    
    .close-modal:hover {
      color: #333;
    }
  </style>
</head>

<body>

  <!-- Error Modal -->
  <div id="errorModal" class="modal">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <div class="modal-icon">
        <i class="fas fa-exclamation-circle"></i>
      </div>
      <h3 class="modal-title">Login Failed</h3>
      <p class="modal-message" id="modalMessage">Incorrect email or password</p>
      <button class="modal-btn" id="modalOkBtn">OK</button>
    </div>
  </div>

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
          <li><a href="index.php">Home</a></li>
          <li><a href="doctors.php">Doctors</a></li>
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
    <div class="login-container">
      <div class="login-card">
        <h1>Welcome Back</h1>
        <p class="subtitle">Sign in to access your appointmed account</p>

        <form class="login-form" action="login.php" method="post">
              <?php if (isset($_SESSION['login_error'])): ?>
                  <script>
                      document.addEventListener('DOMContentLoaded', function() {
                          document.getElementById('modalMessage').textContent = "<?php echo addslashes($_SESSION['login_error']); ?>";
                          document.getElementById('errorModal').style.display = 'block';
                      });
                  </script>
                  <?php unset($_SESSION['login_error']); ?>
              <?php endif; ?>
              
              <div class="form-group">
                  <label for="email">Email Address</label>
                  <div class="input-with-icon">
                      <i class="fas fa-envelope"></i>
                      <input type="email" id="email" name="email" placeholder="Enter your email" required
                            value="<?php echo isset($_SESSION['login_email']) ? htmlspecialchars($_SESSION['login_email']) : ''; ?>">
                  </div>
              </div>

              <div class="form-group">
                  <div class="password-header">
                      <label for="password">Password</label>
                      <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                  </div>
                  <div class="input-with-icon">
                      <i class="fas fa-lock"></i>
                      <input type="password" id="password" name="password" placeholder="Enter your password" required>
                      <i class="fas fa-eye-slash toggle-password" id="toggle-password"></i>
                  </div>
              </div>

              <div class="remember-me">
                  <input type="checkbox" id="remember" name="remember" <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                  <label for="remember">Remember me</label>
              </div>

              <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="signup-prompt">
          <span>Don't have an account?</span>
          <a href="signup.php">Sign up</a>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
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
          <li><a href="index.php">Home</a></li>
          <li><a href="doctors.php">Doctors</a></li>
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

  <script src="script/script.js"></script>
  
  <script>

    // Modal functionality
    const modal = document.getElementById('errorModal');
    const modalOkBtn = document.getElementById('modalOkBtn');
    const closeModal = document.querySelector('.close-modal');
    
    function closeErrorModal() {
        modal.style.display = 'none';
    }
    
    if (modalOkBtn) {
        modalOkBtn.addEventListener('click', closeErrorModal);
    }
    
    if (closeModal) {
        closeModal.addEventListener('click', closeErrorModal);
    }
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeErrorModal();
        }
    });
    
    // Check if there's an error to show modal on page load
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            document.getElementById('modalMessage').textContent = urlParams.get('error');
            modal.style.display = 'block';
        }
    });
  </script>


</body>

</html>