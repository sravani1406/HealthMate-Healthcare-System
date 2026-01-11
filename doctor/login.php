<?php
// doctor/login.php

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

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'doctor') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            // Query users table with doctor type and check if verified
            $stmt = $db->prepare("
                SELECT u.*, d.is_verified 
                FROM users u
                INNER JOIN doctors d ON u.id = d.user_id
                WHERE u.email = ? 
                AND u.user_type = 'doctor' 
                AND u.is_active = 1
                AND d.is_verified = 1
            ");
            $stmt->execute([$email]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doctor && password_verify($password, $doctor['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $doctor['id'];
                $_SESSION['user_type'] = 'doctor';
                $_SESSION['username'] = $doctor['username'];
                $_SESSION['full_name'] = $doctor['full_name'];
                $_SESSION['email'] = $doctor['email'];
                
                // Log activity
                log_activity($doctor['id'], 'login', 'Doctor login');
                
                header('Location: dashboard.php');
                exit();
            } else {
                // Check if doctor exists but not verified
                $stmt = $db->prepare("
                    SELECT u.*, d.is_verified 
                    FROM users u
                    LEFT JOIN doctors d ON u.id = d.user_id
                    WHERE u.email = ? AND u.user_type = 'doctor'
                ");
                $stmt->execute([$email]);
                $check_doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($check_doctor && !$check_doctor['is_active']) {
                    $error = 'Your account is pending admin approval. Please wait for verification.';
                } elseif ($check_doctor && !$check_doctor['is_verified']) {
                    $error = 'Your account is pending admin approval. Please wait for verification.';
                } else {
                    $error = 'Invalid email or password';
                }
            }
        } catch (PDOException $e) {
            error_log("Doctor login error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Login - HealthHive</title>
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
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        
        .login-container h2 {
            color: #28a745;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #28a745;
        }
        
        button[type="submit"] {
            width: 100%;
            background: #28a745;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button[type="submit"]:hover {
            background: #218838;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        
        .links a {
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Doctor Login</h2>
        <p class="subtitle">Access your healthcare professional dashboard</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="info-box">
            <strong>Note:</strong> Your account must be approved by an administrator before you can login. If you just registered, please wait for admin verification.
        </div>
        
        <div class="links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="../index.php">Back to Home</a></p>
        </div>
    </div>
</body>
</html>