<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Helper.php';
require_once 'includes/customer_functions.php';

$cart       = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cart_items = [];
$total      = 0;

if (!empty($cart)) {
    try {
        $pdo = Database::getInstance();
        foreach ($cart as $product_id => $quantity) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                $product['cart_quantity'] = $quantity;
                $product['subtotal']      = $product['selling_price'] * $quantity;
                $cart_items[]             = $product;
                $total                   += $product['subtotal'];
            }
        }
    } catch (PDOException $e) {
        error_log("Cart Error: " . $e->getMessage());
    }
}

$tax         = $total * 0.15;
$shipping    = $total > 2000 ? 0 : 200;
$grand_total = $total + $tax + $shipping;
$item_count  = count($cart_items);

$prod_icons  = ['fa-laptop','fa-microchip','fa-print','fa-desktop','fa-memory','fa-hard-drive','fa-video','fa-keyboard','fa-computer-mouse','fa-headphones'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart — SAP Computers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:      #0a0a0f;
  --bg2:     #0f0f17;
  --surface: #13131e;
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
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: rgba(255,45,85,.3); border-radius: 4px; }

/* ── PAGE BG ── */
.page-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
.orb { position: absolute; border-radius: 50%; filter: blur(120px); }
.orb-1 { width: 500px; height: 500px; background: rgba(255,45,85,.07);  top: -120px; right: -80px; }
.orb-2 { width: 400px; height: 400px; background: rgba(61,142,248,.04); bottom: -80px; left: -100px; }

/* ── ANNOUNCE ── */
.announce { background: var(--accent); color: #fff; text-align: center; padding: 8px 16px; font-size: .76rem; font-weight: 500; }
.announce a { color: #fff; text-decoration: underline; }

/* ── HEADER ── */
.hdr {
  background: rgba(10,10,15,.92); backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--edge);
  position: sticky; top: 0; z-index: 500;
  height: 66px; display: flex; align-items: center; padding: 0 32px; gap: 24px;
}
.logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.5rem; letter-spacing: -.03em; }
.logo img { height: 34px; width: auto; }
.logo-dot { color: var(--accent); }
.hdr-right { display: flex; align-items: center; gap: 6px; margin-left: auto; }
.hbtn {
  display: flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 100px;
  font-size: .8rem; font-weight: 600; color: var(--t2); border: none; background: transparent; transition: all .18s;
}
.hbtn:hover { background: var(--surface); color: var(--t1); }
.hbtn-accent { background: var(--accent) !important; color: #fff !important; }
.hbtn-accent:hover { background: var(--accent2) !important; }

/* ── BREADCRUMB ── */
.breadcrumb {
  padding: 13px 32px; border-bottom: 1px solid var(--edge); background: var(--bg2);
  display: flex; align-items: center; gap: 8px; font-size: .76rem; color: var(--t3);
}
.breadcrumb a { color: var(--t3); transition: color .18s; }
.breadcrumb a:hover { color: var(--accent); }
.breadcrumb i { font-size: .6rem; }

/* ── LAYOUT ── */
.page-wrap { max-width: 1340px; margin: 0 auto; padding: 32px 32px 80px; }
.page-head { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 12px; flex-wrap: wrap; }
.page-title {
  font-family: 'Syne', sans-serif; font-weight: 800;
  font-size: 1.7rem; letter-spacing: -.03em;
  display: flex; align-items: center; gap: 12px;
}
.cart-count {
  font-family: 'DM Sans', sans-serif; font-size: .8rem; font-weight: 600;
  background: var(--surface); border: 1px solid var(--edge2);
  color: var(--t3); padding: 4px 12px; border-radius: 100px;
}

/* PROGRESS STEPS */
.steps-bar { display: flex; align-items: center; gap: 0; }
.step-node {
  display: flex; align-items: center; gap: 8px;
  font-size: .76rem; font-weight: 600; color: var(--t3);
}
.step-node.done { color: var(--green); }
.step-node.active { color: var(--t1); }
.step-circle {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--surface); border: 1.5px solid var(--edge2);
  display: flex; align-items: center; justify-content: center;
  font-size: .72rem; font-weight: 700; transition: all .2s;
}
.step-node.done  .step-circle { background: rgba(48,209,88,.1); border-color: rgba(48,209,88,.3); color: var(--green); }
.step-node.active .step-circle { background: var(--accent); border-color: var(--accent); color: #fff; }
.step-line { width: 40px; height: 1px; background: var(--edge); margin: 0 6px; }

/* ── GRID ── */
.cart-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }

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

