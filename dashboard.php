<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = 'Dashboard';

$database = new Database();
$pdo = $database->getConnection();

// Get statistics
$stats = [];

// Total items
$stmt = $pdo->query("SELECT COUNT(*) as total FROM items");
$stats['total_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Items with low quantity (less than 5) for New items or low weight (less than 10 kg) for Used items
$stmt = $pdo->query("SELECT COUNT(*) as total FROM items WHERE (grade = 'New' AND quantity < 5) OR (grade = 'Used' AND weight < 10)");
$stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Currently borrowed items
$stmt = $pdo->query("SELECT COUNT(*) as total FROM borrowings WHERE date_returned IS NULL");
$stats['borrowed_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Overdue items (borrowed more than 12 hours ago)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM borrowings WHERE date_returned IS NULL AND date_borrowed < DATE_SUB(NOW(), INTERVAL 12 HOUR)");
$stats['overdue_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent activities (last 10 logs)
$stmt = $pdo->query("
    SELECT l.*, u.name as user_name, i.name as item_name 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN items i ON l.item_id = i.id 
    ORDER BY l.timestamp DESC 
    LIMIT 10
");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock items
$stmt = $pdo->query("SELECT * FROM items WHERE (grade = 'New' AND quantity < 5) OR (grade = 'Used' AND weight < 10) ORDER BY CASE WHEN grade = 'New' THEN quantity ELSE weight END ASC LIMIT 5");
$low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overdue items
$stmt = $pdo->query("
    SELECT b.*, i.name as item_name, u.name as user_name 
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    JOIN users u ON b.user_id = u.id 
    WHERE b.date_returned IS NULL AND b.date_borrowed < DATE_SUB(NOW(), INTERVAL 12 HOUR)
    ORDER BY b.date_borrowed ASC
");
$overdue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get item counts by category for pie chart
$categoryData = [];
$stmt = $pdo->query("SELECT category, COUNT(*) as count FROM items GROUP BY category");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categoryData[$row['category']] = $row['count'];
}

include 'includes/header.php';
?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Items</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_items']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Currently Borrowed</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['borrowed_items']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Low Stock Items</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['low_stock']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Low Stock Items -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Items
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_items)): ?>
                    <p class="text-muted">No items with low stock.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Grade</th>
                                    <th>Quantity/Weight</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td>
                                        <?php if ($item['grade'] === 'New'): ?>
                                            <span class="badge bg-primary">New</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark">Used</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['grade'] === 'New'): ?>
                                            <?php 
                                            $quantityClass = $item['quantity'] <= 2 ? 'bg-danger' : 'bg-dark';
                                            ?>
                                            <span class="badge <?php echo $quantityClass; ?>"><?php echo $item['quantity']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-dark"><?php echo number_format($item['weight'], 2); ?> kg</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['location']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Overdue Items -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-danger">
                    <i class="fas fa-clock me-2"></i>Overdue Items (<?php echo $stats['overdue_items']; ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($overdue_items)): ?>
                    <p class="text-muted">No overdue items.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Borrower</th>
                                    <th>Borrowed Date</th>
                                    <th>Hours Overdue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdue_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['user_name']); ?></td>
                                    <td><?php echo formatDate($item['date_borrowed']); ?></td>
                                    <td>
                                        <?php 
                                        $borrowDate = new DateTime($item['date_borrowed']);
                                        $now = new DateTime();
                                        $diff = $now->diff($borrowDate);
                                        $totalHours = ($diff->days * 24) + $diff->h;
                                        echo '<span class="badge bg-danger">' . $totalHours . ' hours</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<?php
$borrow_return_activities = [];
foreach ($recent_activities as $activity) {
    $action = strtolower($activity['action']);
    if ((strpos($action, 'borrow') !== false || strpos($action, 'return') !== false) && !empty($activity['item_name'])) {
        $borrow_return_activities[] = $activity;
    }
}
?>
<div class="row">
    <!-- Borrow/Return Activities -->
    <div class="col-12 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-exchange-alt me-2"></i>Borrow/Return Activities
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($borrow_return_activities)): ?>
                    <p class="text-muted">No borrow/return activities.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Item</th>
                                    <th>Details</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrow_return_activities as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['item_name']); ?></td>
                                    <td><?php echo $activity['details'] ? htmlspecialchars($activity['details']) : '-'; ?></td>
                                    <td><?php echo formatDate($activity['timestamp']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?> 