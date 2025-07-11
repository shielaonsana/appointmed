document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('nav-container').classList.toggle('active');
    });

    // Specialty card selection
    document.querySelectorAll('.specialty-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.specialty-card').forEach(c => {
                c.classList.remove('selected');
            });
            this.classList.add('selected');
        });
    });

    // Step 1 search functionality
    document.getElementById('specialty-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();

        document.querySelectorAll('.specialty-card').forEach(card => {
            const title = card.querySelector('.specialty-title').textContent.toLowerCase();
            const desc = card.querySelector('.specialty-desc').textContent.toLowerCase();

            if (title.includes(searchTerm) || desc.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Step 2 search functionality
    document.getElementById('doctor-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();

        document.querySelectorAll('.doctor-card').forEach(card => {
            const doctorName = card.querySelector('h3').textContent.toLowerCase();
            const doctorSpecialty = card.querySelector('.doctor-specialty').textContent.toLowerCase();
            
            if (doctorName.includes(searchTerm) || doctorSpecialty.includes(searchTerm)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Doctor card selection
    document.querySelectorAll('.doctor-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.doctor-card').forEach(c => {
                c.classList.remove('selected');
            });
            this.classList.add('selected');
        });
    });

    // Filter pills functionality
    document.querySelectorAll('.pill').forEach(pill => {
        pill.addEventListener('click', function() {
            document.querySelectorAll('.pill').forEach(p => {
                p.classList.remove('active');
            });
            this.classList.add('active');
            
            const filter = this.textContent.toLowerCase();
            filterDoctors(filter);
        });
    });

    function filterDoctors(filter) {
        document.querySelectorAll('.doctor-card').forEach(card => {
            const doctorInfo = card.textContent.toLowerCase();
            const doctorGender = card.dataset.gender || '';
            const doctorRating = parseFloat(card.querySelector('.rating span').textContent) || 0;
            const doctorAvailability = card.querySelector('.availability').textContent.toLowerCase();
            
            if (filter === 'all') {
                card.style.display = 'block';
            } else if (filter === 'female' && doctorGender === 'female') {
                card.style.display = 'block';
            } else if (filter === 'male' && doctorGender === 'male') {
                card.style.display = 'block';
            } else if (filter === 'top rated' && doctorRating >= 4.8) {
                card.style.display = 'block';
            } else if (filter === 'available today' && doctorAvailability.includes(getCurrentDay())) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function getCurrentDay() {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const date = new Date();
        return days[date.getDay()];
    }

    // Time slot selection
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.addEventListener('click', function() {
            document.querySelectorAll('.time-slot').forEach(s => {
                s.classList.remove('selected');
            });
            this.classList.add('selected');
        });
    });

    // Calendar functionality
    const today = new Date();
    let currentMonth = today.getMonth();
    let currentYear = today.getFullYear();

    function generateCalendar(month, year) {
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        document.querySelector('.month-title').textContent = `${monthNames[month]} ${year}`;
        
        const calendarBody = document.getElementById('calendar-body');
        calendarBody.innerHTML = '';
        
        let date = 1;
        for (let i = 0; i < 6; i++) {
            // Create a table row
            let row = document.createElement('tr');
            
            for (let j = 0; j < 7; j++) {
                if (i === 0 && j < firstDay) {
                    // Empty cells before the first day of the month
                    let cell = document.createElement('td');
                    row.appendChild(cell);
                } else if (date > daysInMonth) {
                    // Break if we've gone beyond the last day of the month
                    break;
                } else {
                    // Create a cell for each day
                    let cell = document.createElement('td');
                    let dayDiv = document.createElement('div');
                    dayDiv.textContent = date;
                    dayDiv.classList.add('day');
                    
                    // Check if this day is in the past
                    const currentDate = new Date(year, month, date);
                    if (currentDate < new Date(today.getFullYear(), today.getMonth(), today.getDate())) {
                        dayDiv.classList.add('disabled');
                    } else {
                        // Add click event to selectable days
                        dayDiv.addEventListener('click', function() {
                            document.querySelectorAll('.day').forEach(d => {
                                d.classList.remove('selected');
                            });
                            this.classList.add('selected');
                            updateTimeSlots(new Date(year, month, date));
                        });
                    }
                    
                    // Mark today's date
                    if (date === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                        dayDiv.classList.add('today');
                    }
                    
                    cell.appendChild(dayDiv);
                    row.appendChild(cell);
                    date++;
                }
            }
            
            calendarBody.appendChild(row);
            if (date > daysInMonth) {
                break;
            }
        }
    }

    // Function to update available time slots based on date and doctor
    function updateTimeSlots(date) {
        const selectedDoctor = document.querySelector('.doctor-card.selected');
        const doctorName = selectedDoctor ? selectedDoctor.querySelector('h3').textContent : '';
        const dayOfWeek = date.getDay(); // 0 is Sunday, 1 is Monday, etc.
        
        // This would typically involve an API call to get actual available slots
        // For demo purposes, we're just randomly disabling some slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
            
            // Randomly make some slots unavailable
            if (Math.random() > 0.7) {
                slot.style.display = 'none';
            } else {
                slot.style.display = 'block';
            }
        });
        
        // Select the first available slot by default
        const firstAvailable = document.querySelector('.time-slot[style="display: block;"]');
        if (firstAvailable) {
            firstAvailable.classList.add('selected');
        }
    }

    // Initialize calendar
    generateCalendar(currentMonth, currentYear);
    
    // Calendar navigation
    document.querySelector('.calendar-nav.prev').addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    document.querySelector('.calendar-nav.next').addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });

    // Select today's date by default
    const todayCell = document.querySelector('.day.today');
    if (todayCell) {
        todayCell.classList.add('selected');
        updateTimeSlots(today);
    }

    // Navigation between steps
    document.getElementById('step1-continue').addEventListener('click', function() {
        const selectedSpecialty = document.querySelector('.specialty-card.selected');
        
        if (!selectedSpecialty) {
            alert('Please select a specialty to continue.');
            return;
        }
        
        document.getElementById('step-1').classList.remove('active');
        document.getElementById('step-2').classList.add('active');
        document.getElementById('step-1-indicator').classList.remove('active');
        document.getElementById('step-2-indicator').classList.add('active');
        
        // Filter doctors based on selected specialty
        const specialty = selectedSpecialty.querySelector('.specialty-title').textContent;
        filterDoctorsBySpecialty(specialty);
    });
    
    document.getElementById('step2-previous').addEventListener('click', function() {
        document.getElementById('step-2').classList.remove('active');
        document.getElementById('step-1').classList.add('active');
        document.getElementById('step-2-indicator').classList.remove('active');
        document.getElementById('step-1-indicator').classList.add('active');
    });
    
    document.getElementById('step2-continue').addEventListener('click', function() {
        const selectedDoctor = document.querySelector('.doctor-card.selected');
        const selectedDay = document.querySelector('.day.selected');
        const selectedTime = document.querySelector('.time-slot.selected');
        
        if (!selectedDoctor || !selectedDay || !selectedTime) {
            alert('Please select a doctor, date, and time to continue.');
            return;
        }
        
        // Here you would navigate to step 3
        alert('You selected ' + selectedDoctor.querySelector('h3').textContent + 
              ' on ' + document.querySelector('.month-title').textContent + ' ' + 
              selectedDay.textContent + ' at ' + selectedTime.textContent);
        
        // This is where you would add code to navigate to step 3
        // document.getElementById('step-2').classList.remove('active');
        // document.getElementById('step-3').classList.add('active');
        // document.getElementById('step-2-indicator').classList.remove('active');
        // document.getElementById('step-3-indicator').classList.add('active');
    });

    // Filter doctors by specialty
    function filterDoctorsBySpecialty(specialty) {
        document.querySelectorAll('.doctor-card').forEach(card => {
            const doctorSpecialty = card.querySelector('.doctor-specialty').textContent;
            
            if (specialty === doctorSpecialty || specialty === 'All Specialties') {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Select the first visible doctor
        const firstVisibleDoctor = document.querySelector('.doctor-card[style="display: block;"]');
        if (firstVisibleDoctor) {
            document.querySelectorAll('.doctor-card').forEach(c => {
                c.classList.remove('selected');
            });
            firstVisibleDoctor.classList.add('selected');
        }
    }

    // Let's add functionality for the remaining steps (we would expand this for steps 3, 4, and 5)
    // For now, this is a placeholder for future implementation
    function setupRemainingSteps() {
        // Step 3: Appointment Details would be implemented here
        
        // Step 4: Patient Info would be implemented here
        
        // Step 5: Confirm would be implemented here
    }
    
    // Initialize remaining steps (this is a placeholder)
    setupRemainingSteps();
});