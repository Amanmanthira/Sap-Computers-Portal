<?php
// supplier/products.php
require_once __DIR__ . '/../includes/bootstrap.php';
Session::requireLogin();
if (Session::get('user_role') === 'seller') Helper::redirect('/sap-computers/products.php');

$pageTitle = 'My Products';
$activePage = 'products';
$supplierId = (int)Session::get('supplier_id');
$productModel = new ProductModel();
$products = $productModel->getBySupplier($supplierId);

require_once __DIR__ . '/../views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">My Products</div>
        <div class="page-header-sub"><?= count($products) ?> products supplied by your company</div>
    </div>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr><th>#</th><th>Product Name</th><th>Brand</th><th>Model</th><th>Category</th><th>Cost Price</th><th>Selling Price</th><th>Stock</th></tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td><strong><?= Helper::e($p['product_name']) ?></strong></td>
                    <td><?= Helper::e($p['brand']) ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($p['model']) ?></td>
                    <td><span class="badge bg-info"><?= Helper::e($p['category_name']) ?></span></td>
                    <td><?= Helper::formatCurrency($p['cost_price']) ?></td>
                    <td style="color:var(--success)"><?= Helper::formatCurrency($p['selling_price']) ?></td>
                    <td><?= Helper::stockBadge((int)$p['total_stock'],(int)$p['reorder_level']) ?> <small style="color:var(--text-muted)">(<?= $p['total_stock'] ?>)</small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../views/layouts/footer.php'; ?>
