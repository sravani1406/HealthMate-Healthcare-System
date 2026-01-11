<?php
// patient/profile.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

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

// Fetch patient medical records
$stmt = $db->prepare("SELECT * FROM patient_records WHERE patient_id = ?");
$stmt->execute([$user_id]);
$patient_record = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch emergency contacts
$stmt = $db->prepare("SELECT * FROM emergency_contacts WHERE patient_id = ? ORDER BY is_primary DESC");
$stmt->execute([$user_id]);
$emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile Information
    if (isset($_POST['update_profile'])) {
        try {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $date_of_birth = $_POST['date_of_birth'];
            $gender = $_POST['gender'];
            $address = trim($_POST['address']);
            $language_preference = $_POST['language_preference'];
            
            // Check if email is already taken by another user
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "Email is already in use by another account.";
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ?, address = ?, language_preference = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $date_of_birth, $gender, $address, $language_preference, $user_id]);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                $message = "Profile updated successfully!";
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
    
    // Update Medical Records
    if (isset($_POST['update_medical'])) {
        try {
            $blood_group = trim($_POST['blood_group']);
            $height = $_POST['height'];
            $weight = $_POST['weight'];
            $allergies = trim($_POST['allergies']);
            $chronic_conditions = trim($_POST['chronic_conditions']);
            $current_medications = trim($_POST['current_medications']);
            $medical_history = trim($_POST['medical_history']);
            $family_history = trim($_POST['family_history']);
            
            if ($patient_record) {
                // Update existing record
                $stmt = $db->prepare("UPDATE patient_records SET blood_group = ?, height = ?, weight = ?, allergies = ?, chronic_conditions = ?, current_medications = ?, medical_history = ?, family_history = ? WHERE patient_id = ?");
                $stmt->execute([$blood_group, $height, $weight, $allergies, $chronic_conditions, $current_medications, $medical_history, $family_history, $user_id]);
            } else {
                // Insert new record
                $stmt = $db->prepare("INSERT INTO patient_records (patient_id, blood_group, height, weight, allergies, chronic_conditions, current_medications, medical_history, family_history) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $blood_group, $height, $weight, $allergies, $chronic_conditions, $current_medications, $medical_history, $family_history]);
            }
            
            $message = "Medical records updated successfully!";
            // Refresh medical records
            $stmt = $db->prepare("SELECT * FROM patient_records WHERE patient_id = ?");
            $stmt->execute([$user_id]);
            $patient_record = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error updating medical records: " . $e->getMessage();
        }
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 8) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
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
    
    // Add Emergency Contact
    if (isset($_POST['add_emergency_contact'])) {
        try {
            $contact_name = trim($_POST['contact_name']);
            $relationship = trim($_POST['relationship']);
            $contact_phone = trim($_POST['contact_phone']);
            $contact_email = trim($_POST['contact_email']);
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            
            // If this is set as primary, unset other primary contacts
            if ($is_primary) {
                $stmt = $db->prepare("UPDATE emergency_contacts SET is_primary = 0 WHERE patient_id = ?");
                $stmt->execute([$user_id]);
            }
            
            $stmt = $db->prepare("INSERT INTO emergency_contacts (patient_id, contact_name, relationship, phone, email, is_primary) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $contact_name, $relationship, $contact_phone, $contact_email, $is_primary]);
            
            $message = "Emergency contact added successfully!";
            // Refresh emergency contacts
            $stmt = $db->prepare("SELECT * FROM emergency_contacts WHERE patient_id = ? ORDER BY is_primary DESC");
            $stmt->execute([$user_id]);
            $emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error adding emergency contact: " . $e->getMessage();
        }
    }
    
    // Delete Emergency Contact
    if (isset($_POST['delete_contact'])) {
        try {
            $contact_id = $_POST['contact_id'];
            $stmt = $db->prepare("DELETE FROM emergency_contacts WHERE id = ? AND patient_id = ?");
            $stmt->execute([$contact_id, $user_id]);
            
            $message = "Emergency contact deleted successfully!";
            // Refresh emergency contacts
            $stmt = $db->prepare("SELECT * FROM emergency_contacts WHERE patient_id = ? ORDER BY is_primary DESC");
            $stmt->execute([$user_id]);
            $emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error deleting emergency contact: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile - HealthHive</title>
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
            max-width: 1200px;
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
            color: #667eea;
            font-size: 28px;
        }
        
        .back-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #5568d3;
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
            overflow-x: auto;
        }
        
        .tab-button {
            padding: 15px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #6c757d;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab-button:hover {
            background: #e9ecef;
        }
        
        .tab-button.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
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
            min-height: 100px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .emergency-contacts-list {
            margin-top: 30px;
        }
        
        .contact-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .contact-info h3 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .contact-info p {
            color: #6c757d;
            margin: 3px 0;
        }
        
        .primary-badge {
            background: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .contact-card {
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
            <h1>👤 Patient Profile</h1>
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
                <button class="tab-button active" onclick="openTab(event, 'profile')">Profile Information</button>
                <button class="tab-button" onclick="openTab(event, 'medical')">Medical Records</button>
                <button class="tab-button" onclick="openTab(event, 'emergency')">Emergency Contacts</button>
                <button class="tab-button" onclick="openTab(event, 'security')">Security</button>
            </div>
            
            <!-- Profile Information Tab -->
            <div id="profile" class="tab-content active">
                <h2 style="margin-bottom: 25px; color: #333;">Personal Information</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Language Preference</label>
                            <select name="language_preference">
                                <option value="en" <?php echo ($user['language_preference'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="es" <?php echo ($user['language_preference'] ?? '') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                <option value="fr" <?php echo ($user['language_preference'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                <option value="de" <?php echo ($user['language_preference'] ?? '') === 'de' ? 'selected' : ''; ?>>German</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
            
            <!-- Medical Records Tab -->
            <div id="medical" class="tab-content">
                <h2 style="margin-bottom: 25px; color: #333;">Medical Information</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Blood Group</label>
                            <select name="blood_group">
                                <option value="">Select Blood Group</option>
                                <option value="A+" <?php echo ($patient_record['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($patient_record['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($patient_record['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($patient_record['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo ($patient_record['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($patient_record['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo ($patient_record['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($patient_record['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" step="0.01" name="height" value="<?php echo htmlspecialchars($patient_record['height'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" step="0.01" name="weight" value="<?php echo htmlspecialchars($patient_record['weight'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Allergies</label>
                        <textarea name="allergies" placeholder="List any allergies you have..."><?php echo htmlspecialchars($patient_record['allergies'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Chronic Conditions</label>
                        <textarea name="chronic_conditions" placeholder="List any chronic conditions..."><?php echo htmlspecialchars($patient_record['chronic_conditions'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Medications</label>
                        <textarea name="current_medications" placeholder="List medications you are currently taking..."><?php echo htmlspecialchars($patient_record['current_medications'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Medical History</label>
                        <textarea name="medical_history" placeholder="Describe your medical history..."><?php echo htmlspecialchars($patient_record['medical_history'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Family History</label>
                        <textarea name="family_history" placeholder="Describe your family medical history..."><?php echo htmlspecialchars($patient_record['family_history'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_medical" class="btn">Update Medical Records</button>
                </form>
            </div>
            
            <!-- Emergency Contacts Tab -->
            <div id="emergency" class="tab-content">
                <h2 style="margin-bottom: 25px; color: #333;">Add Emergency Contact</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Contact Name *</label>
                            <input type="text" name="contact_name" required>
                        </div>
                        <div class="form-group">
                            <label>Relationship *</label>
                            <input type="text" name="relationship" placeholder="e.g., Spouse, Parent, Sibling" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="tel" name="contact_phone" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="contact_email">
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_primary" id="is_primary">
                        <label for="is_primary" style="margin-bottom: 0;">Set as primary contact</label>
                    </div>
                    
                    <button type="submit" name="add_emergency_contact" class="btn" style="margin-top: 15px;">Add Contact</button>
                </form>
                
                <div class="emergency-contacts-list">
                    <h2 style="margin-bottom: 20px; color: #333;">Your Emergency Contacts</h2>
                    <?php if (empty($emergency_contacts)): ?>
                        <p style="color: #6c757d;">No emergency contacts added yet.</p>
                    <?php else: ?>
                        <?php foreach ($emergency_contacts as $contact): ?>
                            <div class="contact-card">
                                <div class="contact-info">
                                    <h3>
                                        <?php echo htmlspecialchars($contact['contact_name']); ?>
                                        <?php if ($contact['is_primary']): ?>
                                            <span class="primary-badge">PRIMARY</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p><strong>Relationship:</strong> <?php echo htmlspecialchars($contact['relationship']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($contact['phone']); ?></p>
                                    <?php if ($contact['email']): ?>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($contact['email']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this contact?');">
                                    <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                    <button type="submit" name="delete_contact" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div id="security" class="tab-content">
                <h2 style="margin-bottom: 25px; color: #333;">Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" name="new_password" required minlength="8">
                        <small style="color: #6c757d;">Password must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn">Change Password</button>
                </form>
                
                <div style="margin-top: 40px; padding: 20px; background: #f8d7da; border-radius: 8px; border: 1px solid #f5c6cb;">
                    <h3 style="color: #721c24; margin-bottom: 10px;">Account Deactivation</h3>
                    <p style="color: #721c24; margin-bottom: 15px;">If you wish to deactivate your account, please contact support at support@healthhive.com</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openTab(evt, tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all buttons
            var tabButtons = document.getElementsByClassName('tab-button');
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            // Show the current tab and mark button as active
            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>