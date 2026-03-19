<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Helper.php';
require_once 'includes/customer_functions.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['customer_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT p.* FROM wishlists w
                           JOIN products p ON w.product_id = p.product_id
                           WHERE w.customer_id = ? ORDER BY w.created_at DESC");
    $stmt->execute([$_SESSION['customer_id']]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Account Page Error: " . $e->getMessage());
    die("Database Error: " . $e->getMessage());
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';

// Quick stats
$totalOrders    = count($orders);
$totalSpent     = array_sum(array_column($orders, 'total_amount'));
$pendingOrders  = count(array_filter($orders, fn($o) => strtolower($o['status']) === 'pending'));
$wishlistCount  = count($wishlist);

$statusColors = [
    'pending'    => ['bg' => 'rgba(245,166,35,.1)',   'color' => '#f5a623', 'border' => 'rgba(245,166,35,.25)'],
    'processing' => ['bg' => 'rgba(61,142,248,.1)',   'color' => '#3d8ef8', 'border' => 'rgba(61,142,248,.25)'],
    'shipped'    => ['bg' => 'rgba(0,212,200,.1)',    'color' => '#00d4c8', 'border' => 'rgba(0,212,200,.25)'],
    'delivered'  => ['bg' => 'rgba(48,209,88,.1)',    'color' => '#30d158', 'border' => 'rgba(48,209,88,.25)'],
    'cancelled'  => ['bg' => 'rgba(255,45,85,.1)',    'color' => '#ff2d55', 'border' => 'rgba(255,45,85,.25)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account — SAP Computers</title>
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
  --card-s:  #161622;
  --edge:    rgba(255,255,255,.06);
  --edge2:   rgba(255,255,255,.11);
  --edge3:   rgba(255,255,255,.2);
  --accent:  #ff2d55;
  --accent2: #e0002a;
  --ag2:     rgba(255,45,85,.06);
  --green:   #30d158;
  --gold:    #f5a623;
  --blue:    #3d8ef8;
  --cyan:    #00d4c8;
  --t1:      #f5f5fa;
  --t2:      rgba(245,245,250,.65);
  --t3:      rgba(245,245,250,.38);
  --t4:      rgba(245,245,250,.14);
  --r:       8px;
  --r2:      12px;
  --r3:      16px;
  --r4:      20px;
}
html { font-size: 14px; scroll-behavior: smooth; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--t1); min-height: 100vh; overflow-x: hidden; line-height: 1.5; }
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; }
img { display: block; max-width: 100%; }
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: rgba(255,45,85,.3); border-radius: 4px; }

