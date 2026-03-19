<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$error   = '';
$success = '';

// Preserve field values on error
$old = ['name' => '', 'email' => '', 'phone' => ''];

// Validation functions
function validateName($name) {
    if (empty($name)) return 'Name is required.';
    if (strlen($name) < 2) return 'Name must be at least 2 characters.';
    if (strlen($name) > 50) return 'Name cannot exceed 50 characters.';
    if (!preg_match('/^[a-zA-Z\s]+$/', $name)) return 'Name can only contain letters and spaces.';
    return null;
}

function validateEmail($email) {
    if (empty($email)) return 'Email is required.';
    if (strlen($email) > 100) return 'Email cannot exceed 100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
    return null;
}

function validatePhone($phone) {
    if (empty($phone)) return 'Phone number is required.';
    $phone = preg_replace('/\s+/', '', $phone);
    if (!preg_match('/^(\+94|0)[0-9]{9}$/', $phone)) return 'Phone must be in format: +94 77 000 0000 or 077 000 0000.';
    return null;
}

function validatePassword($password) {
    if (empty($password)) return 'Password is required.';
    if (strlen($password) < 8) return 'Password must be at least 8 characters.';
    if (strlen($password) > 128) return 'Password cannot exceed 128 characters.';
    if (!preg_match('/[A-Z]/', $password)) return 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) return 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) return 'Password must contain at least one number.';
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/]/', $password)) return 'Password must contain at least one special character (!@#$%^&*).';
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = isset($_POST['name'])             ? trim($_POST['name'])             : '';
    $email            = isset($_POST['email'])            ? trim($_POST['email'])            : '';
    $phone            = isset($_POST['phone'])            ? trim($_POST['phone'])            : '';
    $password         = isset($_POST['password'])         ? $_POST['password']               : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password']       : '';

    $old = ['name' => $name, 'email' => $email, 'phone' => $phone];

    // Validate all fields
    $nameErr = validateName($name);
    $emailErr = validateEmail($email);
    $phoneErr = validatePhone($phone);
    $passwordErr = validatePassword($password);
    
    if ($nameErr) {
        $error = $nameErr;
    } elseif ($emailErr) {
        $error = $emailErr;
    } elseif ($phoneErr) {
        $error = $phoneErr;
    } elseif ($passwordErr) {
        $error = $passwordErr;
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $pdo->prepare("INSERT INTO customers (name, email, phone, password, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt->execute([$name, $email, $phone, $hashed])) {
                    $_SESSION['success'] = 'Account created successfully! Please log in.';
                    header('Location: login.php');
                    exit;
                } else {
                    $error = 'Error creating account. Please try again.';
                }
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $error = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — SAP Computers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:      #0a0a0f;
  --bg2:     #0f0f17;
  --surface: #13131e;
  --edge:    rgba(255,255,255,.06);
  --edge2:   rgba(255,255,255,.11);
  --edge3:   rgba(255,255,255,.2);
  --accent:  #ff2d55;
  --accent2: #e0002a;
  --ag2:     rgba(255,45,85,.06);
  --green:   #30d158;
  --gold:    #f5a623;
  --cyan:    #00d4c8;
  --t1:      #f5f5fa;
  --t2:      rgba(245,245,250,.65);
  --t3:      rgba(245,245,250,.38);
  --t4:      rgba(245,245,250,.14);
  --r2:      12px;
  --r3:      16px;
  --r4:      20px;
}
html { font-size: 14px; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--t1);
  min-height: 100vh;
  overflow-x: hidden;
  line-height: 1.5;
}
a { color: inherit; text-decoration: none; }

/* ── BACKGROUND ── */
.page-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; }
.orb { position: absolute; border-radius: 50%; filter: blur(110px); pointer-events: none; }
.orb-1 { width: 550px; height: 550px; background: rgba(255,45,85,.08);  top: -160px; right: -120px; }
.orb-2 { width: 480px; height: 480px; background: rgba(0,212,200,.05);  bottom: -120px; left: -100px; }
.orb-3 { width: 280px; height: 280px; background: rgba(255,45,85,.04);  top: 50%; left: 40%; }
.noise {
  position: absolute; inset: 0; opacity: .02;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size: 200px;
}

