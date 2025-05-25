
<?php
/**
 * Improved availability data parser with better error handling
 */
function getAvailability($availabilityData) {
    // Return default message if data is empty
    if (empty($availabilityData)) {
        return '<i class="fas fa-calendar-alt"></i> Available: Mon, Wed, Fri<br>
                <i class="fas fa-clock"></i> Hours: 9:00 AM - 5:00 PM';
    }
    
    // First, fix common JSON formatting issues
    $fixedData = str_replace(
        ['working_hours', 'working_ays'], 
        ['working_hours', 'working_days'],
        $availabilityData
    );

    // Try to decode the JSON
    $availability = json_decode($fixedData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || empty($availability)) {
        error_log("Invalid availability data: " . $availabilityData . " - JSON error: " . json_last_error_msg());
        return '<i class="fas fa-calendar-alt"></i> Contact for availability';
    }

    // Extract data with fallbacks
    $workingDays = $availability['working_days'] ?? ['Monday', 'Wednesday', 'Friday'];
    $startTime = isset($availability['working_hours']['start_time']) ? $availability['working_hours']['start_time'] : '09:00';
    $endTime = isset($availability['working_hours']['end_time']) ? $availability['working_hours']['end_time'] : '17:00';

    // Format days
    $shortDays = array_map(function($day) {
        return substr($day, 0, 3);
    }, $workingDays);

    // Format times (with validation)
    try {
        $formattedStart = date("g:i A", strtotime($startTime));
        $formattedEnd = date("g:i A", strtotime($endTime));
    } catch (Exception $e) {
        error_log("Error formatting time: " . $e->getMessage());
        $formattedStart = '9:00 AM';
        $formattedEnd = '5:00 PM';
    }

    // Build output
    $output = '<i class="fas fa-calendar-alt"></i> Available: ' . implode(', ', $shortDays) . '<br>';
    $output .= '<i class="fas fa-clock"></i> Hours: ' . $formattedStart . ' - ' . $formattedEnd;
    
    // Add break time if exists and properly formatted
    if (isset($availability['break_time']['start_time']) && 
        isset($availability['break_time']['duration']) &&
        !empty($availability['break_time']['start_time']) && 
        !empty($availability['break_time']['duration'])) {
        
        try {
            $breakStart = date("g:i A", strtotime($availability['break_time']['start_time']));
            $breakDuration = $availability['break_time']['duration'];
            $output .= '<br><i class="fas fa-coffee"></i> Break: ' . $breakStart . ' (' . $breakDuration . ' mins)';
        } catch (Exception $e) {
            error_log("Error formatting break time: " . $e->getMessage());
        }
    }

    return $output;
}

/**
 * Helper function to determine the correct image path
 */
