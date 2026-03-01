<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
$configPath = __DIR__ . '/config/database.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    $configPath = __DIR__ . '/../config/database.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        die("Database configuration file not found.");
    }
}

// Include therapy functions
$therapyFunctionsPath = __DIR__ . '/fetch-therapy.php';
if (file_exists($therapyFunctionsPath)) {
    require_once $therapyFunctionsPath;
} else {
    $therapyFunctionsPath = __DIR__ . '/../fetch-therapy.php';
    if (file_exists($therapyFunctionsPath)) {
        require_once $therapyFunctionsPath;
    }
}

// Check if form was submitted from Therapy/details.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'therapy_details') {
    // Process therapy form submission
    $data = [
        "Age" => $_POST["Age"],
        "ASD_Level" => $_POST["ASD_Level"],
        "Speech_Delay" => $_POST["Speech_Delay"],
        "Motor_Delay" => $_POST["Motor_Delay"],
        "Feedback" => $_POST["Feedback"],
        "Past_Therapies" => isset($_POST["Past_Therapies"]) ? $_POST["Past_Therapies"] : [],
        "Challenges" => isset($_POST["Challenges"]) ? $_POST["Challenges"] : [],
        "child_name" => $_POST["child_name"] ?? 'Child from Therapy Form'
    ];

    $json_data = json_encode($data);

    // Call ML API
    $ch = curl_init("http://localhost:5002/predict");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // Save to database
    $therapyPrediction = null;
    if (isset($result['prediction'])) {
        $therapyPrediction = $result['prediction'];
        
        $childName = $_POST["child_name"] ?: "Child from Therapy Form";
        
        // Convert arrays to strings
        $pastTherapiesStr = !empty($data["Past_Therapies"]) ? implode(';', $data["Past_Therapies"]) : null;
        $challengesStr = !empty($data["Challenges"]) ? implode(';', $data["Challenges"]) : null;
        
        // Store the form data as JSON
        $testDataJson = json_encode($data);
        
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO test_results (
                user_id, child_name, child_age, asd_level, 
                speech_delay, motor_delay, feedback, past_therapies,
                challenges, therapy_prediction, test_type, test_data, confidence_score
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'therapy', ?, ?)
        ");
        
        $userId = $_SESSION['user_id'] ?? 2;
        $childAge = $data["Age"];
        $asdLevel = $data["ASD_Level"];
        $speechDelay = $data["Speech_Delay"];
        $motorDelay = $data["Motor_Delay"];
        $feedback = $data["Feedback"];
        $confidenceScore = 85;
        
        $stmt->bind_param(
            "isiiiiissssi",
            $userId,
            $childName,
            $childAge,
            $asdLevel,
            $speechDelay,
            $motorDelay,
            $feedback,
            $pastTherapiesStr,
            $challengesStr,
            $therapyPrediction,
            $testDataJson,
            $confidenceScore
        );
        
        if ($stmt->execute()) {
            $testId = $stmt->insert_id;
            $_SESSION['last_therapy_test_id'] = $testId;
            // Redirect to show the newly created test
            header("Location: test-details.php?id=$testId&tab=therapy");
            exit();
        }
        
        $stmt->close();
    }
}

// Get parameters from URL
$test_id = $_GET['id'] ?? 0;
$test_date = $_GET['date'] ?? '';
$child_name = $_GET['child'] ?? '';
$tab = $_GET['tab'] ?? 'screening'; // 'screening' or 'therapy'
$userId = $_SESSION['user_id'] ?? 0;

// If no parameters, redirect
if (!$test_id && !$test_date && !$child_name) {
    header('Location: test-history.php');
    exit();
}

