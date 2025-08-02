<?php
require_once 'includes/functions.php';

// Redirect to appropriate page
if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?> 