/* ── ANNOUNCE ── */
.announce {
  background: var(--accent);
  color: #fff; text-align: center;
  padding: 8px 16px; font-size: .76rem; font-weight: 500;
}
.announce a { color: #fff; text-decoration: underline; }

/* ── HEADER ── */
.hdr {
  background: rgba(10,10,15,.92);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--edge);
  position: sticky; top: 0; z-index: 500;
  height: 66px; display: flex; align-items: center;
  padding: 0 32px; gap: 24px;
}
.logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.5rem; letter-spacing: -.03em; }
.logo img { height: 34px; width: auto; }
.logo-dot { color: var(--accent); }
.hdr-right { display: flex; align-items: center; gap: 6px; margin-left: auto; }
.hbtn {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 16px; border-radius: 100px;
  font-size: .8rem; font-weight: 600; color: var(--t2);
  border: none; background: transparent; transition: all .18s;
}
.hbtn:hover { background: var(--surface); color: var(--t1); }
.hbtn-accent { background: var(--accent) !important; color: #fff !important; }
.hbtn-accent:hover { background: var(--accent2) !important; }

/* ── PAGE BG ── */
.page-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
.orb { position: absolute; border-radius: 50%; filter: blur(120px); }
.orb-1 { width: 500px; height: 500px; background: rgba(255,45,85,.06);  top: 0;    right: -100px; }
.orb-2 { width: 400px; height: 400px; background: rgba(61,142,248,.04); bottom: 0; left: -80px;  }

/* ── BREADCRUMB ── */
.breadcrumb {
  padding: 14px 32px;
  border-bottom: 1px solid var(--edge);
  background: var(--bg2);
  display: flex; align-items: center; gap: 8px;
  font-size: .76rem; color: var(--t3);
}
.breadcrumb a { color: var(--t3); transition: color .18s; }
.breadcrumb a:hover { color: var(--accent); }
.breadcrumb i { font-size: .6rem; }

/* ── LAYOUT ── */
.page-wrap { max-width: 1340px; margin: 0 auto; padding: 32px 32px 64px; }
.account-grid { display: grid; grid-template-columns: 260px 1fr; gap: 24px; align-items: start; }

/* ── SIDEBAR ── */
.sidebar {
  background: var(--surface);
  border: 1px solid var(--edge2);
  border-radius: var(--r4);
  overflow: hidden;
  position: sticky;
  top: 90px;
}

/* Avatar card */
.avatar-card {
  padding: 28px 20px 20px;
  text-align: center;
  border-bottom: 1px solid var(--edge);
  background: linear-gradient(160deg, rgba(255,45,85,.05) 0%, transparent 60%);
  position: relative;
}
.avatar-ring {
  width: 76px; height: 76px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), #c8102e);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.7rem;
  color: #fff; margin: 0 auto 14px;
  box-shadow: 0 8px 24px rgba(255,45,85,.3);
}
.avatar-name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; margin-bottom: 3px; letter-spacing: -.01em; }
.avatar-email { font-size: .74rem; color: var(--t3); margin-bottom: 12px; }
.member-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(48,209,88,.1); border: 1px solid rgba(48,209,88,.22);
  color: var(--green); font-size: .65rem; font-weight: 700;
  padding: 3px 10px; border-radius: 100px; letter-spacing: .04em;
}

/* Nav */
.side-nav { padding: 10px 0; }
.side-nav-item {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 20px; font-size: .83rem; font-weight: 500;
  color: var(--t2); transition: all .18s; position: relative;
  border-left: 2.5px solid transparent;
}
.side-nav-item:hover { background: rgba(255,255,255,.03); color: var(--t1); }
.side-nav-item.active {
  background: rgba(255,45,85,.06);
  color: var(--accent); border-left-color: var(--accent);
  font-weight: 600;
}
.side-nav-item i { width: 18px; text-align: center; font-size: .9rem; }
.side-nav-badge {
  margin-left: auto; background: var(--accent); color: #fff;
  font-size: .6rem; font-weight: 700; padding: 2px 7px; border-radius: 100px;
}
.side-nav-divider { height: 1px; background: var(--edge); margin: 8px 0; }
.side-nav-item.logout { color: var(--t3); }
.side-nav-item.logout:hover { color: var(--accent); background: rgba(255,45,85,.05); }

/* ── MAIN ── */
.main-content { display: flex; flex-direction: column; gap: 20px; min-width: 0; }

/* Section header */
.sec-hd {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 20px; gap: 12px; flex-wrap: wrap;
}
.sec-title {
  font-family: 'Syne', sans-serif; font-weight: 700;
  font-size: 1.2rem; letter-spacing: -.02em;
  display: flex; align-items: center; gap: 9px;
}
.sec-title::before {
  content: ''; width: 3px; height: 20px;
  background: var(--accent); border-radius: 2px; flex-shrink: 0;
}
.sec-sub { font-size: .78rem; color: var(--t3); margin-top: 3px; }

/* ── STAT CARDS ── */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--edge);
  border-radius: var(--r3); padding: 20px;
  display: flex; flex-direction: column; gap: 10px;
  transition: border-color .2s, transform .2s;
}
.stat-card:hover { border-color: var(--edge2); transform: translateY(-3px); }
.stat-icon {
  width: 40px; height: 40px; border-radius: var(--r2);
  display: flex; align-items: center; justify-content: center;
  font-size: .95rem;
}
.stat-num { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.55rem; letter-spacing: -.02em; }
.stat-lbl { font-size: .73rem; color: var(--t3); font-weight: 500; }