/* ── CART ITEMS ── */
.cart-item {
  display: grid;
  grid-template-columns: 80px 1fr auto auto auto;
  align-items: center; gap: 18px;
  padding: 18px 24px;
  border-bottom: 1px solid var(--edge);
  transition: background .15s;
  animation: slideIn .3s ease both;
}
@keyframes slideIn { from{opacity:0;transform:translateX(-10px)} to{opacity:1;transform:translateX(0)} }
.cart-item:last-child { border: none; }
.cart-item:hover { background: rgba(255,255,255,.015); }

/* Thumb */
.item-thumb {
  width: 80px; height: 80px; border-radius: var(--r2);
  background: var(--bg2); border: 1px solid var(--edge);
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem; color: rgba(255,255,255,.08); overflow: hidden; flex-shrink: 0;
}
.item-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Info */
.item-info { min-width: 0; }
.item-brand { font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--t4); margin-bottom: 4px; }
.item-name { font-size: .9rem; font-weight: 500; line-height: 1.35; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.item-price { font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; color: var(--t2); }

/* Quantity */
.qty-ctrl { display: flex; align-items: center; gap: 0; background: var(--bg2); border: 1.5px solid var(--edge2); border-radius: 100px; overflow: hidden; }
.qty-btn {
  width: 34px; height: 34px; border: none; background: transparent; color: var(--t2);
  font-size: .85rem; display: flex; align-items: center; justify-content: center; transition: all .18s;
}
.qty-btn:hover { background: rgba(255,255,255,.06); color: var(--t1); }
.qty-num {
  width: 38px; text-align: center; background: transparent; border: none;
  color: var(--t1); font-family: 'Syne', sans-serif; font-weight: 700; font-size: .9rem;
  outline: none; padding: 0;
  -moz-appearance: textfield;
}
.qty-num::-webkit-inner-spin-button,
.qty-num::-webkit-outer-spin-button { -webkit-appearance: none; }

/* Subtotal */
.item-subtotal { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem; white-space: nowrap; min-width: 100px; text-align: right; }

/* Remove */
.item-remove {
  width: 34px; height: 34px; border-radius: 50%;
  background: rgba(255,45,85,.06); border: 1px solid rgba(255,45,85,.15);
  color: var(--t3); font-size: .8rem;
  display: flex; align-items: center; justify-content: center; transition: all .2s;
}
.item-remove:hover { background: rgba(255,45,85,.15); color: var(--accent); border-color: rgba(255,45,85,.3); }

/* Footer of cart list */
.cart-footer { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-top: 1px solid var(--edge); flex-wrap: wrap; gap: 10px; }

/* ── COUPON ── */
.coupon-row { display: flex; gap: 8px; }
.coupon-input {
  flex: 1; background: var(--bg2); border: 1.5px solid var(--edge2);
  border-radius: 100px; padding: 10px 18px; color: var(--t1);
  font-family: 'DM Sans', sans-serif; font-size: .84rem; outline: none; transition: border-color .2s;
}
.coupon-input::placeholder { color: var(--t4); }
.coupon-input:focus { border-color: var(--accent); }

/* ── SUMMARY ── */
.summary-panel { position: sticky; top: 86px; }
.summary-rows { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; border-bottom: 1px solid var(--edge); }
.sum-row { display: flex; align-items: center; justify-content: space-between; font-size: .84rem; }
.sum-row .lbl { color: var(--t3); }
.sum-row .val { font-weight: 600; }
.sum-row .free { color: var(--green); font-weight: 600; }
.sum-divider { height: 1px; background: var(--edge); margin: 4px 0; }
.sum-total { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--edge); }
.sum-total .lbl { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; }
.sum-total .val { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.35rem; }
.summary-actions { padding: 20px 24px; display: flex; flex-direction: column; gap: 10px; }

