<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

$doctor_user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get doctor's ID from doctors table
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$doctor_user_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    $error = 'Doctor profile not found.';
    $doctor_id = null;
} else {
    $doctor_id = $doctor['id'];
}

// Handle adding new appointment slots
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    try {
        $slot_date = $_POST['slot_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $max_patients = intval($_POST['max_patients']);
        
        if (empty($slot_date) || empty($start_time) || empty($end_time)) {
            $error = 'Please fill in all required fields.';
        } elseif ($start_time >= $end_time) {
            $error = 'End time must be after start time.';
        } else {
            // Check if slot already exists
            $stmt = $pdo->prepare("
                SELECT id FROM appointment_slots 
                WHERE doctor_id = ? AND slot_date = ? AND start_time = ? AND end_time = ?
            ");
            $stmt->execute([$doctor_user_id, $slot_date, $start_time, $end_time]);
            
            if ($stmt->fetch()) {
                $error = 'This time slot already exists.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO appointment_slots (doctor_id, slot_date, start_time, end_time, max_patients, is_available) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                
                if ($stmt->execute([$doctor_user_id, $slot_date, $start_time, $end_time, $max_patients])) {
                    $success = 'Appointment slot added successfully!';
                } else {
                    $error = 'Failed to add appointment slot.';
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle deleting appointment slots
if (isset($_POST['delete_slot'])) {
    try {
        $slot_id = $_POST['slot_id'];
        
        // Check if slot has any booked consultations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM consultations 
            WHERE doctor_id = ? 
            AND DATE(consultation_date) = (SELECT slot_date FROM appointment_slots WHERE id = ?)
            AND TIME(consultation_date) >= (SELECT start_time FROM appointment_slots WHERE id = ?)
            AND TIME(consultation_date) < (SELECT end_time FROM appointment_slots WHERE id = ?)
            AND status = 'scheduled'
        ");
        $stmt->execute([$doctor_user_id, $slot_id, $slot_id, $slot_id]);
        $booked_count = $stmt->fetchColumn();
        
        if ($booked_count > 0) {
            $error = 'Cannot delete slot with scheduled consultations.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM appointment_slots WHERE id = ? AND doctor_id = ?");
            if ($stmt->execute([$slot_id, $doctor_user_id])) {
                $success = 'Appointment slot deleted successfully!';
            } else {
                $error = 'Failed to delete appointment slot.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Handle toggling slot availability
if (isset($_POST['toggle_availability'])) {
    try {
        $slot_id = $_POST['slot_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status == 1 ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE appointment_slots SET is_available = ? WHERE id = ? AND doctor_id = ?");
        if ($stmt->execute([$new_status, $slot_id, $doctor_user_id])) {
            $success = 'Slot availability updated successfully!';
        } else {
            $error = 'Failed to update slot availability.';
        }
    } catch (PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all appointment slots
$appointment_slots = [];
if ($doctor_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM appointment_slots 
        WHERE doctor_id = ? 
        ORDER BY slot_date ASC, start_time ASC
    ");
    $stmt->execute([$doctor_user_id]);
    $appointment_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get upcoming consultations
$upcoming_consultations = [];
if ($doctor_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as patient_name, u.phone, u.email
        FROM consultations c
        JOIN users u ON c.patient_id = u.id
        WHERE c.doctor_id = ? 
        AND c.status = 'scheduled'
        AND c.consultation_date >= NOW()
        ORDER BY c.consultation_date ASC
        LIMIT 10
    ");
    $stmt->execute([$doctor_user_id]);
    $upcoming_consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - HealthHive</title>
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
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #218838;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-danger {
            background: #dc3545;
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
        
        .slots-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .slot-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .slot-card.unavailable {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        
        .slot-info h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .slot-info p {
            color: #6c757d;
            font-size: 14px;
            margin: 4px 0;
        }
        
        .slot-actions {
            display: flex;
            gap: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
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
        
        .consultations-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .consultation-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .consultation-card h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .consultation-card p {
            color: #6c757d;
            font-size: 14px;
            margin: 4px 0;
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
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .slot-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Schedule Management</h1>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="main-content">
            <!-- Add Appointment Slot Form -->
            <div class="section">
                <h2>Add Appointment Slot</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="slot_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Time *</label>
                            <input type="time" name="start_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label>End Time *</label>
                            <input type="time" name="end_time" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Max Patients per Slot</label>
                        <input type="number" name="max_patients" min="1" max="10" value="1">
                    </div>
                    
                    <button type="submit" name="add_slot" class="btn">Add Slot</button>
                </form>
            </div>
            
            <!-- My Appointment Slots -->
            <div class="section">
                <h2>My Appointment Slots</h2>
                <?php if (empty($appointment_slots)): ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p>No appointment slots created yet.</p>
                        <p style="font-size: 12px; margin-top: 5px;">Add slots to let patients book consultations.</p>
                    </div>
                <?php else: ?>
                    <div class="slots-list">
                        <?php foreach ($appointment_slots as $slot): ?>
                            <div class="slot-card <?php echo $slot['is_available'] ? '' : 'unavailable'; ?>">
                                <div class="slot-info">
                                    <h4>📅 <?php echo date('l, M j, Y', strtotime($slot['slot_date'])); ?></h4>
                                    <p>🕐 <strong>Time:</strong> <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?></p>
                                    <p>👥 <strong>Max Patients:</strong> <?php echo $slot['max_patients']; ?></p>
                                    <p>
                                        <span class="badge <?php echo $slot['is_available'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $slot['is_available'] ? 'Available' : 'Unavailable'; ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="slot-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $slot['is_available']; ?>">
                                        <button type="submit" name="toggle_availability" class="btn btn-sm btn-warning">
                                            <?php echo $slot['is_available'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this slot?');">
                                        <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                        <button type="submit" name="delete_slot" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Consultations -->
        <div class="section full-width">
            <h2>Upcoming Consultations</h2>
            <?php if (empty($upcoming_consultations)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <p>No upcoming consultations scheduled.</p>
                </div>
            <?php else: ?>
                <div class="consultations-list">
                    <?php foreach ($upcoming_consultations as $consultation): ?>
                        <div class="consultation-card">
                            <h4>👤 <?php echo htmlspecialchars($consultation['patient_name']); ?></h4>
                            <p>📅 <strong>Date:</strong> <?php echo date('l, M j, Y', strtotime($consultation['consultation_date'])); ?></p>
                            <p>🕐 <strong>Time:</strong> <?php echo date('g:i A', strtotime($consultation['consultation_date'])); ?></p>
                            <p>🏥 <strong>Type:</strong> <?php echo ucfirst($consultation['consultation_type']); ?></p>
                            <p>📞 <strong>Phone:</strong> <?php echo htmlspecialchars($consultation['phone'] ?? 'N/A'); ?></p>
                            <p>📧 <strong>Email:</strong> <?php echo htmlspecialchars($consultation['email']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>