<?php
// patient/settings.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// ===== DOMPDF setup =====
require_once '../dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// ===== Add PDF export block here =====
if (isset($_GET['download_pdf'])) {

    $dompdf = new Dompdf();

    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $html = '
    <div style="font-family: DejaVu Sans, sans-serif; padding:20px;">
        <h2 style="text-align:center; color:#667eea;">HealthHive – Patient Data Export</h2>
        <hr>
        <h3>👤 Personal Information</h3>
        <p><strong>Username:</strong> '.htmlspecialchars($user['username']).'</p>
        <p><strong>Email:</strong> '.htmlspecialchars($user['email']).'</p>
        <p><strong>Member Since:</strong> '.date('F Y', strtotime($user['created_at'])).'</p>
        <hr>
        <h3>⚙️ Preferences</h3>
        <p><strong>Language:</strong> '.htmlspecialchars($user['language_preference'] ?? 'en').'</p>
        <p><strong>Email Notifications:</strong> '.($_SESSION['email_notifications'] ?? 1 ? 'Enabled' : 'Disabled').'</p>
        <p><strong>SMS Notifications:</strong> '.($_SESSION['sms_notifications'] ?? 1 ? 'Enabled' : 'Disabled').'</p>
        <p><strong>Appointment Reminders:</strong> '.($_SESSION['appointment_reminders'] ?? 1 ? 'Enabled' : 'Disabled').'</p>
        <p><strong>Medication Reminders:</strong> '.($_SESSION['medication_reminders'] ?? 1 ? 'Enabled' : 'Disabled').'</p>
        <hr>
        <h3>🔒 Privacy Settings</h3>
        <p><strong>Profile Visibility:</strong> '.ucfirst($_SESSION['profile_visibility'] ?? 'Private').'</p>
        <p><strong>Share Medical History:</strong> '.(($_SESSION['share_medical_history'] ?? 0) ? 'Yes' : 'No').'</p>
        <br><br>
        <p style="text-align:center; color:#777;">Generated on '.date('d-m-Y H:i').' by HealthHive</p>
    </div>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('HealthHive_Patient_Data.pdf', ['Attachment' => true]);
    exit;
}
// ===== End of PDF export block =====

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Notification Preferences
    if (isset($_POST['update_notifications'])) {
        $_SESSION['email_notifications'] = isset($_POST['email_notifications']) ? 1 : 0;
        $_SESSION['sms_notifications'] = isset($_POST['sms_notifications']) ? 1 : 0;
        $_SESSION['appointment_reminders'] = isset($_POST['appointment_reminders']) ? 1 : 0;
        $_SESSION['medication_reminders'] = isset($_POST['medication_reminders']) ? 1 : 0;
        
        $message = "Notification preferences updated successfully!";
    }
    
    // Update Privacy Settings
    if (isset($_POST['update_privacy'])) {
        $_SESSION['profile_visibility'] = $_POST['profile_visibility'] ?? 'private';
        $_SESSION['share_medical_history'] = isset($_POST['share_medical_history']) ? 1 : 0;
        
        $message = "Privacy settings updated successfully!";
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 8) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        log_activity($user_id, 'password_changed', 'User changed password');
                        $message = "Password changed successfully!";
                    } else {
                        $error = "New password must be at least 8 characters long.";
                    }
                } else {
                    $error = "New passwords do not match.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Error changing password: " . $e->getMessage();
        }
    }
    
    // Update Language
    if (isset($_POST['update_language'])) {
        try {
            $language = $_POST['language_preference'];
            $stmt = $db->prepare("UPDATE users SET language_preference = ? WHERE id = ?");
            $stmt->execute([$language, $user_id]);
            
            $message = "Language preference updated successfully!";
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error updating language: " . $e->getMessage();
        }
    }
}

// Get notification preferences from session (demo)
$email_notifications = $_SESSION['email_notifications'] ?? 1;
$sms_notifications = $_SESSION['sms_notifications'] ?? 1;
$appointment_reminders = $_SESSION['appointment_reminders'] ?? 1;
$medication_reminders = $_SESSION['medication_reminders'] ?? 1;

