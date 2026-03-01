<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'customer';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Query database for user
        $stmt = $conn->prepare("SELECT id, password, user_type, full_name, is_active, email FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                if ($user['is_active'] == 1) {
                    // Check user type matches login type
                    if ($login_type == 'admin' && $user['user_type'] != 'admin') {
                        $error = "This account is not an administrator account. Please use user login.";
                    } else if ($login_type == 'customer' && $user['user_type'] == 'admin') {
                        $error = "Administrator account detected. Please use admin login.";
                    } else {
                        // Update last login
                        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->bind_param("i", $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $username;
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['logged_in'] = true;
                        
                        // Track login activity
                        trackActivity($conn, $user['id'], 'User logged in successfully');
                        
                        // Redirect based on user type
                        if ($user['user_type'] == 'admin') {
                            header('Location: admin/admin_dashboard.php');
                        } else {
                            header('Location: dashboard.php');
                        }
                        exit();
                    }
                } else {
                    $error = "Your account is deactivated. Please contact support.";
                }
            } else {
                $error = "Invalid username or password.";
                trackActivity($conn, null, 'Wrong password for: ' . $username);
            }
        } else {
            $error = "Invalid username or password.";
            trackActivity($conn, null, 'Non-existent username: ' . $username);
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
    <title>Autism Care System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #17a2b8;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            margin: 20px auto;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 1rem;
        }
        
        .login-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .login-type-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .login-type-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(74, 111, 165, 0.3);
        }
        
        .demo-credentials {
            background: #e8f4fc;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1 class="login-title">
                <i class="fas fa-heartbeat me-2" style="color: var(--primary-color);"></i>
                AutismCare
            </h1>
            <p class="login-subtitle">Login to access the system</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="login_type" id="loginType" value="customer">
            
            <div class="login-type-selector">
                <button type="button" class="login-type-btn active" onclick="setLoginType('customer')">
                    <i class="fas fa-user me-1"></i> User Login
                </button>
                <button type="button" class="login-type-btn" onclick="setLoginType('admin')">
                    <i class="fas fa-shield-alt me-1"></i> Admin Login
                </button>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" class="form-control" name="username" placeholder="Enter username" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe">
                <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
            
            <button type="submit" class="btn btn-login mb-3">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
            
            <div class="text-center mb-3">
                <a href="register.php" class="text-decoration-none">Create new account</a> | 
                <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
            </div>
        </form>
        
        <div class="demo-credentials">
            <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
            <p class="mb-1"><strong>User:</strong> testuser / password123</p>
            <p class="mb-0"><strong>Admin:</strong> admin / admin123</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.querySelector('input[name="password"]');
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
        
        // Set login type
        function setLoginType(type) {
            document.getElementById('loginType').value = type;
            document.querySelectorAll('.login-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>