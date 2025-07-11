<?php
require_once __DIR__ . '/../config/database.php';

// Function to parse doctor availability
function parseDoctorAvailability($availabilityJson) {
    $defaultAvailability = [
        'working_days' => ['Monday', 'Wednesday', 'Friday'],
        'working_hours' => [
            'start_time' => '08:00',
            'end_time' => '17:00'
        ],
        'break_time' => [
            'start_time' => '',
            'duration' => ''
        ]
    ];
    
    if (empty($availabilityJson)) {
        return $defaultAvailability;
    }
    
    $availability = json_decode($availabilityJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($availability)) {
        return $defaultAvailability;
    }
    
    // Merge data without considering 'enabled' flag
    $merged = array_merge($defaultAvailability, $availability);
    
    // Always ensure break_time structure exists
    if (!isset($merged['break_time'])) {
        $merged['break_time'] = $defaultAvailability['break_time'];
    }
    
    return $merged;
}

// Fetch doctors
$doctors = [];
try {
    $query = "SELECT d.doctor_id, d.user_id, d.first_name, d.last_name, 
             d.specialization, d.sub_specialties, d.availability,
             u.profile_image
      FROM doctor_details d
      JOIN users u ON d.user_id = u.user_id
      ORDER BY d.last_name ASC, d.first_name ASC";
    
    $result = $conn->query($query);
    $doctors = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors | AppointMed</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/logo.png" type="my_logo">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Main Grid Layout */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            width: 100%;
        }
        
        /* Doctor Card Styling */
        .doctor-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%; /* Ensure all cards have same height */
        }

        .doctor-image {
            width: 100%;
            height: 300px; /* Fixed height for images */
            object-fit: cover;
            object-position: top center; /* Ensures faces are visible */
        }

        .doctor-info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .doctor-name {
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
            flex-grow: 1; /* Allows this section to grow and push button down */
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
            text-align: center; /* Add this to center the button */
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
            display: block; /* Changed from inline-flex */
            background: #3498db;
            color: white;
            text-align: center;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
            margin-top: auto; /* Pushes button to bottom */
            width: 100%; /* Full width */
            box-sizing: border-box;
        }

        .btn-appoint:hover {
            background: #2980b9;
        }
        
        /* Filter Section */
        .sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Responsive Layout */
        @media (max-width: 768px) {
            .doctors-grid {
                grid-template-columns: 1fr;
            }
            
            .page-container {
                flex-direction: column;
            }
        }

        .testimonials-section {
            background-color: #f8f9fa;
        }

        .no-doctors-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
    </style>
</head>

<body>
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

    <section class="doctor-hero">
        <div class="text-content">
            <h1>Our Specialist Doctors</h1>
            <p>Meet our team of experienced healthcare professionals dedicated to providing you <br> with the best
                medical
                care.</p>
            <a href="login.php" class="btn-book">Book Appointment</a>
        </div>
    </section>

    <section class="doctors-section">
        <div class="page-container">
            <!-- Sidebar Filters -->
            <div class="sidebar">
                <h3>Filter By</h3>

                <div class="filter-group">
                    <label for="specialtyFilter">Specialty</label>
                    <select id="specialtyFilter">
                        <option value="">All Specialties</option>
                        <option value="Cardiology">Cardiology</option>
                        <option value="Neurology">Neurology</option>
                        <option value="Pediatrics">Pediatrics</option>
                        <option value="Dermatology">Dermatology</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="availabilityFilter">Availability</label>
                    <select id="availabilityFilter">
                        <option value="">All Days</option>
                        <option value="Mon">Monday</option>
                        <option value="Tue">Tuesday</option>
                        <option value="Wed">Wednesday</option>
                        <option value="Thu">Thursday</option>
                        <option value="Fri">Friday</option>
                        <option value="Sat">Saturday</option>
                        <option value="Sun">Sunday</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="ratingFilter">Rating</label>
                    <select id="ratingFilter">
                        <option value="">All Ratings</option>
                        <option value="5">★★★★★</option>
                        <option value="4">★★★★</option>
                        <option value="3">★★★</option>
                        <option value="2">★★</option>
                        <option value="1">★</option>
                    </select>
                </div>

                <div id="noDoctorsMessage" class="noDoctorsMessage">
                    No available doctors.
                </div>
            </div>

            <!-- Doctors Grid -->
            <div class="doctors-content">
                <div class="doctors-grid">
                    <?php if (!empty($doctors)): ?>
                        <?php foreach ($doctors as $doctor): ?>
                            <?php
                            $availability = parseDoctorAvailability($doctor['availability'] ?? '');
                            $workingDays = $availability['working_days'];
                            $workingHours = $availability['working_hours'];
                            
                            // Generate random rating and reviews for demo
                            $rating = rand(4, 5); // Most doctors will have 4-5 stars
                            $reviews = rand(100, 500);
                            
                            $imagePath = !empty($doctor['profile_image']) ? 
                                '../images/doctors/' . $doctor['profile_image'] : 
                                'images/doctors/default.png';
                            ?>
                            
                            <div class="doctor-card" 
                                data-specialty="<?= htmlspecialchars($doctor['specialization']) ?>"
                                data-availability="<?= htmlspecialchars(implode(',', $workingDays)) ?>">
                                
                                <img src="<?= $imagePath ?>" class="doctor-image" 
                                    alt="Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>">
                                
                                <div class="doctor-info">
                                    <h3 class="doctor-name">Dr. <?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?></h3>
                                    <p class="specialty"><?= htmlspecialchars($doctor['specialization']) ?></p>
                                    
                                    <div class="rating">
                                        <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?>
                                        <span>(<?= $reviews ?> reviews)</span>
                                    </div>
                                    
                                    <p class="availability">
                                        <i class="fas fa-calendar-alt"></i> Available: <?= implode(', ', $workingDays) ?><br>
                                        <i class="fas fa-clock"></i> Hours: <?= date("g:i A", strtotime($workingHours['start_time'])) ?> - <?= date("g:i A", strtotime($workingHours['end_time'])) ?>
                                        <?php if (!empty($availability['break_time']['start_time']) && !empty($availability['break_time']['duration'])): ?>
                                            <br><i class="fas fa-coffee"></i> Break: <?= date("g:i A", strtotime($availability['break_time']['start_time'])) ?> (<?= $availability['break_time']['duration'] ?> mins)
                                        <?php endif; ?>
                                    </p>
                                    
                                    <a href="login.php" class="btn-appoint">
                                        Book Appointment
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-doctors-message">
                            <p>No doctors found. Please check back later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


        </div>

        <div class="view-all" style="margin-top: 40px;">
            <a href="index.php" class="view-all-btn">← Back to Home</a>
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
</body>

</html>