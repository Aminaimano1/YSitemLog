<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>ItemLog System - YS Manufacturing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/ItemLog/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/ItemLog/icon-192.png">
    <meta name="theme-color" content="#000000">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #000;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.15);
        }
        .main-content {
            background-color: white;
            min-height: 100vh;
        }
        .card {
            border: 1px solid #e9ecef;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            color: #000;
            font-weight: 600;
        }
        .btn-primary {
            background-color: #000;
            border-color: #000;
        }
        .btn-primary:hover {
            background-color: #222;
            border-color: #222;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .badge.bg-primary {
            background-color: #000 !important;
        }
        .badge.bg-secondary {
            background-color: #6c757d !important;
        }
        .text-primary {
            color: #000 !important;
        }
        .border-primary {
            border-color: #000 !important;
        }
        .company-logo {
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .company-logo i {
            color: #ffd700;
        }
        .logout-btn {
            background-color: #dc3545;
            border: none;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            display: block;
            text-align: left;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        .logout-btn:hover {
            background-color: #c82333;
            color: white;
            text-decoration: none;
            transform: translateX(5px);
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 100%;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
            .table-responsive {
                font-size: 0.875rem;
            }
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            .card-body {
                padding: 1rem;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            .img-thumbnail {
                width: 40px !important;
                height: 40px !important;
            }
        }
        
        /* Mobile Navigation Toggle */
        .mobile-nav-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background-color: #000;
            border: none;
            color: white;
            padding: 0.5rem;
            border-radius: 0.375rem;
        }
        
        @media (max-width: 768px) {
            .mobile-nav-toggle {
                display: block;
            }
        }
        
        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        .badge-soft-success {
            background-color: #d1f7df !important;  /* very light green */
            color: #198754 !important;             /* Bootstrap green */
            border-radius: 999px !important;       /* pill shape */
            font-weight: 500;
            padding: 0.35em 0.8em;
            font-size: 1em;
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white mb-2 company-logo">
                            <i class="fas fa-industry me-2"></i>YS Manufacturing
                        </h4>
                        <h6 class="text-white-50 mb-2">ItemLog System</h6>
                        <small class="text-white-50">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>" href="items.php">
                                <i class="fas fa-boxes me-2"></i>
                                Items
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['borrow.php', 'admin_borrowings.php']) ? 'active' : ''; ?>" href="admin_borrowings.php">
                                <i class="fas fa-hand-holding me-2"></i>
                                Borrow Records
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['return.php', 'admin_returns.php']) ? 'active' : ''; ?>" href="admin_returns.php">
                                <i class="fas fa-undo me-2"></i>
                                Return Records
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'borrow.php' ? 'active' : ''; ?>" href="borrow.php">
                                <i class="fas fa-hand-holding me-2"></i>
                                Borrow Item
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'return.php' ? 'active' : ''; ?>" href="return.php">
                                <i class="fas fa-undo me-2"></i>
                                Return Item
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                Users
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'print_qr_labels.php' ? 'active' : ''; ?>" href="print_qr_labels.php">
                                <i class="fas fa-qrcode me-2"></i>
                                Print QR Labels
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>" href="logs.php">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="logout-btn" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h1>
                </div>
    <?php endif; ?>

    <!-- Flash Messages -->
    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }
    
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    }
    
    // Close sidebar when clicking on a nav link on mobile
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });
    });
    </script> 