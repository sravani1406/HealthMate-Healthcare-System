<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

$doctor_user_id = $_SESSION['user_id'];
$consultation_id = $_GET['consultation_id'] ?? null;
$success = '';
$error = '';

// Get doctor's ID from doctors table
$stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$doctor_user_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    $error = 'Doctor profile not found.';
    $doctor_id = null;
} else {
    $doctor_id = $doctor['id'];
}

// Get consultation information if ID is provided
$consultation = null;
$patient = null;
$patient_record = null;
$symptom = null;

if ($consultation_id && $doctor_id) {
    // Get consultation details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.full_name as patient_name, 
               u.email as patient_email, 
               u.phone as patient_phone,
               u.date_of_birth,
               u.gender,
               s.symptoms,
               s.severity,
               s.duration,
               s.body_temperature,
               s.blood_pressure,
               s.pulse_rate
        FROM consultations c
        JOIN users u ON c.patient_id = u.id
        LEFT JOIN symptoms s ON c.symptom_id = s.id
        WHERE c.id = ? AND c.doctor_id = ?
    ");
    $stmt->execute([$consultation_id, $doctor_user_id]);
    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$consultation) {
        $error = 'Consultation not found or not assigned to you.';
        $consultation_id = null;
    } else {
        // Get patient medical records
        $stmt = $pdo->prepare("SELECT * FROM patient_records WHERE patient_id = ?");
        $stmt->execute([$consultation['patient_id']]);
        $patient_record = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle consultation update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_consultation']) && $consultation_id) {
    try {
        $diagnosis = trim($_POST['diagnosis']);
        $prescription = trim($_POST['prescription']);
        $notes = trim($_POST['notes']);
        $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
        $status = $_POST['status'];
        
        if (empty($diagnosis)) {
            $error = 'Diagnosis is required';
        } else {
            $stmt = $pdo->prepare("
                UPDATE consultations 
                SET diagnosis = ?, 
                    prescription = ?, 
                    notes = ?, 
                    follow_up_date = ?,
                    status = ?
                WHERE id = ? AND doctor_id = ?
            ");
            
            if ($stmt->execute([$diagnosis, $prescription, $notes, $follow_up_date, $status, $consultation_id, $doctor_user_id])) {
                $success = 'Consultation record updated successfully!';
                
                // Refresh consultation data
                $stmt = $pdo->prepare("
                    SELECT c.*, 
                           u.full_name as patient_name, 
                           u.email as patient_email, 
                           u.phone as patient_phone,
                           u.date_of_birth,
                           u.gender,
                           s.symptoms,
                           s.severity,
                           s.duration,
                           s.body_temperature,
                           s.blood_pressure,
                           s.pulse_rate
                    FROM consultations c
                    JOIN users u ON c.patient_id = u.id
                    LEFT JOIN symptoms s ON c.symptom_id = s.id
                    WHERE c.id = ? AND c.doctor_id = ?
                ");
                $stmt->execute([$consultation_id, $doctor_user_id]);
                $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update consultation record';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error updating consultation: ' . $e->getMessage();
    }
}

require_once '../includes/notification_functions.php';
// Get all consultations for this doctor
$all_consultations = [];
if ($doctor_id) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.consultation_date, c.consultation_type, c.status,
               u.full_name as patient_name,
               s.symptoms,
               s.severity
        FROM consultations c
        JOIN users u ON c.patient_id = u.id
        LEFT JOIN symptoms s ON c.symptom_id = s.id
        WHERE c.doctor_id = ?
        ORDER BY 
            CASE c.status
                WHEN 'scheduled' THEN 1
                WHEN 'completed' THEN 2
                WHEN 'cancelled' THEN 3
                WHEN 'no_show' THEN 4
            END,
            c.consultation_date DESC
    ");
    $stmt->execute([$doctor_user_id]);
    $all_consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - HealthHive</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #28a745;
            font-size: 28px;
        }
        
        .back-btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #218838;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
        }
        
        .consultations-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }
        
        .consultations-list h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .consultation-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        
        .consultation-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .consultation-card.active {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .consultation-card h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .consultation-card p {
            color: #6c757d;
            font-size: 13px;
            margin: 4px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .status-scheduled { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-no_show { background: #e2e3e5; color: #383d41; }
        
        .severity-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .severity-mild { background: #d1ecf1; color: #0c5460; }
        .severity-moderate { background: #fff3cd; color: #856404; }
        .severity-severe { background: #f8d7da; color: #721c24; }
        
        .details-panel {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-selection {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-selection svg {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .patient-header {
            background: #28a745;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .patient-header h2 {
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            background: rgba(255,255,255,0.1);
            padding: 12px;
            border-radius: 8px;
        }
        
        .info-item label {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .info-item span {
            font-size: 16px;
            font-weight: 600;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 8px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .info-card h4 {
            color: #28a745;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-card p {
            color: #333;
            line-height: 1.6;
            margin: 8px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #218838;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .consultations-list {
                max-height: 400px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Patient Records Management</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="main-content">
            <!-- Consultations List -->
            <div class="consultations-list">
                <h2>Your Consultations</h2>
                
                <?php if (empty($all_consultations)): ?>
                    <div class="empty-state">
                        <p>No consultations found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_consultations as $cons): ?>
                        <a href="?consultation_id=<?php echo $cons['id']; ?>" 
                           class="consultation-card <?php echo ($consultation_id == $cons['id']) ? 'active' : ''; ?>">
                            <h3><?php echo htmlspecialchars($cons['patient_name']); ?></h3>
                            <p>📅 <?php echo date('M j, Y g:i A', strtotime($cons['consultation_date'])); ?></p>
                            <p>🏥 <?php echo ucfirst($cons['consultation_type']); ?></p>
                            <?php if ($cons['symptoms']): ?>
                                <p>💊 <?php echo substr(htmlspecialchars($cons['symptoms']), 0, 50); ?>...
                                    <?php if ($cons['severity']): ?>
                                        <span class="severity-badge severity-<?php echo $cons['severity']; ?>">
                                            <?php echo ucfirst($cons['severity']); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo $cons['status']; ?>">
                                <?php echo ucfirst($cons['status']); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Details Panel -->
            <div class="details-panel">
                <?php if (!$consultation_id): ?>
                    <div class="no-selection">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h2>Select a Consultation</h2>
                        <p>Choose a consultation from the list to view and update patient records.</p>
                    </div>
                <?php elseif ($consultation): ?>
                    <!-- Patient Header -->
                    <div class="patient-header">
                        <h2>👤 <?php echo htmlspecialchars($consultation['patient_name']); ?></h2>
                        <div class="patient-info-grid">
                            <div class="info-item">
                                <label>Email</label>
                                <span><?php echo htmlspecialchars($consultation['patient_email']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Phone</label>
                                <span><?php echo htmlspecialchars($consultation['patient_phone'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Gender</label>
                                <span><?php echo ucfirst($consultation['gender'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Age</label>
                                <span>
                                    <?php 
                                    if ($consultation['date_of_birth']) {
                                        $dob = new DateTime($consultation['date_of_birth']);
                                        $now = new DateTime();
                                        $age = $now->diff($dob)->y;
                                        echo $age . ' years';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Symptoms -->
                    <?php if ($consultation['symptoms']): ?>
                        <div class="section">
                            <h3>Current Symptoms</h3>
                            <div class="info-card">
                                <h4>Reported Symptoms</h4>
                                <p><?php echo nl2br(htmlspecialchars($consultation['symptoms'])); ?></p>
                                
                                <?php if ($consultation['severity']): ?>
                                    <p><strong>Severity:</strong> 
                                        <span class="severity-badge severity-<?php echo $consultation['severity']; ?>">
                                            <?php echo ucfirst($consultation['severity']); ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($consultation['duration']): ?>
                                    <p><strong>Duration:</strong> <?php echo htmlspecialchars($consultation['duration']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($consultation['body_temperature']): ?>
                                    <p><strong>Temperature:</strong> <?php echo htmlspecialchars($consultation['body_temperature']); ?>°C</p>
                                <?php endif; ?>
                                
                                <?php if ($consultation['blood_pressure']): ?>
                                    <p><strong>Blood Pressure:</strong> <?php echo htmlspecialchars($consultation['blood_pressure']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($consultation['pulse_rate']): ?>
                                    <p><strong>Pulse Rate:</strong> <?php echo htmlspecialchars($consultation['pulse_rate']); ?> bpm</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Patient Medical Records -->
                    <?php if ($patient_record): ?>
                        <div class="section">
                            <h3>Medical History</h3>
                            <div class="info-card">
                                <div class="form-row" style="margin-bottom: 15px;">
                                    <?php if ($patient_record['blood_group']): ?>
                                        <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($patient_record['blood_group']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($patient_record['height']): ?>
                                        <p><strong>Height:</strong> <?php echo htmlspecialchars($patient_record['height']); ?> cm</p>
                                    <?php endif; ?>
                                    <?php if ($patient_record['weight']): ?>
                                        <p><strong>Weight:</strong> <?php echo htmlspecialchars($patient_record['weight']); ?> kg</p>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($patient_record['allergies']): ?>
                                    <p><strong>Allergies:</strong> <?php echo nl2br(htmlspecialchars($patient_record['allergies'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($patient_record['chronic_conditions']): ?>
                                    <p><strong>Chronic Conditions:</strong> <?php echo nl2br(htmlspecialchars($patient_record['chronic_conditions'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($patient_record['current_medications']): ?>
                                    <p><strong>Current Medications:</strong> <?php echo nl2br(htmlspecialchars($patient_record['current_medications'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($patient_record['medical_history']): ?>
                                    <p><strong>Medical History:</strong> <?php echo nl2br(htmlspecialchars($patient_record['medical_history'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($patient_record['family_history']): ?>
                                    <p><strong>Family History:</strong> <?php echo nl2br(htmlspecialchars($patient_record['family_history'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Update Consultation Form -->
                    <div class="section">
                        <h3>Update Consultation Record</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Diagnosis *</label>
                                <textarea name="diagnosis" required><?php echo htmlspecialchars($consultation['diagnosis'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Prescription</label>
                                <textarea name="prescription"><?php echo htmlspecialchars($consultation['prescription'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Additional Notes</label>
                                <textarea name="notes"><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Follow-up Date</label>
                                    <input type="date" name="follow_up_date" value="<?php echo htmlspecialchars($consultation['follow_up_date'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Consultation Status *</label>
                                    <select name="status" required>
                                        <option value="scheduled" <?php echo ($consultation['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="completed" <?php echo ($consultation['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo ($consultation['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no_show" <?php echo ($consultation['status'] === 'no_show') ? 'selected' : ''; ?>>No Show</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_consultation" class="btn">Update Record</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>