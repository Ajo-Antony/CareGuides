
<?php
session_start();
require_once 'config/database.php';
requireLogin();

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? 0;

if (!$appointment_id) {
    header('Location: appointments.php');
    exit();
}

// Get appointment details
$stmt = $conn->prepare("
    SELECT a.*, u.username, u.email as user_email
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND (a.user_id = ? OR ? = (SELECT id FROM users WHERE user_type = 'admin' LIMIT 1))
");
$stmt->bind_param("iii", $appointment_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    header('Location: appointments.php');
    exit();
}

// Format date and time
$formatted_date = date('F j, Y', strtotime($appointment['appointment_date']));
$time_slot = !empty($appointment['appointment_time']) 
    ? date('g:i A', strtotime($appointment['appointment_time'])) 
    : 'Not specified';

// Format consultation type
$consult_type_display = ucfirst(str_replace('-', ' ', $appointment['consult_type'] ?? 'in-person'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - CareGuides</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4a6fa5, #166088);
            color: #fff;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .details-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .info-group {
            margin-bottom: 25px;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #212529;
            font-size: 1.1rem;
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #4a6fa5;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -36px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4a6fa5;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #4a6fa5;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .contact-info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #4a6fa5;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .details-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="fw-bold">Appointment Details</h1>
                    <p class="mb-0">Appointment ID: APPT-<?php echo $appointment_id; ?></p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button onclick="window.print()" class="btn btn-light">
                        <i class="bi bi-printer me-2"></i> Print
                    </button>
                    <a href="appointments.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-2"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container pb-5">
        <div class="row">
            <div class="col-lg-8">
                <!-- Appointment Details -->
                <div class="details-card">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4 class="mb-0">Appointment Information</h4>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php
                            $statusClass = 'badge-' . $appointment['status'];
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Appointment ID</div>
                                <div class="info-value">APPT-<?php echo $appointment_id; ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Child's Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['child_name']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Child's Age</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['child_age'] ?? 'N/A'); ?> years</div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Parent/Guardian</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-group">
                                <div class="info-label">Specialist</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['doctor']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Date & Time</div>
                                <div class="info-value">
                                    <?php echo $formatted_date; ?> at <?php echo $time_slot; ?>
                                </div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Consultation Type</div>
                                <div class="info-value"><?php echo $consult_type_display; ?></div>
                            </div>
                            
                            <div class="info-group">
                                <div class="info-label">Booked On</div>
                                <div class="info-value"><?php echo date('F j, Y', strtotime($appointment['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($appointment['message'])): ?>
                    <div class="info-group">
                        <div class="info-label">Additional Notes</div>
                        <div class="info-value">
                            <?php echo nl2br(htmlspecialchars($appointment['message'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contact Information -->
                <div class="details-card">
                    <h4 class="mb-4">Contact Information</h4>
                    <div class="contact-info-box">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Parent Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($appointment['parent_email']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Parent Phone</div>
                                    <div class="info-value"><?php echo !empty($appointment['parent_phone']) ? htmlspecialchars($appointment['parent_phone']) : 'N/A'; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Account Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['username'] ?? 'Guest'); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Account Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['user_email'] ?? 'Not linked to account'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Action Panel -->
                <div class="details-card">
                    <h4 class="mb-4">Actions</h4>
                    
                    <div class="action-buttons">
                        <?php if ($appointment['status'] === 'pending'): ?>
                        <button onclick="confirmAppointment()" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i> Confirm
                        </button>
                        <button onclick="rescheduleAppointment()" class="btn btn-warning">
                            <i class="bi bi-calendar-check me-2"></i> Reschedule
                        </button>
                        <button onclick="cancelAppointment()" class="btn btn-danger">
                            <i class="bi bi-x-circle me-2"></i> Cancel
                        </button>
                        <?php elseif ($appointment['status'] === 'confirmed'): ?>
                        <button onclick="startConsultation()" class="btn btn-primary">
                            <i class="bi bi-camera-video me-2"></i> Start Consultation
                        </button>
                        <button onclick="rescheduleAppointment()" class="btn btn-outline-warning">
                            <i class="bi bi-calendar-check me-2"></i> Reschedule
                        </button>
                        <?php endif; ?>
                        
                        <a href="mailto:<?php echo htmlspecialchars($appointment['parent_email']); ?>" class="btn btn-outline-primary">
                            <i class="bi bi-envelope me-2"></i> Email
                        </a>
                        
                        <?php if (!empty($appointment['parent_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($appointment['parent_phone']); ?>" class="btn btn-outline-success">
                            <i class="bi bi-telephone me-2"></i> Call
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Quick Links</h6>
                        <div class="list-group">
                            <a href="appointment_success.php?id=<?php echo $appointment_id; ?>" class="list-group-item list-group-item-action">
                                <i class="bi bi-printer me-2"></i> View Confirmation
                            </a>
                            <a href="book_appointment.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-calendar-plus me-2"></i> Book New Appointment
                            </a>
                            <a href="contact.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-headset me-2"></i> Contact Support
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="details-card">
                    <h4 class="mb-4">Appointment Timeline</h4>
                    <div class="timeline">
                        <div class="timeline-item">
                            <h6 class="mb-1">Appointment Booked</h6>
                            <p class="text-muted mb-1"><?php echo date('F j, Y', strtotime($appointment['created_at'])); ?></p>
                            <small class="text-muted">Appointment was scheduled</small>
                        </div>
                        
                        <?php if ($appointment['status'] === 'confirmed'): ?>
                        <div class="timeline-item">
                            <h6 class="mb-1">Appointment Confirmed</h6>
                            <p class="text-muted mb-1">Awaiting confirmation timestamp</p>
                            <small class="text-muted">Appointment was confirmed by the system</small>
                        </div>
                        
                        <div class="timeline-item">
                            <h6 class="mb-1">Upcoming</h6>
                            <p class="text-muted mb-1"><?php echo $formatted_date; ?> at <?php echo $time_slot; ?></p>
                            <small class="text-muted">Scheduled appointment time</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="details-card">
                    <h4 class="mb-3">Instructions</h4>
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle me-2"></i>Important Information</h6>
                        <ul class="mb-0">
                            <li>Please arrive 15 minutes before your appointment</li>
                            <li>Bring any relevant medical reports</li>
                            <li>For video consultations, ensure stable internet connection</li>
                            <li>Cancellation must be made 24 hours in advance</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmAppointment() {
            if (confirm('Confirm this appointment?')) {
                updateAppointmentStatus('confirm');
            }
        }
        
        function rescheduleAppointment() {
            window.location.href = 'book_appointment.php?reschedule=<?php echo $appointment_id; ?>';
        }
        
        function cancelAppointment() {
            if (confirm('Are you sure you want to cancel this appointment?')) {
                updateAppointmentStatus('cancel');
            }
        }
        
        function startConsultation() {
            // Check if it's time for the appointment
            const appointmentDate = new Date('<?php echo $appointment['appointment_date'] . 'T' . $appointment['appointment_time']; ?>');
            const now = new Date();
            
            if (now >= appointmentDate) {
                if ('<?php echo $appointment['consult_type']; ?>' === 'video') {
                    // For video consultations
                    alert('Starting video consultation...');
                    // window.open('video-consultation.php?id=<?php echo $appointment_id; ?>', '_blank');
                } else {
                    // For in-person consultations
                    alert('Please proceed to the consultation room.');
                }
            } else {
                alert('Appointment has not started yet. Please wait until the scheduled time.');
            }
        }
        
        function updateAppointmentStatus(action) {
            fetch('update_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=<?php echo $appointment_id; ?>&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }
        
        // Print functionality
        document.addEventListener('DOMContentLoaded', function() {
            const printBtn = document.querySelector('[onclick="window.print()"]');
            printBtn.addEventListener('click', function() {
                window.print();
            });
        });
    </script>
</body>
</html>
