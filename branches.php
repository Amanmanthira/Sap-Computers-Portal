<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Branch Management';
$activePage = 'branches';
$model = new BranchModel();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $model->delete((int)$_POST['branch_id']);
        Session::setFlash('success', 'Branch deleted.');
        Helper::redirect('/sap-computers/branches.php');
    }

    $d = [
        'branch_name'  => Helper::sanitize($_POST['branch_name'] ?? ''),
        'address'      => Helper::sanitize($_POST['address'] ?? ''),
        'phone'        => Helper::sanitize($_POST['phone'] ?? ''),
        'manager_name' => Helper::sanitize($_POST['manager_name'] ?? ''),
        'status'       => $_POST['status'] ?? 'active',
    ];

    // --- Server-side validation ---
    $errors = [];

    // Branch name: required, 2–100 chars, letters/numbers/spaces/hyphens/dots
    if (empty($d['branch_name'])) {
        $errors[] = 'Branch name is required.';
    } elseif (strlen($d['branch_name']) < 2 || strlen($d['branch_name']) > 20) {
        $errors[] = 'Branch name must be between 2 and 20 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9\s\(\)\-\.]+$/', $d['branch_name'])) {
        $errors[] = 'Branch name may only contain letters, numbers, spaces, hyphens and dots.';
    }

    // Manager name: optional, 2–20 chars, letters/spaces/dots only
    if (!empty($d['manager_name'])) {
        if (strlen($d['manager_name']) < 2 || strlen($d['manager_name']) > 20) {
            $errors[] = 'Manager name must be between 2 and 20 characters.';
        } elseif (!preg_match('/^[A-Za-z\s\.]+$/', $d['manager_name'])) {
            $errors[] = 'Manager name may only contain letters, spaces and dots.';
        }
    }

    // Phone: optional, digits/+/-/space, 9–15 chars
    if (!empty($d['phone'])) {
        $phoneClean = preg_replace('/[\s\-]/', '', $d['phone']);
        if (!preg_match('/^\+?[0-9]{9,15}$/', $phoneClean)) {
            $errors[] = 'Phone must be a valid number (9–15 digits).';
        }
    }

    // Address: optional, max 250 chars
    if (!empty($d['address']) && strlen($d['address']) > 250) {
        $errors[] = 'Address must not exceed 250 characters.';
    }

    if (!empty($errors)) {
        Session::setFlash('error', implode(' ', $errors));
        Helper::redirect('/sap-computers/branches.php');
    }

    if ($action === 'create') {
        $model->create($d);
        Session::setFlash('success', 'Branch added successfully.');
    } elseif ($action === 'update') {
        $model->update((int)$_POST['branch_id'], $d);
        Session::setFlash('success', 'Branch updated successfully.');
    }
    Helper::redirect('/sap-computers/branches.php');
}

