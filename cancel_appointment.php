<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$appointment_id = $_GET['id'] ?? null;
$success = false;
$message = '';
$appointment_details = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process cancellation
    if (isset($_POST['cancel_reason']) && $appointment_id) {
        $cancel_reason = htmlspecialchars(trim($_POST['cancel_reason']));
        
        try {
            // First verify the appointment belongs to the user
            $verify_stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
            $verify_stmt->bind_param("ii", $appointment_id, $user_id);
            $verify_stmt->execute();
            $result = $verify_stmt->get_result();
            
            if ($result->num_rows === 1) {
                $appointment = $result->fetch_assoc();
                
                // Check if appointment can be cancelled (not already cancelled or completed)
                if ($appointment['status'] != 'cancelled' && $appointment['status'] != 'completed') {
                    // Update appointment status
                    $update_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?");
                    $update_stmt->bind_param("si", $cancel_reason, $appointment_id);
                    
                    if ($update_stmt->execute()) {
                        $success = true;
                        $message = "Appointment #{$appointment_id} has been successfully cancelled.";
                        
                        // Optional: Send notification email
                        sendCancellationEmail($appointment, $cancel_reason);
                    } else {
                        $message = "Failed to cancel appointment. Please try again.";
                    }
                } else {
                    $message = "This appointment is already " . $appointment['status'] . " and cannot be cancelled.";
                }
            } else {
                $message = "Appointment not found or you don't have permission to cancel it.";
            }
        } catch (Exception $e) {
            $message = "An error occurred: " . $e->getMessage();
        }
    } else {
        $message = "Please provide a cancellation reason.";
    }
} else {
    // GET request - show confirmation page
    if ($appointment_id) {
        try {
            $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $appointment_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $appointment_details = $result->fetch_assoc();
                
                // Check if appointment can be cancelled
                if ($appointment_details['status'] == 'cancelled') {
                    $message = "This appointment is already cancelled.";
                } elseif ($appointment_details['status'] == 'completed') {
                    $message = "This appointment has already been completed and cannot be cancelled.";
                }
            } else {
                $message = "Appointment not found or you don't have permission to cancel it.";
            }
        } catch (Exception $e) {
            $message = "An error occurred while fetching appointment details.";
        }
    } else {
        $message = "No appointment specified.";
    }
}

// Function to send cancellation email
function sendCancellationEmail($appointment, $reason) {
    // In a real application, implement email sending here
    // This is a placeholder function
    return true;
}

