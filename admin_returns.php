<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

$pageTitle = 'All Returns';

$database = new Database();
$pdo = $database->getConnection();

// Pagination
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$condition = isset($_GET['condition']) ? sanitizeInput($_GET['condition']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query
$whereConditions = ["b.date_returned IS NOT NULL"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(i.name LIKE ? OR u.name LIKE ? OR b.purpose LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($condition)) {
    $whereConditions[] = "b.return_condition = ?";
    $params[] = $condition;
}

if (!empty($category)) {
    $whereConditions[] = "i.category = ?";
    $params[] = $category;
}

if (!empty($location)) {
    $whereConditions[] = "i.location = ?";
    $params[] = $location;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(b.date_returned) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(b.date_returned) <= ?";
    $params[] = $dateTo;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    JOIN users u ON b.user_id = u.id 
    $whereClause
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$pagination = getPagination($totalRecords, $recordsPerPage, $currentPage);

// Get returns with pagination
$sql = "
    SELECT b.*, i.name as item_name, i.category, i.location, u.name as user_name, u.username as user_username
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    JOIN users u ON b.user_id = u.id 
    $whereClause
    ORDER BY b.date_returned DESC 
    LIMIT $recordsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Get locations for filter
$stmt = $pdo->query("SELECT DISTINCT location FROM items ORDER BY location");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get return conditions for filter
$conditions = ['Good', 'Fair', 'Poor', 'Damaged'];

include 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="search" placeholder="Search items, users, or purpose..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="condition">
                            <option value="">All Conditions</option>
                            <?php foreach ($conditions as $cond): ?>
                                <option value="<?php echo htmlspecialchars($cond); ?>" 
                                        <?php echo $condition === $cond ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cond); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_from" placeholder="From Date" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_to" placeholder="To Date" 
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="admin_returns.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-undo me-2"></i>All Return Records
                </h5>
                <div>
                    <span class="badge bg-success"><?php echo $totalRecords; ?> returns</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($returns)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No return records found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Returned By</th>
                                    <th>Purpose</th>
                                    <th>Quantity</th>
                                    <th>Date Borrowed</th>
                                    <th>Date Returned</th>
                                    <th>Return Condition</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returns as $return): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($return['item_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($return['category']); ?> • 
                                                    <?php echo htmlspecialchars($return['location']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($return['user_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($return['user_username']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                                  title="<?php echo htmlspecialchars($return['purpose']); ?>">
                                                <?php echo htmlspecialchars($return['purpose']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $quantityClass = $return['quantity_borrowed'] <= 2 ? 'bg-danger' : 'bg-dark';
                                            ?>
                                            <span class="badge <?php echo $quantityClass; ?>"><?php echo $return['quantity_borrowed']; ?></span>
                                        </td>
                                        <td>
                                            <?php echo formatDate($return['date_borrowed']); ?>
                                        </td>
                                        <td>
                                            <?php echo formatDate($return['date_returned']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $conditionClass = '';
                                            switch ($return['return_condition']) {
                                                case 'Good':
                                                    $conditionClass = 'bg-success';
                                                    break;
                                                                        case 'Fair':
                            $conditionClass = 'bg-dark';
                            break;
                                                case 'Poor':
                                                    $conditionClass = 'bg-danger';
                                                    break;
                                                case 'Damaged':
                                                    $conditionClass = 'bg-dark';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $conditionClass; ?>">
                                                <?php echo htmlspecialchars($return['return_condition']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $borrowDate = new DateTime($return['date_borrowed']);
                                            $returnDate = new DateTime($return['date_returned']);
                                            $duration = $borrowDate->diff($returnDate);
                                            
                                            if ($duration->days == 0) {
                                                if ($duration->h == 0) {
                                                    echo $duration->i . ' minutes';
                                                } else {
                                                    echo $duration->h . 'h ' . $duration->i . 'm';
                                                }
                                            } else {
                                                echo $duration->days . ' days';
                                                if ($duration->h > 0) {
                                                    echo ' ' . $duration->h . 'h';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="viewDetails(<?php echo $return['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
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
                    <nav aria-label="Return records pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagination['hasPrev']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['currentPage'] - 1; ?>&search=<?php echo urlencode($search); ?>&condition=<?php echo urlencode($condition); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                                <li class="page-item <?php echo $i == $pagination['currentPage'] ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&condition=<?php echo urlencode($condition); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagination['hasNext']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['currentPage'] + 1; ?>&search=<?php echo urlencode($search); ?>&condition=<?php echo urlencode($condition); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Return Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(borrowingId) {
    // Load borrowing details via AJAX
    fetch(`get_borrowing_details.php?id=${borrowingId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        });
}
</script>

<?php include 'includes/footer.php'; ?> 