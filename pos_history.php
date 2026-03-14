<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Sales History';
$activePage = 'pos_history';
$saleModel   = new SaleModel();
$branchModel = new BranchModel();

// view single sale detail
if (isset($_GET['view'])) {
    $sale_id = (int)$_GET['view'];
    $sale = $saleModel->getById($sale_id);
    $items = $saleModel->getItems($sale_id);
    $pageTitle = 'Sale Detail — ' . ($sale['sale_number'] ?? '');
    require_once __DIR__ . '/views/layouts/header.php';
    ?>
    <div class="page-header">
        <div>
            <div class="page-header-title"><?= Helper::e($sale['sale_number']) ?></div>
            <div class="page-header-sub">Sales transaction detail</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/sap-computers/pos_history.php" class="btn-ghost"><i class="bi bi-arrow-left"></i> Back</a>
            <button class="btn-cyan" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print</button>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-info-circle-fill"></i> Sale Info</div></div>
                <div class="content-card-body">
                    <?php
                    $info = [
                        'Sale Number' => $sale['sale_number'],
                        'Branch' => $sale['branch_name'],
                        'Created By' => $sale['created_by_name'],
                        'Created At' => Helper::formatDateTime($sale['created_at']),
                    ];
                    foreach ($info as $label => $val):
                    ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--navy-border);">
                        <span style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?= $label ?></span>
                        <span style="font-size:13px;font-weight:500;"><?= Helper::e($val) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:16px;padding:12px;background:var(--cyan-dim);border-radius:8px;text-align:center;">
                        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;">Total Amount</div>
                        <div style="font-size:22px;font-weight:800;font-family:'Syne',sans-serif;color:var(--cyan);"><?= Helper::formatCurrency($sale['total_amount']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-list-ul"></i> Items (<?= count($items) ?>)</div></div>
                <div style="overflow-x:auto;">
                    <table class="table-dark-custom">
                        <thead><tr><th>#</th><th>Product</th><th>Brand</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                            <td><strong><?= Helper::e($item['product_name']) ?></strong></td>
                            <td><?= Helper::e($item['brand']) ?></td>
                            <td><strong style="color:var(--cyan)"><?= $item['quantity'] ?></strong></td>
                            <td><?= Helper::formatCurrency($item['unit_price']) ?></td>
                            <td style="color:var(--success)"><?= Helper::formatCurrency($item['total_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--navy);">
                            <td colspan="5" style="text-align:right;font-weight:700;color:var(--text-muted);">GRAND TOTAL</td>
                            <td style="color:var(--cyan);font-weight:800;font-size:15px;"><?= Helper::formatCurrency($sale['total_amount']) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/views/layouts/footer.php';
    exit;
}

// listing
$filters = [
    'branch_id' => (int)($_GET['branch_id'] ?? 0),
];

$sales    = $saleModel->getAll($filters);
$branches = $branchModel->getActive();

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Sales History</div>
        <div class="page-header-sub"><?= count($sales) ?> records</div>
    </div>
</div>

<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <select name="branch_id" class="form-select-dark" style="width:180px;">
            <option value="">All Branches</option>
            <?php foreach($branches as $b): ?>
            <option value="<?= $b['branch_id'] ?>" <?= $filters['branch_id']==$b['branch_id']?'selected':'' ?>><?= Helper::e($b['branch_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-cyan"><i class="bi bi-funnel-fill"></i> Filter</button>
        <a href="/sap-computers/pos_history.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr><th>Sale #</th><th>Branch</th><th>Date</th><th>Amount</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><span class="text-cyan"><?= Helper::e($s['sale_number']) ?></span></td>
                    <td><?= Helper::e($s['branch_name']) ?></td>
                    <td><?= Helper::formatDateTime($s['created_at']) ?></td>
                    <td style="color:var(--success);font-weight:600;"><?= Helper::formatCurrency($s['total_amount']) ?></td>
                    <td>
                        <a href="/sap-computers/pos_history.php?view=<?= $s['sale_id'] ?>" class="action-btn view"><i class="bi bi-eye-fill"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/views/layouts/footer.php';
?>