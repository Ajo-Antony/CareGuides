<?php
session_start();
// Get child information from session if available
$childName = $_SESSION['child_name'] ?? '';
$childAge = $_SESSION['child_age'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapy Details Form</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .container {
            width: 100%;
            max-width: 800px;
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

        .form-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .typing-box {
            background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);
            padding: 20px 25px;
            border-radius: 15px;
            border-left: 5px solid var(--primary);
            margin-bottom: 30px;
            font-size: 1.1rem;
            color: var(--text-primary);
            min-height: 70px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .typing-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--primary);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .typing-cursor {
            display: inline-block;
            animation: blink 1s infinite;
            font-weight: bold;
        }

        @keyframes blink {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0; }
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .label-with-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .info {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        input, select, .select2-container {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8fafc;
        }

        input:focus, select:focus, .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary);
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .select2-container--default .select2-selection--multiple {
            border: 2px solid var(--border);
            min-height: 48px;
            border-radius: 12px;
            background: #f8fafc;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #fef3c7;
        }

        .multi-choice-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .multi-choice-option {
            background: #f1f5f9;
            padding: 14px 18px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
        }

        .multi-choice-option:hover {
            background: #e0f2fe;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .multi-choice-option input[type="checkbox"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .multi-choice-option.selected {
            background: #dbeafe;
            border-color: var(--primary);
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 40px;
        }

        .btn {
            flex: 1;
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

        .progress-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }

        .progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #cbd5e1;
            transition: all 0.3s;
        }

        .progress-dot.active {
            background: var(--primary);
            width: 24px;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .form-card {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .multi-choice-group {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heartbeat"></i> Therapy Prediction System</h1>
            <p>Fill in the details below to get personalized therapy recommendations based on your child's needs</p>
        </div>

        <div class="form-card">
            <div class="typing-box" id="typingText">
                <i class="fas fa-comment-medical" style="margin-right: 10px; color: var(--primary);"></i>
                <span id="typingContent"></span><span class="typing-cursor">|</span>
            </div>

            <form action="test.php" method="post">
                <!-- Child's Name Field -->
                <div class="form-group">
                    <label for="child_name" class="label-with-icon">
                        <i class="fas fa-user"></i> Child's Name
                    </label>
                    <div class="info">Name of the child for therapy recommendation</div>
                    <input type="text" id="child_name" name="child_name" 
                           value="<?php echo htmlspecialchars($childName); ?>" 
                           placeholder="Enter child's name">
                </div>

                <div class="form-group">
                    <label for="age" class="label-with-icon">
                        <i class="fas fa-birthday-cake"></i> Age
                    </label>
                    <div class="info">Child's age (between 2 and 15 years)</div>
                    <input type="number" id="age" name="Age" min="2" max="15" 
                           value="<?php echo htmlspecialchars($childAge); ?>" 
                           required placeholder="Enter age">
                </div>

                <div class="form-group">
                    <label for="asd" class="label-with-icon">
                        <i class="fas fa-brain"></i> ASD Level
                    </label>
                    <div class="info">The diagnosed severity of Autism Spectrum Disorder</div>
                    <select id="asd" name="ASD_Level" required>
                        <option value="">-- Select Severity Level --</option>
                        <option value="1">Mild (Level 1)</option>
                        <option value="2">Moderate (Level 2)</option>
                        <option value="3">Severe (Level 3)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="speech" class="label-with-icon">
                        <i class="fas fa-comments"></i> Speech Delay
                    </label>
                    <div class="info">Does the child show delayed speech development?</div>
                    <select id="speech" name="Speech_Delay" required>
                        <option value="">-- Select Option --</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="motor" class="label-with-icon">
                        <i class="fas fa-walking"></i> Motor Delay
                    </label>
                    <div class="info">Is there a delay in motor skills such as walking or coordination?</div>
                    <select id="motor" name="Motor_Delay" required>
                        <option value="">-- Select Option --</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="feedback" class="label-with-icon">
                        <i class="fas fa-star"></i> Feedback
                    </label>
                    <div class="info">General caregiver or therapist feedback on progress</div>
                    <select id="feedback" name="Feedback" required>
                        <option value="">-- Select Feedback --</option>
                        <option value="positive">Positive</option>
                        <option value="neutral">Neutral</option>
                        <option value="negative">Negative</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="past" class="label-with-icon">
                        <i class="fas fa-history"></i> Past Therapies
                    </label>
                    <div class="info">Therapies previously tried. You can select multiple options</div>
                    <select id="past" name="Past_Therapies[]" multiple="multiple">
                        <option value="ABA">ABA (Applied Behavior Analysis)</option>
                        <option value="OT">OT (Occupational Therapy)</option>
                        <option value="Play">Play Therapy</option>
                        <option value="Speech">Speech Therapy</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="label-with-icon">
                        <i class="fas fa-tasks"></i> Additional Challenges (Optional)
                    </label>
                    <div class="info">Select one or more challenges the child faces</div>
                    <div class="multi-choice-group">
                        <label class="multi-choice-option">
                            <input type="checkbox" name="Challenges[]" value="Social Difficulty"> Social Difficulty
                        </label>
                        <label class="multi-choice-option">
                            <input type="checkbox" name="Challenges[]" value="Sensory Issues"> Sensory Issues
                        </label>
                        <label class="multi-choice-option">
                            <input type="checkbox" name="Challenges[]" value="Learning Delay"> Learning Delay
                        </label>
                        <label class="multi-choice-option">
                            <input type="checkbox" name="Challenges[]" value="Hyperactivity"> Hyperactivity
                        </label>
                    </div>
                </div>

                <div class="button-group">
                    <a href="..\dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-magic"></i> Predict Effective Therapy
                    </button>
                </div>

                <div class="progress-indicator">
                    <div class="progress-dot active"></div>
                    <div class="progress-dot"></div>
                    <div class="progress-dot"></div>
                    <div class="progress-dot"></div>
                </div>
            </form>
        </div>
    </div>

    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2
            $('#past').select2({
                placeholder: "Select past therapies",
                allowClear: true,
                width: '100%'
            });

            // Typing animation
            const typingContent = document.getElementById("typingContent");
            const messages = [
                "Welcome! Please provide details below to get personalized therapy recommendations.",
                "Our AI will analyze the information to suggest the most effective therapy approach.",
                "Please fill all required fields for accurate prediction.",
                "Your data is secure and will be used only for therapeutic recommendations."
            ];
            
            let messageIndex = 0;
            let charIndex = 0;
            let isDeleting = false;
            let typingSpeed = 50;
            let deleteSpeed = 30;
            let pauseBetweenMessages = 2000;

            function typeWriter() {
                const currentMessage = messages[messageIndex];

                if (!isDeleting && charIndex <= currentMessage.length) {
                    typingContent.innerHTML = currentMessage.substring(0, charIndex);
                    charIndex++;
                    setTimeout(typeWriter, typingSpeed);
                } else if (isDeleting && charIndex >= 0) {
                    typingContent.innerHTML = currentMessage.substring(0, charIndex);
                    charIndex--;
                    setTimeout(typeWriter, deleteSpeed);
                } else {
                    isDeleting = !isDeleting;
                    if (!isDeleting) {
                        messageIndex = (messageIndex + 1) % messages.length;
                    }
                    setTimeout(typeWriter, isDeleting ? pauseBetweenMessages : 500);
                }
            }

            typeWriter();

            // Multi-choice checkbox styling
            $('.multi-choice-option').click(function() {
                const checkbox = $(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked'));
                $(this).toggleClass('selected', checkbox.prop('checked'));
            });

            // Form validation and progress indicator
            $('form').on('input change', function() {
                const filledFields = $(this).find('input[required], select[required]').filter(function() {
                    return $(this).val() !== '';
                }).length;
                
                const totalFields = $(this).find('input[required], select[required]').length;
                const progress = Math.min(Math.round((filledFields / totalFields) * 4), 4);
                
                $('.progress-dot').removeClass('active');
                $('.progress-dot').slice(0, progress).addClass('active');
            });
        });
    </script>
</body>
</html>