/* ── TOP BAR ── */
.top-bar {
  position: relative; z-index: 10;
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 32px;
  border-bottom: 1px solid var(--edge);
  background: rgba(10,10,15,.7);
  backdrop-filter: blur(16px);
}
.logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.5rem; letter-spacing: -.03em; }
.logo img { height: 34px; width: auto; display: block; }
.logo-dot { color: var(--accent); }
.back-link { display: flex; align-items: center; gap: 7px; font-size: .8rem; font-weight: 500; color: var(--t3); transition: color .18s; }
.back-link:hover { color: var(--t1); }

/* ── PAGE WRAP ── */
.page-wrap {
  position: relative; z-index: 1;
  min-height: calc(100vh - 65px);
  display: flex; align-items: center; justify-content: center;
  padding: 48px 20px;
}

/* ── SPLIT ── */
.auth-split {
  display: grid;
  grid-template-columns: 1fr 460px;
  max-width: 980px;
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--edge2);
  border-radius: var(--r4);
  overflow: hidden;
  box-shadow: 0 40px 100px rgba(0,0,0,.7);
  animation: fadeUp .45s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }

/* LEFT PANEL */
.auth-left {
  background: linear-gradient(145deg, #00100e 0%, #001814 50%, #000f0c 100%);
  padding: 52px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}
.auth-left::before {
  content: '';
  position: absolute; top: -80px; left: -60px;
  width: 380px; height: 380px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,212,200,.1), transparent 70%);
  pointer-events: none;
}
.auth-left::after {
  content: '';
  position: absolute; bottom: -70px; right: -50px;
  width: 280px; height: 280px; border-radius: 50%;
  background: radial-gradient(circle, rgba(48,209,88,.07), transparent 70%);
  pointer-events: none;
}
.left-brand { position: relative; z-index: 1; }
.left-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.6rem; letter-spacing: -.03em; margin-bottom: 5px; }
.left-logo span { color: var(--cyan); }
.left-tagline { font-size: .8rem; color: var(--t3); }

.left-hero { position: relative; z-index: 1; }
.left-headline {
  font-family: 'Syne', sans-serif;
  font-weight: 800; font-size: 2rem; line-height: 1.1;
  letter-spacing: -.03em; margin-bottom: 14px;
}
.left-headline span { color: var(--cyan); }
.left-sub { font-size: .86rem; color: var(--t2); line-height: 1.65; margin-bottom: 28px; }

/* STEPS */
.steps { display: flex; flex-direction: column; gap: 0; }
.step {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 14px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
  position: relative;
}
.step:last-child { border: none; }
.step-num {
  width: 30px; height: 30px; border-radius: 50%;
  background: rgba(0,212,200,.1); border: 1px solid rgba(0,212,200,.22);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-weight: 700; font-size: .78rem;
  color: var(--cyan); flex-shrink: 0;
}
.step-text { font-size: .82rem; color: var(--t2); line-height: 1.4; padding-top: 5px; }
.step-text strong { color: var(--t1); display: block; margin-bottom: 2px; font-weight: 600; }

.left-deco-word {
  position: absolute; bottom: -24px; right: -16px;
  font-family: 'Syne', sans-serif; font-weight: 800; font-size: 7.5rem;
  color: rgba(255,255,255,.022); letter-spacing: -.06em;
  pointer-events: none; line-height: 1;
}

/* RIGHT PANEL */
.auth-right {
  padding: 44px 44px;
  background: var(--surface);
  display: flex; flex-direction: column; justify-content: center;
}

/* TABS */
.auth-tabs {
  display: flex;
  background: var(--bg2); border: 1px solid var(--edge);
  border-radius: 100px; padding: 4px; margin-bottom: 30px;
}
.auth-tab {
  flex: 1; padding: 9px 16px; text-align: center;
  border: none; background: transparent;
  font-family: 'DM Sans', sans-serif; font-size: .82rem; font-weight: 600;
  color: var(--t3); cursor: pointer; border-radius: 100px; transition: all .2s;
}
.auth-tab.active { background: var(--accent); color: #fff; }
.auth-tab:not(.active):hover { color: var(--t1); }

/* HEADING */
.form-heading { margin-bottom: 22px; }
.form-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.35rem; letter-spacing: -.02em; margin-bottom: 4px; }
.form-sub { font-size: .8rem; color: var(--t3); }

