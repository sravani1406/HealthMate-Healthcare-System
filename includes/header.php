<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user_info = get_user_info();

// Get database connection if available
if (isset($pdo)) {
    $db = $pdo;
} elseif (isset($GLOBALS['db'])) {
    $db = $GLOBALS['db'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>HealthHive</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>assets/css/style.css" rel="stylesheet">
    
    <!-- Custom CSS for responsive design -->
    <style>
        .navbar-brand {
            font-weight: bold;
            color: #0d6efd !important;
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        .notification-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        .notification-item.unread {
            background: #e7f3ff;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-heartbeat me-2"></i>HealthHive
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (is_logged_in()): ?>
                    <ul class="navbar-nav me-auto">
                        <?php if ($user_info['user_type'] == 'patient'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>patient/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'add_symptoms' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>patient/add_symptoms.php">
                                    <i class="fas fa-notes-medical me-1"></i>Report Symptoms
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'view_records' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>patient/view_records.php">
                                    <i class="fas fa-file-medical me-1"></i>Medical Records
                                </a>
                            </li>
                        <?php elseif ($user_info['user_type'] == 'doctor'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>doctor/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'patient_list' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>doctor/patient_list.php">
                                    <i class="fas fa-users me-1"></i>Patients
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'consultation' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>doctor/consultation.php">
                                    <i class="fas fa-stethoscope me-1"></i>Consultations
                                </a>
                            </li>
                        <?php elseif ($user_info['user_type'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Admin Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $current_page == 'manage_users' ? 'active' : ''; ?>" 
                                   href="<?php echo SITE_URL; ?>admin/manage_users.php">
                                    <i class="fas fa-users-cog me-1"></i>Manage Users
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <!-- Notifications -->
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link position-relative" href="#" id="notificationDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php
                                // Get unread notification count
                                $unread_count = 0;
                                if (isset($db) && $db instanceof PDO) {
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $unread_count = (int)$stmt->fetchColumn();
                                    } catch (Exception $e) {
                                        error_log("Notification count error: " . $e->getMessage());
                                    }
                                }
                                
                                if ($unread_count > 0) {
                                    echo '<span class="notification-badge">' . $unread_count . '</span>';
                                }
                                ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php
                                // Fetch recent notifications
                                if (isset($db) && $db instanceof PDO) {
                                    try {
                                        $stmt = $db->prepare("
                                            SELECT id, title, message, type, priority, is_read, created_at 
                                            FROM notifications 
                                            WHERE user_id = ? 
                                            ORDER BY created_at DESC 
                                            LIMIT 5
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($notifications)) {
                                            echo '<li>
                                                <div class="text-center py-3">
                                                    <small class="text-muted">No notifications</small>
                                                </div>
                                            </li>';
                                        } else {
                                            foreach ($notifications as $notif) {
                                                $unread_class = $notif['is_read'] ? '' : 'unread';
                                                $priority_icon = '';
                                                if ($notif['priority'] === 'urgent') {
                                                    $priority_icon = '<i class="fas fa-exclamation-circle text-danger me-2"></i>';
                                                } elseif ($notif['priority'] === 'high') {
                                                    $priority_icon = '<i class="fas fa-exclamation-triangle text-warning me-2"></i>';
                                                }
                                                
                                                echo '<li>
                                                    <div class="notification-item ' . $unread_class . '">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <strong class="d-block">' . $priority_icon . htmlspecialchars($notif['title']) . '</strong>
                                                                <small class="text-muted d-block mt-1">' . 
                                                                    substr(htmlspecialchars($notif['message']), 0, 60) . 
                                                                    (strlen($notif['message']) > 60 ? '...' : '') . 
                                                                '</small>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-clock me-1"></i>' . 
                                                                    date('M j, g:i A', strtotime($notif['created_at'])) . 
                                                                '</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>';
                                            }
                                        }
                                    } catch (Exception $e) {
                                        echo '<li>
                                            <div class="text-center py-3">
                                                <small class="text-danger">Error loading notifications</small>
                                            </div>
                                        </li>';
                                    }
                                }
                                ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-center text-primary" 
                                       href="<?php echo SITE_URL . $user_info['user_type']; ?>/notifications.php">
                                        <i class="fas fa-eye me-2"></i>View all notifications
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- User Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                               id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (isset($user_info['profile_image']) && !empty($user_info['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL . 'assets/uploads/' . $user_info['profile_image']; ?>" 
                                         class="user-avatar me-2" alt="Profile">
                                <?php else: ?>
                                    <div class="user-avatar me-2 bg-primary d-flex align-items-center justify-content-center text-white">
                                        <?php echo strtoupper(substr($user_info['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="d-none d-md-inline"><?php echo $user_info['full_name']; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <h6 class="dropdown-header">
                                        <?php echo ucfirst($user_info['user_type']); ?> Account
                                    </h6>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL . $user_info['user_type']; ?>/profile.php">
                                        <i class="fas fa-user me-2"></i>Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL . $user_info['user_type']; ?>/settings.php">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>auth/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                <?php else: ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="<?php echo SITE_URL; ?>auth/register.php">Get Started</a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-0" role="alert">
            <div class="container">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0" role="alert">
            <div class="container">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show m-0" role="alert">
            <div class="container">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['warning']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="main-content">
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>