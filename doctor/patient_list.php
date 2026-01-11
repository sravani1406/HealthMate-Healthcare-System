<?php
// doctor/patient_list.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Get patients who have appointments or consultations with this doctor
try {
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.full_name, u.phone, u.email, u.gender, u.date_of_birth,
               (SELECT risk_level FROM symptoms WHERE patient_id = u.id ORDER BY reported_at DESC LIMIT 1) as latest_risk,
               (SELECT COUNT(*) FROM consultations WHERE patient_id = u.id AND doctor_id = ?) as consultation_count,
               (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id AND doctor_id = ?) as appointment_count,
               (SELECT status FROM appointments WHERE patient_id = u.id AND doctor_id = ? ORDER BY appointment_date DESC, appointment_time DESC LIMIT 1) as latest_appointment_status,
               (SELECT CONCAT(appointment_date, ' ', appointment_time) FROM appointments WHERE patient_id = u.id AND doctor_id = ? ORDER BY appointment_date DESC, appointment_time DESC LIMIT 1) as latest_appointment_datetime
        FROM users u
        WHERE u.user_type = 'patient' 
        AND (
            EXISTS (SELECT 1 FROM appointments WHERE patient_id = u.id AND doctor_id = ?)
            OR EXISTS (SELECT 1 FROM consultations WHERE patient_id = u.id AND doctor_id = ?)
        )
        ORDER BY u.full_name
    ");
    $stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id, $doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - HealthHive</title>
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
        
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-filter input,
        .search-filter select {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .search-filter input:focus,
        .search-filter select:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .patient-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .patient-header h3 {
            color: #333;
            font-size: 20px;
            flex: 1;
        }
        
        .risk-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .risk-badge.low {
            background: #d4edda;
            color: #155724;
        }
        
        .risk-badge.medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .risk-badge.high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .risk-badge.critical {
            background: #dc3545;
            color: white;
        }
        
        .risk-badge.unknown {
            background: #e2e3e5;
            color: #6c757d;
        }
        
        .patient-info {
            margin-bottom: 20px;
        }
        
        .patient-info p {
            margin: 8px 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .patient-info strong {
            color: #333;
            display: inline-block;
            width: 120px;
        }
        
        .patient-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .no-patients {
            background: white;
            padding: 60px 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-patients p {
            color: #6c757d;
            font-size: 18px;
        }
        
        .stats-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item h4 {
            color: #28a745;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .stat-item p {
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .patients-grid {
                grid-template-columns: 1fr;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            .search-filter input,
            .search-filter select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Patients</h1>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
        
        <?php if (!empty($patients)): ?>
            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-item">
                    <h4><?php echo count($patients); ?></h4>
                    <p>Total Patients</p>
                </div>
                <div class="stat-item">
                    <h4><?php echo count(array_filter($patients, function($p) { return ($p['latest_risk'] ?? '') === 'high' || ($p['latest_risk'] ?? '') === 'critical'; })); ?></h4>
                    <p>High Risk Patients</p>
                </div>
                <div class="stat-item">
                    <h4><?php echo array_sum(array_column($patients, 'consultation_count')); ?></h4>
                    <p>Total Consultations</p>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="search-filter">
                <input type="text" id="searchPatient" placeholder="Search patients by name..." onkeyup="filterPatients()">
                <select id="riskFilter" onchange="filterPatients()">
                    <option value="">All Risk Levels</option>
                    <option value="low">Low Risk</option>
                    <option value="medium">Medium Risk</option>
                    <option value="high">High Risk</option>
                    <option value="critical">Critical</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>
            
            <!-- Patients Grid -->
            <div class="patients-grid" id="patientsGrid">
                <?php foreach ($patients as $patient): ?>
                    <?php
                    $age = 'N/A';
                    if ($patient['date_of_birth']) {
                        $dob = new DateTime($patient['date_of_birth']);
                        $now = new DateTime();
                        $age = $dob->diff($now)->y;
                    }
                    $risk = $patient['latest_risk'] ?? 'unknown';
                    ?>
                    <div class="patient-card" data-risk="<?php echo $risk; ?>" data-name="<?php echo strtolower($patient['full_name']); ?>">
                        <div class="patient-header">
                            <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                            <span class="risk-badge <?php echo $risk; ?>">
                                <?php echo ucfirst($risk); ?>
                            </span>
                        </div>
                        
                        <div class="patient-info">
                            <p><strong>Age:</strong> <?php echo $age; ?></p>
                            <p><strong>Gender:</strong> <?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                            <p><strong>Consultations:</strong> <?php echo $patient['consultation_count']; ?></p>
                            <?php if (isset($patient['latest_consultation_status'])): ?>
                                <p><strong>Status:</strong> <span style="color: #28a745; font-weight: 500;"><?php echo ucfirst($patient['latest_consultation_status']); ?></span></p>
                            <?php endif; ?>
                            <?php if (isset($patient['latest_consultation_date'])): ?>
                                <p><strong>Last Visit:</strong> <?php echo date('M j, Y', strtotime($patient['latest_consultation_date'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="patient-actions">
                            <a href="consultation.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">Consult</a>
                            <a href="consultation.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary">Records</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-patients">
                <p>No patients assigned to you yet.</p>
                <p style="margin-top: 10px; font-size: 14px;">Patients will appear here after appointments are scheduled.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filterPatients() {
            const searchTerm = document.getElementById('searchPatient').value.toLowerCase();
            const riskFilter = document.getElementById('riskFilter').value;
            const patientCards = document.querySelectorAll('.patient-card');
            
            let visibleCount = 0;
            
            patientCards.forEach(card => {
                const name = card.getAttribute('data-name');
                const risk = card.getAttribute('data-risk');
                
                const matchesSearch = name.includes(searchTerm);
                const matchesRisk = !riskFilter || risk === riskFilter;
                
                if (matchesSearch && matchesRisk) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            console.log('Filtered patients:', visibleCount);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Patient list loaded with', document.querySelectorAll('.patient-card').length, 'patients');
        });
    </script>
</body>
</html>