/* ALERT */
.alert {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 16px; border-radius: var(--r2);
  font-size: .82rem; margin-bottom: 20px;
  animation: fadeUp .3s ease both;
}
.alert-error  { background: rgba(255,45,85,.08);  border: 1px solid rgba(255,45,85,.22);  color: #ff6b82; }
.alert-success{ background: rgba(48,209,88,.08);  border: 1px solid rgba(48,209,88,.22);  color: var(--green); }
.alert i { margin-top: 1px; flex-shrink: 0; }

/* TWO-COL */
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* FORM FIELDS */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: .77rem; font-weight: 600; color: var(--t2); margin-bottom: 6px; letter-spacing: .02em; }
.input-wrap { position: relative; }
.input-ico {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: var(--t4); font-size: .82rem; pointer-events: none; transition: color .2s;
}
.form-input {
  width: 100%;
  background: var(--bg2); border: 1.5px solid var(--edge2);
  border-radius: var(--r2);
  padding: 11px 14px 11px 40px;
  color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: .87rem;
  outline: none; transition: border-color .2s, box-shadow .2s;
}
.form-input::placeholder { color: var(--t4); }
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--ag2); }
.form-input.valid   { border-color: rgba(48,209,88,.4); }
.form-input.invalid { border-color: rgba(255,45,85,.4); }
.input-toggle {
  position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: var(--t4);
  cursor: pointer; font-size: .82rem; padding: 3px; transition: color .18s;
}
.input-toggle:hover { color: var(--t2); }
.field-hint { font-size: .7rem; color: var(--t4); margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.field-hint.ok  { color: var(--green); }
.field-hint.err { color: #ff6b82; }

/* PASSWORD STRENGTH */
.pw-strength { margin-top: 8px; }
.pw-bars { display: flex; gap: 4px; margin-bottom: 5px; }
.pw-bar { flex: 1; height: 3px; border-radius: 2px; background: var(--edge); transition: background .3s; }
.pw-bar.weak   { background: #ff4444; }
.pw-bar.medium { background: var(--gold); }
.pw-bar.strong { background: var(--green); }
.pw-label { font-size: .68rem; color: var(--t4); transition: color .3s; }
.pw-label.weak   { color: #ff4444; }
.pw-label.medium { color: var(--gold); }
.pw-label.strong { color: var(--green); }

/* TERMS */
.terms-wrap {
  display: flex; align-items: flex-start; gap: 10px;
  font-size: .78rem; color: var(--t2); cursor: pointer;
  margin-bottom: 20px; user-select: none;
}
.terms-wrap input[type="checkbox"] { display: none; }
.custom-check {
  width: 17px; height: 17px; border: 1.5px solid var(--edge2);
  border-radius: 4px; background: var(--bg2);
  display: flex; align-items: center; justify-content: center;
  transition: all .18s; flex-shrink: 0; margin-top: 1px;
}
.terms-wrap input:checked ~ .custom-check { background: var(--accent); border-color: var(--accent); }
.custom-check::after {
  content: '';
  width: 5px; height: 8px;
  border: 2px solid #fff; border-top: none; border-left: none;
  transform: rotate(42deg) translateY(-1px); opacity: 0; transition: opacity .15s;
}
.terms-wrap input:checked ~ .custom-check::after { opacity: 1; }
.terms-wrap a { color: var(--accent); font-weight: 600; }
.terms-wrap a:hover { text-decoration: underline; }

/* SUBMIT */
.btn-submit {
  width: 100%; background: var(--accent); border: none; border-radius: 100px;
  color: #fff; font-family: 'DM Sans', sans-serif; font-size: .9rem; font-weight: 700;
  padding: 13px; cursor: pointer; transition: all .22s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-submit:hover { background: var(--accent2); transform: translateY(-2px); box-shadow: 0 12px 32px rgba(255,45,85,.3); }
.btn-submit:active { transform: translateY(0); }
.btn-submit:disabled { opacity: .6; cursor: not-allowed; transform: none; box-shadow: none; }

/* SWITCH LINK */
.switch-link { text-align: center; font-size: .8rem; color: var(--t3); margin-top: 20px; }
.switch-link a { color: var(--accent); font-weight: 600; transition: color .18s; }
.switch-link a:hover { color: #ff6b82; }

/* RESPONSIVE */
@media (max-width: 860px) {
  .auth-split { grid-template-columns: 1fr; max-width: 480px; }
  .auth-left  { display: none; }
  .auth-right { padding: 36px 28px; }
  .top-bar    { padding: 16px 20px; }
}
@media (max-width: 480px) {
  .auth-right   { padding: 28px 18px; }
  .form-row-2   { grid-template-columns: 1fr; }
  .page-wrap    { padding: 20px 12px; }
}
</style>
</head>
<body>

<!-- BG -->
<div class="page-bg">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
  <div class="noise"></div>
</div>

<!-- TOP BAR -->
<div class="top-bar">
  <a href="index.php" class="logo">
    <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers">
  </a>
  <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Store</a>
</div>

<!-- PAGE -->
<div class="page-wrap">
  <div class="auth-split">

    <!-- LEFT -->
    <div class="auth-left">
      <div class="left-brand">
        <div class="left-logo">SAP<span>.</span></div>
        <div class="left-tagline">Gampaha's #1 Tech Store</div>
      </div>

      <div class="left-hero">
        <div class="left-headline">Join the<br><span>SAP Family</span><br>Today</div>
        <div class="left-sub">Create a free account and unlock exclusive member benefits, faster checkout, and order tracking.</div>

        <div class="steps">
          <div class="step">
            <div class="step-num">1</div>
            <div class="step-text">
              <strong>Fill in your details</strong>
              Name, email, phone & a secure password.
            </div>
          </div>
          <div class="step">
            <div class="step-num">2</div>
            <div class="step-text">
              <strong>Verify & sign in</strong>
              Log in immediately — no email verification needed.
            </div>
          </div>
          <div class="step">
            <div class="step-num">3</div>
            <div class="step-text">
              <strong>Unlock your 10% coupon</strong>
              Use code <strong style="color:var(--cyan)">SAP10</strong> on your first order.
            </div>
          </div>
        </div>
      </div>

      <div style="font-size:.72rem;color:var(--t4);position:relative;z-index:1">
        © <?= date('Y') ?> SAP Computers, Gampaha.
      </div>

      <div class="left-deco-word">NEW</div>
    </div>

    <!-- RIGHT -->
    <div class="auth-right">

      <!-- TABS -->
      <div class="auth-tabs">
        <button class="auth-tab" onclick="window.location.href='login.php'">Sign In</button>
        <button class="auth-tab active">Create Account</button>
      </div>

      <!-- HEADING -->
      <div class="form-heading">
        <div class="form-title">Create your free account</div>
        <div class="form-sub">Takes less than a minute — no credit card needed</div>
      </div>

      <!-- ERROR -->
      <?php if ($error): ?>
        <div class="alert alert-error">
          <i class="fas fa-circle-exclamation"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- FORM -->
      <form method="POST" id="regForm" novalidate>

        <!-- Name + Phone -->
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="name">Full Name <span style="color:var(--accent)">*</span></label>
            <div class="input-wrap">
              <input class="form-input" type="text" id="name" name="name"
                     placeholder="John Silva"
                     value="<?= htmlspecialchars($old['name']) ?>"
                     minlength="2" maxlength="50"
                     pattern="[a-zA-Z\s]+"
                     required autocomplete="name"
                     oninput="validateField(this)">
              <i class="fas fa-user input-ico"></i>
            </div>
            <div class="field-hint" id="nameHint"></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="phone">Phone Number <span style="color:var(--accent)">*</span></label>
            <div class="input-wrap">
              <input class="form-input" type="tel" id="phone" name="phone"
                     placeholder="+94 77 000 0000"
                     value="<?= htmlspecialchars($old['phone']) ?>"
                     pattern="\+94[0-9]{9}|0[0-9]{9}"
                     maxlength="13"
                     inputmode="numeric"
                     required autocomplete="tel"
                     oninput="validatePhoneInput(this); validateField(this)">
              <i class="fas fa-phone input-ico"></i>
            </div>
            <div class="field-hint" id="phoneHint"></div>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email">Email Address <span style="color:var(--accent)">*</span></label>
          <div class="input-wrap">
            <input class="form-input" type="email" id="email" name="email"
                   placeholder="you@example.com"
                   value="<?= htmlspecialchars($old['email']) ?>"
                   maxlength="100"
                   pattern="[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}"
                   required autocomplete="email"
                   oninput="validateField(this)">
            <i class="fas fa-envelope input-ico"></i>
          </div>
          <div class="field-hint" id="emailHint"></div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label" for="password">Password <span style="color:var(--accent)">*</span></label>
          <div class="input-wrap">
            <input class="form-input" type="password" id="password" name="password"
                   placeholder="Min 8 chars: uppercase, lowercase, number, special char"
                   minlength="8" maxlength="128"
                   required autocomplete="new-password"
                   oninput="checkStrength(this.value)">
            <i class="fas fa-lock input-ico"></i>
            <button type="button" class="input-toggle" onclick="togglePw('password','pwIcon1')">
              <i class="fas fa-eye" id="pwIcon1"></i>
            </button>
          </div>
          <div class="pw-strength">
            <div class="pw-bars">
              <div class="pw-bar" id="bar1"></div>
              <div class="pw-bar" id="bar2"></div>
              <div class="pw-bar" id="bar3"></div>
              <div class="pw-bar" id="bar4"></div>
            </div>
            <div class="pw-label" id="pwLabel">Enter a password</div>
          </div>
          <div style="font-size:.7rem;color:var(--t4);margin-top:8px;line-height:1.5">
            <div id="req1"><i class="fas fa-circle" style="font-size:.4rem;margin-right:4px"></i> At least 8 characters</div>
            <div id="req2"><i class="fas fa-circle" style="font-size:.4rem;margin-right:4px"></i> Uppercase letter (A-Z)</div>
            <div id="req3"><i class="fas fa-circle" style="font-size:.4rem;margin-right:4px"></i> Lowercase letter (a-z)</div>
            <div id="req4"><i class="fas fa-circle" style="font-size:.4rem;margin-right:4px"></i> Number (0-9)</div>
            <div id="req5"><i class="fas fa-circle" style="font-size:.4rem;margin-right:4px"></i> Special character (!@#$%^&*)</div>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm Password <span style="color:var(--accent)">*</span></label>
          <div class="input-wrap">
            <input class="form-input" type="password" id="confirm_password" name="confirm_password"
                   placeholder="Repeat your password"
                   maxlength="128"
                   required autocomplete="new-password"
                   oninput="checkMatch()">
            <i class="fas fa-lock-open input-ico"></i>
            <button type="button" class="input-toggle" onclick="togglePw('confirm_password','pwIcon2')">
              <i class="fas fa-eye" id="pwIcon2"></i>
            </button>
          </div>
          <div class="field-hint" id="matchHint" style="display:none"></div>
        </div>

        <!-- Terms -->
        <label class="terms-wrap">
          <input type="checkbox" id="terms" required>
          <span class="custom-check"></span>
          I agree to the <a href="terms.php" target="_blank">Terms of Service</a> &amp; <a href="privacy.php" target="_blank">Privacy Policy</a>
        </label>

        <button type="submit" class="btn-submit" id="submitBtn">
          <i class="fas fa-user-plus"></i> Create My Account
        </button>
      </form>

      <div class="switch-link">
        Already have an account? <a href="login.php">Sign in here</a>
      </div>

    </div><!-- /auth-right -->
  </div><!-- /auth-split -->
</div>

<script>
/* Password visibility toggle */
function togglePw(fieldId, iconId) {
  const inp = document.getElementById(fieldId);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else                         { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}

/* Prevent letters in phone field - only allow numbers, +, and spaces */
function validatePhoneInput(field) {
  let val = field.value;
  const filtered = val.replace(/[^0-9+\s]/g, '');
  if (val !== filtered) {
    field.value = filtered;
  }
}

/* Real-time field validation */
function validateField(field) {
  const hintId = field.id + 'Hint';
  const hint = document.getElementById(hintId);
  if (!hint) return;

  let isValid = true;
  let message = '';

  if (field.id === 'name') {
    const val = field.value.trim();
    if (val.length < 2) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> At least 2 characters'; }
    else if (val.length > 50) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Max 50 characters'; }
    else if (!/^[a-zA-Z\s]+$/.test(val)) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Letters and spaces only'; }
    else { isValid = true; message = '<i class="fas fa-check-circle"></i> Name looks good'; }
  }

  if (field.id === 'email') {
    const val = field.value.trim();
    // Strict email validation: must have local@domain.extension format
    const emailRegex = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;
    if (val.length === 0) { isValid = false; message = ''; }
    else if (val.includes(' ')) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Email cannot contain spaces'; }
    else if (!val.includes('@')) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Email must contain @'; }
    else if (!emailRegex.test(val)) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Invalid email format (example: user@domain.com)'; }
    else if (val.length > 100) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Email too long'; }
    else { isValid = true; message = '<i class="fas fa-check-circle"></i> Email looks good'; }
  }

  if (field.id === 'phone') {
    const val = field.value.trim();
    const phoneRegex = /^(\+94[0-9]{9}|0[0-9]{9})$/;
    if (val.length === 0) { isValid = false; message = ''; }
    else if (!phoneRegex.test(val)) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Use +94 77 000 0000 format'; }
    else { isValid = true; message = '<i class="fas fa-check-circle"></i> Phone looks good'; }
  }

  hint.innerHTML = message;
  hint.style.display = message ? 'flex' : 'none';
  hint.className = isValid && message ? 'field-hint ok' : !isValid && message ? 'field-hint err' : 'field-hint';
  field.className = isValid && message ? 'form-input valid' : !isValid && message ? 'form-input invalid' : 'form-input';
}

/* Enhanced password strength meter */
function checkStrength(val) {
  const bars = [document.getElementById('bar1'), document.getElementById('bar2'),
                document.getElementById('bar3'), document.getElementById('bar4')];
  const label = document.getElementById('pwLabel');
  bars.forEach(b => b.className = 'pw-bar');
  label.className = 'pw-label';

  // Update requirements
  const req1 = document.getElementById('req1');
  const req2 = document.getElementById('req2');
  const req3 = document.getElementById('req3');
  const req4 = document.getElementById('req4');
  const req5 = document.getElementById('req5');
  const passPwField = document.getElementById('password');

  const hasLength = val.length >= 8;
  const hasUpper = /[A-Z]/.test(val);
  const hasLower = /[a-z]/.test(val);
  const hasNum = /[0-9]/.test(val);
  const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/]/.test(val);

  // Update requirement indicators
  updateReq(req1, hasLength);
  updateReq(req2, hasUpper);
  updateReq(req3, hasLower);
  updateReq(req4, hasNum);
  updateReq(req5, hasSpecial);

  let score = 0;
  if (hasLength) score++;
  if (hasUpper && hasLower) score++;
  if (hasNum) score++;
  if (hasSpecial) score++;

  if (!val) { 
    label.textContent = 'Enter a password';
    passPwField.className = 'form-input';
    return;
  }

  const cls = score <= 1 ? 'weak' : score <= 2 ? 'medium' : 'strong';
  const txt = score <= 1 ? 'Weak' : score <= 2 ? 'Fair' : score === 3 ? 'Good' : 'Strong';
  
  for (let i = 0; i < score; i++) bars[i].classList.add(cls);
  label.classList.add(cls);
  label.textContent = txt;
  passPwField.className = cls === 'strong' ? 'form-input valid' : cls === 'medium' ? 'form-input' : 'form-input invalid';
}

function updateReq(elem, isValid) {
  if (isValid) {
    elem.style.color = 'var(--green)';
    elem.innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--green)"></i>' + elem.textContent.split(' ').slice(1).join(' ');
  } else {
    elem.style.color = 'var(--t4)';
    elem.innerHTML = '<i class="fas fa-circle" style="font-size:.4rem;margin-right:4px"></i>' + elem.textContent.split(' ').slice(1).join(' ');
  }
}

/* Confirm match hint */
function checkMatch() {
  const pw  = document.getElementById('password').value;
  const cpw = document.getElementById('confirm_password').value;
  const inp = document.getElementById('confirm_password');
  const h   = document.getElementById('matchHint');
  if (!cpw) { h.style.display = 'none'; inp.className = 'form-input'; return; }
  if (pw === cpw) {
    h.style.display = 'flex'; h.className = 'field-hint ok';
    h.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
    inp.className = 'form-input valid';
  } else {
    h.style.display = 'flex'; h.className = 'field-hint err';
    h.innerHTML = '<i class="fas fa-xmark-circle"></i> Passwords do not match';
    inp.className = 'form-input invalid';
  }
}

/* Checkbox click */
document.querySelectorAll('.terms-wrap').forEach(wrap => {
  wrap.addEventListener('click', e => {
    if (e.target.tagName === 'A') return; // allow link clicks
    const cb = wrap.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
  });
});

/* Input focus colour */
document.querySelectorAll('.form-input').forEach(inp => {
  inp.addEventListener('focus', () => {
    const ico = inp.closest('.input-wrap').querySelector('.input-ico');
    if (ico) ico.style.color = 'var(--accent)';
  });
  inp.addEventListener('blur', () => {
    const ico = inp.closest('.input-wrap').querySelector('.input-ico');
    if (ico) ico.style.color = '';
  });
});

/* Submit loading state + basic client-side guard */
document.getElementById('regForm').addEventListener('submit', function(e) {
  const pw  = document.getElementById('password').value;
  const cpw = document.getElementById('confirm_password').value;
  const terms = document.getElementById('terms').checked;

  if (pw !== cpw) { e.preventDefault(); alert('Passwords do not match.'); return; }
  if (!terms)     { e.preventDefault(); alert('Please agree to the Terms of Service.'); return; }

  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';
  btn.disabled = true;
});
</script>
</body>
</html>