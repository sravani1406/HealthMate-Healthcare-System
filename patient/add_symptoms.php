<?php
// patient/add_symptom.php

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
    error_log("Include error in add_symptom.php: " . $e->getMessage());
}

// ✅ Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// ✅ Check authentication - redirect if not logged in as patient
require_auth('patient');

$patient_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ✅ Use consistent database variable ($db instead of $pdo)
$use_db = false;
$patient = null;

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

// ✅ Get patient info from database if available
if ($use_db && $db) {
    try {
        $stmt = $db->prepare("
            SELECT u.*, pr.* 
            FROM users u 
            LEFT JOIN patient_records pr ON u.id = pr.patient_id 
            WHERE u.id = ? AND u.user_type = 'patient'
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching patient info: " . $e->getMessage());
    }
}

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_symptoms = $_POST['symptoms'] ?? [];
    $severity_levels = $_POST['severity'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $body_temperature = sanitize_input($_POST['body_temperature'] ?? '');
    $blood_pressure = sanitize_input($_POST['blood_pressure'] ?? '');
    $pulse_rate = sanitize_input($_POST['pulse_rate'] ?? '');
    $additional_notes = sanitize_input($_POST['additional_notes'] ?? '');
    
    if (empty($selected_symptoms)) {
        $error = 'Please select at least one symptom';
    } else {
        if ($use_db && $db) {
            try {
                // ✅ Prepare symptoms data properly
                $symptoms_list = [];
                foreach ($selected_symptoms as $index => $symptom) {
                    $symptoms_list[] = [
                        'symptom' => $symptom,
                        'severity' => $severity_levels[$index] ?? 'mild',
                        'duration' => $durations[$index] ?? 'recent'
                    ];
                }
                
                // ✅ Determine overall severity and risk level
                $overall_severity = 'mild';
                $risk_level = 'low';
                
                foreach ($severity_levels as $sev) {
                    if ($sev === 'severe') {
                        $overall_severity = 'severe';
                        $risk_level = 'high';
                        break;
                    } elseif ($sev === 'moderate' && $overall_severity !== 'severe') {
                        $overall_severity = 'moderate';
                        $risk_level = 'medium';
                    }
                }
                
                // ✅ Insert into symptoms table
                $stmt = $db->prepare("
                    INSERT INTO symptoms 
                    (patient_id, symptoms, severity, duration, additional_notes, 
                     body_temperature, blood_pressure, pulse_rate, risk_level, reported_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $symptoms_text = implode(', ', $selected_symptoms);
                $duration_text = !empty($durations) ? $durations[0] : 'recent';
                
                $stmt->execute([
                    $patient_id,
                    $symptoms_text,
                    $overall_severity,
                    $duration_text,
                    $additional_notes,
                    $body_temperature ?: null,
                    $blood_pressure ?: null,
                    $pulse_rate ?: null,
                    $risk_level
                ]);

                $symptom_id = $db->lastInsertId();
                
                // ✅ Log the activity
                log_activity($patient_id, 'symptom_reported', "Reported symptoms: $symptoms_text");
                
                // ✅ Create notification for high-risk symptoms
                if ($risk_level === 'high' || $overall_severity === 'severe') {
                    $message = "High-risk symptoms detected: $symptoms_text. Please consult a doctor immediately.";
                    send_notification($patient_id, 'High-Risk Symptoms Alert', $message, 'alert', 'urgent');
                }
                
                $success = 'Symptoms recorded successfully! ' . 
                          ($risk_level === 'high' ? 'Due to the severity of your symptoms, please consult a healthcare provider immediately.' : 
                           'You can view your symptom history in your medical records.');
                
            } catch (Exception $e) {
                error_log("Error saving symptoms: " . $e->getMessage());
                $error = 'Failed to save symptoms. Please try again.';
            }
        } else {
            $error = 'Database connection error. Please try again later.';
        }
    }
}

// ✅ Common symptoms list
$common_symptoms = [
    'fever' => 'Fever',
    'headache' => 'Headache', 
    'cough' => 'Cough',
    'sore_throat' => 'Sore Throat',
    'nausea' => 'Nausea',
    'vomiting' => 'Vomiting',
    'diarrhea' => 'Diarrhea',
    'abdominal_pain' => 'Abdominal Pain',
    'chest_pain' => 'Chest Pain',
    'shortness_of_breath' => 'Shortness of Breath',
    'fatigue' => 'Fatigue',
    'dizziness' => 'Dizziness',
    'muscle_aches' => 'Muscle Aches',
    'joint_pain' => 'Joint Pain',
    'rash' => 'Skin Rash',
    'back_pain' => 'Back Pain',
    'neck_pain' => 'Neck Pain',
    'difficulty_swallowing' => 'Difficulty Swallowing',
    'loss_of_appetite' => 'Loss of Appetite',
    'weight_loss' => 'Unexplained Weight Loss',
    'night_sweats' => 'Night Sweats',
    'chills' => 'Chills',
    'difficulty_sleeping' => 'Difficulty Sleeping',
    'runny_nose' => 'Runny Nose',
    'congestion' => 'Nasal Congestion'
];

$page_title = 'Report Symptoms - HealthHive';

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
        <style>
            body { background: #f5f5f5 !important; }
        </style>
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
                        <i class="fas fa-thermometer-half text-primary me-2"></i>
                        Report Your Symptoms
                    </h1>
                    <p class="text-muted mb-0">Please describe your current symptoms accurately for better health tracking</p>
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
                    <?php echo $success; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Symptom Form -->
    <form method="POST" action="" id="symptomsForm">
        <div class="row">
            <!-- Symptoms Selection -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list-check me-2"></i>
                            Select Your Symptoms
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($common_symptoms as $value => $label): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="symptoms[]" value="<?php echo $value; ?>" 
                                               id="symptom_<?php echo $value; ?>"
                                               onchange="toggleSymptomDetails('<?php echo $value; ?>')">
                                        <label class="form-check-label" for="symptom_<?php echo $value; ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Custom Symptoms -->
                        <div class="mt-4">
                            <div class="border-top pt-3">
                                <h6>Other Symptoms</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="custom_symptom" 
                                           placeholder="Enter any other symptoms (separate by commas)">
                                    <button type="button" class="btn btn-outline-primary" onclick="addCustomSymptoms()">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Symptom Details -->
                <div id="symptomDetails" class="card mb-4" style="display: none;">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Symptom Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="selectedSymptoms" class="row g-3"></div>
                    </div>
                </div>

                <!-- Vital Signs -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-heartbeat me-2"></i>
                            Vital Signs (Optional)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="body_temperature" class="form-label">Body Temperature (°C)</label>
                                <input type="number" class="form-control" id="body_temperature" 
                                       name="body_temperature" step="0.1" min="35" max="45" 
                                       placeholder="e.g., 37.5">
                            </div>
                            <div class="col-md-4">
                                <label for="blood_pressure" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="blood_pressure" 
                                       name="blood_pressure" placeholder="e.g., 120/80">
                            </div>
                            <div class="col-md-4">
                                <label for="pulse_rate" class="form-label">Pulse Rate (BPM)</label>
                                <input type="number" class="form-control" id="pulse_rate" 
                                       name="pulse_rate" min="40" max="200" placeholder="e.g., 72">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-sticky-note me-2"></i>
                            Additional Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="additional_notes" class="form-label">
                                Describe any additional details about your condition
                            </label>
                            <textarea class="form-control" id="additional_notes" name="additional_notes" 
                                      rows="4" placeholder="When did symptoms start? What makes them better or worse? Any other relevant information..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar with Instructions -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Important Instructions
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Select all symptoms you're experiencing
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Be honest about severity levels
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Include when symptoms started
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Mention vital signs if available
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Emergency Warning
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-3"><strong>Seek immediate medical attention if you experience:</strong></p>
                        <ul class="small mb-3">
                            <li>Severe chest pain</li>
                            <li>Difficulty breathing</li>
                            <li>Loss of consciousness</li>
                            <li>Severe bleeding</li>
                            <li>High fever (over 40°C)</li>
                        </ul>
                        <a href="tel:911" class="btn btn-danger btn-sm w-100">
                            <i class="fas fa-phone me-2"></i>Call Emergency: 911
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex gap-2 justify-content-end">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit Symptoms
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let selectedSymptoms = [];

function toggleSymptomDetails(symptom) {
    const checkbox = event.target;
    const detailsContainer = document.getElementById('symptomDetails');
    
    if (checkbox.checked) {
        selectedSymptoms.push(symptom);
        addSymptomDetail(symptom);
    } else {
        selectedSymptoms = selectedSymptoms.filter(s => s !== symptom);
        removeSymptomDetail(symptom);
    }
    
    detailsContainer.style.display = selectedSymptoms.length > 0 ? 'block' : 'none';
}

function addSymptomDetail(symptom) {
    const container = document.getElementById('selectedSymptoms');
    const symptomDiv = document.createElement('div');
    symptomDiv.id = `detail_${symptom}`;
    symptomDiv.className = 'col-lg-6';
    
    const symptomName = symptom.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    
    symptomDiv.innerHTML = `
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">${symptomName}</h6>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Severity:</label>
                        <select name="severity[]" class="form-select form-select-sm">
                            <option value="mild">Mild</option>
                            <option value="moderate">Moderate</option>
                            <option value="severe">Severe</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Duration:</label>
                        <select name="duration[]" class="form-select form-select-sm">
                            <option value="recent">Just started</option>
                            <option value="few_hours">Few hours</option>
                            <option value="today">Today</option>
                            <option value="few_days">Few days</option>
                            <option value="week">About a week</option>
                            <option value="weeks">Several weeks</option>
                            <option value="months">Months</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(symptomDiv);
}

function removeSymptomDetail(symptom) {
    const element = document.getElementById(`detail_${symptom}`);
    if (element) {
        element.remove();
    }
}

function addCustomSymptoms() {
    const input = document.getElementById('custom_symptom');
    const symptoms = input.value.split(',').map(s => s.trim()).filter(s => s.length > 0);
    
    if (symptoms.length === 0) {
        alert('Please enter at least one symptom');
        return;
    }
    
    symptoms.forEach(symptom => {
        const symptomValue = symptom.toLowerCase().replace(/\s+/g, '_');
        
        // Check if already exists
        if (document.getElementById(`symptom_${symptomValue}`)) {
            return;
        }
        
        // Find a good place to add it (end of symptoms grid)
        const grid = document.querySelector('.row.g-3');
        const newCol = document.createElement('div');
        newCol.className = 'col-md-6 col-lg-4';
        newCol.innerHTML = `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" 
                       name="symptoms[]" value="${symptomValue}" 
                       id="symptom_${symptomValue}" checked
                       onchange="toggleSymptomDetails('${symptomValue}')">
                <label class="form-check-label" for="symptom_${symptomValue}">
                    ${symptom} <span class="badge bg-secondary ms-1">Custom</span>
                </label>
            </div>
        `;
        grid.appendChild(newCol);
        
        selectedSymptoms.push(symptomValue);
        addSymptomDetail(symptomValue);
    });
    
    document.getElementById('symptomDetails').style.display = 'block';
    input.value = '';
}

// Form validation
document.getElementById('symptomsForm').addEventListener('submit', function(e) {
    const checkedSymptoms = document.querySelectorAll('input[name="symptoms[]"]:checked');
    
    if (checkedSymptoms.length === 0) {
        e.preventDefault();
        alert('Please select at least one symptom before submitting.');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    submitBtn.disabled = true;
});
</script>

<?php 
