<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Stock Management';
$activePage = 'stock';
$stockModel   = new StockModel();
$supModel     = new SupplierModel();
$branchModel  = new BranchModel();
$catModel     = new CategoryModel();
$productModel = new ProductModel();

$filters = [
    'search'      => Helper::sanitize($_GET['search'] ?? ''),
    'branch_id'   => (int)($_GET['branch_id'] ?? 0),
    'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
    'category_id' => (int)($_GET['category_id'] ?? 0),
];

$filterLow = ($_GET['filter'] ?? '') === 'low';

$stocks     = $stockModel->getAll(array_filter($filters));
$suppliers  = $supModel->getActive();
$branches   = $branchModel->getActive();
$categories = $catModel->getAll();

if ($filterLow) {
    $lowItems = $productModel->getLowStock();
}

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Stock Management</div>
        <div class="page-header-sub">
            <?= $filterLow ? 'Showing low stock products' : count($stocks) . ' stock records' ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/sap-computers/stock.php?filter=low" class="btn-ghost <?= $filterLow?'active':'' ?>" style="<?= $filterLow?'border-color:var(--warning);color:var(--warning);':'' ?>">
            <i class="bi bi-exclamation-triangle-fill"></i> Low Stock
        </a>
        <a href="/sap-computers/grn.php" class="btn-cyan"><i class="bi bi-plus-lg"></i> Add Stock (GRN)</a>
    </div>
</div>

<?php if ($filterLow): ?>
<!-- Low Stock View -->
<div class="content-card">
    <div class="content-card-header">
        <div class="content-card-title" style="color:var(--warning);"><i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alerts (<?= count($lowItems ?? []) ?>)</div>
        <a href="/sap-computers/stock.php" class="btn-ghost">View All Stock</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr><th>Product</th><th>Brand</th><th>Category</th><th>Supplier</th><th>Total Stock</th><th>Reorder Level</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lowItems ?? [] as $p): ?>
                <tr>
                    <td><strong><?= Helper::e($p['product_name']) ?></strong></td>
                    <td><?= Helper::e($p['brand']) ?></td>
                    <td><span class="badge bg-info"><?= Helper::e($p['category_name']) ?></span></td>
                    <td><?= Helper::e($p['supplier_name']) ?></td>
                    <td>
                        <?php if((int)$p['total_stock']===0): ?>
                            <span class="badge bg-danger">Out of Stock</span>
                        <?php else: ?>
                            <span style="color:var(--warning);font-weight:700;"><?= $p['total_stock'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $p['reorder_level'] ?></td>
                    <td><?= Helper::stockBadge((int)$p['total_stock'],(int)$p['reorder_level']) ?></td>
                    <td><a href="/sap-computers/grn.php" class="btn-cyan" style="font-size:12px;padding:5px 10px;"><i class="bi bi-cart-plus-fill"></i> Reorder</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Full Stock View -->
<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search…" class="form-control-dark" style="width:180px;" value="<?= Helper::e($filters['search']) ?>">
        <select name="branch_id" class="form-select-dark" style="width:160px;">
            <option value="">All Branches</option>
            <?php foreach($branches as $b): ?>
            <option value="<?= $b['branch_id'] ?>" <?= $filters['branch_id']==$b['branch_id']?'selected':'' ?>><?= Helper::e($b['branch_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="supplier_id" class="form-select-dark" style="width:160px;">
            <option value="">All Suppliers</option>
            <?php foreach($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>" <?= $filters['supplier_id']==$s['supplier_id']?'selected':'' ?>><?= Helper::e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="category_id" class="form-select-dark" style="width:160px;">
            <option value="">All Categories</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= $filters['category_id']==$c['category_id']?'selected':'' ?>><?= Helper::e($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-cyan"><i class="bi bi-funnel-fill"></i> Filter</button>
        <a href="/sap-computers/stock.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>Product</th><th>Brand</th><th>Model</th><th>Category</th>
                    <th>Branch</th><th>Supplier</th><th>Qty</th>
                    <th>Cost</th><th>Selling</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stocks as $s): ?>
                <tr>
                    <td><strong><?= Helper::e($s['product_name']) ?></strong></td>
                    <td><?= Helper::e($s['brand']) ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($s['model']) ?></td>
                    <td><span class="badge bg-info"><?= Helper::e($s['category_name']) ?></span></td>
                    <td><?= Helper::e($s['branch_name']) ?></td>
                    <td><?= Helper::e($s['supplier_name']) ?></td>
                    <td>
                        <strong style="font-size:15px;<?= (int)$s['quantity'] <= (int)$s['reorder_level'] ? 'color:var(--warning)' : 'color:var(--cyan)' ?>">
                            <?= $s['quantity'] ?>
                        </strong>
                    </td>
                    <td><?= Helper::formatCurrency($s['cost_price']) ?></td>
                    <td style="color:var(--success)"><?= Helper::formatCurrency($s['selling_price']) ?></td>
                    <td><?= Helper::stockBadge((int)$s['quantity'], (int)$s['reorder_level']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
