<?php
// index.php

// ✅ Enable error reporting to catch blank page issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ Include database connection FIRST (this now sets up global $db)
require_once 'config/database.php';

// ✅ Include functions after database connection
require_once 'includes/functions.php';

// ✅ Ensure SITE_URL is defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// ✅ Debug: Check if database connection is available (remove in production)
if (isset($db)) {
    // Database is connected successfully
} else {
    error_log("❌ Database connection not available in index.php");
}

// Check for logout success message
$logout_success = '';
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $logout_success = 'You have been successfully logged out.';
}

// Redirect if already logged in
if (function_exists('is_logged_in') && is_logged_in()) {
    $user_type = $_SESSION['user_type'] ?? 'patient'; // safer access
    header("Location: " . SITE_URL . $user_type . "/dashboard.php");
    exit();
}

$page_title = "Smart Healthcare Assistant";
include 'includes/header.php';
?>

<div class="container-fluid p-0">
    <!-- Hero Section -->
    <section class="bg-primary text-white py-5" style="background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Your Smart Healthcare Assistant</h1>
                    <p class="lead mb-4">
                        HealthHive centralizes your health records, provides AI-powered symptom analysis, 
                        and delivers proactive health reminders - all in one intelligent platform.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="patient/register.php" class="btn btn-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Get Started as Patient
                        </a>
                        <a href="doctor/register.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-md me-2"></i>Join as Doctor
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                             class="img-fluid rounded shadow-lg" alt="Healthcare Technology">
                        <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary rounded" 
                             style="opacity: 0.1; z-index: -1; transform: translate(20px, 20px);"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Comprehensive Healthcare Management</h2>
                <p class="text-muted">Advanced features to keep you healthy and connected with healthcare providers</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-robot text-primary" style="font-size: 24px;"></i>
                            </div>
                            <h5 class="card-title">AI Symptom Analysis</h5>
                            <p class="card-text text-muted">Get intelligent preliminary assessments of your symptoms with our advanced AI technology.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-calendar-check text-success" style="font-size: 24px;"></i>
                            </div>
                            <h5 class="card-title">Smart Appointments</h5>
                            <p class="card-text text-muted">Book, manage, and track your medical appointments with automated reminders.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-file-medical-alt text-info" style="font-size: 24px;"></i>
                            </div>
                            <h5 class="card-title">Health Records</h5>
                            <p class="card-text text-muted">Securely store and access your complete medical history from anywhere.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-bell text-warning" style="font-size: 24px;"></i>
                            </div>
                            <h5 class="card-title">Health Reminders</h5>
                            <p class="card-text text-muted">Never miss medications, check-ups, or health screenings with smart notifications.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-phone text-danger" style="font-size: 24px;"></i>
                            </div>
                            <h5 class="card-title">Emergency Access</h5>
                            <p class="card-text text-muted">Quick access to emergency contacts and critical health information when needed.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="bg-secondary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-shield-alt text-secondary" style="font-size: 24px;"></i>
                            </div>
                            <h5 class="card-title">Secure & Private</h5>
                            <p class="card-text text-muted">Your health data is protected with enterprise-grade security and privacy measures.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Section - FIXED VERSION -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <h3>Already have an account?</h3>
                                <p class="text-muted">Sign in to access your health dashboard</p>
                            </div>
                            
                            <!-- ✅ ADD LOGOUT SUCCESS MESSAGE HERE -->
                            <?php if ($logout_success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $logout_success; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- ✅ FIXED: Changed action to point to correct login files -->
                            <form method="POST" action="patient/login.php" class="row g-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="remember">
                                            <label class="form-check-label" for="remember">Remember me</label>
                                        </div>
                                        <a href="patient/forgot_password.php" class="text-decoration-none">Forgot password?</a>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100">Sign In</button>
                                </div>
                            </form>
                            
                            <!-- ✅ Add quick login options -->
                            <div class="text-center mt-4">
                                <p class="mb-2">Or sign in as:</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="patient/login.php" class="btn btn-outline-primary">Patient</a>
                                    <a href="doctor/login.php" class="btn btn-outline-success">Doctor</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Emergency Access Button -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
    <button type="button" class="btn btn-danger btn-lg rounded-circle shadow" 
            data-bs-toggle="modal" data-bs-target="#emergencyModal" title="Emergency">
        <i class="fas fa-phone" style="font-size: 1.5rem;"></i>
    </button>
</div>

<?php include 'includes/footer.php'; ?>