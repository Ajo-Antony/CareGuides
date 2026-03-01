<?php
session_start();
require_once 'config/database.php';
requireLogin();

// If just registered, track the activity
if (!isset($_SESSION['welcome_shown'])) {
    trackActivity($_SESSION['user_id'], 'first_login', 'First login after registration');
    $_SESSION['welcome_shown'] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Autism Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .welcome-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .welcome-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .welcome-body {
            padding: 40px;
        }
        .welcome-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #f093fb;
        }
        .feature-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .feature-icon {
            font-size: 1.5rem;
            color: #f5576c;
            margin-bottom: 10px;
        }
        .btn-start {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="welcome-header">
            <div class="welcome-icon">
                <i class="bi bi-emoji-smile"></i>
            </div>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']); ?>!</h1>
            <p class="mb-0">Your account has been successfully created.</p>
        </div>
        
        <div class="welcome-body">
            <h3 class="text-center mb-4">Getting Started</h3>
            <p class="text-center text-muted mb-4">
                Welcome to the Autism Care System. Here's what you can do:
            </p>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <h5>Take Screening Test</h5>
                        <p class="text-muted small">
                            Complete the autism screening questionnaire for your child.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h5>Book Appointments</h5>
                        <p class="text-muted small">
                            Schedule appointments with specialists for consultation.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <h5>View Test History</h5>
                        <p class="text-muted small">
                            Track all your previous screening tests and results.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <h5>Profile Management</h5>
                        <p class="text-muted small">
                            Update your personal information and preferences.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-start me-3">
                    <i class="bi bi-house-door me-2"></i> Go to Homepage
                </a>
                <a href="test.php" class="btn btn-outline-primary">
                    <i class="bi bi-clipboard-check me-2"></i> Start Screening Test
                </a>
            </div>
            
            <div class="text-center mt-4 pt-3 border-top">
                <small class="text-muted">
                    Need help? Visit our <a href="help.php" class="text-decoration-none">Help Center</a> or 
                    <a href="contact.php" class="text-decoration-none">Contact Support</a>
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto redirect after 30 seconds
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 30000);
    </script>
</body>
</html>