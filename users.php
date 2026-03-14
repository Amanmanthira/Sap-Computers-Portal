<?php
require_once __DIR__ . '/includes/bootstrap.php';
Session::requireSeller();

$pageTitle = 'User Management';
$activePage = 'users';
$userModel    = new UserModel();
$supModel     = new SupplierModel();
$branchModel  = new BranchModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errors = [];

    // Only validate Name/Email/Pass if we are NOT deleting
    if ($action === 'create' || $action === 'update') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($name) || strlen($name) > 20) {
            $errors[] = "Name must be between 1 and 20 characters.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        if ($action === 'create' && (empty($password) || strlen($password) > 20)) {
            $errors[] = "Password is required and must not exceed 20 characters.";
        }
        if ($action === 'update' && !empty($password) && strlen($password) > 20) {
            $errors[] = "New password must not exceed 20 characters.";
        }

        $d = [
            'name'        => Helper::sanitize($name),
            'email'       => Helper::sanitize($email),
            'password'    => $password,
            'role'        => $_POST['role'] ?? 'seller',
            'supplier_id' => $_POST['supplier_id'] ?: null,
            'branch_id'   => $_POST['branch_id'] ?: null,
            'status'      => $_POST['status'] ?? 'active',
        ];
    }

    if (empty($errors)) {
        if ($action === 'create') {
            $userModel->create($d);
            Session::setFlash('success', 'User created successfully.');
        } elseif ($action === 'update') {
            $userModel->update((int)$_POST['user_id'], $d);
            Session::setFlash('success', 'User updated.');
        } elseif ($action === 'delete') {
            $uid = (int)$_POST['user_id'];
            if ($uid === Session::get('user_id')) {
                Session::setFlash('error', 'You cannot delete your own account.');
            } else {
                $userModel->delete($uid);
                Session::setFlash('success', 'User deleted.');
            }
        }
    } else {
        Session::setFlash('error', implode(' ', $errors));
    }
    Helper::redirect('/sap-computers/users.php');
}

$users     = $userModel->getAll();
$suppliers = $supModel->getActive();

require_once __DIR__ . '/views/layouts/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-header-title">User Management</div>
        <div class="page-header-sub"><?= count($users) ?> users in the system</div>
    </div>
    <button class="btn-cyan" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="bi bi-plus-lg"></i> Add User
    </button>
</div>

