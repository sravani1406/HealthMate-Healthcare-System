<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['symptoms']) || empty($input['symptoms'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Symptoms are required']);
    exit();
}

try {
    // Prepare data for Python AI script
    $symptom_data = [
        'symptoms' => $input['symptoms'],
        'age' => $input['age'] ?? null,
        'gender' => $input['gender'] ?? null,
        'medical_history' => $input['medical_history'] ?? []
    ];
    
    // Call Python AI script
    $python_script = '../ai/symptom_analyzer.py';
    $command = "python3 $python_script '" . json_encode($symptom_data) . "'";
    $output = shell_exec($command);
    
    if ($output === null) {
        throw new Exception('AI analysis failed');
    }
    
    $analysis_result = json_decode($output, true);
    
    if ($analysis_result === null) {
        throw new Exception('Invalid AI response');
    }
    
    // Save analysis to database
    $stmt = $pdo->prepare("
        INSERT INTO ai_analyses (patient_id, symptoms, analysis_result, risk_level, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        json_encode($input['symptoms']),
        json_encode($analysis_result),
        $analysis_result['risk_level'] ?? 'unknown'
    ]);
    
    echo json_encode($analysis_result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Analysis failed: ' . $e->getMessage()]);
}
?>
