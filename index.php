<?php
// At the VERY TOP of your PHP file (before session_start or other code)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL); // log everything, just don't display in prod
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/vendor/autoload.php';

// Main entry point for the website

// --- ENV Loader Section ---
// This block checks for .env.local first, then .env.
// Put your database credentials in .env.local for local dev, .env for production.

include_once __DIR__ . '/config/env.php';
// Try to load .env.local first; if not, load .env
if (file_exists(__DIR__ . '/config/.env.local')) {
    loadEnv(__DIR__ . '/config/.env.local');
} elseif (file_exists(__DIR__ . '/config/.env')) {
    loadEnv(__DIR__ . '/config/.env');
}

// --- End ENV Loader Section ---

include_once __DIR__ . '/config/db.php';

// --- Database Connection Section ---
// Use environment variables for connection

$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
$port = isset($_ENV['DB_PORT']) ? $_ENV['DB_PORT'] : '3306';
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : '';
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$connectionStatus = '';
try {
    $pdo = new PDO($dsn, $user, $pass);
    $connectionStatus = 'success';
} catch (PDOException $e) {
    $connectionStatus = 'failed: ' . $e->getMessage();
}
// --- End Database Connection Section ---
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, maximum-scale=5.0">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CHO Koronadal">
    <title>City Health Office - Koronadal</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Prevent zoom on iOS Safari */
        input, select, textarea {
            font-size: 16px !important;
        }
        
        /* Smooth scrolling for all devices */
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile touch improvements */
        * {
            -webkit-tap-highlight-color: rgba(37, 99, 235, 0.2);
        }
        
        /* Prevent text selection on buttons */
        .btn, .nav-btn, .carousel-btn {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>
</head>

<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="assets/images/Nav_Logo_Dark.png" alt="CHO Koronadal Logo" class="logo-img">
            </div>
            <div class="nav-actions">
                <a href="pages/patient/auth/patient_login.php" class="nav-btn btn-outline">
                    <i class="fas fa-user"></i> Patient Portal
                </a>
                <a href="pages/management/auth/employee_login.php" class="nav-btn btn-primary">
                    <i class="fas fa-user-md"></i> Staff Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Welcome to<br><span class="text-primary">Koronadal City Health Office</span></h1>
                <p class="hero-subtitle">Providing comprehensive healthcare services across three districts with modern facilities and dedicated professionals.</p>
                <div class="hero-buttons">
                    <a href="pages/patient/auth/patient_login.php" class="btn btn-primary btn-large">
                        <i class="fas fa-user"></i> Patient Login
                    </a>
                    <a href="pages/management/auth/employee_login.php" class="btn btn-outline btn-large">
                        <i class="fas fa-user-md"></i> Employee Login
                    </a>
                    <a href="testdb.php" class="btn btn-secondary btn-large">
                        <i class="fas fa-database"></i> Test Connection
                    </a>
                </div>
            </div>
            <div class="hero-carousel">
                <div class="carousel-container">
                    <div class="carousel-slides">
                        <div class="slide active">
                            <img src="assets/images/Location_Aerial_View.jpeg" alt="Modern Healthcare Facility">
                            <div class="slide-overlay">
                                <h3>Modern Healthcare Facilities</h3>
                                <p>State-of-the-art equipment and comfortable patient care areas</p>
                            </div>
                        </div>
                        <div class="slide">
                            <img src="https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Medical Professionals">
                            <div class="slide-overlay">
                                <h3>Expert Medical Team</h3>
                                <p>Dedicated healthcare professionals serving the community</p>
                            </div>
                        </div>
                        <div class="slide">
                            <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Community Health">
                            <div class="slide-overlay">
                                <h3>Community Healthcare</h3>
                                <p>Serving all residents across Main, Concepcion, and GPS districts</p>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-controls">
                        <button class="carousel-btn prev" onclick="changeSlide(-1)" aria-label="Previous slide">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="carousel-btn next" onclick="changeSlide(1)" aria-label="Next slide">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="carousel-indicators">
                        <span class="indicator active" onclick="currentSlide(1)" role="button" aria-label="Go to slide 1" tabindex="0"></span>
                        <span class="indicator" onclick="currentSlide(2)" role="button" aria-label="Go to slide 2" tabindex="0"></span>
                        <span class="indicator" onclick="currentSlide(3)" role="button" aria-label="Go to slide 3" tabindex="0"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section">
        <div class="container">
            <div class="section-header">
                <h2>About CHO Koronadal</h2>
                <p>Established as the primary healthcare provider for Koronadal City</p>
            </div>
            <div class="about-content">
                <div class="about-text">
                    <p>The City Health Office (CHO) of Koronadal has long been the primary healthcare provider for the city, but it was only in 2022 that it established its Main District building, making its facilities relatively new.</p>
                    <p>CHO operates across three districts—<strong>Main</strong>, <strong>Concepcion</strong>, and <strong>GPS</strong>—each covering 8 to 10 barangays to ensure accessible healthcare for all residents.</p>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">3</div>
                        <div class="stat-label">Districts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">24+</div>
                        <div class="stat-label">Barangays Served</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">9</div>
                        <div class="stat-label">Core Services</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services-section">
        <div class="container">
            <div class="section-header">
                <h2>Our Healthcare Services</h2>
                <p>Comprehensive medical care for the Koronadal community</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <h3>Konsulta</h3>
                    <p>Free basic healthcare services and medical consultations</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-tooth"></i>
                    </div>
                    <h3>Dental Services</h3>
                    <p>Tooth extraction and comprehensive dental care</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-lungs"></i>
                    </div>
                    <h3>TB DOTS</h3>
                    <p>Tuberculosis Directly Observed Treatment, Short-course</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <h3>Vaccines</h3>
                    <p>Tetanus and other vaccine administration services</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-ambulance"></i>
                    </div>
                    <h3>HEMS (911)</h3>
                    <p>Emergency Medical Services and urgent care</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Family Planning</h3>
                    <p>Consultation and services for family planning</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <h3>Animal Bite Treatment</h3>
                    <p>Rabies prevention and treatment for animal bites</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-vial"></i>
                    </div>
                    <h3>Laboratory Test</h3>
                    <p>Laboratory testing and diagnostic services for accurate health assessments</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h3>Medical Document Request</h3>
                    <p>Issuance of medical certificates and other health-related documents</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 style="color: #fff;">Ready to Access Healthcare Services?</h2>
                <p style="color: #fff;">Choose your portal to get started with CHO Koronadal's comprehensive healthcare services</p>
                <div class="cta-buttons">
                    <a href="pages/patient/auth/patient_login.php" class="btn btn-primary btn-large">
                        <i class="fas fa-user"></i> Patient Portal
                    </a>
                    <a href="pages/management/auth/employee_login.php" class="btn btn-outline btn-large">
                        <i class="fas fa-user-md"></i> Staff Portal
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="assets/images/Nav_Logo.png" alt="CHO Koronadal Logo" class="logo-img">
                </div>
                <div class="footer-info" style="text-align: center;">
                    <p>&copy; 2024 City Health Office - Koronadal. All rights reserved.</p>
                    <p>Modern facilities, accessible healthcare, and essential services for our community.</p>
                </div>
                <div class="footer-test">
                    <a href="testdb.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-database"></i> Test DB Connection
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>

<!-- Connection Status Snackbar -->
<div id="snackbar"></div>

<!-- Carousel JavaScript -->
<script>
let slideIndex = 0;
const slides = document.querySelectorAll('.slide');
const indicators = document.querySelectorAll('.indicator');
let autoPlayInterval;

function showSlide(n) {
    slides[slideIndex].classList.remove('active');
    indicators[slideIndex].classList.remove('active');
    
    slideIndex = (n + slides.length) % slides.length;
    
    slides[slideIndex].classList.add('active');
    indicators[slideIndex].classList.add('active');
}

function changeSlide(n) {
    showSlide(slideIndex + n);
    resetAutoPlay();
}

function currentSlide(n) {
    showSlide(n - 1);
    resetAutoPlay();
}

function startAutoPlay() {
    autoPlayInterval = setInterval(() => {
        changeSlide(1);
    }, 5000);
}

function resetAutoPlay() {
    clearInterval(autoPlayInterval);
    startAutoPlay();
}

// Touch/Swipe support for mobile
let touchStartX = 0;
let touchEndX = 0;

function handleTouchStart(e) {
    touchStartX = e.changedTouches[0].screenX;
}

function handleTouchEnd(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
}

function handleSwipe() {
    const swipeThreshold = 50;
    const swipeDistance = touchEndX - touchStartX;
    
    if (Math.abs(swipeDistance) > swipeThreshold) {
        if (swipeDistance > 0) {
            changeSlide(-1); // Swipe right - previous slide
        } else {
            changeSlide(1); // Swipe left - next slide
        }
    }
}

// Keyboard navigation
function handleKeyPress(e) {
    if (e.key === 'ArrowLeft') {
        changeSlide(-1);
    } else if (e.key === 'ArrowRight') {
        changeSlide(1);
    }
}

// Initialize carousel
document.addEventListener('DOMContentLoaded', function() {
    const carouselContainer = document.querySelector('.carousel-container');
    
    // Add touch events
    carouselContainer.addEventListener('touchstart', handleTouchStart, { passive: true });
    carouselContainer.addEventListener('touchend', handleTouchEnd, { passive: true });
    
    // Add keyboard events
    document.addEventListener('keydown', handleKeyPress);
    
    // Add click events to indicators for keyboard accessibility
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                currentSlide(index + 1);
            }
        });
    });
    
    // Start auto-play
    startAutoPlay();
    
    // Pause auto-play when user is interacting
    carouselContainer.addEventListener('mouseenter', () => clearInterval(autoPlayInterval));
    carouselContainer.addEventListener('mouseleave', startAutoPlay);
    
    // Pause auto-play when page is not visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(autoPlayInterval);
        } else {
            startAutoPlay();
        }
    });
});

