<?php
// patient/view_records.php

// Enable error reporting
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

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Check authentication
require_auth('patient');

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['full_name'] ?? 'Patient';

// Redirect if user_id is missing
if (!$user_id) {
    header("Location: " . SITE_URL . "login.php");
    exit();
}

// Initialize variables
$patient_record = [];
$symptoms = [];
$consultations = [];
$medications = [];
$use_db = false;
$error_message = '';

// Database connection check
if (isset($db) && $db instanceof PDO) {
    try {
        $db->query("SELECT 1");
        $use_db = true;
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $use_db = false;
    }
} elseif (isset($pdo) && $pdo instanceof PDO) {
    try {
        $db = $pdo;
        $db->query("SELECT 1");
        $use_db = true;
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $use_db = false;
    }
}

// Fetch data from DB
if ($use_db && $db) {
    try {
        // Patient record
        $stmt = $db->prepare("SELECT * FROM patient_records WHERE patient_id = ?");
        $stmt->execute([$user_id]);
        $patient_record = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Symptoms
        $stmt = $db->prepare("SELECT * FROM symptoms WHERE patient_id = ? ORDER BY reported_at DESC LIMIT 30");
        $stmt->execute([$user_id]);
        $symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Consultations
        $stmt = $db->prepare("
            SELECT c.*, u.full_name as doctor_name, d.specialization
            FROM consultations c
            LEFT JOIN users u ON c.doctor_id = u.id
            LEFT JOIN doctors d ON d.user_id = u.id
            WHERE c.patient_id = ?
            ORDER BY c.consultation_date DESC LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Medications
        $stmt = $db->prepare("SELECT * FROM medication_reminders WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Error fetching records: " . $e->getMessage());
        $error_message = "Unable to load some medical records.";
    }
}

// Process symptoms for visualization
$severity_counts = ['mild' => 0, 'moderate' => 0, 'severe' => 0];
$risk_counts = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
$status_counts = ['active' => 0, 'resolved' => 0, 'under_treatment' => 0];
$symptoms_timeline = [];

// Calculate totals
$total_symptoms = count($symptoms);
$total_severity = 0;
$total_risk = 0;

foreach ($symptoms as $s) {
    // Count severity levels
    $sev = strtolower($s['severity'] ?? '');
    if (isset($severity_counts[$sev])) {
        $severity_counts[$sev]++;
        $total_severity++;
    }
    
    // Count risk levels
    $risk = strtolower($s['risk_level'] ?? '');
    if (isset($risk_counts[$risk])) {
        $risk_counts[$risk]++;
        $total_risk++;
    }
    
    // Count status
    $status = strtolower($s['status'] ?? '');
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
    
    // Timeline data
    $date = date('M d', strtotime($s['reported_at']));
    if (!isset($symptoms_timeline[$date])) {
        $symptoms_timeline[$date] = 0;
    }
    $symptoms_timeline[$date]++;
}

// Reverse timeline for chronological order and limit to last 10 dates
$symptoms_timeline = array_reverse($symptoms_timeline, true);
$symptoms_timeline = array_slice($symptoms_timeline, -10, 10, true);

// Calculate percentages
$severity_percentages = [
    'mild' => $total_severity > 0 ? round(($severity_counts['mild'] / $total_severity) * 100, 1) : 0,
    'moderate' => $total_severity > 0 ? round(($severity_counts['moderate'] / $total_severity) * 100, 1) : 0,
    'severe' => $total_severity > 0 ? round(($severity_counts['severe'] / $total_severity) * 100, 1) : 0,
];

$risk_percentages = [
    'low' => $total_risk > 0 ? round(($risk_counts['low'] / $total_risk) * 100, 1) : 0,
    'medium' => $total_risk > 0 ? round(($risk_counts['medium'] / $total_risk) * 100, 1) : 0,
    'high' => $total_risk > 0 ? round(($risk_counts['high'] / $total_risk) * 100, 1) : 0,
    'critical' => $total_risk > 0 ? round(($risk_counts['critical'] / $total_risk) * 100, 1) : 0,
];

// Helper functions
function safe_html($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatSeverity($severity) {
    $classes = ['mild'=>'bg-success','moderate'=>'bg-warning','severe'=>'bg-danger'];
    return $classes[$severity] ?? 'bg-secondary';
}

function formatRiskLevel($risk) {
    $classes = ['low'=>'bg-success','medium'=>'bg-warning','high'=>'bg-danger','critical'=>'bg-dark'];
    return $classes[$risk] ?? 'bg-secondary';
}

function formatStatus($status) {
    $classes = [
        'active'=>'bg-warning','resolved'=>'bg-success',
        'under_treatment'=>'bg-info','scheduled'=>'bg-primary',
        'completed'=>'bg-success','cancelled'=>'bg-secondary'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

$page_title = 'Medical Records - HealthHive';

// Include header
include __DIR__ . '/../includes/header.php';
?>

<style>
.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-card h3 {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0;
}
.stat-card p {
    margin: 0;
    opacity: 0.9;
}
.chart-container {
    position: relative;
    height: 300px;
    padding: 20px;
}
.insight-box {
    background: #f8f9fa;
    border-left: 4px solid #667eea;
    padding: 15px;
    margin-top: 15px;
    border-radius: 5px;
}
.insight-box h6 {
    color: #667eea;
    font-weight: bold;
    margin-bottom: 10px;
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1"><i class="fas fa-file-medical text-primary me-2"></i>Medical Records</h1>
                <p class="text-muted mb-0">Comprehensive medical history for <?php echo safe_html($user_name); ?></p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>
    </div>

    <!-- Error Message -->
    <?php if ($error_message): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><?php echo safe_html($error_message); ?></div>
    <?php endif; ?>

    <!-- Patient Info -->
    <?php if (!empty($patient_record)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Patient Information</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3"><strong>Blood Group:</strong><br><span class="badge bg-danger fs-6"><?php echo safe_html($patient_record['blood_group'] ?? 'Not specified'); ?></span></div>
                        <div class="col-md-3"><strong>Height:</strong><br><?php echo safe_html($patient_record['height'] ?? 'Not recorded'); ?> cm</div>
                        <div class="col-md-3"><strong>Weight:</strong><br><?php echo safe_html($patient_record['weight'] ?? 'Not recorded'); ?> kg</div>
                        <div class="col-md-3"><strong>Last Checkup:</strong><br><?php echo !empty($patient_record['last_checkup']) ? date('M d, Y', strtotime($patient_record['last_checkup'])) : 'No recent checkup'; ?></div>
                    </div>

                    <?php if (!empty($patient_record['allergies'])): ?>
                        <div class="alert alert-warning mt-3"><strong><i class="fas fa-exclamation-triangle me-2"></i>Allergies:</strong> <?php echo nl2br(safe_html($patient_record['allergies'])); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($patient_record['chronic_conditions'])): ?>
                        <div class="alert alert-info mt-2"><strong><i class="fas fa-heartbeat me-2"></i>Chronic Conditions:</strong> <?php echo nl2br(safe_html($patient_record['chronic_conditions'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fas fa-thermometer-half fa-2x mb-2"></i>
                <h3><?php echo $total_symptoms; ?></h3>
                <p>Total Symptoms</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <h3><?php echo $status_counts['active']; ?></h3>
                <p>Active Symptoms</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h3><?php echo $status_counts['resolved']; ?></h3>
                <p>Resolved</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fas fa-user-md fa-2x mb-2"></i>
                <h3><?php echo count($consultations); ?></h3>
                <p>Consultations</p>
            </div>
        </div>
    </div>

    <!-- Symptom Analytics & Visualizations -->
    <?php if (!empty($symptoms)): ?>
    <div class="row mb-4">
        <!-- Symptom Timeline -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Symptom Timeline (Last 10 Entries)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="timelineChart"></canvas>
                    </div>
                    <div class="insight-box">
                        <h6><i class="fas fa-lightbulb me-2"></i>Insight</h6>
                        <p class="mb-0 small">This chart shows when you reported symptoms. Peaks indicate days with multiple symptom reports. Track patterns to identify triggers or trends in your health.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Overview -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Symptom Status</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="insight-box">
                        <h6><i class="fas fa-info-circle me-2"></i>Summary</h6>
                        <p class="mb-1 small"><strong>Active:</strong> <?php echo $status_counts['active']; ?> symptoms need attention</p>
                        <p class="mb-1 small"><strong>Under Treatment:</strong> <?php echo $status_counts['under_treatment']; ?> being managed</p>
                        <p class="mb-0 small"><strong>Resolved:</strong> <?php echo $status_counts['resolved']; ?> symptoms cleared</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Severity Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Severity Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="severityChart"></canvas>
                    </div>
                    <div class="insight-box">
                        <h6><i class="fas fa-percentage me-2"></i>Breakdown</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="badge bg-success w-100 mb-1">Mild</div>
                                <strong><?php echo $severity_percentages['mild']; ?>%</strong>
                                <p class="small mb-0">(<?php echo $severity_counts['mild']; ?>)</p>
                            </div>
                            <div class="col-4">
                                <div class="badge bg-warning w-100 mb-1">Moderate</div>
                                <strong><?php echo $severity_percentages['moderate']; ?>%</strong>
                                <p class="small mb-0">(<?php echo $severity_counts['moderate']; ?>)</p>
                            </div>
                            <div class="col-4">
                                <div class="badge bg-danger w-100 mb-1">Severe</div>
                                <strong><?php echo $severity_percentages['severe']; ?>%</strong>
                                <p class="small mb-0">(<?php echo $severity_counts['severe']; ?>)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Level Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Risk Assessment</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="riskChart"></canvas>
                    </div>
                    <div class="insight-box">
                        <h6><i class="fas fa-shield-alt me-2"></i>Risk Distribution</h6>
                        <div class="row small">
                            <div class="col-6 mb-2">
                                <span class="badge bg-success me-1">Low</span> <?php echo $risk_counts['low']; ?> (<?php echo $risk_percentages['low']; ?>%)
                            </div>
                            <div class="col-6 mb-2">
                                <span class="badge bg-warning me-1">Medium</span> <?php echo $risk_counts['medium']; ?> (<?php echo $risk_percentages['medium']; ?>%)
                            </div>
                            <div class="col-6">
                                <span class="badge bg-danger me-1">High</span> <?php echo $risk_counts['high']; ?> (<?php echo $risk_percentages['high']; ?>%)
                            </div>
                            <div class="col-6">
                                <span class="badge bg-dark me-1">Critical</span> <?php echo $risk_counts['critical']; ?> (<?php echo $risk_percentages['critical']; ?>%)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-chart-bar text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">No Data to Visualize</h4>
                    <p class="text-muted">Start reporting your symptoms to see detailed analytics and trends here.</p>
                    <a href="add_symptom.php" class="btn btn-primary mt-2"><i class="fas fa-plus me-2"></i>Report Your First Symptom</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs" id="recordTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="symptoms-tab" data-bs-toggle="tab" data-bs-target="#symptoms" type="button"><i class="fas fa-thermometer-half me-2"></i>Symptoms (<?php echo count($symptoms); ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="consultations-tab" data-bs-toggle="tab" data-bs-target="#consultations" type="button"><i class="fas fa-user-md me-2"></i>Consultations (<?php echo count($consultations); ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="medications-tab" data-bs-toggle="tab" data-bs-target="#medications" type="button"><i class="fas fa-pills me-2"></i>Medications (<?php echo count($medications); ?>)</button></li>
            </ul>

            <div class="tab-content mt-3" id="recordTabContent">
                <!-- Symptoms Tab -->
                <div class="tab-pane fade show active" id="symptoms" role="tabpanel">
                    <?php if (empty($symptoms)): ?>
                        <div class="card text-center py-5"><i class="fas fa-thermometer-half text-muted" style="font-size: 4rem;"></i><h4 class="mt-3">No Symptoms Recorded</h4><a href="add_symptom.php" class="btn btn-primary mt-2">Report Symptoms</a></div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach (array_slice($symptoms, 0, 10) as $symptom): ?>
                                <div class="col-lg-6">
                                    <div class="card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <h6><i class="fas fa-notes-medical me-2 text-primary"></i>Symptom Report</h6>
                                                <div class="d-flex gap-2">
                                                    <span class="badge <?php echo formatSeverity($symptom['severity'] ?? ''); ?>"><?php echo ucfirst($symptom['severity'] ?? 'Unknown'); ?></span>
                                                    <span class="badge <?php echo formatRiskLevel($symptom['risk_level'] ?? ''); ?>"><?php echo ucfirst($symptom['risk_level'] ?? 'Unknown'); ?> Risk</span>
                                                </div>
                                            </div>
                                            <p class="text-muted small mb-2"><?php echo nl2br(safe_html($symptom['symptoms'] ?? '')); ?></p>
                                            <?php if (!empty($symptom['duration'])): ?><p class="mb-2"><small><strong>Duration:</strong> <?php echo safe_html($symptom['duration']); ?></small></p><?php endif; ?>
                                            <div class="row g-2 mb-2">
                                                <?php if (!empty($symptom['body_temperature'])): ?><div class="col-md-4"><small class="text-muted"><i class="fas fa-temperature-high me-1"></i><?php echo safe_html($symptom['body_temperature']); ?>°C</small></div><?php endif; ?>
                                                <?php if (!empty($symptom['blood_pressure'])): ?><div class="col-md-4"><small class="text-muted"><i class="fas fa-heart me-1"></i><?php echo safe_html($symptom['blood_pressure']); ?></small></div><?php endif; ?>
                                                <?php if (!empty($symptom['pulse_rate'])): ?><div class="col-md-4"><small class="text-muted"><i class="fas fa-heartbeat me-1"></i><?php echo safe_html($symptom['pulse_rate']); ?> BPM</small></div><?php endif; ?>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo !empty($symptom['reported_at']) ? date('M d, Y g:i A', strtotime($symptom['reported_at'])) : ''; ?></small>
                                                <span class="badge <?php echo formatStatus($symptom['status'] ?? ''); ?>"><?php echo ucfirst(str_replace('_',' ',$symptom['status'] ?? 'Unknown')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Consultations Tab -->
                <div class="tab-pane fade" id="consultations" role="tabpanel">
                    <?php if (empty($consultations)): ?>
                        <div class="card text-center py-5"><i class="fas fa-user-md text-muted" style="font-size:4rem;"></i><h4 class="mt-3">No Consultations Yet</h4><a href="appointments.php" class="btn btn-primary mt-2">Book Consultation</a></div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($consultations as $consultation): ?>
                                <div class="col-lg-6">
                                    <div class="card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <div>
                                                    <h6><i class="fas fa-stethoscope me-2 text-success"></i><?php echo ucfirst($consultation['consultation_type'] ?? 'General'); ?> Consultation</h6>
                                                    <p class="text-muted mb-1 small"><i class="fas fa-user-md me-1"></i>Dr. <?php echo safe_html($consultation['doctor_name'] ?? 'Unknown'); ?></p>
                                                    <?php if (!empty($consultation['specialization'])): ?><p class="text-muted small mb-0"><i class="fas fa-briefcase-medical me-1"></i><?php echo safe_html($consultation['specialization']); ?></p><?php endif; ?>
                                                </div>
                                                <span class="badge <?php echo formatStatus($consultation['status'] ?? ''); ?>"><?php echo ucfirst($consultation['status'] ?? 'Unknown'); ?></span>
                                            </div>
                                            <?php if (!empty($consultation['diagnosis'])): ?><p class="small mb-2"><strong>Diagnosis:</strong> <?php echo nl2br(safe_html($consultation['diagnosis'])); ?></p><?php endif; ?>
                                            <?php if (!empty($consultation['prescription'])): ?><p class="small mb-2"><strong>Prescription:</strong> <?php echo nl2br(safe_html($consultation['prescription'])); ?></p><?php endif; ?>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted"><i class="fas fa-calendar me-1"></i><?php echo !empty($consultation['consultation_date']) ? date('M d, Y g:i A', strtotime($consultation['consultation_date'])) : 'N/A'; ?></small>
                                                <?php if (!empty($consultation['fee_amount'])): ?><small class="text-success"><i class="fas fa-dollar-sign me-1"></i><?php echo number_format($consultation['fee_amount'],2); ?></small><?php endif; ?>
                                            </div>
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
                        <div class="card text-center py-5"><i class="fas fa-pills text-muted" style="font-size:4rem;"></i><h4 class="mt-3">No Medications Recorded</h4></div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($medications as $medication): ?>
                                <div class="col-lg-6">
                                    <div class="card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <h6><i class="fas fa-capsules me-2 text-warning"></i><?php echo safe_html($medication['medication_name'] ?? 'Unnamed Medication'); ?></h6>
                                                <span class="badge <?php echo ($medication['is_active'] ?? false) ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ($medication['is_active'] ?? false) ? 'Active' : 'Inactive'; ?></span>
                                            </div>
                                            <p class="small mb-2"><strong><i class="fas fa-prescription-bottle me-1"></i>Dosage:</strong> <?php echo safe_html($medication['dosage'] ?? 'Not specified'); ?></p>
                                            <p class="small mb-2"><strong><i class="fas fa-clock me-1"></i>Frequency:</strong> <?php echo safe_html($medication['frequency'] ?? 'Not specified'); ?></p>
                                            <?php if (!empty($medication['duration'])): ?><p class="small mb-2"><strong><i class="fas fa-calendar-alt me-1"></i>Duration:</strong> <?php echo safe_html($medication['duration']); ?></p><?php endif; ?>
                                            <?php if (!empty($medication['notes'])): ?><p class="small mb-0"><strong><i class="fas fa-sticky-note me-1"></i>Notes:</strong> <?php echo nl2br(safe_html($medication['notes'])); ?></p><?php endif; ?>
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

<!-- Charts Script -->
<?php if (!empty($symptoms)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
Chart.defaults.font.size = 13;

new Chart(document.getElementById('timelineChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_keys($symptoms_timeline)); ?>,
        datasets: [{
            label: 'Symptoms Reported',
            data: <?php echo json_encode(array_values($symptoms_timeline)); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            borderRadius: 8,
            hoverBackgroundColor: 'rgba(102, 126, 234, 1)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top', labels: { padding: 15, font: { size: 13, weight: 'bold' } } },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14 },
                bodyFont: { size: 13 },
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + ' symptom' + (context.parsed.y !== 1 ? 's' : '') + ' reported';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, precision: 0, font: { size: 12 } },
                title: { display: true, text: 'Number of Symptoms', font: { size: 13, weight: 'bold' } },
                grid: { color: 'rgba(0, 0, 0, 0.05)' }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 12 } },
                title: { display: true, text: 'Date', font: { size: 13, weight: 'bold' } }
            }
        }
    }
});

new Chart(document.getElementById('severityChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Mild', 'Moderate', 'Severe'],
        datasets: [{
            label: 'Severity Count',
            data: [<?php echo $severity_counts['mild']; ?>, <?php echo $severity_counts['moderate']; ?>, <?php echo $severity_counts['severe']; ?>],
            backgroundColor: ['rgba(75, 192, 192, 0.85)', 'rgba(255, 206, 86, 0.85)', 'rgba(255, 99, 132, 0.85)'],
            borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)', 'rgba(255, 99, 132, 1)'],
            borderWidth: 3,
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    font: { size: 13, weight: 'bold' },
                    generateLabels: function(chart) {
                        const data = chart.data;
                        return data.labels.map((label, i) => {
                            const value = data.datasets[0].data[i];
                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return {
                                text: label + ': ' + value + ' (' + percentage + '%)',
                                fillStyle: data.datasets[0].backgroundColor[i],
                                hidden: false,
                                index: i
                            };
                        });
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14 },
                bodyFont: { size: 13 },
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + value + ' symptoms (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('riskChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Low Risk', 'Medium Risk', 'High Risk', 'Critical Risk'],
        datasets: [{
            label: 'Number of Symptoms',
            data: [<?php echo $risk_counts['low']; ?>, <?php echo $risk_counts['medium']; ?>, <?php echo $risk_counts['high']; ?>, <?php echo $risk_counts['critical']; ?>],
            backgroundColor: ['rgba(75, 192, 192, 0.85)', 'rgba(255, 206, 86, 0.85)', 'rgba(255, 99, 132, 0.85)', 'rgba(54, 54, 54, 0.85)'],
            borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)', 'rgba(255, 99, 132, 1)', 'rgba(54, 54, 54, 1)'],
            borderWidth: 2,
            borderRadius: 8,
            hoverBackgroundColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)', 'rgba(255, 99, 132, 1)', 'rgba(54, 54, 54, 1)']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14 },
                bodyFont: { size: 13 },
                callbacks: {
                    label: function(context) {
                        const value = context.parsed.y;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return value + ' symptoms (' + percentage + '%)';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, precision: 0, font: { size: 12 } },
                title: { display: true, text: 'Number of Symptoms', font: { size: 13, weight: 'bold' } },
                grid: { color: 'rgba(0, 0, 0, 0.05)' }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            }
        }
    }
});

new Chart(document.getElementById('statusChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: ['Active', 'Resolved', 'Under Treatment'],
        datasets: [{
            label: 'Status Count',
            data: [<?php echo $status_counts['active']; ?>, <?php echo $status_counts['resolved']; ?>, <?php echo $status_counts['under_treatment']; ?>],
            backgroundColor: ['rgba(255, 206, 86, 0.85)', 'rgba(75, 192, 192, 0.85)', 'rgba(54, 162, 235, 0.85)'],
            borderColor: ['rgba(255, 206, 86, 1)', 'rgba(75, 192, 192, 1)', 'rgba(54, 162, 235, 1)'],
            borderWidth: 3,
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    font: { size: 13, weight: 'bold' },
                    generateLabels: function(chart) {
                        const data = chart.data;
                        return data.labels.map((label, i) => {
                            const value = data.datasets[0].data[i];
                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return {
                                text: label + ': ' + value + ' (' + percentage + '%)',
                                fillStyle: data.datasets[0].backgroundColor[i],
                                hidden: false,
                                index: i
                            };
                        });
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 14 },
                bodyFont: { size: 13 },
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return label + ': ' + value + ' symptoms (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>