class AIInterface {
    private $python_path;
    private $scripts_path;
    
    public function __construct() {
        $this->python_path = 'python3'; // or full path to python
        $this->scripts_path = __DIR__;
    }
    
    public function analyzeSymptoms($symptoms_data, $age, $gender, $medical_history = '') {
        try {
            $input_data = [
                'symptoms' => $symptoms_data,
                'age' => $age,
                'gender' => $gender,
                'medical_history' => $medical_history
            ];
            
            $script_path = $this->scripts_path . '/symptom_analyzer.py';
            $command = escapeshellcmd($this->python_path . ' ' . $script_path . ' ' . escapeshellarg(json_encode($input_data)));
            
            $output = shell_exec($command);
            
            if ($output === null) {
                throw new Exception('Failed to execute AI analysis script');
            }
            
            $result = json_decode(trim($output), true);
            
            if ($result === null) {
                throw new Exception('Invalid response from AI analysis script');
            }
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'error' => 'AI analysis failed: ' . $e->getMessage(),
                'risk_level' => 'unknown',
                'recommendations' => ['Please consult a healthcare provider for proper evaluation.']
            ];
        }
    }
    
    public function predictHealthRisks($patient_data) {
        try {
            $script_path = $this->scripts_path . '/risk_predictor.py';
            $command = escapeshellcmd($this->python_path . ' ' . $script_path . ' ' . escapeshellarg(json_encode($patient_data)));
            
            $output = shell_exec($command);
            
            if ($output === null) {
                throw new Exception('Failed to execute risk prediction script');
            }
            
            $result = json_decode(trim($output), true);
            
            if ($result === null) {
                throw new Exception('Invalid response from risk prediction script');
            }
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'error' => 'Risk prediction failed: ' . $e->getMessage(),
                'overall_risk_score' => 0.0,
                'risk_category' => 'unknown',
                'predicted_risks' => [],
                'prevention_recommendations' => ['Please consult a healthcare provider for proper risk assessment.']
            ];
        }
    }
    
    public function getPatientHistoryForPrediction($patient_id) {
        global $pdo;
        
        try {
            // Get patient basic info
            $stmt = $pdo->prepare("SELECT age, gender, medical_history FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                throw new Exception('Patient not found');
            }
            
            // Get symptom history
            $stmt = $pdo->prepare("
                SELECT symptoms, analysis_result, risk_level, created_at 
                FROM ai_analyses 
                WHERE patient_id = ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([$patient_id]);
            $symptom_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get current risk level (most recent analysis)
            $current_risk = 'low';
            if (!empty($symptom_history)) {
                $current_risk = $symptom_history[0]['risk_level'];
            }
            
            return [
                'age' => $patient['age'],
                'gender' => $patient['gender'],
                'medical_history' => $patient['medical_history'],
                'symptom_history' => $symptom_history,
                'current_risk_level' => $current_risk
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to get patient history: ' . $e->getMessage());
        }
    }
    
    public function processSymptomAnalysis($patient_id, $symptoms_data, $additional_info = '') {
        global $pdo;
        
        try {
            // Get patient info
            $stmt = $pdo->prepare("SELECT age, gender, medical_history FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                throw new Exception('Patient not found');
            }
            
            // Combine medical history with additional info
            $full_history = trim($patient['medical_history'] . ' ' . $additional_info);
            
            // Analyze symptoms
            $analysis_result = $this->analyzeSymptoms(
                $symptoms_data, 
                $patient['age'], 
                $patient['gender'], 
                $full_history
            );
            
            // Save analysis to database
            $stmt = $pdo->prepare("
                INSERT INTO ai_analyses (patient_id, symptoms, analysis_result, risk_level, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $symptoms_json = json_encode($symptoms_data);
            $analysis_json = json_encode($analysis_result);
            $risk_level = $analysis_result['risk_level'] ?? 'unknown';
            
            $stmt->execute([$patient_id, $symptoms_json, $analysis_json, $risk_level]);
            
            $analysis_id = $pdo->lastInsertId();
            
            // If high risk, create notification for assigned doctor
            if (in_array($risk_level, ['high', 'critical'])) {
                $this->createHighRiskNotification($patient_id, $risk_level, $analysis_result);
            }
            
            return [
                'analysis_id' => $analysis_id,
                'result' => $analysis_result
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to process symptom analysis: ' . $e->getMessage());
        }
    }
    
    private function createHighRiskNotification($patient_id, $risk_level, $analysis_result) {
        global $pdo;
        
        try {
            // Get patient and doctor info
            $stmt = $pdo->prepare("
                SELECT p.name as patient_name, p.assigned_doctor_id, d.name as doctor_name 
                FROM patients p 
                LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$patient_id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($info['assigned_doctor_id']) {
                $message = "HIGH RISK ALERT: Patient {$info['patient_name']} has been flagged with {$risk_level} risk level. ";
                
                if (isset($analysis_result['possible_conditions']) && !empty($analysis_result['possible_conditions'])) {
                    $conditions = implode(', ', array_slice($analysis_result['possible_conditions'], 0, 2));
                    $message .= "Possible conditions: {$conditions}. ";
                }
                
                $message .= "Please review immediately.";
                
                // Create notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (sender_id, receiver_id, message, type, created_at) 
                    VALUES (?, ?, ?, 'high_risk_alert', NOW())
                ");
                $stmt->execute([null, $info['assigned_doctor_id'], $message]);
            }
            
        } catch (Exception $e) {
            error_log('Failed to create high risk notification: ' . $e->getMessage());
        }
    }
    
    public function generateHealthReport($patient_id) {
        try {
            // Get patient data for prediction
            $patient_data = $this->getPatientHistoryForPrediction($patient_id);
            
            // Get risk prediction
            $risk_prediction = $this->predictHealthRisks($patient_data);
            
            // Get recent analyses summary
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT 
                    risk_level,
                    COUNT(*) as count,
                    MAX(created_at) as latest_date
                FROM ai_analyses 
                WHERE patient_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY risk_level
            ");
            $stmt->execute([$patient_id]);
            $recent_risk_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get most common symptoms in last 30 days
            $stmt = $pdo->prepare("
                SELECT symptoms, created_at 
                FROM ai_analyses 
                WHERE patient_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$patient_id]);
            $recent_analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $symptom_frequency = [];
            foreach ($recent_analyses as $analysis) {
                $symptoms = json_decode($analysis['symptoms'], true);
                if ($symptoms) {
                    foreach ($symptoms as $symptom) {
                        $symptom_name = $symptom['symptom'] ?? $symptom;
                        $symptom_frequency[$symptom_name] = ($symptom_frequency[$symptom_name] ?? 0) + 1;
                    }
                }
            }
            
            arsort($symptom_frequency);
            $common_symptoms = array_slice(array_keys($symptom_frequency), 0, 5);
            
            return [
                'patient_data' => $patient_data,
                'risk_prediction' => $risk_prediction,
                'recent_risk_summary' => $recent_risk_summary,
                'common_symptoms' => $common_symptoms,
                'symptom_frequency' => $symptom_frequency,
                'report_generated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'Failed to generate health report: ' . $e->getMessage()
            ];
        }
    }
    
    public function validateSymptomInput($symptoms_data) {
        $errors = [];
        
        if (!is_array($symptoms_data) || empty($symptoms_data)) {
            $errors[] = 'At least one symptom is required';
            return $errors;
        }
        
        $valid_severities = ['mild', 'moderate', 'severe'];
        $valid_durations = ['recent', 'few_hours', 'today', 'few_days', 'week', 'weeks', 'months'];
        
        foreach ($symptoms_data as $index => $symptom) {
            if (!isset($symptom['symptom']) || empty(trim($symptom['symptom']))) {
                $errors[] = "Symptom #" . ($index + 1) . " is missing or empty";
                continue;
            }
            
            if (isset($symptom['severity']) && !in_array($symptom['severity'], $valid_severities)) {
                $errors[] = "Invalid severity for symptom: " . $symptom['symptom'];
            }
            
            if (isset($symptom['duration']) && !in_array($symptom['duration'], $valid_durations)) {
                $errors[] = "Invalid duration for symptom: " . $symptom['symptom'];
            }
        }
        
        return $errors;
    }
}

// Example usage and testing functions
function testAIInterface() {
    $ai = new AIInterface();
    
    // Test symptom analysis
    $test_symptoms = [
        [
            'symptom' => 'fever',
            'severity' => 'moderate',
            'duration' => 'few_days'
        ],
        [
            'symptom' => 'headache',
            'severity' => 'severe',
            'duration' => 'today'
        ]
    ];
    
    $result = $ai->analyzeSymptoms($test_symptoms, 35, 'male', 'No significant medical history');
    
    echo "Symptom Analysis Test:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test risk prediction
    $test_patient_data = [
        'age' => 45,
        'gender' => 'female',
        'medical_history' => 'Hypertension, diabetes',
        'current_risk_level' => 'medium',
        'symptom_history' => []
    ];
    
    $risk_result = $ai->predictHealthRisks($test_patient_data);
    
    echo "Risk Prediction Test:\n";
    echo json_encode($risk_result, JSON_PRETTY_PRINT) . "\n";
}

// Uncomment to test
// testAIInterface();

?>
                <div class="no-analysis">
                    <p>No health analysis yet. <a href="add_symptoms.php">Add your symptoms</a> to get started.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Doctor Information -->
        <div class="dashboard-card doctor-info">
            <h2>Your Doctor</h2>
            <?php if ($patient['doctor_name']): ?>
                <div class="doctor-details">
                    <h3><?php echo htmlspecialchars($patient['doctor_name']); ?></h3>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($patient['specialization']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['doctor_phone']); ?></p>
                </div>
            <?php else: ?>
                <p>No doctor assigned yet. Please contact admin for assignment.</p>
            <?php endif; ?>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="dashboard-card appointments">
            <h2>Upcoming Appointments</h2>
            <?php if (!empty($upcoming_appointments)): ?>
                <div class="appointments-list">
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <div class="appointment-date">
                                <?php echo date('M j, Y', strtotime($appointment['follow_up_date'])); ?>
                            </div>
                            <div class="appointment-doctor">
                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No upcoming appointments.</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Notifications -->
        <div class="dashboard-card notifications">
            <h2>Recent Notifications</h2>
            <?php if (!empty($notifications)): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="notifications.php" class="view-all">View all notifications</a>
            <?php else: ?>
                <p>No notifications.</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Analyses -->
        <div class="dashboard-card recent-analyses">
            <h2>Recent Health Analyses</h2>
            <?php if (!empty($recent_analyses)): ?>
                <div class="analyses-list">
                    <?php foreach (array_slice($recent_analyses, 0, 3) as $analysis): ?>
                        <div class="analysis-item">
                            <div class="analysis-risk <?php echo $analysis['risk_level']; ?>">
                                <?php echo ucfirst($analysis['risk_level']); ?>
                            </div>
                            <div class="analysis-date">
                                <?php echo date('M j, Y', strtotime($analysis['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No analyses yet. Start by adding your symptoms.</p>
            <?php endif; ?>
        </div>
    </div>
</div>