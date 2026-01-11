<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    switch ($method) {
        case 'GET':
            // Get notifications for user
            $stmt = $pdo->prepare("
                SELECT n.*, u.name as sender_name 
                FROM notifications n 
                LEFT JOIN users u ON n.sender_id = u.id 
                WHERE n.receiver_id = ? 
                ORDER BY n.created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['notifications' => $notifications]);
            break;
            
        case 'POST':
            // Create new notification
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['receiver_id']) || !isset($input['message'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (sender_id, receiver_id, message, type, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $input['receiver_id'],
                $input['message'],
                $input['type'] ?? 'general'
            ]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'PUT':
            // Mark notification as read
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['notification_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing notification ID']);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND receiver_id = ?
            ");
            $stmt->execute([$input['notification_id'], $user_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>