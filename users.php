<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

$pageTitle = 'User Management';

$database = new Database();
$pdo = $database->getConnection();

// Handle user operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitizeInput($_POST['name']);
                $username = sanitizeInput($_POST['username']);
                $password = $_POST['password'];
                $role = sanitizeInput($_POST['role']);
                $department = isset($_POST['department']) ? sanitizeInput($_POST['department']) : null;
                
                if (!empty($name) && !empty($username) && !empty($password)) {
                    if (validatePassword($password)) {
                        // Check if username already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->fetch()) {
                            setFlashMessage('danger', 'Username already exists.');
                        } else {
                            $sql = "INSERT INTO users (name, username, user_password, role, department) VALUES (?, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            if ($stmt->execute([$name, $username, $password, $role, $department])) {
                                logActivity($pdo, $_SESSION['user_id'], null, 'Added new user', "User: $name, Role: $role");
                                setFlashMessage('success', 'User added successfully!');
                            } else {
                                setFlashMessage('danger', 'Error adding user.');
                            }
                        }
                    } else {
                        setFlashMessage('danger', 'Password must be at least 6 characters long.');
                    }
                } else {
                    setFlashMessage('danger', 'Please fill in all fields correctly.');
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitizeInput($_POST['name']);
                $username = sanitizeInput($_POST['username']);
                $role = sanitizeInput($_POST['role']);
                $department = isset($_POST['department']) ? sanitizeInput($_POST['department']) : null;
                $password = $_POST['password'];
                
                if (!empty($name) && !empty($username)) {
                    // Check if username already exists for other users
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $id]);
                    if ($stmt->fetch()) {
                        setFlashMessage('danger', 'Username already exists.');
                    } else {
                        if (!empty($password)) {
                            if (validatePassword($password)) {
                                $sql = "UPDATE users SET name = ?, username = ?, user_password = ?, role = ?, department = ? WHERE id = ?";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$name, $username, $password, $role, $department, $id]);
                            } else {
                                setFlashMessage('danger', 'Password must be at least 6 characters long.');
                                break;
                            }
                        } else {
                            $sql = "UPDATE users SET name = ?, username = ?, role = ?, department = ? WHERE id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$name, $username, $role, $department, $id]);
                        }
                        
                        if ($stmt->rowCount() > 0) {
                            logActivity($pdo, $_SESSION['user_id'], null, 'Updated user', "User: $name, Role: $role");
                            setFlashMessage('success', 'User updated successfully!');
                        } else {
                            setFlashMessage('danger', 'Error updating user.');
                        }
                    }
                } else {
                    setFlashMessage('danger', 'Please fill in all required fields correctly.');
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Prevent deleting self
                if ($id == $_SESSION['user_id']) {
                    setFlashMessage('danger', 'You cannot delete your own account.');
                } else {
                    // Check if user has active borrowings
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND date_returned IS NULL");
                    $stmt->execute([$id]);
                    $activeBorrowings = $stmt->fetchColumn();
                    
                    if ($activeBorrowings > 0) {
                        setFlashMessage('danger', 'Cannot delete user with active borrowings.');
                    } else {
                        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt->execute([$id]);
                        $userName = $stmt->fetchColumn();
                        
                        $sql = "DELETE FROM users WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute([$id])) {
                            logActivity($pdo, $_SESSION['user_id'], null, 'Deleted user', "User: $userName");
                            setFlashMessage('success', 'User deleted successfully!');
                        } else {
                            setFlashMessage('danger', 'Error deleting user.');
                        }
                    }
                }
                break;
        }
    }
    header("Location: users.php");
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role = isset($_GET['role']) ? sanitizeInput($_GET['role']) : '';
$department = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(name LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role)) {
    $whereConditions[] = "role = ?";
    $params[] = $role;
}

if (!empty($department)) {
    $whereConditions[] = "department = ?";
    $params[] = $department;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total records for pagination
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();

$pagination = getPagination($totalRecords, $recordsPerPage, $page);

// Get users
$sql = "SELECT * FROM users $whereClause ORDER BY name ASC LIMIT " . (int)$recordsPerPage . " OFFSET " . (int)$pagination['offset'];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or email...">
            </div>
            <div class="col-md-2">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="staff" <?php echo $role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="manager" <?php echo $role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="supervisor" <?php echo $role === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">All Departments</option>
                    <option value="FS" <?php echo $department === 'FS' ? 'selected' : ''; ?>>FS</option>
                    <option value="QA" <?php echo $department === 'QA' ? 'selected' : ''; ?>>QA</option>
                    <option value="FABRICATION" <?php echo $department === 'FABRICATION' ? 'selected' : ''; ?>>FABRICATION</option>
                    <option value="CNC" <?php echo $department === 'CNC' ? 'selected' : ''; ?>>CNC</option>
                    <option value="SHEET METAL" <?php echo $department === 'SHEET METAL' ? 'selected' : ''; ?>>SHEET METAL</option>
                    <option value="ADMINISTRATION" <?php echo $department === 'ADMINISTRATION' ? 'selected' : ''; ?>>ADMINISTRATION</option>
                    <option value="OUTSOURCING" <?php echo $department === 'OUTSOURCING' ? 'selected' : ''; ?>>OUTSOURCING</option>
                    <option value="PAINTING" <?php echo $department === 'PAINTING' ? 'selected' : ''; ?>>PAINTING</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <a href="users.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add User Button -->
<div class="mb-3">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus me-2"></i>Add New User
    </button>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>Users (<?php echo $totalRecords; ?> total)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <p class="text-muted">No users found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php elseif ($user['role'] === 'manager'): ?>
                                    <span class="badge bg-success">Manager</span>
                                <?php elseif ($user['role'] === 'supervisor'): ?>
                                                                            <span class="badge bg-dark">Supervisor</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Staff</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
            <nav aria-label="Users pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($pagination['hasPrev']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['currentPage'] - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&department=<?php echo urlencode($department); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['currentPage'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&department=<?php echo urlencode($department); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['hasNext']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['currentPage'] + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&department=<?php echo urlencode($department); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return validateForm('addUserForm')">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select role...</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">Department *</label>
                        <select class="form-select" id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="FS">FS</option>
                            <option value="QA">QA</option>
                            <option value="FABRICATION">FABRICATION</option>
                            <option value="CNC">CNC</option>
                            <option value="SHEET METAL">SHEET METAL</option>
                            <option value="ADMINISTRATION">ADMINISTRATION</option>
                            <option value="OUTSOURCING">OUTSOURCING</option>
                            <option value="PAINTING">PAINTING</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return validateForm('editUserForm')">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password" minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role *</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="">Select role...</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department" class="form-label">Department *</label>
                        <select class="form-select" id="edit_department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="FS">FS</option>
                            <option value="QA">QA</option>
                            <option value="FABRICATION">FABRICATION</option>
                            <option value="CNC">CNC</option>
                            <option value="SHEET METAL">SHEET METAL</option>
                            <option value="ADMINISTRATION">ADMINISTRATION</option>
                            <option value="OUTSOURCING">OUTSOURCING</option>
                            <option value="PAINTING">PAINTING</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_department').value = user.department;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function deleteUser(id, name) {
    if (confirmDelete('Are you sure you want to delete user "' + name + '"?')) {
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
</script>

<?php include 'includes/footer.php'; ?> 