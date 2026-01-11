<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['user_type'] === 'doctor' ? '../doctor/dashboard.php' : 
                ($_SESSION['user_type'] === 'patient' ? '../patient/dashboard.php' : '../admin/dashboard.php');
    header('Location: ' . $redirect);
    exit();
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
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? null;
    
    // Validation
    if (empty($full_name) || empty($email) || empty($username) || empty($password) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email is already registered.';
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username is already taken.';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new patient user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, full_name, phone, user_type, date_of_birth, gender, is_active) 
                        VALUES (?, ?, ?, ?, ?, 'patient', ?, ?, 1)
                    ");
                    
                    if ($stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $date_of_birth, $gender])) {
                        $user_id = $pdo->lastInsertId();
                        
                        // Create empty patient record
                        $stmt = $pdo->prepare("INSERT INTO patient_records (patient_id) VALUES (?)");
                        $stmt->execute([$user_id]);
                        
                        $success = 'Registration successful! You can now login.';
                        
                        // Auto-login after registration (optional)
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['email'] = $email;
                        $_SESSION['user_type'] = 'patient';
                        
                        // Redirect to patient dashboard after 2 seconds
                        header('refresh:2;url=../patient/dashboard.php');
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - HealthHive</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            margin: 20px auto;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 36px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #6c757d;
            font-size: 14px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            font-size: 24px;
        }
        
        .subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
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
        
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #6c757d;
        }
        
        button[type="submit"] {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 10px;
        }
        
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        button[type="submit"]:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .back-home {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-home a {
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-home a:hover {
            color: #667eea;
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
        <div class="logo">
            <h1>🏥 HealthHive</h1>
            <p>Your Health, Our Priority</p>
        </div>
        
        <h2>Create Patient Account</h2>
        <p class="subtitle">Join HealthHive and start managing your health</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
                <br><small>Redirecting to dashboard...</small>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="full_name">Full Name <span class="required">*</span></label>
                <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" required placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required placeholder="+1234567890" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required placeholder="your.email@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Create a strong password">
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                </div>
                <div class="password-requirements" id="passwordStrength">
                    Must be at least 8 characters with uppercase, lowercase, and number
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-enter your password">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                </div>
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
        
        <div class="login-link">
            Are you a doctor? <a href="../doctor/register.php">Register as Doctor</a>
        </div>
        
        <div class="back-home">
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleBtn = passwordInput.parentElement.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (password.length === 0) {
                strengthDiv.style.color = '#6c757d';
                strengthDiv.textContent = 'Must be at least 8 characters with uppercase, lowercase, and number';
            } else if (strength < 3) {
                strengthDiv.style.color = '#dc3545';
                strengthDiv.textContent = '❌ Weak password - add more characters';
            } else if (strength < 4) {
                strengthDiv.style.color = '#ffc107';
                strengthDiv.textContent = '⚠️ Medium strength - consider adding special characters';
            } else {
                strengthDiv.style.color = '#28a745';
                strengthDiv.textContent = '✓ Strong password';
            }
        });
        
        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = e.target.value;
            
            if (confirmPassword && password !== confirmPassword) {
                e.target.setCustomValidity('Passwords do not match');
                e.target.style.borderColor = '#dc3545';
            } else if (confirmPassword && password === confirmPassword) {
                e.target.setCustomValidity('');
                e.target.style.borderColor = '#28a745';
            } else {
                e.target.setCustomValidity('');
                e.target.style.borderColor = '#e9ecef';
            }
        });
        
        // Form submission validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';
        });
    </script>
</body>
</html>