<?php
// patient/register.php

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
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = sanitize_input($_POST['phone'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = sanitize_input($_POST['gender'] ?? '');
    $emergency_contact = sanitize_input($_POST['emergency_contact'] ?? '');
    $medical_history = sanitize_input($_POST['medical_history'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($age) || empty($gender)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($age < 1 || $age > 120) {
        $error = 'Please enter a valid age';
    } else {
        // ✅ Use $db instead of $pdo for consistency
        global $db;
        
        if (!$db) {
            $error = 'Database connection error. Please try again.';
        } else {
            try {
                // Check if email already exists in users table
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = 'Email already registered';
                } else {
                    // Register patient in users table
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, full_name, phone, user_type, gender, emergency_contact, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'patient', ?, ?, 1, NOW())
                    ");
                    
                    if ($stmt->execute([$name, $email, $hashed_password, $name, $phone, $gender, $emergency_contact])) {
                        $user_id = $db->lastInsertId();

                        // Insert into patient_records table
                        $stmt = $db->prepare("INSERT INTO patient_records (patient_id, medical_history) VALUES (?, ?)");
                        $stmt->execute([$user_id, $medical_history]);

                        // ✅ Log the registration activity
                        log_activity($user_id, 'patient_registered', 'New patient registration');

                        $success = 'Registration successful! You can now <a href="login.php">login to your account</a>.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

// ✅ Set page title before including header
$page_title = "Patient Registration - HealthHive";
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="h3">Patient Registration</h2>
                        <p class="text-muted">Create your account to access HealthHive services</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="Enter your full name">
                                <div class="invalid-feedback">Please provide your full name.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="Enter your email">
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       minlength="6" placeholder="Enter password (min 6 characters)">
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                       placeholder="Confirm your password">
                                <div class="invalid-feedback">Please confirm your password.</div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="Enter your phone number">
                                <div class="invalid-feedback">Please provide your phone number.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="age" class="form-label">Age *</label>
                                <input type="number" class="form-control" id="age" name="age" required 
                                       min="1" max="120" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>"
                                       placeholder="Enter your age">
                                <div class="invalid-feedback">Please provide a valid age (1-120).</div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender *</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo (($_POST['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="invalid-feedback">Please select your gender.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact"
                                       value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>"
                                       placeholder="Emergency contact number">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="medical_history" class="form-label">Medical History</label>
                            <textarea class="form-control" id="medical_history" name="medical_history" rows="4"
                                      placeholder="Any existing conditions, allergies, or medical history..."><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="login.php" class="text-decoration-none">Login here</a>
                        </p>
                        <p class="mt-2">
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

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    var password = document.getElementById('password').value;
    var confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>