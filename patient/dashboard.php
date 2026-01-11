<?php
// patient/dashboard.php

// ✅ Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// ✅ Include required files with proper error handling
$includes_loaded = false;
try {
    if (file_exists(__DIR__ . '/../config/database.php')) {
        require_once __DIR__ . '/../config/database.php';
    }
    if (file_exists(__DIR__ . '/../includes/functions.php')) {
        require_once __DIR__ . '/../includes/functions.php';
        $includes_loaded = true;
    }
} catch (Exception $e) {
    error_log("Include error: " . $e->getMessage());
}

// ✅ Check authentication - redirect if not logged in
$demo_mode = false;
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // For demo purposes, you can enable demo mode
    // Remove this in production
    $demo_mode = true;
    $_SESSION['user_id'] = 1;
    $_SESSION['user_type'] = 'patient';
    $_SESSION['full_name'] = 'Demo Patient';
    $_SESSION['email'] = 'demo@patient.com';
} else {
    // Check if user is patient
    if ($_SESSION['user_type'] !== 'patient') {
        header('Location: ../index.php');
        exit();
    }
}

$user_id = $_SESSION['user_id'];

// ✅ Safe output function
function safe($v) { 
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); 
}

// ✅ Initialize counters
$medical_records_count = 0;
$symptoms_count = 0;
$appointments_count = 0;
$prescriptions_count = 0;
$use_db = false;

// ✅ Database connection check
if (isset($db) && $db instanceof PDO) {
    try {
        // Test connection
        $db->query("SELECT 1");
        $use_db = true;
    } catch (Exception $e) {
        error_log("Database test failed: " . $e->getMessage());
        $use_db = false;
    }
} elseif (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Use $pdo if $db is not available
        $db = $pdo;
        $db->query("SELECT 1");
        $use_db = true;
    } catch (Exception $e) {
        error_log("Database test failed: " . $e->getMessage());
        $use_db = false;
    }
}

// ✅ Fetch real data if database is available
if ($use_db && $db) {
    try {
        // Patient records count
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'patient_records'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM patient_records WHERE patient_id = ?");
                $stmt->execute([$user_id]);
                $medical_records_count = (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log("Error fetching patient records: " . $e->getMessage());
        }

        // Symptoms count
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'symptoms'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM symptoms WHERE patient_id = ?");
                $stmt->execute([$user_id]);
                $symptoms_count = (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log("Error fetching symptoms: " . $e->getMessage());
        }

        // Appointments count
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'appointments'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
                $stmt->execute([$user_id]);
                $appointments_count = (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log("Error fetching appointments: " . $e->getMessage());
        }

        // Prescriptions count
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'consultations'");
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM consultations WHERE patient_id = ? AND prescription IS NOT NULL AND prescription != ''");
                $stmt->execute([$user_id]);
                $prescriptions_count = (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            error_log("Error fetching prescriptions: " . $e->getMessage());
        }

    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        $use_db = false;
    }
}

// ✅ Demo data if database not available
if (!$use_db) {
    $medical_records_count = 5;
    $symptoms_count = 3;
    $appointments_count = 2;
    $prescriptions_count = 4;
}

// ✅ Get user info safely
$user_name = safe($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Patient');
$user_email = safe($_SESSION['email'] ?? 'patient@example.com');

$page_title = 'Patient Dashboard - HealthHive';

// ✅ Include header with error handling
$header_included = false;
try {
    if (file_exists(__DIR__ . '/../includes/header.php')) {
        include __DIR__ . '/../includes/header.php';
        $header_included = true;
    }
} catch (Exception $e) {
    error_log("Header include error: " . $e->getMessage());
}

// ✅ Fallback header if include failed
if (!$header_included) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $page_title . '</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>';
}
?>

