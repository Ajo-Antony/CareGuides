<?php
session_start();
require_once 'config/database.php';

// If already logged in, redirect to home
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $fullName = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (!empty($fullName) && strlen($fullName) > 100) {
        $errors[] = "Full name cannot exceed 100 characters.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if username/email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->bind_param("s", $username);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $errors[] = "Username already exists. Please choose another.";
                }
                $checkStmt->close();
                
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $errors[] = "Email already registered. Please use another email.";
                }
                $checkStmt->close();
            }
        }
        $stmt->close();
    }
    
    // If no errors, create account
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, full_name, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $fullName, $hashedPassword);
        
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            
            // Track registration
            trackActivity($userId, 'registration', 'New user registered: ' . $username);
            
            // Auto-login the user
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;
            $_SESSION['user_type'] = 'user';
            $_SESSION['logged_in'] = true;
            
            // Update last login
            $conn->query("UPDATE users SET last_login = NOW() WHERE id = $userId");
            
            // Track first login
            trackActivity($userId, 'first_login', 'First login after registration');
            
            $success = true;
            
            // Redirect to welcome page
            header("Location: dashboard.php?welcome=1");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Autism Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .register-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #11998e;
            box-shadow: 0 0 0 0.25rem rgba(17, 153, 142, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.4);
        }
        
        .password-strength {
            height: 5px;
            border-radius: 2px;
            margin-top: 5px;
            background: #e0e0e0;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }
        
        .requirements {
            font-size: 0.85rem;
            color: #666;
        }
        
        .requirement {
            margin-bottom: 5px;
        }
        
        .requirement.met {
            color: #28a745;
        }
        
        .requirement.unmet {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo" style="font-size: 2.5rem; font-weight: bold;">
                <i class="fas fa-heart-pulse"></i> AutismCare
            </div>
            <div class="subtitle">Join Our Community</div>
        </div>
        
        <div class="register-body">
            <h3 class="text-center mb-4">Create Account</h3>
            <p class="text-center text-muted mb-4">Fill in your details to get started</p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Registration successful! Redirecting...
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="john_doe" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <small class="form-text text-muted">3-50 characters, letters, numbers, underscores only</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="john@example.com" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-id-card"></i>
                        </span>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               placeholder="John Doe"
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="••••••" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" placeholder="••••••" required>
                        </div>
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                </div>
                
                <div class="mb-4 requirements">
                    <div class="requirement" id="reqLength">
                        <i class="fas fa-circle me-1"></i> At least 6 characters
                    </div>
                    <div class="requirement" id="reqMatch">
                        <i class="fas fa-circle me-1"></i> Passwords match
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" class="text-decoration-none">Terms of Service</a>
                        and <a href="#" class="text-decoration-none">Privacy Policy</a>
                    </label>
                </div>
                
                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i> Create Account
                    </button>
                </div>
                
                <div class="text-center">
                    <p>Already have an account? 
                        <a href="login.php" class="text-decoration-none">Login here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const reqLength = document.getElementById('reqLength');
            
            let strength = 0;
            
            // Length check
            if (password.length >= 6) {
                strength += 25;
                reqLength.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> At least 6 characters';
                reqLength.className = 'requirement met';
            } else {
                reqLength.innerHTML = '<i class="fas fa-circle me-1"></i> At least 6 characters';
                reqLength.className = 'requirement unmet';
            }
            
            // Upper case check
            if (/[A-Z]/.test(password)) strength += 25;
            // Number check
            if (/[0-9]/.test(password)) strength += 25;
            // Special character check
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            // Update color based on strength
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#ffc107';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
        }
        
        // Password match check
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            const reqMatch = document.getElementById('reqMatch');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                matchText.className = 'form-text';
                reqMatch.innerHTML = '<i class="fas fa-circle me-1"></i> Passwords match';
                reqMatch.className = 'requirement unmet';
            } else if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'form-text text-success';
                reqMatch.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Passwords match';
                reqMatch.className = 'requirement met';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'form-text text-danger';
                reqMatch.innerHTML = '<i class="fas fa-circle me-1"></i> Passwords match';
                reqMatch.className = 'requirement unmet';
            }
        }
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            updatePasswordStrength();
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9_]+$/;
            
            if (!regex.test(username) && username.length > 0) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters!');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('You must agree to the Terms of Service and Privacy Policy!');
                return false;
            }
            
            return true;
        });
        
        // Initialize
        updatePasswordStrength();
        checkPasswordMatch();
    </script>
</body>
</html>