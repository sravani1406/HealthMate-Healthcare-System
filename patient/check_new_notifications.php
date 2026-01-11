<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'patient') {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$patient_id = $_SESSION['user_id'];
$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));

try {
    // Count new unread notifications since last check
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_count
        FROM notifications
        WHERE user_id = ? 
        AND user_type = 'patient' 
        AND is_read = 0 
        AND created_at > ?
    ");
    $stmt->execute([$patient_id, $last_check]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_unread
        FROM notifications
        WHERE user_id = ? 
        AND user_type = 'patient' 
        AND is_read = 0
    ");
    $stmt->execute([$patient_id]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'has_new' => $result['new_count'] > 0,
        'new_count' => (int)$result['new_count'],
        'total_unread' => (int)$total['total_unread']
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
?>