/* ── PANEL ── */
.panel {
  background: var(--surface); border: 1px solid var(--edge2);
  border-radius: var(--r4); overflow: hidden;
}
.panel-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 24px; border-bottom: 1px solid var(--edge);
}
.panel-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; letter-spacing: -.01em; }
.panel-body { padding: 20px 24px; }
.panel-body-np { padding: 0; }

/* ── ORDERS ── */
.order-card {
  display: flex; align-items: center; gap: 16px;
  padding: 18px 24px; border-bottom: 1px solid var(--edge);
  transition: background .15s;
}
.order-card:last-child { border: none; }
.order-card:hover { background: rgba(255,255,255,.02); }
.order-icon {
  width: 48px; height: 48px; border-radius: var(--r2);
  background: var(--bg2); display: flex; align-items: center;
  justify-content: center; font-size: 1.15rem; color: var(--t4); flex-shrink: 0;
}
.order-info { flex: 1; min-width: 0; }
.order-id { font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; margin-bottom: 3px; }
.order-date { font-size: .73rem; color: var(--t3); }
.order-items-count { font-size: .76rem; color: var(--t2); margin-top: 2px; }
.order-amount { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; white-space: nowrap; }
.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .66rem; font-weight: 700; letter-spacing: .04em;
  text-transform: uppercase; padding: 4px 10px; border-radius: 100px;
  border: 1px solid; white-space: nowrap;
}
.order-actions { display: flex; gap: 7px; flex-shrink: 0; }

/* ── EMPTY STATE ── */
.empty-state {
  text-align: center; padding: 64px 24px;
  display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.empty-ico {
  width: 72px; height: 72px; border-radius: 50%;
  background: var(--bg2); display: flex; align-items: center;
  justify-content: center; font-size: 1.8rem; color: var(--t4); margin-bottom: 4px;
}
.empty-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; }
.empty-sub { font-size: .82rem; color: var(--t3); max-width: 260px; }

/* ── FORMS ── */
.form-section { padding: 24px; border-bottom: 1px solid var(--edge); }
.form-section:last-child { border: none; }
.form-section-title {
  font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem;
  margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
}
.form-section-title i { color: var(--accent); font-size: .85rem; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
.form-label { font-size: .76rem; font-weight: 600; color: var(--t2); letter-spacing: .02em; }
.input-wrap { position: relative; }
.input-ico {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  color: var(--t4); font-size: .8rem; pointer-events: none; transition: color .2s;
}
.form-input {
  width: 100%; background: var(--bg2);
  border: 1.5px solid var(--edge2); border-radius: var(--r2);
  padding: 11px 13px 11px 38px;
  color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: .87rem;
  outline: none; transition: border-color .2s, box-shadow .2s;
}
.form-input.no-ico { padding-left: 13px; }
.form-input::placeholder { color: var(--t4); }
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--ag2); }
.form-input:focus ~ .input-ico,
.input-wrap:focus-within .input-ico { color: var(--accent); }
.pw-toggle {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: var(--t4); font-size: .8rem; transition: color .18s;
}
.pw-toggle:hover { color: var(--t2); }
.form-hint { font-size: .69rem; color: var(--t4); margin-top: 2px; }