$branches = $model->getAll();
require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Branch Management</div>
        <div class="page-header-sub"><?= count($branches) ?> branches registered</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#branchModal">
        <i class="bi bi-plus-lg"></i> Add Branch
    </button>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch Name</th>
                    <th>Manager</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($branches as $i => $b): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td><strong><?= Helper::e($b['branch_name']) ?></strong></td>
                    <td><?= Helper::e($b['manager_name']) ?></td>
                    <td><?= Helper::e($b['phone']) ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= Helper::e($b['address']) ?></td>
                    <td>
                        <?php if($b['status']==='active'): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted)"><?= Helper::formatDate($b['created_at']) ?></td>
                    <td>
                        <button class="action-btn edit" onclick="editBranch(<?= htmlspecialchars(json_encode($b)) ?>)" title="Edit">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="branch_id" value="<?= $b['branch_id'] ?>">
                            <button type="submit" class="action-btn delete" data-confirm="Delete branch '<?= Helper::e($b['branch_name']) ?>'?" title="Delete">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade modal-dark" id="branchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="branchModalTitle">Add Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="branchAction" value="create">
                <input type="hidden" name="branch_id" id="branchId" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-dark">Branch Name * <span class="field-hint">max 20 chars</span></label>
                            <input type="text" name="branch_name" id="f_branch_name" class="form-control-dark w-100"
                                maxlength="20" minlength="2"
                                pattern="[A-Za-z0-9\s\(\)\-\.]+"
                                title="Branch name: letters, numbers, spaces, hyphens only"
                                placeholder="e.g. Gampaha Main"
                                required>
                            <div class="field-counter"><span id="branch_name_count">0</span>/20</div>
                            <div class="field-error" id="err_branch_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Manager Name <span class="field-hint">max 20 chars</span></label>
                            <input type="text" name="manager_name" id="f_manager_name" class="form-control-dark w-100"
                                maxlength="20" minlength="2"
                                pattern="[A-Za-z\s\.]+"
                                title="Manager name: letters and spaces only"
                                placeholder="e.g. Kasun Perera">
                            <div class="field-counter"><span id="manager_name_count">0</span>/20</div>
                            <div class="field-error" id="err_manager_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Phone <span class="field-hint">10 digits only</span></label>
                            <input type="tel" name="phone" id="f_phone" class="form-control-dark w-100"
                                maxlength="15" minlength="9"
                                pattern="[0-9\+\-\s]+"
                                title="Phone: numbers only (e.g. 0332221111)"
                                placeholder="e.g. 0332 221 111"
                                oninput="this.value=this.value.replace(/[^0-9\+\-\s]/g,'')">
                            <div class="field-error" id="err_phone"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">Status</label>
                            <select name="status" id="f_status" class="form-select-dark w-100">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-dark">Address <span class="field-hint">max 250 chars</span></label>
                            <textarea name="address" id="f_address" class="form-control-dark w-100" rows="2"
                                maxlength="250"
                                placeholder="e.g. No. 45, Main Street, Gampaha"></textarea>
                            <div class="field-counter"><span id="address_count">0</span>/250</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cyan" id="branchSaveBtn"><i class="bi bi-check-lg"></i> Save Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<style>
.field-hint {
    font-size: 10px;
    color: var(--text-dim);
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    margin-left: 4px;
}
.field-counter {
    font-size: 11px;
    color: var(--text-dim);
    text-align: right;
    margin-top: 3px;
}
.field-counter.near-limit { color: var(--warning); }
.field-counter.at-limit   { color: var(--danger); }
.field-error {
    font-size: 11px;
    color: var(--danger);
    margin-top: 4px;
    min-height: 16px;
}
.form-control-dark.is-invalid,
.form-control-dark.is-invalid:focus {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 3px rgba(255,71,87,0.2) !important;
}
.form-control-dark.is-valid,
.form-control-dark.is-valid:focus {
    border-color: var(--success) !important;
    box-shadow: 0 0 0 3px rgba(0,214,143,0.15) !important;
}
</style>

<script>
// ---- Character counters ----
function initCounter(inputId, counterId, limit) {
    const input = document.getElementById(inputId);
    const counter = document.getElementById(counterId);
    if (!input || !counter) return;

    function update() {
        const len = input.value.length;
        counter.textContent = len;
        const wrapper = counter.closest(".field-counter");
        wrapper.classList.remove("near-limit","at-limit");
        if (len >= limit)           wrapper.classList.add("at-limit");
        else if (len >= limit * 0.85) wrapper.classList.add("near-limit");
    }
    input.addEventListener("input", update);
    update();
}

