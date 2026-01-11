<?php
// doctor/consultation.php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// Database reference
if (isset($pdo)) {
    $db = $pdo;
}

// Site URL
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

$doctor_id = $_SESSION['user_id'];
$patient_id = $_GET['patient_id'] ?? null;
$success = '';
$error = '';

// Get patient information
$patient = null;
if ($patient_id) {
    try {
        $stmt = $db->prepare("
            SELECT u.*, pr.blood_group, pr.allergies, pr.chronic_conditions, pr.medical_history
            FROM users u
            LEFT JOIN patient_records pr ON u.id = pr.patient_id
            WHERE u.id = ? AND u.user_type = 'patient'
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            $error = 'Patient not found.';
            $patient_id = null;
        }
    } catch (PDOException $e) {
        error_log("Error fetching patient: " . $e->getMessage());
        $error = 'Error loading patient information.';
    }
}

// Handle PDF download
if (isset($_GET['download_pdf']) && $patient_id) {
    try {
        $stmtSymptoms = $db->prepare("SELECT * FROM symptoms WHERE patient_id = ? ORDER BY reported_at DESC");
        $stmtSymptoms->execute([$patient_id]);
        $symptoms = $stmtSymptoms->fetchAll(PDO::FETCH_ASSOC);

        $stmtConsultations = $db->prepare("
            SELECT c.*, u.full_name as doctor_name
            FROM consultations c
            LEFT JOIN users u ON c.doctor_id = u.id
            WHERE c.patient_id = ?
            ORDER BY c.consultation_date DESC
        ");
        $stmtConsultations->execute([$patient_id]);
        $consultations = $stmtConsultations->fetchAll(PDO::FETCH_ASSOC);

        $html = '<h1>Patient Medical History</h1>';
        $html .= '<p><strong>Name:</strong> ' . htmlspecialchars($patient['full_name']) . '</p>';
        $html .= '<p><strong>Email:</strong> ' . htmlspecialchars($patient['email']) . '</p>';
        $html .= '<p><strong>Phone:</strong> ' . htmlspecialchars($patient['phone'] ?? 'N/A') . '</p>';
        $html .= '<p><strong>Blood Group:</strong> ' . htmlspecialchars($patient['blood_group'] ?? 'N/A') . '</p>';
        $html .= '<p><strong>Allergies:</strong> ' . htmlspecialchars($patient['allergies'] ?? 'None') . '</p>';
        $html .= '<p><strong>Chronic Conditions:</strong> ' . htmlspecialchars($patient['chronic_conditions'] ?? 'None') . '</p>';
        
        $html .= '<h2>Symptoms</h2>';
        if (empty($symptoms)) {
            $html .= '<p>No symptoms recorded.</p>';
        } else {
            foreach ($symptoms as $s) {
                $html .= '<p><strong>Date:</strong> ' . date('M j, Y', strtotime($s['reported_at'])) .
                         ' | <strong>Symptoms:</strong> ' . htmlspecialchars($s['symptoms']) .
                         ' | <strong>Severity:</strong> ' . htmlspecialchars($s['severity']) .
                         ' | <strong>Risk:</strong> ' . htmlspecialchars($s['risk_level']) . '</p>';
            }
        }

        $html .= '<h2>Consultations</h2>';
        if (empty($consultations)) {
            $html .= '<p>No consultations recorded.</p>';
        } else {
            foreach ($consultations as $c) {
                $html .= '<p><strong>Date:</strong> ' . date('M j, Y', strtotime($c['consultation_date'])) .
                         ' | <strong>Doctor:</strong> Dr. ' . htmlspecialchars($c['doctor_name']) .
                         ' | <strong>Diagnosis:</strong> ' . htmlspecialchars($c['diagnosis'] ?? 'N/A') .
                         ' | <strong>Prescription:</strong> ' . htmlspecialchars($c['prescription'] ?? 'N/A') .
                         ' | <strong>Notes:</strong> ' . htmlspecialchars($c['notes'] ?? 'N/A') . '</p>';
            }
        }

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('patient_medical_history_' . $patient['id'] . '.pdf', ['Attachment' => true]);
        exit();
    } catch (PDOException $e) {
        error_log("PDF generation error: " . $e->getMessage());
        $error = 'Failed to generate PDF.';
    }
}

// Handle consultation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient_id) {
    $diagnosis = sanitize_input($_POST['diagnosis'] ?? '');
    $prescription = sanitize_input($_POST['prescription'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $follow_up_date = $_POST['follow_up_date'] ?? null;
    $consultation_type = sanitize_input($_POST['consultation_type'] ?? 'in_person');

    if (empty($diagnosis)) {
        $error = 'Diagnosis is required';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO consultations 
                (patient_id, doctor_id, consultation_type, consultation_date, diagnosis, prescription, notes, follow_up_date, status, created_at) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, 'completed', NOW())
            ");
            if ($stmt->execute([$patient_id, $doctor_id, $consultation_type, $diagnosis, $prescription, $notes, $follow_up_date])) {
                send_notification(
                    $patient_id,
                    'New Consultation Completed',
                    'Your consultation has been completed. Please check your medical records for details.',
                    'appointment',
                    'high'
                );
                log_activity($doctor_id, 'consultation_completed', "Consultation with patient ID: $patient_id");
                $success = 'Consultation recorded successfully!';
            } else {
                $error = 'Failed to record consultation';
            }
        } catch (PDOException $e) {
            error_log("Error recording consultation: " . $e->getMessage());
            $error = 'Failed to record consultation. Please try again.';
        }
    }
}

