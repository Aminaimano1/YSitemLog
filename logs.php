<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

$pageTitle = 'System Logs';

$database = new Database();
$pdo = $database->getConnection();

// Get filter parameters
$user = isset($_GET['user']) ? (int)$_GET['user'] : '';
$item = isset($_GET['item']) ? (int)$_GET['item'] : '';
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;

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

// Get total records for pagination
$countSql = "
    SELECT COUNT(*) 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN items i ON l.item_id = i.id 
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();

$pagination = getPagination($totalRecords, $recordsPerPage, $page);

// Get logs
$sql = "
    SELECT l.*, u.name as user_name, i.name as item_name 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN items i ON l.item_id = i.id 
    $whereClause 
    ORDER BY l.timestamp DESC 
    LIMIT " . (int)$recordsPerPage . " OFFSET " . (int)$pagination['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter
$stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get items for filter
$stmt = $pdo->query("SELECT id, name FROM items ORDER BY name");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get actions for filter
$stmt = $pdo->query("SELECT DISTINCT action FROM logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="user" class="form-label">User</label>
                <select class="form-select" id="user" name="user">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="item" class="form-label">Item</label>
                <select class="form-select" id="item" name="item">
                    <option value="">All Items</option>
                    <?php foreach ($items as $i): ?>
                        <option value="<?php echo $i['id']; ?>" <?php echo $item == $i['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($i['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $action === $a ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                    <a href="logs.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Export Button -->
<div class="mb-3">
    <a href="export_logs.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
        <i class="fas fa-download me-2"></i>Export to CSV
    </a>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>System Logs (<?php echo $totalRecords; ?> total)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="text-muted">No logs found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Item</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <small><?php echo formatDate($log['timestamp']); ?></small>
                            </td>
                            <td>
                                <?php if ($log['user_name']): ?>
                                    <span class="badge bg-dark"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $actionClass = 'bg-primary';
                                if (strpos($log['action'], 'borrowed') !== false) $actionClass = 'bg-success';
                                elseif (strpos($log['action'], 'returned') !== false) $actionClass = 'bg-dark';
                                elseif (strpos($log['action'], 'deleted') !== false) $actionClass = 'bg-danger';
                                elseif (strpos($log['action'], 'added') !== false) $actionClass = 'bg-info';
                                ?>
                                <span class="badge <?php echo $actionClass; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                            </td>
                            <td>
                                <?php if ($log['item_name']): ?>
                                    <?php echo htmlspecialchars($log['item_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['details']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['details']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['totalPages'] > 1): ?>
            <style>
                .pagination .page-link {
                    color: #000 !important;
                    border-color: #dee2e6 !important;
                }
                .pagination .page-link:hover {
                    background-color: #000 !important;
                    color: white !important;
                    border-color: #000 !important;
                }
                .pagination .page-item.active .page-link {
                    background-color: #000 !important;
                    border-color: #000 !important;
                    color: white !important;
                }
            </style>
            <nav aria-label="Logs pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($pagination['hasPrev']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['currentPage'] - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['currentPage'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['hasNext']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['currentPage'] + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 