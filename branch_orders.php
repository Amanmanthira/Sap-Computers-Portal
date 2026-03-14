<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireLogin();

if (Session::get('user_role') !== 'staff') {
    Helper::redirect('/sap-computers/index.php');
}

$branchId     = Session::get('user_branch_id');
$orderModel   = new BranchOrderModel();
$productModel = new ProductModel();

// --- VIEW ORDER DETAIL LOGIC ---
$viewOrder = null;
if (isset($_GET['view'])) {
    $viewOrder = $orderModel->getById((int)$_GET['view']);
    // Security: Ensure staff can only view their own branch orders
    if (!$viewOrder || $viewOrder['branch_id'] != $branchId) {
        die("Order not found or access denied.");
    }
    $orderItems = $orderModel->getItems((int)$_GET['view']);
}

// --- AJAX SAVE HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save_order') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['items'])) throw new Exception('Your order cart is empty.');
        
        $items = array_map(fn($it) => [
            'product_id' => (int)$it['id'], 
            'quantity'   => (int)$it['qty']
        ], $data['items']);

        $notes   = trim($data['notes'] ?? '');
        $orderId = $orderModel->create($branchId, Session::get('user_id'), $items, $notes);

        echo json_encode(['success' => true, 'order_id' => $orderId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$products = $productModel->getAll(['status' => 'active']);
$myOrders = $orderModel->getByBranch($branchId);

require_once __DIR__ . '/views/layouts/header.php';
?>

<style>
    /* UI Styles */
    .order-container { display: grid; grid-template-columns: 1fr 350px; gap: 20px; align-items: start; }
    .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
    .p-card { background: #1e1e26; border: 1px solid #333; border-radius: 12px; padding: 15px; transition: 0.2s; cursor: pointer; }
    .p-card:hover { border-color: #007bff; transform: translateY(-3px); }
    .cart-sidebar { background: #1e1e26; border-radius: 12px; padding: 20px; position: sticky; top: 20px; border: 1px solid #333; }
    .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
    .status-pending { background: #3b2b00; color: #ffc107; }
    .status-approved { background: #1b3b1b; color: #28a745; }

    /* Invoice / Print Styles */
    .invoice-card { background: #fff; color: #000; padding: 40px; border-radius: 8px; max-width: 800px; margin: auto; }
    .invoice-header { border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
    
    @media print {
        body * { visibility: hidden; }
        .invoice-card, .invoice-card * { visibility: visible; }
        .invoice-card { position: absolute; left: 0; top: 0; width: 100%; padding: 0; }
        .no-print { display: none !important; }
    }
</style>

<?php if ($viewOrder): ?>
    <div class="mb-4 no-print">
        <a href="branch_orders.php" class="btn btn-outline-light btn-sm">&larr; Back to Catalog</a>
    </div>

    <div class="invoice-card">
        <div class="d-flex justify-content-between invoice-header">
            <div>
                <h2 class="fw-bold mb-0">SAP COMPUTERS</h2>
                <p class="mb-0 text-muted">Stock Requisition Note</p>
            </div>
            <div class="text-end">
                <h4 class="mb-0">Order #<?= $viewOrder['order_id'] ?></h4>
                <p class="mb-0"><?= date('M d, Y', strtotime($viewOrder['created_at'])) ?></p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <small class="text-muted d-block text-uppercase">Request From:</small>
                <strong>Branch ID: <?= $viewOrder['branch_id'] ?></strong><br>
                Staff: <?= Session::get('user_name') ?>
            </div>
            <div class="col-6 text-end">
                <small class="text-muted d-block text-uppercase">Status:</small>
                <span class="badge bg-dark"><?= strtoupper($viewOrder['status']) ?></span>
            </div>
        </div>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Item Description</th>
                    <th class="text-center" style="width: 80px;">Qty</th>
                    <th class="text-center" style="width: 120px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td><?= $item['product_name'] ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-center">
                        <?php
                        $s = $item['status'];
                        $cls = 'badge bg-secondary';
                        if ($s === 'accepted') $cls = 'badge bg-success';
                        if ($s === 'rejected') $cls = 'badge bg-danger';
                        if ($s === 'pending') $cls = 'badge bg-warning text-dark';
                        ?>
                        <span class="<?= $cls ?>"><?= ucfirst($s) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($viewOrder['notes'])): ?>
            <div class="mt-3 p-3 bg-light border">
                <small class="d-block text-muted">Notes:</small>
                <?= nl2br(Helper::e($viewOrder['notes'])) ?>
            </div>
        <?php endif; ?>

        <div class="mt-5 d-flex justify-content-between no-print">
            <button class="btn btn-primary" onclick="window.print()">Print Invoice</button>
            <p class="text-muted small">Generated on <?= date('Y-m-d H:i') ?></p>
        </div>
    </div>

<?php else: ?>
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0">Branch Inventory Request</h2>
            <p class="text-muted">Select products to restock your branch</p>
        </div>
        <input type="text" id="search" class="form-control w-25" placeholder="🔍 Search products...">
    </div>

    <div class="order-container mt-4">
        <div class="catalog-section">
            <div class="catalog-grid" id="productGrid">
                <?php foreach ($products as $p): ?>
                    <div class="p-card" onclick="addToCart(<?= $p['product_id'] ?>, '<?= addslashes($p['product_name']) ?>')">
                        <div class="brand-badge" style="font-size:0.7rem; color:#888;"><?= $p['brand'] ?? 'Generic' ?></div>
                        <div class="p-name" style="font-weight:600; color:#efefef;"><?= $p['product_name'] ?></div>
                        <div class="text-primary mt-2 small">+ Add to Order</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="cart-sidebar">
            <h5 class="fw-bold mb-3">New Request</h5>
            <div id="cartList" class="mb-3">
                <div class="text-center text-muted py-4">No items selected</div>
            </div>
            
            <div id="cartFooter" style="display:none;">
                <label class="small text-muted">Notes for Admin:</label>
                <textarea id="notes" class="form-control bg-dark text-white border-secondary mb-3" rows="3"></textarea>
                <button class="btn btn-primary w-100 py-2 fw-bold" onclick="submitOrder()">SUBMIT ORDER</button>
            </div>
        </div>
    </div>

    <?php if (!empty($myOrders)): ?>
    <div class="mt-5">
        <h4 class="fw-bold mb-3">Order History</h4>
        <div class="card bg-dark border-secondary">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myOrders as $o): ?>
                    <tr>
                        <td>#<?= $o['order_id'] ?></td>
                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                        <td><span class="status-pill status-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
                        <td>
                            <a href="?view=<?= $o['order_id'] ?>" class="btn btn-sm btn-outline-info">View & Print</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
let cart = [];

function addToCart(id, name) {
    let exists = cart.find(item => item.id === id);
    if (exists) { exists.qty++; } 
    else { cart.push({ id, name, qty: 1 }); }
    renderCart();
}

function updateQty(id, delta) {
    let item = cart.find(x => x.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) cart = cart.filter(x => x.id !== id);
    }
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartList');
    const footer = document.getElementById('cartFooter');
    if (cart.length === 0) {
        list.innerHTML = '<div class="text-center text-muted py-4">No items selected</div>';
        footer.style.display = 'none';
        return;
    }
    footer.style.display = 'block';
    list.innerHTML = cart.map(item => `
        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-2">
            <div class="small fw-bold text-truncate" style="max-width:150px">${item.name}</div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-dark p-1" style="width:25px" onclick="updateQty(${item.id}, -1)">-</button>
                <span>${item.qty}</span>
                <button class="btn btn-sm btn-dark p-1" style="width:25px" onclick="updateQty(${item.id}, 1)">+</button>
            </div>
        </div>
    `).join('');
}

async function submitOrder() {
    if (!confirm('Submit this order to warehouse?')) return;
    try {
        const res = await fetch('branch_orders.php?action=save_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: cart, notes: document.getElementById('notes').value })
        });
        const out = await res.json();
        if (out.success) { alert('Order submitted!'); location.reload(); }
        else alert(out.message);
    } catch (e) { alert('Error saving order'); }
}

if(document.getElementById('search')) {
    document.getElementById('search').addEventListener('input', e => {
        const v = e.target.value.toLowerCase();
        document.querySelectorAll('.p-card').forEach(c => {
            c.style.display = c.innerText.toLowerCase().includes(v) ? 'block' : 'none';
        });
    });
}
</script>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>