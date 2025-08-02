<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Log the logout activity
    logActivity($pdo, $_SESSION['user_id'], null, 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?> 