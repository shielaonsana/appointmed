/* JavaScript for Mobile Menu Toggle */
document.addEventListener('DOMContentLoaded', function () {
  const menuToggle = document.getElementById('menu-toggle');
  const navContainer = document.getElementById('nav-container');

  if (menuToggle && navContainer) {
    menuToggle.addEventListener('click', function () {
      navContainer.classList.toggle('active');
    });

    // Close menu when clicking on a nav link
    const navLinks = document.querySelectorAll('.nav-links li a');
    navLinks.forEach(link => {
      link.addEventListener('click', function () {
        navContainer.classList.remove('active');
      });
    });
  }
});

const specialtyFilter = document.getElementById('specialtyFilter');
const availabilityFilter = document.getElementById('availabilityFilter');
const ratingFilter = document.getElementById('ratingFilter');
const doctorCards = document.querySelectorAll('.doctor-card');
const noDoctorsMessage = document.getElementById('noDoctorsMessage');

function filterDoctors() {
  const selectedSpecialty = specialtyFilter.value;
  const selectedAvailability = availabilityFilter.value;
  const selectedRating = ratingFilter.value;

  let anyVisible = false;

  doctorCards.forEach(card => {
    const specialty = card.getAttribute('data-specialty');
    const availability = card.getAttribute('data-availability');
    const rating = card.getAttribute('data-rating');

    const specialtyMatch = !selectedSpecialty || specialty === selectedSpecialty;
    const availabilityMatch = !selectedAvailability || availability.includes(selectedAvailability);
    const ratingMatch = !selectedRating || rating === selectedRating;

    if (specialtyMatch && availabilityMatch && ratingMatch) {
      card.style.display = 'block';
      anyVisible = true;
    } else {
      card.style.display = 'none';
    }
  });

  // Show or hide the "No available doctors" message
  noDoctorsMessage.style.display = anyVisible ? 'none' : 'block';
}

specialtyFilter.addEventListener('change', filterDoctors);
availabilityFilter.addEventListener('change', filterDoctors);
ratingFilter.addEventListener('change', filterDoctors);

document.addEventListener('DOMContentLoaded', function () {
  const urlParams = new URLSearchParams(window.location.search);
  const specialty = urlParams.get('specialty');
  const dateParam = urlParams.get('date');

  if (specialty && dateParam) {
    const date = new Date(dateParam);
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const targetDay = days[date.getDay()];

    let anyVisible = false;

    document.querySelectorAll('.doctor-card').forEach(card => {
      const cardSpecialty = card.dataset.specialty;
      const availability = card.dataset.availability.split(',').map(d => d.trim());

      if (cardSpecialty === specialty && availability.includes(targetDay)) {
        card.style.display = 'block';
        anyVisible = true;
      } else {
        card.style.display = 'none';
      }
    });

    noDoctorsMessage.style.display = anyVisible ? 'none' : 'block';
  }
});

/*login.html*/

// Toggle password visibility
document.querySelector('.toggle-password').addEventListener('click', function () {
  const passwordInput = document.getElementById('password');
  const icon = this;

  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  } else {
    passwordInput.type = 'password';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  }
});

// Mobile menu toggle
document.getElementById('menu-toggle').addEventListener('click', function () {
  document.getElementById('nav-container').classList.toggle('active');
});


/*booking.html*/
