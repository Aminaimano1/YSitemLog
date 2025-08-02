<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "Missing borrowing ID";
    exit();
}

$borrowingId = (int)$_GET['id'];
$database = new Database();
$pdo = $database->getConnection();

// Get borrowing details
$stmt = $pdo->prepare("
    SELECT b.*, i.name as item_name, i.category, i.location, i.item_condition,
           u.name as user_name, u.username as user_username
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    JOIN users u ON b.user_id = u.id 
    WHERE b.id = ?
");
$stmt->execute([$borrowingId]);
$borrowing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$borrowing) {
    http_response_code(404);
    echo "Borrowing record not found";
    exit();
}

// Calculate duration
$borrowDate = new DateTime($borrowing['date_borrowed']);
$duration = '';
$isOverdue = false;

if ($borrowing['date_returned']) {
    $returnDate = new DateTime($borrowing['date_returned']);
    $diff = $borrowDate->diff($returnDate);
    
    // Format duration based on the difference
    if ($diff->days > 0) {
        $duration = $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        if ($diff->h > 0) {
            $duration .= ', ' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            $duration .= ', ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    } elseif ($diff->h > 0) {
        $duration = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        if ($diff->i > 0) {
            $duration .= ', ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    } else {
        $duration = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
    }
} else {
    $now = new DateTime();
    $diff = $borrowDate->diff($now);
    
    // Format duration based on the difference
    if ($diff->days > 0) {
        $duration = $diff->days . ' day' . ($diff->days > 1 ? 's' : '');
        if ($diff->h > 0) {
            $duration .= ', ' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            $duration .= ', ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    } elseif ($diff->h > 0) {
        $duration = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        if ($diff->i > 0) {
            $duration .= ', ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    } else {
        $duration = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
    }
    
    $isOverdue = isOverdue($borrowing['date_borrowed']);
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary">Item Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Name:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['item_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Category:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['category']); ?></td>
            </tr>
            <tr>
                <td><strong>Location:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['location']); ?></td>
            </tr>
            <tr>
                <td><strong>Condition:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['item_condition']); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-primary">Borrowing Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Borrowed By:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['user_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Username:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['user_username']); ?></td>
            </tr>
            <tr>
                <td><strong>Quantity:</strong></td>
                <td>
                    <?php 
                    $quantityClass = $borrowing['quantity_borrowed'] <= 2 ? 'bg-danger' : 'bg-dark';
                    ?>
                    <span class="badge <?php echo $quantityClass; ?>"><?php echo $borrowing['quantity_borrowed']; ?></span>
                </td>
            </tr>
            <tr>
                <td><strong>Purpose:</strong></td>
                <td><?php echo htmlspecialchars($borrowing['purpose']); ?></td>
            </tr>
            <tr>
                <td><strong>Date Borrowed:</strong></td>
                <td><?php echo formatDate($borrowing['date_borrowed']); ?></td>
            </tr>
            <?php if ($borrowing['date_returned']): ?>
            <tr>
                <td><strong>Date Returned:</strong></td>
                <td><?php echo formatDate($borrowing['date_returned']); ?></td>
            </tr>
            <tr>
                <td><strong>Return Condition:</strong></td>
                <td>
                    <?php
                    $conditionClass = '';
                    switch ($borrowing['return_condition']) {
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
                        <?php echo htmlspecialchars($borrowing['return_condition']); ?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Duration:</strong></td>
                <td>
                    <?php echo $duration; ?>
                    <?php if ($isOverdue): ?>
                        <br><span class="badge bg-danger">Overdue</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php if (!$borrowing['date_returned']): ?>
<div class="row mt-3">
    <div class="col-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            This item is currently borrowed and has not been returned yet.
        </div>
    </div>
</div>
<?php endif; ?> 