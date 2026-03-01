<?php
session_start();
require_once 'config/database.php';

// Check if test result exists
if (!isset($_SESSION['test_result'])) {
    header("Location: test.php");
    exit();
}

$testResult = $_SESSION['test_result'];
$childName = $_SESSION['child_name'] ?? 'Unknown';
$childAge = $_SESSION['child_age'] ?? 0;
$answers = $_SESSION['answers'] ?? array_fill(0, 10, 0);

// Question labels
$questionLabels = [
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

// Get test ID for therapy resources link
$testId = $_SESSION['test_id'] ?? 0;

// If no test ID in session, try to get the latest autism screening test ID for the user
if ($testId === 0 && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT id, child_name, test_date 
        FROM test_results 
        WHERE user_id = ? 
        AND (test_type IS NULL OR test_type = '' OR test_type = 'autism' OR test_type = 'screening')
        ORDER BY test_date DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $testId = $row['id'];
        // Also update child name if not set
        if (empty($childName) || $childName === 'Unknown') {
            $childName = $row['child_name'] ?? $childName;
            $_SESSION['child_name'] = $childName;
        }
        // Store test date for reference
        $_SESSION['test_date'] = $row['test_date'];
    }
    $stmt->close();
}

// Store the test ID back in session for future use
$_SESSION['last_test_id'] = $testId;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results - CareGuides</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f7fa; padding: 20px; }
        .card { border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: none; }
        .list-group-item { background-color: #fff; border: none; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; }
        .result-card { background-color: #fff; border-left: 5px solid; padding: 20px; margin-top: 25px; border-radius: 8px; box-shadow: 0 3px 6px rgba(0,0,0,0.05); }
        .result-positive { border-left-color: #dc3545; background: linear-gradient(to right, rgba(220, 53, 69, 0.05), white); }
        .result-negative { border-left-color: #28a745; background: linear-gradient(to right, rgba(40, 167, 69, 0.05), white); }
        .confidence-meter { height: 10px; background: #e9ecef; border-radius: 5px; margin: 15px 0; overflow: hidden; }
        .confidence-fill { height: 100%; border-radius: 5px; }
        .btn-group-custom { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }
        .therapy-btn { background: linear-gradient(45deg, #ff6b6b, #ff8e53); border: none; color: white; }
        .therapy-btn:hover { background: linear-gradient(45deg, #ff5252, #ff7b39); color: white; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="card p-4">
            <div class="card-body">
                <h2 class="text-center mb-4 text-primary"><i class="bi bi-clipboard-check"></i> Autism Screening Results</h2>
                
                <h5 class="mb-4">Child's Name: <span class="text-info"><?php echo htmlspecialchars($childName); ?></span></h5>
                <h6 class="mb-4">Child's Age: <span class="text-info"><?php echo htmlspecialchars($childAge); ?> years</span></h6>
                
                <ul class="list-group mb-4">
                    <?php for ($i = 1; $i <= 10; $i++): 
                        $answer = $answers[$i-1] == 0 ? 'Yes' : 'No';
                        $textClass = $answers[$i-1] == 0 ? 'success' : 'danger';
                    ?>
                    <li class="list-group-item">
                        <strong>Q<?php echo $i; ?>:</strong> <?php echo $questionLabels[$i]; ?>
                        <span class="float-right text-<?php echo $textClass; ?>"><strong><?php echo $answer; ?></strong></span>
                    </li>
                    <?php endfor; ?>
                </ul>

                <?php
                $prediction = $testResult['prediction'] ?? $testResult;
                $confidence = $testResult['confidence'] ?? 85;
                $isFallback = $testResult['is_fallback'] ?? false;
                
                if ($prediction == 1 || $prediction === '1') {
                    echo '<div class="result-card result-positive">';
                    echo '<h5 class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Possible signs of autism detected</h5>';
                    echo '<p>Based on our ML analysis, there are indications that may warrant further evaluation by a specialist.</p>';
                    
                    if ($isFallback) {
                        echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Using fallback calculation (ML API unavailable)</div>';
                    }
                    
                    echo '<div class="confidence-meter"><div class="confidence-fill bg-danger" style="width: ' . $confidence . '%"></div></div>';
                    echo '<p class="text-muted">Confidence level: ' . $confidence . '%</p>';
                    echo '</div>';

                    echo '<div class="btn-group-custom mt-4">';
                    echo '<a href="book_appointment.php" class="btn btn-danger"><i class="bi bi-calendar-check"></i> Book Specialist Appointment</a>';
                    
                    // Fixed Therapy Resources button with test ID
                    if ($testId > 0) {
                        echo '<a href="test-details.php?id=' . $testId . '" class="btn therapy-btn"><i class="bi bi-heart-pulse"></i> Get Therapy Recommendations</a>';
                    } else {
                        // Pass child info via URL parameters
                        $testDate = $_SESSION['test_date'] ?? date('Y-m-d');
                        echo '<a href="test-details.php?date=' . urlencode($testDate) . '&child=' . urlencode($childName) . '" class="btn therapy-btn"><i class="bi bi-heart-pulse"></i> Get Therapy Recommendations</a>';
                    }
                    
                    echo '</div>';
                } else {
                    echo '<div class="result-card result-negative">';
                    echo '<h5 class="text-success"><i class="bi bi-check-circle-fill"></i> Unlikely signs of autism</h5>';
                    echo '<p>Based on our ML analysis, your child is unlikely to show signs of autism. Continue monitoring development.</p>';
                    
                    if ($isFallback) {
                        echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Using fallback calculation (ML API unavailable)</div>';
                    }
                    
                    echo '<div class="confidence-meter"><div class="confidence-fill bg-success" style="width: ' . $confidence . '%"></div></div>';
                    echo '<p class="text-muted">Confidence level: ' . $confidence . '%</p>';
                    echo '</div>';
                }
                ?>

                <div class="alert alert-info mt-4">
                    <h6><i class="bi bi-info-circle"></i> Important Note</h6>
                    <p class="mb-0">This screening uses machine learning analysis but is not a diagnostic tool. Results should be discussed with a qualified healthcare professional.</p>
                </div>

                <div class="btn-group-custom">
                    <a href="test.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Test</a>
                    <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <button onclick="window.print()" class="btn btn-success"><i class="bi bi-printer"></i> Print Results</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const confidenceFill = document.querySelector('.confidence-fill');
            if (confidenceFill) {
                const width = confidenceFill.style.width;
                confidenceFill.style.width = '0';
                setTimeout(() => {
                    confidenceFill.style.width = width;
                    confidenceFill.style.transition = 'width 1s ease';
                }, 500);
            }
            
            // Add animation to therapy button
            const therapyBtn = document.querySelector('.therapy-btn');
            if (therapyBtn) {
                therapyBtn.addEventListener('mouseenter', function() {
                    this.style.transition = 'all 0.3s ease';
                });
            }
        });
    </script>
</body>
</html>