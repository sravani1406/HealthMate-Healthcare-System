<?php
require_once '../config/database.php';
require_once '../vendor/autoload.php'; // Twilio SDK

use Twilio\Rest\Client;

class TwilioHandler {
    private $client;
    private $from_number;
    
    public function __construct() {
        $account_sid = getenv('TWILIO_ACCOUNT_SID') ?: 'your_account_sid';
        $auth_token = getenv('TWILIO_AUTH_TOKEN') ?: 'your_auth_token';
        $this->from_number = getenv('TWILIO_PHONE_NUMBER') ?: 'your_twilio_number';
        
        $this->client = new Client($account_sid, $auth_token);
    }
    
    public function sendSMS($to, $message) {
        try {
            $this->client->messages->create(
                $to,
                [
                    'from' => $this->from_number,
                    'body' => $message
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log('Twilio SMS Error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function sendEmergencyAlert($patient_id, $risk_level) {
        global $pdo;
        
        // Get patient and emergency contacts
        $stmt = $pdo->prepare("
            SELECT p.name, p.phone, p.emergency_contact, d.name as doctor_name, d.phone as doctor_phone
            FROM patients p 
            LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) return false;
        
        $message = "HEALTH ALERT: {$patient['name']} has been flagged with {$risk_level} risk level. Please check immediately.";
        
        // Send to emergency contact
        if ($patient['emergency_contact']) {
            $this->sendSMS($patient['emergency_contact'], $message);
        }
        
        // Send to assigned doctor
        if ($patient['doctor_phone']) {
            $this->sendSMS($patient['doctor_phone'], $message);
        }
        
        return true;
    }
}

// Handle incoming webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        $twilio = new TwilioHandler();
        
        switch ($input['action']) {
            case 'send_emergency_alert':
                $result = $twilio->sendEmergencyAlert($input['patient_id'], $input['risk_level']);
                echo json_encode(['success' => $result]);
                break;
                
            case 'send_sms':
                $result = $twilio->sendSMS($input['to'], $input['message']);
                echo json_encode(['success' => $result]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
    }
}
?>
