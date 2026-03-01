<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix the database path based on your file structure
$configPath = __DIR__ . '/config/database.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Try alternative path
    $configPath = __DIR__ . '/../config/database.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        die("Database configuration file not found. Checked: " . $configPath);
    }
}

// Function to fetch therapy prediction from database
function fetchTherapyPrediction($conn, $test_id) {
    if (!$test_id || $test_id <= 0) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT therapy_prediction, therapy_data, test_type, child_name, child_age,
               asd_level, speech_delay, motor_delay, feedback, past_therapies,
               test_date, confidence_score
        FROM test_results 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'prediction' => $row['therapy_prediction'],
            'data' => $row['therapy_data'] ? json_decode($row['therapy_data'], true) : null,
            'test_type' => $row['test_type'],
            'child_name' => $row['child_name'],
            'child_age' => $row['child_age'],
            'asd_level' => $row['asd_level'],
            'speech_delay' => $row['speech_delay'],
            'motor_delay' => $row['motor_delay'],
            'feedback' => $row['feedback'],
            'past_therapies' => $row['past_therapies'],
            'test_date' => $row['test_date'],
            'confidence_score' => $row['confidence_score']
        ];
    }
    return null;
}

// Function to fetch therapy prediction by child name and date
function fetchTherapyPredictionByChild($conn, $child_name, $test_date) {
    if (empty($child_name) || empty($test_date)) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT therapy_prediction, therapy_data, test_type, child_name, child_age,
               asd_level, speech_delay, motor_delay, feedback, past_therapies
        FROM test_results 
        WHERE child_name = ? AND test_date = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("ss", $child_name, $test_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'prediction' => $row['therapy_prediction'],
            'data' => $row['therapy_data'] ? json_decode($row['therapy_data'], true) : null,
            'test_type' => $row['test_type'],
            'child_name' => $row['child_name'],
            'child_age' => $row['child_age'],
            'asd_level' => $row['asd_level'],
            'speech_delay' => $row['speech_delay'],
            'motor_delay' => $row['motor_delay'],
            'feedback' => $row['feedback'],
            'past_therapies' => $row['past_therapies']
        ];
    }
    return null;
}