<!-- ✅ Dashboard Content -->
<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-tachometer-alt text-primary me-2"></i>
                        Welcome, <?php echo explode(' ', $user_name)[0]; ?>!
                    </h1>
                    <p class="text-muted mb-0">Here's your health dashboard overview</p>
                    <?php if ($demo_mode): ?>
                        <small class="badge bg-warning text-dark">Demo Mode - Sample Data</small>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <p class="mb-0 text-muted small">Today</p>
                    <p class="mb-0 fw-bold"><?php echo date('M d, Y'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-medical fa-3x mb-3 opacity-75"></i>
                    <h2 class="mb-1"><?php echo safe($medical_records_count); ?></h2>
                    <p class="mb-0">Medical Records</p>
                </div>
                <div class="card-footer bg-primary bg-opacity-25 text-center">
                    <a href="view_records.php" class="text-white text-decoration-none">
                        <small>View All <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-thermometer-half fa-3x mb-3 opacity-75"></i>
                    <h2 class="mb-1"><?php echo safe($symptoms_count); ?></h2>
                    <p class="mb-0">Symptoms Logged</p>
                </div>
                <div class="card-footer bg-info bg-opacity-25 text-center">
                    <a href="add_symptoms.php" class="text-white text-decoration-none">
                        <small>Log New <i class="fas fa-plus"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-check fa-3x mb-3 opacity-75"></i>
                    <h2 class="mb-1"><?php echo safe($appointments_count); ?></h2>
                    <p class="mb-0">Appointments</p>
                </div>
                <div class="card-footer bg-success bg-opacity-25 text-center">
                    <a href="appointments.php" class="text-white text-decoration-none">
                        <small>Manage <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-prescription-bottle-alt fa-3x mb-3 opacity-75"></i>
                    <h2 class="mb-1"><?php echo safe($prescriptions_count); ?></h2>
                    <p class="mb-0">Prescriptions</p>
                </div>
                <div class="card-footer bg-warning bg-opacity-25 text-center">
                    <a href="prescriptions.php" class="text-white text-decoration-none">
                        <small>View All <i class="fas fa-arrow-right"></i></small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-bell text-warning me-2"></i>
                        Recent Notifications
                        <?php
                        // Get unread count
                        if ($use_db && $db) {
                            try {
                                $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                                $stmt->execute([$user_id]);
                                $unread = $stmt->fetchColumn();
                                if ($unread > 0) {
                                    echo '<span class="badge bg-danger ms-2">' . $unread . '</span>';
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching unread count: " . $e->getMessage());
                            }
                        }
                        ?>
                    </h5>
                    <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch recent notifications
                    if ($use_db && $db) {
                        try {
                            $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                            $stmt->execute([$user_id]);
                            $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($recent_notifications)) {
                                echo '<p class="text-muted mb-0 text-center py-3">
                                    <i class="fas fa-bell-slash me-2"></i>No notifications yet
                                </p>';
                            } else {
                                foreach ($recent_notifications as $notif) {
                                    $badge_class = $notif['is_read'] ? 'secondary' : 'primary';
                                    $priority_icon = '';
                                    if ($notif['priority'] === 'urgent') {
                                        $priority_icon = '<i class="fas fa-exclamation-circle text-danger me-1"></i>';
                                    } elseif ($notif['priority'] === 'high') {
                                        $priority_icon = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>';
                                    }
                                    
                                    echo '<div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">' . $priority_icon . htmlspecialchars($notif['title']) . ' 
                                                <span class="badge bg-' . $badge_class . ' ms-2">' . ($notif['is_read'] ? 'Read' : 'New') . '</span>
                                            </h6>
                                            <p class="text-muted small mb-1">' . htmlspecialchars($notif['message']) . '</p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>' . date('M d, Y g:i A', strtotime($notif['created_at'])) . '
                                            </small>';
                                    
                                    if ($notif['action_url']) {
                                        echo '<div class="mt-2">
                                                <a href="' . htmlspecialchars($notif['action_url']) . '" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-arrow-right me-1"></i>Take Action
                                                </a>
                                            </div>';
                                    }
                                    
                                    echo '</div>
                                    </div>';
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching notifications: " . $e->getMessage());
                            echo '<p class="text-muted mb-0 text-center">Unable to load notifications</p>';
                        }
                    } else {
                        echo '<p class="text-muted mb-0 text-center">Demo mode - no notifications available</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt text-warning me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <a href="add_symptoms.php" class="card text-decoration-none h-100 border-2 border-primary border-opacity-25 hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-thermometer-half fa-2x text-primary mb-3"></i>
                                    <h6 class="mb-1">Report Symptoms</h6>
                                    <p class="text-muted small mb-0">Log your health symptoms</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <a href="appointments.php" class="card text-decoration-none h-100 border-2 border-success border-opacity-25 hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-plus fa-2x text-success mb-3"></i>
                                    <h6 class="mb-1">Book Appointment</h6>
                                    <p class="text-muted small mb-0">Schedule with doctors</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <a href="view_records.php" class="card text-decoration-none h-100 border-2 border-info border-opacity-25 hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-medical fa-2x text-info mb-3"></i>
                                    <h6 class="mb-1">Medical Records</h6>
                                    <p class="text-muted small mb-0">View your health history</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <a href="profile.php" class="card text-decoration-none h-100 border-2 border-warning border-opacity-25 hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-edit fa-2x text-warning mb-3"></i>
                                    <h6 class="mb-1">Update Profile</h6>
                                    <p class="text-muted small mb-0">Manage your information</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Info & Logout -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?php echo $user_name; ?></h6>
                            <p class="text-muted mb-0"><?php echo $user_email; ?></p>
                            <small class="text-muted">Patient ID: #<?php echo str_pad($user_id, 6, '0', STR_PAD_LEFT); ?></small>
                        </div>
                        <div>
                            <a href="../auth/logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: all 0.3s ease;
}
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php 
// ✅ Include footer with error handling
try {
    if (file_exists(__DIR__ . '/../includes/footer.php')) {
        include __DIR__ . '/../includes/footer.php';
    }
} catch (Exception $e) {
    error_log("Footer include error: " . $e->getMessage());
    echo '</body></html>';
}
?>