// Connection status display
var status = "<?php echo $connectionStatus; ?>";
if (status) {
    var snackbar = document.getElementById("snackbar");
    snackbar.textContent = status === "success" ? "✓ Database connection successful!" : "✗ Connection failed: " + status;
    snackbar.className = status === "success" ? "show success" : "show error";
    setTimeout(function() {
        snackbar.className = snackbar.className.replace("show", "");
    }, 4000);
}

// Enhanced smooth scrolling and navbar effects
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scroll behavior
    document.documentElement.style.scrollBehavior = 'smooth';
    
    // Navbar scroll effect with throttling for better performance
    let ticking = false;
    
    function updateNavbar() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        ticking = false;
    }
    
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(updateNavbar);
            ticking = true;
        }
    });
    
    // Optimize images loading for mobile
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.loading = 'lazy';
    });
    
    // Add focus management for better accessibility
    const focusableElements = document.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');
    focusableElements.forEach(element => {
        element.addEventListener('focus', function() {
            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
});

// Service Worker registration for better mobile experience (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // This would register a service worker if you create one
        // navigator.serviceWorker.register('/service-worker.js');
    });
}
</script>
<style>
/* Enhanced Snackbar Styles */
#snackbar {
    position: fixed;
    left: 50%;
    bottom: 30px;
    transform: translateX(-50%);
    min-width: 300px;
    max-width: 90vw;
    padding: 16px 24px;
    border-radius: 12px;
    color: #fff;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10000;
    font: 500 14px/1.4 'Inter', system-ui, -apple-system, sans-serif;
    display: flex;
    align-items: center;
    gap: 8px;
}

#snackbar.show {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0);
}

#snackbar.success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
}

#snackbar.error {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

@keyframes slideInUp {
    from {
        transform: translateX(-50%) translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
}

#snackbar.show {
    animation: slideInUp 0.3s ease-out;
}
</style>

</html>