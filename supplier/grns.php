<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Session::requireLogin();
if (Session::get('user_role') === 'seller') Helper::redirect('/sap-computers/grn.php');

$pageTitle = 'GRN History';
$activePage = 'grn';
$supplierId = (int)Session::get('supplier_id');
$grnModel = new GRNModel();
$grns = $grnModel->getBySupplier($supplierId);
$totalValue = array_sum(array_column($grns, 'total_amount'));

require_once __DIR__ . '/../views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">GRN History</div>
        <div class="page-header-sub"><?= count($grns) ?> goods received notes for your company</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-receipt-cutoff"></i></div>
            <div><div class="stat-value"><?= count($grns) ?></div><div class="stat-label">Total GRNs</div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-currency-exchange"></i></div>
            <div><div class="stat-value" style="font-size:16px;"><?= Helper::formatCurrency($totalValue) ?></div><div class="stat-label">Total Value</div></div>
        </div>
    </div>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr><th>GRN #</th><th>Branch</th><th>Invoice #</th><th>Date</th><th>Amount</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($grns as $g): ?>
                <tr>
                    <td class="text-cyan"><?= Helper::e($g['grn_number']) ?></td>
                    <td><?= Helper::e($g['branch_name']) ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($g['invoice_number'] ?: '—') ?></td>
                    <td><?= Helper::formatDate($g['grn_date']) ?></td>
                    <td style="color:var(--success);font-weight:600;"><?= Helper::formatCurrency($g['total_amount']) ?></td>
                    <td><a href="/sap-computers/grn.php?view=<?= $g['grn_id'] ?>" class="action-btn view"><i class="bi bi-eye-fill"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../views/layouts/footer.php'; ?>
