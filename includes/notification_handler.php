<?php
// includes/notification_handler.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_notifications':
            // Get unread notification count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $unread_count = $stmt->fetchColumn();
            
            // Get recent notifications (last 10)
            $stmt = $pdo->prepare("
                SELECT id, title, message, type, priority, is_read, action_url, created_at
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'unread_count' => $unread_count,
                'notifications' => $notifications
            ]);
            break;
            
        case 'mark_as_read':
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_as_read':
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_notification':
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>