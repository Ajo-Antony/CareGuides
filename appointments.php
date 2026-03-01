<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// First, let's check if the appointments table exists and has the required columns
$error = null;
$appointments = [];
$statistics = [
    'total' => 0,
    'confirmed' => 0,
    'pending' => 0,
    'cancelled' => 0,
    'completed' => 0
];

try {
    // Check table structure
    $tableCheck = $conn->query("SHOW TABLES LIKE 'appointments'");
    if ($tableCheck->num_rows == 0) {
        throw new Exception("Appointments table does not exist.");
    }

    // Check if doctors table exists for specialization
    $doctorsTableExists = $conn->query("SHOW TABLES LIKE 'doctors'")->num_rows > 0;
    
    if ($doctorsTableExists) {
        // Try with join first
        $sql = "SELECT a.*, d.specialization 
                FROM appointments a
                LEFT JOIN doctors d ON a.doctor = d.name
                WHERE a.user_id = ? 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    } else {
        // Fallback to simple query without join
        $sql = "SELECT a.*, '' as specialization 
                FROM appointments a
                WHERE a.user_id = ? 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    }

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare SQL statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute SQL statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    $statistics['total'] = count($appointments);
    foreach ($appointments as $appointment) {
        $status = $appointment['status'] ?? 'pending';
        switch($status) {
            case 'confirmed':
                $statistics['confirmed']++;
                break;
            case 'pending':
                $statistics['pending']++;
                break;
            case 'cancelled':
                $statistics['cancelled']++;
                break;
            case 'completed':
                $statistics['completed']++;
                break;
            default:
                $statistics['pending']++;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    
    // Try a simpler query as fallback
    try {
        $simpleSql = "SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC";
        $simpleStmt = $conn->prepare($simpleSql);
        if ($simpleStmt) {
            $simpleStmt->bind_param("i", $user_id);
            $simpleStmt->execute();
            $simpleResult = $simpleStmt->get_result();
            $appointments = $simpleResult->fetch_all(MYSQLI_ASSOC);
            $error = null; // Clear error if successful
        }
    } catch (Exception $e2) {
        // Keep the original error
    }
}

// Check for upcoming appointments
$upcomingCount = 0;
$upcomingAppointments = [];
if (!$error && !empty($appointments)) {
    foreach ($appointments as $appointment) {
        if (($appointment['status'] ?? '') == 'confirmed' && 
            isset($appointment['appointment_date']) &&
            strtotime($appointment['appointment_date']) >= strtotime(date('Y-m-d'))) {
            $upcomingCount++;
            $upcomingAppointments[] = $appointment;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - CareGuides Autism Center</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #17a2b8;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --card-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: 0;
        }
        
        /* Header Styling */
        .main-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.2);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-text h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }
        
        .logo-text p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 25px;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .page-container {
            padding: 30px 0;
        }
        
        .page-title {
            margin-bottom: 30px;
        }
        
        .page-title h2 {
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .page-title p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border-top: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        .stat-total { background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #1976d2; }
        .stat-pending { background: linear-gradient(135deg, #fff3cd, #ffeaa7); color: #ff9800; }
        .stat-confirmed { background: linear-gradient(135deg, #d4edda, #c3e6cb); color: #4caf50; }
        .stat-cancelled { background: linear-gradient(135deg, #f8d7da, #f5c6cb); color: #f44336; }
        .stat-completed { background: linear-gradient(135deg, #d1ecf1, #bee5eb); color: #0c5460; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card-header-custom {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-weight: 700;
            color: var(--secondary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .badge-confirmed {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-cancelled {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge-completed {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .consult-badge {
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .consult-in-person {
            background: #e7f6e7;
            color: #198754;
            border: 1px solid #198754;
        }
        
        .consult-video {
            background: #e3f2fd;
            color: #0d6efd;
            border: 1px solid #0d6efd;
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
        
        .appointment-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .appointment-card.upcoming {
            border-left-color: #28a745;
            background: linear-gradient(to right, #f8fff8, white);
        }
        
        .appointment-card.cancelled {
            border-left-color: #dc3545;
            background: linear-gradient(to right, #fff8f8, white);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .appointment-id {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .appointment-date {
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .child-info {
            margin-bottom: 15px;
        }
        
        .child-name {
            font-weight: 700;
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .child-details {
            color: #666;
            font-size: 0.9rem;
        }
        
        .doctor-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .doctor-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .doctor-specialty {
            color: #666;
            font-size: 0.85rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-view {
            background: #e7f1ff;
            color: #0d6efd;
            border: 1px solid #b8d4ff;
        }
        
        .btn-view:hover {
            background: #d0e2ff;
            color: #0d6efd;
        }
        
        .btn-cancel {
            background: #ffeaea;
            color: #dc3545;
            border: 1px solid #ffc9c9;
        }
        
        .btn-cancel:hover {
            background: #ffd6d6;
            color: #dc3545;
        }
        
        .btn-reschedule {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .btn-reschedule:hover {
            background: #ffeaa7;
            color: #856404;
        }
        
        .btn-join {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .btn-join:hover {
            background: #bee5eb;
            color: #0c5460;
        }
        
        .btn-notes {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .btn-notes:hover {
            background: #d6d8db;
            color: #383d41;
        }
        
        .upcoming-badge {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .notification-alert {
            border-radius: 10px;
            border-left: 5px solid #28a745;
            background: linear-gradient(to right, #f8fff8, white);
            animation: slideIn 0.5s ease;
        }
        
        .error-alert {
            border-left: 5px solid #dc3545;
            animation: shake 0.5s;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-actions {
                width: 100%;
                justify-content: center;
            }
            
            .stat-number {
                font-size: 2rem;
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
    <!-- Custom Header -->
    <header class="main-header">
        <div class="container header-container">
            <div class="logo-area">
                <div class="logo-icon">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <div class="logo-text">
                    <h1>CareGuides Autism Center</h1>
                    <p>Compassionate Care for Every Child</p>
                </div>
            </div>
            
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                        <div class="small">Patient Portal</div>
                    </div>
                </div>
                
                <a href="dashboard.php" class="btn btn-outline-light">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                
                <a href="logout.php" class="btn btn-light">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </header>
    
    <div class="container page-container">
        <!-- Page Header -->
        <div class="page-title">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="bi bi-calendar-check me-2"></i>My Appointments</h2>
                    <p>View and manage all your scheduled consultations and appointments</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="book_appointment.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-plus-circle me-2"></i>New Appointment
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Display error if exists -->
        <?php if ($error): ?>
        <div class="alert alert-danger error-alert alert-dismissible fade show mb-4" role="alert">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Database Error</h5>
            <p><?php echo htmlspecialchars($error); ?></p>
            <p class="mb-0">Please contact support if this issue persists.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-5">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon stat-total">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                    <div class="stat-number"><?php echo $statistics['total']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon stat-pending">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-number"><?php echo $statistics['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon stat-confirmed">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $statistics['confirmed']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon stat-completed">
                        <i class="bi bi-check-all"></i>
                    </div>
                    <div class="stat-number"><?php echo $statistics['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon stat-cancelled">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $statistics['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #e9ecef, #dee2e6); color: #495057;">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-number"><?php echo $upcomingCount; ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Appointments Alert -->
        <?php if ($upcomingCount > 0): ?>
        <div class="alert notification-alert alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle-fill me-3 fs-4 text-success"></i>
                <div>
                    <h5 class="alert-heading mb-1">You have <?php echo $upcomingCount; ?> upcoming appointment(s)</h5>
                    <p class="mb-0">Please join the consultation 5 minutes before the scheduled time. <a href="#upcoming" class="alert-link">View upcoming appointments</a></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Appointments List -->
        <div class="main-card">
            <div class="card-header-custom">
                <h3 class="card-title">
                    <i class="bi bi-list-task text-primary"></i>
                    Scheduled Appointments
                </h3>
                <div class="text-muted">
                    Showing <?php echo count($appointments); ?> appointment(s)
                </div>
            </div>
            
            <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h3 class="fw-bold mb-3">No Appointments Found</h3>
                    <p class="text-muted mb-4">You haven't booked any appointments yet.</p>
                    <a href="book_appointment.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-plus-circle me-2"></i>Book Your First Appointment
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php 
                    $counter = 0;
                    foreach ($appointments as $row): 
                        // Safely handle missing fields
                        $appointmentDate = $row['appointment_date'] ?? '';
                        $consultType = $row['consult_type'] ?? 'in-person';
                        $status = $row['status'] ?? 'pending';
                        $specialization = $row['specialization'] ?? '';
                        
                        // Determine if upcoming
                        $isUpcoming = false;
                        if (!empty($appointmentDate) && $status == 'confirmed') {
                            $dateObj = new DateTime($appointmentDate);
                            $today = new DateTime();
                            $isUpcoming = $dateObj >= $today;
                        }
                        
                        // Card CSS class based on status
                        $cardClass = 'appointment-card';
                        if ($isUpcoming) $cardClass .= ' upcoming';
                        if ($status == 'cancelled') $cardClass .= ' cancelled';
                        
                        $counter++;
                    ?>
                    <div class="col-lg-6 mb-3" id="<?php echo $isUpcoming ? 'upcoming' : ''; ?>">
                        <div class="<?php echo $cardClass; ?>">
                            <div class="appointment-header">
                                <div class="appointment-id">
                                    Appointment #<?php echo $row['id'] ?? $counter; ?>
                                    <?php if ($isUpcoming): ?>
                                        <span class="upcoming-badge">
                                            <i class="bi bi-arrow-up-right"></i>Upcoming
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="appointment-date">
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo !empty($appointmentDate) ? date('M d, Y', strtotime($appointmentDate)) : 'Date not set'; ?>
                                </div>
                            </div>
                            
                            <div class="child-info">
                                <div class="child-name">
                                    <?php echo htmlspecialchars($row['child_name'] ?? 'N/A'); ?>
                                </div>
                                <div class="child-details">
                                    <div><i class="bi bi-person me-1"></i> Parent: <?php echo htmlspecialchars($row['parent_name'] ?? 'N/A'); ?></div>
                                    <div><i class="bi bi-calendar3 me-1"></i> Age: <?php echo htmlspecialchars($row['child_age'] ?? 'N/A'); ?> years</div>
                                </div>
                            </div>
                            
                            <div class="doctor-info">
                                <div class="doctor-name">
                                    <i class="bi bi-person-badge me-2"></i>
                                    <?php echo !empty($row['doctor']) ? 'Dr. ' . htmlspecialchars($row['doctor']) : 'Doctor not assigned'; ?>
                                </div>
                                <?php if (!empty($specialization)): ?>
                                    <div class="doctor-specialty">
                                        <i class="bi bi-tags me-1"></i>
                                        <?php echo htmlspecialchars($specialization); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row align-items-center mb-3">
                                <div class="col-md-6">
                                    <?php if ($consultType == 'video'): ?>
                                        <span class="consult-badge consult-video">
                                            <i class="bi bi-camera-video"></i>Video Consultation
                                        </span>
                                    <?php else: ?>
                                        <span class="consult-badge consult-in-person">
                                            <i class="bi bi-building"></i>In-Person Visit
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <?php
                                    $statusBadge = '';
                                    $statusIcon = '';
                                    switch($status) {
                                        case 'pending':
                                            $statusBadge = 'badge-pending';
                                            $statusIcon = 'bi-clock-history';
                                            break;
                                        case 'confirmed':
                                            $statusBadge = 'badge-confirmed';
                                            $statusIcon = 'bi-check-circle';
                                            break;
                                        case 'cancelled':
                                            $statusBadge = 'badge-cancelled';
                                            $statusIcon = 'bi-x-circle';
                                            break;
                                        case 'completed':
                                            $statusBadge = 'badge-completed';
                                            $statusIcon = 'bi-check-all';
                                            break;
                                        default:
                                            $statusBadge = 'badge-pending';
                                            $statusIcon = 'bi-clock-history';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusBadge; ?>">
                                        <i class="bi <?php echo $statusIcon; ?>"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <!-- View Appointment Button -->
                                <a href="view_appointment.php?id=<?php echo $row['id']; ?>" 
                                   class="btn-action btn-view">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                
                                <?php if ($status == 'pending'): ?>
                                    <!-- Cancel Button for pending appointments -->
                                    <button onclick="cancelAppointment(<?php echo $row['id']; ?>)" 
                                            class="btn-action btn-cancel">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                    
                                <?php elseif ($status == 'confirmed' && $isUpcoming): ?>
                                    <!-- Reschedule Button for upcoming confirmed appointments -->
                                    <button onclick="rescheduleAppointment(<?php echo $row['id']; ?>)" 
                                            class="btn-action btn-reschedule">
                                        <i class="bi bi-calendar-check"></i> Reschedule
                                    </button>
                                    
                                    <!-- Cancel Button for upcoming confirmed appointments -->
                                    <button onclick="cancelAppointment(<?php echo $row['id']; ?>)" 
                                            class="btn-action btn-cancel">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                    
                                    <!-- Join Button for video consultations -->
                                    <?php if ($consultType == 'video'): ?>
                                        <button onclick="joinConsultation(<?php echo $row['id']; ?>)" 
                                                class="btn-action btn-join">
                                            <i class="bi bi-camera-video"></i> Join Call
                                        </button>
                                    <?php endif; ?>
                                    
                                <?php elseif ($status == 'completed'): ?>
                                    <!-- View Notes Button for completed appointments -->
                                    <button onclick="viewNotes(<?php echo $row['id']; ?>)" 
                                            class="btn-action btn-notes">
                                        <i class="bi bi-file-text"></i> Session Notes
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Additional Information -->
        <div class="row">
            <div class="col-md-6">
                <div class="main-card">
                    <h4 class="card-title mb-3">
                        <i class="bi bi-info-circle text-info me-2"></i>
                        Appointment Guidelines
                    </h4>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-check-circle text-success me-3"></i>
                            <div>
                                <strong>Arrive Early:</strong> Please arrive 10-15 minutes before your scheduled time
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-check-circle text-success me-3"></i>
                            <div>
                                <strong>Cancellation Policy:</strong> Cancel at least 24 hours in advance to avoid fees
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-check-circle text-success me-3"></i>
                            <div>
                                <strong>Video Consultations:</strong> Test your audio/video 10 minutes before the call
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-check-circle text-success me-3"></i>
                            <div>
                                <strong>Rescheduling:</strong> You can reschedule up to 2 hours before your appointment
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="main-card">
                    <h4 class="card-title mb-3">
                        <i class="bi bi-question-circle text-warning me-2"></i>
                        Need Help?
                    </h4>
                    <div class="text-center py-4">
                        <i class="bi bi-headset display-4 text-muted mb-3"></i>
                        <h5>Contact Our Support Team</h5>
                        <p class="text-muted">Having issues with your appointments?</p>
                        <div class="d-flex justify-content-center gap-3 mt-3">
                            <a href="tel:+1234567890" class="btn btn-outline-primary">
                                <i class="bi bi-telephone me-2"></i> Call Now
                            </a>
                            <a href="contact.php" class="btn btn-primary">
                                <i class="bi bi-envelope me-2"></i> Send Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function cancelAppointment(id) {
            Swal.fire({
                title: 'Cancel Appointment?',
                text: "Are you sure you want to cancel this appointment? This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!',
                cancelButtonText: 'No, keep it',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`cancel_appointment.php?id=${id}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(response.statusText);
                            }
                            return response.json();
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
                    Swal.fire(
                        'Cancelled!',
                        'Your appointment has been cancelled.',
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                }
            });
        }
        
        function rescheduleAppointment(id) {
            window.location.href = 'reschedule_appointment.php?id=' + id;
        }
        
        function joinConsultation(id) {
            Swal.fire({
                title: 'Join Video Consultation',
                text: 'Make sure your camera and microphone are working properly before joining.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Join Now',
                cancelButtonText: 'Test First',
                confirmButtonColor: '#0d6efd'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'join_consultation.php?id=' + id;
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.open('https://webcamtests.com/', '_blank');
                }
            });
        }
        
        function viewNotes(id) {
            window.location.href = 'session_notes.php?id=' + id;
        }
        
        // Add SweetAlert if not already loaded
        if (typeof Swal === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }
    </script>
</body>
</html>