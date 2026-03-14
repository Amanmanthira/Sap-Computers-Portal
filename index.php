<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

$branchModel    = new BranchModel();
$supplierModel  = new SupplierModel();
$productModel   = new ProductModel();
$grnModel       = new GRNModel();
$stockModel     = new StockModel();

$totalBranches  = $branchModel->count();
$totalSuppliers = $supplierModel->count();
$totalProducts  = $productModel->count();
$totalStock     = $productModel->totalStockQuantity();
$stockValue     = $productModel->totalStockValue();
$lowStockItems  = $productModel->getLowStock();
$recentGRNs     = $grnModel->getRecent(8);
$monthlyTotals  = $grnModel->getMonthlyTotals();
$stockByCategory = $stockModel->getStockByCategory();
$stockByBranch  = $stockModel->getStockByBranch();

require_once __DIR__ . '/views/layouts/header.php';
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <a href="/sap-computers/products.php" class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-box-seam-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalProducts) ?></div>
                <div class="stat-label">Total Products</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="/sap-computers/suppliers.php" class="stat-card">
            <div class="stat-icon green"><i class="bi bi-truck"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalSuppliers) ?></div>
                <div class="stat-label">Suppliers</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="/sap-computers/branches.php" class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-building"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalBranches) ?></div>
                <div class="stat-label">Branches</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="/sap-computers/stock.php" class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-archive-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalStock) ?></div>
                <div class="stat-label">Total Stock Units</div>
            </div>
        </a>
    </div>
</div>

<!-- Stock Value + Low Stock Alert -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="border-color:var(--cyan);background:linear-gradient(135deg,var(--navy-mid),var(--navy));">
            <div class="stat-icon cyan"><i class="bi bi-currency-exchange"></i></div>
            <div>
                <div class="stat-value" style="font-size:20px;"><?= Helper::formatCurrency($stockValue) ?></div>
                <div class="stat-label">Total Stock Value</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <a href="/sap-computers/stock.php?filter=low" class="stat-card" style="border-color:<?= count($lowStockItems)>0?'var(--warning)':'var(--card-border)' ?>">
            <div class="stat-icon <?= count($lowStockItems)>0?'orange':'green' ?>">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div>
                <div class="stat-value" style="color:<?= count($lowStockItems)>0?'var(--warning)':'var(--success)' ?>">
                    <?= count($lowStockItems) ?>
                </div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/sap-computers/grn.php" class="stat-card">
            <div class="stat-icon green"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="stat-value"><?= $grnModel->countAll() ?></div>
                <div class="stat-label">Total GRNs</div>
            </div>
        </a>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="content-card h-100">
            <div class="content-card-header">
                <div class="content-card-title"><i class="bi bi-pie-chart-fill"></i> Stock by Category</div>
            </div>
            <div class="content-card-body">
                <div class="chart-wrapper" style="height:240px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="content-card h-100">
            <div class="content-card-header">
                <div class="content-card-title"><i class="bi bi-bar-chart-fill"></i> Monthly GRN Purchases</div>
            </div>
            <div class="content-card-body">
                <div class="chart-wrapper" style="height:240px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row g-3">
    <!-- Recent GRNs -->
    <div class="col-lg-7">
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title"><i class="bi bi-receipt-cutoff"></i> Recent GRNs</div>
                <a href="/sap-computers/grn.php" class="btn-outline-cyan" style="font-size:12px;padding:5px 12px;">View All</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table-dark-custom">
                    <thead>
                        <tr>
                            <th>GRN #</th>
                            <th>Supplier</th>
                            <th>Branch</th>
                            <th>Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentGRNs as $g): ?>
                        <tr>
                            <td><span class="text-cyan"><?= Helper::e($g['grn_number']) ?></span></td>
                            <td><?= Helper::e($g['supplier_name']) ?></td>
                            <td><?= Helper::e($g['branch_name']) ?></td>
                            <td><?= Helper::formatDate($g['grn_date']) ?></td>
                            <td><?= Helper::formatCurrency($g['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="col-lg-5">
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title"><i class="bi bi-exclamation-triangle-fill" style="color:var(--warning);"></i> Low Stock Products</div>
                <a href="/sap-computers/stock.php?filter=low" class="btn-outline-cyan" style="font-size:12px;padding:5px 12px;">View All</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table-dark-custom">
                    <thead>
                        <tr><th>Product</th><th>Stock</th><th>Reorder</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($lowStockItems, 0, 8) as $p): ?>
                        <tr>
                            <td>
                                <div style="font-weight:500;"><?= Helper::e($p['product_name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?= Helper::e($p['brand']) ?></div>
                            </td>
                            <td>
                                <?php if ((int)$p['total_stock'] === 0): ?>
                                    <span class="badge bg-danger">Out of Stock</span>
                                <?php else: ?>
                                    <span style="color:var(--warning);font-weight:600;"><?= $p['total_stock'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--text-muted);"><?= $p['reorder_level'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lowStockItems)): ?>
                        <tr><td colspan="3" style="text-align:center;color:var(--success);padding:20px;">
                            <i class="bi bi-check-circle-fill me-2"></i>All products are well stocked!
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Branch Stock Distribution -->
    <div class="col-12">
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title"><i class="bi bi-building"></i> Stock Distribution by Branch</div>
            </div>
            <div class="content-card-body">
                <div class="row g-3">
                <?php foreach ($stockByBranch as $b): ?>
                    <div class="col-6 col-md-3">
                        <div style="background:var(--navy);border:1px solid var(--navy-border);border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:22px;font-weight:800;font-family:'Syne',sans-serif;color:var(--cyan);"><?= number_format($b['total_qty']) ?></div>
                            <div style="font-weight:600;font-size:13px;margin-top:2px;"><?= Helper::e($b['branch_name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);"><?= $b['product_count'] ?> products</div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Prepare chart data
$catLabels = array_column($stockByCategory, 'category_name');
$catData = array_column($stockByCategory, 'total_qty');
$months = array_column($monthlyTotals, 'month');
$monthAmounts = array_column($monthlyTotals, 'total');

$extraScripts = '<script>
// Category Pie Chart
new Chart(document.getElementById("categoryChart"), {
    type: "doughnut",
    data: {
        labels: ' . json_encode($catLabels) . ',
        datasets: [{
            data: ' . json_encode($catData) . ',
            backgroundColor: ["#00c8ff","#00d68f","#ffaa00","#ff4757","#9370db","#ff6348"],
            borderWidth: 2,
            borderColor: "#162236"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "right",
                labels: { color: "#7a92b0", font: { size: 12 }, padding: 12, boxWidth: 14 }
            }
        },
        cutout: "65%"
    }
});

// Monthly Bar Chart
new Chart(document.getElementById("monthlyChart"), {
    type: "bar",
    data: {
        labels: ' . json_encode($months) . ',
        datasets: [{
            label: "GRN Amount (Rs.)",
            data: ' . json_encode($monthAmounts) . ',
            backgroundColor: "rgba(0,200,255,0.2)",
            borderColor: "#00c8ff",
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: "#243550" }, ticks: { color: "#7a92b0" } },
            y: { grid: { color: "#243550" }, ticks: { color: "#7a92b0",
                callback: v => "Rs." + (v/1000).toFixed(0)+"K" } }
        }
    }
});
</script>';

require_once __DIR__ . '/views/layouts/footer.php';
?>
