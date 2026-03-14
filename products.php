<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Product Management';
$activePage = 'products';
$model = new ProductModel();
$catModel = new CategoryModel();
$supModel = new SupplierModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $model->delete((int)$_POST['product_id']);
        Session::setFlash('success', 'Product deleted.');
        Helper::redirect('/sap-computers/products.php');
    }

    $d = [
        'product_name'  => Helper::sanitize($_POST['product_name'] ?? ''),
        'brand'         => Helper::sanitize($_POST['brand'] ?? ''),
        'model'         => Helper::sanitize($_POST['model'] ?? ''),
        'category_id'   => (int)$_POST['category_id'],
        'supplier_id'   => (int)$_POST['supplier_id'],
        'cost_price'    => (float)$_POST['cost_price'],
        'selling_price' => (float)$_POST['selling_price'],
        'reorder_level' => (int)$_POST['reorder_level'],
        'status'        => $_POST['status'] ?? 'active',
    ];

    // --- Server-side validation ---
    $errors = [];

    // Product name: required, 2–50 chars, letters/numbers/spaces/hyphens/dots
    if (empty($d['product_name'])) {
        $errors[] = 'Product name is required.';
    } elseif (strlen($d['product_name']) < 2 || strlen($d['product_name']) > 50) {
        $errors[] = 'Product name must be between 2 and 50 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9\s\-\.\/\(\)]+$/', $d['product_name'])) {
        $errors[] = 'Product name may only contain letters, numbers, spaces, hyphens and dots.';
    }

    // Brand: optional, 2–20 chars, letters/numbers/spaces only
    if (!empty($d['brand'])) {
        if (strlen($d['brand']) < 2 || strlen($d['brand']) > 20) {
            $errors[] = 'Brand must be between 2 and 20 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9\s\-]+$/', $d['brand'])) {
            $errors[] = 'Brand may only contain letters, numbers, spaces and hyphens.';
        }
    }

    // Model: optional, max 30 chars, alphanumeric/hyphens/dots/slashes
    if (!empty($d['model'])) {
        if (strlen($d['model']) > 30) {
            $errors[] = 'Model must not exceed 30 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9\s\-\.\/]+$/', $d['model'])) {
            $errors[] = 'Model may only contain letters, numbers, hyphens, dots and slashes.';
        }
    }

    // Category & Supplier: required
    if ($d['category_id'] <= 0) $errors[] = 'Please select a category.';
    if ($d['supplier_id'] <= 0) $errors[] = 'Please select a supplier.';

    // Cost price: required, must be > 0
    if ($d['cost_price'] <= 0) {
        $errors[] = 'Cost price must be greater than 0.';
    } elseif ($d['cost_price'] > 9999999) {
        $errors[] = 'Cost price is too large.';
    }

    // Selling price: required, must be >= cost price
    if ($d['selling_price'] <= 0) {
        $errors[] = 'Selling price must be greater than 0.';
    } elseif ($d['selling_price'] < $d['cost_price']) {
        $errors[] = 'Selling price cannot be less than cost price.';
    } elseif ($d['selling_price'] > 9999999) {
        $errors[] = 'Selling price is too large.';
    }

    // Reorder level: 0–999
    if ($d['reorder_level'] < 0 || $d['reorder_level'] > 999) {
        $errors[] = 'Reorder level must be between 0 and 999.';
    }

    if (!empty($errors)) {
        Session::setFlash('error', implode(' ', $errors));
        Helper::redirect('/sap-computers/products.php');
    }

    if ($action === 'create') { $model->create($d); Session::setFlash('success', 'Product added successfully.'); }
    elseif ($action === 'update') { $model->update((int)$_POST['product_id'], $d); Session::setFlash('success', 'Product updated.'); }
    Helper::redirect('/sap-computers/products.php');
}

// Filters
$filters = [
    'search'      => Helper::sanitize($_GET['search'] ?? ''),
    'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
    'category_id' => (int)($_GET['category_id'] ?? 0),
    'brand'       => Helper::sanitize($_GET['brand'] ?? ''),
];

$products   = $model->getAll(array_filter($filters));
$categories = $catModel->getAll();
$suppliers  = $supModel->getActive();
$brands     = $model->getBrands();

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Product Management</div>
        <div class="page-header-sub"><?= count($products) ?> products found</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="bi bi-plus-lg"></i> Add Product
    </button>
