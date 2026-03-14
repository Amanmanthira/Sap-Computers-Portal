<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Session::requireLogin();
if (Session::get('user_role') === 'seller') {
    Helper::redirect('/sap-computers/index.php');
}

$pageTitle = 'Supplier Dashboard';
$activePage = 'dashboard';
$supplierId = (int)Session::get('supplier_id');

$supModel     = new SupplierModel();
$grnModel     = new GRNModel();
$productModel = new ProductModel();

$supplier = $supModel->findById($supplierId);
$stats    = $supModel->getSupplierStats($supplierId);
$products = $productModel->getBySupplier($supplierId);
$grns     = $grnModel->getBySupplier($supplierId);
$recentGRNs = array_slice($grns, 0, 5);

require_once __DIR__ . '/../views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Welcome, <?= Helper::e($supplier['supplier_name'] ?? 'Supplier') ?></div>
        <div class="page-header-sub">Supplier Portal — Read-only access to your products and orders</div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-box-seam-fill"></i></div>
            <div>
                <div class="stat-value"><?= $stats['total_products'] ?></div>
                <div class="stat-label">My Products</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="stat-value"><?= $stats['total_grns'] ?></div>
                <div class="stat-label">Total GRNs</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-currency-exchange"></i></div>
            <div>
                <div class="stat-value" style="font-size:16px;"><?= Helper::formatCurrency($stats['total_value']) ?></div>
                <div class="stat-label">Total Supplied Value</div>
            </div>
        </div>
    </div>
</div>

<!-- Supplier Info + Recent GRNs -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="content-card">
            <div class="content-card-header"><div class="content-card-title"><i class="bi bi-building"></i> Company Info</div></div>
            <div class="content-card-body">
                <?php
                $fields = [
                    'Company' => $supplier['supplier_name'],
                    'Contact' => $supplier['contact_person'],
                    'Phone'   => $supplier['phone'],
                    'Email'   => $supplier['email'],
                    'Reg. #'  => $supplier['company_registration'],
                    'Address' => $supplier['address'],
                ];
                foreach ($fields as $label => $val):
                ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--navy-border);">
                    <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?= $label ?></span>
                    <span style="font-size:13px;font-weight:500;text-align:right;max-width:60%;"><?= Helper::e($val ?: '—') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title"><i class="bi bi-receipt-cutoff"></i> Recent GRNs</div>
                <a href="/sap-computers/supplier/grns.php" class="btn-outline-cyan" style="font-size:12px;padding:5px 12px;">View All</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table-dark-custom">
                    <thead><tr><th>GRN #</th><th>Branch</th><th>Date</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentGRNs as $g): ?>
                    <tr>
                        <td class="text-cyan"><?= Helper::e($g['grn_number']) ?></td>
                        <td><?= Helper::e($g['branch_name']) ?></td>
                        <td><?= Helper::formatDate($g['grn_date']) ?></td>
                        <td style="color:var(--success)"><?= Helper::formatCurrency($g['total_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recentGRNs)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px;">No GRNs found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/layouts/footer.php'; ?>
