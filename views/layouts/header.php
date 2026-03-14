<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helper::e($pageTitle ?? 'Dashboard') ?> — SAP Computers IMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="/sap-computers/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        
       <div class="brand-text">
           <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers Logo" >
       </div>
        <button class="sidebar-toggle d-lg-none" id="sidebarClose">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= strtoupper(substr(Session::get('user_name','?'),0,2)) ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?= Helper::e(Session::get('user_name')) ?></div>
            <div class="user-role">
                <?php
                $role = Session::get('user_role');
                if ($role === 'seller') {
                    echo '<i class="bi bi-shield-check me-1"></i>Admin';
                } elseif ($role === 'staff') {
                    echo '<i class="bi bi-people-fill me-1"></i>Branch Staff';
                    $branch = Session::get('user_branch_name');
                    if ($branch) {
                        echo ' &ndash; ' . Helper::e($branch);
                    }
                } else {
                    echo '<i class="bi bi-building me-1"></i>Supplier';
                }
                ?>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if(Session::get('user_role') === 'seller'): ?>
        <div class="nav-section-label">Main Menu</div>
        <a href="/sap-computers/index.php" class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <div class="nav-section-label">Operations</div>
        <a href="/sap-computers/branches.php" class="nav-item <?= ($activePage??'')==='branches'?'active':'' ?>">
            <i class="bi bi-building"></i><span>Branches</span>
        </a>
        <a href="/sap-computers/suppliers.php" class="nav-item <?= ($activePage??'')==='suppliers'?'active':'' ?>">
            <i class="bi bi-truck"></i><span>Suppliers</span>
        </a>
        <a href="/sap-computers/categories.php" class="nav-item <?= ($activePage??'')==='categories'?'active':'' ?>">
            <i class="bi bi-tags-fill"></i><span>Categories</span>
        </a>
        <a href="/sap-computers/products.php" class="nav-item <?= ($activePage??'')==='products'?'active':'' ?>">
            <i class="bi bi-box-seam-fill"></i><span>Products</span>
        </a>
        <div class="nav-section-label">Inventory</div>
        <a href="/sap-computers/grn.php" class="nav-item <?= ($activePage??'')==='grn'?'active':'' ?>">
            <i class="bi bi-receipt-cutoff"></i><span>GRN</span>
        </a>
        <a href="/sap-computers/stock.php" class="nav-item <?= ($activePage??'')==='stock'?'active':'' ?>">
            <i class="bi bi-archive-fill"></i><span>Stock</span>
        </a>
        <a href="/sap-computers/movements.php" class="nav-item <?= ($activePage??'')==='movements'?'active':'' ?>">
            <i class="bi bi-arrow-left-right"></i><span>Movements</span>
        </a>
        <a href="/sap-computers/pos.php" class="nav-item <?= ($activePage??'')==='pos'?'active':'' ?>">
            <i class="bi bi-currency-dollar"></i><span>POS</span>
        </a>
        <a href="/sap-computers/pos_history.php" class="nav-item <?= ($activePage??'')==='pos_history'?'active':'' ?>">
            <i class="bi bi-receipt"></i><span>Sales History</span>
        </a>
        <div class="nav-section-label">Admin</div>
        <a href="/sap-computers/reports.php" class="nav-item <?= ($activePage??'')==='reports'?'active':'' ?>">
            <i class="bi bi-bar-chart-line-fill"></i><span>Reports</span>
        </a>
        <a href="/sap-computers/admin_branch_orders.php" class="nav-item <?= ($activePage??'')==='orders_admin'?'active':'' ?>">
            <i class="bi bi-list-check"></i><span>Branch Orders</span>
        </a>
        <a href="/sap-computers/users.php" class="nav-item <?= ($activePage??'')==='users'?'active':'' ?>">
            <i class="bi bi-people-fill"></i><span>Users</span>
        </a>
        <?php elseif(Session::get('user_role')==='staff'): ?>
        <div class="nav-section-label">Branch Staff</div>
        <a href="/sap-computers/branch_orders.php" class="nav-item <?= ($activePage??'')==='branch_orders'?'active':'' ?>">
            <i class="bi bi-cart-plus"></i><span>Request Products</span>
        </a>
        <?php else: ?>
        <div class="nav-section-label">Supplier Portal</div>
        <a href="/sap-computers/supplier/dashboard.php" class="nav-item <?= ($activePage??'')==='dashboard'?'active':'' ?>">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <a href="/sap-computers/supplier/products.php" class="nav-item <?= ($activePage??'')==='products'?'active':'' ?>">
            <i class="bi bi-box-seam-fill"></i><span>My Products</span>
        </a>
        <a href="/sap-computers/supplier/grns.php" class="nav-item <?= ($activePage??'')==='grn'?'active':'' ?>">
            <i class="bi bi-receipt-cutoff"></i><span>GRN History</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="/sap-computers/logout.php" class="nav-item nav-item-logout">
            <i class="bi bi-box-arrow-left"></i><span>Logout</span>
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="main-wrapper" id="mainWrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <button class="btn-menu-toggle" id="menuToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title"><?= Helper::e($pageTitle ?? 'Dashboard') ?></div>
        <div class="topbar-actions">
            <!-- Live Search -->
            <div class="search-wrapper d-none d-md-flex" id="globalSearchWrapper">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="global-search" id="globalSearch" placeholder="Search products, suppliers…">
                <div class="search-results" id="searchResults"></div>
            </div>

            <?php
            // Low stock notification bell
            $productModel = new ProductModel();
            $lowStockCount = count($productModel->getLowStock());
            ?>
            <div class="notification-bell" data-bs-toggle="dropdown">
                <i class="bi bi-bell-fill"></i>
                <?php if($lowStockCount > 0): ?>
                <span class="notif-badge"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </div>
            <ul class="dropdown-menu dropdown-menu-end notif-dropdown">
                <li class="dropdown-header">Notifications</li>
                <?php if($lowStockCount > 0): ?>
                <li><a class="dropdown-item notif-item" href="/sap-computers/stock.php?filter=low">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                    <?= $lowStockCount ?> product(s) below reorder level
                </a></li>
                <?php else: ?>
                <li><div class="dropdown-item text-muted small">No alerts</div></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php $flash = Session::getFlash(); if($flash): ?>
    <div class="flash-container">
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible flash-alert" role="alert">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?> me-2"></i>
            <?= Helper::e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="page-content">