// Get patient's recent symptoms
$recent_symptoms = [];
if ($patient_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM symptoms WHERE patient_id = ? ORDER BY reported_at DESC LIMIT 3");
        $stmt->execute([$patient_id]);
        $recent_symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching symptoms: " . $e->getMessage());
    }
}

// Get patient's consultation history (all)
$consultation_history = [];
if ($patient_id) {
    try {
        $stmt = $db->prepare("
            SELECT c.*, u.full_name as doctor_name
            FROM consultations c
            LEFT JOIN users u ON c.doctor_id = u.id
            WHERE c.patient_id = ?
            ORDER BY c.consultation_date DESC
        ");
        $stmt->execute([$patient_id]);
        $consultation_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching consultation history: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Consultation - HealthHive</title>
    <style>
        /* === KEEP ALL YOUR EXISTING CSS === */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { color: #28a745; font-size: 28px; }
        .back-btn { background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .back-btn:hover { background: #218838; }
        .alert { padding: 15px 20px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .patient-info-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .patient-info-card h2 { color: #333; margin-bottom: 15px; }
        .patient-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .patient-details p { color: #6c757d; margin: 5px 0; }
        .patient-details strong { color: #333; }
        .recent-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .info-section h3 { color: #333; margin-bottom: 15px; font-size: 18px; }
        .symptom-item, .history-item { padding: 12px; background: #f8f9fa; border-radius: 5px; margin-bottom: 10px; border-left: 3px solid #28a745; }
        .symptom-item.high { border-left-color: #ffc107; }
        .symptom-item.critical { border-left-color: #dc3545; }
        .symptom-item p, .history-item p { color: #6c757d; margin: 3px 0; font-size: 14px; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
        .badge-high { background: #fff3cd; color: #856404; }
        .badge-critical { background: #f8d7da; color: #721c24; }
        .badge-low { background: #d4edda; color: #155724; }
        .consultation-form-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .consultation-form-card h3 { color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 15px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #28a745; }
        .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #218838; }
        @media (max-width: 768px) { .recent-info { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Patient Consultation</h1>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

<?php if (!$patient_id): ?>
    <div class="patient-info-card">
        <h2>Select a Patient</h2>
        <p style="color: #6c757d; margin-bottom: 20px;">Choose a patient from your consultations to start a new consultation</p>
        <?php
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT u.id, u.full_name, u.phone, u.email, u.gender, u.date_of_birth
                FROM users u
                INNER JOIN consultations c ON u.id = c.patient_id
                WHERE c.doctor_id = ? AND u.user_type = 'patient'
                ORDER BY u.full_name
            ");
            $stmt->execute([$doctor_id]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($patients)) {
                echo '<p style="color: #6c757d;">No patients found. Patients will appear here after appointments are scheduled.</p>';
            } else {
                echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">';
                foreach ($patients as $p) {
                    $age = $p['date_of_birth'] ? date_diff(date_create($p['date_of_birth']), date_create('today'))->y : 'N/A';
                    echo '<a href="?patient_id=' . $p['id'] . '" style="text-decoration: none;">
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; transition: all 0.3s;">
                            <h3 style="color: #333; margin-bottom: 8px;">' . htmlspecialchars($p['full_name']) . '</h3>
                            <p style="color: #6c757d; margin: 3px 0; font-size: 14px;">Age: ' . $age . ' | ' . ucfirst($p['gender'] ?? 'N/A') . '</p>
                            <p style="color: #6c757d; margin: 3px 0; font-size: 14px;">Phone: ' . htmlspecialchars($p['phone']) . '</p>
                        </div>
                    </a>';
                }
                echo '</div>';
            }
        } catch (PDOException $e) {
            echo '<p style="color: #dc3545;">Error loading patients.</p>';
        }
        ?>
    </div>
<?php else: ?>
    <!-- Patient Info -->
    <div class="patient-info-card">
        <h2>Patient: <?php echo htmlspecialchars($patient['full_name']); ?></h2>
        <div class="patient-details">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></p>
            <p><strong>Gender:</strong> <?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></p>
            <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></p>
            <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies'] ?? 'None'); ?></p>
            <p><strong>Chronic Conditions:</strong> <?php echo htmlspecialchars($patient['chronic_conditions'] ?? 'None'); ?></p>
        </div>
        <a href="?patient_id=<?php echo $patient_id; ?>&download_pdf=1" class="btn" style="margin-top:15px;">Download PDF</a>
    </div>

    <!-- Recent Info -->
    <div class="recent-info">
        <div class="info-section">
            <h3>Recent Symptoms</h3>
            <?php if (empty($recent_symptoms)): ?>
                <p style="color: #6c757d;">No recent symptoms recorded</p>
            <?php else: ?>
                <?php foreach ($recent_symptoms as $symptom): ?>
                    <div class="symptom-item <?php echo $symptom['risk_level']; ?>">
                        <p><strong>Risk:</strong> <span class="badge badge-<?php echo $symptom['risk_level']; ?>"><?php echo ucfirst($symptom['risk_level']); ?></span> | 
                        <strong>Severity:</strong> <?php echo ucfirst($symptom['severity']); ?></p>
                        <p><strong>Symptoms:</strong> <?php echo htmlspecialchars($symptom['symptoms']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($symptom['reported_at'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <h3>Consultation History</h3>
            <?php if (empty($consultation_history)): ?>
                <p style="color: #6c757d;">No consultation history</p>
            <?php else: ?>
                <?php foreach ($consultation_history as $history): ?>
                    <div class="history-item">
                        <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($history['doctor_name']); ?></p>
                        <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($history['diagnosis'] ?? 'No diagnosis'); ?></p>
                        <p><strong>Prescription:</strong> <?php echo htmlspecialchars($history['prescription'] ?? 'No prescription'); ?></p>
                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($history['notes'] ?? 'No notes'); ?></p>
                        <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($history['consultation_date'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Consultation Form -->
    <div class="consultation-form-card">
        <h3>New Consultation</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="consultation_type">Consultation Type *</label>
                <select id="consultation_type" name="consultation_type" required>
                    <option value="in_person">In-Person</option>
                    <option value="online">Online</option>
                    <option value="emergency">Emergency</option>
                </select>
            </div>
            <div class="form-group">
                <label for="diagnosis">Diagnosis *</label>
                <textarea id="diagnosis" name="diagnosis" required placeholder="Enter diagnosis..."></textarea>
            </div>
            <div class="form-group">
                <label for="prescription">Prescription</label>
                <textarea id="prescription" name="prescription" placeholder="Enter prescription details..."></textarea>
            </div>
            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" placeholder="Any additional notes or observations..."></textarea>
            </div>
            <div class="form-group">
                <label for="follow_up_date">Follow-up Date</label>
                <input type="date" id="follow_up_date" name="follow_up_date" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <button type="submit" class="btn">Record Consultation</button>
        </form>
    </div>
<?php endif; ?>
</div>
</body>
</html>