// Function to fetch therapy prediction from Therapy/details form
function fetchTherapyDetailsPrediction($conn, $user_id, $child_name = null, $limit = 1) {
    $query = "
        SELECT therapy_prediction, therapy_data, test_type, child_name, child_age, 
               test_date, asd_level, speech_delay, motor_delay
        FROM test_results 
        WHERE user_id = ? AND test_type IN ('therapy', 'therapy_from_screening')
    ";
    
    if ($child_name) {
        $query .= " AND child_name = ?";
    }
    
    $query .= " ORDER BY test_date DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    if ($child_name) {
        $stmt->bind_param("isi", $user_id, $child_name, $limit);
    } else {
        $stmt->bind_param("ii", $user_id, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $predictions = [];
    while ($row = $result->fetch_assoc()) {
        $predictions[] = [
            'prediction' => $row['therapy_prediction'],
            'data' => $row['therapy_data'] ? json_decode($row['therapy_data'], true) : null,
            'test_type' => $row['test_type'],
            'child_name' => $row['child_name'],
            'child_age' => $row['child_age'],
            'test_date' => $row['test_date'],
            'asd_level' => $row['asd_level'],
            'speech_delay' => $row['speech_delay'],
            'motor_delay' => $row['motor_delay']
        ];
    }
    
    return $predictions;
}

// Function to fetch all therapy predictions for a user
function fetchAllTherapyPredictions($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT id, therapy_prediction, therapy_data, test_type, 
               child_name, child_age, test_date, confidence_score,
               asd_level, speech_delay, motor_delay, feedback
        FROM test_results 
        WHERE user_id = ? AND therapy_prediction IS NOT NULL
        ORDER BY test_date DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $predictions = [];
    while ($row = $result->fetch_assoc()) {
        $predictions[] = [
            'test_id' => $row['id'],
            'prediction' => $row['therapy_prediction'],
            'data' => $row['therapy_data'] ? json_decode($row['therapy_data'], true) : null,
            'test_type' => $row['test_type'],
            'child_name' => $row['child_name'],
            'child_age' => $row['child_age'],
            'test_date' => $row['test_date'],
            'confidence_score' => $row['confidence_score'],
            'asd_level' => $row['asd_level'],
            'speech_delay' => $row['speech_delay'],
            'motor_delay' => $row['motor_delay'],
            'feedback' => $row['feedback']
        ];
    }
    
    return $predictions;
}

// Function to get therapy prediction from screening test data
function getTherapyFromScreening($conn, $screeningTestId, $userId) {
    if (!$screeningTestId || $screeningTestId <= 0) {
        return null;
    }
    
    // Get screening test data
    $stmt = $conn->prepare("
        SELECT * FROM test_results 
        WHERE id = ? AND user_id = ? AND test_type = 'screening'
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("ii", $screeningTestId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$screeningData = $result->fetch_assoc()) {
        return null;
    }
    
    // Check if therapy prediction already exists
    if (!empty($screeningData['therapy_prediction'])) {
        return [
            'prediction' => $screeningData['therapy_prediction'],
            'data' => !empty($screeningData['therapy_data']) ? json_decode($screeningData['therapy_data'], true) : null,
            'screening_data' => $screeningData
        ];
    }
    
    // Extract data from screening test
    $no_responses = 0;
    for ($i = 1; $i <= 10; $i++) {
        $key = "a{$i}";
        if (isset($screeningData[$key]) && $screeningData[$key] == 1) {
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
    
    // Determine speech delay
    $speechDelay = 0;
    $speech_questions = ['a1', 'a2', 'a3', 'a10'];
    $speech_count = 0;
    foreach ($speech_questions as $q) {
        if (isset($screeningData[$q]) && $screeningData[$q] == 1) {
            $speech_count++;
        }
    }
    if ($speech_count >= 2) {
        $speechDelay = 1;
    }
    
    // Determine motor delay
    $motorDelay = 0;
    $motor_questions = ['a8', 'a9'];
    $motor_count = 0;
    foreach ($motor_questions as $q) {
        if (isset($screeningData[$q]) && $screeningData[$q] == 1) {
            $motor_count++;
        }
    }
    if ($motor_count >= 1) {
        $motorDelay = 1;
    }
    
    // Extract past therapies if any
    $pastTherapies = [];
    if (!empty($screeningData['past_therapies'])) {
        $therapies = explode(';', $screeningData['past_therapies']);
        foreach ($therapies as $therapy) {
            $therapy = trim($therapy);
            if (!empty($therapy)) {
                $pastTherapies[] = $therapy;
            }
        }
    }
    
    // Prepare data for therapy prediction
    $therapyData = [
        "Age" => intval($screeningData['child_age'] ?? 3),
        "ASD_Level" => $asdLevel,
        "Speech_Delay" => $speechDelay,
        "Motor_Delay" => $motorDelay,
        "Feedback" => "neutral",
        "Past_Therapies" => $pastTherapies,
        "Challenges" => []
    ];
    
    // Extract challenges from screening responses
    $challenges = [];
    
    // Check specific questions for challenges
    if (isset($screeningData['a6']) && $screeningData['a6'] == 1) {
        $challenges[] = 'Sensory Issues';
    }
    if (isset($screeningData['a7']) && $screeningData['a7'] == 1) {
        $challenges[] = 'Social Difficulty';
    }
    if (isset($screeningData['a10']) && $screeningData['a10'] == 1) {
        $challenges[] = 'Behavioral Issues';
    }
    if ($speechDelay == 1) {
        $challenges[] = 'Communication Delay';
    }
    if ($motorDelay == 1) {
        $challenges[] = 'Motor Delay';
    }
    
    // Add any other challenges from test_data if available
    if (!empty($screeningData['test_data'])) {
        $testData = json_decode($screeningData['test_data'], true);
        if (isset($testData['Challenges']) && is_array($testData['Challenges'])) {
            $challenges = array_merge($challenges, $testData['Challenges']);
        }
    }
    
    $therapyData['Challenges'] = array_unique($challenges);
    
    // Call therapy prediction API
    $apiUrl = "http://localhost:5002/predict";
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($therapyData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['prediction'])) {
            // Save therapy prediction to screening test
            $therapyDataJson = json_encode($therapyData);
            $updateStmt = $conn->prepare("
                UPDATE test_results 
                SET therapy_prediction = ?, 
                    therapy_data = ?,
                    asd_level = ?,
                    speech_delay = ?,
                    motor_delay = ?,
                    feedback = 'neutral'
                WHERE id = ?
            ");
            
            if ($updateStmt) {
                $updateStmt->bind_param(
                    "ssiiii",
                    $result['prediction'],
                    $therapyDataJson,
                    $asdLevel,
                    $speechDelay,
                    $motorDelay,
                    $screeningTestId
                );
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            return [
                'prediction' => $result['prediction'],
                'data' => $therapyData,
                'screening_data' => $screeningData
            ];
        }
    } else {
        error_log("Therapy API call failed for screening test $screeningTestId. HTTP Code: $httpCode, Error: $error");
        
        // Fallback logic if API fails
        $fallbackPrediction = determineFallbackTherapy($asdLevel, $speechDelay, $motorDelay, $challenges);
        
        // Save fallback prediction
        $therapyDataJson = json_encode($therapyData);
        $updateStmt = $conn->prepare("
            UPDATE test_results 
            SET therapy_prediction = ?, 
                therapy_data = ?,
                asd_level = ?,
                speech_delay = ?,
                motor_delay = ?,
                feedback = 'neutral'
            WHERE id = ?
        ");
        
        if ($updateStmt) {
            $updateStmt->bind_param(
                "ssiiii",
                $fallbackPrediction,
                $therapyDataJson,
                $asdLevel,
                $speechDelay,
                $motorDelay,
                $screeningTestId
            );
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        return [
            'prediction' => $fallbackPrediction,
            'data' => $therapyData,
            'screening_data' => $screeningData,
            'is_fallback' => true
        ];
    }
    
    return null;
}

// Helper function for fallback therapy prediction
function determineFallbackTherapy($asdLevel, $speechDelay, $motorDelay, $challenges) {
    if ($asdLevel == '3') {
        return 'ABA';
    } elseif ($asdLevel == '2') {
        if ($speechDelay == 1) {
            return 'Speech';
        } elseif ($motorDelay == 1) {
            return 'OT';
        } else {
            return 'Play';
        }
    } else {
        // Level 1 or unknown
        if (in_array('Social Difficulty', $challenges)) {
            return 'Social Skills';
        } elseif (in_array('Sensory Issues', $challenges)) {
            return 'OT';
        } elseif ($speechDelay == 1) {
            return 'Speech';
        } else {
            return 'Play';
        }
    }
}

// Function to generate therapy prediction from screening data (called from dashboard)
function generateTherapyFromScreeningData($screeningData, $conn, $userId) {
    if (!$screeningData || !is_array($screeningData)) {
        return null;
    }
    
    // Get test ID
    $testId = $screeningData['id'] ?? 0;
    
    // First check if already exists
    if ($testId > 0 && !empty($screeningData['therapy_prediction'])) {
        return $screeningData['therapy_prediction'];
    }
    
    // Use the getTherapyFromScreening function
    $result = getTherapyFromScreening($conn, $testId, $userId);
    
    return $result ? $result['prediction'] : null;
}

// Function to get therapy icon
function getTherapyIcon($therapy) {
    $icons = [
        "ABA" => "fas fa-chart-line",
        "OT" => "fas fa-hands-helping",
        "Play" => "fas fa-gamepad",
        "Speech" => "fas fa-comments",
        "Music" => "fas fa-music",
        "Art" => "fas fa-palette",
        "Social Skills" => "fas fa-users",
        "Physical" => "fas fa-running"
    ];
    return isset($icons[$therapy]) ? $icons[$therapy] : "fas fa-heartbeat";
}

// Function to get therapy description
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

// Function to get readable ASD level
function readableASDLevel($level) {
    $map = [
        "1" => "Mild",
        "2" => "Moderate", 
        "3" => "Severe",
        "Mild" => "Mild",
        "Moderate" => "Moderate",
        "Severe" => "Severe"
    ];
    return isset($map[$level]) ? $map[$level] : 'N/A';
}

// Function to check if a test has therapy prediction
function hasTherapyPrediction($conn, $test_id) {
    if (!$test_id || $test_id <= 0) {
        return false;
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM test_results 
        WHERE id = ? AND therapy_prediction IS NOT NULL
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row && $row['count'] > 0;
}

// Function to get latest therapy prediction for a child
function getLatestTherapyPredictionForChild($conn, $user_id, $child_name) {
    if (empty($child_name)) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT therapy_prediction, therapy_data, test_date, test_type
        FROM test_results 
        WHERE user_id = ? AND child_name = ? AND therapy_prediction IS NOT NULL
        ORDER BY test_date DESC
        LIMIT 1
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("is", $user_id, $child_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'prediction' => $row['therapy_prediction'],
            'data' => $row['therapy_data'] ? json_decode($row['therapy_data'], true) : null,
            'test_date' => $row['test_date'],
            'test_type' => $row['test_type']
        ];
    }
    return null;
}

// Function to get all screening tests that have therapy predictions
function getScreeningTestsWithTherapy($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT id, child_name, child_age, test_date, therapy_prediction,
               asd_level, speech_delay, motor_delay, confidence_score
        FROM test_results 
        WHERE user_id = ? AND test_type = 'screening' AND therapy_prediction IS NOT NULL
        ORDER BY test_date DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $tests[] = [
            'test_id' => $row['id'],
            'child_name' => $row['child_name'],
            'child_age' => $row['child_age'],
            'test_date' => $row['test_date'],
            'therapy_prediction' => $row['therapy_prediction'],
            'asd_level' => $row['asd_level'],
            'speech_delay' => $row['speech_delay'],
            'motor_delay' => $row['motor_delay'],
            'confidence_score' => $row['confidence_score']
        ];
    }
    
    return $tests;
}

// Function to link therapy test to screening test
function linkTherapyToScreening($conn, $therapy_test_id, $screening_test_id) {
    if (!$therapy_test_id || !$screening_test_id) {
        return false;
    }
    
    // Update therapy test with screening reference
    $stmt1 = $conn->prepare("
        UPDATE test_results 
        SET screening_test_id = ?, test_type = 'therapy_from_screening'
        WHERE id = ?
    ");
    
    if (!$stmt1) {
        error_log("Prepare failed for therapy update: " . $conn->error);
        return false;
    }
    
    $stmt1->bind_param("ii", $screening_test_id, $therapy_test_id);
    $success1 = $stmt1->execute();
    $stmt1->close();
    
    // Update screening test with therapy reference
    $stmt2 = $conn->prepare("
        UPDATE test_results 
        SET linked_therapy_test = ?
        WHERE id = ?
    ");
    
    if (!$stmt2) {
        error_log("Prepare failed for screening update: " . $conn->error);
        return false;
    }
    
    $stmt2->bind_param("ii", $therapy_test_id, $screening_test_id);
    $success2 = $stmt2->execute();
    $stmt2->close();
    
    return $success1 && $success2;
}

// Function to get combined test data (screening + therapy)
function getCombinedTestData($conn, $test_id) {
    if (!$test_id || $test_id <= 0) {
        return null;
    }
    
    // Get the test
    $stmt = $conn->prepare("
        SELECT * FROM test_results 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $test = $result->fetch_assoc();
    
    if (!$test) {
        return null;
    }
    
    $combinedData = $test;
    
    // If this is a therapy test linked to screening, get screening data
    if (!empty($test['screening_test_id'])) {
        $screeningStmt = $conn->prepare("
            SELECT * FROM test_results 
            WHERE id = ?
        ");
        
        if ($screeningStmt) {
            $screeningStmt->bind_param("i", $test['screening_test_id']);
            $screeningStmt->execute();
            $screeningResult = $screeningStmt->get_result();
            $screeningData = $screeningResult->fetch_assoc();
            
            if ($screeningData) {
                $combinedData['screening_data'] = $screeningData;
            }
            $screeningStmt->close();
        }
    }
    
    // If this is a screening test linked to therapy, get therapy data
    if (!empty($test['linked_therapy_test'])) {
        $therapyStmt = $conn->prepare("
            SELECT * FROM test_results 
            WHERE id = ?
        ");
        
        if ($therapyStmt) {
            $therapyStmt->bind_param("i", $test['linked_therapy_test']);
            $therapyStmt->execute();
            $therapyResult = $therapyStmt->get_result();
            $therapyData = $therapyResult->fetch_assoc();
            
            if ($therapyData) {
                $combinedData['therapy_data_full'] = $therapyData;
            }
            $therapyStmt->close();
        }
    }
    
    // Decode JSON data if present
    if (!empty($combinedData['test_data'])) {
        $combinedData['test_data_decoded'] = json_decode($combinedData['test_data'], true);
    }
    if (!empty($combinedData['therapy_data'])) {
        $combinedData['therapy_data_decoded'] = json_decode($combinedData['therapy_data'], true);
    }
    
    return $combinedData;
}

// API endpoint for AJAX calls
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'data' => null, 'message' => ''];
    
    if (isset($_GET['test_id'])) {
        $test_id = intval($_GET['test_id']);
        $therapyData = fetchTherapyPrediction($conn, $test_id);
        
        if ($therapyData) {
            $response['success'] = true;
            $response['data'] = $therapyData;
            $response['message'] = 'Therapy prediction found';
        } else {
            $response['message'] = 'No therapy prediction found for this test';
        }
    }
    elseif (isset($_GET['child_name']) && isset($_GET['test_date'])) {
        $child_name = $_GET['child_name'];
        $test_date = $_GET['test_date'];
        $therapyData = fetchTherapyPredictionByChild($conn, $child_name, $test_date);
        
        if ($therapyData) {
            $response['success'] = true;
            $response['data'] = $therapyData;
            $response['message'] = 'Therapy prediction found';
        } else {
            $response['message'] = 'No therapy prediction found';
        }
    }
    elseif (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        $child_name = $_GET['child'] ?? null;
        
        if ($child_name) {
            $predictions = fetchTherapyDetailsPrediction($conn, $user_id, $child_name);
        } else {
            $predictions = fetchAllTherapyPredictions($conn, $user_id);
        }
        
        if (!empty($predictions)) {
            $response['success'] = true;
            $response['data'] = $predictions;
            $response['message'] = 'Therapy predictions found';
        } else {
            $response['message'] = 'No therapy predictions found';
        }
    }
    elseif (isset($_GET['generate_from_screening']) && isset($_GET['test_id']) && isset($_GET['user_id'])) {
        $test_id = intval($_GET['test_id']);
        $user_id = intval($_GET['user_id']);
        
        $therapyData = getTherapyFromScreening($conn, $test_id, $user_id);
        
        if ($therapyData) {
            $response['success'] = true;
            $response['data'] = $therapyData;
            $response['message'] = 'Therapy prediction generated from screening';
        } else {
            $response['message'] = 'Failed to generate therapy prediction from screening';
        }
    }
    elseif (isset($_GET['combined_data']) && isset($_GET['test_id'])) {
        $test_id = intval($_GET['test_id']);
        $combinedData = getCombinedTestData($conn, $test_id);
        
        if ($combinedData) {
            $response['success'] = true;
            $response['data'] = $combinedData;
            $response['message'] = 'Combined test data retrieved';
        } else {
            $response['message'] = 'Test not found';
        }
    }
    else {
        $response['message'] = 'Invalid parameters';
    }
    
    echo json_encode($response);
    exit();
}
?>