// Get test details
$test = null;
if ($test_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.*, u.username, u.full_name 
        FROM test_results t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $test_id);
} else {
    $stmt = $conn->prepare("
        SELECT t.*, u.username, u.full_name 
        FROM test_results t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.child_name = ? AND t.test_date = ?
        ORDER BY t.test_date DESC
        LIMIT 1
    ");
    $stmt->bind_param("ss", $child_name, $test_date);
}

$stmt->execute();
$result = $stmt->get_result();
$test = $result->fetch_assoc();

if (!$test) {
    echo "<div class='container mt-5'>
        <div class='alert alert-danger'>
            <h4>Test Not Found</h4>
            <p>Test not found in database.</p>
            <p><a href='test-history.php' class='btn btn-primary'>Go to Test History</a></p>
        </div>
    </div>";
    exit();
}

// Update test_id with actual value
$test_id = $test['id'] ?? 0;

// Fetch therapy prediction if exists
$therapyPrediction = null;
$therapyData = null;

// NEW: Unified function to get therapy prediction (same as dashboard)
function getUnifiedTherapyPrediction($test, $conn, $userId) {
    // Priority 1: Direct column value
    if (!empty($test['therapy_prediction'])) {
        return [
            'prediction' => $test['therapy_prediction'],
            'data' => !empty($test['therapy_data']) ? json_decode($test['therapy_data'], true) : null
        ];
    }
    
    // Priority 2: From test_data JSON
    if (!empty($test['test_data'])) {
        $testData = json_decode($test['test_data'], true);
        if (isset($testData['therapy_prediction'])) {
            return [
                'prediction' => $testData['therapy_prediction'],
                'data' => $testData
            ];
        }
    }
    
    // Priority 3: Generate if screening test
    $test_type = $test['test_type'] ?? 'screening';
    if ($test_type == 'screening' && function_exists('generateTherapyFromScreeningData')) {
        $generated = generateTherapyFromScreeningData($test, $conn, $userId);
        
        if ($generated) {
            return [
                'prediction' => $generated,
                'data' => null,
                'generated' => true
            ];
        }
    }
    
    return null;
}

// Get therapy prediction using unified function
$unifiedTherapy = getUnifiedTherapyPrediction($test, $conn, $userId);
$therapyPrediction = $unifiedTherapy['prediction'] ?? null;
$therapyData = $unifiedTherapy['data'] ?? null;

// If still no therapy prediction but it's a screening test, try to generate
if (!$therapyPrediction && ($test['test_type'] ?? 'screening') == 'screening' && function_exists('getTherapyFromScreening')) {
    $generatedTherapy = getTherapyFromScreening($conn, $test_id, $userId);
    if ($generatedTherapy && isset($generatedTherapy['prediction'])) {
        $therapyPrediction = $generatedTherapy['prediction'];
        $therapyData = $generatedTherapy['data'] ?? null;
        
        // Reload the test to get updated data
        $stmt = $conn->prepare("SELECT therapy_prediction, therapy_data FROM test_results WHERE id = ?");
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $newResult = $stmt->get_result();
        if ($newTest = $newResult->fetch_assoc()) {
            $therapyPrediction = $newTest['therapy_prediction'];
            if (!empty($newTest['therapy_data'])) {
                $therapyData = json_decode($newTest['therapy_data'], true);
            }
        }
    }
}

// Questions array for screening test
$questions = [
    'a1' => "Does the child look at you when you call his/her name?",
    'a2' => "Does the child point at objects to show interest?",
    'a3' => "Does the child pretend play (e.g., pretend to drink from an empty cup)?",
    'a4' => "Does the child use single words meaningfully?",
    'a5' => "Does the child respond to their name?",
    'a6' => "Does the child make eye contact when interacting?",
    'a7' => "Does the child show interest in other children?",
    'a8' => "Does the child bring objects to show parents?",
    'a9' => "Does the child follow simple instructions?",
    'a10' => "Does the child engage in back-and-forth social interaction?"
];

// Calculate screening results
$no_responses = 0;
for ($i = 1; $i <= 10; $i++) {
    $key = "a{$i}";
    if (isset($test[$key]) && $test[$key] == 1) {
        $no_responses++;
    }
}

// Determine ASD level
$asdLevel = "1";
if ($no_responses >= 8) {
    $asdLevel = "3";
} elseif ($no_responses >= 5) {
    $asdLevel = "2";
}

// Get prediction result
$prediction_result = 'Negative';
if ($no_responses >= 5) {
    $prediction_result = 'Positive';
}

// Override with database value if exists
if (!empty($test['prediction_result'])) {
    $prediction_result = $test['prediction_result'];
} elseif (isset($test['asd_status'])) {
    $prediction_result = $test['asd_status'] == 1 ? 'Positive' : 'Negative';
}

$is_positive = $prediction_result === 'Positive';
$score_percentage = ($no_responses / 10) * 100;

// Decode test_data if exists
$testData = [];
if (!empty($test['test_data'])) {
    $testData = json_decode($test['test_data'], true);
}

// Get test type
$test_type = $test['test_type'] ?? 'screening';

// Helper functions

function readable($key, $value) {
    $map = [
        "ASD_Level" => ["1" => "Mild", "2" => "Moderate", "3" => "Severe"],
        "Speech_Delay" => ["1" => "Yes", "0" => "No"],
        "Motor_Delay" => ["1" => "Yes", "0" => "No"],
    ];
    return isset($map[$key][$value]) ? $map[$key][$value] : $value;
}

// NEW: Helper function to calculate speech delay from test data
function calculateSpeechDelay($test) {
    $speech_questions = ['a1', 'a2', 'a3', 'a10'];
    $count = 0;
    foreach ($speech_questions as $q) {
        if (isset($test[$q]) && $test[$q] == 1) {
            $count++;
        }
    }
    return $count >= 2 ? 1 : 0;
}

// NEW: Helper function to calculate motor delay from test data
function calculateMotorDelay($test) {
    $motor_questions = ['a8', 'a9'];
    $count = 0;
    foreach ($motor_questions as $q) {
        if (isset($test[$q]) && $test[$q] == 1) {
            $count++;
        }
    }
    return $count >= 1 ? 1 : 0;
}

// Calculate delays for display
$speechDelay = calculateSpeechDelay($test);
$motorDelay = calculateMotorDelay($test);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($test['child_name'] ?? 'Test Details'); ?> - CareGuides</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            background: #f5f7fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        .page-header { 
            background: linear-gradient(135deg, #4a6fa5, #166088); 
            color: #fff; 
            padding: 20px 0; 
        }
        .card { 
            border-radius: 10px; 
            border: none; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
            border: none;
            padding: 12px 24px;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #4a6fa5, #166088);
            color: white;
            border-radius: 8px;
        }
        .result-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .badge-positive {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        .badge-negative {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .badge-neutral {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        .therapy-card {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-left: 5px solid #1976d2;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .feature-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .question-row {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }
        .question-row:last-child {
            border-bottom: none;
        }
        .response-yes {
            color: #059669;
            font-weight: 600;
        }
        .response-no {
            color: #dc2626;
            font-weight: 600;
        }
        .therapy-icon-large {
            font-size: 3rem;
            color: #1976d2;
            margin-bottom: 15px;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4a6fa5;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .therapy-prediction-badge {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        <?php echo htmlspecialchars($test['child_name'] ?? 'Test Details'); ?>
                    </h1>
                    <small class="text-light">
                        Test ID: #<?php echo $test_id; ?> • 
                        Date: <?php echo date('F j, Y', strtotime($test['test_date'])); ?> •
                        Type: <?php echo ucfirst($test_type); ?>
                    </small>
                </div>
                <div>
                    <a href="test-history.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container py-4">
        <!-- Test Header with Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $no_responses; ?>/10</div>
                    <div class="stat-label">ASD Indicators</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo readableASDLevel($asdLevel); ?></div>
                    <div class="stat-label">ASD Level</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo round($score_percentage, 1); ?>%</div>
                    <div class="stat-label">Confidence</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value">
                        <span class="result-badge badge-<?php echo strtolower($prediction_result); ?>">
                            <?php echo $prediction_result; ?>
                        </span>
                    </div>
                    <div class="stat-label">Result</div>
                </div>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="testTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $tab == 'screening' ? 'active' : ''; ?>" 
                        id="screening-tab" data-bs-toggle="tab" data-bs-target="#screening" 
                        type="button" role="tab">
                    <i class="fas fa-clipboard-check me-2"></i> Screening Results
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $tab == 'therapy' ? 'active' : ''; ?>" 
                        id="therapy-tab" data-bs-toggle="tab" data-bs-target="#therapy" 
                        type="button" role="tab">
                    <i class="fas fa-heartbeat me-2"></i> Therapy Recommendation
                    <?php if ($therapyPrediction): ?>
                    <span class="therapy-prediction-badge ms-2">
                        <?php echo $therapyPrediction; ?>
                    </span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="testTabsContent">
            <!-- Screening Tab -->
            <div class="tab-pane fade <?php echo $tab == 'screening' ? 'show active' : ''; ?>" 
                 id="screening" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-chart-bar me-2"></i> Autism Screening Results
                        </h4>
                        
                        <!-- Child Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i> Child Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($test['child_name']); ?></p>
                                    <p class="mb-1"><strong>Age:</strong> <?php echo htmlspecialchars($test['child_age'] ?? 'N/A'); ?> years</p>
                                    <p class="mb-0"><strong>Test Date:</strong> <?php echo date('F j, Y', strtotime($test['test_date'])); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="mb-2"><i class="fas fa-chart-line me-2"></i> Summary</h6>
                                    <p class="mb-1"><strong>ASD Level:</strong> 
                                        <span class="badge bg-info">
                                            Level <?php echo $asdLevel; ?> - <?php echo readableASDLevel($asdLevel); ?>
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>Screening Result:</strong> 
                                        <span class="result-badge badge-<?php echo strtolower($prediction_result); ?>">
                                            <?php echo $prediction_result; ?>
                                        </span>
                                    </p>
                                    <p class="mb-0"><strong>Confidence:</strong> <?php echo round($score_percentage, 1); ?>%</p>
                                    
                                    <?php if ($therapyPrediction): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block">Therapy Recommendation:</small>
                                        <span class="therapy-prediction-badge">
                                            <i class="<?php echo getTherapyIcon($therapyPrediction); ?> me-1"></i>
                                            <?php echo $therapyPrediction; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Features Detected -->
                        <?php 
                        // Detect features from screening
                        $features = [];
                        
                        if ($speechDelay == 1) {
                            $features[] = 'Speech Delay';
                        }
                        
                        if ($motorDelay == 1) {
                            $features[] = 'Motor Delay';
                        }
                        
                        // Sensory issues
                        if (isset($test['a6']) && $test['a6'] == 1) {
                            $features[] = 'Sensory Issues';
                        }
                        
                        // Social difficulty
                        if (isset($test['a7']) && $test['a7'] == 1) {
                            $features[] = 'Social Difficulty';
                        }
                        
                        // Behavioral issues
                        if (isset($test['a10']) && $test['a10'] == 1) {
                            $features[] = 'Behavioral Issues';
                        }
                        ?>
                        
                        <?php if (!empty($features)): ?>
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-tasks me-2"></i> Features Detected</h6>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($features as $feature): ?>
                                <span class="feature-badge">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo $feature; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Question Responses -->
                        <h6 class="mb-3"><i class="fas fa-question-circle me-2"></i> Question Responses</h6>
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Question</th>
                                        <th width="15%">Response</th>
                                        <th width="10%">Indicator</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($questions as $key => $question): 
                                    $value = $test[$key] ?? null;
                                    $text = ($value === 1) ? 'No' : (($value === 0) ? 'Yes' : 'Not Answered');
                                    $class = ($value === 1) ? 'response-no' : (($value === 0) ? 'response-yes' : 'text-muted');
                                    $is_indicator = ($value == 1);
                                    ?>
                                    <tr class="question-row">
                                        <td><?php echo $i; ?></td>
                                        <td><?php echo $question; ?></td>
                                        <td>
                                            <span class="<?php echo $class; ?>">
                                                <?php echo $text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($is_indicator): ?>
                                            <span class="badge bg-danger">ASD Indicator</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php $i++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mt-4 flex-wrap">
                            <a href="generate-report.php?test_id=<?php echo $test_id; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-download me-2"></i> Download Report
                            </a>
                            <a href="book_appointment.php?test_id=<?php echo $test_id; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-calendar-plus me-2"></i> Book Consultation
                            </a>
                            <?php if ($test_type == 'screening' && !$therapyPrediction): ?>
                            <a href="./Therapy/details.php?test_id=<?php echo $test_id; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-heartbeat me-2"></i> Get Therapy Recommendation
                            </a>
                            <?php endif; ?>
                            <?php if ($test_type == 'therapy' && isset($_SESSION['last_therapy_test_id'])): ?>
                            <a href="./Therapy/details.php" class="btn btn-warning">
                                <i class="fas fa-redo me-2"></i> New Therapy Assessment
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($therapyPrediction): ?>
                            <a href="#therapy" class="btn btn-info" onclick="document.querySelector('#therapy-tab').click();">
                                <i class="fas fa-heartbeat me-2"></i> View Therapy Details
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Therapy Tab -->
            <div class="tab-pane fade <?php echo $tab == 'therapy' ? 'show active' : ''; ?>" 
                 id="therapy" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-heartbeat me-2"></i> Therapy Recommendation
                        </h4>
                        
                        <?php if ($therapyPrediction): ?>
                        <!-- Therapy Recommendation Card -->
                        <div class="therapy-card">
                            <div class="text-center mb-4">
                                <div class="therapy-icon-large">
                                    <i class="<?php echo getTherapyIcon($therapyPrediction); ?>"></i>
                                </div>
                                <h3 class="text-dark mb-2"><?php echo $therapyPrediction; ?> Therapy</h3>
                                <p class="text-muted">
                                    <?php echo getTherapyDescription($therapyPrediction); ?>
                                </p>
                            </div>
                            
                            <!-- Assessment Factors -->
                            <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i> Assessment Factors</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Child's Name</small>
                                        <strong><?php echo htmlspecialchars($test['child_name']); ?></strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Child's Age</small>
                                        <strong><?php echo $therapyData['Age'] ?? ($test['child_age'] ?? 'N/A'); ?> years</strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">ASD Level</small>
                                        <strong><?php echo readableASDLevel($therapyData['ASD_Level'] ?? $asdLevel); ?></strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Speech Delay</small>
                                        <strong><?php echo ($therapyData['Speech_Delay'] ?? $speechDelay) == '1' ? 'Yes' : 'No'; ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Motor Delay</small>
                                        <strong><?php echo ($therapyData['Motor_Delay'] ?? $motorDelay) == '1' ? 'Yes' : 'No'; ?></strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Feedback</small>
                                        <span class="result-badge badge-<?php echo $therapyData['Feedback'] ?? 'neutral'; ?>">
                                            <?php echo ucfirst($therapyData['Feedback'] ?? 'neutral'); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($therapyData['Past_Therapies'])): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Past Therapies</small>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <?php if (is_array($therapyData['Past_Therapies'])): ?>
                                                <?php foreach ($therapyData['Past_Therapies'] as $therapy): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($therapy); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($therapyData['Past_Therapies']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($therapyData['Challenges'])): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Challenges</small>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            <?php if (is_array($therapyData['Challenges'])): ?>
                                                <?php foreach ($therapyData['Challenges'] as $challenge): ?>
                                                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($challenge); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($therapyData['Challenges']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Next Steps -->
                            <div class="mt-4 pt-3 border-top">
                                <h6><i class="fas fa-clock me-2"></i> Next Steps</h6>
                                <p class="mb-3">Based on this recommendation, consider:</p>
                                <ul class="mb-0">
                                    <li>Consulting with a certified <?php echo $therapyPrediction; ?> therapist</li>
                                    <li>Developing a personalized treatment plan</li>
                                    <li>Scheduling regular therapy sessions (3-5 times per week recommended)</li>
                                    <li>Monitoring progress and adjusting as needed</li>
                                    <li>Expected duration: 6-12 months for noticeable improvement</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="find-therapist.php?specialty=<?php echo urlencode($therapyPrediction); ?>" 
                               class="btn btn-success">
                                <i class="fas fa-user-md me-2"></i> Find Therapist
                            </a>
                            <a href="therapy-resources.php?therapy=<?php echo urlencode($therapyPrediction); ?>" 
                               class="btn btn-outline-info">
                                <i class="fas fa-book me-2"></i> Resources
                            </a>
                            <a href="generate-report.php?test_id=<?php echo $test_id; ?>&type=therapy" 
                               class="btn btn-outline-secondary">
                                <i class="fas fa-download me-2"></i> Download Report
                            </a>
                            <a href="./Therapy/details.php?test_id=<?php echo $test_id; ?>&regenerate=true" 
                               class="btn btn-outline-warning">
                                <i class="fas fa-redo me-2"></i> Regenerate
                            </a>
                            <a href="./Therapy/details.php" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-plus-circle me-2"></i> New Assessment
                            </a>
                        </div>
                        
                        <?php else: ?>
                        <!-- No Therapy Recommendation -->
                        <div class="therapy-card text-center py-5">
                            <i class="fas fa-heartbeat fa-3x mb-3" style="color: #6c757d;"></i>
                            <h4>No Therapy Recommendation Yet</h4>
                            <p class="text-muted mb-4">
                                <?php if ($test_type == 'screening'): ?>
                                Generate a personalized therapy recommendation based on this screening test.
                                <?php else: ?>
                                This test doesn't have a therapy recommendation yet.
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($test_type == 'screening'): ?>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="./Therapy/details.php?test_id=<?php echo $test_id; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-magic me-2"></i> Generate Therapy Recommendation
                                </a>
                                <a href="test-details.php?id=<?php echo $test_id; ?>&generate_therapy=1" 
                                   class="btn btn-success">
                                    <i class="fas fa-bolt me-2"></i> Quick Generate
                                </a>
                            </div>
                            <?php else: ?>
                            <a href="./Therapy/details.php" 
                               class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i> New Therapy Assessment
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Back to Screening Link -->
                        <div class="mt-4 text-center">
                            <a href="test-details.php?id=<?php echo $test_id; ?>&tab=screening" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-chart-bar me-2"></i> View Screening Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching with URL update
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('#testTabs button[data-bs-toggle="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(event) {
                    const activeTab = event.target.getAttribute('data-bs-target').replace('#', '');
                    const url = new URL(window.location);
                    url.searchParams.set('tab', activeTab);
                    window.history.replaceState({}, '', url);
                });
            });
            
            // Auto-activate tab from URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            if (activeTab) {
                const tabElement = document.querySelector(`#${activeTab}-tab`);
                if (tabElement) {
                    new bootstrap.Tab(tabElement).show();
                }
            }
            
            // Auto-generate therapy if parameter is set
            const urlParamsObj = new URLSearchParams(window.location.search);
            if (urlParamsObj.has('generate_therapy') && urlParamsObj.get('generate_therapy') === '1') {
                // Switch to therapy tab
                const therapyTab = document.querySelector('#therapy-tab');
                if (therapyTab) {
                    new bootstrap.Tab(therapyTab).show();
                }
                
                // Show generating message
                const therapyContent = document.querySelector('#therapy .card-body');
                if (therapyContent) {
                    therapyContent.innerHTML = `
                        <div class="therapy-card text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h4>Generating Therapy Recommendation...</h4>
                            <p class="text-muted mb-4">Please wait while we analyze the screening data.</p>
                        </div>
                    `;
                    
                    // Simulate API call
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            }
        });
    </script>
</body>
</html>