/* ── BUTTONS ── */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  border: none; border-radius: 100px; font-family: 'DM Sans', sans-serif;
  font-weight: 600; font-size: .82rem; transition: all .2s; white-space: nowrap;
  padding: 10px 20px;
}
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: var(--accent2); transform: translateY(-2px); box-shadow: 0 10px 28px rgba(255,45,85,.28); }
.btn-ghost { background: var(--bg2); border: 1.5px solid var(--edge2); color: var(--t2); }
.btn-ghost:hover { border-color: var(--edge3); color: var(--t1); }
.btn-sm { padding: 7px 14px; font-size: .76rem; }
.btn-danger { background: rgba(255,45,85,.1); border: 1px solid rgba(255,45,85,.22); color: var(--accent); }
.btn-danger:hover { background: var(--accent); color: #fff; }

/* ── WISHLIST GRID ── */
.wish-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.wish-card {
  background: var(--bg2); border: 1px solid var(--edge);
  border-radius: var(--r3); overflow: hidden;
  transition: all .25s; display: flex; flex-direction: column;
}
.wish-card:hover { border-color: rgba(255,45,85,.25); transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,.5); }
.wish-img {
  height: 150px; background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  font-size: 3rem; color: rgba(255,255,255,.06); position: relative; overflow: hidden;
}
.wish-img img { width: 100%; height: 100%; object-fit: cover; }
.wish-remove {
  position: absolute; top: 8px; right: 8px;
  width: 28px; height: 28px; border-radius: 50%;
  background: rgba(255,45,85,.15); border: 1px solid rgba(255,45,85,.3);
  color: var(--accent); display: flex; align-items: center; justify-content: center;
  font-size: .72rem; transition: all .18s;
}
.wish-remove:hover { background: var(--accent); color: #fff; }
.wish-body { padding: 14px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
.wish-name { font-size: .83rem; font-weight: 500; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.wish-price { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; }
.wish-actions { display: flex; gap: 7px; margin-top: auto; }

/* ── TOAST ── */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast {
  background: var(--card-s); border: 1px solid var(--edge2);
  border-radius: var(--r3); padding: 12px 18px;
  display: flex; align-items: center; gap: 10px;
  font-size: .82rem; min-width: 240px; box-shadow: 0 16px 40px rgba(0,0,0,.8);
  transform: translateX(120%); transition: transform .32s cubic-bezier(.4,0,.2,1);
}
.toast.in { transform: translateX(0); }

/* ── REVEAL ── */
.reveal { opacity: 0; transform: translateY(16px); transition: opacity .4s, transform .4s; }
.reveal.vis { opacity: 1; transform: none; }

/* ── RESPONSIVE ── */
@media (max-width: 1100px) {
  .stats-row { grid-template-columns: repeat(2,1fr); }
  .wish-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
  .account-grid { grid-template-columns: 1fr; }
  .sidebar { position: static; }
  .side-nav { display: flex; overflow-x: auto; padding: 0; scrollbar-width: none; }
  .side-nav::-webkit-scrollbar { display: none; }
  .side-nav-divider { display: none; }
  .side-nav-item { border-left: none; border-bottom: 2.5px solid transparent; flex-shrink: 0; padding: 14px 18px; }
  .side-nav-item.active { border-bottom-color: var(--accent); border-left: none; }
  .avatar-card { display: flex; align-items: center; gap: 14px; text-align: left; padding: 18px 20px; }
  .avatar-ring { width: 52px; height: 52px; font-size: 1.2rem; margin: 0; flex-shrink: 0; }
  .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
  .page-wrap { padding: 20px 16px 48px; }
  .hdr { padding: 0 16px; }
  .breadcrumb { padding: 12px 16px; }
}
@media (max-width: 600px) {
  .stats-row { grid-template-columns: 1fr 1fr; }
  .wish-grid { grid-template-columns: 1fr 1fr; }
  .order-card { flex-wrap: wrap; gap: 12px; }
  .order-actions { width: 100%; }
  .order-actions .btn { flex: 1; justify-content: center; }
}
@media (max-width: 420px) {
  .wish-grid { grid-template-columns: 1fr; }
  .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
}
</style>
</head>
<body>

<!-- BG -->
<div class="page-bg"><div class="orb orb-1"></div><div class="orb orb-2"></div></div>

<!-- ANNOUNCE -->
<div class="announce">🚀 FREE DELIVERY on orders over Rs.&nbsp;2,000 &nbsp;·&nbsp; Call <a href="tel:+94773987246">+94 77 398 7246</a></div>

<!-- HEADER -->
<header class="hdr">
  <a href="index.php" class="logo">
    <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers">
  </a>
  <div class="hdr-right">
    <a href="shop.php" class="hbtn"><i class="fas fa-store"></i> Shop</a>
    <a href="cart.php" class="hbtn"><i class="fas fa-bag-shopping"></i> Cart</a>
    <a href="logout.php" class="hbtn hbtn-accent"><i class="fas fa-arrow-right-from-bracket"></i> Logout</a>
  </div>
</header>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <i class="fas fa-chevron-right"></i>
  <span style="color:var(--t2)">My Account</span>
</div>

<!-- PAGE -->
<div class="page-wrap">
  <div class="account-grid">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
      <div class="avatar-card">
        <div class="avatar-ring"><?= strtoupper(mb_substr($customer['name'], 0, 1)) ?></div>
        <div>
          <div class="avatar-name"><?= htmlspecialchars($customer['name']) ?></div>
          <div class="avatar-email"><?= htmlspecialchars($customer['email']) ?></div>
          <span class="member-pill"><i class="fas fa-circle-check"></i> Verified Member</span>
        </div>
      </div>

      <nav class="side-nav">
        <a href="?tab=orders"   class="side-nav-item <?= $tab==='orders'   ? 'active':'' ?>">
          <i class="fas fa-box-open"></i> My Orders
          <?php if ($totalOrders > 0): ?>
            <span class="side-nav-badge"><?= $totalOrders ?></span>
          <?php endif; ?>
        </a>
        <a href="?tab=profile"  class="side-nav-item <?= $tab==='profile'  ? 'active':'' ?>">
          <i class="fas fa-user-pen"></i> Profile
        </a>
        <a href="?tab=address"  class="side-nav-item <?= $tab==='address'  ? 'active':'' ?>">
          <i class="fas fa-location-dot"></i> Address
        </a>
        <a href="?tab=wishlist" class="side-nav-item <?= $tab==='wishlist' ? 'active':'' ?>">
          <i class="fas fa-heart"></i> Wishlist
          <?php if ($wishlistCount > 0): ?>
            <span class="side-nav-badge" style="background:rgba(255,45,85,.15);color:var(--accent);border:1px solid rgba(255,45,85,.25)"><?= $wishlistCount ?></span>
          <?php endif; ?>
        </a>
        <div class="side-nav-divider"></div>
        <a href="?tab=security" class="side-nav-item <?= $tab==='security' ? 'active':'' ?>">
          <i class="fas fa-shield-halved"></i> Security
        </a>
        <a href="logout.php"    class="side-nav-item logout">
          <i class="fas fa-arrow-right-from-bracket"></i> Sign Out
        </a>
      </nav>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main-content">

      <?php if ($tab === 'orders'): ?>

        <!-- STATS -->
        <div class="stats-row reveal">
          <div class="stat-card">
            <div class="stat-icon" style="background:rgba(61,142,248,.1)"><i class="fas fa-box-open" style="color:var(--blue)"></i></div>
            <div><div class="stat-num"><?= $totalOrders ?></div><div class="stat-lbl">Total Orders</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:rgba(48,209,88,.1)"><i class="fas fa-circle-check" style="color:var(--green)"></i></div>
            <div><div class="stat-num">Rs. <?= number_format($totalSpent, 0) ?></div><div class="stat-lbl">Total Spent</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,166,35,.1)"><i class="fas fa-clock" style="color:var(--gold)"></i></div>
            <div><div class="stat-num"><?= $pendingOrders ?></div><div class="stat-lbl">Pending Orders</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,45,85,.1)"><i class="fas fa-heart" style="color:var(--accent)"></i></div>
            <div><div class="stat-num"><?= $wishlistCount ?></div><div class="stat-lbl">Wishlist Items</div></div>
          </div>
        </div>

        <!-- ORDERS LIST -->
        <div class="panel reveal">
          <div class="panel-head">
            <div class="panel-title">Order History</div>
            <a href="shop.php" class="btn btn-ghost btn-sm"><i class="fas fa-plus"></i> New Order</a>
          </div>
          <div class="panel-body-np">
            <?php if ($totalOrders > 0): ?>
              <?php foreach ($orders as $o):
                $sl = strtolower($o['status']);
                $sc = $statusColors[$sl] ?? $statusColors['pending'];
              ?>
              <div class="order-card">
                <div class="order-icon"><i class="fas fa-box"></i></div>
                <div class="order-info">
                  <div class="order-id">Order #<?= htmlspecialchars($o['order_id']) ?></div>
                  <div class="order-date"><?= date('d M Y, g:ia', strtotime($o['created_at'])) ?></div>
                  <div class="order-items-count"><?= (int)($o['item_count'] ?? 1) ?> item<?= ($o['item_count'] ?? 1) != 1 ? 's' : '' ?></div>
                </div>
                <div>
                  <span class="status-pill" style="background:<?=$sc['bg']?>;color:<?=$sc['color']?>;border-color:<?=$sc['border']?>">
                    <?= ucfirst($o['status']) ?>
                  </span>
                </div>
                <div class="order-amount">Rs. <?= number_format($o['total_amount'], 0) ?></div>
                <div class="order-actions">
                  <a href="order-details.php?id=<?= (int)$o['order_id'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> View</a>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <div class="empty-ico"><i class="fas fa-box-open"></i></div>
                <div class="empty-title">No orders yet</div>
                <div class="empty-sub">Start exploring our products and place your first order!</div>
                <a href="shop.php" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-store"></i> Shop Now</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php elseif ($tab === 'profile'): ?>

        <!-- PROFILE FORM -->
        <div class="panel reveal">
          <div class="panel-head">
            <div class="panel-title">Profile Information</div>
          </div>
          <form method="POST" action="api/update_profile.php">
            <div class="form-section">
              <div class="form-section-title"><i class="fas fa-user"></i> Personal Details</div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label class="form-label">Full Name</label>
                  <div class="input-wrap">
                    <input class="form-input" type="text" name="name" value="<?= htmlspecialchars($customer['name']) ?>" required placeholder="John Silva">
                    <i class="fas fa-user input-ico"></i>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Email Address</label>
                  <div class="input-wrap">
                    <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($customer['email']) ?>" required placeholder="you@example.com">
                    <i class="fas fa-envelope input-ico"></i>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Phone Number</label>
                  <div class="input-wrap">
                    <input class="form-input" type="tel" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" placeholder="+94 77 000 0000">
                    <i class="fas fa-phone input-ico"></i>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Date of Birth</label>
                  <div class="input-wrap">
                    <input class="form-input" type="date" name="dob" value="<?= htmlspecialchars($customer['dob'] ?? '') ?>">
                    <i class="fas fa-calendar input-ico"></i>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-section" style="border:none;padding-top:0">
              <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Changes</button>
            </div>
          </form>
        </div>

      <?php elseif ($tab === 'security'): ?>

        <!-- CHANGE PASSWORD -->
        <div class="panel reveal">
          <div class="panel-head">
            <div class="panel-title">Security Settings</div>
          </div>
          <form method="POST" action="api/change_password.php">
            <div class="form-section">
              <div class="form-section-title"><i class="fas fa-lock"></i> Change Password</div>
              <div class="form-grid-2">
                <div class="form-group full">
                  <label class="form-label">Current Password</label>
                  <div class="input-wrap">
                    <input class="form-input" type="password" name="current_password" required placeholder="Your current password">
                    <i class="fas fa-lock input-ico"></i>
                    <button type="button" class="pw-toggle" onclick="toggleField(this)"><i class="fas fa-eye"></i></button>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <div class="input-wrap">
                    <input class="form-input" type="password" name="new_password" required placeholder="Min. 6 characters">
                    <i class="fas fa-lock-open input-ico"></i>
                    <button type="button" class="pw-toggle" onclick="toggleField(this)"><i class="fas fa-eye"></i></button>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Confirm New Password</label>
                  <div class="input-wrap">
                    <input class="form-input" type="password" name="confirm_password" required placeholder="Repeat new password">
                    <i class="fas fa-lock-open input-ico"></i>
                    <button type="button" class="pw-toggle" onclick="toggleField(this)"><i class="fas fa-eye"></i></button>
                  </div>
                </div>
              </div>
              <div class="form-hint" style="margin-top:10px"><i class="fas fa-circle-info"></i> Use at least 6 characters with a mix of letters and numbers.</div>
            </div>
            <div class="form-section" style="border:none;padding-top:0">
              <button type="submit" class="btn btn-primary"><i class="fas fa-shield-halved"></i> Update Password</button>
            </div>
          </form>
        </div>

      <?php elseif ($tab === 'address'): ?>

        <!-- ADDRESS FORM -->
        <div class="panel reveal">
          <div class="panel-head">
            <div class="panel-title">Delivery Address</div>
          </div>
          <form method="POST" action="api/update_address.php">
            <div class="form-section">
              <div class="form-section-title"><i class="fas fa-location-dot"></i> Primary Address</div>
              <div class="form-grid-2">
                <div class="form-group full">
                  <label class="form-label">Street Address</label>
                  <div class="input-wrap">
                    <input class="form-input" type="text" name="street_address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>" required placeholder="123, Main Street">
                    <i class="fas fa-road input-ico"></i>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">City</label>
                  <div class="input-wrap">
                    <input class="form-input" type="text" name="city" value="<?= htmlspecialchars($customer['city'] ?? '') ?>" required placeholder="Gampaha">
                    <i class="fas fa-city input-ico"></i>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Postal Code</label>
                  <div class="input-wrap">
                    <input class="form-input" type="text" name="postal_code" value="<?= htmlspecialchars($customer['postal_code'] ?? '') ?>" placeholder="11000">
                    <i class="fas fa-mailbox input-ico"></i>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Country</label>
                  <div class="input-wrap">
                    <input class="form-input" type="text" name="country" value="Sri Lanka" required>
                    <i class="fas fa-globe input-ico"></i>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-section" style="border:none;padding-top:0">
              <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Address</button>
            </div>
          </form>
        </div>

      <?php elseif ($tab === 'wishlist'): ?>

        <!-- WISHLIST -->
        <div class="panel reveal">
          <div class="panel-head">
            <div class="panel-title">My Wishlist <span style="color:var(--t3);font-weight:400;font-size:.82rem;font-family:'DM Sans',sans-serif">(<?= $wishlistCount ?> item<?= $wishlistCount != 1 ? 's' : '' ?>)</span></div>
            <a href="shop.php" class="btn btn-ghost btn-sm"><i class="fas fa-plus"></i> Add More</a>
          </div>
          <div class="panel-body">
            <?php if ($wishlistCount > 0): ?>
            <div class="wish-grid">
              <?php
              $wIcons = ['fa-laptop','fa-print','fa-video','fa-microchip','fa-desktop','fa-headphones'];
              foreach ($wishlist as $wi => $wp):
              ?>
              <div class="wish-card">
                <div class="wish-img">
                  <?php if (!empty($wp['image'])): ?>
                    <img src="../assets/images/products/<?= htmlspecialchars($wp['image']) ?>"
                         alt="<?= htmlspecialchars($wp['product_name'] ?? $wp['name'] ?? '') ?>"
                         onerror="this.style.display='none'">
                  <?php else: ?>
                    <i class="fas <?= $wIcons[$wi % count($wIcons)] ?>"></i>
                  <?php endif; ?>
                  <button class="wish-remove remove-wishlist" data-product-id="<?= (int)($wp['product_id'] ?? $wp['id']) ?>" title="Remove">
                    <i class="fas fa-xmark"></i>
                  </button>
                </div>
                <div class="wish-body">
                  <div class="wish-name"><?= htmlspecialchars($wp['product_name'] ?? $wp['name'] ?? 'Product') ?></div>
                  <div class="wish-price">Rs. <?= number_format($wp['selling_price'] ?? $wp['price'] ?? 0, 0) ?></div>
                  <div class="wish-actions">
                    <button class="btn btn-primary btn-sm add-to-cart" data-product-id="<?= (int)($wp['product_id'] ?? $wp['id']) ?>" style="flex:1;justify-content:center">
                      <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                    <a href="product.php?id=<?= (int)($wp['product_id'] ?? $wp['id']) ?>" class="btn btn-ghost btn-sm">
                      <i class="fas fa-eye"></i>
                    </a>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
              <div class="empty-ico"><i class="fas fa-heart"></i></div>
              <div class="empty-title">Your wishlist is empty</div>
              <div class="empty-sub">Save items you love and find them here whenever you're ready.</div>
              <a href="shop.php" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-store"></i> Browse Products</a>
            </div>
            <?php endif; ?>
          </div>
        </div>

      <?php endif; ?>
    </main>
  </div>
</div>

<!-- TOAST -->
<div class="toast-wrap" id="toasts"></div>

<script>
/* Toast */
function showToast(msg, color = 'var(--green)') {
  const wrap = document.getElementById('toasts');
  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = `<i class="fas fa-check-circle" style="color:${color};font-size:1.05rem;flex-shrink:0"></i><span>${msg}</span>`;
  wrap.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('in')));
  setTimeout(() => { t.classList.remove('in'); setTimeout(() => t.remove(), 380); }, 3200);
}

