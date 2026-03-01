
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
$appointment = null;
$error = null;
$doctor_info = null;

if ($appointment_id) {
    try {
        // Try to get appointment with doctor info
        $doctorsTableExists = $conn->query("SHOW TABLES LIKE 'doctors'")->num_rows > 0;
        
        if ($doctorsTableExists) {
            // First verify the doctor table has the expected columns
            $doctorColumns = $conn->query("SHOW COLUMNS FROM doctors")->fetch_all(MYSQLI_ASSOC);
            $hasSpecialization = array_filter($doctorColumns, function($col) { return $col['Field'] == 'specialization'; });
            
            if ($hasSpecialization) {
                $sql = "SELECT a.*, d.specialization, d.qualification, d.experience, d.contact_info
                        FROM appointments a
                        LEFT JOIN doctors d ON a.doctor = d.name
                        WHERE a.id = ? AND a.user_id = ?";
            } else {
                // Fallback if columns don't exist
                $sql = "SELECT a.*, '' as specialization, '' as qualification, '' as experience, '' as contact_info
                        FROM appointments a
                        WHERE a.id = ? AND a.user_id = ?";
            }
        } else {
            $sql = "SELECT a.*, '' as specialization, '' as qualification, '' as experience, '' as contact_info
                    FROM appointments a
                    WHERE a.id = ? AND a.user_id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        
        // Check if prepare() was successful
        if ($stmt === false) {
            throw new Exception("SQL preparation failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $appointment_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $appointment = $result->fetch_assoc();
            
            // Format appointment data
            if (!empty($appointment['appointment_date'])) {
                $appointment['formatted_date'] = date('F j, Y', strtotime($appointment['appointment_date']));
                $appointment['day_of_week'] = date('l', strtotime($appointment['appointment_date']));
            }
            
            if (!empty($appointment['appointment_time'])) {
                $appointment['formatted_time'] = date('g:i A', strtotime($appointment['appointment_time']));
            }
            
            // Calculate time until appointment
            if (!empty($appointment['appointment_date'])) {
                $appointment_date = new DateTime($appointment['appointment_date']);
                $today = new DateTime();
                
                if ($appointment_date >= $today) {
                    $interval = $today->diff($appointment_date);
                    $appointment['days_until'] = $interval->format('%a');
                } else {
                    $appointment['days_until'] = 'Past';
                }
            }
            
            // Get doctor info if available
            if (!empty($appointment['doctor'])) {
                $doctor_stmt = $conn->prepare("SELECT * FROM doctors WHERE name = ?");
                if ($doctor_stmt !== false) {
                    $doctor_stmt->bind_param("s", $appointment['doctor']);
                    $doctor_stmt->execute();
                    $doctor_result = $doctor_stmt->get_result();
                    
                    if ($doctor_result->num_rows > 0) {
                        $doctor_info = $doctor_result->fetch_assoc();
                    }
                    $doctor_stmt->close();
                }
            }
        } else {
            $error = "Appointment not found or you don't have permission to view it.";
        }
        
        if ($stmt) {
            $stmt->close();
        }
    } catch (Exception $e) {
        // Debug: Show SQL error for development
        if (isset($sql)) {
            $error = "SQL Error: " . $conn->error . "<br>SQL: " . htmlspecialchars($sql);
        } else {
            $error = "An error occurred while fetching appointment details: " . $e->getMessage();
        }
    }
} else {
    $error = "No appointment specified.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - CareGuides Autism Center</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .view-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .appointment-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header-bg {
            position: absolute;
            top: 0;
            right: 0;
            opacity: 0.1;
            font-size: 150px;
        }
        
        .appointment-id {
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .main-card {
            background: white;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .info-section {
            padding: 30px;
        }
        
        .section-title {
            color: var(--secondary-color);
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: #212529;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }
        
        .type-badge {
            padding: 6px 15px;
            border-radius: 15px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .type-inperson {
            background: #e7f6e7;
            color: #198754;
            border: 1px solid #198754;
        }
        
        .type-video {
            background: #e3f2fd;
            color: #0d6efd;
            border: 1px solid #0d6efd;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin: 30px 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-left: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -33px;
            top: 5px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary-color);
        }
        
        .timeline-date {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .doctor-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
        }
        
        .doctor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            margin: 0 auto 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-action {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-print:hover {
            background: #5a6268;
            color: white;
        }
        
        .btn-download {
            background: #17a2b8;
            color: white;
            border: none;
        }
        
        .btn-download:hover {
            background: #138496;
            color: white;
        }
        
        .btn-share {
            background: #28a745;
            color: white;
            border: none;
        }
        
        .btn-share:hover {
            background: #218838;
            color: white;
        }
        
        .btn-back {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-back:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .view-container {
                padding: 10px;
            }
            
            .appointment-header {
                padding: 20px;
            }
            
            .info-section {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
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
                <i class="bi bi-calendar-check me-1"></i>
                Appointment Details
            </span>
        </div>
    </nav>
    
    <div class="view-container">
        <?php if ($error): ?>
            <!-- Error State -->
            <div class="main-card">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h3 class="text-danger mb-3">Error Loading Appointment</h3>
                    <p class="lead mb-4"><?php echo htmlspecialchars($error); ?></p>
                    <p class="text-muted small">If you're a developer, check your database structure and SQL query.</p>
                    <a href="appointments.php" class="btn btn-primary px-4">
                        <i class="bi bi-calendar-check me-2"></i>Return to Appointments
                    </a>
                </div>
            </div>
            
        <?php elseif ($appointment): ?>
            <!-- Appointment Details -->
            <div class="main-card">
                <!-- Header -->
                <div class="appointment-header">
                    <div class="header-bg">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    
                    <div class="appointment-id">
                        <i class="bi bi-hash me-1"></i>APPOINTMENT #<?php echo $appointment['id']; ?>
                    </div>
                    
                    <h1 class="display-6 fw-bold mb-3">
                        <?php echo htmlspecialchars($appointment['child_name']); ?>'s Appointment
                    </h1>
                    
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <p class="lead mb-0">
                                <i class="bi bi-person me-2"></i>
                                Parent: <?php echo htmlspecialchars($appointment['parent_name']); ?>
                                • 
                                <i class="bi bi-calendar3 ms-3 me-1"></i>
                                Age: <?php echo htmlspecialchars($appointment['child_age']); ?> years
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <?php
                            $status = $appointment['status'] ?? 'pending';
                            $statusClass = '';
                            switch($status) {
                                case 'pending':
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'bi-clock-history';
                                    break;
                                case 'confirmed':
                                    $statusClass = 'status-confirmed';
                                    $statusIcon = 'bi-check-circle';
                                    break;
                                case 'cancelled':
                                    $statusClass = 'status-cancelled';
                                    $statusIcon = 'bi-x-circle';
                                    break;
                                case 'completed':
                                    $statusClass = 'status-completed';
                                    $statusIcon = 'bi-check-all';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                                    $statusIcon = 'bi-clock-history';
                            }
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <i class="bi <?php echo $statusIcon; ?>"></i>
                                <?php echo strtoupper($status); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Appointment Information -->
                <div class="info-section">
                    <!-- Basic Information -->
                    <h3 class="section-title">
                        <i class="bi bi-info-circle"></i>
                        Appointment Information
                    </h3>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Appointment Date</div>
                            <div class="info-value">
                                <i class="bi bi-calendar3 me-2"></i>
                                <?php echo $appointment['formatted_date'] ?? 'Not scheduled'; ?>
                                <?php if (!empty($appointment['day_of_week'])): ?>
                                    <span class="text-muted">(<?php echo $appointment['day_of_week']; ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Appointment Time</div>
                            <div class="info-value">
                                <i class="bi bi-clock me-2"></i>
                                <?php echo $appointment['formatted_time'] ?? 'Time not set'; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Consultation Type</div>
                            <div class="info-value">
                                <?php 
                                $consultType = $appointment['consult_type'] ?? 'in-person';
                                $typeClass = $consultType == 'video' ? 'type-video' : 'type-inperson';
                                $typeIcon = $consultType == 'video' ? 'bi-camera-video' : 'bi-building';
                                ?>
                                <span class="type-badge <?php echo $typeClass; ?>">
                                    <i class="bi <?php echo $typeIcon; ?>"></i>
                                    <?php echo $consultType == 'video' ? 'Video Consultation' : 'In-Person Visit'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Days Until Appointment</div>
                            <div class="info-value">
                                <i class="bi bi-calendar-week me-2"></i>
                                <?php 
                                if (isset($appointment['days_until'])) {
                                    if ($appointment['days_until'] == 'Past') {
                                        echo 'Appointment Completed';
                                    } elseif ($appointment['days_until'] == '0') {
                                        echo '<span class="text-success fw-bold">Today!</span>';
                                    } else {
                                        echo $appointment['days_until'] . ' day(s)';
                                    }
                                } else {
                                    echo 'Date not set';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Child Information -->
                    <h3 class="section-title mt-5">
                        <i class="bi bi-person-badge"></i>
                        Child Information
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['child_name']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <div class="info-label">Age</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['child_age']); ?> years</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?php echo htmlspecialchars($appointment['child_gender'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($appointment['notes'])): ?>
                    <div class="info-item mt-3">
                        <div class="info-label">Special Notes / Concerns</div>
                        <div class="info-value">
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Doctor Information -->
                    <h3 class="section-title mt-5">
                        <i class="bi bi-person-heart"></i>
                        Doctor Information
                    </h3>
                    
                    <div class="doctor-card">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <div class="doctor-avatar">
                                    <i class="bi bi-person"></i>
                                </div>
                                <h5 class="mt-3 mb-1">Dr. <?php echo htmlspecialchars($appointment['doctor'] ?? 'Not assigned'); ?></h5>
                                <?php if (!empty($appointment['specialization'])): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($appointment['specialization']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-9">
                                <?php if ($doctor_info): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong><i class="bi bi-award me-2"></i>Qualification:</strong><br>
                                            <?php echo htmlspecialchars($doctor_info['qualification'] ?? 'Not specified'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong><i class="bi bi-briefcase me-2"></i>Experience:</strong><br>
                                            <?php echo htmlspecialchars($doctor_info['experience'] ?? 'Not specified'); ?></p>
                                        </div>
                                        <div class="col-12">
                                            <p><strong><i class="bi bi-telephone me-2"></i>Contact:</strong><br>
                                            <?php echo htmlspecialchars($doctor_info['contact_info'] ?? 'Contact center for details'); ?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Doctor details will be updated once your appointment is confirmed.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointment Timeline -->
                    <h3 class="section-title mt-5">
                        <i class="bi bi-clock-history"></i>
                        Appointment Timeline
                    </h3>
                    
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo date('F j, Y', strtotime($appointment['created_at'] ?? 'now')); ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Appointment Created</strong>
                                <p class="mb-0">You requested this appointment</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($appointment['status']) && $appointment['status'] == 'confirmed'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php 
                                $confirmed_date = !empty($appointment['confirmed_at']) ? 
                                    date('F j, Y', strtotime($appointment['confirmed_at'])) : 
                                    $appointment['formatted_date'] ?? date('F j, Y');
                                ?>
                                <?php echo $confirmed_date; ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Appointment Confirmed</strong>
                                <p class="mb-0">Your appointment has been confirmed by our team</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($appointment['appointment_date'])): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo $appointment['formatted_date']; ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Scheduled Appointment</strong>
                                <p class="mb-0">
                                    <?php if (!empty($appointment['formatted_time'])): ?>
                                        Appointment time: <?php echo $appointment['formatted_time']; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($appointment['location'])): ?>
                                        <br>Location: <?php echo htmlspecialchars($appointment['location']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($appointment['status']) && $appointment['status'] == 'completed'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo $appointment['formatted_date']; ?>
                            </div>
                            <div class="timeline-content">
                                <strong>Appointment Completed</strong>
                                <p class="mb-0">
                                    Session completed successfully
                                    <?php if (!empty($appointment['session_notes'])): ?>
                                        <br>Notes: <?php echo htmlspecialchars(substr($appointment['session_notes'], 0, 100)) . '...'; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="appointments.php" class="btn btn-back btn-action">
                            <i class="bi bi-arrow-left me-2"></i>Back to Appointments
                        </a>
                        
                        <button onclick="window.print()" class="btn btn-print btn-action">
                            <i class="bi bi-printer me-2"></i>Print Details
                        </button>
                        
                        <?php if ($status == 'pending'): ?>
                            <a href="cancel_appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-danger btn-action">
                                <i class="bi bi-x-circle me-2"></i>Cancel Appointment
                            </a>
                        <?php elseif ($status == 'confirmed' && isset($appointment['days_until']) && $appointment['days_until'] != 'Past'): ?>
                            <a href="reschedule_appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-warning btn-action">
                                <i class="bi bi-calendar-check me-2"></i>Reschedule
                            </a>
                            <a href="cancel_appointment.php?id=<?php echo $appointment_id; ?>" class="btn btn-danger btn-action">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                            <?php if ($consultType == 'video'): ?>
                                <a href="join_consultation.php?id=<?php echo $appointment_id; ?>" class="btn btn-success btn-action">
                                    <i class="bi bi-camera-video me-2"></i>Join Consultation
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="main-card">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h3 class="mb-3">Appointment Not Found</h3>
                    <p class="lead mb-4">The appointment you're looking for doesn't exist or you don't have permission to view it.</p>
                    <a href="appointments.php" class="btn btn-primary px-4">
                        <i class="bi bi-calendar-check me-2"></i>View All Appointments
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Print styling
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                nav, .action-buttons, .btn-action {
                    display: none !important;
                }
                
                body {
                    background: white !important;
                }
                
                .main-card {
                    box-shadow: none !important;
                    border: 1px solid #dee2e6;
                }
                
                .info-item {
                    break-inside: avoid;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
