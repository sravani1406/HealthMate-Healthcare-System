<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../patient/login.php');
    exit();
}

$type = $_GET['type'] ?? 'doctors';
$tab = $_GET['tab'] ?? 'active';
$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'];
    
    switch ($action) {
        case 'approve_doctor':
            $stmt = $pdo->prepare("UPDATE doctors SET is_active = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'Doctor approved successfully!';
            } else {
                $error = 'Failed to approve doctor.';
            }
            break;
            
        case 'reject_doctor':
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ? AND is_active = 0");
            if ($stmt->execute([$user_id])) {
                $success = 'Doctor registration rejected.';
            } else {
                $error = 'Failed to reject doctor.';
            }
            break;
            
        case 'deactivate_doctor':
            $stmt = $pdo->prepare("UPDATE doctors SET is_active = 0 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'Doctor deactivated successfully!';
            } else {
                $error = 'Failed to deactivate doctor.';
            }
            break;
            
        case 'activate_doctor':
            $stmt = $pdo->prepare("UPDATE doctors SET is_active = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'Doctor activated successfully!';
            } else {
                $error = 'Failed to activate doctor.';
            }
            break;
            
        case 'delete_patient':
            // First delete related records
            $pdo->prepare("DELETE FROM ai_analyses WHERE patient_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM consultations WHERE patient_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM medical_records WHERE patient_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM notifications WHERE sender_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
            
            // Then delete patient
            $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'Patient deleted successfully!';
            } else {
                $error = 'Failed to delete patient.';
            }
            break;
    }
}

// Get users based on type and tab
if ($type === 'doctors') {
    if ($tab === 'pending') {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE is_active = 0 ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE is_active = 1 ORDER BY name");
    }
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, d.name as doctor_name 
        FROM patients p 
        LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
        ORDER BY p.name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
?>

<div class="manage-users">
    <h1>Manage Users</h1>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="user-tabs">
        <a href="?type=doctors&tab=active" class="tab <?php echo ($type === 'doctors' && $tab === 'active') ? 'active' : ''; ?>">Active Doctors</a>
        <a href="?type=doctors&tab=pending" class="tab <?php echo ($type === 'doctors' && $tab === 'pending') ? 'active' : ''; ?>">Pending Doctors</a>
        <a href="?type=patients" class="tab <?php echo ($type === 'patients') ? 'active' : ''; ?>">Patients</a>
    </div>
    
    <div class="users-container">
        <?php if ($type === 'doctors'): ?>
            <?php if ($tab === 'pending'): ?>
                <h2>Pending Doctor Approvals</h2>
                <?php if (empty($users)): ?>
                    <p>No pending doctor approvals.</p>
                <?php else: ?>
                    <div class="pending-doctors">
                        <?php foreach ($users as $doctor): ?>
                            <div class="pending-doctor-card">
                                <h3><?php echo htmlspecialchars($doctor['name']); ?></h3>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <p><strong>License:</strong> <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                                <p><strong>Applied:</strong> <?php echo date('M j, Y', strtotime($doctor['created_at'])); ?></p>
                                
                                <div class="approval-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_doctor">
                                        <input type="hidden" name="user_id" value="<?php echo $doctor['id']; ?>">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Approve this doctor?')">Approve</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject_doctor">
                                        <input type="hidden" name="user_id" value="<?php echo $doctor['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this doctor application?')">Reject</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <h2>Active Doctors</h2>
                <?php if (empty($users)): ?>
                    <p>No active doctors found.</p>
                <?php else: ?>
                    <div class="active-doctors">
                        <?php foreach ($users as $doctor): ?>
                            <div class="doctor-card">
                                <h3><?php echo htmlspecialchars($doctor['name']); ?></h3>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <p><strong>License:</strong> <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                                <p><strong>Last Login:</strong> <?php echo $doctor['last_login'] ? date('M j, Y g:i A', strtotime($doctor['last_login'])) : 'Never'; ?></p>
                                
                                <div class="doctor-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="deactivate_doctor">
                                        <input type="hidden" name="user_id" value="<?php echo $doctor['id']; ?>">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Deactivate this doctor?')">Deactivate</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <h2>Patients</h2>
            <?php if (empty($users)): ?>
                <p>No patients found.</p>
            <?php else: ?>
                <div class="patients-list">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Assigned Doctor</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                    <td><?php echo $patient['age']; ?></td>
                                    <td><?php echo $patient['gender']; ?></td>
                                    <td><?php echo $patient['doctor_name'] ? htmlspecialchars($patient['doctor_name']) : 'Not assigned'; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_patient">
                                            <input type="hidden" name="user_id" value="<?php echo $patient['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this patient and all related records?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>