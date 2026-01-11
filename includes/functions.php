<?php
// includes/functions.php

use Twilio\Rest\Client;

/*
 * Load Twilio credentials
 * Make sure you have a config/twilio.php with $TWILIO_SID, $TWILIO_TOKEN, $TWILIO_FROM
 */
@include_once __DIR__ . '/../config/twilio.php';

// Sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Generate random token
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Check user type
function check_user_type($required_type) {
    if (!is_logged_in()) {
        return false;
    }
    return $_SESSION['user_type'] === $required_type;
}

// Redirect to login if not authenticated
function require_auth($user_type = null) {
    if (!is_logged_in()) {
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }

    if ($user_type && !check_user_type($user_type)) {
        header('Location: ' . SITE_URL . 'index.php');
        exit();
    }
}

// Get user info from session safely
function get_user_info() {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'        => $_SESSION['user_id'] ?? '',
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'user_type' => $_SESSION['user_type'] ?? '',
        'email'     => $_SESSION['email'] ?? ''
    ];
}

// Format date
function format_date($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// Get time ago
function time_ago($datetime) {
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';

    return floor($time/31536000) . ' years ago';
}

// Send SMS using Twilio
function send_sms($to, $message) {
    global $TWILIO_SID, $TWILIO_TOKEN, $TWILIO_FROM;

    if (empty($TWILIO_SID) || empty($TWILIO_TOKEN) || empty($TWILIO_FROM)) {
        error_log("Twilio credentials not set. SMS not sent.");
        return false;
    }

    try {
        $client = new Client($TWILIO_SID, $TWILIO_TOKEN);
        $client->messages->create(
            $to,
            [
                'from' => $TWILIO_FROM,
                'body' => $message
            ]
        );
        return true;
    } catch (\Throwable $e) {
        error_log("Twilio SMS error: " . $e->getMessage());
        return false;
    }
}

// Send notification
function send_notification($user_id, $title, $message, $type = 'system', $priority = 'medium') {
    global $db;

    // Check if database connection exists
    if (!$db) {
        error_log("Database connection not available in send_notification()");
        return false;
    }

    try {
        $query = "INSERT INTO notifications (receiver_id, title, message, type, priority) 
                  VALUES (:user_id, :title, :message, :type, :priority)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':priority', $priority);
        $stmt->execute();

        // Optionally send SMS if user has phone number
        $stmt2 = $db->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt2->execute([$user_id]);
        $phone = $stmt2->fetchColumn();
        if ($phone) {
            send_sms($phone, $message);
        }

        return true;
    } catch(PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Log activity
function log_activity($user_id, $action, $details = '') {
    global $db;

    // Check if database connection exists
    if (!$db) {
        error_log("Database connection not available in log_activity()");
        return false;
    }

    try {
        $query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
                  VALUES (:user_id, :action, :details, :ip_address)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

// Upload file
function upload_file($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 10485760) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file uploaded'];
    }

    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if ($file_size > $max_size) {
        return ['error' => 'File size too large'];
    }

    if (!in_array($file_ext, $allowed_types)) {
        return ['error' => 'File type not allowed'];
    }

    $new_filename = uniqid() . '.' . $file_ext;
    $upload_path = UPLOAD_PATH . $new_filename;

    if (move_uploaded_file($file_tmp, $upload_path)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $upload_path];
    } else {
        return ['error' => 'Failed to upload file'];
    }
}

// Get system setting - FIXED VERSION
function get_setting($key, $default = '') {
    global $db;

    // Check if database connection exists
    if (!$db) {
        error_log("Database connection not available in get_setting()");
        return $default;
    }

    try {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = :key";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->execute();

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['setting_value'];
        }

        return $default;
    } catch(PDOException $e) {
        error_log("Database error in get_setting(): " . $e->getMessage());
        return $default;
    }
}

// Set system setting
function set_setting($key, $value, $type = 'string') {
    global $db;

    // Check if database connection exists
    if (!$db) {
        error_log("Database connection not available in set_setting()");
        return false;
    }

    try {
        $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                  VALUES (:key, :value, :type) 
                  ON DUPLICATE KEY UPDATE setting_value = :value";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        $stmt->bindParam(':type', $type);
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Database error in set_setting(): " . $e->getMessage());
        return false;
    }
}

// Calculate BMI
function calculate_bmi($height_cm, $weight_kg) {
    if ($height_cm <= 0 || $weight_kg <= 0) {
        return 0;
    }

    $height_m = $height_cm / 100;
    $bmi = $weight_kg / ($height_m * $height_m);

    return round($bmi, 2);
}

// Get BMI category
function get_bmi_category($bmi) {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal weight';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}

// Validate phone number
function validate_phone($phone) {
    return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
}

// Generate OTP
function generate_otp($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Check if string contains sensitive information
function contains_sensitive_info($text) {
    $patterns = [
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        '/\b\d{3}[\s-]?\d{2}[\s-]?\d{4}\b/',
        '/\b\d{16}\b/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }

    return false;
}

// Clean and validate symptom data
function validate_symptom_data($symptoms) {
    $symptoms = sanitize_input($symptoms);

    if (strlen($symptoms) < 10) {
        return ['error' => 'Please provide more detailed symptoms'];
    }

    if (contains_sensitive_info($symptoms)) {
        return ['error' => 'Please avoid including sensitive personal information'];
    }

    return ['success' => true, 'symptoms' => $symptoms];
}

// Multi-language support
function translate($key, $lang = 'en') {
    $translations = [
        'en' => [
            'welcome' => 'Welcome',
            'login' => 'Login',
            'register' => 'Register',
            'dashboard' => 'Dashboard',
            'symptoms' => 'Symptoms',
            'appointments' => 'Appointments',
            'profile' => 'Profile',
            'logout' => 'Logout'
        ],
        'es' => [
            'welcome' => 'Bienvenido',
            'login' => 'Iniciar sesión',
            'register' => 'Registrarse',
            'dashboard' => 'Panel de control',
            'symptoms' => 'Síntomas',
            'appointments' => 'Citas',
            'profile' => 'Perfil',
            'logout' => 'Cerrar sesión'
        ]
    ];

    return $translations[$lang][$key] ?? $key;
}

// Rate limiting
function check_rate_limit($user_id, $action, $limit = 5, $window = 3600) {
    global $db;

    // Check if database connection exists
    if (!$db) {
        error_log("Database connection not available in check_rate_limit()");
        return false;
    }

    try {
        $query = "SELECT COUNT(*) as count FROM rate_limits 
                  WHERE user_id = :user_id AND action = :action 
                  AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':window', $window, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row['count'] ?? 0) < $limit;
    } catch(PDOException $e) {
        error_log("Rate limit error: " . $e->getMessage());
        return false;
    }
}
?>