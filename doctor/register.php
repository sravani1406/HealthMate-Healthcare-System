<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'doctor') {
        header('Location: dashboard.php');
    } elseif ($_SESSION['user_type'] === 'patient') {
        header('Location: ../patient/dashboard.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit();
}

// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $specialization = trim($_POST['specialization']);
    $license_number = trim($_POST['license_number']);
    $qualification = trim($_POST['qualification']);
    $experience_years = intval($_POST['experience_years']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    
    // Validation
    if (empty($full_name) || empty($email) || empty($username) || empty($password) || 
        empty($phone) || empty($specialization) || empty($license_number)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Check if username already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already taken';
                } else {
                    // Check if license number already exists
                    $stmt = $db->prepare("SELECT id FROM doctors WHERE license_number = ?");
                    $stmt->execute([$license_number]);
                    if ($stmt->fetch()) {
                        $error = 'License number already registered';
                    } else {
                        // Begin transaction
                        $db->beginTransaction();
                        
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert into users table (account pending verification, so is_active = 0)
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password, full_name, phone, user_type, is_active) 
                            VALUES (?, ?, ?, ?, ?, 'doctor', 0)
                        ");
                        $stmt->execute([$username, $email, $hashed_password, $full_name, $phone]);
                        
                        // Get the inserted user ID
                        $user_id = $db->lastInsertId();
                        
                        // Insert into doctors table
                        $stmt = $db->prepare("
                            INSERT INTO doctors (user_id, license_number, specialization, qualification, experience_years, consultation_fee, is_verified) 
                            VALUES (?, ?, ?, ?, ?, ?, 0)
                        ");
                        $stmt->execute([$user_id, $license_number, $specialization, $qualification, $experience_years, $consultation_fee]);
                        
                        // Commit transaction
                        $db->commit();
                        
                        $success = 'Registration successful! Your account is pending admin approval. You will be notified once your account is verified.';
                    }
                }
            }
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration - HealthHive</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        
        .register-container h2 {
            color: #667eea;
            margin-bottom: 10px;
            text-align: center;
            font-size: 28px;
        }
        
        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        button[type="submit"] {
            width: 100%;
            background: #667eea;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        button[type="submit"]:hover {
            background: #5568d3;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 600px) {
            .register-container {
                padding: 30px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>🩺 Doctor Registration</h2>
        <p class="subtitle">Join HealthHive as a verified medical professional</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name">Full Name <span class="required">*</span></label>
                <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
            </div>
            
            <div class="form-group">
                <label for="license_number">Medical License Number <span class="required">*</span></label>
                <input type="text" id="license_number" name="license_number" required value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="specialization">Specialization <span class="required">*</span></label>
                <select id="specialization" name="specialization" required>
                    <option value="">Select Specialization</option>
                    <option value="General Practice" <?php echo ($_POST['specialization'] ?? '') === 'General Practice' ? 'selected' : ''; ?>>General Practice</option>
                    <option value="Cardiology" <?php echo ($_POST['specialization'] ?? '') === 'Cardiology' ? 'selected' : ''; ?>>Cardiology</option>
                    <option value="Dermatology" <?php echo ($_POST['specialization'] ?? '') === 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                    <option value="Neurology" <?php echo ($_POST['specialization'] ?? '') === 'Neurology' ? 'selected' : ''; ?>>Neurology</option>
                    <option value="Pediatrics" <?php echo ($_POST['specialization'] ?? '') === 'Pediatrics' ? 'selected' : ''; ?>>Pediatrics</option>
                    <option value="Psychiatry" <?php echo ($_POST['specialization'] ?? '') === 'Psychiatry' ? 'selected' : ''; ?>>Psychiatry</option>
                    <option value="Orthopedics" <?php echo ($_POST['specialization'] ?? '') === 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                    <option value="Gynecology" <?php echo ($_POST['specialization'] ?? '') === 'Gynecology' ? 'selected' : ''; ?>>Gynecology</option>
                    <option value="ENT" <?php echo ($_POST['specialization'] ?? '') === 'ENT' ? 'selected' : ''; ?>>ENT</option>
                    <option value="Ophthalmology" <?php echo ($_POST['specialization'] ?? '') === 'Ophthalmology' ? 'selected' : ''; ?>>Ophthalmology</option>
                    <option value="Other" <?php echo ($_POST['specialization'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="qualification">Qualification</label>
                <input type="text" id="qualification" name="qualification" placeholder="e.g., MBBS, MD" value="<?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="experience_years">Years of Experience</label>
                    <input type="number" id="experience_years" name="experience_years" min="0" value="<?php echo htmlspecialchars($_POST['experience_years'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="consultation_fee">Consultation Fee ($)</label>
                    <input type="number" id="consultation_fee" name="consultation_fee" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>">
                </div>
            </div>
            
            <button type="submit">Register</button>
        </form>
        
        <div class="links">
            <p>Already have an account? <a href="login.php">Login here</a></p>
            <p><a href="../index.php">← Back to Home</a></p>
        </div>
    </div>
</body>
</html>