function getDoctorImagePath($profileImage) {
    // Default image if none provided
    if (empty($profileImage)) {
        return 'images/doctors/default.png';  // Relative path from the current script
    }
    
    // Clean the filename (just in case it has any path components)
    $cleanFilename = basename($profileImage);
    
    // Path to doctors images directory - using relative path from current script location
    $doctorsImagePath = 'images/doctors/' . $cleanFilename;
    
    // For server-side validation, build the full path
    // __DIR__ gives us the directory of the current script
    $fullServerPath = __DIR__ . '/' . $doctorsImagePath;
    
    // Debug info for troubleshooting
    error_log("Looking for image at: " . $fullServerPath);
    
    // Check if file exists on server
    if (file_exists($fullServerPath)) {
        return $doctorsImagePath;
    } else {
        // Try a different relative path (one level up)
        $altPath = '../images/doctors/' . $cleanFilename;
        $altFullPath = dirname(__DIR__) . '/images/doctors/' . $cleanFilename;
        
        error_log("First path failed, trying: " . $altFullPath);
        
        if (file_exists($altFullPath)) {
            return $altPath;
        }
        
        // Log missing image and return default
        error_log("Doctor image not found: " . $fullServerPath);
        return 'images/doctors/default.png';
    }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AppointMed</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="my_logo">
    <style>
        /* Doctors Section - Home Page */
        .doctors-section {
            padding: 30px 20px;
            text-align: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .doctors-header {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 50px;
        }

        .doctors-header h2 {
            font-size: 32px;
            color: #1F2937;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .doctors-header p {
            font-size: 16px;
            color: #666666;
            line-height: 1.5;
        }

        .doctors-container {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .doctors-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(300px, 1fr));
            gap: 30px;
            justify-content: center;
            max-width: 1200px; /* Adjust based on your preferred max width */
            margin: 0 auto;
        }

        .doctor-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .doctor-card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            object-position: top center;
        }

        .doctor-info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .doctor-info h3 {
            font-size: 1.3rem;
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .specialty {
            color: #3498db;
            font-weight: 500;
            margin: 0 0 15px 0;
            font-size: 1rem;
        }

        .rating {
            color: #f1c40f;
            font-size: 1rem;
            margin: 0 0 10px 0;
        }

        .rating span {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .availability {
            color: #7f8c8d;
            margin: 10px 0 15px 0;
            font-size: 0.95rem;
            line-height: 1.5;
            flex-grow: 1;
        }

        .availability i {
            width: 20px;
            text-align: center;
            margin-right: 5px;
            color: #3498db;
        }

        .view-all {
            margin-top: 50px;
            margin-bottom: 50px;
        }

        .view-all-btn {
            padding: 10px 20px;
            border: 1px solid #3498db;
            border-radius: 8px;
            color: #3498db;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .view-all-btn:hover {
            background: #3498db;
            color: #fff;
        }

        .btn-appoint {
            display: block;
            background: #3498db;
            color: white;
            text-align: center;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
            margin-top: auto;
            width: 100%;
            box-sizing: border-box;
        }

        .btn-appoint:hover {
            background: #2980b9;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .doctor-card img {
                height: 250px;
            }
        }

        @media (max-width: 480px) {
            .doctors-cards {
                grid-template-columns: 1fr;
            }
            
            .doctor-card img {
                height: 220px;
            }
        }
    </style>
</head>

<body>

    <?php
    require_once __DIR__ . '/../config/database.php';

    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Fetch doctors
    $doctors = [];
    try {
        $query = "SELECT d.user_id, d.first_name, d.last_name, d.specialization, 
                         d.availability, u.profile_image, u.email
                  FROM doctor_details d
                  JOIN users u ON d.user_id = u.user_id
                  LIMIT 3";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Query error: " . $conn->error);
        }
        
        $doctors = $result->fetch_all(MYSQLI_ASSOC);
        
        // Debug output (remove in production)
        echo "<!-- Debug: Found " . count($doctors) . " doctors -->";
        
    } catch (Exception $e) {
        echo "<div class='error'>Error loading doctors: " . $e->getMessage() . "</div>";
        error_log("Error fetching doctors: " . $e->getMessage());
    }
    ?>

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

    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Find and Book a Doctor<br>Easily</h1>
            <p>Schedule appointments with top healthcare professionals.<br>
                Quick, easy, and secure booking system designed with your convenience in mind.</p>
            <a href="login.php" class="btn-book">Book Appointment</a>
        </div>
    </section>

    <form class="scheduler" method="GET" action="doctors.html">
        <h3>Quick Appointment Scheduler</h3>
        <div class="form-row">
            <select name="specialty" required>
                <option value="">Select specialty</option>
                <option>Cardiologist</option>
                <option>Neurologist</option>
                <option>Pediatrician</option>
                <option>Dermatologist</option>
                <option>Laboratory Services</option>
                <option>Pharmacy</option>
            </select>
            <input type="date" name="date" required>
        </div>
        <button type="submit">Search Availability</button>
    </form>


    <section class="doctors-section" id="doctors">
        <div class="doctors-header">
            <h2>Our Specialist Doctors</h2>
            <p>Meet our team of experienced healthcare professionals dedicated to providing you with the best medical care.</p>
        </div>

        <?php if (!empty($doctors)): ?>
            <div class="doctors-container">
                <div class="doctors-cards">
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="doctor-card">
                            <?php
                            // Get the correct image path using our helper function
                            $imagePath = getDoctorImagePath($doctor['profile_image'] ?? '');
                            
                            // Get availability for this doctor with improved function
                            $availabilityInfo = getAvailability($doctor['availability'] ?? '');
                            ?>
                            
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                alt="Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>"
                                loading="lazy"
                                onerror="this.onerror=null; this.src='images/doctors/default.png'; console.log('Image failed to load, using default');">
                            
                            <div class="doctor-info">
                                <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                                <p class="specialty"><?php 
                                    echo htmlspecialchars($doctor['specialization'] ?? $doctor['specialized'] ?? 'General Practitioner'); 
                                ?></p>
                                
                                <div class="rating">
                                    <?php
                                    $rating = rand(3, 5);
                                    $reviews = rand(50, 400);
                                    echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                    ?>
                                    <span>(<?php echo $reviews; ?> reviews)</span>
                                </div>
                                
                                <p class="availability">
                                    <?php echo $availabilityInfo; ?>
                                </p>
                                
                                <a href="login.php" class="btn-appoint">
                                    Book Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-doctors">
                <p>No doctors available at this time.</p>
                <?php if (isset($conn) && $conn->error): ?>
                    <p class="error">Database error: <?php echo htmlspecialchars($conn->error); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="view-all">
            <a href="doctors.php" class="view-all-btn">View All Doctors</a>
        </div>
    </section>

    <!--Medical Services Section-->
    <section class="medical-services" id="services">
        <div class="services-header">
            <h2>Our Medical Services</h2>
            <p>We offer a wide range of medical services to meet your healthcare needs with the highest standards of
                quality and care.</p>
        </div>

        <div class="services-container">
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h3>Cardiology</h3>
                <p>Comprehensive heart care services including diagnostics, treatment, and prevention of cardiovascular
                    diseases.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h3>Neurology</h3>
                <p>Specialized care for disorders of the nervous system, including the brain, spinal cord, and
                    peripheral nerves.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-baby"></i>
                </div>
                <h3>Pediatrics</h3>
                <p>Specialized medical care for infants, children, and adolescents, focusing on growth, development, and
                    health.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-allergies"></i>
                </div>
                <h3>Dermatology</h3>
                <p>Expert care for conditions affecting the skin, hair, and nails, from common to complex dermatological
                    issues.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3>Laboratory Services</h3>
                <p>Comprehensive diagnostic testing and laboratory services with quick and accurate results for better
                    healthcare.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <h3>Pharmacy</h3>
                <p>On-site pharmacy services providing prescription medications, over-the-counter products, and
                    medication counseling.</p>
            </div>
        </div>
    </section>

    <!-- Patient Testimonials Section -->
    <section class="testimonials-section">
        <div class="testimotinals-header">
            <h2>What Our Patients Say</h2>
            <p class="section-description">Hear from our satisfied patients about their experiences with our healthcare
                services and
                medical professionals.</p>
        </div>

        <div class="testimonials-container">
            <!-- Testimonial 1 -->
            <div class="testimonial-card">
                <div class="stars">★★★★★</div>
                <p class="quote">"The appointment system was incredibly easy to use. I was able to book my appointment
                    with Dr. Johnson in
                    minutes, and the reminder notifications were very helpful."</p>
                <div class="patient-info">
                    <img src="images/testimonial-1.png" alt="Rebecca Thompson" class="patient-image">
                    <div class="patient-details">
                        <span class="patient-name">Rebecca Thompson</span>
                        <span class="patient-type">Cardiology Patient</span>
                    </div>
                </div>
            </div>

            <!-- Testimonial 2 -->
            <div class="testimonial-card">
                <div class="stars">★★★★★</div>
                <p class="quote">"Dr. Chen is an exceptional neurologist. He took the time to explain my condition
                    thoroughly and created a
                    treatment plan that has significantly improved my quality of life."</p>
                <div class="patient-info">
                    <img src="images/testimonial-2.png" alt="James Wilson" class="patient-image">
                    <div class="patient-details">
                        <span class="patient-name">James Wilson</span>
                        <span class="patient-type">Neurology Patient</span>
                    </div>
                </div>
            </div>

            <!-- Testimonial 3 -->
            <div class="testimonial-card">
                <div class="stars">★★★★★</div>
                <p class="quote">"As a parent, I appreciate how Dr. Rodriguez makes my children feel comfortable during
                    their visits. The online
                    appointment system makes scheduling check-ups so convenient."</p>
                <div class="patient-info">
                    <img src="images/testimonial-3.png" alt="Olivia Martinez" class="patient-image">
                    <div class="patient-details">
                        <span class="patient-name">Olivia Martinez</span>
                        <span class="patient-type">Parent of Pediatric Patients</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section" id="contacts">
        <div class="cta-container">
            <div class="cta-content">
                <h2>Ready to Schedule Your Appointment?</h2>
                <p>Take the first step towards better health. Our online appointment system makes it easy to connect
                    with the right healthcare professional for your needs.</p>
                <div class="cta-buttons">
                    <a href="#" class="btn-book-now">Book Now</a>
                    <a href="contacts.html" class="btn-contact">Contact Us</a>
                </div>
            </div>
            <div class="cta-image">
                <img src="images/clinic.png" alt="Modern Clinic Interior">
            </div>
        </div>
    </section>

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

    <script src="script/script.js"></script>

</body>

</html>