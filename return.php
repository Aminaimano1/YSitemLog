<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Redirect admins to admin returns page
if (isAdmin()) {
    header("Location: admin_returns.php");
    exit();
}

$pageTitle = 'Return Item';

$database = new Database();
$pdo = $database->getConnection();

// Handle return operation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $borrowingId = (int)$_POST['borrowing_id'];
    $returnCondition = sanitizeInput($_POST['return_condition']);
    
    if (!empty($returnCondition)) {
        // Check if borrowing exists and is not already returned
        $stmt = $pdo->prepare("
            SELECT b.*, i.name as item_name, i.id as item_id 
            FROM borrowings b 
            JOIN items i ON b.item_id = i.id 
            WHERE b.id = ? AND b.date_returned IS NULL
        ");
        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($borrowing) {
            // Get item details to determine grade
            $stmt = $pdo->prepare("SELECT grade FROM items WHERE id = ?");
            $stmt->execute([$borrowing['item_id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Update borrowing record
                    $sql = "UPDATE borrowings SET date_returned = NOW(), return_condition = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$returnCondition, $borrowingId]);
                    
                    // Increase item quantity or weight based on grade
                    if ($item['grade'] === 'New') {
                        $sql = "UPDATE items SET quantity = quantity + ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$borrowing['quantity_borrowed'], $borrowing['item_id']]);
                    } else {
                        $sql = "UPDATE items SET weight = weight + ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$borrowing['weight_borrowed'], $borrowing['item_id']]);
                    }
                    
                    // Log activity
                    $details = $item['grade'] === 'New' ? "Condition: $returnCondition, Quantity: " . $borrowing['quantity_borrowed'] : "Condition: $returnCondition, Weight: " . $borrowing['weight_borrowed'] . " kg";
                    logActivity($pdo, $_SESSION['user_id'], $borrowing['item_id'], 'Returned item', $details);
                    
                    $pdo->commit();
                    setFlashMessage('success', 'Item returned successfully!');
                    header("Location: return.php");
                    exit();
                } catch (Exception $e) {
                    $pdo->rollback();
                    setFlashMessage('danger', 'Error returning item. Please try again.');
                }
            } else {
                setFlashMessage('danger', 'Item not found.');
            }
        } else {
            setFlashMessage('danger', 'Invalid borrowing record or item already returned.');
        }
    } else {
        setFlashMessage('danger', 'Please select the return condition.');
    }
}

// Remove location filter logic
// Get user's current borrowings
$stmt = $pdo->prepare("
    SELECT b.*, i.name as item_name, i.category, i.location, i.grade 
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    WHERE b.user_id = ? AND b.date_returned IS NULL 
    ORDER BY b.date_borrowed ASC
");
$stmt->execute([$_SESSION['user_id']]);
$currentBorrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all current borrowings (for admin)
$allBorrowings = [];
if (isAdmin()) {
    $stmt = $pdo->query("
        SELECT b.*, i.name as item_name, i.category, i.location, i.grade, u.name as user_name 
        FROM borrowings b 
        JOIN items i ON b.item_id = i.id 
        JOIN users u ON b.user_id = u.id 
        WHERE b.date_returned IS NULL 
        ORDER BY b.date_borrowed ASC
    ");
    $allBorrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="row">
    <!-- QR Code Scanner -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Scan Item QR Code</h5>
            </div>
            <div class="card-body">
                <div id="qr-reader" style="width: 300px;"></div>
                <div id="qr-reader-results"></div>
            </div>
        </div>
    </div>
    <!-- Return Form -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-undo me-2"></i>Return Item
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($currentBorrowings)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You have no items to return.
                    </div>
                <?php else: ?>
                    <form method="POST" onsubmit="return validateForm('returnForm')">
                        <div class="mb-3">
                            <label for="borrowing_id" class="form-label">Select Item to Return *</label>
                            <select class="form-select" id="borrowing_id" name="borrowing_id" required>
                                <option value="">Choose an item to return...</option>
                                <?php foreach ($currentBorrowings as $borrowing): ?>
                                    <option value="<?php echo $borrowing['id']; ?>">
                                        <?php echo htmlspecialchars($borrowing['item_name']); ?> 
                                        (Borrowed: <?php echo formatDate($borrowing['date_borrowed']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
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
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-undo me-2"></i>Return Item
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Return History -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Your Return History
                </h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $pdo->prepare("
                    SELECT b.*, i.name as item_name 
                    FROM borrowings b 
                    JOIN items i ON b.item_id = i.id 
                    WHERE b.user_id = ? AND b.date_returned IS NOT NULL 
                    ORDER BY b.date_returned DESC 
                    LIMIT 5
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $returnHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($returnHistory)): ?>
                    <p class="text-muted">No return history.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($returnHistory as $return): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($return['item_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo formatDate($return['date_returned']); ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <small class="text-muted">Condition: <?php echo htmlspecialchars($return['return_condition']); ?></small>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Current Borrowings Table -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Current Borrowings
                    <?php if (isAdmin()): ?>
                        <span class="badge bg-primary ms-2">All Users</span>
                    <?php else: ?>
                        <span class="badge bg-info ms-2">Your Items</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php 
                $displayBorrowings = isAdmin() ? $allBorrowings : $currentBorrowings;
                ?>
                
                <?php if (empty($displayBorrowings)): ?>
                    <p class="text-muted">No current borrowings.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <?php if (isAdmin()): ?>
                                        <th>Borrower</th>
                                    <?php endif; ?>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Purpose</th>
                                    <th>Borrowed Date</th>
                                    <th>Days Borrowed</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($displayBorrowings as $borrowing): ?>
                                <tr>
                                    <?php if (isAdmin()): ?>
                                        <td><?php echo htmlspecialchars($borrowing['user_name']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($borrowing['item_name']); ?></td>
                                    <td>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($borrowing['category']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($borrowing['purpose']); ?></td>
                                    <td><?php echo formatDate($borrowing['date_borrowed']); ?></td>
                                    <td>
                                        <?php 
                                        $borrowDate = new DateTime($borrowing['date_borrowed']);
                                        $now = new DateTime();
                                        $diff = $now->diff($borrowDate);
                                        echo $diff->days . ' days';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (isOverdue($borrowing['date_borrowed'])): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-dark">Borrowed</span>
                                        <?php endif; ?>
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

<?php include 'includes/footer.php'; ?> 
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
// Simple beep sound generator for QR code success
function createBeepSound() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 800; // 800 Hz beep
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
}

function onScanSuccess(decodedText, decodedResult) {
    // Try to extract ItemID from QR code data
    const match = decodedText.match(/ItemID: (\d+)/);
    if (match) {
        const itemId = match[1];
        const borrowingSelect = document.getElementById('borrowing_id');
        if (borrowingSelect) {
            for (let i = 0; i < borrowingSelect.options.length; i++) {
                // Option text contains item name, but we need to match by itemId
                // We'll use a data attribute for item_id if possible, otherwise fallback to text search
                const option = borrowingSelect.options[i];
                if (option.value) {
                    // Find the borrowing in currentBorrowings PHP array
                    <?php echo "var borrowings = ".json_encode($currentBorrowings).";"; ?>
                    for (let j = 0; j < borrowings.length; j++) {
                        if (borrowings[j].item_id == itemId && borrowings[j].id == option.value) {
                            borrowingSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
        }
        document.getElementById('qr-reader-results').innerHTML = '<span class="text-success">Item selected for return: ' + itemId + '</span>';
        
        // Play success beep sound
        createBeepSound();
    } else {
        document.getElementById('qr-reader-results').innerHTML = '<span class="text-danger">Invalid QR code</span>';
    }
}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "qr-reader", { fps: 10, qrbox: 200 }
);
html5QrcodeScanner.render(onScanSuccess);
</script> 