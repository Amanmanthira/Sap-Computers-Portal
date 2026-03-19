<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'login') {
        $email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
        $password = isset($_POST['password']) ? $_POST['password']       : '';

        // Validate inputs
        $emailErr = '';
        $passwordErr = '';

        // Email validation
        if (empty($email)) {
            $emailErr = 'Email is required.';
        } elseif (strlen($email) > 100) {
            $emailErr = 'Email is too long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = 'Please enter a valid email address.';
        }

        // Password validation
        if (empty($password)) {
            $passwordErr = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $passwordErr = 'Invalid email or password.';
        } elseif (strlen($password) > 128) {
            $passwordErr = 'Invalid email or password.';
        }

        if (!$emailErr && !$passwordErr && $email && $password) {
            try {
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare("SELECT customer_id, name, password FROM customers WHERE email = ?");
                $stmt->execute([$email]);
                $customer = $stmt->fetch();

                if ($customer && password_verify($password, $customer['password'])) {
                    $_SESSION['customer_id']   = $customer['customer_id'];
                    $_SESSION['customer_name'] = $customer['name'];
                    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Invalid email or password. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $error = 'A database error occurred. Please try again later.';
            }
        } else {
            $error = $emailErr ?: $passwordErr ?: 'Please enter your email and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SAP Computers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:      #0a0a0f;
  --bg2:     #0f0f17;
  --surface: #13131e;
  --card:    #16162280;
  --edge:    rgba(255,255,255,.06);
  --edge2:   rgba(255,255,255,.11);
  --edge3:   rgba(255,255,255,.2);
  --accent:  #ff2d55;
  --accent2: #e0002a;
  --ag:      rgba(255,45,85,.18);
  --ag2:     rgba(255,45,85,.06);
  --green:   #30d158;
  --gold:    #f5a623;
  --t1:      #f5f5fa;
  --t2:      rgba(245,245,250,.65);
  --t3:      rgba(245,245,250,.38);
  --t4:      rgba(245,245,250,.14);
  --r:       8px;
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
.page-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  background: var(--bg);
  overflow: hidden;
}
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(110px);
  pointer-events: none;
}
.orb-1 { width: 600px; height: 600px; background: rgba(255,45,85,.09); top: -180px; left: -160px; }
.orb-2 { width: 500px; height: 500px; background: rgba(61,142,248,.06); bottom: -140px; right: -120px; }
.orb-3 { width: 300px; height: 300px; background: rgba(255,45,85,.05); bottom: 20%; left: 30%; }
.noise {
  position: absolute;
  inset: 0;
  opacity: .02;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size: 200px;
}

/* ── HEADER ── */
.top-bar {
  position: relative;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 32px;
  border-bottom: 1px solid var(--edge);
  background: rgba(10,10,15,.7);
  backdrop-filter: blur(16px);
}
.logo {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 1.5rem;
  letter-spacing: -.03em;
  display: flex;
  align-items: center;
  gap: 2px;
}
.logo img { height: 34px; width: auto; }
.logo-dot { color: var(--accent); }
.back-link {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: .8rem;
  font-weight: 500;
  color: var(--t3);
  transition: color .18s;
}
.back-link:hover { color: var(--t1); }
.back-link i { font-size: .75rem; }

/* ── LAYOUT ── */
.page-wrap {
  position: relative;
  z-index: 1;
  min-height: calc(100vh - 65px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
}

/* ── SPLIT PANEL ── */
.auth-split {
  display: grid;
  grid-template-columns: 1fr 420px;
  max-width: 940px;
  width: 100%;
  gap: 0;
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
  background: linear-gradient(145deg, #0e0014 0%, #1a0008 50%, #0a0010 100%);
  padding: 52px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}
.auth-left::before {
  content: '';
  position: absolute;
  top: -80px;
  right: -80px;
  width: 360px;
  height: 360px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,45,85,.12), transparent 70%);
  pointer-events: none;
}
.auth-left::after {
  content: '';
  position: absolute;
  bottom: -60px;
  left: -60px;
  width: 260px;
  height: 260px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,45,85,.07), transparent 70%);
  pointer-events: none;
}
.left-brand { position: relative; z-index: 1; }
.left-logo {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 1.6rem;
  letter-spacing: -.03em;
  margin-bottom: 6px;
}
.left-logo span { color: var(--accent); }
.left-tagline { font-size: .8rem; color: var(--t3); }

