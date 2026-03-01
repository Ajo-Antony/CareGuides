<?php
// logout.php

require_once 'config/database.php';

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user ID from session BEFORE destroying session
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'unknown';

// Track activity BEFORE destroying session
trackActivity($conn, $userId, "logout - User type: $userType");

// Destroy session
session_destroy();

// Redirect to login
header("Location: index.php");
exit();
?>