// ---- Validation rules ----
const rules = {
    f_branch_name: {
        required: true,
        minLen: 2,
        maxLen: 20,
        pattern: /^[A-Za-z0-9\s\(\)\-\.]+$/,
        patternMsg: "Only letters, numbers, spaces, hyphens and dots allowed.",
        errId: "err_branch_name"
    },
    f_manager_name: {
        required: false,
        minLen: 2,
        maxLen: 20,
        pattern: /^[A-Za-z\s\.]*$/,
        patternMsg: "Only letters, spaces and dots allowed.",
        errId: "err_manager_name"
    },
    f_phone: {
        required: false,
        minLen: 9,
        maxLen: 15,
        pattern: /^[\d\+\-\s]*$/,
        patternMsg: "Phone must contain numbers only.",
        errId: "err_phone"
    }
};

function validateField(id) {
    const rule = rules[id];
    if (!rule) return true;
    const el = document.getElementById(id);
    const err = document.getElementById(rule.errId);
    const val = el.value.trim();

    el.classList.remove("is-invalid","is-valid");
    err.textContent = "";

    if (rule.required && val === "") {
        err.textContent = "This field is required.";
        el.classList.add("is-invalid");
        return false;
    }
    if (val === "" && !rule.required) {
        el.classList.remove("is-invalid");
        return true;
    }
    if (val.length < rule.minLen) {
        err.textContent = "Minimum " + rule.minLen + " characters required.";
        el.classList.add("is-invalid");
        return false;
    }
    if (val.length > rule.maxLen) {
        err.textContent = "Maximum " + rule.maxLen + " characters allowed.";
        el.classList.add("is-invalid");
        return false;
    }
    if (rule.pattern && !rule.pattern.test(val)) {
        err.textContent = rule.patternMsg;
        el.classList.add("is-invalid");
        return false;
    }
    el.classList.add("is-valid");
    return true;
}

// Attach live validation
document.addEventListener("DOMContentLoaded", function () {
    initCounter("f_branch_name", "branch_name_count", 20);
    initCounter("f_manager_name", "manager_name_count", 20);
    initCounter("f_address", "address_count", 250);

    Object.keys(rules).forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input",  () => validateField(id));
            el.addEventListener("blur",   () => validateField(id));
        }
    });

    // Phone: strip non-numeric on input (allow +, -, space)
    const phoneEl = document.getElementById("f_phone");
    if (phoneEl) {
        phoneEl.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9\+\-\s]/g, "");
        });
    }

    // Form submit validation
    const form = document.querySelector("#branchModal form");
    form.addEventListener("submit", function (e) {
        let valid = true;
        Object.keys(rules).forEach(id => {
            if (!validateField(id)) valid = false;
        });
        if (!valid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
});

// ---- Edit / Reset ----
function editBranch(b) {
    document.getElementById("branchModalTitle").textContent = "Edit Branch";
    document.getElementById("branchAction").value = "update";
    document.getElementById("branchId").value = b.branch_id;
    document.getElementById("f_branch_name").value = b.branch_name;
    document.getElementById("f_manager_name").value = b.manager_name || "";
    document.getElementById("f_phone").value = b.phone || "";
    document.getElementById("f_address").value = b.address || "";
    document.getElementById("f_status").value = b.status;

    // Trigger counters update
    ["f_branch_name","f_manager_name","f_address"].forEach(id => {
        document.getElementById(id).dispatchEvent(new Event("input"));
    });

    new bootstrap.Modal(document.getElementById("branchModal")).show();
}

document.getElementById("branchModal").addEventListener("hidden.bs.modal", function () {
    document.getElementById("branchModalTitle").textContent = "Add Branch";
    document.getElementById("branchAction").value = "create";
    document.getElementById("branchId").value = "";
    this.querySelector("form").reset();

    // Clear validation states
    ["f_branch_name","f_manager_name","f_phone","f_address"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove("is-valid","is-invalid");
    });
    Object.keys(rules).forEach(id => {
        const rule = rules[id];
        const err = document.getElementById(rule.errId);
        if (err) err.textContent = "";
    });
    ["branch_name_count","manager_name_count","address_count"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = "0";
    });
});
</script>';
require_once __DIR__ . '/views/layouts/footer.php';
?>