<div class="content-card">
    <div class="content-card-body">
        <table class="table-dark-custom datatable w-100">
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Supplier</th><th>Branch</th><th>Status</th><th>Last Login</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:30px;height:30px;background:var(--navy-light);border:1px solid var(--navy-border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--cyan);">
                                <?= strtoupper(substr($u['name'],0,2)) ?>
                            </div>
                            <strong><?= Helper::e($u['name']) ?></strong>
                        </div>
                    </td>
                    <td><?= Helper::e($u['email']) ?></td>
                    <td>
                        <?php if($u['role']==='seller'): ?>
                        <span class="badge" style="background:rgba(0,200,255,0.15);color:var(--cyan);">Admin</span>
                        <?php elseif($u['role']=='staff'): ?>
                        <span class="badge bg-warning text-dark">Branch Staff</span>
                        <?php else: ?>
                        <span class="badge bg-success">Supplier</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted)"><?= Helper::e($u['supplier_name'] ?? '—') ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::e($u['branch_name'] ?? '—') ?></td>
                    <td><?= $u['status']==='active'?'<span class="badge bg-success">Active</span>':'<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td style="color:var(--text-muted)"><?= $u['last_login'] ? Helper::formatDate($u['last_login']) : 'Never' ?></td>
                    <td style="color:var(--text-muted)"><?= Helper::formatDate($u['created_at']) ?></td>
                    <td>
                        <button class="action-btn edit" onclick='editUser(<?= json_encode($u) ?>)'><i class="bi bi-pencil-fill"></i></button>
                        <?php if($u['id'] !== Session::get('user_id')): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="action-btn delete" onclick="return confirm('Delete user \'<?= Helper::e($u['name']) ?>\'?')"><i class="bi bi-trash3-fill"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade modal-dark" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="userForm">
                <input type="hidden" name="action" id="userAction" value="create">
                <input type="hidden" name="user_id" id="userId" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-dark">Full Name *</label>
                            <input type="text" name="name" id="f_u_name" class="form-control-dark w-100" maxlength="20" required>
                            <div id="nameError" class="text-danger small mt-1" style="display:none;">Max 20 characters.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-dark">Email *</label>
                            <input type="email" name="email" id="f_u_email" class="form-control-dark w-100" required>
                            <div id="emailError" class="text-danger small mt-1" style="display:none;">Invalid email format.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-dark">Password <span id="pwdNote" style="color:var(--text-dim);font-size:10px;">(required for new user)</span></label>
                            <input type="password" name="password" id="f_u_pass" class="form-control-dark w-100" maxlength="20">
                            <div id="passError" class="text-danger small mt-1" style="display:none;">Max 20 characters.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Role</label>
                            <select name="role" id="f_u_role" class="form-select-dark w-100" onchange="toggleSupplierField()">
                                <option value="seller">Admin</option>
                                <option value="supplier">Supplier</option>
                                <option value="staff">Branch Staff</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="branchField" style="display:none;">
                            <label class="form-label-dark">Branch</label>
                            <select name="branch_id" id="f_u_branch" class="form-select-dark w-100">
                                <option value="">Select branch…</option>
                                <?php foreach($branchModel->getActive() as $b): ?>
                                <option value="<?= $b['branch_id'] ?>"><?= Helper::e($b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-dark">Status</label>
                            <select name="status" id="f_u_status" class="form-select-dark w-100">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12" id="supplierField" style="display:none;">
                            <label class="form-label-dark">Linked Supplier</label>
                            <select name="supplier_id" id="f_u_sup" class="form-select-dark w-100">
                                <option value="">Select supplier…</option>
                                <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['supplier_id'] ?>"><?= Helper::e($s['supplier_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveBtn" class="btn-cyan">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const nInp = document.getElementById('f_u_name');
const eInp = document.getElementById('f_u_email');
const pInp = document.getElementById('f_u_pass');
const sBtn = document.getElementById('saveBtn');

function validate() {
    let valid = true;
    const action = document.getElementById('userAction').value;

    // Name
    if(nInp.value.trim().length === 0 || nInp.value.length > 20) {
        nInp.style.borderColor = "#dc3545";
        document.getElementById('nameError').style.display = "block";
        valid = false;
    } else {
        nInp.style.borderColor = "";
        document.getElementById('nameError').style.display = "none";
    }

    // Email
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(eInp.value.trim())) {
        eInp.style.borderColor = "#dc3545";
        document.getElementById('emailError').style.display = "block";
        valid = false;
    } else {
        eInp.style.borderColor = "";
        document.getElementById('emailError').style.display = "none";
    }

    // Password
    const pLen = pInp.value.length;
    const isNew = action === 'create';
    if((isNew && (pLen === 0 || pLen > 20)) || (!isNew && pLen > 20)) {
        pInp.style.borderColor = "#dc3545";
        document.getElementById('passError').style.display = "block";
        valid = false;
    } else {
        pInp.style.borderColor = "";
        document.getElementById('passError').style.display = "none";
    }

    sBtn.disabled = !valid;
}

[nInp, eInp, pInp].forEach(el => el.addEventListener('input', validate));

function toggleSupplierField() {
    const role = document.getElementById("f_u_role").value;
    document.getElementById("supplierField").style.display = role === "supplier" ? "block" : "none";
    document.getElementById("branchField").style.display = role === "staff" ? "block" : "none";
}

function editUser(u) {
    document.getElementById("userModalTitle").textContent = "Edit User";
    document.getElementById("userAction").value = "update";
    document.getElementById("userId").value = u.id;
    document.getElementById("f_u_name").value = u.name;
    document.getElementById("f_u_email").value = u.email;
    document.getElementById("f_u_pass").value = "";
    document.getElementById("f_u_role").value = u.role;
    document.getElementById("f_u_status").value = u.status;
    document.getElementById("f_u_sup").value = u.supplier_id || "";
    document.getElementById("f_u_branch").value = u.branch_id || "";
    document.getElementById("pwdNote").textContent = "(Max 20 chars)";
    toggleSupplierField();
    validate();
    new bootstrap.Modal(document.getElementById("userModal")).show();
}

document.getElementById("userModal").addEventListener("hidden.bs.modal", function() {
    document.getElementById("userModalTitle").textContent = "Add User";
    document.getElementById("userAction").value = "create";
    this.querySelector("form").reset();
    [nInp, eInp, pInp].forEach(el => el.style.borderColor = "");
    document.querySelectorAll('.text-danger').forEach(el => el.style.display = 'none');
    sBtn.disabled = false;
    toggleSupplierField();
});
</script>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>