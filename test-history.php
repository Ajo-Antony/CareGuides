<?php
session_start();
require_once 'config/database.php';
requireLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$fullName = $_SESSION['full_name'];
$userType = $_SESSION['user_type'];

// Get all test results for the user
$testHistoryQuery = $conn->prepare("
    SELECT * FROM test_results 
    WHERE user_id = ? 
    ORDER BY test_date DESC
");
$testHistoryQuery->bind_param("i", $userId);
$testHistoryQuery->execute();
$testHistory = $testHistoryQuery->get_result();

$totalTests = $testHistory->num_rows;

// Helper function to get ASD level
function getASDLevel($testData, $test) {
    if (isset($testData['ASD_Level'])) {
        $levels = ["1" => "Mild", "2" => "Moderate", "3" => "Severe"];
        return isset($levels[$testData['ASD_Level']]) ? $levels[$testData['ASD_Level']] : 'Unknown';
    }
    
    // Calculate from question responses
    $positive_count = 0;
    for ($i = 1; $i <= 10; $i++) {
        $key = "a{$i}";
        if (isset($test[$key]) && $test[$key] == 1) {
            $positive_count++;
        }
    }
    
    if ($positive_count >= 8) return "Severe";
    if ($positive_count >= 5) return "Moderate";
    return "Mild";
}

// Helper function to check speech delay
function hasSpeechDelay($testData, $test) {
    if (isset($testData['Speech_Delay'])) {
        return $testData['Speech_Delay'] == 1;
    }
    
    $speech_questions = ['a1', 'a2', 'a3', 'a10'];
    $count = 0;
    foreach ($speech_questions as $q) {
        if (isset($test[$q]) && $test[$q] == 1) {
            $count++;
        }
    }
    return $count >= 2;
}

// Helper function to check motor delay
function hasMotorDelay($testData, $test) {
    if (isset($testData['Motor_Delay'])) {
        return $testData['Motor_Delay'] == 1;
    }
    
    $motor_questions = ['a8', 'a9'];
    $count = 0;
    foreach ($motor_questions as $q) {
        if (isset($test[$q]) && $test[$q] == 1) {
            $count++;
        }
    }
    return $count >= 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test History - CareGuides</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #17a2b8;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        
        .test-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        
        .test-header {
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .test-body {
            padding: 20px;
        }
        
        .test-status-badge {
            padding: 5px 12px;
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
        
        .feature-badge {
            display: inline-block;
            padding: 4px 10px;
            margin-right: 5px;
            margin-bottom: 5px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 12px;
            font-size: 0.8rem;
            border: 1px solid #bae6fd;
        }
        
        .therapy-badge {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px dashed #dee2e6;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #adb5bd;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            margin-bottom: 20px;
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .test-date-badge {
            background: #e2e8f0;
            color: #475569;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .test-metrics {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 20px 0;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">
                        <i class="bi bi-clock-history me-2"></i> Test History
                    </h1>
                    <p class="lead mb-0">Complete history of all your screening tests</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="dashboard.php" class="btn btn-light btn-lg">
                        <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container pb-5">
        <!-- Stats Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="color: var(--primary-color);">
                            <i class="bi bi-clipboard-data fs-1"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $totalTests; ?></h3>
                            <p class="text-muted mb-0">Total Tests</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="color: #28a745;">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                        <div>
                            <?php
                            // Count positive results
                            $positiveCount = 0;
                            $testHistory->data_seek(0); // Reset pointer
                            while ($test = $testHistory->fetch_assoc()) {
                                $asd_status = $test['asd_status'] ?? 0;
                                if ($asd_status == '1' || (isset($test['prediction_result']) && strpos(strtolower($test['prediction_result']), 'positive') !== false)) {
                                    $positiveCount++;
                                }
                            }
                            $testHistory->data_seek(0); // Reset pointer again
                            ?>
                            <h3 class="mb-0"><?php echo $positiveCount; ?></h3>
                            <p class="text-muted mb-0">Positive Results</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="color: #dc3545;">
                            <i class="bi bi-x-circle fs-1"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $totalTests - $positiveCount; ?></h3>
                            <p class="text-muted mb-0">Negative Results</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="color: #8b5cf6;">
                            <i class="bi bi-heart-pulse fs-1"></i>
                        </div>
                        <div>
                            <?php
                            // Count tests with therapy predictions
                            $therapyCount = 0;
                            $testHistory->data_seek(0);
                            while ($test = $testHistory->fetch_assoc()) {
                                if (!empty($test['therapy_prediction'])) {
                                    $therapyCount++;
                                }
                            }
                            $testHistory->data_seek(0);
                            ?>
                            <h3 class="mb-0"><?php echo $therapyCount; ?></h3>
                            <p class="text-muted mb-0">Therapy Predictions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary mb-0">All Tests (<?php echo $totalTests; ?>)</h3>
            </div>
            <div class="d-flex gap-2">
                <a href="test.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> New Test
                </a>
                <a href="generate-report.php?all=true" class="btn btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Export All
                </a>
            </div>
        </div>
        
        <?php if ($totalTests > 0): ?>
            <!-- Test List -->
            <div class="test-list">
                <?php 
                $counter = 0;
                while ($test = $testHistory->fetch_assoc()): 
                    $counter++;
                    
                    // Decode test data
                    $testData = [];
                    if (!empty($test['test_data'])) {
                        $testData = json_decode($test['test_data'], true);
                    }
                    
                    // Combine data
                    $combinedData = array_merge($testData, $test);
                    
                    // Get prediction
                    $prediction = $test['prediction_result'] ?? null;
                    
                    // Get ASD status
                    $asd_status = $test['asd_status'] ?? 0;
                    $status_class = $asd_status == '1' || (isset($prediction) && strpos(strtolower($prediction), 'positive') !== false) ? 'status-positive' : 'status-negative';
                    $status_text = $asd_status == '1' ? 'Positive' : 'Negative';
                    
                    if ($prediction) {
                        $status_text = $prediction;
                    }
                    
                    // Get ASD level
                    $asdLevel = getASDLevel($testData, $test);
                    
                    // Check for delays
                    $hasSpeechDelay = hasSpeechDelay($testData, $test);
                    $hasMotorDelay = hasMotorDelay($testData, $test);
                    
                    // Get therapy prediction
                    $therapyPrediction = $test['therapy_prediction'] ?? null;
                ?>
                <div class="test-card">
                    <div class="test-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="bi bi-person-circle me-2"></i>
                                    <?php echo htmlspecialchars($test['child_name'] ?: 'Unnamed Child'); ?>
                                    <small class="text-muted ms-2">(Age: <?php echo $test['child_age'] ?: 'N/A'; ?> years)</small>
                                </h5>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="test-date-badge">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?php echo date('M d, Y', strtotime($test['test_date'])); ?>
                                </span>
                                <span class="test-status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="test-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="feature-badge">
                                            <i class="bi bi-graph-up me-1"></i>
                                            ASD Level: <?php echo $asdLevel; ?>
                                        </span>
                                        
                                        <?php if ($hasSpeechDelay): ?>
                                        <span class="feature-badge">
                                            <i class="bi bi-chat-dots me-1"></i>Speech Delay
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($hasMotorDelay): ?>
                                        <span class="feature-badge">
                                            <i class="bi bi-activity me-1"></i>Motor Delay
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($test['a6']) && $test['a6'] == 1): ?>
                                        <span class="feature-badge">
                                            <i class="bi bi-emoji-dizzy me-1"></i>Sensory Issues
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($therapyPrediction): ?>
                                        <span class="therapy-badge">
                                            <?php if ($therapyPrediction == 'Speech'): ?>
                                                <i class="bi bi-chat-dots me-1"></i>
                                            <?php elseif ($therapyPrediction == 'OT'): ?>
                                                <i class="bi bi-hand-index-thumb me-1"></i>
                                            <?php elseif ($therapyPrediction == 'ABA'): ?>
                                                <i class="bi bi-diagram-3 me-1"></i>
                                            <?php elseif ($therapyPrediction == 'Play'): ?>
                                                <i class="bi bi-joystick me-1"></i>
                                            <?php else: ?>
                                                <i class="bi bi-heart-pulse me-1"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($therapyPrediction); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($test['notes'])): ?>
                                    <div class="alert alert-light border">
                                        <small>
                                            <i class="bi bi-chat-left-text me-1"></i>
                                            <strong>Notes:</strong> <?php echo htmlspecialchars(substr($test['notes'], 0, 150)); ?>
                                            <?php if (strlen($test['notes']) > 150): ?>...<?php endif; ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="test-metrics">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Confidence Score</small>
                                            <strong><?php echo $test['confidence_score'] ?? 'N/A'; ?>%</strong>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Test Duration</small>
                                            <strong><?php echo $test['test_duration'] ?? 'N/A'; ?> mins</strong>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted d-block">Questions Answered</small>
                                            <strong>10/10</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="action-buttons">
                                    <a href="test-details.php?id=<?php echo $test['id']; ?>" 
                                       class="btn btn-primary w-100">
                                        <i class="bi bi-eye me-1"></i> View Details
                                    </a>
                                    <a href="generate-report.php?test_id=<?php echo $test['id']; ?>" 
                                       class="btn btn-outline-primary w-100">
                                        <i class="bi bi-download me-1"></i> Download Report
                                    </a>
                                    <?php if (empty($therapyPrediction)): ?>
                                    <a href="test-details.php?id=<?php echo $test['id']; ?>&generate_therapy=1" 
                                       class="btn btn-outline-info w-100">
                                        <i class="bi bi-magic me-1"></i> Generate Therapy
                                    </a>
                                    <?php endif; ?>
                                    <a href="book_appointment.php?ref=test_<?php echo $test['id']; ?>" 
                                       class="btn btn-outline-success w-100">
                                        <i class="bi bi-calendar-plus me-1"></i> Book Consultation
                                    </a>
                                </div>
                                
                                <?php if ($therapyPrediction): ?>
                                <div class="alert alert-info mt-3">
                                    <small>
                                        <i class="bi bi-lightbulb me-1"></i>
                                        <strong>Therapy:</strong> <?php echo htmlspecialchars($therapyPrediction); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Export Section -->
            <div class="card border-primary mt-4">
                <div class="card-body">
                    <h5 class="card-title text-primary">
                        <i class="bi bi-download me-2"></i> Export Options
                    </h5>
                    <p class="card-text">Download your complete test history for record-keeping or sharing with specialists.</p>
                    <div class="d-flex gap-2">
                        <a href="generate-report.php?all=true&format=pdf" class="btn btn-primary">
                            <i class="bi bi-file-pdf me-1"></i> Export as PDF
                        </a>
                        <a href="generate-report.php?all=true&format=csv" class="btn btn-outline-primary">
                            <i class="bi bi-file-spreadsheet me-1"></i> Export as CSV
                        </a>
                        <a href="generate-report.php?all=true&format=excel" class="btn btn-outline-success">
                            <i class="bi bi-file-excel me-1"></i> Export as Excel
                        </a>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-clipboard-x"></i>
                </div>
                <h3 class="mb-3">No Tests Found</h3>
                <p class="text-muted mb-4">You haven't completed any screening tests yet.</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="test.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle me-2"></i> Take Your First Test
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add search input if needed
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'form-control mb-3';
            searchInput.placeholder = 'Search tests by child name, date, or results...';
            
            // Insert search after stats
            const statsRow = document.querySelector('.row.mb-4');
            if (statsRow) {
                const searchContainer = document.createElement('div');
                searchContainer.className = 'col-12';
                searchContainer.appendChild(searchInput);
                statsRow.parentNode.insertBefore(searchContainer, statsRow.nextSibling);
            }
            
            // Search functionality
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const testCards = document.querySelectorAll('.test-card');
                
                testCards.forEach(card => {
                    const cardText = card.textContent.toLowerCase();
                    if (cardText.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Add filter toggles
            const filters = document.createElement('div');
            filters.className = 'd-flex flex-wrap gap-2 mb-3';
            filters.innerHTML = `
                <button class="btn btn-sm btn-outline-primary filter-btn active" data-filter="all">All Tests</button>
                <button class="btn btn-sm btn-outline-success filter-btn" data-filter="positive">Positive</button>
                <button class="btn btn-sm btn-outline-danger filter-btn" data-filter="negative">Negative</button>
                <button class="btn btn-sm btn-outline-info filter-btn" data-filter="therapy">With Therapy</button>
            `;
            
            // Insert filters
            const testList = document.querySelector('.test-list');
            if (testList) {
                testList.parentNode.insertBefore(filters, testList);
            }
            
            // Filter functionality
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    // Add active to clicked button
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    const testCards = document.querySelectorAll('.test-card');
                    
                    testCards.forEach(card => {
                        let show = false;
                        
                        switch(filter) {
                            case 'all':
                                show = true;
                                break;
                            case 'positive':
                                if (card.querySelector('.status-positive')) show = true;
                                break;
                            case 'negative':
                                if (card.querySelector('.status-negative')) show = true;
                                break;
                            case 'therapy':
                                if (card.querySelector('.therapy-badge')) show = true;
                                break;
                        }
                        
                        card.style.display = show ? 'block' : 'none';
                    });
                });
            });
            
            // Add sort functionality
            const sortSelect = document.createElement('select');
            sortSelect.className = 'form-select ms-auto';
            sortSelect.style.width = 'auto';
            sortSelect.innerHTML = `
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="name">Child Name A-Z</option>
                <option value="asd_level">ASD Level (High-Low)</option>
            `;
            
            // Insert sort
            const actionsBar = document.querySelector('.d-flex.justify-content-between.align-items-center.mb-4');
            if (actionsBar) {
                const sortContainer = document.createElement('div');
                sortContainer.className = 'd-flex align-items-center';
                sortContainer.innerHTML = '<span class="me-2">Sort:</span>';
                sortContainer.appendChild(sortSelect);
                actionsBar.querySelector('div:last-child').prepend(sortContainer);
            }
            
            // Print functionality
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-outline-secondary';
            printBtn.innerHTML = '<i class="bi bi-printer me-1"></i> Print';
            printBtn.addEventListener('click', () => window.print());
            
            if (actionsBar) {
                actionsBar.querySelector('div:last-child').prepend(printBtn);
            }
        });
    </script>
</body>
</html>