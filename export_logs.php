<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();

// Get filter parameters
$user = isset($_GET['user']) ? (int)$_GET['user'] : '';
$item = isset($_GET['item']) ? (int)$_GET['item'] : '';
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($user)) {
    $whereConditions[] = "l.user_id = ?";
    $params[] = $user;
}

if (!empty($item)) {
    $whereConditions[] = "l.item_id = ?";
    $params[] = $item;
}

if (!empty($action)) {
    $whereConditions[] = "l.action LIKE ?";
    $params[] = "%$action%";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(l.timestamp) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(l.timestamp) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get logs
$sql = "
    SELECT l.*, u.name as user_name, i.name as item_name 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN items i ON l.item_id = i.id 
    $whereClause 
    ORDER BY l.timestamp DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'Timestamp',
    'User',
    'Action',
    'Item',
    'Details'
]);

// Write data rows
foreach ($logs as $log) {
    fputcsv($output, [
        $log['timestamp'],
        $log['user_name'] ?: 'Unknown',
        $log['action'],
        $log['item_name'] ?: 'N/A',
        $log['details'] ?: ''
    ]);
}

fclose($output);
exit();
?> 