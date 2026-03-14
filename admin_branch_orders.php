<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Branch Orders';
$activePage = 'orders_admin';
$orderModel = new BranchOrderModel();
$branchModel = new BranchModel();

// ajax update item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action']==='update_item') {
    ob_clean(); header('Content-Type: application/json');
    try {
        $data = json_decode(file_get_contents('php://input'),true);
        if(!$data) throw new Exception('Invalid');
        $ok = $orderModel->updateItemStatus((int)$data['item_id'], $data['status']);
        if($ok) {
            $orderModel->refreshOrderStatus((int)$data['order_id']);
            echo json_encode(['success'=>true]);
        } else throw new Exception('Update failed');
    } catch(Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// view single order
if (isset($_GET['view'])) {
    $orderId = (int)$_GET['view'];
    $order = $orderModel->getById($orderId);
    $items = $orderModel->getItems($orderId);
    require_once __DIR__ . '/views/layouts/header.php';
    ?>
    <div class="page-header">
        <div>
            <div class="page-header-title">Order #<?= $order['order_id'] ?></div>
            <div class="page-header-sub">Branch: <?= Helper::e($order['branch_name']) ?> | Status: <?= Helper::e($order['status']) ?></div>
        </div>
        <div><a href="/sap-computers/admin_branch_orders.php" class="btn-ghost"><i class="bi bi-arrow-left"></i> Back</a></div>
    </div>
    <div class="content-card">
        <div class="content-card-body">
            <table class="table-dark-custom w-100">
                <thead><tr><th>Product</th><th>Qty</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($items as $it): ?>
                <tr id="item_<?= $it['id'] ?>">
                    <td><?= Helper::e($it['product_name']) ?></td>
                    <td><?= $it['quantity'] ?></td>
                    <td class="status_cell"><?= Helper::e($it['status']) ?></td>
                    <td>
                        <?php if($it['status']=='pending'): ?>
                        <button onclick="update(<?= $it['id'] ?>,<?= $orderId ?>,'accepted')" class="btn btn-sm btn-success">Accept</button>
                        <button onclick="update(<?= $it['id'] ?>,<?= $orderId ?>,'rejected')" class="btn btn-sm btn-danger">Reject</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    async function update(itemId,orderId,status){
        const res=await fetch('admin_branch_orders.php?action=update_item',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({item_id:itemId,order_id:orderId,status})});
        const out=await res.json();
        if(out.success){
            document.querySelector('#item_'+itemId+' .status_cell').innerText=status;
            document.querySelector('#item_'+itemId+' td:last-child').innerHTML='';
        } else alert(out.message);
    }
    </script>
    <?php
    require_once __DIR__ . '/views/layouts/footer.php';
    exit;
}

// list orders
$filters=[
    'status'=>$_GET['status']??'',
];
$orders = $orderModel->getAll($filters);
$branches = $branchModel->getActive();
require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">All Branch Orders</div>
        <div class="page-header-sub"><?= count($orders) ?> records</div>
    </div>
</div>

<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <select name="status" class="form-select-dark" style="width:180px;">
            <option value="">All Status</option>
            <option value="pending" <?=($filters['status']=='pending'?'selected':'')?>>Pending</option>
            <option value="partial" <?=($filters['status']=='partial'?'selected':'')?>>Partial</option>
            <option value="completed" <?=($filters['status']=='completed'?'selected':'')?>>Completed</option>
            <option value="cancelled" <?=($filters['status']=='cancelled'?'selected':'')?>>Cancelled</option>
        </select>
        <button type="submit" class="btn-cyan"><i class="bi bi-funnel-fill"></i> Filter</button>
        <a href="/sap-computers/admin_branch_orders.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>ID</th><th>Branch</th><th>Requested By</th><th>Items</th><th>Date</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($orders as $o): ?>
            <tr>
                <td><?= $o['order_id'] ?></td>
                <td><?= Helper::e($o['branch_name']) ?></td>
                <td><?= Helper::e($o['requested_by']) ?></td>
                <td><?= (int)($o['item_count']??0) ?></td>
                <td><?= Helper::formatDateTime($o['created_at']) ?></td>
                <td><?= Helper::e($o['status']) ?></td>
                <td>
                    <a href="/sap-computers/admin_branch_orders.php?view=<?= $o['order_id'] ?>" class="action-btn view" title="View order details">
                        <i class="bi bi-eye-fill"></i> View
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>