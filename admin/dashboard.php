<?php
// admin/dashboard.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle doctor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_doctor'])) {
        $doctor_id = intval($_POST['doctor_id']);
        
        try {
            $db->beginTransaction();
            
            // Get doctor's user_id
            $stmt = $db->prepare("SELECT user_id FROM doctors WHERE id = ?");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doctor) {
                // Update doctor verification status
                $stmt = $db->prepare("UPDATE doctors SET is_verified = 1 WHERE id = ?");
                $stmt->execute([$doctor_id]);
                
                // Activate user account
                $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $stmt->execute([$doctor['user_id']]);
                
                // Send notification to doctor
                send_notification(
                    $doctor['user_id'],
                    'Account Approved',
                    'Your doctor account has been approved by admin. You can now login and start consultations.',
                    'system',
                    'high'
                );
                
                $db->commit();
                $message = 'Doctor approved successfully!';
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to approve doctor: ' . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject_doctor'])) {
        $doctor_id = intval($_POST['doctor_id']);
        $rejection_reason = trim($_POST['rejection_reason'] ?? 'Account verification failed');
        
        try {
            $db->beginTransaction();
            
            // Get doctor's user_id
            $stmt = $db->prepare("SELECT user_id FROM doctors WHERE id = ?");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doctor) {
                // Send notification to doctor
                send_notification(
                    $doctor['user_id'],
                    'Account Rejected',
                    'Your doctor account verification was rejected. Reason: ' . $rejection_reason,
                    'system',
                    'high'
                );
                
                // Delete doctor record
                $stmt = $db->prepare("DELETE FROM doctors WHERE id = ?");
                $stmt->execute([$doctor_id]);
                
                // Delete user account
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$doctor['user_id']]);
                
                $db->commit();
                $message = 'Doctor application rejected and removed.';
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to reject doctor: ' . $e->getMessage();
        }
    }
}

// Fetch pending doctors
$pending_doctors = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name, u.email, u.phone, u.username, u.created_at
        FROM doctors d
        INNER JOIN users u ON d.user_id = u.id
        WHERE d.is_verified = 0
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $pending_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch pending doctors';
}

// Fetch approved doctors
$approved_doctors = [];
try {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name, u.email, u.phone, u.is_active
        FROM doctors d
        INNER JOIN users u ON d.user_id = u.id
        WHERE d.is_verified = 1
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $approved_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to fetch approved doctors';
}

// Fetch statistics
$stats = [
    'total_patients' => 0,
    'total_doctors' => 0,
    'pending_approvals' => 0,
    'total_appointments' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'patient'");
    $stats['total_patients'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM doctors WHERE is_verified = 1");
    $stats['total_doctors'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM doctors WHERE is_verified = 0");
    $stats['pending_approvals'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM consultations");
    $stats['total_appointments'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Stats remain at 0
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HealthHive</title>
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
            color: #dc3545;
            font-size: 28px;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        }
        
        .stat-card h3 {
            font-size: 36px;
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #6c757d;
            font-size: 14px;
        }
        
        .tabs {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tab-buttons {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .tab-button {
            padding: 15px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .tab-button:hover {
            background: #e9ecef;
        }
        
        .tab-button.active {
            color: #dc3545;
            border-bottom: 3px solid #dc3545;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .doctor-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .doctor-card h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .doctor-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .doctor-info p {
            color: #6c757d;
            margin: 5px 0;
        }
        
        .doctor-info strong {
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            margin-right: 10px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 10px 0;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Admin Dashboard</h1>
            <div>
                <span style="margin-right: 20px; color: #6c757d;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total_patients']; ?></h3>
                <p>Total Patients</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_doctors']; ?></h3>
                <p>Approved Doctors</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['pending_approvals']; ?></h3>
                <p>Pending Approvals</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_appointments']; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="openTab(event, 'pending')">
                    Pending Approvals (<?php echo count($pending_doctors); ?>)
                </button>
                <button class="tab-button" onclick="openTab(event, 'approved')">
                    Approved Doctors (<?php echo count($approved_doctors); ?>)
                </button>
            </div>
            
            <!-- Pending Doctors Tab -->
            <div id="pending" class="tab-content active">
                <h2 style="margin-bottom: 20px; color: #333;">Pending Doctor Approvals</h2>
                
                <?php if (empty($pending_doctors)): ?>
                    <p style="color: #6c757d; text-align: center; padding: 40px;">No pending doctor approvals</p>
                <?php else: ?>
                    <?php foreach ($pending_doctors as $doctor): ?>
                        <div class="doctor-card">
                            <h3><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                            <div class="doctor-info">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($doctor['username']); ?></p>
                                <p><strong>License:</strong> <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <p><strong>Qualification:</strong> <?php echo htmlspecialchars($doctor['qualification'] ?? 'N/A'); ?></p>
                                <p><strong>Experience:</strong> <?php echo $doctor['experience_years']; ?> years</p>
                                <p><strong>Consultation Fee:</strong> $<?php echo number_format($doctor['consultation_fee'], 2); ?></p>
                            </div>
                            <p style="color: #6c757d; font-size: 13px; margin-bottom: 15px;">
                                Applied on: <?php echo date('M d, Y g:i A', strtotime($doctor['created_at'])); ?>
                            </p>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                <button type="submit" name="approve_doctor" class="btn btn-success" 
                                        onclick="return confirm('Are you sure you want to approve this doctor?')">
                                    ✓ Approve
                                </button>
                            </form>
                            
                            <button class="btn btn-danger" onclick="showRejectModal(<?php echo $doctor['id']; ?>)">
                                ✗ Reject
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Approved Doctors Tab -->
            <div id="approved" class="tab-content">
                <h2 style="margin-bottom: 20px; color: #333;">Approved Doctors</h2>
                
                <?php if (empty($approved_doctors)): ?>
                    <p style="color: #6c757d; text-align: center; padding: 40px;">No approved doctors yet</p>
                <?php else: ?>
                    <?php foreach ($approved_doctors as $doctor): ?>
                        <div class="doctor-card">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h3><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                                </div>
                                <span class="badge <?php echo $doctor['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="doctor-info">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                                <p><strong>License:</strong> <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <p><strong>Experience:</strong> <?php echo $doctor['experience_years']; ?> years</p>
                                <p><strong>Fee:</strong> $<?php echo number_format($doctor['consultation_fee'], 2); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRejectModal()">&times;</span>
            <h2 style="color: #dc3545; margin-bottom: 20px;">Reject Doctor Application</h2>
            <form method="POST">
                <input type="hidden" name="doctor_id" id="reject_doctor_id">
                <label for="rejection_reason">Reason for Rejection:</label>
                <textarea name="rejection_reason" id="rejection_reason" rows="4" required 
                          placeholder="Enter reason for rejecting this application..."></textarea>
                <button type="submit" name="reject_doctor" class="btn btn-danger" style="width: 100%;">
                    Reject Application
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function openTab(evt, tabName) {
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            var tabButtons = document.getElementsByClassName('tab-button');
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
        
        function showRejectModal(doctorId) {
            document.getElementById('reject_doctor_id').value = doctorId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>