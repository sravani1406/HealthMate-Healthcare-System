<?php
// doctor/profile.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: ../auth/login.php');
    exit();
}

$doctor_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Initialize variables
$user_data = null;
$doctor_data = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    
    // Doctor specific fields
    $license_number = sanitize_input($_POST['license_number'] ?? '');
    $specialization = sanitize_input($_POST['specialization'] ?? '');
    $qualification = sanitize_input($_POST['qualification'] ?? '');
    $experience_years = sanitize_input($_POST['experience_years'] ?? '');
    $consultation_fee = sanitize_input($_POST['consultation_fee'] ?? '');
    $hospital_affiliation = sanitize_input($_POST['hospital_affiliation'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields (Name and Email)';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Update users table
            $stmt = $db->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, 
                    gender = ?, address = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $full_name, $email, $phone, $date_of_birth ?: null, 
                $gender, $address, $doctor_id
            ]);
            
            // Check if doctor record exists
            $check_stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $check_stmt->execute([$doctor_id]);
            $doctor_exists = $check_stmt->fetch();
            
            if ($doctor_exists) {
                // Update existing doctor record
                $stmt = $db->prepare("
                    UPDATE doctors 
                    SET license_number = ?, specialization = ?, qualification = ?, 
                        experience_years = ?, consultation_fee = ?, hospital_affiliation = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $license_number, $specialization, $qualification,
                    $experience_years ?: null, $consultation_fee ?: null, 
                    $hospital_affiliation, $doctor_id
                ]);
            } else {
                // Insert new doctor record
                $stmt = $db->prepare("
                    INSERT INTO doctors 
                    (user_id, license_number, specialization, qualification, 
                     experience_years, consultation_fee, hospital_affiliation)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $doctor_id, $license_number, $specialization, $qualification,
                    $experience_years ?: null, $consultation_fee ?: null, 
                    $hospital_affiliation
                ]);
            }
            
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            // Log activity
            log_activity($doctor_id, 'profile_updated', 'Doctor profile information updated');
            
            // Commit transaction
            $db->commit();
            
            $success = 'Profile updated successfully!';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            error_log("Error updating profile: " . $e->getMessage());
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

// Fetch current user data
try {
    // Fetch user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch doctor data
    $stmt = $db->prepare("SELECT * FROM doctors WHERE user_id = ?");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
}

// Demo data if no database data
if (!$user_data) {
    $user_data = [
        'full_name' => $_SESSION['full_name'] ?? 'Demo Doctor',
        'email' => $_SESSION['email'] ?? 'demo@doctor.com',
        'phone' => '+1234567890',
        'date_of_birth' => '1980-01-01',
        'gender' => 'male',
        'address' => '123 Medical Center, City'
    ];
}

if (!$doctor_data) {
    $doctor_data = [
        'license_number' => 'MD12345',
        'specialization' => 'General Medicine',
        'qualification' => 'MBBS, MD',
        'experience_years' => 10,
        'consultation_fee' => 500.00,
        'hospital_affiliation' => 'City General Hospital',
        'rating' => 4.5,
        'total_reviews' => 25
    ];
}

$page_title = 'Doctor Profile - HealthHive';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #28a745;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: #fff;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px;
        }
        .card-header h5 {
            margin: 0;
            color: #333;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-card h3 {
            color: #28a745;
            font-size: 32px;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #6c757d;
            margin: 0;
        }
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .btn-primary {
            background: #28a745;
            border-color: #28a745;
        }
        .btn-primary:hover {
            background: #218838;
            border-color: #218838;
        }
    </style>
</head>
<body>
    <div class="profile-header">
        <div class="container">
            <div class="text-center">
                <div class="profile-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <h2><?php echo htmlspecialchars($user_data['full_name']); ?></h2>
                <p class="mb-0"><?php echo htmlspecialchars($doctor_data['specialization'] ?? 'Doctor'); ?></p>
                <div class="mt-3">
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-star text-warning"></i>
                        <?php echo number_format($doctor_data['rating'] ?? 0, 1); ?> Rating
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-comments"></i>
                        <?php echo $doctor_data['total_reviews'] ?? 0; ?> Reviews
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Navigation -->
        <div class="row mb-4">
            <div class="col-12">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?php echo $doctor_data['experience_years'] ?? 0; ?></h3>
                    <p>Years Experience</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>₹<?php echo number_format($doctor_data['consultation_fee'] ?? 0, 2); ?></h3>
                    <p>Consultation Fee</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?php echo $doctor_data['total_reviews'] ?? 0; ?></h3>
                    <p>Total Reviews</p>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="update_profile" value="1">
            
            <div class="row">
                <!-- Personal Information -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-user me-2"></i>
                                Personal Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($user_data['date_of_birth'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($user_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-stethoscope me-2"></i>
                                Professional Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="license_number" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number" 
                                       value="<?php echo htmlspecialchars($doctor_data['license_number'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <select class="form-select" id="specialization" name="specialization">
                                    <option value="">Select Specialization</option>
                                    <option value="General Medicine" <?php echo ($doctor_data['specialization'] ?? '') === 'General Medicine' ? 'selected' : ''; ?>>General Medicine</option>
                                    <option value="Cardiology" <?php echo ($doctor_data['specialization'] ?? '') === 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                                    <option value="Dermatology" <?php echo ($doctor_data['specialization'] ?? '') === 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                                    <option value="Pediatrics" <?php echo ($doctor_data['specialization'] ?? '') === 'Pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                                    <option value="Orthopedics" <?php echo ($doctor_data['specialization'] ?? '') === 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                                    <option value="Neurology" <?php echo ($doctor_data['specialization'] ?? '') === 'Neurology' ? 'selected' : ''; ?>>Neurology</option>
                                    <option value="Psychiatry" <?php echo ($doctor_data['specialization'] ?? '') === 'Psychiatry' ? 'selected' : ''; ?>>Psychiatry</option>
                                    <option value="Other" <?php echo ($doctor_data['specialization'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" 
                                       value="<?php echo htmlspecialchars($doctor_data['qualification'] ?? ''); ?>"
                                       placeholder="e.g., MBBS, MD, MS">
                            </div>
                            
                            <div class="mb-3">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                       value="<?php echo htmlspecialchars($doctor_data['experience_years'] ?? ''); ?>" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="consultation_fee" class="form-label">Consultation Fee (₹)</label>
                                <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" 
                                       value="<?php echo htmlspecialchars($doctor_data['consultation_fee'] ?? ''); ?>" step="0.01" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="hospital_affiliation" class="form-label">Hospital Affiliation</label>
                                <input type="text" class="form-control" id="hospital_affiliation" name="hospital_affiliation" 
                                       value="<?php echo htmlspecialchars($doctor_data['hospital_affiliation'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>