// Return JSON if requested via AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment - CareGuides Autism Center</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .cancel-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .cancel-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .cancel-header {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .cancel-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .cancel-body {
            padding: 30px;
        }
        
        .appointment-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid var(--warning-color);
        }
        
        .summary-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .summary-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }
        
        .summary-value {
            color: #212529;
        }
        
        .reason-options {
            margin-bottom: 20px;
        }
        
        .reason-option {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .reason-option:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
        }
        
        .reason-option.selected {
            background: #fff3cd;
            border-color: var(--warning-color);
        }
        
        .custom-reason {
            display: none;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
        
        .policy-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .success-card {
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .cancel-container {
                margin: 20px auto;
                padding: 10px;
            }
            
            .cancel-body {
                padding: 20px;
            }
            
            .summary-item {
                flex-direction: column;
            }
            
            .summary-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Simple Header -->
    <nav class="navbar navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="appointments.php">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Appointments
            </a>
            <span class="navbar-text">
                CareGuides Autism Center
            </span>
        </div>
    </nav>
    
    <div class="cancel-container">
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <!-- Show success/error message after form submission -->
            <div class="cancel-card">
                <div class="success-card">
                    <?php if ($success): ?>
                        <div class="success-icon">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h2 class="text-success mb-3">Appointment Cancelled!</h2>
                        <p class="lead"><?php echo $message; ?></p>
                        <p class="text-muted mb-4">A confirmation email has been sent to your registered email address.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="appointments.php" class="btn btn-primary px-4">
                                <i class="bi bi-calendar-check me-2"></i>View All Appointments
                            </a>
                            <a href="book_appointment.php" class="btn btn-outline-primary px-4">
                                <i class="bi bi-plus-circle me-2"></i>Book New Appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-danger mb-4" style="font-size: 60px;">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <h2 class="text-danger mb-3">Cancellation Failed</h2>
                        <p class="lead"><?php echo $message; ?></p>
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <a href="appointments.php" class="btn btn-primary px-4">
                                <i class="bi bi-arrow-left me-2"></i>Back to Appointments
                            </a>
                            <button onclick="window.history.back()" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($appointment_details && $appointment_details['status'] != 'cancelled' && $appointment_details['status'] != 'completed'): ?>
            <!-- Show cancellation form -->
            <div class="cancel-card">
                <div class="cancel-header">
                    <div class="cancel-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <h2>Cancel Appointment</h2>
                    <p class="mb-0">Please confirm the appointment details and provide a reason for cancellation</p>
                </div>
                
                <div class="cancel-body">
                    <!-- Appointment Summary -->
                    <div class="appointment-summary">
                        <h5 class="mb-3">
                            <i class="bi bi-calendar-event me-2"></i>
                            Appointment Details
                        </h5>
                        <div class="summary-item">
                            <span class="summary-label">Appointment ID:</span>
                            <span class="summary-value">#<?php echo htmlspecialchars($appointment_details['id']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Child's Name:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($appointment_details['child_name']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Doctor:</span>
                            <span class="summary-value">Dr. <?php echo htmlspecialchars($appointment_details['doctor'] ?? 'Not assigned'); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Date & Time:</span>
                            <span class="summary-value">
                                <?php 
                                echo date('F j, Y', strtotime($appointment_details['appointment_date']));
                                if (!empty($appointment_details['appointment_time'])) {
                                    echo ' at ' . date('g:i A', strtotime($appointment_details['appointment_time']));
                                }
                                ?>
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Type:</span>
                            <span class="summary-value">
                                <?php 
                                $type = $appointment_details['consult_type'] ?? 'in-person';
                                echo ucfirst($type) . ' Consultation';
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Cancellation Form -->
                    <form id="cancelForm" method="POST" action="cancel_appointment.php?id=<?php echo $appointment_id; ?>">
                        <div class="mb-4">
                            <h5 class="mb-3">
                                <i class="bi bi-chat-dots me-2"></i>
                                Reason for Cancellation
                            </h5>
                            
                            <div class="reason-options">
                                <div class="reason-option" onclick="selectReason('schedule')">
                                    <input type="radio" class="form-check-input" name="cancel_reason" id="reason1" value="Schedule Conflict" style="display: none;">
                                    <label class="form-check-label w-100" for="reason1">
                                        <i class="bi bi-calendar-x me-2"></i>
                                        <strong>Schedule Conflict</strong>
                                        <p class="mb-0 text-muted small">I have another appointment or commitment at this time</p>
                                    </label>
                                </div>
                                
                                <div class="reason-option" onclick="selectReason('unavailable')">
                                    <input type="radio" class="form-check-input" name="cancel_reason" id="reason2" value="Doctor Unavailable" style="display: none;">
                                    <label class="form-check-label w-100" for="reason2">
                                        <i class="bi bi-person-x me-2"></i>
                                        <strong>Preferred Doctor Unavailable</strong>
                                        <p class="mb-0 text-muted small">I want to reschedule with a different doctor</p>
                                    </label>
                                </div>
                                
                                <div class="reason-option" onclick="selectReason('emergency')">
                                    <input type="radio" class="form-check-input" name="cancel_reason" id="reason3" value="Family Emergency" style="display: none;">
                                    <label class="form-check-label w-100" for="reason3">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Family Emergency</strong>
                                        <p class="mb-0 text-muted small">Unexpected situation requiring immediate attention</p>
                                    </label>
                                </div>
                                
                                <div class="reason-option" onclick="selectReason('child_illness')">
                                    <input type="radio" class="form-check-input" name="cancel_reason" id="reason4" value="Child Illness" style="display: none;">
                                    <label class="form-check-label w-100" for="reason4">
                                        <i class="bi bi-thermometer me-2"></i>
                                        <strong>Child is Unwell</strong>
                                        <p class="mb-0 text-muted small">My child is sick and cannot attend the appointment</p>
                                    </label>
                                </div>
                                
                                <div class="reason-option" onclick="selectReason('other')">
                                    <input type="radio" class="form-check-input" name="cancel_reason" id="reason5" value="other" style="display: none;">
                                    <label class="form-check-label w-100" for="reason5">
                                        <i class="bi bi-three-dots me-2"></i>
                                        <strong>Other Reason</strong>
                                        <p class="mb-0 text-muted small">Please specify in the text box below</p>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="custom-reason mt-3" id="customReason">
                                <label for="other_reason" class="form-label">Please specify your reason:</label>
                                <textarea class="form-control" id="other_reason" name="other_reason" rows="3" placeholder="Enter your specific reason for cancellation..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Cancellation Policy -->
                        <div class="policy-alert">
                            <h6><i class="bi bi-info-circle me-2"></i>Cancellation Policy</h6>
                            <ul class="mb-0 small">
                                <li>Cancellations made less than 24 hours before the appointment may be subject to a fee</li>
                                <li>Emergency cancellations are exempt from fees with proper documentation</li>
                                <li>You can reschedule your appointment at no additional cost</li>
                                <li>Your appointment slot will be made available to other patients</li>
                            </ul>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="appointments.php" class="btn btn-back">
                                <i class="bi bi-arrow-left me-2"></i>Go Back
                            </a>
                            <button type="submit" class="btn btn-cancel" id="cancelBtn">
                                <i class="bi bi-x-circle me-2"></i>Confirm Cancellation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Show error message -->
            <div class="cancel-card">
                <div class="cancel-header" style="background: linear-gradient(135deg, #6c757d, #5a6268);">
                    <div class="cancel-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h2>Unable to Cancel</h2>
                </div>
                
                <div class="cancel-body text-center py-5">
                    <div class="mb-4" style="font-size: 80px; color: #6c757d;">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h3 class="text-muted mb-3"><?php echo $message ?: 'Appointment not found'; ?></h3>
                    <p class="lead mb-4">You cannot cancel this appointment or it doesn't exist in our system.</p>
                    <a href="appointments.php" class="btn btn-primary px-4">
                        <i class="bi bi-calendar-check me-2"></i>Return to Appointments
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Reason selection handling
        function selectReason(reasonType) {
            // Remove selected class from all options
            document.querySelectorAll('.reason-option').forEach(option => {
                option.classList.remove('selected');
                option.querySelector('input[type="radio"]').checked = false;
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
            
            // Show/hide custom reason textarea
            const customReasonDiv = document.getElementById('customReason');
            if (reasonType === 'other') {
                customReasonDiv.style.display = 'block';
            } else {
                customReasonDiv.style.display = 'none';
            }
        }
        
        // Form submission handling
        document.getElementById('cancelForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedReason = document.querySelector('input[name="cancel_reason"]:checked');
            const otherReason = document.getElementById('other_reason')?.value.trim();
            
            if (!selectedReason) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reason Required',
                    text: 'Please select a reason for cancellation.',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            
            if (selectedReason.value === 'other' && (!otherReason || otherReason.length < 10)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Details Required',
                    text: 'Please provide at least 10 characters for your reason.',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            
            // Prepare form data
            const formData = new FormData(this);
            if (selectedReason.value === 'other' && otherReason) {
                formData.set('cancel_reason', otherReason);
            }
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Confirm Cancellation?',
                html: `<p>Are you sure you want to cancel appointment <strong>#<?php echo $appointment_id; ?></strong>?</p>
                      <p class="text-danger"><strong>Note:</strong> This action cannot be undone.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, cancel it!',
                cancelButtonText: 'No, keep it',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(this.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .catch(error => {
                        Swal.showValidationMessage(
                            `Request failed: ${error}`
                        );
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    // Reload page to show success message
                    location.reload();
                }
            });
        });
        
        // Auto-select first reason on page load
        document.addEventListener('DOMContentLoaded', function() {
            const firstOption = document.querySelector('.reason-option');
            if (firstOption) {
                firstOption.classList.add('selected');
                firstOption.querySelector('input[type="radio"]').checked = true;
            }
        });
    </script>
</body>
</html>