<?php
// doctor/dashboard.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';


// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

require_once '../includes/notification_functions.php';

$doctor_user_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_email = $_SESSION['email'] ?? '';

// Get doctor's ID from doctors table
$stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$doctor_user_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
$doctor_id = $doctor ? $doctor['id'] : null;

// Initialize statistics
$total_patients = 0;
$high_risk_patients = 0;
$pending_consultations = 0;
$completed_consultations = 0;
$recent_alerts = [];

try {
    // Get total consultations (as proxy for patients)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT patient_id) as total 
        FROM consultations 
        WHERE doctor_id = ?
    ");
    $stmt->execute([$doctor_user_id]);
    $total_patients = $stmt->fetchColumn();

    // Get high-risk patients from symptoms table
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT s.patient_id) as high_risk
        FROM symptoms s
        INNER JOIN consultations c ON s.patient_id = c.patient_id
        WHERE c.doctor_id = ? 
        AND s.risk_level IN ('high', 'critical')
        AND DATE(s.reported_at) = CURDATE()
    ");
    $stmt->execute([$doctor_user_id]);
    $high_risk_patients = $stmt->fetchColumn();

    // Get pending consultations
    $stmt = $db->prepare("
        SELECT COUNT(*) as pending
        FROM consultations 
        WHERE doctor_id = ? AND status = 'scheduled'
    ");
    $stmt->execute([$doctor_user_id]);
    $pending_consultations = $stmt->fetchColumn();

    // Get completed consultations
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed
        FROM consultations 
        WHERE doctor_id = ? AND status = 'completed'
    ");
    $stmt->execute([$doctor_user_id]);
    $completed_consultations = $stmt->fetchColumn();

    // Get recent high-risk alerts
    $stmt = $db->prepare("
        SELECT 
            u.full_name as patient_name, 
            u.phone, 
            s.symptoms,
            s.severity,
            s.risk_level, 
            s.reported_at
        FROM symptoms s
        INNER JOIN users u ON s.patient_id = u.id
        INNER JOIN consultations c ON s.patient_id = c.patient_id
        WHERE c.doctor_id = ? 
        AND s.risk_level IN ('high', 'critical')
        ORDER BY s.reported_at DESC
        LIMIT 5
    ");
    $stmt->execute([$doctor_user_id]);
    $recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Doctor dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - HealthHive</title>
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
            margin-bottom: 5px;
        }
        
        .header p {
            color: #6c757d;
            margin-top: 5px;
        }
        
        .user-menu {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
            margin-right: 10px;
        }
        
        .user-info .user-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .user-info .user-role {
            font-size: 12px;
            color: #6c757d;
        }
        
        .profile-dropdown {
            position: relative;
        }
        
        .profile-btn {
            background: #28a745;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            border: 3px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .profile-btn:hover {
            background: #218838;
            transform: scale(1.05);
            border-color: #28a745;
        }
        
        .dropdown-menu-custom {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 220px;
            padding: 10px 0;
            display: none;
            z-index: 1000;
        }
        
        .dropdown-menu-custom.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-menu-custom a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
            font-size: 14px;
            gap: 12px;
        }
        
        .dropdown-menu-custom a:hover {
            background: #f8f9fa;
        }
        
        .dropdown-menu-custom a i {
            width: 20px;
            color: #28a745;
        }
        
        .dropdown-menu-custom .dropdown-divider {
            height: 1px;
            background: #e9ecef;
            margin: 8px 0;
        }
        
        .dropdown-menu-custom .logout-link {
            color: #dc3545;
        }
        
        .dropdown-menu-custom .logout-link i {
            color: #dc3545;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .stat-number {
            font-size: 36px;
            color: #28a745;
            font-weight: bold;
        }
        
        .stat-card.alert .stat-number {
            color: #dc3545;
        }
        
        .stat-card.success .stat-number {
            color: #28a745;
        }
        
        .stat-card.warning .stat-number {
            color: #ffc107;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn {
            background: #28a745;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            background: #218838;
            transform: translateX(5px);
        }
        
        .btn-primary {
            background: #28a745;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-info {
            background: #17a2b8;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-primary:hover {
            background: #218838;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .alerts-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .alert-item {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            transition: all 0.3s;
        }
        
        .alert-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .alert-item.high {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .alert-item.critical {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .alert-item h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .alert-item p {
            color: #6c757d;
            margin: 4px 0;
            font-size: 14px;
        }
        
        .alert-item strong {
            color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        @media (max-width: 1024px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🩺 Doctor Dashboard</h1>
                <p>Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?>!</p>
            </div>
            
            <div class="user-menu">
    <?php include '../includes/notification_widget.php'; ?>
    
    <div class="user-info">
        <div class="user-name">Dr. <?php echo htmlspecialchars($doctor_name); ?></div>
        <div class="user-role">Healthcare Professional</div>
    </div>
    
    <div class="profile-dropdown">
        <div class="profile-btn" onclick="toggleDropdown()">
            <?php 
            $initials = '';
            $names = explode(' ', $doctor_name);
            $initials = strtoupper(substr($names[0], 0, 1));
            if (isset($names[1])) {
                $initials .= strtoupper(substr($names[1], 0, 1));
            }
            echo $initials;
            ?>
        </div>
        
        <div class="dropdown-menu-custom" id="dropdownMenu">
            <a href="profile.php">
                <i>👤</i>
                <span>My Profile</span>
            </a>
            <a href="settings.php">
                <i>⚙️</i>
                <span>Settings</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="../auth/logout.php" class="logout-link">
                <i>🚪</i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>👥 Total Patients</h3>
                <p class="stat-number"><?php echo $total_patients; ?></p>
            </div>
            
            <div class="stat-card alert">
                <h3>⚠️ High Risk (Today)</h3>
                <p class="stat-number"><?php echo $high_risk_patients; ?></p>
            </div>
            
            <div class="stat-card warning">
                <h3>📅 Pending Consultations</h3>
                <p class="stat-number"><?php echo $pending_consultations; ?></p>
            </div>
            
            <div class="stat-card success">
                <h3>✅ Completed</h3>
                <p class="stat-number"><?php echo $completed_consultations; ?></p>
            </div>
        </div>
        
        <!-- Dashboard Sections -->
        <div class="dashboard-sections">
            <div class="section">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="update_records.php" class="btn btn-primary">
                        📋 Update Patient Records
                    </a>
                    <a href="consultation.php" class="btn btn-warning">
                        🏥 View Consultations
                    </a>
                    <a href="patient_list.php" class="btn btn-info">
                        👨‍⚕️ My Patients
                    </a>
                    <a href="schedule.php" class="btn btn-success">
                        📅 Manage & Schedule Appointments
                    </a>
                </div>
            </div>
            
            <div class="section">
                <h2>Recent High-Risk Alerts</h2>
                <?php if (empty($recent_alerts)): ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p>No recent high-risk alerts.</p>
                        <p style="font-size: 12px; margin-top: 5px;">All patients are stable.</p>
                    </div>
                <?php else: ?>
                    <div class="alerts-list">
                        <?php foreach ($recent_alerts as $alert): ?>
                            <div class="alert-item <?php echo $alert['risk_level']; ?>">
                                <h4>👤 <?php echo htmlspecialchars($alert['patient_name']); ?></h4>
                                <p><strong>Risk Level:</strong> 
                                    <span style="color: <?php echo $alert['risk_level'] === 'critical' ? '#dc3545' : '#ffc107'; ?>;">
                                        <?php echo ucfirst($alert['risk_level']); ?>
                                    </span>
                                </p>
                                <p><strong>Severity:</strong> <?php echo ucfirst($alert['severity']); ?></p>
                                <p><strong>Symptoms:</strong> <?php echo htmlspecialchars(substr($alert['symptoms'], 0, 60)) . '...'; ?></p>
                                <p><strong>📞 Phone:</strong> <?php echo htmlspecialchars($alert['phone'] ?? 'N/A'); ?></p>
                                <p><strong>🕒 Time:</strong> <?php echo date('M j, Y g:i A', strtotime($alert['reported_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById('dropdownMenu');
        dropdown.classList.toggle('show');
    }
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(e) {
        const dropdown = document.getElementById('dropdownMenu');
        const profileBtn = document.querySelector('.profile-btn');
        
        if (!profileBtn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
    </script>
</body>
</html>