/* FREE SHIPPING PROGRESS */
.ship-progress { padding: 16px 24px; border-bottom: 1px solid var(--edge); }
.ship-label { font-size: .76rem; color: var(--t2); margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
.ship-label .hl { color: var(--green); font-weight: 600; }
.progress-bar { height: 4px; background: var(--bg2); border-radius: 2px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 2px; background: linear-gradient(90deg, var(--accent), var(--green)); transition: width .5s ease; }

/* TRUST MINI */
.trust-mini { padding: 16px 24px; display: flex; flex-direction: column; gap: 10px; }
.trust-row { display: flex; align-items: center; gap: 10px; font-size: .76rem; color: var(--t3); }
.trust-row i { color: var(--accent); width: 14px; text-align: center; font-size: .8rem; }

/* ── BUTTONS ── */
.btn {
  display: inline-flex; align-items: center; gap: 7px; border: none;
  border-radius: 100px; font-family: 'DM Sans', sans-serif;
  font-weight: 600; font-size: .85rem; transition: all .22s;
  white-space: nowrap; padding: 11px 22px; cursor: pointer;
}
.btn-primary { background: var(--accent); color: #fff; width: 100%; justify-content: center; }
.btn-primary:hover { background: var(--accent2); transform: translateY(-2px); box-shadow: 0 10px 28px rgba(255,45,85,.28); }
.btn-ghost { background: var(--bg2); border: 1.5px solid var(--edge2); color: var(--t2); }
.btn-ghost:hover { border-color: var(--edge3); color: var(--t1); }
.btn-sm { padding: 8px 16px; font-size: .78rem; }
.btn-checkout { padding: 14px 22px; font-size: .9rem; font-weight: 700; }

/* ── EMPTY CART ── */
.empty-wrap {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; padding: 100px 24px; gap: 14px;
}
.empty-icon {
  width: 90px; height: 90px; border-radius: 50%;
  background: var(--surface); border: 1px solid var(--edge2);
  display: flex; align-items: center; justify-content: center;
  font-size: 2.2rem; color: var(--t4); margin-bottom: 4px;
}
.empty-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.4rem; }
.empty-sub { font-size: .86rem; color: var(--t3); max-width: 300px; }

/* ── RECOMMENDED ── */
.rec-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; }
.rec-card {
  background: var(--surface); border: 1px solid var(--edge);
  border-radius: var(--r3); overflow: hidden; transition: all .25s;
}
.rec-card:hover { border-color: rgba(255,45,85,.2); transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,.5); }
.rec-img { height: 130px; background: var(--bg2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: rgba(255,255,255,.06); }
.rec-body { padding: 14px; }
.rec-name { font-size: .82rem; font-weight: 500; line-height: 1.35; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rec-price { font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; margin-bottom: 10px; }
.rec-add { width: 100%; background: rgba(255,45,85,.08); border: 1.5px solid rgba(255,45,85,.18); color: var(--accent); border-radius: 100px; padding: 8px; font-family: 'DM Sans', sans-serif; font-size: .78rem; font-weight: 600; transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
.rec-add:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── TOAST ── */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast { background: var(--card-s); border: 1px solid var(--edge2); border-radius: var(--r3); padding: 12px 18px; display: flex; align-items: center; gap: 10px; font-size: .82rem; min-width: 240px; box-shadow: 0 16px 40px rgba(0,0,0,.8); transform: translateX(120%); transition: transform .32s cubic-bezier(.4,0,.2,1); }
.toast.in { transform: translateX(0); }

/* ── REVEAL ── */
.reveal { opacity: 0; transform: translateY(16px); transition: opacity .4s, transform .4s; }
.reveal.vis { opacity: 1; transform: none; }

/* ── RESPONSIVE ── */
@media (max-width: 1100px) { .cart-grid { grid-template-columns: 1fr 300px; } .rec-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 900px)  { .cart-grid { grid-template-columns: 1fr; } .summary-panel { position: static; } .rec-grid { grid-template-columns: repeat(2,1fr); } .page-wrap { padding: 20px 16px 60px; } .hdr { padding: 0 16px; } .breadcrumb { padding: 12px 16px; } }
@media (max-width: 640px)  {
  .cart-item { grid-template-columns: 64px 1fr; grid-template-rows: auto auto auto; gap: 12px; }
  .item-thumb { width: 64px; height: 64px; font-size: 1.5rem; }
  .item-info { grid-column: 2; }
  .qty-ctrl { grid-column: 1 / -1; width: fit-content; }
  .item-subtotal { grid-column: 1; text-align: left; }
  .item-remove { grid-column: 2; justify-self: end; }
  .steps-bar { display: none; }
  .rec-grid { grid-template-columns: repeat(2,1fr); gap: 10px; }
}
@media (max-width: 420px) { .rec-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<div class="page-bg"><div class="orb orb-1"></div><div class="orb orb-2"></div></div>

<!-- ANNOUNCE -->
<div class="announce">🚀 FREE DELIVERY on orders over Rs.&nbsp;2,000 &nbsp;·&nbsp; Call <a href="tel:+94773987246">+94 77 398 7246</a></div>

<!-- HEADER -->
<header class="hdr">
  <a href="index.php" class="logo">
    <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers">
  </a>
  <div class="hdr-right">
    <a href="shop.php"   class="hbtn"><i class="fas fa-store"></i> Shop</a>
    <a href="account.php" class="hbtn"><i class="fas fa-user-circle"></i> Account</a>
    <a href="checkout.php" class="hbtn hbtn-accent"><i class="fas fa-lock"></i> Checkout</a>
  </div>
</header>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href="index.php">Home</a>
  <i class="fas fa-chevron-right"></i>
  <span style="color:var(--t2)">Shopping Cart</span>
</div>

<!-- PAGE -->
<div class="page-wrap">

  <!-- PAGE HEAD -->
  <div class="page-head">
    <div>
      <div class="page-title">
        Shopping Cart
        <span class="cart-count"><?= $item_count ?> item<?= $item_count != 1 ? 's' : '' ?></span>
      </div>
    </div>
    <!-- PROGRESS STEPS -->
    <div class="steps-bar">
      <div class="step-node done">
        <div class="step-circle"><i class="fas fa-check"></i></div>
        Cart
      </div>
      <div class="step-line"></div>
      <div class="step-node active">
        <div class="step-circle">2</div>
        Checkout
      </div>
      <div class="step-line"></div>
      <div class="step-node">
        <div class="step-circle">3</div>
        Confirm
      </div>
    </div>
  </div>

  <?php if ($item_count > 0): ?>

  <div class="cart-grid">

    <!-- ── LEFT: ITEMS ── -->
    <div style="display:flex;flex-direction:column;gap:20px">

      <!-- ITEMS PANEL -->
      <div class="panel reveal">
        <div class="panel-head">
          <div class="panel-title">Cart Items</div>
          <button class="btn btn-ghost btn-sm" onclick="clearCart()"><i class="fas fa-trash"></i> Clear Cart</button>
        </div>

        <div id="cartItemsWrap">
          <?php foreach ($cart_items as $idx => $item):
            $ico = $prod_icons[$idx % count($prod_icons)];
          ?>
          <div class="cart-item" id="ci-<?= (int)$item['product_id'] ?>" data-id="<?= (int)$item['product_id'] ?>" data-price="<?= (float)$item['selling_price'] ?>" style="animation-delay:<?= $idx * .05 ?>s">

            <!-- Thumb -->
            <div class="item-thumb">
              <img src="assets/images/products/<?= htmlspecialchars($item['product_name']) ?>.jpg"
                   alt="<?= htmlspecialchars($item['product_name']) ?>"
                   onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas <?= $ico ?>\'></i>'">
            </div>

            <!-- Info -->
            <div class="item-info">
              <div class="item-brand"><?= htmlspecialchars($item['brand'] ?? 'Generic') ?></div>
              <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
              <div class="item-price">Rs. <?= number_format($item['selling_price'], 0) ?> each</div>
            </div>

            <!-- Qty -->
            <div class="qty-ctrl">
              <button class="qty-btn" onclick="changeQty(<?= (int)$item['product_id'] ?>, -1)"><i class="fas fa-minus"></i></button>
              <input class="qty-num" type="number" min="1" max="99"
                     value="<?= (int)$item['cart_quantity'] ?>"
                     id="qty-<?= (int)$item['product_id'] ?>"
                     onchange="setQty(<?= (int)$item['product_id'] ?>, this.value)">
              <button class="qty-btn" onclick="changeQty(<?= (int)$item['product_id'] ?>, 1)"><i class="fas fa-plus"></i></button>
            </div>

            <!-- Subtotal -->
            <div class="item-subtotal" id="sub-<?= (int)$item['product_id'] ?>">
              Rs. <?= number_format($item['subtotal'], 0) ?>
            </div>

            <!-- Remove -->
            <button class="item-remove" onclick="removeItem(<?= (int)$item['product_id'] ?>)" title="Remove item">
              <i class="fas fa-xmark"></i>
            </button>

          </div>
          <?php endforeach; ?>
        </div>

        <!-- CART FOOTER -->
        <div class="cart-footer">
          <a href="shop.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
          <div class="coupon-row">
            <input type="text" class="coupon-input" id="couponInp" placeholder="Promo code (e.g. SAP10)">
            <button class="btn btn-ghost btn-sm" onclick="applyCoupon()"><i class="fas fa-tag"></i> Apply</button>
          </div>
        </div>
      </div>

    </div><!-- /left -->

    <!-- ── RIGHT: SUMMARY ── -->
    <aside class="summary-panel">
      <div class="panel reveal">

        <div class="panel-head">
          <div class="panel-title">Order Summary</div>
        </div>

        <!-- SHIPPING PROGRESS -->
        <?php if ($shipping > 0):
          $pct = min(100, round(($total / 2000) * 100));
          $left = 2000 - $total;
        ?>
        <div class="ship-progress">
          <div class="ship-label">
            <span>Add <span class="hl">Rs. <?= number_format($left, 0) ?></span> for free shipping</span>
            <span><?= $pct ?>%</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php else: ?>
        <div class="ship-progress">
          <div class="ship-label"><span><span class="hl"><i class="fas fa-circle-check"></i> You've unlocked free shipping!</span></span></div>
          <div class="progress-bar"><div class="progress-fill" style="width:100%"></div></div>
        </div>
        <?php endif; ?>

        <!-- ROWS -->
        <div class="summary-rows">
          <div class="sum-row"><span class="lbl">Subtotal (<?= $item_count ?> items)</span><span class="val" id="sumSubtotal">Rs. <?= number_format($total, 0) ?></span></div>
          <div class="sum-row"><span class="lbl">Tax (15%)</span><span class="val" id="sumTax">Rs. <?= number_format($tax, 0) ?></span></div>
          <div class="sum-row">
            <span class="lbl">Shipping</span>
            <span class="val" id="sumShip"><?= $shipping > 0 ? 'Rs. ' . number_format($shipping, 0) : '<span class="free"><i class="fas fa-truck-fast"></i> FREE</span>' ?></span>
          </div>
          <div id="discountRow" style="display:none" class="sum-row">
            <span class="lbl" style="color:var(--green)">Discount</span>
            <span class="val" id="discountVal" style="color:var(--green)"></span>
          </div>
        </div>

        <!-- TOTAL -->
        <div class="sum-total">
          <span class="lbl">Total</span>
          <span class="val" id="sumTotal">Rs. <?= number_format($grand_total, 0) ?></span>
        </div>

        <!-- ACTIONS -->
        <div class="summary-actions">
          <a href="checkout.php" class="btn btn-primary btn-checkout">
            <i class="fas fa-lock"></i> Proceed to Checkout
          </a>
          <div style="text-align:center;font-size:.72rem;color:var(--t4);display:flex;align-items:center;justify-content:center;gap:5px">
            <i class="fas fa-shield-halved" style="color:var(--accent)"></i> Secure & encrypted checkout
          </div>
        </div>

        <!-- TRUST -->
        <div class="trust-mini">
          <div class="trust-row"><i class="fas fa-truck-fast"></i> Free delivery on orders over Rs. 2,000</div>
          <div class="trust-row"><i class="fas fa-rotate-left"></i> 30-day hassle-free returns</div>
          <div class="trust-row"><i class="fas fa-headset"></i> Support Mon–Sun 9am–9pm</div>
        </div>

        <!-- PAYMENT ICONS -->
        <div style="padding:14px 24px;border-top:1px solid var(--edge);display:flex;gap:6px;flex-wrap:wrap">
          <?php foreach(['VISA','Mastercard','Bank Transfer','COD'] as $pm): ?>
          <span style="font-size:.6rem;font-weight:600;padding:3px 8px;border-radius:4px;background:var(--bg2);color:var(--t3);border:1px solid var(--edge)"><?= $pm ?></span>
          <?php endforeach; ?>
        </div>

      </div>
    </aside>

  </div><!-- /cart-grid -->

  <?php else: ?>

  <!-- EMPTY CART -->
  <div class="panel reveal">
    <div class="empty-wrap">
      <div class="empty-icon"><i class="fas fa-bag-shopping"></i></div>
      <div class="empty-title">Your cart is empty</div>
      <div class="empty-sub">Looks like you haven't added anything yet. Browse our store and find something you'll love!</div>
      <a href="shop.php" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-store"></i> Start Shopping</a>
    </div>
  </div>

  <?php endif; ?>

  <!-- ── YOU MAY ALSO LIKE ── -->
  <?php
  try {
    $pdoRec = Database::getInstance();
    $recStmt = $pdoRec->query("SELECT product_id, product_name, brand, selling_price FROM products ORDER BY RAND() LIMIT 4");
    $recommended = $recStmt->fetchAll(PDO::FETCH_ASSOC);
  } catch(Exception $e) { $recommended = []; }
  if (!empty($recommended)):
  ?>
  <section style="margin-top:40px" class="reveal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px">
      <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.15rem;letter-spacing:-.02em;display:flex;align-items:center;gap:8px">
        <span style="width:3px;height:20px;background:var(--accent);border-radius:2px;display:inline-block"></span>
        You May Also Like
      </div>
      <a href="shop.php" style="font-size:.78rem;font-weight:600;color:var(--accent);display:flex;align-items:center;gap:4px;transition:gap .2s" onmouseover="this.style.gap='8px'" onmouseout="this.style.gap='4px'">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="rec-grid">
      <?php foreach($recommended as $ri => $rp):
        $rico = $prod_icons[$ri % count($prod_icons)]; ?>
      <div class="rec-card">
        <div class="rec-img"><i class="fas <?= $rico ?>"></i></div>
        <div class="rec-body">
          <div class="rec-name"><?= htmlspecialchars($rp['product_name']) ?></div>
          <div class="rec-price">Rs. <?= number_format($rp['selling_price'], 0) ?></div>
          <button class="rec-add add-to-cart" data-id="<?= (int)$rp['product_id'] ?>">
            <i class="fas fa-cart-plus"></i> Add to Cart
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div><!-- /page-wrap -->

<!-- TOAST -->
<div class="toast-wrap" id="toasts"></div>

<!-- WA FLOAT -->
<a href="https://web.whatsapp.com/send?phone=94773987246" style="position:fixed;bottom:28px;left:28px;z-index:8000;width:52px;height:52px;border-radius:50%;background:#25d366;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;box-shadow:0 4px 20px rgba(37,211,102,.4);transition:all .2s" target="_blank" onmouseover="this.style.transform='scale(1.12)'" onmouseout="this.style.transform='scale(1)'">
  <i class="fab fa-whatsapp"></i>
</a>

<script>
/* ─ State ─ */
const prices = {};
<?php foreach ($cart_items as $it): ?>
prices[<?= (int)$it['product_id'] ?>] = <?= (float)$it['selling_price'] ?>;
<?php endforeach; ?>

let discount = 0;

/* ─ Toast ─ */
function showToast(msg, color = 'var(--green)') {
  const w = document.getElementById('toasts');
  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = `<i class="fas fa-check-circle" style="color:${color};font-size:1rem;flex-shrink:0"></i><span>${msg}</span>`;
  w.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('in')));
  setTimeout(() => { t.classList.remove('in'); setTimeout(() => t.remove(), 380); }, 3200);
}

/* ─ Recalculate totals ─ */
function recalc() {
  let sub = 0;
  document.querySelectorAll('.cart-item').forEach(row => {
    const id  = +row.dataset.id;
    const qty = +document.getElementById('qty-' + id).value;
    const st  = prices[id] * qty;
    sub += st;
    document.getElementById('sub-' + id).textContent = 'Rs. ' + st.toLocaleString('en-US', {maximumFractionDigits:0});
  });
  const tax      = sub * 0.15;
  const ship     = sub > 2000 ? 0 : 200;
  const disc     = sub * discount;
  const grand    = sub + tax + ship - disc;

  document.getElementById('sumSubtotal').textContent = 'Rs. ' + sub.toLocaleString('en-US',{maximumFractionDigits:0});
  document.getElementById('sumTax').textContent      = 'Rs. ' + tax.toLocaleString('en-US',{maximumFractionDigits:0});
  document.getElementById('sumShip').innerHTML       = ship > 0
    ? 'Rs. ' + ship.toLocaleString('en-US',{maximumFractionDigits:0})
    : '<span class="free"><i class="fas fa-truck-fast"></i> FREE</span>';
  document.getElementById('sumTotal').textContent    = 'Rs. ' + grand.toLocaleString('en-US',{maximumFractionDigits:0});

  if (discount > 0) {
    document.getElementById('discountRow').style.display = '';
    document.getElementById('discountVal').textContent   = '- Rs. ' + disc.toLocaleString('en-US',{maximumFractionDigits:0});
  }

  // Update shipping progress bar
  const pct = Math.min(100, Math.round((sub / 2000) * 100));
  const fill = document.querySelector('.progress-fill');
  if (fill) fill.style.width = pct + '%';
  const sLabel = document.querySelector('.ship-label');
  if (sLabel) {
    if (sub >= 2000) sLabel.innerHTML = '<span><span class="hl"><i class="fas fa-circle-check"></i> Free shipping unlocked!</span></span>';
    else sLabel.innerHTML = `<span>Add <span class="hl">Rs. ${(2000-sub).toLocaleString('en-US',{maximumFractionDigits:0})}</span> for free shipping</span><span>${pct}%</span>`;
  }
}

/* ─ Change qty ─ */
function changeQty(id, delta) {
  const inp = document.getElementById('qty-' + id);
  const nv  = Math.max(1, Math.min(99, +inp.value + delta));
  inp.value = nv;
  updateCart(id, nv);
}
function setQty(id, val) {
  const nv = Math.max(1, Math.min(99, +val || 1));
  document.getElementById('qty-' + id).value = nv;
  updateCart(id, nv);
}

async function updateCart(id, qty) {
  recalc();
  try {
    await fetch('cart_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `product_id=${id}&quantity=${qty}`
    });
  } catch(e) {}
}

/* ─ Remove item ─ */
async function removeItem(id) {
  const row = document.getElementById('ci-' + id);
  row.style.transition = 'opacity .28s, transform .28s';
  row.style.opacity = '0'; row.style.transform = 'translateX(-12px)';
  setTimeout(() => { row.remove(); recalc(); }, 300);
  showToast('Item removed from cart', 'var(--accent)');
  try {
    await fetch('cart_remove.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `product_id=${id}`
    });
  } catch(e) {}
}