.left-hero { position: relative; z-index: 1; }
.left-headline {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 2rem;
  line-height: 1.1;
  letter-spacing: -.03em;
  margin-bottom: 14px;
}
.left-headline span { color: var(--accent); }
.left-sub { font-size: .86rem; color: var(--t2); line-height: 1.65; margin-bottom: 28px; }

.perks { display: flex; flex-direction: column; gap: 11px; }
.perk {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: .82rem;
  color: var(--t2);
}
.perk-ico {
  width: 30px;
  height: 30px;
  border-radius: var(--r);
  background: rgba(255,45,85,.1);
  border: 1px solid rgba(255,45,85,.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
  color: var(--accent);
  flex-shrink: 0;
}

.left-deco-word {
  position: absolute;
  bottom: -30px;
  right: -20px;
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 8rem;
  color: rgba(255,255,255,.025);
  letter-spacing: -.06em;
  pointer-events: none;
  line-height: 1;
}

/* RIGHT PANEL */
.auth-right {
  padding: 48px 44px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: var(--surface);
}

/* TABS */
.auth-tabs {
  display: flex;
  background: var(--bg2);
  border: 1px solid var(--edge);
  border-radius: 100px;
  padding: 4px;
  margin-bottom: 34px;
  gap: 0;
}
.auth-tab {
  flex: 1;
  padding: 9px 16px;
  text-align: center;
  border: none;
  background: transparent;
  font-family: 'DM Sans', sans-serif;
  font-size: .82rem;
  font-weight: 600;
  color: var(--t3);
  cursor: pointer;
  border-radius: 100px;
  transition: all .2s;
  white-space: nowrap;
}
.auth-tab.active {
  background: var(--accent);
  color: #fff;
}
.auth-tab:not(.active):hover { color: var(--t1); }

/* HEADING */
.form-heading { margin-bottom: 28px; }
.form-title {
  font-family: 'Syne', sans-serif;
  font-weight: 700;
  font-size: 1.45rem;
  letter-spacing: -.02em;
  margin-bottom: 5px;
}
.form-sub { font-size: .82rem; color: var(--t3); }

/* ERROR / SUCCESS */
.alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  border-radius: var(--r2);
  font-size: .82rem;
  margin-bottom: 22px;
  animation: fadeUp .3s ease both;
}
.alert-error {
  background: rgba(255,45,85,.08);
  border: 1px solid rgba(255,45,85,.22);
  color: #ff6b82;
}
.alert-success {
  background: rgba(48,209,88,.08);
  border: 1px solid rgba(48,209,88,.22);
  color: var(--green);
}
.alert i { margin-top: 1px; flex-shrink: 0; }

/* FORM */
.form-group { margin-bottom: 18px; }
.form-label {
  display: block;
  font-size: .78rem;
  font-weight: 600;
  color: var(--t2);
  margin-bottom: 7px;
  letter-spacing: .02em;
}
.input-wrap { position: relative; }
.input-ico {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--t4);
  font-size: .85rem;
  pointer-events: none;
  transition: color .2s;
}
.form-input {
  width: 100%;
  background: var(--bg2);
  border: 1.5px solid var(--edge2);
  border-radius: var(--r2);
  padding: 12px 14px 12px 40px;
  color: var(--t1);
  font-family: 'DM Sans', sans-serif;
  font-size: .88rem;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.form-input::placeholder { color: var(--t4); }
.form-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 4px var(--ag2);
}
.form-input:focus + .input-ico,
.input-wrap:focus-within .input-ico { color: var(--accent); }
.input-toggle {
  position: absolute;
  right: 13px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--t4);
  cursor: pointer;
  font-size: .85rem;
  padding: 3px;
  transition: color .18s;
}
.input-toggle:hover { color: var(--t2); }

