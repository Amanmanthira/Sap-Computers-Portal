<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Reports';
$activePage = 'reports';

$reportType = $_GET['type'] ?? 'stock';
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to'] ?? date('Y-m-d');
$branchId   = (int)($_GET['branch_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);

$db = Database::getInstance();
$branchModel   = new BranchModel();
$supplierModel = new SupplierModel();
$branches      = $branchModel->getActive();
$suppliers     = $supplierModel->getActive();

// Report data
$data = [];

switch ($reportType) {
    case 'stock':
        $sql = "SELECT p.product_name, p.brand, p.model, c.category_name, s.supplier_name,
                COALESCE(SUM(st.quantity),0) as total_qty, p.cost_price, p.selling_price, p.reorder_level,
                COALESCE(SUM(st.quantity * p.cost_price),0) as stock_value
                FROM products p
                LEFT JOIN categories c ON p.category_id=c.category_id
                LEFT JOIN suppliers s ON p.supplier_id=s.supplier_id
                LEFT JOIN stock st ON p.product_id=st.product_id " .
                ($branchId ? "AND st.branch_id=$branchId " : "") . "
                GROUP BY p.product_id ORDER BY p.product_name";
        $data = $db->query($sql)->fetchAll();
        break;

    case 'grn':
        $sql = "SELECT g.grn_number, s.supplier_name, b.branch_name, g.invoice_number,
                g.grn_date, g.total_amount, u.name as created_by
                FROM grn g
                JOIN suppliers s ON g.supplier_id=s.supplier_id
                JOIN branches b ON g.branch_id=b.branch_id
                JOIN users u ON g.created_by=u.id
                WHERE g.grn_date BETWEEN ? AND ?" .
                ($supplierId ? " AND g.supplier_id=$supplierId" : "") .
                ($branchId ? " AND g.branch_id=$branchId" : "") .
                " ORDER BY g.grn_date DESC";
        $data = $db->prepare($sql)->execute([$dateFrom, $dateTo]) ? $db->query($sql, [$dateFrom,$dateTo])->fetchAll() : [];
        // Re-execute properly
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom,$dateTo]);
        $data = $stmt->fetchAll();
        break;

    case 'low_stock':
        $productModel = new ProductModel();
        $data = $productModel->getLowStock();
        break;

    case 'supplier':
        $sql = "SELECT s.supplier_name, s.contact_person, s.phone,
                COUNT(DISTINCT p.product_id) as product_count,
                COUNT(DISTINCT g.grn_id) as grn_count,
                COALESCE(SUM(g.total_amount),0) as total_purchased
                FROM suppliers s
                LEFT JOIN products p ON s.supplier_id=p.supplier_id
                LEFT JOIN grn g ON s.supplier_id=g.supplier_id AND g.grn_date BETWEEN ? AND ?
                GROUP BY s.supplier_id ORDER BY total_purchased DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateFrom,$dateTo]);
        $data = $stmt->fetchAll();
        break;
}

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Reports</div>
        <div class="page-header-sub">Generate and export inventory reports</div>
    </div>
    <button class="btn-cyan" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print Report</button>
</div>

