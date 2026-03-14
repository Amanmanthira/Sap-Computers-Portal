<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'GRN Management';
$activePage = 'grn';
$grnModel     = new GRNModel();
$supModel     = new SupplierModel();
$branchModel  = new BranchModel();
$productModel = new ProductModel();

// View a single GRN
if (isset($_GET['view'])) {
    $grn_id = (int)$_GET['view'];
    $grn = $grnModel->findById($grn_id);
    $items = $grnModel->getItems($grn_id);
    $pageTitle = 'GRN Detail — ' . ($grn['grn_number'] ?? '');
    require_once __DIR__ . '/views/layouts/header.php';
    ?>
    <div class="page-header">
        <div>
            <div class="page-header-title"><?= Helper::e($grn['grn_number']) ?></div>
            <div class="page-header-sub">Goods Received Note Detail</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/sap-computers/grn.php" class="btn-ghost"><i class="bi bi-arrow-left"></i> Back</a>
            <button class="btn-cyan" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print</button>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-info-circle-fill"></i> GRN Info</div></div>
                <div class="content-card-body">
                    <?php
                    $info = [
                        'GRN Number' => $grn['grn_number'],
                        'Supplier' => $grn['supplier_name'],
                        'Branch' => $grn['branch_name'],
                        'Invoice #' => $grn['invoice_number'] ?: '—',
                        'GRN Date' => Helper::formatDate($grn['grn_date']),
                        'Created By' => $grn['created_by_name'],
                        'Created At' => Helper::formatDateTime($grn['created_at']),
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
                        <div style="font-size:22px;font-weight:800;font-family:'Syne',sans-serif;color:var(--cyan);"><?= Helper::formatCurrency($grn['total_amount']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="content-card">
                <div class="content-card-header"><div class="content-card-title"><i class="bi bi-list-ul"></i> Items (<?= count($items) ?>)</div></div>
                <div style="overflow-x:auto;">
                    <table class="table-dark-custom">
                        <thead><tr><th>#</th><th>Product</th><th>Brand</th><th>Qty</th><th>Unit Cost</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                            <td><strong><?= Helper::e($item['product_name']) ?></strong></td>
                            <td><?= Helper::e($item['brand']) ?></td>
                            <td><strong style="color:var(--cyan)"><?= $item['quantity'] ?></strong></td>
                            <td><?= Helper::formatCurrency($item['unit_cost']) ?></td>
                            <td style="color:var(--success)"><?= Helper::formatCurrency($item['total_cost']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--navy);">
                            <td colspan="5" style="text-align:right;font-weight:700;color:var(--text-muted);">GRAND TOTAL</td>
                            <td style="color:var(--cyan);font-weight:800;font-size:15px;"><?= Helper::formatCurrency($grn['total_amount']) ?></td>
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

// Handle POST: Create GRN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $header = [
        'supplier_id'    => (int)$_POST['supplier_id'],
    'branch_id'      => 1, // MAIN BRANCH (Gampaha) forced
        'invoice_number' => Helper::sanitize($_POST['invoice_number'] ?? ''),
        'grn_date'       => $_POST['grn_date'] ?? date('Y-m-d'),
        'notes'          => Helper::sanitize($_POST['notes'] ?? ''),
        'created_by'     => Session::get('user_id'),
    ];

    $rawItems = $_POST['items'] ?? [];
    $items = [];
    foreach ($rawItems as $item) {
        if (!empty($item['product_id']) && !empty($item['quantity'])) {
            $qty  = (int)$item['quantity'];
            $cost = (float)$item['unit_cost'];
            $items[] = [
                'product_id' => (int)$item['product_id'],
                'quantity'   => $qty,
                'unit_cost'  => $cost,
                'total_cost' => $qty * $cost,
            ];
        }
    }

    if (empty($items)) {
        Session::setFlash('error', 'Please add at least one product item.');
    } else {
        $id = $grnModel->create($header, $items);
        if ($id) {
            Session::setFlash('success', 'GRN created successfully!');
            Helper::redirect('/sap-computers/grn.php?view=' . $id);
        } else {
            Session::setFlash('error', 'Failed to create GRN. Please try again.');
        }
    }
    Helper::redirect('/sap-computers/grn.php');
}

// Filters
$filters = [
    'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
    'branch_id'   => (int)($_GET['branch_id'] ?? 0),
    'date_from'   => $_GET['date_from'] ?? '',
    'date_to'     => $_GET['date_to'] ?? '',
];

$grns       = $grnModel->getAll(array_filter($filters));
$suppliers  = $supModel->getActive();
$branches   = $branchModel->getActive();
$products   = $productModel->getAll(['status' => 'active']);

// Build product options HTML for JS
$productOptions = '';
foreach ($products as $p) {
    $productOptions .= '<option value="' . $p['product_id'] . '" data-cost="' . $p['cost_price'] . '" data-price="' . $p['selling_price'] . '">' .
        htmlspecialchars($p['product_name'] . ' — ' . $p['brand'] . ' ' . $p['model']) .
        '</option>';
}

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">GRN Management</div>
        <div class="page-header-sub"><?= count($grns) ?> goods received notes</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#grnModal">
        <i class="bi bi-plus-lg"></i> Create GRN
    </button>
</div>

<!-- Filter Bar -->
<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <select name="supplier_id" class="form-select-dark" style="width:160px;">
            <option value="">All Suppliers</option>
            <?php foreach($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>" <?= $filters['supplier_id']==$s['supplier_id']?'selected':'' ?>><?= Helper::e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="branch_id" class="form-select-dark" style="width:160px;">
            <option value="">All Branches</option>
            <?php foreach($branches as $b): ?>
            <option value="<?= $b['branch_id'] ?>" <?= $filters['branch_id']==$b['branch_id']?'selected':'' ?>><?= Helper::e($b['branch_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" class="form-control-dark" style="width:150px;" value="<?= Helper::e($filters['date_from']) ?>">
        <input type="date" name="date_to" class="form-control-dark" style="width:150px;" value="<?= Helper::e($filters['date_to']) ?>">
        <button type="submit" class="btn-cyan"><i class="bi bi-funnel-fill"></i> Filter</button>
        <a href="/sap-computers/grn.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>GRN #</th><th>Supplier</th><th>Branch</th><th>Invoice #</th>
                    <th>Date</th><th>Amount</th><th>Created By</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($grns as $g): ?>
                <tr>
                    <td><span class="text-cyan"><?= Helper::e($g['grn_number']) ?></span></td>
                    <td><?= Helper::e($g['supplier_name']) ?></td>
                    <td><?= Helper::e($g['branch_name']) ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($g['invoice_number'] ?: '—') ?></td>
                    <td><?= Helper::formatDate($g['grn_date']) ?></td>
                    <td style="color:var(--success);font-weight:600;"><?= Helper::formatCurrency($g['total_amount']) ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($g['created_by_name']) ?></td>
                    <td>
                        <a href="/sap-computers/grn.php?view=<?= $g['grn_id'] ?>" class="action-btn view"><i class="bi bi-eye-fill"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create GRN Modal -->
<div class="modal fade modal-dark" id="grnModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New GRN</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/sap-computers/grn.php">
                <div class="modal-body">
                    <!-- Header -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label-dark">Supplier *</label>
                            <select name="supplier_id" class="form-select-dark w-100" required>
                                <option value="">Select supplier…</option>
                                <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['supplier_id'] ?>"><?= Helper::e($s['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                       <div class="col-md-3">
    <label class="form-label-dark">Branch</label>
    <input type="text" class="form-control-dark w-100" value="Main Branch (Gampaha)" readonly>
    <input type="hidden" name="branch_id" value="1">
</div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control-dark w-100" placeholder="Supplier invoice #">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">GRN Date *</label>
                            <input type="date" name="grn_date" class="form-control-dark w-100" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label-dark">Notes</label>
                            <textarea name="notes" class="form-control-dark w-100" rows="2" placeholder="Any notes…"></textarea>
                        </div>
                    </div>

                    <!-- Items -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.06em;">GRN Items</div>
                        <button type="button" class="btn-outline-cyan" onclick="addGRNItem()"><i class="bi bi-plus-lg"></i> Add Item</button>
                    </div>
                    <div id="grnItems"></div>

                    <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                        <div style="background:var(--cyan-dim);border:1px solid var(--cyan);border-radius:8px;padding:12px 24px;text-align:right;">
                            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;">Grand Total</div>
                            <div id="grnGrandTotal" style="font-size:20px;font-weight:800;font-family:'Syne',sans-serif;color:var(--cyan);">Rs. 0.00</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cyan"><i class="bi bi-save-fill"></i> Save GRN & Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
window.productOptions = ' . json_encode($productOptions) . ';

let grnItemCount = 0;

// Add first item when modal opens
document.getElementById("grnModal").addEventListener("shown.bs.modal", function () {
    if (document.getElementById("grnItems").children.length === 0) {
        addGRNItem();
    }
});

function addGRNItem() {
    grnItemCount++;

    const container = document.getElementById("grnItems");
    const row = document.createElement("div");
    row.className = "grn-item-row mb-3";
    row.id = "grnRow_" + grnItemCount;

    row.innerHTML = `
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label-dark">Product</label>
                <select name="items[${grnItemCount}][product_id]" 
                    class="form-select-dark w-100 product-select" 
                    required onchange="fillUnitCost(this, ${grnItemCount})">
                    <option value="">Select product…</option>
                    ${window.productOptions || ""}
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label-dark">Quantity</label>
                <input type="number" 
                    name="items[${grnItemCount}][quantity]" 
                    class="form-control-dark w-100 qty-input"
                    min="1" value="1" required 
                    onchange="calcRowTotal(${grnItemCount})">
            </div>

            <div class="col-md-2">
                <label class="form-label-dark">Unit Cost (Rs.)</label>
                <input type="number" 
                    name="items[${grnItemCount}][unit_cost]" 
                    class="form-control-dark w-100 cost-input"
                    id="unitCost_${grnItemCount}" 
                    min="0" step="0.01" value="0.00" required 
                    onchange="calcRowTotal(${grnItemCount})">
            </div>

            <div class="col-md-2">
                <label class="form-label-dark">Total</label>
                <input type="text" 
                    id="rowTotal_${grnItemCount}" 
                    class="form-control-dark w-100" 
                    readonly value="Rs. 0.00">
            </div>

            <div class="col-md-2">
                <button type="button" class="btn-ghost w-100" onclick="removeGRNItem(${grnItemCount})">
                    <i class="bi bi-trash3"></i> Remove
                </button>
            </div>
        </div>
    `;

    container.appendChild(row);
}

function removeGRNItem(id) {
    const rowEl = document.getElementById("grnRow_" + id);
    if (rowEl) rowEl.remove();
    calcGRNTotal();
}

function fillUnitCost(select, rowId) {
    const option = select.options[select.selectedIndex];
    const costPrice = option.dataset.cost || 0;

    const costInput = document.getElementById("unitCost_" + rowId);
    if (costInput) {
        costInput.value = costPrice;
        calcRowTotal(rowId);
    }
}

function calcRowTotal(rowId) {
    const qty = document.querySelector(`[name="items[${rowId}][quantity]"]`).value || 0;
    const cost = document.getElementById("unitCost_" + rowId).value || 0;

    const total = qty * cost;

    document.getElementById("rowTotal_" + rowId).value = "Rs. " + total.toFixed(2);

    calcGRNTotal();
}

function calcGRNTotal() {
    let grandTotal = 0;

    document.querySelectorAll(".qty-input").forEach((qtyInput) => {
        const row = qtyInput.closest(".grn-item-row");
        const costInput = row.querySelector(".cost-input");

        const qty = parseFloat(qtyInput.value) || 0;
        const cost = parseFloat(costInput.value) || 0;

        grandTotal += qty * cost;
    });

    document.getElementById("grnGrandTotal").innerText = "Rs. " + grandTotal.toFixed(2);
}
</script>';

require_once __DIR__ . "/views/layouts/footer.php";
?>
