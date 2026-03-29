<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Returns Management';
$activePage = 'returns';

$returnModel = new ReturnModel();
$productModel = new ProductModel();

// Handle POST: Create return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    error_log("Return POST received. POST data: " . json_encode($_POST));
    
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        try {
            $create_data = [
                'product_id' => (int)$_POST['product_id'],
                'order_id' => !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null,
                'sale_id' => !empty($_POST['sale_id']) ? (int)$_POST['sale_id'] : null,
                'quantity' => !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1,
                'return_reason' => Helper::sanitize($_POST['return_reason'] ?? ''),
                'return_date' => date('Y-m-d'),
                'created_by' => Session::get('user_id'),
            ];
            
            error_log("Creating return with data: " . json_encode($create_data));
            
            $result = $returnModel->create($create_data);
            
            if ($result) {
                Session::setFlash('success', 'Return request created successfully!');
                Helper::redirect('/sap-computers/returns.php?view=' . $result);
            } else {
                Session::setFlash('error', 'Failed to create return - check server logs');
                Helper::redirect('/sap-computers/returns.php');
            }
        } catch (Exception $e) {
            error_log("Return creation exception: " . $e->getMessage());
            Session::setFlash('error', 'Error: ' . $e->getMessage());
            Helper::redirect('/sap-computers/returns.php');
        }
    }
}

// Handle POST: Approve return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'approve') {
    $return_id = (int)($_GET['return_id'] ?? 0);
    if ($returnModel->approve($return_id, Session::get('user_id'))) {
        Session::setFlash('success', 'Return approved and stock updated!');
    } else {
        Session::setFlash('error', 'Failed to approve return');
    }
    Helper::redirect('/sap-computers/returns.php?view=' . $return_id);
}

// Handle POST: Reject return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'reject') {
    $return_id = (int)($_GET['return_id'] ?? 0);
    $reason = Helper::sanitize($_POST['rejection_reason'] ?? '');
    if ($returnModel->reject($return_id, Session::get('user_id'), $reason)) {
        Session::setFlash('success', 'Return rejected');
    } else {
        Session::setFlash('error', 'Failed to reject return');
    }
    Helper::redirect('/sap-computers/returns.php?view=' . $return_id);
}

// Handle POST: Mark as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'complete') {
    $return_id = (int)($_GET['return_id'] ?? 0);
    if ($returnModel->complete($return_id, Session::get('user_id'))) {
        Session::setFlash('success', 'Return marked as completed');
    } else {
        Session::setFlash('error', 'Failed to complete return');
    }
    Helper::redirect('/sap-computers/returns.php?view=' . $return_id);
}