/* REMEMBER / FORGOT */
.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
  gap: 10px;
}
.checkbox-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: .8rem;
  color: var(--t2);
  user-select: none;
}
.checkbox-wrap input[type="checkbox"] { display: none; }
.custom-check {
  width: 16px;
  height: 16px;
  border: 1.5px solid var(--edge2);
  border-radius: 4px;
  background: var(--bg2);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .18s;
  flex-shrink: 0;
}
.checkbox-wrap input:checked ~ .custom-check {
  background: var(--accent);
  border-color: var(--accent);
}
.custom-check::after {
  content: '';
  width: 5px;
  height: 8px;
  border: 2px solid #fff;
  border-top: none;
  border-left: none;
  transform: rotate(42deg) translateY(-1px);
  opacity: 0;
  transition: opacity .15s;
}
.checkbox-wrap input:checked ~ .custom-check::after { opacity: 1; }
.forgot-link {
  font-size: .78rem;
  color: var(--accent);
  font-weight: 500;
  transition: color .18s;
  white-space: nowrap;
}
.forgot-link:hover { color: #ff6b82; }

/* SUBMIT */
.btn-submit {
  width: 100%;
  background: var(--accent);
  border: none;
  border-radius: 100px;
  color: #fff;
  font-family: 'DM Sans', sans-serif;
  font-size: .9rem;
  font-weight: 700;
  padding: 14px;
  cursor: pointer;
  transition: all .22s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  letter-spacing: .01em;
}
.btn-submit:hover {
  background: var(--accent2);
  transform: translateY(-2px);
  box-shadow: 0 12px 32px rgba(255,45,85,.3);
}
.btn-submit:active { transform: translateY(0); }

/* DIVIDER */
.or-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 22px 0;
  font-size: .75rem;
  color: var(--t4);
}
.or-divider::before,
.or-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--edge);
}

