// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize features based on current page
    initializePageFeatures();
});

function initializePageFeatures() {
    // Check if we're on the doctors page
    if (document.querySelector('.doctors-section')) {
        initializeDoctorFilters();
    }

    // Check if we're on a page with mobile menu
    if (document.getElementById('menu-toggle')) {
        setupMobileMenu();
    }

    // Check if we're on login page
    if (document.querySelector('.toggle-password')) {
        setupPasswordToggle();
    }
}

function initializeDoctorFilters() {
    // Get all filter elements
    const filterElements = {
        specialty: document.getElementById('specialtyFilter'),
        availability: document.getElementById('availabilityFilter'),
        rating: document.getElementById('ratingFilter'),
        cards: document.querySelectorAll('.doctor-card'),
        noDoctorsMsg: document.getElementById('noDoctorsMessage')
    };

    // Verify all required elements exist
    if (!Object.values(filterElements).every(element => {
        if (Array.isArray(element)) return element.length > 0;
        return element !== null;
    })) {
        console.log('Some filter elements are missing - skipping doctor filters');
        return;
    }

    function filterDoctors() {
        const filters = {
            specialty: filterElements.specialty.value,
            availability: filterElements.availability.value,
            rating: filterElements.rating.value
        };
        
        let visibleCount = 0;
        
        filterElements.cards.forEach(card => {
            // Get card data
            const cardData = {
                specialty: card.dataset.specialty,
                availability: card.dataset.availability,
                rating: getCardRating(card)
            };
            
            // Apply filters
            const shouldShow = (
                (!filters.specialty || cardData.specialty === filters.specialty) &&
                (!filters.availability || cardData.availability.includes(filters.availability)) &&
                (!filters.rating || cardData.rating >= parseInt(filters.rating))
            );
            
            card.style.display = shouldShow ? 'block' : 'none';
            if (shouldShow) visibleCount++;
        });
        
        filterElements.noDoctorsMsg.style.display = visibleCount ? 'none' : 'block';
    }

    function getCardRating(card) {
        const ratingElement = card.querySelector('.rating');
        if (!ratingElement) return 0;
        const starsText = ratingElement.textContent;
        return (starsText.match(/★/g) || []).length;
    }

    // Add event listeners
    filterElements.specialty.addEventListener('change', filterDoctors);
    filterElements.availability.addEventListener('change', filterDoctors);
    filterElements.rating.addEventListener('change', filterDoctors);
    
    // Initial filter
    filterDoctors();
}

function setupMobileMenu() {
    const menuToggle = document.getElementById('menu-toggle');
    const navContainer = document.getElementById('nav-container');

    if (!menuToggle || !navContainer) {
        console.log('Mobile menu elements not found');
        return;
    }

    menuToggle.addEventListener('click', function() {
        navContainer.classList.toggle('active');
        menuToggle.classList.toggle('active');
    });

    // Close menu when clicking on nav links
    document.querySelectorAll('.nav-links li a').forEach(link => {
        link.addEventListener('click', function() {
            navContainer.classList.remove('active');
            menuToggle.classList.remove('active');
        });
    });

    // Close when clicking outside
    document.addEventListener('click', function(event) {
        if (navContainer.classList.contains('active') && 
            !event.target.closest('.nav-container') && 
            !event.target.closest('#menu-toggle')) {
            navContainer.classList.remove('active');
            menuToggle.classList.remove('active');
        }
    });
}

function setupPasswordToggle() {
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password');

    if (!togglePassword || !passwordInput) {
        console.log('Password toggle elements not found');
        return;
    }

    togglePassword.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        this.classList.toggle('fa-eye-slash');
        this.classList.toggle('fa-eye');
    });
}