// View single return
if (isset($_GET['view'])) {
    $return_id = (int)$_GET['view'];
    $return = $returnModel->findById($return_id);
    
    $pageTitle = 'Return Detail — ' . ($return['return_number'] ?? '');
    require_once __DIR__ . '/views/layouts/header.php';
    ?>

    <div class="container-fluid" style="padding: 20px; background: var(--navy-dark); min-height: 100vh;">
        <div class="row mb-4">
            <div class="col">
                <h2 style="color: var(--cyan);">
                    <i class="bi bi-arrow-repeat"></i> Return Details
                </h2>
            </div>
            <div class="col text-end">
                <a href="/sap-computers/returns.php" class="btn btn-sm btn-outline-cyan">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <?php if (Session::getFlash()): ?>
            <div class="alert alert-<?= Session::getFlash('type') === 'error' ? 'danger' : 'success' ?>">
                <i class="bi bi-<?= Session::getFlash('type') === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <?= Session::getFlash('message') ?>
            </div>
        <?php endif; ?>

        <?php if ($return): ?>
            <div style="background: var(--navy-mid); padding: 20px; border-radius: 12px; border: 1px solid var(--navy-border); margin-bottom: 20px;">
                <div class="row mb-3">
                    <div class="col">
                        <strong style="color: var(--cyan);">Return Number:</strong> <?= Helper::e($return['return_number']) ?>
                    </div>
                    <div class="col">
                        <strong style="color: var(--cyan);">Status:</strong>
                        <?php
                        $statusColors = [
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'completed' => 'info'
                        ];
                        $statusColor = $statusColors[$return['return_status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $statusColor ?>">
                            <?= ucfirst($return['return_status']) ?>
                        </span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <strong style="color: var(--cyan);">Product:</strong> <?= Helper::e($return['product_name']) ?><br>
                        <small class="text-muted"><?= Helper::e($return['brand'] . ' ' . $return['model']) ?></small>
                    </div>
                    <div class="col">
                        <strong style="color: var(--cyan);">Quantity:</strong> <?= $return['quantity'] ?>
                    </div>
                    <div class="col">
                        <strong style="color: var(--cyan);">Return Date:</strong> <?= Helper::formatDate($return['return_date']) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <strong style="color: var(--cyan);">Return Reason:</strong><br>
                        <?= Helper::e($return['return_reason'] ?? 'Not specified') ?>
                    </div>
                </div>

                <?php if ($return['purchase_date']): ?>
                    <div class="alert alert-info">
                        <strong>Purchase Date:</strong> <?= Helper::formatDate($return['purchase_date']) ?> 
                        (<?php
                        $daysSince = (int)((time() - strtotime($return['purchase_date'])) / (60 * 60 * 24));
                        echo $daysSince . " days ago";
                        ?>
                        )
                        <br>
                        <?php if ($daysSince <= 3): ?>
                            <strong style="color: var(--success);">✓ Within 3-day return window</strong>
                        <?php else: ?>
                            <strong style="color: var(--danger);">✕ Outside 3-day return window</strong>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action buttons based on status -->
            <?php if ($return['return_status'] === 'pending'): ?>
                <div class="row" style="gap: 10px; margin-bottom: 20px;">
                    <div class="col-auto">
                        <form method="POST" action="/sap-computers/returns.php?return_id=<?= $return_id ?>&action=approve" style="display: inline;">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Approve Return
                            </button>
                        </form>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle"></i> Reject Return
                        </button>
                    </div>
                </div>
            <?php elseif ($return['return_status'] === 'approved'): ?>
                <form method="POST" action="/sap-computers/returns.php?return_id=<?= $return_id ?>&action=complete">
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-check"></i> Mark as Completed
                    </button>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-danger">Return not found</div>
        <?php endif; ?>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--navy-mid); border: 1px solid var(--navy-border);">
                <form method="POST" action="/sap-computers/returns.php?return_id=<?= $return_id ?>&action=reject">
                    <div class="modal-header" style="border-color: var(--navy-border);">
                        <h5 class="modal-title" style="color: var(--cyan);">Reject Return</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label-dark">Reason for Rejection *</label>
                        <textarea name="rejection_reason" class="form-control-dark" rows="4" required placeholder="Explain why this return is being rejected..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/views/layouts/footer.php';
    exit;
}

// List all returns
$returns = $returnModel->getAll();

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="container-fluid" style="padding: 20px; background: var(--navy-dark); min-height: 100vh;">
    <div class="row mb-4">
        <div class="col">
            <h2 style="color: var(--cyan);">
                <i class="bi bi-arrow-repeat"></i> Returns Management
            </h2>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-cyan" data-bs-toggle="modal" data-bs-target="#newReturnModal">
                <i class="bi bi-plus-circle"></i> New Return Request
            </button>
        </div>
    </div>

    <?php if (Session::getFlash()): ?>
        <div class="alert alert-<?= Session::getFlash('type') === 'error' ? 'danger' : 'success' ?>">
            <i class="bi bi-<?= Session::getFlash('type') === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
            <?= Session::getFlash('message') ?>
        </div>
    <?php endif; ?>

    <!-- Returns Table -->
    <div style="background: var(--navy-mid); border-radius: 12px; border: 1px solid var(--navy-border); overflow: hidden;">
        <table class="table table-dark" style="margin: 0;">
            <thead style="background: var(--navy-dark);">
                <tr>
                    <th style="color: var(--cyan);">Return #</th>
                    <th style="color: var(--cyan);">Product</th>
                    <th style="color: var(--cyan);">Qty</th>
                    <th style="color: var(--cyan);">Return Date</th>
                    <th style="color: var(--cyan);">Status</th>
                    <th style="color: var(--cyan);">Created By</th>
                    <th style="color: var(--cyan); text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returns as $r): ?>
                    <tr>
                        <td><strong><?= Helper::e($r['return_number']) ?></strong></td>
                        <td><?= Helper::e($r['product_name']) ?></td>
                        <td><?= $r['quantity'] ?></td>
                        <td><?= Helper::formatDate($r['return_date']) ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'completed' => 'info'
                            ];
                            $statusColor = $statusColors[$r['return_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $statusColor ?>">
                                <?= ucfirst($r['return_status']) ?>
                            </span>
                        </td>
                        <td><?= Helper::e($r['created_by_name']) ?></td>
                        <td style="text-align: center;">
                            <a href="/sap-computers/returns.php?view=<?= $r['return_id'] ?>" class="btn btn-sm btn-outline-cyan">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Return Modal -->
<div class="modal fade" id="newReturnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: var(--navy-mid); border: 1px solid var(--navy-border);">
            <form method="POST" action="/sap-computers/returns.php">
                <div class="modal-header" style="border-color: var(--navy-border);">
                    <h5 class="modal-title" style="color: var(--cyan);">Create Return Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Search Customer *</label>
                        <input type="text" id="returnCustomerSearch" class="form-control-dark" placeholder="Enter customer name or phone..." autocomplete="off">
                        <div id="returnCustomerDropdown" style="display:none;max-height:200px;overflow-y:auto;border:1px solid var(--cyan);border-radius:8px;background:#0f172a;margin-top:8px;"></div>
                    </div>

                    <div id="returnPurchaseSection" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label-dark">Select Purchase *</label>
                            <div id="returnPurchaseList" style="display:grid;gap:10px;"></div>
                        </div>
                    </div>

                    <div id="returnItemsSection" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label-dark">Select Item to Return *</label>
                            <div id="returnItemList" style="display:grid;gap:10px;"></div>
                        </div>
                    </div>

                    <div id="returnReasonSection" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label-dark">Quantity to Return *</label>
                            <input type="number" id="returnQuantity" name="quantity" class="form-control-dark" min="1" value="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-dark">Reason for Return *</label>
                            <textarea id="returnReason" name="return_reason" class="form-control-dark" rows="3" required placeholder="Defective, changed mind, wrong item, etc..."></textarea>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" id="return_order_id" name="order_id" value="">
                        <input type="hidden" id="return_sale_id" name="sale_id" value="">
                        <input type="hidden" id="return_product_id" name="product_id" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-cyan" disabled id="returnSubmitBtn">
                        <i class="bi bi-plus-circle"></i> Create Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load customers for returns
let allCustomersForReturn = [];

document.getElementById('returnCustomerSearch').addEventListener('focus', function() {
    if (allCustomersForReturn.length === 0) {
        fetch('/sap-computers/ajax/search_customer_purchases.php?q=')
            .then(r => r.json())
            .then(data => {
                allCustomersForReturn = data.results || [];
                displayReturnCustomers(allCustomersForReturn);
            });
    } else {
        displayReturnCustomers(allCustomersForReturn);
    }
});

document.getElementById('returnCustomerSearch').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    const filtered = allCustomersForReturn.filter(c => 
        c.name.toLowerCase().includes(query) || c.phone.toLowerCase().includes(query)
    );
    displayReturnCustomers(filtered);
});

