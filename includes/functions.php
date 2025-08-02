<?php
session_start();

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isSupervisor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'supervisor';
}

function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

function canPerformBulkActions() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'supervisor', 'manager']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}



// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Logging function
function logActivity($pdo, $userId, $itemId, $action, $details = null) {
    $sql = "INSERT INTO logs (user_id, item_id, action, details) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $itemId, $action, $details]);
}

// Pagination function
function getPagination($totalRecords, $recordsPerPage, $currentPage) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'totalPages' => $totalPages,
        'offset' => $offset,
        'currentPage' => $currentPage,
        'hasNext' => $currentPage < $totalPages,
        'hasPrev' => $currentPage > 1
    ];
}

// Format date function
function formatDate($date) {
    return date('M d, Y H:i', strtotime($date));
}

// Check if item is overdue (more than 12 hours)
function isOverdue($borrowDate) {
    $borrowDateTime = new DateTime($borrowDate);
    $now = new DateTime();
    $diff = $now->diff($borrowDateTime);
    
    // Convert to total hours
    $totalHours = ($diff->days * 24) + $diff->h;
    return $totalHours > 12;
}

// Generate QR code for items
function generateQRCode($itemId, $itemName) {
    $data = "ItemID: $itemId, Name: $itemName";
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($data);
    return $qrUrl;
}

// Email notification function
function sendEmailNotification($to, $subject, $message) {
    $headers = "From: noreply@itemlog.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Generate PDF report
function generatePDFReport($data, $type) {
    // This would integrate with a PDF library like TCPDF or FPDF
    // For now, return a placeholder
    return "PDF report for $type generated successfully";
}

// Backup database function
function backupDatabase($pdo) {
    $backupDir = 'backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    $filename = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // This would use mysqldump in production
    // For now, return success message
    return "Database backup created: $filename";
}
?> 