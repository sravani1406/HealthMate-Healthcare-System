<?php
// patient/login.php

// ✅ Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ Include files with error checking
require_once '../config/database.php';
require_once '../includes/functions.php';

// ✅ Ensure SITE_URL is defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// ✅ Check if user is already logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'patient') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } else {
        // ✅ Use $db instead of $pdo for consistency
        global $db;
        
        if (!$db) {
            $error = 'Database connection error. Please try again.';
        } else {
            try {
                // ✅ Use users table and filter for patients
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'patient' AND is_active = 1");
                $stmt->execute([$email]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patient && password_verify($password, $patient['password'])) {
                    // ✅ Set all necessary session variables
                    $_SESSION['user_id'] = $patient['id'];
                    $_SESSION['user_type'] = 'patient';
                    $_SESSION['username'] = $patient['username'];
                    $_SESSION['full_name'] = $patient['full_name'];
                    $_SESSION['email'] = $patient['email'];
                    
                    // ✅ Log the login activity
                    log_activity($patient['id'], 'login', 'Patient login');
                    
                    // ✅ Update last login (optional)
                    try {
                        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$patient['id']]);
                    } catch (PDOException $e) {
                        // Log but don't fail login for this
                        error_log("Failed to update last login: " . $e->getMessage());
                    }
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password';
                    // ✅ Log failed login attempt
                    error_log("Failed login attempt for email: " . $email);
                }
            } catch (PDOException $e) {
                error_log("Login database error: " . $e->getMessage());
                $error = 'Login failed. Please try again.';
            }
        }
    }
}

// ✅ Set page title before including header
$page_title = "Patient Login - HealthHive";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="h3">Patient Login</h2>
                        <p class="text-muted">Sign in to access your health dashboard</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="login.php" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope text-muted"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="Enter your email">
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required
                                       placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                                <div class="invalid-feedback">Please provide your password.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <div class="row">
                            <div class="col-12 mb-2">
                                <p class="mb-0">
                                    Don't have an account? 
                                    <a href="register.php" class="text-decoration-none">Register here</a>
                                </p>
                            </div>
                            <div class="col-12 mb-2">
                                <p class="mb-0">
                                    <a href="forgot_password.php" class="text-decoration-none">Forgot your password?</a>
                                </p>
                            </div>
                            <div class="col-12">
                                <p class="mb-0">
                                    <a href="../index.php" class="text-muted text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Home
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Demo Credentials Card (Remove in production) -->
<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-info-circle me-2"></i>Demo Credentials (For Testing)
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Email:</strong> patient@demo.com</p>
                    <p class="mb-0"><strong>Password:</strong> demo123</p>
                    <small class="text-muted">Remove this card in production</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Password toggle functionality
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (password.type === 'password') {
        password.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
});

// Remember me functionality
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    const rememberCheckbox = document.getElementById('remember');
    
    // Load remembered email
    if (localStorage.getItem('rememberedEmail')) {
        emailInput.value = localStorage.getItem('rememberedEmail');
        rememberCheckbox.checked = true;
    }
    
    // Save email when form is submitted
    document.querySelector('form').addEventListener('submit', function() {
        if (rememberCheckbox.checked) {
            localStorage.setItem('rememberedEmail', emailInput.value);
        } else {
            localStorage.removeItem('rememberedEmail');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>