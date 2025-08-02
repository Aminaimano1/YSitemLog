<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = 'Items Management';

$database = new Database();
$pdo = $database->getConnection();

// Handle bulk actions (move this to the top before other POST handlers)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_items'])) {
    $bulkAction = $_POST['bulk_action'];
    $selectedItems = array_map('intval', $_POST['selected_items']);
    
    // Check if user has permission to perform bulk actions
    if (!canPerformBulkActions()) {
        setFlashMessage('danger', 'You do not have permission to perform bulk actions.');
        header('Location: items.php');
        exit();
    }
    
    if ($bulkAction === 'delete' && isAdmin()) {
        $in = str_repeat('?,', count($selectedItems) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM items WHERE id IN ($in)");
        $stmt->execute($selectedItems);
        setFlashMessage('success', 'Selected items deleted successfully.');
        header('Location: items.php');
        exit();
    } elseif ($bulkAction === 'export') {
        $in = str_repeat('?,', count($selectedItems) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id IN ($in) ORDER BY name ASC");
        $stmt->execute($selectedItems);
        $exportItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="items_export_' . date('Y-m-d_H-i-s') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($exportItems[0] ?? ['id' => 'ID']));
        foreach ($exportItems as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
}

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads/items/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (isAdmin()) {
                    $name = sanitizeInput($_POST['name']);
                    $category = sanitizeInput($_POST['category']);
                    $grade = sanitizeInput($_POST['grade']);
                    $condition = sanitizeInput($_POST['item_condition']);
                    $location = sanitizeInput($_POST['location']);
                    
                    // Validate based on grade
                    $quantity = null;
                    $weight = null;
                    
                    if ($grade === 'New') {
                        $quantity = (int)$_POST['quantity'];
                        if ($quantity < 0) {
                            setFlashMessage('danger', 'Quantity must be 0 or greater.');
                            break;
                        }
                    } elseif ($grade === 'Used') {
                        $weight = (float)$_POST['weight'];
                        if ($weight <= 0) {
                            setFlashMessage('danger', 'Weight must be greater than 0.');
                            break;
                        }
                    }
                    
                    if (!empty($name) && !empty($category) && !empty($grade)) {
                        $imagePath = null;
                        
                        // Handle image upload
                        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            $maxSize = 5 * 1024 * 1024; // 5MB
                            
                            if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxSize) {
                                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                                $targetPath = $uploadsDir . $fileName;
                                
                                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                                    $imagePath = $targetPath;
                                }
                            }
                        }
                        
                        $sql = "INSERT INTO items (name, category, grade, quantity, weight, item_condition, location, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute([$name, $category, $grade, $quantity, $weight, $condition, $location, $imagePath])) {
                            $itemId = $pdo->lastInsertId();
                            $details = $grade === 'New' ? "Item: $name, Quantity: $quantity" : "Item: $name, Weight: $weight kg";
                            logActivity($pdo, $_SESSION['user_id'], $itemId, 'Added new item', $details);
                            setFlashMessage('success', 'Item added successfully!');
                        } else {
                            setFlashMessage('danger', 'Error adding item.');
                        }
                    } else {
                        setFlashMessage('danger', 'Please fill in all required fields.');
                    }
                }
                break;
                
            case 'edit':
                if (isAdmin()) {
                    $id = (int)$_POST['id'];
                    $name = sanitizeInput($_POST['name']);
                    $category = sanitizeInput($_POST['category']);
                    $grade = sanitizeInput($_POST['grade']);
                    $condition = sanitizeInput($_POST['item_condition']);
                    $location = sanitizeInput($_POST['location']);
                    
                    // Validate based on grade
                    $quantity = null;
                    $weight = null;
                    
                    if ($grade === 'New') {
                        $quantity = (int)$_POST['quantity'];
                        if ($quantity < 0) {
                            setFlashMessage('danger', 'Quantity must be 0 or greater.');
                            break;
                        }
                    } elseif ($grade === 'Used') {
                        $weight = (float)$_POST['weight'];
                        if ($weight <= 0) {
                            setFlashMessage('danger', 'Weight must be greater than 0.');
                            break;
                        }
                    }
                    
                    if (!empty($name) && !empty($category) && !empty($grade)) {
                        $imagePath = null;
                        
                        // Handle image upload
                        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            $maxSize = 5 * 1024 * 1024; // 5Megabites
                            
                            if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxSize) {
                                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                                $targetPath = $uploadsDir . $fileName;
                                
                                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                                    $imagePath = $targetPath;
                                    
                                    // Delete old image if exists
                                    $stmt = $pdo->prepare("SELECT image_path FROM items WHERE id = ?");
                                    $stmt->execute([$id]);
                                    $oldImage = $stmt->fetchColumn();
                                    if ($oldImage && file_exists($oldImage)) {
                                        unlink($oldImage);
                                    }
                                }
                            }
                        }
                        
                        if ($imagePath) {
                            $sql = "UPDATE items SET name = ?, category = ?, grade = ?, quantity = ?, weight = ?, item_condition = ?, location = ?, image_path = ? WHERE id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$name, $category, $grade, $quantity, $weight, $condition, $location, $imagePath, $id]);
                        } else {
                            $sql = "UPDATE items SET name = ?, category = ?, grade = ?, quantity = ?, weight = ?, item_condition = ?, location = ? WHERE id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$name, $category, $grade, $quantity, $weight, $condition, $location, $id]);
                        }
                        
                        if ($stmt->rowCount() > 0) {
                            $details = $grade === 'New' ? "Item: $name, Quantity: $quantity" : "Item: $name, Weight: $weight kg";
                            logActivity($pdo, $_SESSION['user_id'], $id, 'Updated item', $details);
                            setFlashMessage('success', 'Item updated successfully!');
                        } else {
                            setFlashMessage('danger', 'Error updating item.');
                        }
                    } else {
                        setFlashMessage('danger', 'Please fill in all required fields.');
                    }
                }
                break;
                
            case 'delete':
                if (isAdmin()) {
                    $id = (int)$_POST['id'];
                    
                    // Check if item is currently borrowed
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE item_id = ? AND date_returned IS NULL");
                    $stmt->execute([$id]);
                    $borrowedCount = $stmt->fetchColumn();
                    
                    if ($borrowedCount > 0) {
                        setFlashMessage('danger', 'Cannot delete item that is currently borrowed.');
                    } else {
                        // Delete image file if exists
                        $stmt = $pdo->prepare("SELECT image_path FROM items WHERE id = ?");
                        $stmt->execute([$id]);
                        $imagePath = $stmt->fetchColumn();
                        if ($imagePath && file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                        
                        $stmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
                        $stmt->execute([$id]);
                        $itemName = $stmt->fetchColumn();
                        
                        $sql = "DELETE FROM items WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute([$id])) {
                            logActivity($pdo, $_SESSION['user_id'], null, 'Deleted item', "Item: $itemName");
                            setFlashMessage('success', 'Item deleted successfully!');
                        } else {
                            setFlashMessage('danger', 'Error deleting item.');
                        }
                    }
                }
                break;
        }
    }
    header("Location: items.php");
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$grade = isset($_GET['grade']) ? sanitizeInput($_GET['grade']) : '';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(name LIKE ? OR category LIKE ? OR location LIKE ? OR grade LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}
if (!empty($location)) {
    $whereConditions[] = "location = ?";
    $params[] = $location;
}
if (!empty($grade)) {
    $whereConditions[] = "grade = ?";
    $params[] = $grade;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total records for pagination
$countSql = "SELECT COUNT(*) FROM items $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();

$pagination = getPagination($totalRecords, $recordsPerPage, $page);

// Get items
$sql = "SELECT * FROM items $whereClause ORDER BY name ASC LIMIT " . (int)$recordsPerPage . " OFFSET " . (int)$pagination['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Get locations for filter
$stmt = $pdo->query("SELECT DISTINCT location FROM items ORDER BY location");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search items...">
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="location" class="form-label">Location</label>
                <select class="form-select" id="location" name="location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="grade" class="form-label">Grade</label>
                <select class="form-select" id="grade" name="grade">
                    <option value="">All Grades</option>
                    <option value="New" <?php echo $grade === 'New' ? 'selected' : ''; ?>>New</option>
                    <option value="Used" <?php echo $grade === 'Used' ? 'selected' : ''; ?>>Used</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <a href="items.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Item Button (Admin Only) -->
<?php if (isAdmin()): ?>
<div class="mb-3">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addItemModal">
        <i class="fas fa-plus me-2"></i>Add New Item
    </button>
</div>
<?php endif; ?>

<!-- Bulk Actions and Items Table Form (start) -->
<form method="POST" id="bulkActionsForm">
    <?php if (canPerformBulkActions()): ?>
    <div class="mb-3 d-flex align-items-center gap-2 bg-white py-2" style="border-bottom: 1px solid #eee;">
        <input type="hidden" name="bulk_action" id="bulk_action" value="">
        <button type="button" class="btn btn-danger btn-sm" onclick="submitBulkAction('delete')" disabled id="bulkDeleteBtn"><i class="fas fa-trash me-1"></i>Delete Selected</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="submitBulkAction('export')" disabled id="bulkExportBtn"><i class="fas fa-file-csv me-1"></i>Export Selected</button>
        <span id="bulkSelectedCount" class="ms-2 text-muted small"></span>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <?php if (canPerformBulkActions()): ?>
                    <th><input type="checkbox" id="selectAllItems" onclick="toggleSelectAllItems(this)"></th>
                    <?php endif; ?>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Grade</th>
                    <th>Quantity/Weight</th>
                    <th>Condition</th>
                    <th>Location</th>
                    <th>QR Code</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <?php if (canPerformBulkActions()): ?>
                    <td><input type="checkbox" class="item-checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" onchange="updateBulkButtons()"></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="img-thumbnail item-image" 
                                 style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;"
                                 onclick="viewImage('<?php echo htmlspecialchars($item['image_path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px; border-radius: 0.375rem;">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </td>
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
                    <td><?php echo htmlspecialchars($item['item_condition']); ?></td>
                    <td><?php echo htmlspecialchars($item['location']); ?></td>
                    <td>
                        <?php 
                            $qrUrl = generateQRCode($item['id'], $item['name']);
                        ?>
                        <img src="<?php echo $qrUrl; ?>" alt="QR Code" style="width: 50px; height: 50px; cursor:pointer; display:block; margin:auto;" onclick="viewQR('<?php echo $qrUrl; ?>', '<?php echo htmlspecialchars($item['name']); ?>')" />
                        <div class="text-center mt-1">
                            <a href="<?php echo $qrUrl; ?>" download="item_<?php echo $item['id']; ?>_qrcode.png" class="btn btn-link btn-sm p-0">Download</a>
                        </div>
                    </td>
                    <td>
                        <?php if (isAdmin()): ?>
                            <button type="button" class="btn btn-sm btn-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>
<!-- Bulk Actions and Items Table Form (end) -->

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
<nav aria-label="Items pagination">
    <ul class="pagination justify-content-center">
        <?php if ($pagination['hasPrev']): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $pagination['currentPage'] - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>">Previous</a>
            </li>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
            <li class="page-item <?php echo $i == $pagination['currentPage'] ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&grade=<?php echo urlencode($grade); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        
        <?php if ($pagination['hasNext']): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $pagination['currentPage'] + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&location=<?php echo urlencode($location); ?>&grade=<?php echo urlencode($grade); ?>">Next</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Add Item Modal -->
<?php if (isAdmin()): ?>
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm('addItemForm')">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Item Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category *</label>
                        <input type="text" class="form-control" id="category" name="category" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="grade" class="form-label">Grade *</label>
                        <select class="form-select" id="grade" name="grade" required onchange="toggleGradeFields()">
                            <option value="">Select Grade</option>
                            <option value="New">New</option>
                            <option value="Used">Used</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="quantityField" style="display: none;">
                        <label for="quantity" class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="0">
                    </div>
                    
                    <div class="mb-3" id="weightField" style="display: none;">
                        <label for="weight" class="form-label">Weight (kg) *</label>
                        <input type="number" class="form-control" id="weight" name="weight" min="0" step="0.01">
                    </div>
                    
                    <div class="mb-3">
                        <label for="item_condition" class="form-label">Condition</label>
                        <select class="form-select" id="item_condition" name="item_condition">
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="New">New</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="Storage">
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Item Image (Optional)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="text-muted">Max file size: 5MB. Allowed types: JPEG, PNG, GIF, WebP.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm('editItemForm')">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Item Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Category *</label>
                        <input type="text" class="form-control" id="edit_category" name="category" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_grade" class="form-label">Grade *</label>
                        <select class="form-select" id="edit_grade" name="grade" required onchange="toggleEditGradeFields()">
                            <option value="">Select Grade</option>
                            <option value="New">New</option>
                            <option value="Used">Used</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="editQuantityField" style="display: none;">
                        <label for="edit_quantity" class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0">
                    </div>
                    
                    <div class="mb-3" id="editWeightField" style="display: none;">
                        <label for="edit_weight" class="form-label">Weight (kg) *</label>
                        <input type="number" class="form-control" id="edit_weight" name="weight" min="0" step="0.01">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_condition" class="form-label">Condition</label>
                        <select class="form-select" id="edit_condition" name="item_condition">
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="New">New</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="edit_location" name="location">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_image" class="form-label">Item Image (Optional)</label>
                        <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                        <small class="text-muted">Max file size: 5MB. Allowed types: JPEG, PNG, GIF, WebP.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageViewerTitle">Item Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imageViewerImg" src="" alt="" class="img-fluid" style="max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qrModalLabel">QR Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="qrModalImg" src="" alt="QR Code" style="max-width: 100%; height: auto;" />
        <div id="qrModalItemName" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>

<script>
function viewImage(imagePath, itemName) {
    document.getElementById('imageViewerImg').src = imagePath;
    document.getElementById('imageViewerImg').alt = itemName;
    document.getElementById('imageViewerTitle').textContent = itemName + ' - Image';
    
    new bootstrap.Modal(document.getElementById('imageViewerModal')).show();
}

function editItem(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_name').value = item.name;
    document.getElementById('edit_category').value = item.category;
    document.getElementById('edit_grade').value = item.grade;
    document.getElementById('edit_quantity').value = item.quantity || '';
    document.getElementById('edit_weight').value = item.weight || '';
    document.getElementById('edit_condition').value = item.item_condition;
    document.getElementById('edit_location').value = item.location;
    
    // Show appropriate fields based on grade
    toggleEditGradeFields();
    
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

function toggleGradeFields() {
    const grade = document.getElementById('grade').value;
    const quantityField = document.getElementById('quantityField');
    const weightField = document.getElementById('weightField');
    const quantityInput = document.getElementById('quantity');
    const weightInput = document.getElementById('weight');
    
    if (grade === 'New') {
        quantityField.style.display = 'block';
        weightField.style.display = 'none';
        quantityInput.required = true;
        weightInput.required = false;
        weightInput.value = '';
    } else if (grade === 'Used') {
        quantityField.style.display = 'none';
        weightField.style.display = 'block';
        quantityInput.required = false;
        weightInput.required = true;
        quantityInput.value = '';
    } else {
        quantityField.style.display = 'none';
        weightField.style.display = 'none';
        quantityInput.required = false;
        weightInput.required = false;
    }
}

function toggleEditGradeFields() {
    const grade = document.getElementById('edit_grade').value;
    const quantityField = document.getElementById('editQuantityField');
    const weightField = document.getElementById('editWeightField');
    const quantityInput = document.getElementById('edit_quantity');
    const weightInput = document.getElementById('edit_weight');
    
    if (grade === 'New') {
        quantityField.style.display = 'block';
        weightField.style.display = 'none';
        quantityInput.required = true;
        weightInput.required = false;
    } else if (grade === 'Used') {
        quantityField.style.display = 'none';
        weightField.style.display = 'block';
        quantityInput.required = false;
        weightInput.required = true;
    } else {
        quantityField.style.display = 'none';
        weightField.style.display = 'none';
        quantityInput.required = false;
        weightInput.required = false;
    }
}

function deleteItem(id, name) {
    if (confirmDelete('Are you sure you want to delete "' + name + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewQR(qrUrl, itemName) {
    document.getElementById('qrModalImg').src = qrUrl;
    document.getElementById('qrModalItemName').textContent = itemName;
    var qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
    qrModal.show();
}

function toggleSelectAllItems(source) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkButtons();
}
function updateBulkButtons() {
    const checked = document.querySelectorAll('.item-checkbox:checked').length;
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkExportBtn = document.getElementById('bulkExportBtn');
    const bulkSelectedCount = document.getElementById('bulkSelectedCount');
    
    if (bulkDeleteBtn) {
        bulkDeleteBtn.disabled = checked === 0;
    }
    if (bulkExportBtn) {
        bulkExportBtn.disabled = checked === 0;
    }
    if (bulkSelectedCount) {
        bulkSelectedCount.textContent = checked > 0 ? checked + ' selected' : '';
    }
}
function submitBulkAction(action) {
    if (action === 'delete' && !confirm('Are you sure you want to delete the selected items?')) return;
    document.getElementById('bulk_action').value = action;
    document.getElementById('bulkActionsForm').submit();
}
document.addEventListener('DOMContentLoaded', updateBulkButtons);
</script>

<?php include 'includes/footer.php'; ?> 