<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Redirect admins to admin borrowings page
if (isAdmin()) {
    header("Location: admin_borrowings.php");
    exit();
}

$pageTitle = 'Borrow Item';

$database = new Database();
$pdo = $database->getConnection();

// Handle borrow operation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $itemId = (int)$_POST['item_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 0;
    $purpose = sanitizeInput($_POST['purpose']);
    
    if (!empty($purpose)) {
        // Check if item exists and has available quantity/weight
        $stmt = $pdo->prepare("SELECT id, name, quantity, weight, grade FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $available = false;
            if ($item['grade'] === 'New' && $item['quantity'] >= $quantity && $quantity > 0) {
                $available = true;
            } elseif ($item['grade'] === 'Used') {
                // For used items, we don't check weight since user specifies quantity in purpose
                $available = true;
                // Set default values for used items
                $quantity = 1;
                $weight = 0;
            }
            
            if ($available) {
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Create borrowing record
                    $sql = "INSERT INTO borrowings (item_id, user_id, purpose, quantity_borrowed, weight_borrowed) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$itemId, $_SESSION['user_id'], $purpose, $quantity, $weight]);
                    
                    // Reduce item quantity or weight
                    if ($item['grade'] === 'New') {
                        $sql = "UPDATE items SET quantity = quantity - ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$quantity, $itemId]);
                    } else {
                        // For used items, we don't reduce weight since quantity is specified in purpose
                        // The weight remains the same as it's a bulk item
                    }
                    
                    // Log activity
                    $details = $item['grade'] === 'New' ? "Purpose: $purpose, Quantity: $quantity" : "Purpose: $purpose, Quantity specified in purpose";
                    logActivity($pdo, $_SESSION['user_id'], $itemId, 'Borrowed item', $details);
                    
                    $pdo->commit();
                    setFlashMessage('success', 'Item borrowed successfully!');
                    header("Location: borrow.php");
                    exit();
                } catch (Exception $e) {
                    $pdo->rollback();
                    setFlashMessage('danger', 'Error borrowing item. Please try again.');
                }
            } else {
                setFlashMessage('danger', 'Item not available or insufficient quantity/weight.');
            }
        } else {
            setFlashMessage('danger', 'Item not found.');
        }
    } else {
        setFlashMessage('danger', 'Please provide a purpose for borrowing.');
    }
}

