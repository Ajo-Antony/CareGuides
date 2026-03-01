<?php
session_start();
require_once 'config/database.php';
requireLogin();

// Security: Add CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user statistics
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$fullName = $_SESSION['full_name'];
$userType = $_SESSION['user_type'];

// Validate user_type values - only 'user' allowed
$allowedTypes = ['user'];
if (!in_array($userType, $allowedTypes)) {
    $userType = 'user';
}

// FIX: Get user's test history - only show screening tests and exclude incomplete tests
$testHistoryQuery = $conn->prepare("
    SELECT id, child_name, child_age, test_date, test_type,
           prediction_result, asd_status, therapy_prediction,
           a1, a2, a3, a4, a5, a6, a7, a8, a9, a10,
           asd_level, speech_delay, motor_delay, confidence_score,
           test_data, past_therapies, feedback
    FROM test_results 
    WHERE user_id = ? 
    AND test_type = 'screening'
    AND child_name IS NOT NULL 
    AND child_name != '' 
    AND child_name != '0'
    AND (a1 IS NOT NULL OR a2 IS NOT NULL OR a3 IS NOT NULL)
    ORDER BY test_date DESC 
    LIMIT 6  -- Get 6 to skip the first one if it's the latest
");
if (!$testHistoryQuery) {
    die("Test history query failed: " . $conn->error);
}
$testHistoryQuery->bind_param("i", $userId);
$testHistoryQuery->execute();
$testHistory = $testHistoryQuery->get_result();

// Get total tests count - only valid screening tests
$totalTestsQuery = $conn->prepare("
    SELECT COUNT(*) as count FROM test_results 
    WHERE user_id = ? 
    AND test_type = 'screening'
    AND child_name IS NOT NULL 
    AND child_name != '' 
    AND child_name != '0'
");
if (!$totalTestsQuery) {
    die("Total tests query failed: " . $conn->error);
}
$totalTestsQuery->bind_param("i", $userId);
$totalTestsQuery->execute();
$totalTestsResult = $totalTestsQuery->get_result();
$totalTests = $totalTestsResult->fetch_assoc()['count'];

// Get upcoming appointments
$appointmentsQuery = $conn->prepare("
    SELECT id, child_name, doctor, appointment_date, appointment_time, 
           consult_type, status, created_at, parent_phone, parent_name, parent_email
    FROM appointments 
    WHERE user_id = ? AND status IN ('confirmed', 'pending') 
    AND appointment_date >= CURDATE() 
    ORDER BY appointment_date, appointment_time 
    LIMIT 3
");
if (!$appointmentsQuery) {
    die("Appointments query failed: " . $conn->error);
}
$appointmentsQuery->bind_param("i", $userId);
$appointmentsQuery->execute();
$appointments = $appointmentsQuery->get_result();

// Get recent activities with error handling
$activities = false;
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_activities'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $activitiesQuery = $conn->prepare("
            SELECT * FROM user_activities 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        if ($activitiesQuery) {
            $activitiesQuery->bind_param("i", $userId);
            $activitiesQuery->execute();
            $activities = $activitiesQuery->get_result();
        }
    }
} catch (Exception $e) {
    error_log("Activities query error: " . $e->getMessage());
}

// Get appointments count
$appointmentsCountQuery = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
if (!$appointmentsCountQuery) {
    die("Appointments count query failed: " . $conn->error);
}
$appointmentsCountQuery->bind_param("i", $userId);
$appointmentsCountQuery->execute();
$appointmentsCountResult = $appointmentsCountQuery->get_result();
$appointmentsCount = $appointmentsCountResult->fetch_assoc()['count'];

// Get confirmed appointments count
$confirmedAppointmentsQuery = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'confirmed'");
if (!$confirmedAppointmentsQuery) {
    die("Confirmed appointments query failed: " . $conn->error);
}
$confirmedAppointmentsQuery->bind_param("i", $userId);
$confirmedAppointmentsQuery->execute();
$confirmedAppointmentsResult = $confirmedAppointmentsQuery->get_result();
$confirmedAppointments = $confirmedAppointmentsResult->fetch_assoc()['count'];

// Get latest test result - FIXED: Fetch the most recent valid test
$latestTestQuery = $conn->prepare("
    SELECT id, child_name, child_age, test_date, test_type,
           prediction_result, asd_status, therapy_prediction,
           a1, a2, a3, a4, a5, a6, a7, a8, a9, a10,
           asd_level, speech_delay, motor_delay, confidence_score,
           test_data, past_therapies, feedback
    FROM test_results 
    WHERE user_id = ? 
    AND test_type = 'screening'
    AND child_name IS NOT NULL 
    AND child_name != '' 
    AND child_name != '0'
    AND (a1 IS NOT NULL OR a2 IS NOT NULL OR a3 IS NOT NULL)
    ORDER BY test_date DESC, id DESC 
    LIMIT 1
");
if (!$latestTestQuery) {
    die("Latest test query failed: " . $conn->error);
}
$latestTestQuery->bind_param("i", $userId);
$latestTestQuery->execute();
$latestTestResult = $latestTestQuery->get_result();

// Check if we have a latest test
$hasLatestTest = false;
$latestTestData = null;
if ($latestTestResult->num_rows > 0) {
    $hasLatestTest = true;
    $latestTestData = $latestTestResult->fetch_assoc();
    
    // Store the test ID for the View Latest button
    $latestTestId = $latestTestData['id'];
}

// Helper functions
function calculateASDLevelFromResponses($testData) {
    $positive_count = 0;
    for ($i = 1; $i <= 10; $i++) {
        $key = "a{$i}";
        if (isset($testData[$key]) && $testData[$key] == 1) {
            $positive_count++;
        }
    }
    
    if ($positive_count >= 8) return "3"; // Severe
    if ($positive_count >= 5) return "2"; // Moderate
    return "1"; // Mild
}

function calculateSpeechDelay($testData) {
    $speech_questions = ['a1', 'a2', 'a3', 'a10'];
    $count = 0;
    foreach ($speech_questions as $q) {
        if (isset($testData[$q]) && $testData[$q] == 1) {
            $count++;
        }
    }
    return $count >= 2 ? 1 : 0;
}

function calculateMotorDelay($testData) {
    $motor_questions = ['a8', 'a9'];
    $count = 0;
    foreach ($motor_questions as $q) {
        if (isset($testData[$q]) && $testData[$q] == 1) {
            $count++;
        }
    }
    return $count >= 1 ? 1 : 0;
}

function getTherapyPredictionForDashboard($testData, $conn, $userId) {
    // If therapy prediction already exists, return it
    if (!empty($testData['therapy_prediction'])) {
        return $testData['therapy_prediction'];
    }
    
    // If test has ID, check database
    if (isset($testData['id']) && $testData['id'] > 0) {
        $stmt = $conn->prepare("
            SELECT therapy_prediction 
            FROM test_results 
            WHERE id = ? AND therapy_prediction IS NOT NULL
        ");
        if ($stmt) {
            $stmt->bind_param("i", $testData['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row['therapy_prediction'];
            }
            $stmt->close();
        }
    }
    
    // Generate simple prediction based on ASD level
    $asdLevel = calculateASDLevelFromResponses($testData);
    $speechDelay = calculateSpeechDelay($testData);
    $motorDelay = calculateMotorDelay($testData);
    
    if ($asdLevel == '3') return 'ABA';
    if ($asdLevel == '2') {
        if ($speechDelay == 1) return 'Speech';
        if ($motorDelay == 1) return 'OT';
        return 'Play';
    }
    if ($speechDelay == 1) return 'Speech';
    if ($motorDelay == 1) return 'OT';
    return 'Social Skills';
}

function readableASDLevel($level) {
    $map = [
        "1" => "Mild",
        "2" => "Moderate", 
        "3" => "Severe"
    ];
    return isset($map[$level]) ? $map[$level] : 'N/A';
}

function getTherapyIcon($therapy) {
    $icons = [
        "ABA" => "bi-diagram-3",
        "OT" => "bi-hand-index-thumb",
        "Play" => "bi-joystick",
        "Speech" => "bi-chat-dots",
        "Music" => "bi-music-note-beamed",
        "Art" => "bi-palette",
        "Social Skills" => "bi-people",
        "Physical" => "bi-activity"
    ];
    return isset($icons[$therapy]) ? $icons[$therapy] : "bi-heart-pulse";
}

function getTherapyDescription($therapy) {
    $descriptions = [
        "ABA" => "Applied Behavior Analysis focuses on improving specific behaviors and skills through positive reinforcement.",
        "OT" => "Occupational Therapy helps develop skills for daily living, sensory processing, and fine motor coordination.",
        "Play" => "Play Therapy uses play activities to improve social, emotional, and cognitive development.",
        "Speech" => "Speech Therapy targets communication skills, language development, and speech clarity.",
        "Music" => "Music Therapy utilizes musical activities to enhance communication, social skills, and emotional expression.",
        "Art" => "Art Therapy encourages self-expression and fine motor skills through creative activities.",
        "Social Skills" => "Social Skills Training focuses on improving interaction, communication, and relationship-building abilities.",
        "Physical" => "Physical Therapy develops gross motor skills, coordination, and physical fitness."
    ];
    return isset($descriptions[$therapy]) ? $descriptions[$therapy] : "A tailored therapeutic approach based on individual needs and characteristics.";
}

function formatTime($time) {
    if (empty($time)) return '—';
    return date('g:i A', strtotime($time));
}

function formatDate($date) {
    if (empty($date)) return '—';
    return date('M d, Y', strtotime($date));
}

function getAppointmentTypeBadge($type) {
    $types = [
        'in-person' => ['label' => 'In-Person', 'class' => 'bg-primary'],
        'video' => ['label' => 'Video', 'class' => 'bg-info']
    ];
    $type = $type ?? 'in-person';
    $data = $types[$type] ?? $types['in-person'];
    return '<span class="badge ' . $data['class'] . '">' . $data['label'] . '</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - CareGuides</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(5px);
            text-decoration: none;
        }
        
        .quick-action-icon {
            width: 50px;
            height: 50px;
            background: rgba(74, 111, 165, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .test-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .test-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 3px 10px rgba(74, 111, 165, 0.1);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-positive {
            background: #d4edda;
            color: #155724;
        }
        
        .status-negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #999;
        }
        
        .prediction-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .feature-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        .latest-test-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }
        
        .test-result-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-right: 20px;
        }
        
        .therapy-recommendation {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #bae6fd;
        }
        
        .therapy-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #4a6fa5;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .test-date-badge {
            background: #e2e8f0;
            color: #475569;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        
        .appointment-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #4a6fa5;
            transition: all 0.3s;
        }
        
        .appointment-item.confirmed {
            border-left-color: #28a745;
        }
        
        .appointment-item.pending {
            border-left-color: #ffc107;
        }
        
        .appointment-time {
            font-weight: 600;
            color: #4a6fa5;
        }
        
        .appointment-doctor {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .therapy-prediction-badge {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
        
        .therapy-variety-note {
            background: #f0f9ff;
            border: 1px dashed #bae6fd;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #0369a1;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 30px 0;
            }
            
            .welcome-card {
                padding: 20px;
            }
            
            .latest-test-card {
                text-align: center;
            }
            
            .test-result-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Welcome back, <?php echo htmlspecialchars($fullName ?: $username); ?>!</h1>
                    <p class="lead mb-0">
                        <?php echo date('l, F j, Y'); ?> • 
                        <span class="badge bg-light text-primary"><?php echo ucfirst($userType); ?></span>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="profile.php" class="btn btn-light btn-lg">
                        <i class="bi bi-person-circle me-2"></i> My Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container pb-5">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h3 class="mb-3">Your Autism Care Journey</h3>
                    <p class="text-muted mb-4">
                        <?php if ($totalTests > 0): ?>
                            You have completed <?php echo $totalTests; ?> screening test(s) and booked <?php echo $appointmentsCount; ?> appointment(s). 
                            Continue monitoring your child's development and book consultations as needed.
                        <?php else: ?>
                            You haven't completed any screening tests yet. Start by taking our comprehensive autism screening test.
                        <?php endif; ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="test.php" class="btn btn-primary px-4">
                            <i class="bi bi-plus-circle me-2"></i> New Test
                        </a>
                        <a href="book_appointment.php" class="btn btn-outline-primary px-4">
                            <i class="bi bi-calendar-plus me-2"></i> Book Appointment
                        </a>
                        <a href="reports.php" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-download me-2"></i> Download Reports
                        </a>
                        <a href="./Therapy/details.php" class="btn btn-outline-success px-4">
                            <i class="bi bi-heart-pulse me-2"></i> Therapy Assessment
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0">
                    <img src="assets/img/dashboard-illustration.svg" alt="Dashboard" class="img-fluid" 
                         style="max-height: 150px;" onerror="this.style.display='none'">
                    <?php if (!file_exists('assets/img/dashboard-illustration.svg')): ?>
                        <div class="display-1 text-primary">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(74, 111, 165, 0.1); color: var(--primary-color);">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $totalTests; ?></div>
                    <div class="stat-label">Tests Completed</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(22, 96, 136, 0.1); color: var(--secondary-color);">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $appointmentsCount; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $confirmedAppointments; ?></div>
                    <div class="stat-label">Confirmed Appointments</div>
                </div>
            </div>
        </div>
        
        <!-- LATEST TEST RESULTS - FIXED -->
        <?php if ($hasLatestTest): 
            // Calculate values from test data
            $asdLevel = calculateASDLevelFromResponses($latestTestData);
            $asdLevelDisplay = readableASDLevel($asdLevel);
            $speechDelay = calculateSpeechDelay($latestTestData);
            $motorDelay = calculateMotorDelay($latestTestData);
            
            // Get prediction result
            $prediction = $latestTestData['prediction_result'] ?? null;
            if (empty($prediction) && isset($latestTestData['asd_status'])) {
                $prediction = $latestTestData['asd_status'] == 1 ? 'Positive' : 'Negative';
            } elseif (empty($prediction)) {
                // Calculate based on responses
                $positive_responses = 0;
                for ($i = 1; $i <= 10; $i++) {
                    $key = "a{$i}";
                    if (isset($latestTestData[$key]) && $latestTestData[$key] == 1) {
                        $positive_responses++;
                    }
                }
                $prediction = $positive_responses >= 5 ? 'Positive' : 'Negative';
            }
            
            // Get therapy prediction
            $therapyPrediction = getTherapyPredictionForDashboard($latestTestData, $conn, $userId);
            
            // Calculate confidence score
            $positive_responses = 0;
            for ($i = 1; $i <= 10; $i++) {
                $key = "a{$i}";
                if (isset($latestTestData[$key]) && $latestTestData[$key] == 1) {
                    $positive_responses++;
                }
            }
            $confidenceScore = $latestTestData['confidence_score'] ?? round(($positive_responses / 10) * 100, 0);
        ?>
        <div class="latest-test-card">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center mb-3">
                        <div class="test-result-icon" style="background: rgba(74, 111, 165, 0.1); color: var(--primary-color);">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <div>
                            <h4 class="mb-1">Latest Test Result</h4>
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <span class="test-date-badge">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?php echo date('M d, Y', strtotime($latestTestData['test_date'])); ?>
                                </span>
                                <?php if ($prediction): ?>
                                    <span class="prediction-badge"><?php echo htmlspecialchars($prediction); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($therapyPrediction): ?>
                                    <span class="therapy-prediction-badge">
                                        <i class="bi <?php echo getTherapyIcon($therapyPrediction); ?> me-1"></i>
                                        <?php echo htmlspecialchars($therapyPrediction); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-2">
                                <small class="text-muted d-block">Child Name</small>
                                <strong><?php echo htmlspecialchars($latestTestData['child_name'] ?? 'Not specified'); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <small class="text-muted d-block">Child Age</small>
                                <strong><?php echo htmlspecialchars($latestTestData['child_age'] ?? 'N/A'); ?> years</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <small class="text-muted d-block">Confidence</small>
                                <strong><?php echo $confidenceScore; ?>%</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="feature-badge">
                                <i class="bi bi-graph-up me-1"></i>
                                ASD Level: <?php echo $asdLevelDisplay; ?>
                            </span>
                            
                            <?php if ($speechDelay == 1): ?>
                            <span class="feature-badge">
                                <i class="bi bi-chat-dots me-1"></i>Speech Delay
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($motorDelay == 1): ?>
                            <span class="feature-badge">
                                <i class="bi bi-activity me-1"></i>Motor Delay
                            </span>
                            <?php endif; ?>
                            
                            <?php if (isset($latestTestData['a6']) && $latestTestData['a6'] == 1): ?>
                            <span class="feature-badge">
                                <i class="bi bi-emoji-dizzy me-1"></i>Sensory Issues
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (isset($latestTestData['id']) && $latestTestData['id'] > 0): ?>
                            <a href="test-details.php?id=<?php echo $latestTestData['id']; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        <?php else: ?>
                            <a href="test-details.php?date=<?php echo urlencode($latestTestData['test_date']); ?>&child=<?php echo urlencode($latestTestData['child_name']); ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        <?php endif; ?>
                        
                        <?php if (isset($latestTestData['id']) && $latestTestData['id'] > 0): ?>
                            <a href="generate-report.php?test_id=<?php echo $latestTestData['id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1"></i> Download Report
                            </a>
                        <?php endif; ?>
                        
                        <a href="book_appointment.php?ref=latest_test" 
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-calendar-plus me-1"></i> Book Consultation
                        </a>
                        
                        <?php if (isset($latestTestData['id']) && $latestTestData['id'] > 0): ?>
                            <a href="./Therapy/details.php?test_id=<?php echo $latestTestData['id']; ?>" 
                               class="btn btn-outline-success btn-sm">
                                <i class="bi bi-heart-pulse me-1"></i> Therapy Assessment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0">
                    <div class="therapy-recommendation">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-clipboard-check me-2"></i>Therapy Prediction
                        </h6>
                        
                        <?php if ($therapyPrediction): ?>
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="me-3">
                                    <div class="therapy-icon">
                                        <i class="bi <?php echo getTherapyIcon($therapyPrediction); ?>"></i>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="text-dark mb-1"><?php echo htmlspecialchars($therapyPrediction); ?></h5>
                                    <small class="text-muted">Recommended Therapy Approach</small>
                                </div>
                            </div>
                            <p class="text-muted small">
                                <i class="bi bi-info-circle me-1"></i>
                                <?php echo getTherapyDescription($therapyPrediction); ?>
                            </p>
                            
                            <!-- Explanation of prediction -->
                            <div class="therapy-variety-note">
                                <i class="bi bi-lightbulb me-1"></i>
                                <?php 
                                $explanations = [
                                    'Speech' => 'Recommended for speech/communication challenges',
                                    'OT' => 'Recommended for motor/sensory integration needs',
                                    'ABA' => 'Recommended for behavioral intervention',
                                    'Play' => 'Recommended for social/emotional development',
                                    'Music' => 'Recommended for communication through music',
                                    'Art' => 'Recommended for creative expression',
                                    'Social Skills' => 'Recommended for social interaction improvement',
                                    'Physical' => 'Recommended for motor development'
                                ];
                                echo isset($explanations[$therapyPrediction]) 
                                    ? $explanations[$therapyPrediction] 
                                    : 'Tailored to individual needs';
                                ?>
                            </div>
                            
                            <!-- Link to Therapy Module -->
                            <div class="mt-3">
                                <a href="./Therapy/test.php" class="btn btn-sm btn-primary w-100">
                                    <i class="bi bi-heart-pulse me-1"></i> View All Therapy Tests
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-clipboard-x fs-1 text-muted mb-3"></i>
                                <p class="text-muted">No therapy prediction available</p>
                                <?php if (isset($latestTestData['id']) && $latestTestData['id'] > 0): ?>
                                    <a href="./Therapy/details.php?test_id=<?php echo $latestTestData['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-magic me-1"></i> Generate Prediction
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- No valid tests found -->
        <div class="alert alert-info">
            <h5>No Screening Tests Completed</h5>
            <p>You haven't completed any autism screening tests yet.</p>
            <a href="test.php" class="btn btn-primary">Take Your First Test</a>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- Left Column: Quick Actions & Recent Tests -->
            <div class="col-lg-8">
                <!-- Quick Actions -->
                <div class="section-card mb-4">
                    <h4 class="section-title">
                        <i class="bi bi-lightning me-2"></i> Quick Actions
                    </h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="test.php" class="quick-action">
                                <div class="quick-action-icon" style="color: var(--primary-color);">
                                    <i class="bi bi-clipboard-plus"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">New Screening Test</h6>
                                    <small class="text-muted">Take a new autism screening test</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="./Therapy/details.php" class="quick-action">
                                <div class="quick-action-icon" style="color: #8b5cf6;">
                                    <i class="bi bi-heart-pulse"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Therapy Assessment</h6>
                                    <small class="text-muted">Get therapy recommendations</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6">
                            <a href="book_appointment.php" class="quick-action">
                                <div class="quick-action-icon" style="color: var(--secondary-color);">
                                    <i class="bi bi-calendar-plus"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Book Appointment</h6>
                                    <small class="text-muted">Schedule with a specialist</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6">
                            <a href="appointments.php" class="quick-action">
                                <div class="quick-action-icon" style="color: #28a745;">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">My Appointments</h6>
                                    <small class="text-muted">View all appointments</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6">
                            <a href="therapy-resources.php" class="quick-action">
                                <div class="quick-action-icon" style="color: #f59e0b;">
                                    <i class="bi bi-book"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Therapy Resources</h6>
                                    <small class="text-muted">Access therapy materials</small>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-md-6">
                            <a href="test-history.php" class="quick-action">
                                <div class="quick-action-icon" style="color: #ef4444;">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">View Progress</h6>
                                    <small class="text-muted">Track test results over time</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Tests -->
                <div class="section-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="section-title mb-0">
                            <i class="bi bi-clock-history me-2"></i> Recent Tests
                        </h4>
                        <div class="d-flex gap-2">
                            <a href="test-history.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-list-check me-1"></i> View All History
                            </a>
                            <?php if ($hasLatestTest && isset($latestTestId)): ?>
                                <a href="test-details.php?id=<?php echo $latestTestId; ?>" 
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye me-1"></i> View Latest Details
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($testHistory->num_rows > 0): ?>
                        <div class="test-list">
                            <?php 
                            $testHistory->data_seek(0);
                            $displayedTests = 0;
                            while ($test = $testHistory->fetch_assoc()): 
                                // Skip if this is the latest test already shown
                                if ($hasLatestTest && isset($latestTestData['test_date']) && 
                                    $test['test_date'] == $latestTestData['test_date'] && 
                                    $test['child_name'] == $latestTestData['child_name']) {
                                    continue;
                                }
                                
                                $displayedTests++;
                                if ($displayedTests > 5) break;
                                
                                // Calculate values for display
                                $testASDLevel = calculateASDLevelFromResponses($test);
                                $testSpeechDelay = calculateSpeechDelay($test);
                                $testMotorDelay = calculateMotorDelay($test);
                                $testTherapyPrediction = getTherapyPredictionForDashboard($test, $conn, $userId);
                                
                                // Get prediction
                                $testPrediction = $test['prediction_result'] ?? null;
                                if (empty($testPrediction) && isset($test['asd_status'])) {
                                    $testPrediction = $test['asd_status'] == 1 ? 'Positive' : 'Negative';
                                }
                            ?>
                            <div class="test-item">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($test['child_name'] ?: 'Unnamed Child'); ?></h6>
                                        <small class="text-muted">Age: <?php echo $test['child_age'] ?: 'N/A'; ?> years</small>
                                    </div>
                                    <div class="col-md-3">
                                        <?php if ($testPrediction): ?>
                                            <span class="badge bg-<?php echo $testPrediction == 'Positive' ? 'success' : 'danger'; ?>">
                                                <?php echo htmlspecialchars($testPrediction); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo date('M d, Y', strtotime($test['test_date'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <?php if (isset($test['id']) && $test['id'] > 0): ?>
                                            <a href="test-details.php?id=<?php echo $test['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        <?php else: ?>
                                            <a href="test-details.php?date=<?php echo urlencode($test['test_date']); ?>&child=<?php echo urlencode($test['child_name']); ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <?php if ($testTherapyPrediction): ?>
                                            <span class="therapy-prediction-badge me-3">
                                                <i class="bi <?php echo getTherapyIcon($testTherapyPrediction); ?> me-1"></i>
                                                <?php echo htmlspecialchars($testTherapyPrediction); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted me-3">
                                            <i class="bi bi-graph-up me-1"></i>
                                            ASD: <?php echo readableASDLevel($testASDLevel); ?>
                                        </small>
                                        
                                        <?php if ($testSpeechDelay == 1): ?>
                                        <small class="text-muted me-3">
                                            <i class="bi bi-chat-dots me-1"></i>
                                            Speech: Delayed
                                        </small>
                                        <?php endif; ?>
                                        
                                        <?php if ($testMotorDelay == 1): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-activity me-1"></i>
                                            Motor: Delayed
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <?php if ($displayedTests == 0): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-clipboard-x"></i>
                                    </div>
                                    <p class="text-muted">No other tests found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-clipboard-x"></i>
                            </div>
                            <h5 class="mb-3">No Tests Completed Yet</h5>
                            <p class="text-muted mb-4">You haven't completed any screening tests yet.</p>
                            <a href="test.php" class="btn btn-primary">Take Your First Test</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column: Upcoming Appointments & Recent Activity -->
            <div class="col-lg-4">
                <!-- Upcoming Appointments -->
                <div class="section-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="section-title mb-0">
                            <i class="bi bi-calendar-event me-2"></i> Upcoming Appointments
                        </h4>
                        <a href="appointments.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    
                    <?php if ($appointments && $appointments->num_rows > 0): ?>
                        <div class="appointment-list">
                            <?php while ($appointment = $appointments->fetch_assoc()): 
                                $status_class = $appointment['status'] === 'confirmed' ? 'confirmed' : 'pending';
                            ?>
                            <div class="appointment-item <?php echo $status_class; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="appointment-doctor">
                                            <?php echo htmlspecialchars($appointment['doctor']); ?>
                                        </div>
                                        <div class="appointment-time">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo formatTime($appointment['appointment_time']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo formatDate($appointment['appointment_date']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php echo getAppointmentTypeBadge($appointment['consult_type']); ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small>
                                            <i class="bi bi-person me-1"></i>
                                            <?php echo htmlspecialchars($appointment['child_name']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php echo $appointment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <a href="appointment-details.php?id=<?php echo $appointment['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i> Details
                                    </a>
                                    <?php if ($appointment['status'] === 'pending'): ?>
                                    <a href="book_appointment.php?reschedule=<?php echo $appointment['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-calendar-check me-1"></i> Reschedule
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h5 class="mb-3">No Upcoming Appointments</h5>
                            <p class="text-muted mb-4">You haven't booked any appointments yet.</p>
                            <a href="book_appointment.php" class="btn btn-primary btn-sm">Book Now</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activity -->
                <div class="section-card">
                    <h4 class="section-title">
                        <i class="bi bi-activity me-2"></i> Recent Activity
                    </h4>
                    
                    <div class="activity-list">
                        <?php if ($activities && $activities->num_rows > 0): ?>
                            <?php while ($activity = $activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1" style="font-size: 0.95rem;">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                        </h6>
                                        <?php if ($activity['activity_details']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($activity['activity_details'], 0, 60)) . (strlen($activity['activity_details']) > 60 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="activity-time">
                                        <?php echo date('M j', strtotime($activity['created_at'])); ?><br>
                                        <?php echo date('g:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-database-slash"></i>
                                </div>
                                <h5 class="mb-3">Activity Tracking</h5>
                                <p class="text-muted mb-3">Activity tracking is not currently available.</p>
                                <small class="text-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This feature requires database setup
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Tips -->
                <div class="section-card mt-4">
                    <h4 class="section-title">
                        <i class="bi bi-lightbulb me-2"></i> Quick Tips
                    </h4>
                    <div class="alert alert-info border-0 bg-light">
                        <small>
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Tip:</strong> Regular screening every 3-6 months helps track progress effectively.
                        </small>
                    </div>
                    <div class="alert alert-success border-0 bg-light">
                        <small>
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Remember:</strong> Different therapy types address different needs (Speech, OT, ABA, Play, etc.).
                        </small>
                    </div>
                    <div class="alert alert-warning border-0 bg-light">
                        <small>
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Therapy predictions are based on age, ASD level, delays, and challenges.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update local time
        function updateLocalTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const timeElements = document.querySelectorAll('.current-time');
            if (timeElements.length > 0) {
                timeElements.forEach(el => {
                    el.textContent = now.toLocaleDateString('en-US', options);
                });
            }
        }
        
        // Update time every minute
        setInterval(updateLocalTime, 60000);
        updateLocalTime();
        
        // Add hover effects
        document.querySelectorAll('.quick-action').forEach(action => {
            action.addEventListener('mouseenter', function() {
                const icon = this.querySelector('.quick-action-icon');
                icon.style.transform = 'scale(1.1)';
                icon.style.transition = 'transform 0.3s';
            });
            
            action.addEventListener('mouseleave', function() {
                const icon = this.querySelector('.quick-action-icon');
                icon.style.transform = 'scale(1)';
            });
        });
        
        // Animate stat cards on scroll
        function animateCards() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        // Initial animation
        setTimeout(animateCards, 300);
        
        // Appointment reminder for today's appointments
        document.addEventListener('DOMContentLoaded', function() {
            const appointmentItems = document.querySelectorAll('.appointment-item');
            const today = new Date();
            const todayFormatted = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            
            appointmentItems.forEach(item => {
                const dateText = item.querySelector('small.text-muted')?.textContent;
                
                if (dateText && dateText.includes(todayFormatted)) {
                    item.style.border = '2px solid #ffc107';
                    item.style.animation = 'pulse 2s infinite';
                    
                    // Add CSS animation
                    if (!document.querySelector('#pulse-animation')) {
                        const style = document.createElement('style');
                        style.id = 'pulse-animation';
                        style.textContent = `
                            @keyframes pulse {
                                0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
                                70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
                                100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
                            }
                        `;
                        document.head.appendChild(style);
                    }
                }
            });
        });
    </script>
</body>
</html>