/* ─ Clear cart ─ */
async function clearCart() {
  if (!confirm('Remove all items from your cart?')) return;
  document.querySelectorAll('.cart-item').forEach((r,i) => {
    setTimeout(() => { r.style.transition='opacity .2s,transform .2s'; r.style.opacity='0'; r.style.transform='translateX(-10px)'; setTimeout(()=>r.remove(),220); }, i*50);
  });
  setTimeout(recalc, 400);
  showToast('Cart cleared');
  try { await fetch('cart_clear.php', {method:'POST'}); } catch(e) {}
}

/* ─ Coupon ─ */
function applyCoupon() {
  const code = document.getElementById('couponInp').value.trim().toUpperCase();
  if (code === 'SAP10') {
    discount = 0.10;
    recalc();
    showToast('🎉 Coupon applied — 10% off!');
    document.getElementById('couponInp').value = '';
    document.getElementById('couponInp').style.borderColor = 'var(--green)';
  } else if (code === '') {
    showToast('Enter a promo code', 'var(--gold)');
  } else {
    showToast('Invalid promo code', 'var(--accent)');
    document.getElementById('couponInp').style.borderColor = 'var(--accent)';
    setTimeout(() => document.getElementById('couponInp').style.borderColor = '', 1500);
  }
}
document.getElementById('couponInp')?.addEventListener('keypress', e => { if(e.key==='Enter') applyCoupon(); });

/* ─ Add recommended to cart ─ */
document.querySelectorAll('.add-to-cart').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.id;
    const orig = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    this.disabled = true;
    try {
      const r = await fetch('cart_add.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`product_id=${id}&quantity=1` });
      const d = await r.json();
      if (d.success) {
        showToast('Added to cart!');
        this.innerHTML = '<i class="fas fa-check"></i> Added';
        setTimeout(() => { this.innerHTML = orig; this.disabled = false; }, 2000);
      } else {
        showToast(d.message || 'Could not add', 'var(--accent)');
        this.innerHTML = orig; this.disabled = false;
      }
    } catch { showToast('Sign in to add items','var(--accent)'); this.innerHTML=orig; this.disabled=false; }
  });
});

/* ─ Scroll reveal ─ */
const io = new IntersectionObserver(es => es.forEach(e => { if(e.isIntersecting){e.target.classList.add('vis');io.unobserve(e.target);} }), {threshold:.05});
document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>
</body>
</html>