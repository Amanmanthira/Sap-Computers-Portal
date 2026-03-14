<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Supplier Management';
$activePage = 'suppliers';
$model = new SupplierModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $model->delete((int)$_POST['supplier_id']);
        Session::setFlash('success', 'Supplier deleted.');
        Helper::redirect('/sap-computers/suppliers.php');
    }

    $d = [
        'supplier_name'        => Helper::sanitize($_POST['supplier_name'] ?? ''),
        'contact_person'       => Helper::sanitize($_POST['contact_person'] ?? ''),
        'phone'                => Helper::sanitize($_POST['phone'] ?? ''),
        'email'                => Helper::sanitize($_POST['email'] ?? ''),
        'address'              => Helper::sanitize($_POST['address'] ?? ''),
        'company_registration' => Helper::sanitize($_POST['company_registration'] ?? ''),
        'status'               => $_POST['status'] ?? 'active',
    ];

    // --- Server-side validation ---
    $errors = [];

    // Supplier name: required, 2–20 chars
    if (empty($d['supplier_name'])) {
        $errors[] = 'Supplier name is required.';
    } elseif (strlen($d['supplier_name']) < 2 || strlen($d['supplier_name']) > 20) {
        $errors[] = 'Supplier name must be between 2 and 20 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9\s\(\)\-\.&]+$/', $d['supplier_name'])) {
        $errors[] = 'Supplier name may only contain letters, numbers, spaces, hyphens, dots and &.';
    }

    // Contact person: optional, 2–20 chars, letters/spaces/dots only
    if (!empty($d['contact_person'])) {
        if (strlen($d['contact_person']) < 2 || strlen($d['contact_person']) > 20) {
            $errors[] = 'Contact person name must be between 2 and 20 characters.';
        } elseif (!preg_match('/^[A-Za-z\s\.]+$/', $d['contact_person'])) {
            $errors[] = 'Contact person name may only contain letters, spaces and dots.';
        }
    }

    // Phone: optional, 9–15 digits
    if (!empty($d['phone'])) {
        $phoneClean = preg_replace('/[\s\-]/', '', $d['phone']);
        if (!preg_match('/^\+?[0-9]{9,15}$/', $phoneClean)) {
            $errors[] = 'Phone must be a valid number (9–15 digits).';
        }
    }

    // Email: optional, valid format, max 100 chars
    if (!empty($d['email'])) {
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($d['email']) > 100) {
            $errors[] = 'Email must not exceed 100 characters.';
        }
    }

    // Company registration: optional, max 20 chars, alphanumeric/hyphens/slashes
    if (!empty($d['company_registration'])) {
        if (strlen($d['company_registration']) > 20) {
            $errors[] = 'Registration number must not exceed 20 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9\-\/]+$/', $d['company_registration'])) {
            $errors[] = 'Registration number may only contain letters, numbers, hyphens and slashes.';
        }
    }

    // Address: optional, max 50 chars
    if (!empty($d['address']) && strlen($d['address']) > 50) {
        $errors[] = 'Address must not exceed 50 characters.';
    }

    if (!empty($errors)) {
        Session::setFlash('error', implode(' ', $errors));
        Helper::redirect('/sap-computers/suppliers.php');
    }

    if ($action === 'create') {
        $model->create($d);
        Session::setFlash('success', 'Supplier added successfully.');
    } elseif ($action === 'update') {
        $model->update((int)$_POST['supplier_id'], $d);
        Session::setFlash('success', 'Supplier updated.');
    }
    Helper::redirect('/sap-computers/suppliers.php');
}