// Get available items (quantity > 0 for New items, weight > 0 for Used items)
$stmt = $pdo->query("SELECT * FROM items WHERE (grade = 'New' AND quantity > 0) OR (grade = 'Used' AND weight > 0) ORDER BY name ASC");
$availableItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's current borrowings
$stmt = $pdo->prepare("
    SELECT b.*, i.name as item_name, i.category 
    FROM borrowings b 
    JOIN items i ON b.item_id = i.id 
    WHERE b.user_id = ? AND b.date_returned IS NULL 
    ORDER BY b.date_borrowed DESC
");
$stmt->execute([$_SESSION['user_id']]);
$currentBorrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <!-- Borrow Form -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-hand-holding me-2"></i>Borrow Item
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($availableItems)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No items available for borrowing at the moment.
                    </div>
                <?php else: ?>
                    <form method="POST" onsubmit="return validateForm('borrowForm')">
                        <div class="mb-3">
                            <label for="item_id" class="form-label">Select Item *</label>
                            <select class="form-select" id="item_id" name="item_id" required onchange="updateBorrowOptions()">
                                <option value="">Choose an item...</option>
                                <?php foreach ($availableItems as $item): ?>
                                    <?php 
                                    $available = $item['grade'] === 'New' ? $item['quantity'] : number_format($item['weight'], 2) . ' kg';
                                    $gradeLabel = $item['grade'] === 'New' ? 'New' : 'Used';
                                    ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            data-grade="<?php echo $item['grade']; ?>"
                                            data-quantity="<?php echo $item['quantity']; ?>"
                                            data-weight="<?php echo $item['weight']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?> 
                                        (<?php echo htmlspecialchars($item['category']); ?>) - 
                                        <?php echo $gradeLabel; ?> - Available: <?php echo $available; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="quantityField">
                            <label for="quantity" class="form-label">Quantity to Borrow *</label>
                            <select class="form-select" id="quantity" name="quantity">
                                <option value="">Select quantity...</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="weightField" style="display: none;">
                            <label for="weight" class="form-label">Weight to Borrow (kg) *</label>
                            <input type="number" class="form-control" id="weight" name="weight" min="0" step="0.01" placeholder="Enter weight in kg">
                        </div>
                        
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Purpose of Borrowing *</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required 
                                      placeholder="Please describe why you need to borrow this item..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-hand-holding me-2"></i>Borrow Item
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Current Borrowings -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Your Current Borrowings
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($currentBorrowings)): ?>
                    <p class="text-muted">You have no current borrowings.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($currentBorrowings as $borrowing): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($borrowing['item_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php 
                                        $borrowDate = new DateTime($borrowing['date_borrowed']);
                                        $now = new DateTime();
                                        $diff = $now->diff($borrowDate);
                                        echo $diff->days . ' days ago';
                                        ?>
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <small class="text-muted">Purpose: <?php echo htmlspecialchars($borrowing['purpose']); ?></small>
                                </p>
                                <small class="text-muted">Category: <?php echo htmlspecialchars($borrowing['category']); ?></small>
                                
                                <?php if (isOverdue($borrowing['date_borrowed'])): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-danger">Overdue</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Available Items List -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2"></i>Available Items
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($availableItems)): ?>
                    <p class="text-muted">No items available for borrowing.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Condition</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($availableItems as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($item['category']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $quantityClass = $item['quantity'] <= 2 ? 'bg-danger' : 'bg-dark';
                                        ?>
                                        <span class="badge <?php echo $quantityClass; ?>"><?php echo $item['quantity']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['item_condition']); ?></td>
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
</div>

<script>
function updateBorrowOptions() {
    const itemSelect = document.getElementById('item_id');
    const quantityField = document.getElementById('quantityField');
    const weightField = document.getElementById('weightField');
    const quantitySelect = document.getElementById('quantity');
    const weightInput = document.getElementById('weight');
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    
    // Hide both fields initially
    quantityField.style.display = 'none';
    weightField.style.display = 'none';
    
    if (selectedOption.value) {
        const grade = selectedOption.getAttribute('data-grade');
        
        if (grade === 'New') {
            // Show quantity field for New items
            quantityField.style.display = 'block';
            weightField.style.display = 'none';
            
            // Clear and populate quantity options
            quantitySelect.innerHTML = '<option value="">Select quantity...</option>';
            const maxQuantity = parseInt(selectedOption.getAttribute('data-quantity'));
            
            for (let i = 1; i <= maxQuantity; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                quantitySelect.appendChild(option);
            }
            
            // Clear weight input
            weightInput.value = '';
            weightInput.required = false;
            quantitySelect.required = true;
            
            // Update purpose placeholder for new items
            const purposeTextarea = document.getElementById('purpose');
            purposeTextarea.placeholder = 'Please describe why you need to borrow this item...';
        } else if (grade === 'Used') {
            // Hide both quantity and weight fields for Used items
            quantityField.style.display = 'none';
            weightField.style.display = 'none';
            
            // Clear quantity select
            quantitySelect.innerHTML = '<option value="">Select quantity...</option>';
            quantitySelect.required = false;
            
            // Clear weight input
            weightInput.value = '';
            weightInput.required = false;
            
            // Update purpose placeholder for used items
            const purposeTextarea = document.getElementById('purpose');
            purposeTextarea.placeholder = 'Please describe why you need to borrow this item and include the quantity (e.g., "2pcs")...';
        }
    }
}

// Initialize options on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBorrowOptions();
});
</script>

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
        const itemSelect = document.getElementById('item_id');
        if (itemSelect) {
            for (let i = 0; i < itemSelect.options.length; i++) {
                if (itemSelect.options[i].value == itemId) {
                    itemSelect.selectedIndex = i;
                    updateBorrowOptions();
                    break;
                }
            }
        }
        document.getElementById('qr-reader-results').innerHTML = '<span class="text-success">Item selected: ' + itemId + '</span>';
        
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