/* Password toggle */
function toggleField(btn) {
  const inp = btn.closest('.input-wrap').querySelector('input');
  const ico = btn.querySelector('i');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else                         { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}

/* Input icon colour on focus */
document.querySelectorAll('.form-input').forEach(inp => {
  inp.addEventListener('focus', () => {
    const ico = inp.closest('.input-wrap')?.querySelector('.input-ico');
    if (ico) ico.style.color = 'var(--accent)';
  });
  inp.addEventListener('blur', () => {
    const ico = inp.closest('.input-wrap')?.querySelector('.input-ico');
    if (ico) ico.style.color = '';
  });
});

/* Add to cart */
document.querySelectorAll('.add-to-cart').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.productId;
    const orig = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    this.disabled = true;
    try {
      const r = await fetch('cart_add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${id}&quantity=1`
      });
      const d = await r.json();
      if (d.success) {
        showToast('Added to cart!');
        this.innerHTML = '<i class="fas fa-check"></i> Added';
        setTimeout(() => { this.innerHTML = orig; this.disabled = false; }, 2000);
      } else {
        showToast(d.message || 'Could not add', 'var(--accent)');
        this.innerHTML = orig; this.disabled = false;
      }
    } catch {
      showToast('Error — please try again', 'var(--accent)');
      this.innerHTML = orig; this.disabled = false;
    }
  });
});

/* Remove from wishlist */
document.querySelectorAll('.remove-wishlist').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.productId;
    const card = this.closest('.wish-card');
    try {
      const r = await fetch('api/remove_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${id}`
      });
      const d = await r.json();
      if (d.success) {
        card.style.transition = 'opacity .3s, transform .3s';
        card.style.opacity = '0'; card.style.transform = 'scale(.95)';
        setTimeout(() => card.remove(), 320);
        showToast('Removed from wishlist', 'var(--accent)');
      }
    } catch { showToast('Error removing item', 'var(--accent)'); }
  });
});

/* Scroll reveal */
const io = new IntersectionObserver(es => {
  es.forEach((e, i) => {
    if (e.isIntersecting) {
      setTimeout(() => e.target.classList.add('vis'), i * 60);
      io.unobserve(e.target);
    }
  });
}, { threshold: .05 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>
</body>
</html>