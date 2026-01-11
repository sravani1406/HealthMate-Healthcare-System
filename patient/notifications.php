<?php
// patient/notifications.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Use consistent database variable
if (isset($pdo)) {
    $db = $pdo;
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit();
}

$patient_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $patient_id]);
        $success = 'Notification marked as read';
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        $error = 'Failed to update notification';
    }
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$patient_id]);
        $success = 'All notifications marked as read';
    } catch (Exception $e) {
        error_log("Error marking all as read: " . $e->getMessage());
        $error = 'Failed to update notifications';
    }
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    
    try {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $patient_id]);
        $success = 'Notification deleted';
    } catch (Exception $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        $error = 'Failed to delete notification';
    }
}

// Fetch notifications from database
$notifications = [];
$unread_count = 0;

try {
    // Get all notifications
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$patient_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$patient_id]);
    $unread_count = (int)$stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}

// Function to get icon based on notification type
function getNotificationIcon($type) {
    $icons = [
        'appointment' => 'fa-calendar-check',
        'medication' => 'fa-pills',
        'checkup' => 'fa-stethoscope',
        'alert' => 'fa-exclamation-triangle',
        'system' => 'fa-info-circle'
    ];
    return $icons[$type] ?? 'fa-bell';
}

// Function to get color based on priority
function getNotificationColor($priority) {
    $colors = [
        'low' => 'secondary',
        'medium' => 'info',
        'high' => 'warning',
        'urgent' => 'danger'
    ];
    return $colors[$priority] ?? 'secondary';
}

$page_title = 'Notifications - HealthHive';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .notification-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .notification-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
        }
        .notification-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .notification-card.unread {
            background: #f8f9fa;
            border-left-color: #0d6efd;
        }
        .notification-card.urgent {
            border-left-color: #dc3545;
        }
        .notification-card.high {
            border-left-color: #ffc107;
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .notification-time {
            font-size: 12px;
            color: #6c757d;
        }
        .badge-unread {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        .filter-tabs {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .filter-tabs .btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="notification-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-bell text-primary me-2"></i>
                        Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge-unread"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0">Stay updated with your health alerts and reminders</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="btn btn-sm btn-outline-primary active" onclick="filterNotifications('all')">
                All
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="filterNotifications('unread')">
                Unread (<?php echo $unread_count; ?>)
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="filterNotifications('appointment')">
                Appointments
            </button>
            <button class="btn btn-sm btn-outline-warning" onclick="filterNotifications('medication')">
                Medications
            </button>
            <button class="btn btn-sm btn-outline-success" onclick="filterNotifications('checkup')">
                Checkups
            </button>
            
            <?php if ($unread_count > 0): ?>
                <form method="POST" class="d-inline float-end">
                    <button type="submit" name="mark_all_read" class="btn btn-sm btn-primary">
                        <i class="fas fa-check-double me-1"></i>Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No Notifications</h4>
                <p class="text-muted">You're all caught up! Check back later for updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?> <?php echo $notification['priority']; ?>"
                     data-type="<?php echo $notification['type']; ?>"
                     data-read="<?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="d-flex">
                        <div class="notification-icon bg-<?php echo getNotificationColor($notification['priority']); ?> bg-opacity-10 text-<?php echo getNotificationColor($notification['priority']); ?> me-3">
                            <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                        </div>
                        
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary">New</span>
                                        <?php endif; ?>
                                        <?php if ($notification['priority'] === 'urgent'): ?>
                                            <span class="badge bg-danger">Urgent</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-2 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small class="notification-time">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (!$notification['is_read']): ?>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" name="mark_read" class="dropdown-item">
                                                        <i class="fas fa-check me-2"></i>Mark as Read
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($notification['action_url']): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo htmlspecialchars($notification['action_url']); ?>">
                                                    <i class="fas fa-external-link-alt me-2"></i>View Details
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" name="delete_notification" class="dropdown-item text-danger" onclick="return confirm('Delete this notification?')">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($notification['action_url']): ?>
                                <div class="mt-2">
                                    <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-arrow-right me-1"></i>Take Action
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter notifications
        function filterNotifications(filter) {
            const cards = document.querySelectorAll('.notification-card');
            const buttons = document.querySelectorAll('.filter-tabs .btn');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active', 'btn-primary'));
            buttons.forEach(btn => btn.classList.add('btn-outline-primary'));
            event.target.classList.add('active', 'btn-primary');
            event.target.classList.remove('btn-outline-primary');
            
            cards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'block';
                } else if (filter === 'unread') {
                    card.style.display = card.dataset.read === 'unread' ? 'block' : 'none';
                } else {
                    card.style.display = card.dataset.type === filter ? 'block' : 'none';
                }
            });
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>