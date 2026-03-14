<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Redirect if already logged in
if (Session::isLoggedIn()) {
    $role = Session::get('user_role');
    if ($role === 'seller') {
        Helper::redirect('/sap-computers/index.php');
    } elseif ($role === 'staff') {
        Helper::redirect('/sap-computers/branch_orders.php');
    } else {
        Helper::redirect('/sap-computers/supplier/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Server-side validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) > 30) {
        $error = 'Password cannot exceed 30 characters.';
    } elseif (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $userModel = new UserModel();
        $user = $userModel->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            Session::set('user_id', $user['id']);
            Session::set('user_name', $user['name']);
            Session::set('user_email', $user['email']);
            Session::set('user_role', $user['role']);
            Session::set('supplier_id', $user['supplier_id']);
            Session::set('supplier_name', $user['supplier_name'] ?? null);
            Session::set('user_branch_id', $user['branch_id'] ?? null);
            Session::set('user_branch_name', $user['branch_name'] ?? null);

            $userModel->updateLastLogin($user['id']);
            Session::setFlash('success', 'Welcome back, ' . $user['name'] . '!');

            if ($user['role'] === 'seller') {
                Helper::redirect('/sap-computers/index.php');
            } elseif ($user['role'] === 'staff') {
                Helper::redirect('/sap-computers/branch_orders.php');
            } else {
                Helper::redirect('/sap-computers/supplier/dashboard.php');
            }
        } else {
            $error = 'Invalid email address or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SAP Computers IMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/sap-computers/assets/css/style.css" rel="stylesheet">
    <style>
        .error-msg { color: #ff4757; font-size: 11px; margin-top: 5px; display: none; }
        .is-invalid { border-color: #ff4757 !important; }
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="brand-icon"><i class="bi bi-cpu-fill"></i></div>
            <h1>SAP Computers</h1>
            <p>Inventory Management System</p>
        </div>

        <?php if ($error): ?>
        <div class="alert" style="background:rgba(255,71,87,0.12);border:1px solid rgba(255,71,87,0.3);color:#ff4757;border-radius:8px;padding:12px 16px;font-size:13.5px;margin-bottom:20px;">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= Helper::e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" novalidate>
            <div class="mb-4">
                <label class="form-label-dark" for="email">Email Address</label>
                <div class="position-relative">
                    <i class="bi bi-envelope-fill" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:14px;"></i>
                    <input type="email" id="email" name="email"
                        class="form-control-dark w-100" style="padding-left:42px!important;"
                        placeholder="you@sapcomputers.lk"
                        value="<?= Helper::e($_POST['email'] ?? '') ?>" required>
                </div>
                <div id="emailHint" class="error-msg">Please enter a valid email format.</div>
            </div>

            <div class="mb-4">
                <label class="form-label-dark" for="password">Password</label>
                <div class="position-relative">
                    <i class="bi bi-lock-fill" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:14px;"></i>
                    <input type="password" id="password" name="password"
                        class="form-control-dark w-100" style="padding-left:42px!important;"
                        placeholder="Enter your password" maxlength="30" required>
                    <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-dim);cursor:pointer;">
                        <i class="bi bi-eye-slash" id="passIcon"></i>
                    </button>
                </div>
                <div id="passHint" class="error-msg">Password must not exceed 30 characters.</div>
            </div>

            <button type="submit" id="submitBtn" class="btn-cyan w-100" style="justify-content:center;padding:12px;font-size:15px;">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--navy-border);">
            <div style="font-size:11px;color:var(--text-dim);text-align:center;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.06em;">Demo Credentials</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <button class="btn-ghost" onclick="fillCreds('admin@sapcomputers.lk','Admin@123')">
                    <i class="bi bi-shield-check" style="color:var(--cyan);"></i> Admin Login
                </button>
                <button class="btn-ghost" onclick="fillCreds('orders@singer.lk','Admin@123')">
                    <i class="bi bi-building" style="color:var(--success);"></i> Supplier Login
                </button>
            </div>
        </div>

        <div style="text-align:center;margin-top:20px;font-size:11px;color:var(--text-dim);">
            SAP Computers — Gampaha, Sri Lanka &copy; <?= date('Y') ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const emailInput = document.getElementById('email');
const passInput = document.getElementById('password');
const submitBtn = document.getElementById('submitBtn');
const emailHint = document.getElementById('emailHint');
const passHint = document.getElementById('passHint');

function validate() {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    let isEmailValid = emailRegex.test(emailInput.value.trim());
    let isPassValid = passInput.value.length > 0 && passInput.value.length <= 30;

    // Email UI feedback
    if (emailInput.value.length > 0 && !isEmailValid) {
        emailInput.classList.add('is-invalid');
        emailHint.style.display = 'block';
    } else {
        emailInput.classList.remove('is-invalid');
        emailHint.style.display = 'none';
    }

    // Pass UI feedback
    if (passInput.value.length > 30) {
        passInput.classList.add('is-invalid');
        passHint.style.display = 'block';
    } else {
        passInput.classList.remove('is-invalid');
        passHint.style.display = 'none';
    }

    submitBtn.disabled = !(isEmailValid && isPassValid);
}

emailInput.addEventListener('input', validate);
passInput.addEventListener('input', validate);

function togglePass() {
    const icon = document.getElementById('passIcon');
    if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.className = 'bi bi-eye';
    } else {
        passInput.type = 'password';
        icon.className = 'bi bi-eye-slash';
    }
}

function fillCreds(email, pass) {
    emailInput.value = email;
    passInput.value = pass;
    validate();
}
</script>
</body>
</html>