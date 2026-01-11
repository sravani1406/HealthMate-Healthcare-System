<?php
// patient/dashboard_debug.php - USE THIS TO DEBUG YOUR ISSUES

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

echo '<h2>Session Debug Information</h2>';
echo '<pre>';
echo '<strong>Session Data:</strong>' . "\n";
print_r($_SESSION);
echo "\n<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive');
echo "\n<strong>Session ID:</strong> " . session_id();
echo '</pre>';

// Check database connection
require_once __DIR__ . '/../config/database.php';

if (isset($pdo)) {
    $db = $pdo;
}

echo '<h2>Database Connection</h2>';
echo '<pre>';
try {
    $db->query("SELECT 1");
    echo "✓ Database connection: <strong style='color:green'>SUCCESS</strong>\n";
    
    // Check if user exists
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\n<strong>User Query Result:</strong>\n";
        if ($user) {
            echo "✓ User found in database\n";
            echo "  - ID: " . $user['id'] . "\n";
            echo "  - Name: " . $user['full_name'] . "\n";
            echo "  - Email: " . $user['email'] . "\n";
            echo "  - Type: " . $user['user_type'] . "\n";
        } else {
            echo "✗ User ID " . $_SESSION['user_id'] . " NOT FOUND in database\n";
        }
        
        // Check notifications
        echo "\n<strong>Notifications Check:</strong>\n";
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $notif_count = $stmt->fetchColumn();
        echo "Total notifications for user: " . $notif_count . "\n";
        
        if ($notif_count > 0) {
            $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? LIMIT 3");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "\nSample notifications:\n";
            foreach ($notifications as $n) {
                echo "  - " . $n['title'] . " (Created: " . $n['created_at'] . ")\n";
            }
        }
    } else {
        echo "✗ No user_id in session\n";
    }
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
echo '</pre>';

echo '<h2>Diagnosis</h2>';
echo '<pre>';

if (!isset($_SESSION['user_id'])) {
    echo "❌ PROBLEM: No user_id in session\n";
    echo "   SOLUTION: Your login is not setting the session correctly.\n";
    echo "   CHECK: auth/login.php - make sure it sets \$_SESSION['user_id']\n";
} elseif (!isset($_SESSION['full_name'])) {
    echo "⚠ WARNING: user_id exists but no full_name in session\n";
    echo "   SOLUTION: Add \$_SESSION['full_name'] in your login script\n";
} else {
    echo "✓ Session appears to be set correctly\n";
}

echo '</pre>';

echo '<hr>';
echo '<a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a> ';
echo '<a href="../auth/logout.php" class="btn btn-danger">Logout</a>';
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px; }
pre { background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; }
.btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; }
.btn-primary { background: #007bff; color: white; }
.btn-danger { background: #dc3545; color: white; }
</style>