$suppliers = $model->getAll();
require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Supplier Management</div>
        <div class="page-header-sub"><?= count($suppliers) ?> suppliers registered</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#supplierModal">
        <i class="bi bi-plus-lg"></i> Add Supplier
    </button>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>#</th><th>Supplier Name</th><th>Contact Person</th><th>Phone</th>
                    <th>Email</th><th>Reg. No.</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $i => $s): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td>
                        <strong><?= Helper::e($s['supplier_name']) ?></strong>
                        <?php if($s['address']): ?>
                        <div style="font-size:11px;color:var(--text-muted);"><?= Helper::e(substr($s['address'],0,40)) ?>…</div>
                        <?php endif; ?>
                    </td>
                    <td><?= Helper::e($s['contact_person']) ?></td>
                    <td><?= Helper::e($s['phone']) ?></td>
                    <td><a href="mailto:<?= Helper::e($s['email']) ?>" style="color:var(--cyan)"><?= Helper::e($s['email']) ?></a></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($s['company_registration']) ?></td>
                    <td><?= $s['status']==='active'?'<span class="badge bg-success">Active</span>':'<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td>
                        <button class="action-btn edit" onclick="editSupplier(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="bi bi-pencil-fill"></i></button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="supplier_id" value="<?= $s['supplier_id'] ?>">
                            <button type="submit" class="action-btn delete" data-confirm="Delete supplier '<?= Helper::e($s['supplier_name']) ?>'?"><i class="bi bi-trash3-fill"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade modal-dark" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalTitle">Add Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="supplierForm">
                <input type="hidden" name="action" id="supplierAction" value="create">
                <input type="hidden" name="supplier_id" id="supplierId" value="">
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label-dark">Supplier Name * <span class="field-hint">max 20 chars</span></label>
                            <input type="text" name="supplier_name" id="f_supplier_name"
                                class="form-control-dark w-100"
                                maxlength="20" minlength="2"
                                placeholder="e.g. Dell Lanka"
                                required>
                            <div class="field-counter"><span id="cnt_supplier_name">0</span>/20</div>
                            <div class="field-error" id="err_supplier_name"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-dark">Contact Person <span class="field-hint">max 20 chars</span></label>
                            <input type="text" name="contact_person" id="f_contact_person"
                                class="form-control-dark w-100"
                                maxlength="20" minlength="2"
                                placeholder="e.g. Kasun Perera">
                            <div class="field-counter"><span id="cnt_contact_person">0</span>/20</div>
                            <div class="field-error" id="err_contact_person"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-dark">Phone <span class="field-hint">9–15 digits only</span></label>
                            <input type="tel" name="phone" id="f_s_phone"
                                class="form-control-dark w-100"
                                maxlength="15" minlength="9"
                                placeholder="e.g. 0112001234"
                                oninput="this.value=this.value.replace(/[^0-9\+\-\s]/g,'')">
                            <div class="field-error" id="err_s_phone"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-dark">Email <span class="field-hint">valid email format</span></label>
                            <input type="email" name="email" id="f_s_email"
                                class="form-control-dark w-100"
                                maxlength="100"
                                placeholder="e.g. orders@supplier.lk">
                            <div class="field-error" id="err_s_email"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-dark">Company Registration # <span class="field-hint">max 20 chars</span></label>
                            <input type="text" name="company_registration" id="f_reg"
                                class="form-control-dark w-100"
                                maxlength="20"
                                placeholder="e.g. PV/00001">
                            <div class="field-counter"><span id="cnt_reg">0</span>/20</div>
                            <div class="field-error" id="err_reg"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-dark">Status</label>
                            <select name="status" id="f_s_status" class="form-select-dark w-100">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label-dark">Address <span class="field-hint">max 50 chars</span></label>
                            <textarea name="address" id="f_s_address"
                                class="form-control-dark w-100" rows="2"
                                maxlength="50"
                                placeholder="e.g. No. 12, Galle Road, Colombo 03"></textarea>
                            <div class="field-counter"><span id="cnt_address">0</span>/50</div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cyan"><i class="bi bi-check-lg"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $extraScripts = '
<style>
.field-hint  { font-size:10px; color:var(--text-dim); font-weight:400; text-transform:none; letter-spacing:0; margin-left:4px; }
.field-counter { font-size:11px; color:var(--text-dim); text-align:right; margin-top:3px; }
.field-counter.near-limit { color:var(--warning); }
.field-counter.at-limit   { color:var(--danger); }
.field-error  { font-size:11px; color:var(--danger); margin-top:4px; min-height:16px; }
.form-control-dark.is-invalid, .form-control-dark.is-invalid:focus { border-color:var(--danger)!important; box-shadow:0 0 0 3px rgba(255,71,87,0.2)!important; }
.form-control-dark.is-valid,   .form-control-dark.is-valid:focus   { border-color:var(--success)!important; box-shadow:0 0 0 3px rgba(0,214,143,0.15)!important; }
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

