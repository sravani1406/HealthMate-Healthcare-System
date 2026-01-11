<?php
// doctor/settings.php

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

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$doctor_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $doctor_id]);
                
                log_activity($doctor_id, 'password_changed', 'Password was changed');
                $success = 'Password changed successfully!';
            } else {
                $error = 'Current password is incorrect';
            }
        } catch (Exception $e) {
            error_log("Error changing password: " . $e->getMessage());
            $error = 'Failed to change password. Please try again.';
        }
    }
}

// Handle notification settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
    $patient_updates = isset($_POST['patient_updates']) ? 1 : 0;
    
    // Store in session for now (you can add a settings table to database)
    $_SESSION['settings'] = [
        'email_notifications' => $email_notifications,
        'sms_notifications' => $sms_notifications,
        'appointment_reminders' => $appointment_reminders,
        'patient_updates' => $patient_updates
    ];
    
    $success = 'Notification settings updated successfully!';
}

// Handle availability settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability'])) {
    $availability_schedule = json_encode([
        'monday' => ['enabled' => isset($_POST['monday_enabled']), 'start' => $_POST['monday_start'] ?? '', 'end' => $_POST['monday_end'] ?? ''],
        'tuesday' => ['enabled' => isset($_POST['tuesday_enabled']), 'start' => $_POST['tuesday_start'] ?? '', 'end' => $_POST['tuesday_end'] ?? ''],
        'wednesday' => ['enabled' => isset($_POST['wednesday_enabled']), 'start' => $_POST['wednesday_start'] ?? '', 'end' => $_POST['wednesday_end'] ?? ''],
        'thursday' => ['enabled' => isset($_POST['thursday_enabled']), 'start' => $_POST['thursday_start'] ?? '', 'end' => $_POST['thursday_end'] ?? ''],
        'friday' => ['enabled' => isset($_POST['friday_enabled']), 'start' => $_POST['friday_start'] ?? '', 'end' => $_POST['friday_end'] ?? ''],
        'saturday' => ['enabled' => isset($_POST['saturday_enabled']), 'start' => $_POST['saturday_start'] ?? '', 'end' => $_POST['saturday_end'] ?? ''],
        'sunday' => ['enabled' => isset($_POST['sunday_enabled']), 'start' => $_POST['sunday_start'] ?? '', 'end' => $_POST['sunday_end'] ?? '']
    ]);
    
    try {
        $stmt = $db->prepare("UPDATE doctors SET availability_schedule = ? WHERE user_id = ?");
        $stmt->execute([$availability_schedule, $doctor_id]);
        
        log_activity($doctor_id, 'availability_updated', 'Availability schedule updated');
        $success = 'Availability schedule updated successfully!';
    } catch (Exception $e) {
        error_log("Error updating availability: " . $e->getMessage());
        $error = 'Failed to update availability. Please try again.';
    }
}

// Fetch current settings
$user_data = null;
$doctor_data = null;
$settings = $_SESSION['settings'] ?? [
    'email_notifications' => 1,
    'sms_notifications' => 1,
    'appointment_reminders' => 1,
    'patient_updates' => 1
];

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT * FROM doctors WHERE user_id = ?");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Parse availability schedule
$availability = json_decode($doctor_data['availability_schedule'] ?? '{}', true);
if (!$availability) {
    $availability = [
        'monday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'tuesday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'wednesday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'thursday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'friday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
        'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00']
    ];
}