function displayReturnCustomers(customers) {
    const dropdown = document.getElementById('returnCustomerDropdown');
    if (!customers.length) {
        dropdown.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text-muted);">No customers found</div>';
    } else {
        dropdown.innerHTML = customers.map(c => `
            <div style="padding:12px;cursor:pointer;border-bottom:1px solid var(--navy-border);" 
                 onmouseover="this.style.background='var(--navy-dark)'" 
                 onmouseout="this.style.background=''" 
                 onclick="selectReturnCustomer(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                <strong style="color:var(--cyan)">${c.name}</strong><br>
                <small style="color:var(--text-muted)">${c.phone} • ${c.orders.length} purchase(s)</small>
            </div>
        `).join('');
    }
    dropdown.style.display = customers.length ? 'block' : 'none';
}

function selectReturnCustomer(customer) {
    document.getElementById('returnCustomerSearch').value = customer.name;
    document.getElementById('returnCustomerDropdown').style.display = 'none';
    
    // Show purchases
    const purchaseList = document.getElementById('returnPurchaseList');
    purchaseList.innerHTML = customer.orders.map((order, idx) => `
        <div style="padding:12px;background:var(--navy-dark);border:1px solid var(--navy-border);border-radius:8px;cursor:pointer;"
             onclick="selectReturnOrder(${JSON.stringify(order).replace(/"/g, '&quot;')}, ${JSON.stringify(customer).replace(/"/g, '&quot;')})">
            <strong style="color:var(--cyan)">${order.order_date}</strong> - ${order.items.length} item(s)
            <br><small style="color:var(--text-muted)">Total: Rs. ${parseFloat(order.total_amount).toLocaleString()}</small>
        </div>
    `).join('');
    
    document.getElementById('returnPurchaseSection').style.display = 'block';
}

function selectReturnOrder(order, customer) {
    const itemList = document.getElementById('returnItemList');
    itemList.innerHTML = order.items.map(item => `
        <div style="padding:12px;background:var(--navy-dark);border:1px solid var(--navy-border);border-radius:8px;cursor:pointer;"
             onclick="selectReturnItem(${item.product_id}, ${order.order_id || ''}, ${order.order_type === 'pos' ? order.order_id : 'null'}, '${item.product_name}')">
            <strong style="color:var(--cyan)">${item.product_name}</strong><br>
            <small style="color:var(--text-muted)">${item.brand} ${item.model} • Qty: ${item.quantity}</small>
        </div>
    `).join('');
    
    // Store order info
    document.getElementById('return_order_id').value = order.order_type === 'pos' ? '' : order.order_id;
    document.getElementById('return_sale_id').value = order.order_type === 'pos' ? order.order_id : '';
    
    document.getElementById('returnItemsSection').style.display = 'block';
}

function selectReturnItem(productId, orderId, saleId, productName) {
    document.getElementById('return_product_id').value = productId;
    document.getElementById('returnReasonSection').style.display = 'block';
    document.getElementById('returnSubmitBtn').disabled = false;
    
    alert('Item selected: ' + productName + '\nNow fill in the return details and submit.');
}
</script>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
