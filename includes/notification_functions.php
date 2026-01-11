<?php
// includes/notification_functions.php
// Helper functions to create notifications

/**
 * Create a notification for a user
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID to send notification to
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Type: appointment, medication, checkup, alert, system
 * @param string $priority Priority: low, medium, high, urgent
 * @param string $action_url Optional URL to navigate when clicked
 * @param bool $action_required Whether action is required
 * @return bool Success status
 */
function createNotification($pdo, $user_id, $title, $message, $type = 'system', $priority = 'medium', $action_url = null, $action_required = false) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, priority, action_url, action_required, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $user_id,
            $title,
            $message,
            $type,
            $priority,
            $action_url,
            $action_required ? 1 : 0
        ]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send appointment confirmation notification
 */
function notifyAppointmentBooked($pdo, $patient_id, $doctor_id, $consultation_date, $consultation_id) {
    // Get doctor name
    $stmt = $pdo->prepare("SELECT u.full_name FROM users u JOIN doctors d ON u.id = d.user_id WHERE d.id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    $doctor_name = $doctor ? $doctor['full_name'] : 'Doctor';
    
    // Notify patient
    createNotification(
        $pdo,
        $patient_id,
        'Appointment Confirmed',
        "Your appointment with Dr. {$doctor_name} has been scheduled for " . date('M j, Y g:i A', strtotime($consultation_date)),
        'appointment',
        'high',
        '../patient/consultations.php',
        false
    );
    
    // Notify doctor
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_name = $patient ? $patient['full_name'] : 'Patient';
    
    createNotification(
        $pdo,
        $doctor_id,
        'New Appointment',
        "New appointment scheduled with {$patient_name} on " . date('M j, Y g:i A', strtotime($consultation_date)),
        'appointment',
        'high',
        '../doctor/update_records.php?consultation_id=' . $consultation_id,
        true
    );
}

/**
 * Send appointment reminder (24 hours before)
 */
function notifyAppointmentReminder($pdo, $user_id, $consultation_date, $doctor_or_patient_name) {
    createNotification(
        $pdo,
        $user_id,
        'Appointment Reminder',
        "Reminder: You have an appointment with {$doctor_or_patient_name} tomorrow at " . date('g:i A', strtotime($consultation_date)),
        'appointment',
        'high',
        null,
        false
    );
}

/**
 * Send high-risk symptom alert to doctor
 */
function notifyHighRiskSymptom($pdo, $doctor_id, $patient_id, $symptom_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_name = $patient ? $patient['full_name'] : 'Patient';
    
    createNotification(
        $pdo,
        $doctor_id,
        '⚠️ High Risk Alert',
        "Patient {$patient_name} has reported high-risk symptoms. Immediate attention required.",
        'alert',
        'urgent',
        '../doctor/update_records.php',
        true
    );
}

/**
 * Send medication reminder
 */
function notifyMedicationReminder($pdo, $patient_id, $medication_name, $dosage, $time) {
    createNotification(
        $pdo,
        $patient_id,
        '💊 Medication Reminder',
        "Time to take {$medication_name} - {$dosage}",
        'medication',
        'high',
        '../patient/medications.php',
        true
    );
}

/**
 * Send prescription notification
 */
function notifyNewPrescription($pdo, $patient_id, $doctor_name, $consultation_id) {
    createNotification(
        $pdo,
        $patient_id,
        'New Prescription',
        "Dr. {$doctor_name} has prescribed new medication for you. Please review your prescription.",
        'medication',
        'high',
        '../patient/consultations.php?id=' . $consultation_id,
        true
    );
}

/**
 * Send consultation completed notification
 */
function notifyConsultationCompleted($pdo, $patient_id, $doctor_name, $consultation_id) {
    createNotification(
        $pdo,
        $patient_id,
        'Consultation Completed',
        "Your consultation with Dr. {$doctor_name} has been completed. View your diagnosis and prescription.",
        'checkup',
        'medium',
        '../patient/consultations.php?id=' . $consultation_id,
        false
    );
}

/**
 * Send follow-up reminder
 */
function notifyFollowUp($pdo, $patient_id, $doctor_name, $follow_up_date) {
    createNotification(
        $pdo,
        $patient_id,
        'Follow-up Reminder',
        "You have a follow-up appointment with Dr. {$doctor_name} scheduled for " . date('M j, Y', strtotime($follow_up_date)),
        'checkup',
        'medium',
        '../patient/dashboard.php',
        false
    );
}

/**
 * Send account verification notification
 */
function notifyAccountVerified($pdo, $user_id, $user_type) {
    $message = $user_type === 'doctor' 
        ? "Your doctor account has been verified! You can now start accepting patient consultations."
        : "Your account has been activated successfully!";
    
    createNotification(
        $pdo,
        $user_id,
        'Account Verified ✓',
        $message,
        'system',
        'high',
        null,
        false
    );
}

/**
 * Send system notification
 */
function notifySystem($pdo, $user_id, $title, $message, $priority = 'medium') {
    createNotification(
        $pdo,
        $user_id,
        $title,
        $message,
        'system',
        $priority,
        null,
        false
    );
}

/**
 * Schedule notification for future delivery
 */
function scheduleNotification($pdo, $user_id, $title, $message, $type, $priority, $scheduled_for, $action_url = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, priority, action_url, scheduled_for, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $user_id,
            $title,
            $message,
            $type,
            $priority,
            $action_url,
            $scheduled_for
        ]);
    } catch (PDOException $e) {
        error_log("Error scheduling notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Process scheduled notifications (should be run via cron job)
 */
function processScheduledNotifications($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET sent_at = NOW() 
            WHERE scheduled_for IS NOT NULL 
            AND scheduled_for <= NOW() 
            AND sent_at IS NULL
        ");
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error processing scheduled notifications: " . $e->getMessage());
        return false;
    }
}
?>