// Get privacy settings from session (demo)
$profile_visibility = $_SESSION['profile_visibility'] ?? 'private';
$share_medical_history = $_SESSION['share_medical_history'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings - HealthHive</title>
<style>
/* Full CSS from your previous code */
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f5f5f5;min-height:100vh;padding:20px}.container{max-width:1200px;margin:0 auto}.header{background:white;padding:20px 30px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1);display:flex;justify-content:space-between;align-items:center}.header h1{color:#667eea;font-size:28px}.back-btn{background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;transition:background 0.3s}.back-btn:hover{background:#5568d3}.alert{padding:15px 20px;border-radius:5px;margin-bottom:20px;font-weight:500}.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.tabs{background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1)}.tab-buttons{display:flex;background:#f8f9fa;border-bottom:2px solid #e9ecef;overflow-x:auto}.tab-button{padding:15px 25px;background:none;border:none;cursor:pointer;font-size:16px;color:#6c757d;transition:all 0.3s;white-space:nowrap}.tab-button:hover{background:#e9ecef}.tab-button.active{color:#667eea;border-bottom:3px solid #667eea;font-weight:600}.tab-content{display:none;padding:30px}.tab-content.active{display:block}.form-group{margin-bottom:20px}.form-group label{display:block;margin-bottom:8px;color:#333;font-weight:500}.form-group input,.form-group select{width:100%;padding:12px;border:1px solid #ddd;border-radius:5px;font-size:15px;font-family:inherit}.form-group input:focus,.form-group select:focus{outline:none;border-color:#667eea}.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}.btn{background:#667eea;color:white;padding:12px 30px;border:none;border-radius:5px;font-size:16px;cursor:pointer;transition:background 0.3s}.btn:hover{background:#5568d3}.btn-danger{background:#dc3545}.btn-danger:hover{background:#c82333}.checkbox-group{display:flex;align-items:center;padding:15px;background:#f8f9fa;border-radius:5px;margin-bottom:15px}.checkbox-group input[type=checkbox]{width:auto;margin-right:12px;cursor:pointer}.checkbox-group label{margin-bottom:0;cursor:pointer;flex:1}.checkbox-group .description{color:#6c757d;font-size:14px;margin-top:5px}.info-box{padding:20px;background:#e7f3ff;border-radius:8px;border-left:4px solid #0d6efd;margin-bottom:20px}.warning-box{padding:20px;background:#fff3cd;border-radius:8px;border-left:4px solid #ffc107;margin-bottom:20px}.danger-box{padding:20px;background:#f8d7da;border-radius:8px;border-left:4px solid #dc3545;margin-top:30px}@media(max-width:768px){.form-row{grid-template-columns:1fr}.header{flex-direction:column;text-align:center;gap:15px}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚙️ Settings</h1>
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="tabs">
        <div class="tab-buttons">
            <button class="tab-button active" onclick="openTab(event,'notifications')">Notifications</button>
            <button class="tab-button" onclick="openTab(event,'privacy')">Privacy</button>
            <button class="tab-button" onclick="openTab(event,'security')">Security</button>
            <button class="tab-button" onclick="openTab(event,'preferences')">Preferences</button>
            <button class="tab-button" onclick="openTab(event,'account')">Account</button>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications" class="tab-content active">
            <h2 style="margin-bottom:25px;color:#333;">Notification Settings</h2>
            <div class="info-box"><strong>📧 Stay Connected</strong><p style="margin:5px 0 0 0;color:#666;">Choose how you want to receive notifications about your health and appointments.</p></div>
            <form method="POST">
                <div class="checkbox-group">
                    <input type="checkbox" name="email_notifications" id="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                    <div>
                        <label for="email_notifications">Email Notifications</label>
                        <div class="description">Receive important updates and reminders via email</div>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="sms_notifications" id="sms_notifications" <?php echo $sms_notifications ? 'checked' : ''; ?>>
                    <div>
                        <label for="sms_notifications">SMS Notifications</label>
                        <div class="description">Get text message alerts for urgent matters</div>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="appointment_reminders" id="appointment_reminders" <?php echo $appointment_reminders ? 'checked' : ''; ?>>
                    <div>
                        <label for="appointment_reminders">Appointment Reminders</label>
                        <div class="description">Receive reminders before scheduled appointments</div>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="medication_reminders" id="medication_reminders" <?php echo $medication_reminders ? 'checked' : ''; ?>>
                    <div>
                        <label for="medication_reminders">Medication Reminders</label>
                        <div class="description">Get notified when it's time to take your medications</div>
                    </div>
                </div>
                <button type="submit" name="update_notifications" class="btn">Save Notification Settings</button>
            </form>
        </div>

        <!-- Privacy Tab -->
        <div id="privacy" class="tab-content">
            <h2 style="margin-bottom:25px;color:#333;">Privacy Settings</h2>
            <div class="info-box"><strong>🔒 Your Privacy Matters</strong><p style="margin:5px 0 0 0;color:#666;">Control who can see your information and how it's used.</p></div>
            <form method="POST">
                <div class="form-group">
                    <label>Profile Visibility</label>
                    <select name="profile_visibility">
                        <option value="private" <?php echo $profile_visibility==='private'?'selected':''; ?>>Private - Only visible to me and my doctors</option>
                        <option value="doctors" <?php echo $profile_visibility==='doctors'?'selected':''; ?>>Doctors - Visible to all verified doctors</option>
                        <option value="public" <?php echo $profile_visibility==='public'?'selected':''; ?>>Public - Anyone can view basic info</option>
                    </select>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="share_medical_history" id="share_medical_history" <?php echo $share_medical_history?'checked':''; ?>>
                    <div>
                        <label for="share_medical_history">Share Medical History with Doctors</label>
                        <div class="description">Allow doctors to view your complete medical history for better diagnosis</div>
                    </div>
                </div>
                <button type="submit" name="update_privacy" class="btn">Save Privacy Settings</button>
            </form>
        </div>

        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <h2 style="margin-bottom:25px;color:#333;">Security Settings</h2>
            <div class="warning-box"><strong>⚠️ Password Security</strong><p style="margin:5px 0 0 0;color:#856404;">Use a strong, unique password to protect your health information.</p></div>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" required minlength="8">
                    <small style="color:#6c757d;">Password must be at least 8 characters long.</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" name="change_password" class="btn">Change Password</button>
            </form>
        </div>

        <!-- Preferences Tab -->
        <div id="preferences" class="tab-content">
            <h2 style="margin-bottom:25px;color:#333;">General Preferences</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Language Preference</label>
                    <select name="language_preference">
                        <option value="en" <?php echo ($user['language_preference'] ?? 'en')==='en'?'selected':''; ?>>English</option>
                        <option value="te" <?php echo ($user['language_preference'] ?? '')==='te'?'selected':''; ?>>Telugu</option>
                        <option value="hn" <?php echo ($user['language_preference'] ?? '')==='hn'?'selected':''; ?>>Hindi</option>
                    </select>
                </div>
                <button type="submit" name="update_language" class="btn">Save Preferences</button>
            </form>
            <div style="margin-top:40px;">
                <h3 style="margin-bottom:15px;color:#333;">Data Export</h3>
                <p style="color:#6c757d;margin-bottom:15px;">Download a copy of all your health data.</p>
                <a href="?download_pdf=1" class="btn" style="background:#17a2b8;">Export My Data</a>
            </div>
        </div>

        <!-- Account Tab -->
        <div id="account" class="tab-content">
            <h2 style="margin-bottom:25px;color:#333;">Account Management</h2>
            <div class="info-box">
                <strong>👤 Account Information</strong>
                <p style="margin:10px 0 5px 0;"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p style="margin:5px 0;"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="danger-box">
                <p>For account deletion or major changes, contact support.</p>
            </div>
        </div>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    var tabContents = document.getElementsByClassName('tab-content');
    for (var i = 0; i < tabContents.length; i++) tabContents[i].classList.remove('active');
    var tabButtons = document.getElementsByClassName('tab-button');
    for (var i = 0; i < tabButtons.length; i++) tabButtons[i].classList.remove('active');
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}
</script>
</body>
</html>