/* SOCIAL BTNS */
.social-btns { display: flex; gap: 8px; margin-bottom: 0; }
.social-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px 14px;
  border-radius: var(--r2);
  border: 1.5px solid var(--edge2);
  background: var(--bg2);
  color: var(--t2);
  font-family: 'DM Sans', sans-serif;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
}
.social-btn:hover { border-color: var(--edge3); background: rgba(255,255,255,.05); color: var(--t1); }
.social-btn i { font-size: 1rem; }
.social-btn .fb { color: #1877f2; }
.social-btn .gg { color: #ea4335; }

/* FOOTER LINK */
.switch-link {
  text-align: center;
  font-size: .8rem;
  color: var(--t3);
  margin-top: 24px;
}
.switch-link a { color: var(--accent); font-weight: 600; transition: color .18s; }
.switch-link a:hover { color: #ff6b82; }

/* RESPONSIVE */
@media (max-width: 768px) {
  .auth-split { grid-template-columns: 1fr; max-width: 440px; }
  .auth-left { display: none; }
  .auth-right { padding: 36px 28px; }
  .top-bar { padding: 16px 20px; }
}
@media (max-width: 420px) {
  .auth-right { padding: 28px 20px; }
  .social-btns { flex-direction: column; }
  .page-wrap { padding: 24px 12px; }
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
        <div class="left-headline">Welcome<br>back to<br><span>SAP Computers</span></div>
        <div class="left-sub">Sign in to manage your orders, track deliveries, and access exclusive member deals.</div>
        <div class="perks">
          <div class="perk">
            <div class="perk-ico"><i class="fas fa-tag"></i></div>
            <span>Member-only discounts & early access deals</span>
          </div>
          <div class="perk">
            <div class="perk-ico"><i class="fas fa-box-open"></i></div>
            <span>Real-time order tracking & history</span>
          </div>
          <div class="perk">
            <div class="perk-ico"><i class="fas fa-heart"></i></div>
            <span>Save products to your wishlist</span>
          </div>
          <div class="perk">
            <div class="perk-ico"><i class="fas fa-headset"></i></div>
            <span>Priority customer support, Mon–Sun</span>
          </div>
        </div>
      </div>

      <div style="font-size:.72rem;color:var(--t4);position:relative;z-index:1">
        © <?= date('Y') ?> SAP Computers, Gampaha.
      </div>

      <div class="left-deco-word">SAP</div>
    </div>

    <!-- RIGHT -->
    <div class="auth-right">

      <!-- TABS -->
      <div class="auth-tabs">
        <button class="auth-tab active" onclick="window.location.href='login.php'">Sign In</button>
        <button class="auth-tab" onclick="window.location.href='register.php'">Create Account</button>
      </div>

      <!-- HEADING -->
      <div class="form-heading">
        <div class="form-title">Sign in to your account</div>
        <div class="form-sub">Enter your credentials to continue</div>
      </div>

      <!-- ERROR -->
      <?php if ($error): ?>
        <div class="alert alert-error">
          <i class="fas fa-circle-exclamation"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- FORM -->
      <form method="POST" id="loginForm" novalidate>
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label class="form-label" for="email">Email Address <span style="color:var(--accent)">*</span></label>
          <div class="input-wrap">
            <input
              class="form-input"
              type="email"
              id="email"
              name="email"
              placeholder="you@example.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              maxlength="100"
              required
              autocomplete="email"
              oninput="validateLoginField(this)"
            >
            <i class="fas fa-envelope input-ico"></i>
          </div>
          <div class="field-hint" id="emailHint"></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password <span style="color:var(--accent)">*</span></label>
          <div class="input-wrap">
            <input
              class="form-input"
              type="password"
              id="password"
              name="password"
              placeholder="Enter your password"
              minlength="6"
              maxlength="128"
              required
              autocomplete="current-password"
              oninput="validateLoginField(this)"
            >
            <i class="fas fa-lock input-ico"></i>
            <button type="button" class="input-toggle" id="pwToggle" onclick="toggleLoginPw()" title="Show/hide password">
              <i class="fas fa-eye" id="pwIcon"></i>
            </button>
          </div>
          <div class="field-hint" id="passwordHint"></div>
        </div>

        <div class="form-row">
          <label class="checkbox-wrap">
            <input type="checkbox" name="remember">
            <span class="custom-check"></span>
            Remember me
          </label>
          <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <i class="fas fa-arrow-right-to-bracket"></i> Sign In
        </button>
      </form>

      <div class="or-divider">or continue with</div>

      <div class="social-btns">
        <button class="social-btn">
          <i class="fab fa-google gg"></i> Google
        </button>
        <button class="social-btn">
          <i class="fab fa-facebook-f fb"></i> Facebook
        </button>
      </div>

      <div class="switch-link">
        Don't have an account? <a href="register.php">Sign up free</a>
      </div>

    </div><!-- /auth-right -->
  </div><!-- /auth-split -->
</div><!-- /page-wrap -->

<script>
/* Password toggle */
function toggleLoginPw() {
  const inp = document.getElementById('password');
  const ico = document.getElementById('pwIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fas fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fas fa-eye';
  }
}

/* Real-time login field validation */
function validateLoginField(field) {
  const hintId = field.id + 'Hint';
  const hint = document.getElementById(hintId);
  if (!hint) return;

  let isValid = true;
  let message = '';

  if (field.id === 'email') {
    const val = field.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (val.length === 0) { isValid = true; message = ''; }
    else if (!emailRegex.test(val)) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Invalid email format'; }
    else if (val.length > 100) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Email too long'; }
    else { isValid = true; message = '<i class="fas fa-check-circle"></i> Email OK'; }
  }

  if (field.id === 'password') {
    const val = field.value;
    if (val.length === 0) { isValid = true; message = ''; }
    else if (val.length < 6) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> At least 6 characters'; }
    else if (val.length > 128) { isValid = false; message = '<i class="fas fa-circle-xmark"></i> Password too long'; }
    else { isValid = true; message = '<i class="fas fa-check-circle"></i> Password OK'; }
  }

  hint.innerHTML = message;
  hint.style.display = message ? 'flex' : 'none';
  field.className = isValid && message ? 'form-input valid' : !isValid && message ? 'form-input invalid' : 'form-input';
}

/* Loading state on submit */
document.getElementById('loginForm').addEventListener('submit', function(e) {
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  
  // Final validation before submit
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  
  if (!email || !emailRegex.test(email)) {
    e.preventDefault();
    alert('Please enter a valid email address');
    return;
  }
  
  if (!password || password.length < 6) {
    e.preventDefault();
    alert('Please enter a valid password');
    return;
  }
  
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
  btn.disabled = true;
});

/* Checkbox visual */
document.querySelectorAll('.checkbox-wrap').forEach(wrap => {
  wrap.addEventListener('click', () => {
    const cb = wrap.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
  });
});

/* Input focus — move icon colour via CSS already handles it,
   but make sure label also lights up */
document.querySelectorAll('.form-input').forEach(inp => {
  inp.addEventListener('focus', () => {
    inp.closest('.input-wrap').querySelector('.input-ico').style.color = 'var(--accent)';
  });
  inp.addEventListener('blur', () => {
    inp.closest('.input-wrap').querySelector('.input-ico').style.color = '';
  });
});
</script>
</body>
</html>