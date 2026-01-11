<?php
// patient/prescriptions.php

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
    error_log("Include error in prescriptions.php: " . $e->getMessage());
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
$prescriptions = [];
$medications = [];
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

// ✅ Handle adding medication reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medication'])) {
    $medication_name = sanitize_input($_POST['medication_name'] ?? '');
    $dosage = sanitize_input($_POST['dosage'] ?? '');
    $frequency = sanitize_input($_POST['frequency'] ?? '');
    $start_date = sanitize_input($_POST['start_date'] ?? '');
    $end_date = sanitize_input($_POST['end_date'] ?? '');
    $times_per_day = (int)($_POST['times_per_day'] ?? 1);
    $instructions = sanitize_input($_POST['instructions'] ?? '');
    
    if (empty($medication_name) || empty($dosage) || empty($frequency) || empty($start_date)) {
        $error = 'Please fill in all required fields';
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = 'Start date cannot be in the past';
    } else {
        if ($use_db && $db) {
            try {
                // Generate reminder times based on frequency
                $reminder_times = [];
                for ($i = 0; $i < $times_per_day; $i++) {
                    $hour = 8 + ($i * (12 / $times_per_day)); // Spread throughout day starting at 8 AM
                    $reminder_times[] = sprintf("%02d:00", $hour);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO medication_reminders 
                    (patient_id, medication_name, dosage, frequency, start_date, end_date, 
                     times_per_day, reminder_times, instructions, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                
                $stmt->execute([
                    $patient_id,
                    $medication_name,
                    $dosage,
                    $frequency,
                    $start_date,
                    $end_date ?: null,
                    $times_per_day,
                    json_encode($reminder_times),
                    $instructions
                ]);
                
                log_activity($patient_id, 'medication_added', "Added medication reminder: $medication_name");
                
                $message = "Medication reminder set for $medication_name ($dosage) - $frequency";
                send_notification($patient_id, 'Medication Reminder Added', $message, 'medication', 'medium');
                
                $success = 'Medication reminder added successfully!';
                
            } catch (Exception $e) {
                error_log("Error adding medication: " . $e->getMessage());
                $error = 'Failed to add medication reminder. Please try again.';
            }
        } else {
            $error = 'Database connection error. Please try again later.';
        }
    }
}

// ✅ Handle updating medication status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_medication'])) {
    $medication_id = (int)($_POST['medication_id'] ?? 0);
    $new_status = (int)($_POST['new_status'] ?? 0);
    
    if ($use_db && $db) {
        try {
            $stmt = $db->prepare("
                UPDATE medication_reminders 
                SET is_active = ? 
                WHERE id = ? AND patient_id = ?
            ");
            $stmt->execute([$new_status, $medication_id, $patient_id]);
            
            if ($stmt->rowCount() > 0) {
                $action = $new_status ? 'activated' : 'deactivated';
                log_activity($patient_id, 'medication_updated', "Medication reminder $action");
                $success = 'Medication status updated successfully.';
            } else {
                $error = 'Unable to update medication status.';
            }
        } catch (Exception $e) {
            error_log("Error updating medication: " . $e->getMessage());
            $error = 'Failed to update medication. Please try again.';
        }
    }
}

// ✅ Fetch data if database is available
if ($use_db && $db) {
    try {
        // Fetch prescriptions from consultations
        $stmt = $db->prepare("
            SELECT c.id, c.prescription, c.consultation_date, c.diagnosis,
                   u.full_name as doctor_name, d.specialization
            FROM consultations c
            LEFT JOIN users u ON c.doctor_id = u.id
            LEFT JOIN doctors d ON d.user_id = u.id
            WHERE c.patient_id = ? AND c.prescription IS NOT NULL AND c.prescription != ''
            ORDER BY c.consultation_date DESC
        ");
        $stmt->execute([$patient_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch medication reminders
        $stmt = $db->prepare("
            SELECT * FROM medication_reminders 
            WHERE patient_id = ? 
            ORDER BY is_active DESC, created_at DESC
        ");
        $stmt->execute([$patient_id]);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error fetching prescriptions/medications: " . $e->getMessage());
    }
}

// ✅ Demo data if no database
if (!$use_db || (empty($prescriptions) && empty($medications))) {
    $prescriptions = [
        [
            'id' => 1,
            'prescription' => 'Lisinopril 10mg - Take once daily for blood pressure\nAspirin 81mg - Take once daily with food',
            'consultation_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'diagnosis' => 'Hypertension management',
            'doctor_name' => 'Dr. Sarah Johnson',
            'specialization' => 'Cardiology'
        ],
        [
            'id' => 2,
            'prescription' => 'Amoxicillin 500mg - Take three times daily for 7 days\nIbuprofen 400mg - Take as needed for pain',
            'consultation_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'diagnosis' => 'Bacterial infection',
            'doctor_name' => 'Dr. Michael Chen',
            'specialization' => 'General Medicine'
        ]
    ];
    
    $medications = [
        [
            'id' => 1,
            'medication_name' => 'Lisinopril',
            'dosage' => '10mg',
            'frequency' => 'Once daily',
            'start_date' => date('Y-m-d', strtotime('-1 month')),
            'end_date' => null,
            'times_per_day' => 1,
            'instructions' => 'Take with water, preferably in the morning',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month'))
        ],
        [
            'id' => 2,
            'medication_name' => 'Vitamin D3',
            'dosage' => '1000 IU',
            'frequency' => 'Once daily',
            'start_date' => date('Y-m-d', strtotime('-2 weeks')),
            'end_date' => date('Y-m-d', strtotime('+3 months')),
            'times_per_day' => 1,
            'instructions' => 'Take with meal for better absorption',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 weeks'))
        ]
    ];
}

$page_title = 'Prescriptions & Medications - HealthHive';

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
                        <i class="fas fa-prescription-bottle text-primary me-2"></i>
                        Prescriptions & Medications
                    </h1>
                    <p class="text-muted mb-0">Manage your prescriptions and medication reminders</p>
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-file-prescription fa-2x mb-2 opacity-75"></i>
                    <h3><?php echo count($prescriptions); ?></h3>
                    <p class="mb-0">Total Prescriptions</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-pills fa-2x mb-2 opacity-75"></i>
                    <h3><?php echo count(array_filter($medications, function($m) { return $m['is_active']; })); ?></h3>
                    <p class="mb-0">Active Medications</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-bell fa-2x mb-2 opacity-75"></i>
                    <h3><?php echo array_sum(array_column(array_filter($medications, function($m) { return $m['is_active']; }), 'times_per_day')); ?></h3>
                    <p class="mb-0">Daily Reminders</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs" id="prescriptionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptions" type="button">
                        <i class="fas fa-file-prescription me-2"></i>Doctor Prescriptions
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="medications-tab" data-bs-toggle="tab" data-bs-target="#medications" type="button">
                        <i class="fas fa-pills me-2"></i>Medication Reminders
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="add-medication-tab" data-bs-toggle="tab" data-bs-target="#add-medication" type="button">
                        <i class="fas fa-plus-circle me-2"></i>Add Reminder
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-4" id="prescriptionTabContent">
                <!-- Prescriptions Tab -->
                <div class="tab-pane fade show active" id="prescriptions" role="tabpanel">
                    <?php if (empty($prescriptions)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-file-prescription text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">No Prescriptions Yet</h4>
                                <p class="text-muted">Your doctor prescriptions will appear here after consultations.</p>
                                <a href="appointments.php" class="btn btn-primary">Book Consultation</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($prescriptions as $prescription): ?>
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-user-md me-2"></i>
                                                        <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                                                    </h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($prescription['specialization'] ?? 'General Medicine'); ?></small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($prescription['consultation_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($prescription['diagnosis']): ?>
                                                <div class="mb-3">
                                                    <strong class="text-info">Diagnosis:</strong>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="alert alert-success">
                                                <strong><i class="fas fa-prescription-bottle me-2"></i>Prescription:</strong>
                                                <div class="mt-2">
                                                    <?php echo nl2br(htmlspecialchars($prescription['prescription'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Prescribed on <?php echo date('M d, Y g:i A', strtotime($prescription['consultation_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Medications Tab -->
                <div class="tab-pane fade" id="medications" role="tabpanel">
                    <?php if (empty($medications)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-pills text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">No Medication Reminders</h4>
                                <p class="text-muted">Add medication reminders to track your daily medications.</p>
                                <button class="btn btn-primary" onclick="document.getElementById('add-medication-tab').click()">
                                    <i class="fas fa-plus me-2"></i>Add First Medication
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($medications as $medication): ?>
                                <div class="col-lg-6">
                                    <div class="card <?php echo $medication['is_active'] ? '' : 'border-secondary'; ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($medication['medication_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($medication['dosage']); ?></small>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?php echo $medication['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $medication['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="toggle_medication" value="1">
                                                    <input type="hidden" name="medication_id" value="<?php echo $medication['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $medication['is_active'] ? 0 : 1; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $medication['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                            title="<?php echo $medication['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-2 mb-3">
                                                <div class="col-sm-6">
                                                    <small class="text-muted">Frequency:</small><br>
                                                    <strong><?php echo htmlspecialchars($medication['frequency']); ?></strong>
                                                </div>
                                                <div class="col-sm-6">
                                                    <small class="text-muted">Times per day:</small><br>
                                                    <strong><?php echo $medication['times_per_day']; ?>x daily</strong>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-2 mb-3">
                                                <div class="col-sm-6">
                                                    <small class="text-muted">Start Date:</small><br>
                                                    <?php echo date('M d, Y', strtotime($medication['start_date'])); ?>
                                                </div>
                                                <div class="col-sm-6">
                                                    <small class="text-muted">End Date:</small><br>
                                                    <?php echo $medication['end_date'] ? date('M d, Y', strtotime($medication['end_date'])) : 'Ongoing'; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($medication['instructions']): ?>
                                                <div class="alert alert-info small">
                                                    <strong>Instructions:</strong>
                                                    <?php echo nl2br(htmlspecialchars($medication['instructions'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($medication['is_active'] && !empty($medication['reminder_times'])): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">Reminder Times:</small><br>
                                                    <?php
                                                    $times = json_decode($medication['reminder_times'], true) ?: [];
                                                    foreach ($times as $time) {
                                                        echo '<span class="badge bg-primary me-1">' . date('g:i A', strtotime($time)) . '</span>';
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add Medication Tab -->
                <div class="tab-pane fade" id="add-medication" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        Add Medication Reminder
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="add_medication" value="1">
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="medication_name" class="form-label">Medication Name *</label>
                                                <input type="text" class="form-control" id="medication_name" 
                                                       name="medication_name" required placeholder="e.g., Aspirin">
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="dosage" class="form-label">Dosage *</label>
                                                <input type="text" class="form-control" id="dosage" 
                                                       name="dosage" required placeholder="e.g., 100mg">
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="frequency" class="form-label">Frequency *</label>
                                                <select class="form-select" id="frequency" name="frequency" required>
                                                    <option value="">Select frequency...</option>
                                                    <option value="Once daily">Once daily</option>
                                                    <option value="Twice daily">Twice daily</option>
                                                    <option value="Three times daily">Three times daily</option>
                                                    <option value="Four times daily">Four times daily</option>
                                                    <option value="As needed">As needed</option>
                                                    <option value="Weekly">Weekly</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="times_per_day" class="form-label">Times per Day *</label>
                                                <select class="form-select" id="times_per_day" name="times_per_day" required>
                                                    <option value="1">1 time</option>
                                                    <option value="2">2 times</option>
                                                    <option value="3">3 times</option>
                                                    <option value="4">4 times</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="start_date" class="form-label">Start Date *</label>
                                                <input type="date" class="form-control" id="start_date" 
                                                       name="start_date" min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="end_date" class="form-label">End Date (Optional)</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date">
                                            </div>
                                            
                                            <div class="col-12">
                                                <label for="instructions" class="form-label">Special Instructions</label>
                                                <textarea class="form-control" id="instructions" name="instructions" rows="3"
                                                          placeholder="e.g., Take with food, avoid alcohol, etc."></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Medication Reminder
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo me-2"></i>Reset Form
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update end date minimum when start date changes
document.getElementById('start_date').addEventListener('change', function() {
    const endDate = document.getElementById('end_date');
    endDate.min = this.value;
});

// Auto-update times per day based on frequency
document.getElementById('frequency').addEventListener('change', function() {
    const timesSelect = document.getElementById('times_per_day');
    const frequency = this.value;
    
    // Auto-select times based on common frequencies
    if (frequency.includes('Once')) {
        timesSelect.value = '1';
    } else if (frequency.includes('Twice')) {
        timesSelect.value = '2';
    } else if (frequency.includes('Three')) {
        timesSelect.value = '3';
    } else if (frequency.includes('Four')) {
        timesSelect.value = '4';
    }
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = ['medication_name', 'dosage', 'frequency', 'start_date'];
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