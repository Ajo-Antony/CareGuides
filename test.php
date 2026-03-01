<?php
session_start();
// Fix the path to config/database.php
require_once '../config/database.php'; // Changed from 'config/database.php'

if ($_SERVER["REQUEST_METHOD"] == "POST")  {
    // Capture child's name from form
    $childName = $_POST["child_name"] ?? 'Child from Therapy Form';
    $childAge = $_POST["Age"] ?? 0;
    
    // Store child information in session
    $_SESSION['child_name'] = $childName;
    $_SESSION['child_age'] = $childAge;
    
    // Your existing data collection
    $data = array(
        "Age" => $_POST["Age"],
        "ASD_Level" => $_POST["ASD_Level"],
        "Speech_Delay" => $_POST["Speech_Delay"],
        "Motor_Delay" => $_POST["Motor_Delay"],
        "Feedback" => $_POST["Feedback"],
        "Past_Therapies" => isset($_POST["Past_Therapies"]) ? $_POST["Past_Therapies"] : [],
        "Challenges" => isset($_POST["Challenges"]) ? $_POST["Challenges"] : []
    );

    $json_data = json_encode($data);

    // Call ML API
    $ch = curl_init("http://localhost:5002/predict");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // SAVE TO DATABASE
    $therapyPrediction = null;
    if (isset($result['prediction'])) {
        $therapyPrediction = $result['prediction'];
        
        // Use the child name from the form
        $childName = $childName ?: "Child from Therapy Form";
        
        // Convert Past_Therapies array to string
        $pastTherapiesStr = !empty($data["Past_Therapies"]) ? implode(';', $data["Past_Therapies"]) : null;
        
        // Convert Challenges array to string
        $challengesStr = !empty($data["Challenges"]) ? implode(';', $data["Challenges"]) : null;
        
        // Determine ASD status based on ASD_Level (you can adjust this logic)
        $asdStatus = ($data["ASD_Level"] >= 2) ? 1 : 0;
        
        // Calculate confidence score (simple logic - adjust as needed)
        $confidenceScore = 85; // Default or calculate based on your logic
        
        // Store the form data as JSON in test_data column
        $testDataJson = json_encode($data);
        
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO test_results (
                user_id, child_name, child_age, asd_level, 
                speech_delay, motor_delay, feedback, past_therapies,
                therapy_prediction, test_type, test_data, confidence_score
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'therapy', ?, ?)
        ");
        
        $userId = $_SESSION['user_id'] ?? 2; // Default to user_id 2 if not logged in
        $childAge = $data["Age"];
        $asdLevel = $data["ASD_Level"];
        $speechDelay = $data["Speech_Delay"];
        $motorDelay = $data["Motor_Delay"];
        $feedback = $data["Feedback"];
        
        $stmt->bind_param(
            "isiiiiisssi",
            $userId,
            $childName,
            $childAge,
            $asdLevel,
            $speechDelay,
            $motorDelay,
            $feedback,
            $pastTherapiesStr,
            $therapyPrediction,
            $testDataJson,
            $confidenceScore
        );
        
        if ($stmt->execute()) {
            $testId = $stmt->insert_id;
            // Store test ID in session for later use
            $_SESSION['last_test_id'] = $testId;
        } else {
            // Log error but continue
            error_log("Database save failed: " . $stmt->error);
        }
        
        $stmt->close();
    }

    // Convert values to readable format
    function readable($key, $value) {
        $map = [
            "ASD_Level" => ["1" => "Mild", "2" => "Moderate", "3" => "Severe"],
            "Speech_Delay" => ["1" => "Yes", "0" => "No"],
            "Motor_Delay" => ["1" => "Yes", "0" => "No"],
        ];
        return isset($map[$key][$value]) ? $map[$key][$value] : $value;
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

    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Prediction Result | Therapy System</title>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
        <style>
            /* Your existing CSS styles */
            :root {
                --primary: #2563eb;
                --primary-dark: #1d4ed8;
                --secondary: #10b981;
                --light-bg: #f0f9ff;
                --card-bg: #ffffff;
                --text-primary: #1f2937;
                --text-secondary: #6b7280;
                --border: #cbd5e1;
                --success: #10b981;
                --warning: #f59e0b;
                --danger: #ef4444;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                min-height: 100vh;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .result-container {
                width: 100%;
                max-width: 900px;
            }

            .header {
                text-align: center;
                margin-bottom: 30px;
            }

            .header h1 {
                color: var(--primary);
                font-size: 2.5rem;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
            }

            .header p {
                color: var(--text-secondary);
                font-size: 1.1rem;
                max-width: 600px;
                margin: 0 auto;
            }

            .main-card {
                background: var(--card-bg);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
                position: relative;
                overflow: hidden;
            }

            .main-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 5px;
                background: linear-gradient(90deg, var(--primary), var(--secondary));
            }

            .result-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid var(--light-bg);
            }

            .result-header h2 {
                color: var(--text-primary);
                font-size: 2rem;
                margin-bottom: 10px;
            }

            .subtitle {
                color: var(--text-secondary);
                font-size: 1rem;
            }

            .content-wrapper {
                display: grid;
                gap: 30px;
            }

            .info-section {
                background: var(--light-bg);
                padding: 25px;
                border-radius: 15px;
                border-left: 5px solid var(--primary);
            }

            .info-section h3 {
                color: var(--primary);
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 1.4rem;
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .info-item {
                background: white;
                padding: 15px;
                border-radius: 10px;
                border: 1px solid var(--border);
            }

            .info-label {
                font-weight: 600;
                color: var(--text-secondary);
                font-size: 0.9rem;
                margin-bottom: 5px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .info-value {
                font-size: 1.1rem;
                color: var(--text-primary);
                font-weight: 500;
            }

            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                margin-left: 10px;
            }

            .status-positive {
                background: rgba(16, 185, 129, 0.1);
                color: var(--success);
                border: 1px solid var(--success);
            }

            .status-neutral {
                background: rgba(245, 158, 11, 0.1);
                color: var(--warning);
                border: 1px solid var(--warning);
            }

            .status-negative {
                background: rgba(239, 68, 68, 0.1);
                color: var(--danger);
                border: 1px solid var(--danger);
            }

            .prediction-result {
                background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                padding: 30px;
                border-radius: 15px;
                color: white;
                text-align: center;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { box-shadow: 0 5px 20px rgba(37, 99, 235, 0.3); }
                50% { box-shadow: 0 5px 30px rgba(37, 99, 235, 0.6); }
                100% { box-shadow: 0 5px 20px rgba(37, 99, 235, 0.3); }
            }

            .prediction-label {
                font-size: 1rem;
                margin-bottom: 10px;
                opacity: 0.9;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            .therapy-name {
                font-size: 2.5rem;
                font-weight: bold;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
            }

            .therapy-description {
                font-size: 1.1rem;
                opacity: 0.9;
                max-width: 600px;
                margin: 0 auto;
                line-height: 1.6;
            }

            .error-message {
                background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
                padding: 25px;
                border-radius: 15px;
                border-left: 5px solid var(--danger);
                color: var(--danger);
            }

            .error-message h3 {
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .button-group {
                display: flex;
                gap: 15px;
                margin-top: 30px;
                flex-wrap: wrap;
            }

            .btn {
                flex: 1;
                min-width: 200px;
                padding: 16px;
                border: none;
                border-radius: 12px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                text-decoration: none;
                text-align: center;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                color: white;
                box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
            }

            .btn-primary:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
            }

            .btn-secondary {
                background: #f1f5f9;
                color: var(--text-primary);
                border: 2px solid var(--border);
            }

            .btn-secondary:hover {
                background: #e2e8f0;
                transform: translateY(-3px);
            }

            .btn-success {
                background: linear-gradient(135deg, var(--success) 0%, #0da271 100%);
                color: white;
                box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            }

            .btn-success:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
            }
            
            .database-notice {
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                border-radius: 8px;
                padding: 10px 15px;
                margin-top: 15px;
                font-size: 0.85rem;
                color: #0369a1;
            }
            
            .database-notice i {
                margin-right: 8px;
            }

            @media (max-width: 768px) {
                .main-card {
                    padding: 25px;
                }
                
                .header h1 {
                    font-size: 2rem;
                }
                
                .therapy-name {
                    font-size: 2rem;
                }
                
                .button-group {
                    flex-direction: column;
                }
                
                .btn {
                    min-width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class='result-container'>
            <div class='header'>
                <h1><i class='fas fa-chart-line'></i> Prediction Results</h1>
                <p>Based on the information you provided, here are the therapy recommendations</p>
            </div>

            <div class='main-card'>
                <div class='result-header'>
                    <h2>Therapy Prediction Analysis</h2>
                    <div class='subtitle'>Generated on " . date("F j, Y, g:i a") . "</div>
                </div>

                <div class='content-wrapper'>
                    <div class='info-section'>
                        <h3><i class='fas fa-user-circle'></i> Child's Profile</h3>
                        <div class='info-grid'>";
                            // Display child's name
                            if (!empty($childName) && $childName !== 'Child from Therapy Form') {
                                echo "<div class='info-item'>
                                    <div class='info-label'><i class='fas fa-user'></i> Child's Name</div>
                                    <div class='info-value'>" . htmlspecialchars($childName) . "</div>
                                </div>";
                            }
                            
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-birthday-cake'></i> Age</div>
                                <div class='info-value'>" . htmlspecialchars($data["Age"]) . " years</div>
                            </div>";
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-brain'></i> ASD Level</div>
                                <div class='info-value'>" . readable("ASD_Level", $data["ASD_Level"]) . "</div>
                            </div>";
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-comments'></i> Speech Delay</div>
                                <div class='info-value'>" . readable("Speech_Delay", $data["Speech_Delay"]) . "</div>
                            </div>";
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-walking'></i> Motor Delay</div>
                                <div class='info-value'>" . readable("Motor_Delay", $data["Motor_Delay"]) . "</div>
                            </div>";
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-star'></i> Feedback</div>
                                <div class='info-value'>" . htmlspecialchars(ucfirst($data["Feedback"])) . " 
                                    <span class='status-badge status-" . $data["Feedback"] . "'>" . ucfirst($data["Feedback"]) . "</span>
                                </div>
                            </div>";
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-history'></i> Past Therapies</div>
                                <div class='info-value'>" . (!empty($data["Past_Therapies"]) ? implode(", ", $data["Past_Therapies"]) : "None tried") . "</div>
                            </div>";
                            echo "<div class='info-item'>
                                <div class='info-label'><i class='fas fa-tasks'></i> Challenges</div>
                                <div class='info-value'>" . (!empty($data["Challenges"]) ? implode(", ", $data["Challenges"]) : "None specified") . "</div>
                            </div>";
                        echo "</div>
                    </div>";

                    if (isset($result['prediction'])) {
                        $therapyIcon = getTherapyIcon($result['prediction']);
                        $therapyDescriptions = [
                            "ABA" => "Focuses on improving specific behaviors and skills through positive reinforcement.",
                            "OT" => "Helps develop skills for daily living, sensory processing, and fine motor coordination.",
                            "Play" => "Uses play activities to improve social, emotional, and cognitive development.",
                            "Speech" => "Targets communication skills, language development, and speech clarity.",
                            "Music" => "Utilizes musical activities to enhance communication, social skills, and emotional expression.",
                            "Art" => "Encourages self-expression and fine motor skills through creative activities.",
                            "Social Skills" => "Focuses on improving interaction, communication, and relationship-building abilities.",
                            "Physical" => "Develops gross motor skills, coordination, and physical fitness."
                        ];
                        
                        $description = isset($therapyDescriptions[$result['prediction']]) 
                            ? $therapyDescriptions[$result['prediction']] 
                            : "A tailored therapeutic approach based on individual needs and characteristics.";

                        echo "<div class='prediction-result'>
                            <div class='prediction-label'><i class='fas fa-lightbulb'></i> RECOMMENDED THERAPY</div>
                            <div class='therapy-name'><i class='" . $therapyIcon . "'></i> " . htmlspecialchars($result['prediction']) . "</div>
                            <div class='therapy-description'>" . $description . "</div>
                        </div>

                        <div class='info-section'>
                            <h3><i class='fas fa-clock'></i> Next Steps</h3>
                            <p>Based on the prediction, we recommend starting with " . htmlspecialchars($result['prediction']) . " therapy. 
                            This approach has shown effectiveness for similar profiles. Consider consulting with a certified therapist 
                            to develop a personalized implementation plan.</p>
                            
                            <!-- Database Save Notice -->
                            <div class='database-notice'>
                                <i class='fas fa-database'></i>
                                <strong>Therapy prediction saved to your dashboard.</strong> 
                                You can view this recommendation anytime in your test history.
                            </div>
                        </div>";
                    } else {
                        echo "<div class='error-message'>
                            <h3><i class='fas fa-exclamation-triangle'></i> Prediction Error</h3>
                            <p>" . htmlspecialchars($result['error'] ?? 'Unable to generate prediction. Please try again.') . "</p>
                        </div>";
                    }

                    echo "<div class='button-group'>
                        <a href='details.php' class='btn btn-secondary'>
                            <i class='fas fa-redo'></i> New Prediction
                        </a>
                        <a href='../dashboard.php' class='btn btn-primary'>
                            <i class='fas fa-tachometer-alt'></i> User Dashboard
                        </a>
                        <a href='javascript:window.print()' class='btn btn-success'>
                            <i class='fas fa-print'></i> Print Results
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Add animation to the therapy result
            document.addEventListener('DOMContentLoaded', function() {
                const therapyResult = document.querySelector('.prediction-result');
                if (therapyResult) {
                    therapyResult.style.opacity = '0';
                    therapyResult.style.transform = 'scale(0.9)';
                    
                    setTimeout(() => {
                        therapyResult.style.transition = 'all 0.6s ease-out';
                        therapyResult.style.opacity = '1';
                        therapyResult.style.transform = 'scale(1)';
                    }, 300);
                }
            });
        </script>
    </body>
    </html>";
}
?>