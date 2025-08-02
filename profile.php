<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();

$userId = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT name, username, role, department, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle password change
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Fetch current password
    $stmt = $pdo->prepare("SELECT user_password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentPassword = $row['user_password'];

    if ($current !== $currentPassword) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET user_password = ? WHERE id = ?");
        if ($stmt->execute([$new, $userId])) {
            $success = 'Password updated successfully!';
        } else {
            $error = 'Failed to update password.';
        }
    }
}

$pageTitle = 'My Profile';
include 'includes/header.php';
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header" style="background-color: #000; color: white;">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Full Name</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user['name']); ?></dd>
                        <dt class="col-sm-4">Username</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user['username']); ?></dd>
                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8 text-capitalize"><?php echo htmlspecialchars($user['role']); ?></dd>
                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($user['department']); ?></dd>
                        <dt class="col-sm-4">Joined</dt>
                        <dd class="col-sm-8"><?php echo formatDate($user['created_at']); ?></dd>
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="card-header" style="background-color: #000; color: white;">
                    <h6 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h6>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"> <?php echo $success; ?> </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"> <?php echo $error; ?> </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?> 