</div>

<!-- Filter Bar -->
<div class="content-card mb-4">
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search products…" class="form-control-dark" style="width:200px;" value="<?= Helper::e($filters['search']) ?>">
        <select name="category_id" class="form-select-dark" style="width:160px;">
            <option value="">All Categories</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= $filters['category_id']==$c['category_id']?'selected':'' ?>><?= Helper::e($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="supplier_id" class="form-select-dark" style="width:160px;">
            <option value="">All Suppliers</option>
            <?php foreach($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>" <?= $filters['supplier_id']==$s['supplier_id']?'selected':'' ?>><?= Helper::e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="brand" class="form-select-dark" style="width:140px;">
            <option value="">All Brands</option>
            <?php foreach($brands as $b): ?>
            <option value="<?= Helper::e($b['brand']) ?>" <?= $filters['brand']==$b['brand']?'selected':'' ?>><?= Helper::e($b['brand']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-cyan"><i class="bi bi-funnel-fill"></i> Filter</button>
        <a href="/sap-computers/products.php" class="btn-ghost"><i class="bi bi-x-circle"></i> Clear</a>
    </form>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>#</th><th>Product Name</th><th>Brand</th><th>Model</th>
                    <th>Category</th><th>Supplier</th><th>Cost</th><th>Selling</th>
                    <th>Stock</th><th>Reorder</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td><strong><?= Helper::e($p['product_name']) ?></strong></td>
                    <td><?= Helper::e($p['brand']) ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($p['model']) ?></td>
                    <td><span class="badge bg-info"><?= Helper::e($p['category_name']) ?></span></td>
                    <td><?= Helper::e($p['supplier_name']) ?></td>
                    <td><?= Helper::formatCurrency($p['cost_price']) ?></td>
                    <td style="color:var(--success)"><?= Helper::formatCurrency($p['selling_price']) ?></td>
                    <td><?= Helper::stockBadge((int)$p['total_stock'], (int)$p['reorder_level']) ?> <small style="color:var(--text-muted)">(<?= $p['total_stock'] ?>)</small></td>
                    <td style="color:var(--text-muted)"><?= $p['reorder_level'] ?></td>
                    <td><?= $p['status']==='active'?'<span class="badge bg-success">Active</span>':'<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td>
                        <button class="action-btn edit" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="bi bi-pencil-fill"></i></button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                            <button type="submit" class="action-btn delete" data-confirm="Delete product?"><i class="bi bi-trash3-fill"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade modal-dark" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prodModalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="prodAction" value="create">
                <input type="hidden" name="product_id" id="prodId" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label-dark">Product Name * <span class="field-hint">max 50 chars</span></label>
                            <input type="text" name="product_name" id="f_p_name"
                                class="form-control-dark w-100"
                                maxlength="50" minlength="2"
                                placeholder="e.g. Inspiron 15 3000"
                                required>
                            <div class="field-counter"><span id="cnt_p_name">0</span>/50</div>
                            <div class="field-error" id="err_p_name"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Brand <span class="field-hint">max 20 chars</span></label>
                            <input type="text" name="brand" id="f_p_brand"
                                class="form-control-dark w-100"
                                maxlength="20"
                                placeholder="e.g. Dell">
                            <div class="field-counter"><span id="cnt_p_brand">0</span>/20</div>
                            <div class="field-error" id="err_p_brand"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Model <span class="field-hint">max 30 chars</span></label>
                            <input type="text" name="model" id="f_p_model"
                                class="form-control-dark w-100"
                                maxlength="30"
                                placeholder="e.g. INS15-3520">
                            <div class="field-counter"><span id="cnt_p_model">0</span>/30</div>
                            <div class="field-error" id="err_p_model"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Status</label>
                            <select name="status" id="f_p_status" class="form-select-dark w-100">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Category *</label>
                            <select name="category_id" id="f_p_cat" class="form-select-dark w-100" required>
                                <option value="">Select category…</option>
                                <?php foreach($categories as $c): ?>
                                <option value="<?= $c['category_id'] ?>"><?= Helper::e($c['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="err_p_cat"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Supplier *</label>
                            <select name="supplier_id" id="f_p_sup" class="form-select-dark w-100" required>
                                <option value="">Select supplier…</option>
                                <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['supplier_id'] ?>"><?= Helper::e($s['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="field-error" id="err_p_sup"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Cost Price (Rs.) *</label>
                            <input type="number" name="cost_price" id="f_p_cost"
                                class="form-control-dark w-100"
                                min="1" max="9999999" step="0.01"
                                placeholder="0.00"
                                required>
                            <div class="field-error" id="err_p_cost"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Selling Price (Rs.) *</label>
                            <input type="number" name="selling_price" id="f_p_sell"
                                class="form-control-dark w-100"
                                min="1" max="9999999" step="0.01"
                                placeholder="0.00"
                                required>
                            <div class="field-error" id="err_p_sell"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Reorder Level <span class="field-hint">0–999</span></label>
                            <input type="number" name="reorder_level" id="f_p_reorder"
                                class="form-control-dark w-100"
                                min="0" max="999" value="5">
                            <div class="field-error" id="err_p_reorder"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cyan"><i class="bi bi-check-lg"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $extraScripts = '
<style>
.field-hint    { font-size:10px; color:var(--text-dim); font-weight:400; text-transform:none; letter-spacing:0; margin-left:4px; }
.field-counter { font-size:11px; color:var(--text-dim); text-align:right; margin-top:3px; }
.field-counter.near-limit { color:var(--warning); }
.field-counter.at-limit   { color:var(--danger); }
.field-error   { font-size:11px; color:var(--danger); margin-top:4px; min-height:16px; }
.form-control-dark.is-invalid, .form-control-dark.is-invalid:focus { border-color:var(--danger)!important; box-shadow:0 0 0 3px rgba(255,71,87,0.2)!important; }
.form-control-dark.is-valid,   .form-control-dark.is-valid:focus   { border-color:var(--success)!important; box-shadow:0 0 0 3px rgba(0,214,143,0.15)!important; }
.form-select-dark.is-invalid { border-color:var(--danger)!important; }
</style>

<script>
function initCounter(inputId, spanId, limit) {
    const el = document.getElementById(inputId);
    const sp = document.getElementById(spanId);
    if (!el || !sp) return;
    function upd() {
        const len = el.value.length;
        sp.textContent = len;
        const wrap = sp.closest(".field-counter");
        wrap.classList.remove("near-limit","at-limit");
        if (len >= limit)             wrap.classList.add("at-limit");
        else if (len >= limit * 0.85) wrap.classList.add("near-limit");
    }
    el.addEventListener("input", upd); upd();
}

function setErr(errId, msg) {
    const el = document.getElementById(errId);
    if (el) el.textContent = msg;
}

function setValidity(id, valid) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove("is-valid","is-invalid");
    el.classList.add(valid ? "is-valid" : "is-invalid");
}

const pRules = {
    f_p_name: {
        required: true, minLen: 2, maxLen: 50,
        pattern: /^[A-Za-z0-9\s\-\.\/\(\)]+$/,
        patternMsg: "Letters, numbers, spaces, hyphens and dots only.",
        errId: "err_p_name"
    },
    f_p_brand: {
        required: false, minLen: 2, maxLen: 20,
        pattern: /^[A-Za-z0-9\s\-]*$/,
        patternMsg: "Letters, numbers, spaces and hyphens only.",
        errId: "err_p_brand"
    },
    f_p_model: {
        required: false, maxLen: 30,
        pattern: /^[A-Za-z0-9\s\-\.\/]*$/,
        patternMsg: "Letters, numbers, hyphens, dots and slashes only.",
        errId: "err_p_model"
    },
    f_p_cat: {
        required: true, isSelect: true,
        errId: "err_p_cat"
    },
    f_p_sup: {
        required: true, isSelect: true,
        errId: "err_p_sup"
    },
    f_p_cost: {
        required: true, isNumber: true, min: 1, max: 9999999,
        errId: "err_p_cost"
    },
    f_p_sell: {
        required: true, isNumber: true, min: 1, max: 9999999,
        errId: "err_p_sell",
        customCheck: function(val) {
            const cost = parseFloat(document.getElementById("f_p_cost").value || 0);
            if (parseFloat(val) < cost) return "Selling price cannot be less than cost price.";
            return "";
        }
    },
    f_p_reorder: {
        required: false, isNumber: true, min: 0, max: 999,
        errId: "err_p_reorder"
    }
};

function validatePField(id) {
    const rule = pRules[id];
    if (!rule) return true;
    const el  = document.getElementById(id);
    const val = el.value.trim();
    setErr(rule.errId, "");
    el.classList.remove("is-valid","is-invalid");

    if (rule.required && (val === "" || val === "0")) {
        setErr(rule.errId, rule.isSelect ? "Please select an option." : "This field is required.");
        el.classList.add("is-invalid"); return false;
    }
    if (val === "") return true;

    if (rule.isNumber) {
        const num = parseFloat(val);
        if (isNaN(num)) { setErr(rule.errId, "Must be a valid number."); el.classList.add("is-invalid"); return false; }
        if (rule.min !== undefined && num < rule.min) { setErr(rule.errId, "Minimum value is " + rule.min + "."); el.classList.add("is-invalid"); return false; }
        if (rule.max !== undefined && num > rule.max) { setErr(rule.errId, "Maximum value is " + rule.max + "."); el.classList.add("is-invalid"); return false; }
    } else {
        if (rule.minLen && val.length < rule.minLen) { setErr(rule.errId, "Minimum " + rule.minLen + " characters required."); el.classList.add("is-invalid"); return false; }
        if (rule.maxLen && val.length > rule.maxLen) { setErr(rule.errId, "Maximum " + rule.maxLen + " characters allowed."); el.classList.add("is-invalid"); return false; }
        if (rule.pattern && !rule.pattern.test(val)) { setErr(rule.errId, rule.patternMsg); el.classList.add("is-invalid"); return false; }
    }

    if (rule.customCheck) {
        const msg = rule.customCheck(val);
        if (msg) { setErr(rule.errId, msg); el.classList.add("is-invalid"); return false; }
    }

    el.classList.add("is-valid"); return true;
}

document.addEventListener("DOMContentLoaded", function () {
    initCounter("f_p_name",  "cnt_p_name",  50);
    initCounter("f_p_brand", "cnt_p_brand", 20);
    initCounter("f_p_model", "cnt_p_model", 30);

    Object.keys(pRules).forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input",  () => validatePField(id));
            el.addEventListener("change", () => validatePField(id));
            el.addEventListener("blur",   () => validatePField(id));
        }
    });

    // Re-validate selling price when cost changes
    document.getElementById("f_p_cost").addEventListener("input", () => {
        const sell = document.getElementById("f_p_sell");
        if (sell.value) validatePField("f_p_sell");
    });

    document.querySelector("#productModal form").addEventListener("submit", function (e) {
        let valid = true;
        Object.keys(pRules).forEach(id => { if (!validatePField(id)) valid = false; });
        if (!valid) { e.preventDefault(); e.stopPropagation(); }
    });
});

function editProduct(p) {
    document.getElementById("prodModalTitle").textContent = "Edit Product";
    document.getElementById("prodAction").value    = "update";
    document.getElementById("prodId").value        = p.product_id;
    document.getElementById("f_p_name").value      = p.product_name;
    document.getElementById("f_p_brand").value     = p.brand || "";
    document.getElementById("f_p_model").value     = p.model || "";
    document.getElementById("f_p_cat").value       = p.category_id;
    document.getElementById("f_p_sup").value       = p.supplier_id;
    document.getElementById("f_p_cost").value      = p.cost_price;
    document.getElementById("f_p_sell").value      = p.selling_price;
    document.getElementById("f_p_reorder").value   = p.reorder_level;
    document.getElementById("f_p_status").value    = p.status;
    ["f_p_name","f_p_brand","f_p_model"].forEach(id => {
        document.getElementById(id).dispatchEvent(new Event("input"));
    });
    new bootstrap.Modal(document.getElementById("productModal")).show();
}

document.getElementById("productModal").addEventListener("hidden.bs.modal", function () {
    document.getElementById("prodModalTitle").textContent = "Add Product";
    document.getElementById("prodAction").value = "create";
    document.getElementById("prodId").value = "";
    this.querySelector("form").reset();
    Object.keys(pRules).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove("is-valid","is-invalid");
        const err = document.getElementById(pRules[id].errId);
        if (err) err.textContent = "";
    });
    ["cnt_p_name","cnt_p_brand","cnt_p_model"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = "0";
    });
});
</script>';
require_once __DIR__ . '/views/layouts/footer.php'; ?>