const sRules = {
    f_supplier_name: {
        required: true, minLen: 2, maxLen: 20,
        pattern: /^[A-Za-z0-9\s\(\)\-\.&]+$/,
        patternMsg: "Letters, numbers, spaces, hyphens, dots and & only.",
        errId: "err_supplier_name"
    },
    f_contact_person: {
        required: false, minLen: 2, maxLen: 20,
        pattern: /^[A-Za-z\s\.]*$/,
        patternMsg: "Letters, spaces and dots only.",
        errId: "err_contact_person"
    },
    f_s_phone: {
        required: false, minLen: 9, maxLen: 15,
        pattern: /^[\d\+\-\s]*$/,
        patternMsg: "Numbers only (digits, +, -).",
        errId: "err_s_phone"
    },
    f_s_email: {
        required: false, maxLen: 100,
        customCheck: function(val) {
            if (val === "") return "";
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) ? "" : "Enter a valid email address.";
        },
        errId: "err_s_email"
    },
    f_reg: {
        required: false, maxLen: 20,
        pattern: /^[A-Za-z0-9\-\/]*$/,
        patternMsg: "Letters, numbers, hyphens and slashes only.",
        errId: "err_reg"
    }
};

function validateSField(id) {
    const rule = sRules[id];
    if (!rule) return true;
    const el  = document.getElementById(id);
    const err = document.getElementById(rule.errId);
    const val = el.value.trim();
    el.classList.remove("is-invalid","is-valid");
    err.textContent = "";
    if (rule.required && val === "") {
        err.textContent = "This field is required.";
        el.classList.add("is-invalid"); return false;
    }
    if (val === "") return true;
    if (rule.minLen && val.length < rule.minLen) {
        err.textContent = "Minimum " + rule.minLen + " characters required.";
        el.classList.add("is-invalid"); return false;
    }
    if (rule.maxLen && val.length > rule.maxLen) {
        err.textContent = "Maximum " + rule.maxLen + " characters allowed.";
        el.classList.add("is-invalid"); return false;
    }
    if (rule.customCheck) {
        const msg = rule.customCheck(val);
        if (msg) { err.textContent = msg; el.classList.add("is-invalid"); return false; }
    }
    if (rule.pattern && !rule.pattern.test(val)) {
        err.textContent = rule.patternMsg;
        el.classList.add("is-invalid"); return false;
    }
    el.classList.add("is-valid"); return true;
}

document.addEventListener("DOMContentLoaded", function () {
    initCounter("f_supplier_name",  "cnt_supplier_name", 20);
    initCounter("f_contact_person", "cnt_contact_person", 20);
    initCounter("f_reg",            "cnt_reg", 20);
    initCounter("f_s_address",      "cnt_address", 50);

    document.getElementById("f_s_phone").addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9\+\-\s]/g, "");
    });

    Object.keys(sRules).forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", () => validateSField(id));
            el.addEventListener("blur",  () => validateSField(id));
        }
    });

    document.getElementById("supplierForm").addEventListener("submit", function (e) {
        let valid = true;
        Object.keys(sRules).forEach(id => { if (!validateSField(id)) valid = false; });
        if (!valid) { e.preventDefault(); e.stopPropagation(); }
    });
});

function editSupplier(s) {
    document.getElementById("supplierModalTitle").textContent = "Edit Supplier";
    document.getElementById("supplierAction").value = "update";
    document.getElementById("supplierId").value     = s.supplier_id;
    document.getElementById("f_supplier_name").value  = s.supplier_name;
    document.getElementById("f_contact_person").value = s.contact_person || "";
    document.getElementById("f_s_phone").value        = s.phone || "";
    document.getElementById("f_s_email").value        = s.email || "";
    document.getElementById("f_reg").value            = s.company_registration || "";
    document.getElementById("f_s_address").value      = s.address || "";
    document.getElementById("f_s_status").value       = s.status;
    ["f_supplier_name","f_contact_person","f_reg","f_s_address"].forEach(id => {
        document.getElementById(id).dispatchEvent(new Event("input"));
    });
    new bootstrap.Modal(document.getElementById("supplierModal")).show();
}

document.getElementById("supplierModal").addEventListener("hidden.bs.modal", function () {
    document.getElementById("supplierModalTitle").textContent = "Add Supplier";
    document.getElementById("supplierAction").value = "create";
    document.getElementById("supplierId").value = "";
    this.querySelector("form").reset();
    Object.keys(sRules).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove("is-valid","is-invalid");
        const err = document.getElementById(sRules[id].errId);
        if (err) err.textContent = "";
    });
    ["cnt_supplier_name","cnt_contact_person","cnt_reg","cnt_address"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = "0";
    });
});
</script>';
require_once __DIR__ . '/views/layouts/footer.php'; ?>