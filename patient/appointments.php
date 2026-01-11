<?php
// patient/appointments.php

// ✅ Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Include required files with error handling
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
    error_log("Include error in appointments.php: " . $e->getMessage());
}

// ✅ Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// ✅ Check authentication - redirect if not logged in as patient
require_auth('patient');

$patient_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'Patient';

// ✅ Initialize variables
$appointments = [];
$doctors = [];
$use_db = false;
$error = '';
$success = '';

// ✅ Check database connection
if (isset($db) && $db instanceof PDO) {
    try {
        $db->query("SELECT 1");
        $use_db = true;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        $use_db = false;
    }
} elseif (isset($pdo) && $pdo instanceof PDO) {
    try {
        $db = $pdo;
        $db->query("SELECT 1");
        $use_db = true;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        $use_db = false;
    }
}

// ✅ Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $consultation_type = sanitize_input($_POST['consultation_type'] ?? '');
    $preferred_date = sanitize_input($_POST['preferred_date'] ?? '');
    $preferred_time = sanitize_input($_POST['preferred_time'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    if (empty($doctor_id) || empty($consultation_type) || empty($preferred_date) || empty($preferred_time)) {
        $error = 'Please fill in all required fields';
    } elseif (strtotime($preferred_date) < strtotime(date('Y-m-d'))) {
        $error = 'Please select a future date';
    } else {
        if ($use_db && $db) {
            try {
                // Combine date and time
                $consultation_datetime = $preferred_date . ' ' . $preferred_time;
                
                // Get doctor's consultation fee
                $fee_stmt = $db->prepare("SELECT consultation_fee FROM doctors WHERE user_id = ?");
                $fee_stmt->execute([$doctor_id]);
                $consultation_fee = $fee_stmt->fetchColumn() ?: 0;
                
                // Insert appointment
                $stmt = $db->prepare("
                    INSERT INTO consultations 
                    (patient_id, doctor_id, consultation_type, consultation_date, notes, status, fee_amount, payment_status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'scheduled', ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $patient_id,
                    $doctor_id,
                    $consultation_type,
                    $consultation_datetime,
                    $notes,
                    $consultation_fee
                ]);
                
                $consultation_id = $db->lastInsertId();
                
                // Log activity
                log_activity($patient_id, 'appointment_booked', "Booked appointment with doctor ID: $doctor_id");
                
                // Send notification to patient
                $doctor_stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
                $doctor_stmt->execute([$doctor_id]);
                $doctor_name = $doctor_stmt->fetchColumn();
                
                $message = "Your appointment with Dr. $doctor_name has been scheduled for " . date('M d, Y g:i A', strtotime($consultation_datetime));
                send_notification($patient_id, 'Appointment Scheduled', $message, 'appointment', 'medium');
                
                $success = 'Appointment booked successfully! You will receive a confirmation notification.';
                
            } catch (Exception $e) {
                error_log("Error booking appointment: " . $e->getMessage());
                $error = 'Failed to book appointment. Please try again.';
            }
        } else {
            $error = 'Database connection error. Please try again later.';
        }
    }
}

// ✅ Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    
    if ($use_db && $db) {
        try {
            $stmt = $db->prepare("
                UPDATE consultations 
                SET status = 'cancelled' 
                WHERE id = ? AND patient_id = ? AND status = 'scheduled'
            ");
            $stmt->execute([$appointment_id, $patient_id]);
            
            if ($stmt->rowCount() > 0) {
                log_activity($patient_id, 'appointment_cancelled', "Cancelled appointment ID: $appointment_id");
                $success = 'Appointment cancelled successfully.';
            } else {
                $error = 'Unable to cancel appointment. It may have already been processed.';
            }
        } catch (Exception $e) {
            error_log("Error cancelling appointment: " . $e->getMessage());
            $error = 'Failed to cancel appointment. Please try again.';
        }
    }
}

// ✅ Fetch appointments and doctors if database is available
if ($use_db && $db) {
    try {
        // Fetch patient's appointments with doctor details
        $stmt = $db->prepare("
            SELECT c.*, 
                   u.full_name as doctor_name,
                   d.specialization,
                   c.fee_amount as consultation_fee
            FROM consultations c
            LEFT JOIN users u ON c.doctor_id = u.id
            LEFT JOIN doctors d ON d.user_id = u.id
            WHERE c.patient_id = ? 
            ORDER BY c.consultation_date DESC
        ");
        $stmt->execute([$patient_id]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch available doctors
        $stmt = $db->prepare("
            SELECT u.id, u.full_name, d.specialization, d.consultation_fee, d.rating, d.experience_years, d.qualification
            FROM users u
            INNER JOIN doctors d ON u.id = d.user_id
            WHERE u.user_type = 'doctor' AND u.is_active = 1 AND d.is_verified = 1
            ORDER BY d.rating DESC, u.full_name
        ");
        $stmt->execute();
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error fetching appointments/doctors: " . $e->getMessage());
    }
}

// ✅ Demo data if no database
if (!$use_db || (empty($appointments) && empty($doctors))) {
    $doctors = [
        [
            'id' => 1,
            'full_name' => 'Dr. Sarah Johnson',
            'specialization' => 'General Medicine',
            'consultation_fee' => 100.00,
            'rating' => 4.8
        ],
        [
            'id' => 2,
            'full_name' => 'Dr. Michael Chen',
            'specialization' => 'Cardiology',
            'consultation_fee' => 150.00,
            'rating' => 4.9
        ],
        [
            'id' => 3,
            'full_name' => 'Dr. Emily Rodriguez',
            'specialization' => 'Dermatology',
            'consultation_fee' => 120.00,
            'rating' => 4.7
        ]
    ];
    
    $appointments = [
        [
            'id' => 1,
            'doctor_name' => 'Dr. Sarah Johnson',
            'specialization' => 'General Medicine',
            'consultation_type' => 'online',
            'consultation_date' => date('Y-m-d H:i:s', strtotime('+2 days 10:00')),
            'status' => 'scheduled',
            'consultation_fee' => 100.00,
            'notes' => 'Regular checkup'
        ]
    ];
}

// ✅ Helper function for status styling
function getStatusClass($status) {
    switch(strtolower($status)) {
        case 'scheduled': return 'bg-primary';
        case 'completed': return 'bg-success';
        case 'cancelled': return 'bg-secondary';
        case 'no_show': return 'bg-warning';
        default: return 'bg-secondary';
    }
}

$page_title = 'Appointments - HealthHive';

// ✅ Include header
try {
    include __DIR__ . '/../includes/header.php';
} catch (Exception $e) {
    error_log("Header include error: " . $e->getMessage());
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $page_title . '</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>';
}
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-calendar-check text-primary me-2"></i>
                        Appointments
                    </h1>
                    <p class="text-muted mb-0">Book and manage your medical appointments</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Appointments Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="book-tab" data-bs-toggle="tab" data-bs-target="#book" type="button">
                        <i class="fas fa-plus-circle me-2"></i>Book Appointment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="my-appointments-tab" data-bs-toggle="tab" data-bs-target="#my-appointments" type="button">
                        <i class="fas fa-list me-2"></i>My Appointments (<?php echo count($appointments); ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-4" id="appointmentTabContent">
                <!-- Book Appointment Tab -->
                <div class="tab-pane fade show active" id="book" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-plus me-2"></i>
                                        Book New Appointment
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="book_appointment" value="1">
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="doctor_id" class="form-label">Select Doctor *</label>
                                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                                    <option value="">Choose a doctor...</option>
                                                    <?php foreach ($doctors as $doctor): ?>
                                                        <option value="<?php echo $doctor['id']; ?>" 
                                                                data-fee="<?php echo $doctor['consultation_fee']; ?>">
                                                            Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> - 
                                                            <?php echo htmlspecialchars($doctor['specialization']); ?>
                                                            (Rating: <?php echo $doctor['rating']; ?>/5)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="consultation_type" class="form-label">Consultation Type *</label>
                                                <select class="form-select" id="consultation_type" name="consultation_type" required>
                                                    <option value="">Select type...</option>
                                                    <option value="online">Online Consultation</option>
                                                    <option value="in_person">In-Person Visit</option>
                                                    <option value="emergency">Emergency</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="preferred_date" class="form-label">Preferred Date *</label>
                                                <input type="date" class="form-control" id="preferred_date" 
                                                       name="preferred_date" min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="preferred_time" class="form-label">Preferred Time *</label>
                                                <select class="form-select" id="preferred_time" name="preferred_time" required>
                                                    <option value="">Select time...</option>
                                                    <option value="09:00">9:00 AM</option>
                                                    <option value="10:00">10:00 AM</option>
                                                    <option value="11:00">11:00 AM</option>
                                                    <option value="14:00">2:00 PM</option>
                                                    <option value="15:00">3:00 PM</option>
                                                    <option value="16:00">4:00 PM</option>
                                                    <option value="17:00">5:00 PM</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label for="notes" class="form-label">Additional Notes</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                                          placeholder="Describe your symptoms or reason for consultation..."></textarea>
                                            </div>
                                            
                                            <div class="col-12">
                                                <div id="consultation_fee_display" class="alert alert-info" style="display: none;">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Consultation Fee:</strong> $<span id="fee_amount">0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo me-2"></i>Reset Form
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Booking Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-primary me-2"></i>
                                            Appointments are scheduled within 48 hours
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-video text-success me-2"></i>
                                            Online consultations available
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-bell text-warning me-2"></i>
                                            You'll receive confirmation notifications
                                        </li>
                                        <li class="mb-0">
                                            <i class="fas fa-ban text-danger me-2"></i>
                                            Cancel at least 2 hours before appointment
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Appointments Tab -->
                <div class="tab-pane fade" id="my-appointments" role="tabpanel">
                    <?php if (empty($appointments)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-calendar-times text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">No Appointments Yet</h4>
                                <p class="text-muted">You haven't booked any appointments yet.</p>
                                <button class="btn btn-primary" onclick="document.getElementById('book-tab').click()">
                                    <i class="fas fa-plus me-2"></i>Book Your First Appointment
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user-md me-2"></i>
                                                    <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?>
                                                </small>
                                            </div>
                                            <span class="badge <?php echo getStatusClass($appointment['status']); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 mb-3">
                                                <div class="col-sm-6">
                                                    <small class="text-muted">Type:</small><br>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['consultation_type'])); ?>
                                                    </span>
                                                </div>
                                                <div class="col-sm-6">
                                                    <small class="text-muted">Fee:</small><br>
                                                    <strong class="text-success">
                                                        $<?php echo number_format($appointment['consultation_fee'] ?? 0, 2); ?>
                                                    </strong>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">Appointment Time:</small><br>
                                                <strong>
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M d, Y g:i A', strtotime($appointment['consultation_date'])); ?>
                                                </strong>
                                            </div>
                                            
                                            <?php if ($appointment['notes']): ?>
                                                <div class="mb-3">
                                                    <small class="text-muted">Notes:</small><br>
                                                    <p class="small mb-0"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <div class="d-flex gap-2">
                                                    <form method="POST" action="" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                                        <input type="hidden" name="cancel_appointment" value="1">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                                            <i class="fas fa-times me-1"></i>Cancel
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show consultation fee when doctor is selected
document.getElementById('doctor_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const fee = selectedOption.getAttribute('data-fee');
    const feeDisplay = document.getElementById('consultation_fee_display');
    const feeAmount = document.getElementById('fee_amount');
    
    if (fee && fee !== '0') {
        feeAmount.textContent = parseFloat(fee).toFixed(2);
        feeDisplay.style.display = 'block';
    } else {
        feeDisplay.style.display = 'none';
    }
});

// Set minimum date to today
document.getElementById('preferred_date').min = new Date().toISOString().split('T')[0];

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = ['doctor_id', 'consultation_type', 'preferred_date', 'preferred_time'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});
</script>

<?php 
// ✅ Include footer
try {
    include __DIR__ . '/../includes/footer.php';
} catch (Exception $e) {
    error_log("Footer include error: " . $e->getMessage());
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
?>