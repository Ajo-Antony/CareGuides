<?php
session_start();
require_once 'config/database.php';

// Function to call Flask API
function predictAutism($answers, $childName = '', $childAge = 0, $userId = null) {
    // Build API URL
    $baseUrl = "http://localhost:5001/check";
    
    // Add parameters
    $params = [];
    for ($i = 1; $i <= 10; $i++) {
        $params["A$i"] = $answers[$i-1];
    }
    
    if ($childName) {
        $params['child_name'] = urlencode($childName);
    }
    
    if ($childAge) {
        $params['child_age'] = $childAge;
    }
    
    if ($userId) {
        $params['user_id'] = $userId;
    }
    
    $url = $baseUrl . '?' . http_build_query($params);
    
    // Call API with timeout
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $result = json_decode($response, true);
        if (isset($result['success']) && $result['success']) {
            // Save to database if user is logged in
            if (isset($_SESSION['user_id']) && $userId) {
                saveTestResult($result, $answers, $childName, $childAge, $userId);
            }
            return $result;
        }
    }
    
    // Fallback: Use local calculation if API fails
    return calculateFallbackPrediction($answers, $childName, $childAge);
}

function saveTestResult($apiResult, $answers, $childName, $childAge, $userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO test_results 
            (user_id, child_name, child_age, a1, a2, a3, a4, a5, a6, a7, a8, a9, a10, 
             prediction_result, confidence_score, test_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // FIXED: Bind parameters correctly - 15 parameters total
        $prediction_label = $apiResult['prediction'] == 1 ? 'Positive' : 'Negative';
        $confidence = $apiResult['confidence'] ?? 0;
        
        // Debug: Check what we're binding
        error_log("Binding parameters: user_id=$userId, child_name=$childName, child_age=$childAge, answers=" . implode(',', $answers));
        
        $stmt->bind_param(
            "isiiiiiiiiiiiss", // 15 parameters: i=integer, s=string
            $userId,                   // user_id (i)
            $childName,                // child_name (s)
            $childAge,                 // child_age (i)
            $answers[0],               // a1 (i)
            $answers[1],               // a2 (i)
            $answers[2],               // a3 (i)
            $answers[3],               // a4 (i)
            $answers[4],               // a5 (i)
            $answers[5],               // a6 (i)
            $answers[6],               // a7 (i)
            $answers[7],               // a8 (i)
            $answers[8],               // a9 (i)
            $answers[9],               // a10 (i)
            $prediction_label,         // prediction_result (s)
            $confidence                // confidence_score (s)
        );
        
        if ($stmt->execute()) {
            $testId = $conn->insert_id;
            error_log("Test result saved successfully with ID: $testId");
            $stmt->close();
            return $testId;
        } else {
            error_log("Error executing statement: " . $stmt->error);
            $stmt->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error saving test result: " . $e->getMessage());
        return false;
    }
}

function calculateFallbackPrediction($answers, $childName = '', $childAge = 0) {
    // Simple rule-based fallback if API fails
    $score = array_sum($answers);
    $prediction = $score >= 5 ? 1 : 0;
    
    $confidence = ($score / 10) * 100;
    
    return [
        'success' => true,
        'prediction' => $prediction,
        'prediction_label' => $prediction == 1 ? 'Positive' : 'Negative',
        'confidence' => round($confidence, 2),
        'probabilities' => [
            'No' => round((10 - $score) / 10 * 100, 2),
            'Yes' => round($score / 10 * 100, 2)
        ],
        'child_info' => [
            'name' => $childName,
            'age' => $childAge
        ],
        'is_fallback' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $childName = sanitize($_POST['child_name'] ?? '');
    $childAge = intval($_POST['child_age'] ?? 0);
    
    // Get answers - ensure we have exactly 10 answers
    $answers = [];
    for ($i = 1; $i <= 10; $i++) {
        $answers[] = isset($_POST["a$i"]) ? intval($_POST["a$i"]) : 0;
    }
    
    // Validate we have exactly 10 answers
    if (count($answers) != 10) {
        die("Error: Expected 10 answers, got " . count($answers));
    }
    
    // Get user ID if logged in
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Call prediction API
    $result = predictAutism($answers, $childName, $childAge, $userId);
    
    // Store in session for results page
    $_SESSION['test_result'] = $result;
    $_SESSION['child_name'] = $childName;
    $_SESSION['child_age'] = $childAge;
    $_SESSION['answers'] = $answers;
    
    // Add user_id to result for tracking
    if ($userId) {
        $_SESSION['test_result']['user_id'] = $userId;
    }
    
    // Redirect to results page
    header("Location: details.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autism Screening Test - CareGuides</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .test-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            margin-bottom: 0;
        }
        
        .test-body {
            background: white;
            padding: 40px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .question-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 25px;
            background: white;
            transition: all 0.3s;
        }
        
        .question-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(74, 111, 165, 0.1);
        }
        
        .question-number {
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .answer-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .answer-option {
            flex: 1;
            text-align: center;
        }
        
        .answer-option input {
            display: none;
        }
        
        .answer-option label {
            display: block;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .answer-option label:hover {
            background-color: #f8f9fa;
            border-color: var(--accent-color);
        }
        
        .answer-option input:checked + label {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .answer-yes label {
            border-color: #28a745;
            color: #28a745;
        }
        
        .answer-yes input:checked + label {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .answer-no label {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .answer-no input:checked + label {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .progress-container {
            position: sticky;
            top: 0;
            z-index: 100;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .progress {
            height: 8px;
            margin: 10px 0;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
        }
        
        .child-info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--accent-color);
        }
        
        @media (max-width: 768px) {
            .test-body {
                padding: 20px;
            }
            
            .answer-options {
                flex-direction: column;
            }
            
            .question-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>
    
    <div class="test-container">
        <!-- Test Header -->
        <div class="test-header text-center">
            <h1 class="display-5 fw-bold mb-3">
                <i class="bi bi-clipboard-check me-3"></i>Autism Screening Test
            </h1>
            <p class="lead mb-0">Complete this 10-question screening test for preliminary assessment</p>
        </div>
        
        <!-- Test Body -->
        <div class="test-body">
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold">Test Progress</span>
                    <span id="progressText">0/10 questions answered</span>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-primary" id="progressBar" style="width: 0%"></div>
                </div>
                <div class="text-center text-muted small mt-2">
                    <i class="bi bi-info-circle me-1"></i> Answer all questions for accurate results
                </div>
            </div>
            
            <?php if (!isset($_SESSION['logged_in'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                You're not logged in. <a href="login.php" class="alert-link">Login</a> to save your test results and track progress.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="testForm">
                <!-- Child Information -->
                <div class="child-info-card">
                    <h4 class="mb-4">
                        <i class="bi bi-person-circle me-2"></i> Child Information
                    </h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Child's Name (Optional)</label>
                            <input type="text" class="form-control" name="child_name" 
                                   placeholder="Enter child's name">
                            <small class="text-muted">For identification purposes only</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Child's Age (Optional)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="child_age" 
                                       min="1" max="18" placeholder="Age in years">
                                <span class="input-group-text">years</span>
                            </div>
                            <small class="text-muted">Recommended: 18 months to 5 years</small>
                        </div>
                    </div>
                </div>
                
                <!-- Questions -->
                <?php
                $questions = [
                    1 => "Does the child look at you when you call his/her name?",
                    2 => "Does the child point at objects to show interest?",
                    3 => "Does the child pretend play (e.g., pretend to drink from an empty cup)?",
                    4 => "Does the child use single words meaningfully?",
                    5 => "Does the child respond to their name?",
                    6 => "Does the child make eye contact when interacting?",
                    7 => "Does the child show interest in other children?",
                    8 => "Does the child bring objects to show parents?",
                    9 => "Does the child follow simple instructions?",
                    10 => "Does the child engage in back-and-forth social interaction?"
                ];
                
                foreach ($questions as $num => $question):
                ?>
                <div class="question-card" id="question-<?php echo $num; ?>">
                    <div class="d-flex align-items-start mb-3">
                        <div class="question-number"><?php echo $num; ?></div>
                        <div>
                            <h5 class="mb-2"><?php echo $question; ?></h5>
                            <small class="text-muted">Select the most appropriate response</small>
                        </div>
                    </div>
                    
                    <div class="answer-options">
                        <div class="answer-option answer-yes">
                            <input type="radio" name="a<?php echo $num; ?>" 
                                   value="0" id="q<?php echo $num; ?>_yes" required 
                                   data-question="<?php echo $num; ?>">
                            <label for="q<?php echo $num; ?>_yes">
                                <i class="bi bi-check-circle-fill me-2"></i> Yes
                            </label>
                        </div>
                        
                        <div class="answer-option answer-no">
                            <input type="radio" name="a<?php echo $num; ?>" 
                                   value="1" id="q<?php echo $num; ?>_no" 
                                   data-question="<?php echo $num; ?>">
                            <label for="q<?php echo $num; ?>_no">
                                <i class="bi bi-x-circle-fill me-2"></i> No
                            </label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Test Instructions -->
                <div class="alert alert-info mt-4">
                    <h5><i class="bi bi-info-circle-fill me-2"></i> Important Instructions</h5>
                    <ul class="mb-0">
                        <li>Answer all questions based on your observations of the child</li>
                        <li>Be honest and accurate for best results</li>
                        <li>This is a screening tool, not a diagnostic test</li>
                        <li>Results should be discussed with a healthcare professional</li>
                    </ul>
                </div>
                
                <!-- Submit Button -->
                <div class="text-center mt-5">
                    <button type="submit" class="btn btn-primary btn-lg px-5 py-3">
                        <i class="bi bi-clipboard-check me-2"></i> Submit Test & View Results
                    </button>
                    <button type="reset" class="btn btn-outline-secondary btn-lg ms-3 px-5 py-3">
                        <i class="bi bi-arrow-clockwise me-2"></i> Clear All Answers
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="bi bi-shield-lock me-1"></i> Your responses are confidential and secure
                    </small>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Update progress bar
        function updateProgress() {
            const answered = document.querySelectorAll('input[type="radio"]:checked').length;
            const total = 10;
            const percentage = (answered / total) * 100;
            
            // Update progress bar
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = percentage + '%';
            progressBar.textContent = `${Math.round(percentage)}%`;
            
            // Update progress text
            document.getElementById('progressText').textContent = 
                `${answered}/${total} questions answered`;
            
            // Update question card borders
            document.querySelectorAll('.question-card').forEach(card => {
                const questionNum = card.id.split('-')[1];
                const isAnswered = document.querySelector(`input[name="a${questionNum}"]:checked`);
                if (isAnswered) {
                    card.style.borderColor = '#28a745';
                    card.style.borderWidth = '2px';
                } else {
                    card.style.borderColor = '#e0e0e0';
                    card.style.borderWidth = '1px';
                }
            });
            
            // If all questions answered, scroll to submit button
            if (answered === total) {
                document.querySelector('button[type="submit"]').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
        
        // Add event listeners to radio buttons
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', updateProgress);
        });
        
        // Form validation
        document.getElementById('testForm').addEventListener('submit', function(e) {
            const unanswered = [];
            for (let i = 1; i <= 10; i++) {
                if (!document.querySelector(`input[name="a${i}"]:checked`)) {
                    unanswered.push(i);
                }
            }
            
            if (unanswered.length > 0) {
                e.preventDefault();
                
                // Create alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Please answer questions: ${unanswered.join(', ')}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Insert at top of form
                const testBody = document.querySelector('.test-body');
                testBody.insertBefore(alertDiv, testBody.firstChild);
                
                // Scroll to first unanswered question
                document.getElementById(`question-${unanswered[0]}`).scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'center'
                });
                
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Processing...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Reset form handler
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all answers?')) {
                setTimeout(updateProgress, 100);
            } else {
                return false;
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
        });
    </script>
</body>
</html>