$page_title = 'Settings - HealthHive';
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
        .settings-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: #28a745;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .card-header h5 {
            margin: 0;
        }
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 15px 25px;
            font-weight: 500;
        }
        .nav-tabs .nav-link:hover {
            color: #28a745;
            border-color: transparent;
        }
        .nav-tabs .nav-link.active {
            color: #28a745;
            background: transparent;
            border-bottom: 3px solid #28a745;
        }
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-primary {
            background: #28a745;
            border-color: #28a745;
        }
        .btn-primary:hover {
            background: #218838;
            border-color: #218838;
        }
        .time-slot {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .day-label {
            min-width: 100px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="settings-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-cog text-primary me-2"></i>
                        Settings
                    </h1>
                    <p class="text-muted mb-0">Manage your account and preferences</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
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

        <!-- Settings Tabs -->
        <div class="card">
            <div class="card-body p-0">
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                            <i class="fas fa-lock me-2"></i>Security
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="availability-tab" data-bs-toggle="tab" data-bs-target="#availability" type="button" role="tab">
                            <i class="fas fa-calendar-alt me-2"></i>Availability
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                            <i class="fas fa-user-cog me-2"></i>Account
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="settingsTabContent">
                    <!-- Security Tab -->
                    <div class="tab-pane fade show active" id="security" role="tabpanel">
                        <h5 class="mb-4">Change Password</h5>
                        <form method="POST" action="">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle me-2"></i>Password Requirements</h6>
                                        <ul class="mb-0 ps-3">
                                            <li>Minimum 6 characters long</li>
                                            <li>Use a mix of letters and numbers</li>
                                            <li>Avoid common passwords</li>
                                            <li>Don't reuse old passwords</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Notifications Tab -->
                    <div class="tab-pane fade" id="notifications" role="tabpanel">
                        <h5 class="mb-4">Notification Preferences</h5>
                        <form method="POST" action="">
                            <input type="hidden" name="update_notifications" value="1">
                            
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">General Notifications</h6>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        <strong>Email Notifications</strong>
                                        <p class="text-muted small mb-0">Receive notifications via email</p>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications"
                                           <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_notifications">
                                        <strong>SMS Notifications</strong>
                                        <p class="text-muted small mb-0">Receive text message alerts</p>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Specific Notifications</h6>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="appointment_reminders" name="appointment_reminders"
                                           <?php echo $settings['appointment_reminders'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="appointment_reminders">
                                        <strong>Appointment Reminders</strong>
                                        <p class="text-muted small mb-0">Get reminded about upcoming appointments</p>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="patient_updates" name="patient_updates"
                                           <?php echo $settings['patient_updates'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="patient_updates">
                                        <strong>Patient Updates</strong>
                                        <p class="text-muted small mb-0">Receive notifications about patient activities</p>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Notification Settings
                            </button>
                        </form>
                    </div>

                    <!-- Availability Tab -->
                    <div class="tab-pane fade" id="availability" role="tabpanel">
                        <h5 class="mb-4">Weekly Availability Schedule</h5>
                        <form method="POST" action="">
                            <input type="hidden" name="update_availability" value="1">
                            
                            <?php
                            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            foreach ($days as $day):
                                $day_data = $availability[$day] ?? ['enabled' => false, 'start' => '09:00', 'end' => '17:00'];
                            ?>
                            <div class="time-slot">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="<?php echo $day; ?>_enabled" name="<?php echo $day; ?>_enabled"
                                           <?php echo $day_data['enabled'] ? 'checked' : ''; ?>>
                                </div>
                                <label class="day-label" for="<?php echo $day; ?>_enabled">
                                    <?php echo ucfirst($day); ?>
                                </label>
                                <input type="time" class="form-control" name="<?php echo $day; ?>_start" 
                                       value="<?php echo $day_data['start']; ?>" style="max-width: 150px;">
                                <span>to</span>
                                <input type="time" class="form-control" name="<?php echo $day; ?>_end" 
                                       value="<?php echo $day_data['end']; ?>" style="max-width: 150px;">
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Availability
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Account Tab -->
                    <div class="tab-pane fade" id="account" role="tabpanel">
                        <h5 class="mb-4">Account Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Full Name</label>
                                    <p class="fw-bold"><?php echo htmlspecialchars($user_data['full_name'] ?? 'N/A'); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Email</label>
                                    <p class="fw-bold"><?php echo htmlspecialchars($user_data['email'] ?? 'N/A'); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Phone</label>
                                    <p class="fw-bold"><?php echo htmlspecialchars($user_data['phone'] ?? 'N/A'); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Account Type</label>
                                    <p class="fw-bold">
                                        <span class="badge bg-success">Doctor</span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Member Since</label>
                                    <p class="fw-bold"><?php echo date('F d, Y', strtotime($user_data['created_at'] ?? 'now')); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Last Updated</label>
                                    <p class="fw-bold"><?php echo date('F d, Y', strtotime($user_data['updated_at'] ?? 'now')); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted">Account Status</label>
                                    <p class="fw-bold">
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Active
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Profile
                                </a>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash me-2"></i>Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Warning:</strong> This action cannot be undone!</p>
                    <p>Deleting your account will permanently remove:</p>
                    <ul>
                        <li>All your profile information</li>
                        <li>Consultation history</li>
                        <li>Patient records access</li>
                        <li>Account settings</li>
                    </ul>
                    <p class="text-danger">Are you absolutely sure you want to delete your account?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Yes, Delete My Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Password match validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>