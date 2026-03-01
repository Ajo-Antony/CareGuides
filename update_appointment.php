
<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get appointment ID and action
$appointment_id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$appointment_id || !in_array($action, ['confirm', 'cancel', 'reschedule'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Check if user owns this appointment or is admin
$stmt = $conn->prepare("
    SELECT user_id FROM appointments WHERE id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit();
}

// Check permission
$is_admin = ($_SESSION['user_type'] ?? '') === 'admin';
$is_owner = ($appointment['user_id'] == $_SESSION['user_id']);

if (!$is_admin && !$is_owner) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Update appointment based on action
$new_status = '';
$message = '';

switch ($action) {
    case 'confirm':
        $new_status = 'confirmed';
        $message = 'Appointment confirmed successfully';
        break;
    case 'cancel':
        $new_status = 'cancelled';
        $message = 'Appointment cancelled successfully';
        break;
    case 'reschedule':
        // This would redirect to booking page with existing data
        echo json_encode(['success' => true, 'redirect' => 'book_appointment.php?reschedule=' . $appointment_id]);
        exit();
}

// Update the appointment status
$update_stmt = $conn->prepare("
    UPDATE appointments SET status = ? WHERE id = ?
");
$update_stmt->bind_param("si", $new_status, $appointment_id);

if ($update_stmt->execute()) {
    // Log the activity
    $activity_type = 'appointment_' . $action . 'ed';
    $activity_details = $action . ' appointment ID: ' . $appointment_id;
    
    $log_stmt = $conn->prepare("
        INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    $log_stmt->bind_param("isss", $_SESSION['user_id'], $activity_type, $activity_details, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
