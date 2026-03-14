<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'Category Management';
$activePage = 'categories';
$model = new CategoryModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $model->delete((int)$_POST['category_id']);
        Session::setFlash('success', 'Category deleted.');
        Helper::redirect('/sap-computers/categories.php');
    }

    $d = [
        'category_name' => Helper::sanitize($_POST['category_name'] ?? ''),
        'description'   => Helper::sanitize($_POST['description'] ?? ''),
    ];

    // --- Server-side validation ---
    $errors = [];

    // Category name: required, 2–20 chars, letters/spaces/hyphens only
    if (empty($d['category_name'])) {
        $errors[] = 'Category name is required.';
    } elseif (strlen($d['category_name']) < 2 || strlen($d['category_name']) > 20) {
        $errors[] = 'Category name must be between 2 and 20 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9\s\-]+$/', $d['category_name'])) {
        $errors[] = 'Category name may only contain letters, numbers, spaces and hyphens.';
    }

    // Description: optional, max 80 chars
    if (!empty($d['description']) && strlen($d['description']) > 80) {
        $errors[] = 'Description must not exceed 80 characters.';
    }

    if (!empty($errors)) {
        Session::setFlash('error', implode(' ', $errors));
        Helper::redirect('/sap-computers/categories.php');
    }

    if ($action === 'create') { $model->create($d); Session::setFlash('success', 'Category added.'); }
    elseif ($action === 'update') { $model->update((int)$_POST['category_id'], $d); Session::setFlash('success', 'Category updated.'); }
    Helper::redirect('/sap-computers/categories.php');
}

$categories = $model->getAll();
require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">Category Management</div>
        <div class="page-header-sub"><?= count($categories) ?> categories</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#catModal">
        <i class="bi bi-plus-lg"></i> Add Category
    </button>
</div>

<div class="row g-3">
    <?php foreach ($categories as $c): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="content-card" style="height:100%;">
            <div class="content-card-body" style="text-align:center;padding:24px 16px;">
                <div style="width:50px;height:50px;background:var(--cyan-dim);border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:22px;color:var(--cyan);">
                    <?php
                    $icons = ['Laptop'=>'bi-laptop','Desktop'=>'bi-pc-display','Monitor'=>'bi-display','Printer'=>'bi-printer','Accessories'=>'bi-keyboard','Components'=>'bi-cpu'];
                    echo '<i class="bi ' . ($icons[$c['category_name']] ?? 'bi-tag-fill') . '"></i>';
                    ?>
                </div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:14px;"><?= Helper::e($c['category_name']) ?></div>
                <div style="color:var(--cyan);font-size:20px;font-weight:800;font-family:'Syne',sans-serif;margin:6px 0;"><?= $c['product_count'] ?></div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:12px;">products</div>
                <?php if($c['description']): ?>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;"><?= Helper::e($c['description']) ?></div>
                <?php endif; ?>
                <div style="display:flex;gap:8px;justify-content:center;">
                    <button class="action-btn edit" onclick="editCat(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="bi bi-pencil-fill"></i></button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                        <button type="submit" class="action-btn delete" data-confirm="Delete category?"><i class="bi bi-trash3-fill"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade modal-dark" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="catAction" value="create">
                <input type="hidden" name="category_id" id="catId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-dark">Category Name * <span class="field-hint">max 20 chars</span></label>
                        <input type="text" name="category_name" id="f_cat_name"
                            class="form-control-dark w-100"
                            maxlength="20" minlength="2"
                            placeholder="e.g. Laptop"
                            required>
                        <div class="field-counter"><span id="cnt_cat_name">0</span>/20</div>
                        <div class="field-error" id="err_cat_name"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-dark">Description <span class="field-hint">max 80 chars</span></label>
                        <textarea name="description" id="f_cat_desc"
                            class="form-control-dark w-100" rows="3"
                            maxlength="80"
                            placeholder="e.g. Portable computers and notebooks"></textarea>
                        <div class="field-counter"><span id="cnt_cat_desc">0</span>/80</div>
                        <div class="field-error" id="err_cat_desc"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-cyan"><i class="bi bi-check-lg"></i> Save</button>
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

const catRules = {
    f_cat_name: {
        required: true, minLen: 2, maxLen: 20,
        pattern: /^[A-Za-z0-9\s\-]+$/,
        patternMsg: "Letters, numbers, spaces and hyphens only.",
        errId: "err_cat_name"
    },
    f_cat_desc: {
        required: false, maxLen: 80,
        errId: "err_cat_desc"
    }
};

function validateCatField(id) {
    const rule = catRules[id];
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
    if (rule.pattern && !rule.pattern.test(val)) {
        err.textContent = rule.patternMsg;
        el.classList.add("is-invalid"); return false;
    }
    el.classList.add("is-valid"); return true;
}

document.addEventListener("DOMContentLoaded", function () {
    initCounter("f_cat_name", "cnt_cat_name", 20);
    initCounter("f_cat_desc", "cnt_cat_desc", 80);

    Object.keys(catRules).forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", () => validateCatField(id));
            el.addEventListener("blur",  () => validateCatField(id));
        }
    });

    document.querySelector("#catModal form").addEventListener("submit", function (e) {
        let valid = true;
        Object.keys(catRules).forEach(id => { if (!validateCatField(id)) valid = false; });
        if (!valid) { e.preventDefault(); e.stopPropagation(); }
    });
});

function editCat(c) {
    document.getElementById("catModalTitle").textContent = "Edit Category";
    document.getElementById("catAction").value = "update";
    document.getElementById("catId").value = c.category_id;
    document.getElementById("f_cat_name").value = c.category_name;
    document.getElementById("f_cat_desc").value = c.description || "";
    ["f_cat_name","f_cat_desc"].forEach(id => {
        document.getElementById(id).dispatchEvent(new Event("input"));
    });
    new bootstrap.Modal(document.getElementById("catModal")).show();
}

document.getElementById("catModal").addEventListener("hidden.bs.modal", function () {
    document.getElementById("catModalTitle").textContent = "Add Category";
    document.getElementById("catAction").value = "create";
    document.getElementById("catId").value = "";
    this.querySelector("form").reset();
    Object.keys(catRules).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove("is-valid","is-invalid");
        const err = document.getElementById(catRules[id].errId);
        if (err) err.textContent = "";
    });
    ["cnt_cat_name","cnt_cat_desc"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = "0";
    });
});
</script>';
require_once __DIR__ . '/views/layouts/footer.php'; ?>