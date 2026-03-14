<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Inventory Movements';
$activePage = 'movements';
$stockModel  = new StockModel();
$branchModel = new BranchModel();

$filters = [
    'branch_id' => (int)($_GET['branch_id'] ?? 0),
];

$movements = $stockModel->getMovements(array_filter($filters));
$branches  = $branchModel->getActive();

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Inventory Movements</div>
        <div class="page-header-sub">Complete history of all stock changes</div>
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
        <a href="/sap-computers/movements.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr><th>Date</th><th>Product</th><th>Branch</th><th>Type</th><th>Quantity</th><th>Reference</th><th>By</th></tr>
            </thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= Helper::formatDateTime($m['created_at']) ?></td>
                    <td>
                        <strong><?= Helper::e($m['product_name']) ?></strong>
                        <div style="font-size:11px;color:var(--text-muted)"><?= Helper::e($m['brand']) ?></div>
                    </td>
                    <td><?= Helper::e($m['branch_name']) ?></td>
                    <td>
                        <?php
                        $typeColors = ['GRN'=>'bg-success','Sale'=>'bg-danger','Adjustment'=>'bg-warning','Transfer'=>'bg-info'];
                        $cls = $typeColors[$m['type']] ?? 'bg-info';
                        ?>
                        <span class="badge <?= $cls ?>"><?= Helper::e($m['type']) ?></span>
                    </td>
                    <td>
                        <strong style="font-size:15px;color:<?= in_array($m['type'],['GRN','Transfer'])? 'var(--success)' : 'var(--danger)' ?>">
                            <?= in_array($m['type'],['GRN'])? '+' : (in_array($m['type'],['Sale'])? '-' : '') ?><?= $m['quantity'] ?>
                        </strong>
                    </td>
                    <td style="color:var(--text-muted)"><?= $m['reference_id'] ? '#'.$m['reference_id'] : '—' ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($m['user_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
