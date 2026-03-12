<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Faculty Workload System'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .main-content {
            padding: 15px;
        }
        
        /* Responsive Navigation */
        .navbar-toggler {
            border: none;
            padding: 0.25rem 0.5rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #0d6efd;
        }
        
        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        
        /* Card improvements */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        /* Button improvements */
        .btn {
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-group .btn {
            margin-right: 0.25rem;
        }
        
        .btn-group .btn:last-child {
            margin-right: 0;
        }
        
        /* Table improvements */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                left: -250px;
                width: 250px;
                height: calc(100vh - 56px);
                z-index: 1000;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            .main-content.sidebar-open {
                margin-left: 250px;
            }
            
            .card-body {
                padding: 1rem 0.75rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-group {
                display: flex;
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-group .btn {
                margin-right: 0;
                margin-bottom: 0.25rem;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .d-flex.justify-content-between > * {
                margin-bottom: 0.5rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            .card-header {
                padding: 0.75rem;
            }
            
            .card-body {
                padding: 0.75rem;
            }
            
            .btn {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            h5 {
                font-size: 1.1rem;
            }
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                font-size: 12px;
            }
            .container-fluid {
                padding: 0;
            }
            .workload-form {
                page-break-inside: avoid;
            }
            table {
                font-size: 11px;
            }
            .table th, .table td {
                padding: 0.25rem;
                border: 1px solid #000 !important;
            }
        }
        
        .print-only {
            display: none;
        }
        
        .workload-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .college-logo {
            width: 80px;
            height: 80px;
        }
        
        .faculty-photo {
            width: 100px;
            height: 120px;
            border: 1px solid #000;
        }
        
        /* Utility classes */
        .mb-mobile {
            margin-bottom: 1rem;
        }
        
        @media (min-width: 768px) {
            .mb-mobile {
                margin-bottom: 0;
            }
        }
        
        /* Button click animation */
        .btn-clicked {
            transform: scale(0.95);
            transition: transform 0.1s ease;
        }
        
        /* Loading animation */
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Smooth transitions for better UX */
        .card, .btn, .form-control, .modal {
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        /* Success/Error alerts animation */
        .alert {
            animation: slideInDown 0.3s ease;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($hide_navigation) || !$hide_navigation): ?>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-mortarboard-fill me-2"></i>
                <span class="d-none d-sm-inline">Faculty Workload System</span>
                <span class="d-sm-none">FWS</span>
            </a>
            
            <?php if (isLoggedIn()): ?>
                <button class="navbar-toggler d-lg-none" type="button" onclick="toggleSidebar()">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <?php endif; ?>
            
            <div class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <span class="d-none d-md-inline ms-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6></li>
                            <li><span class="dropdown-item-text"><small><?php echo ucfirst($_SESSION['user_role']); ?></small></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php if (!isset($hide_sidebar) || !$hide_sidebar): ?>
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 sidebar no-print" id="sidebar">
                <div class="p-3">
                    <?php if (isAdmin()): ?>
                        <h6 class="text-muted mb-3 text-uppercase">Admin Menu</h6>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : ''; ?>" href="manage-users.php">
                                    <i class="bi bi-person-gear"></i> Manage Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-faculty.php' ? 'active' : ''; ?>" href="manage-faculty.php">
                                    <i class="bi bi-people"></i> Manage Faculty
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'encode-workload.php' ? 'active' : ''; ?>" href="encode-workload.php">
                                    <i class="bi bi-calendar-plus"></i> Encode Workload
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view-workloads.php' ? 'active' : ''; ?>" href="view-workloads.php">
                                    <i class="bi bi-file-text"></i> View Workloads
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-courses.php' ? 'active' : ''; ?>" href="manage-courses.php">
                                    <i class="bi bi-book"></i> Manage Courses
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-sections.php' ? 'active' : ''; ?>" href="manage-sections.php">
                                    <i class="bi bi-collection"></i> Manage Sections
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage-rooms.php' ? 'active' : ''; ?>" href="manage-rooms.php">
                                    <i class="bi bi-door-open"></i> Manage Rooms
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'room-schedule.php' ? 'active' : ''; ?>" href="room-schedule.php">
                                    <i class="bi bi-calendar-week"></i> Room Schedule
                                </a>
                            </li>
                        </ul>
                    <?php elseif (isFaculty()): ?>
                        <h6 class="text-muted mb-3 text-uppercase">Faculty Menu</h6>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-workload.php' ? 'active' : ''; ?>" href="my-workload.php">
                                    <i class="bi bi-calendar-check"></i> My Workload
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'room-availability.php' ? 'active' : ''; ?>" href="room-availability.php">
                                    <i class="bi bi-door-open"></i> Room Availability
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Main Content -->
            <div class="<?php echo (!isset($hide_sidebar) || !$hide_sidebar) ? 'col-lg-10 col-md-9' : 'col-12'; ?> main-content"> 