<div class="row g-3 mb-4">
    <?php
    $reportTypes = [
        'stock'    => ['icon'=>'bi-archive-fill','label'=>'Stock Report','color'=>'cyan'],
        'grn'      => ['icon'=>'bi-receipt-cutoff','label'=>'GRN Report','color'=>'green'],
        'low_stock'=> ['icon'=>'bi-exclamation-triangle-fill','label'=>'Low Stock','color'=>'orange'],
        'supplier' => ['icon'=>'bi-truck','label'=>'Supplier Report','color'=>'purple'],
    ];
    foreach ($reportTypes as $type => $info):
        $active = $reportType === $type;
    ?>
    <div class="col-6 col-md-3">
        <a href="?type=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
           class="stat-card" style="<?= $active ? 'border-color:var(--cyan);background:var(--cyan-dim);' : '' ?>">
            <div class="stat-icon <?= $info['color'] ?>"><i class="bi <?= $info['icon'] ?>"></i></div>
            <div>
                <div style="font-weight:700;font-size:13px;"><?= $info['label'] ?></div>
                <?php if($active): ?><div style="font-size:11px;color:var(--cyan);">Currently viewing</div><?php endif; ?>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <input type="hidden" name="type" value="<?= Helper::e($reportType) ?>">
        <?php if(in_array($reportType,['grn','supplier'])): ?>
        <input type="date" name="date_from" class="form-control-dark" value="<?= Helper::e($dateFrom) ?>" style="width:150px;">
        <input type="date" name="date_to" class="form-control-dark" value="<?= Helper::e($dateTo) ?>" style="width:150px;">
        <?php endif; ?>
        <?php if(in_array($reportType,['stock','grn'])): ?>
        <select name="branch_id" class="form-select-dark" style="width:160px;">
            <option value="">All Branches</option>
            <?php foreach($branches as $b): ?>
            <option value="<?= $b['branch_id'] ?>" <?= $branchId==$b['branch_id']?'selected':'' ?>><?= Helper::e($b['branch_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if($reportType==='grn'): ?>
        <select name="supplier_id" class="form-select-dark" style="width:160px;">
            <option value="">All Suppliers</option>
            <?php foreach($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>" <?= $supplierId==$s['supplier_id']?'selected':'' ?>><?= Helper::e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn-cyan"><i class="bi bi-play-fill"></i> Generate</button>
    </form>
</div>

<!-- Report Output -->
<div class="content-card">
    <div class="content-card-header">
        <div class="content-card-title"><i class="bi bi-table"></i> <?= $reportTypes[$reportType]['label'] ?> — <?= count($data) ?> records</div>
        <div style="display:flex;gap:8px;">
            <span style="font-size:12px;color:var(--text-muted);">Generated: <?= date('d M Y, h:i A') ?></span>
        </div>
    </div>
    <div style="overflow-x:auto;">
    <?php if($reportType==='stock'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Product</th><th>Brand</th><th>Category</th><th>Supplier</th><th>Stock Qty</th><th>Cost</th><th>Selling</th><th>Stock Value</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><?= Helper::e($r['product_name']) ?></td>
                <td><?= Helper::e($r['brand']) ?></td>
                <td><span class="badge bg-info"><?= Helper::e($r['category_name']) ?></span></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td><strong style="color:var(--cyan)"><?= $r['total_qty'] ?></strong></td>
                <td><?= Helper::formatCurrency($r['cost_price']) ?></td>
                <td style="color:var(--success)"><?= Helper::formatCurrency($r['selling_price']) ?></td>
                <td style="color:var(--warning)"><?= Helper::formatCurrency($r['stock_value']) ?></td>
                <td><?= Helper::stockBadge((int)$r['total_qty'],(int)$r['reorder_level']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='grn'): ?>
        <?php $total = array_sum(array_column($data,'total_amount')); ?>
        <div style="padding:12px 20px;background:var(--cyan-dim);display:flex;gap:24px;">
            <span>Total GRNs: <strong style="color:var(--cyan)"><?= count($data) ?></strong></span>
            <span>Total Amount: <strong style="color:var(--cyan)"><?= Helper::formatCurrency($total) ?></strong></span>
        </div>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>GRN #</th><th>Supplier</th><th>Branch</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Created By</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td class="text-cyan"><?= Helper::e($r['grn_number']) ?></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td><?= Helper::e($r['branch_name']) ?></td>
                <td style="color:var(--text-muted)"><?= Helper::e($r['invoice_number']?:'—') ?></td>
                <td><?= Helper::formatDate($r['grn_date']) ?></td>
                <td style="color:var(--success);font-weight:600"><?= Helper::formatCurrency($r['total_amount']) ?></td>
                <td style="color:var(--text-muted)"><?= Helper::e($r['created_by']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='low_stock'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Product</th><th>Brand</th><th>Category</th><th>Supplier</th><th>Stock</th><th>Reorder Level</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><?= Helper::e($r['product_name']) ?></td>
                <td><?= Helper::e($r['brand']) ?></td>
                <td><span class="badge bg-info"><?= Helper::e($r['category_name']) ?></span></td>
                <td><?= Helper::e($r['supplier_name']) ?></td>
                <td><strong style="color:var(--warning)"><?= $r['total_stock'] ?></strong></td>
                <td><?= $r['reorder_level'] ?></td>
                <td><?= Helper::stockBadge((int)$r['total_stock'],(int)$r['reorder_level']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif($reportType==='supplier'): ?>
        <table class="table-dark-custom datatable w-100">
            <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Products</th><th>GRNs</th><th>Total Purchased</th></tr></thead>
            <tbody>
            <?php foreach($data as $r): ?>
            <tr>
                <td><strong><?= Helper::e($r['supplier_name']) ?></strong></td>
                <td><?= Helper::e($r['contact_person']) ?></td>
                <td><?= Helper::e($r['phone']) ?></td>
                <td><span style="color:var(--cyan)"><?= $r['product_count'] ?></span></td>
                <td><span style="color:var(--success)"><?= $r['grn_count'] ?></span></td>
                <td style="color:var(--warning);font-weight:700"><?= Helper::formatCurrency($r['total_purchased']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
