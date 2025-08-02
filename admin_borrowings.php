<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

$pageTitle = 'All Borrowings';

$database = new Database();
$pdo = $database->getConnection();

// Pagination
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Search and filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(i.name LIKE ? OR u.name LIKE ? OR b.purpose LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    if ($status === 'active') {
        $whereConditions[] = "b.date_returned IS NULL";
    } elseif ($status === 'returned') {
        $whereConditions[] = "b.date_returned IS NOT NULL";
    }
}

if (!empty($category)) {
    $whereConditions[] = "i.category = ?";
    $params[] = $category;
}
if (!empty($location)) {
    $whereConditions[] = "i.location = ?";
    $params[] = $location;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

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

// Get borrowings with pagination
$sql = "
    SELECT b.*, i.name as item_name, i.category, i.location, u.name as user_name, u.username as user_username
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    JOIN users u ON b.user_id = u.id 
    $whereClause
    ORDER BY b.date_borrowed DESC 
    LIMIT $recordsPerPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Get locations for filter
$stmt = $pdo->query("SELECT DISTINCT location FROM items ORDER BY location");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search items, users, or purpose..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="returned" <?php echo $status === 'returned' ? 'selected' : ''; ?>>Returned</option>
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
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="admin_borrowings.php" class="btn btn-outline-secondary w-100">
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
                    <i class="fas fa-list me-2"></i>All Borrowing Records
                </h5>
                <div>
                    <span class="badge bg-primary"><?php echo $totalRecords; ?> records</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($borrowings)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No borrowing records found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Borrowed By</th>
                                    <th>Purpose</th>
                                    <th>Quantity</th>
                                    <th>Date Borrowed</th>
                                    <th>Date Returned</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowings as $borrowing): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($borrowing['item_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($borrowing['category']); ?> • 
                                                    <?php echo htmlspecialchars($borrowing['location']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($borrowing['user_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($borrowing['user_username']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                                  title="<?php echo htmlspecialchars($borrowing['purpose']); ?>">
                                                <?php echo htmlspecialchars($borrowing['purpose']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $quantityClass = $borrowing['quantity_borrowed'] <= 2 ? 'bg-danger' : 'bg-dark';
                                            ?>
                                            <span class="badge <?php echo $quantityClass; ?>"><?php echo $borrowing['quantity_borrowed']; ?></span>
                                        </td>
                                        <td>
                                            <?php echo formatDate($borrowing['date_borrowed']); ?>
                                            <?php if (isOverdue($borrowing['date_borrowed']) && $borrowing['date_returned'] === null): ?>
                                                <br><span class="badge bg-danger">Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($borrowing['date_returned']): ?>
                                                <?php echo formatDate($borrowing['date_returned']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    Condition: <?php echo htmlspecialchars($borrowing['return_condition']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($borrowing['date_returned']): ?>
                                                <span class="badge bg-success">Returned</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="viewDetails(<?php echo $borrowing['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (!$borrowing['date_returned']): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="markAsReturned(<?php echo $borrowing['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
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
                    <nav aria-label="Borrowing records pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagination['hasPrev']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['currentPage'] - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                                <li class="page-item <?php echo $i == $pagination['currentPage'] ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagination['hasNext']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['currentPage'] + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>">Next</a>
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
                <h5 class="modal-title">Borrowing Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as Returned</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="returnForm" method="POST" action="admin_return_item.php">
                <div class="modal-body">
                    <input type="hidden" id="return_borrowing_id" name="borrowing_id">
                    <div class="mb-3">
                        <label for="return_condition" class="form-label">Return Condition *</label>
                        <select class="form-select" id="return_condition" name="return_condition" required>
                            <option value="">Select condition...</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Damaged">Damaged</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Mark as Returned
                    </button>
                </div>
            </form>
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

function markAsReturned(borrowingId) {
    document.getElementById('return_borrowing_id').value = borrowingId;
    new bootstrap.Modal(document.getElementById('returnModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?> 