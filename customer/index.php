<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$featured_products = [];
$trending_products = [];
$categories = [];
$db_error = false;
$is_logged_in = isset($_SESSION['customer_id']);

try {
    $pdo = Database::getInstance();
    $sql = "SELECT product_id, product_name, brand, selling_price, category_id FROM products ORDER BY product_id DESC LIMIT 8";
    $featured_products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $sql = "SELECT product_id, product_name, brand, selling_price, category_id FROM products ORDER BY selling_price DESC LIMIT 5";
    $trending_products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ([&$featured_products, &$trending_products] as &$arr) {
        foreach ($arr as &$prod) {
            $stock = $pdo->query("SELECT COALESCE(SUM(quantity),0) as qty FROM stock WHERE product_id = " . (int)$prod['product_id'])->fetch(PDO::FETCH_ASSOC);
            $prod['qty'] = (int)($stock['qty'] ?? 0);
        }
    }
    $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name LIMIT 8";
    $categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $db_error = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SAP Computers — Laptops, PCs, Printers & CCTV | Gampaha</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ══════════════════════════════════════
   TOKENS & RESET
══════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0a0f;
  --bg2: #0f0f17;
  --surface: #13131e;
  --card: #16162200;
  --card-solid: #161622;
  --edge: rgba(255,255,255,.06);
  --edge2: rgba(255,255,255,.11);
  --edge3: rgba(255,255,255,.18);
  --accent: #ff2d55;
  --accent2: #ff6b35;
  --accent-glow: rgba(255,45,85,.18);
  --accent-glow2: rgba(255,45,85,.06);
  --blue: #3d8ef8;
  --cyan: #00d4c8;
  --gold: #f5a623;
  --green: #30d158;
  --purple: #8366ff;
  --t1: #f5f5fa;
  --t2: rgba(245,245,250,.65);
  --t3: rgba(245,245,250,.38);
  --t4: rgba(245,245,250,.16);
  --r: 8px;
  --r2: 12px;
  --r3: 16px;
  --r4: 20px;
  --shadow: 0 24px 64px rgba(0,0,0,.6);
  --shadow-sm: 0 4px 16px rgba(0,0,0,.3);
  --shadow-lg: 0 32px 96px rgba(0,0,0,.8);
}
html { scroll-behavior: smooth; font-size: 14px; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--t1); overflow-x: hidden; line-height: 1.5; }
a { color: inherit; text-decoration: none; }
button { font-family: inherit; }
img { display: block; max-width: 100%; }

/* ══════════════════════════════════════
   SCROLLBAR
══════════════════════════════════════ */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: rgba(255,45,85,.35); border-radius: 4px; }

/* ══════════════════════════════════════
   ANNOUNCEMENT BAR
══════════════════════════════════════ */
.announce {
  background: linear-gradient(90deg, var(--accent), #c8102e, var(--accent));
  background-size: 200% 100%;
  animation: gradSlide 6s linear infinite;
  color: #fff;
  text-align: center;
  padding: 9px 16px;
  font-size: .78rem;
  font-weight: 500;
  letter-spacing: .01em;
  position: relative;
  z-index: 100;
}
.announce strong { font-weight: 700; }
.announce a { color: #fff; text-decoration: underline; text-underline-offset: 2px; }
@keyframes gradSlide { 0%{background-position:0%} 100%{background-position:200%} }

/* ══════════════════════════════════════
   HEADER
══════════════════════════════════════ */
.hdr {
  background: linear-gradient(180deg, rgba(10,10,15,.98), rgba(10,10,15,.92));
  backdrop-filter: blur(24px) saturate(1.8);
  -webkit-backdrop-filter: blur(24px);
  border-bottom: 1px solid var(--edge);
  position: sticky;
  top: 0;
  z-index: 500;
  height: 68px;
  display: flex;
  align-items: center;
  padding: 0 32px;
  gap: 24px;
  box-shadow: 0 8px 32px rgba(0,0,0,.5);
}
.logo {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 1.55rem;
  letter-spacing: -.03em;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 2px;
}
.logo img { height: 36px; width: auto; }
.logo-dot { color: var(--accent); }

.search-wrap {
  flex: 1;
  max-width: 560px;
  position: relative;
  display: flex;
  align-items: center;
}
.search-form {
  display: flex;
  width: 100%;
  background: var(--surface);
  border: 1.5px solid var(--edge2);
  border-radius: 100px;
  overflow: hidden;
  transition: all .28s;
  box-shadow: 0 8px 24px rgba(0,0,0,.2);
}
.search-form:focus-within {
  border-color: var(--accent);
  box-shadow: 0 12px 40px rgba(255,45,85,.25);
  background: linear-gradient(135deg, var(--surface), rgba(255,45,85,.02));
}
.search-cat {
  background: transparent;
  border: none;
  border-right: 1px solid var(--edge);
  color: var(--t2);
  font-family: 'DM Sans', sans-serif;
  font-size: .78rem;
  font-weight: 500;
  padding: 0 14px;
  height: 44px;
  cursor: pointer;
  outline: none;
  min-width: 120px;
}
.search-cat option { background: var(--surface); }
.search-inp {
  flex: 1;
  background: transparent;
  border: none;
  color: var(--t1);
  font-family: 'DM Sans', sans-serif;
  font-size: .88rem;
  padding: 0 16px;
  height: 44px;
  outline: none;
}
.search-inp::placeholder { color: var(--t3); }
.search-btn {
  background: linear-gradient(135deg, var(--accent), #ff1745);
  border: none;
  color: #fff;
  width: 44px;
  height: 44px;
  border-radius: 0 100px 100px 0;
  cursor: pointer;
  font-size: .9rem;
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.search-btn::before { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,.2); opacity: 0; transition: opacity .24s; }
.search-btn:hover { transform: translateX(2px); }
.search-btn:hover::before { opacity: 1; }

.hdr-right {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-left: auto;
  flex-shrink: 0;
}
.hbtn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 14px;
  border-radius: var(--r2);
  color: var(--t2);
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  cursor: pointer;
  transition: all .24s;
  border: none;
  background: transparent;
  position: relative;
  white-space: nowrap;
}
.hbtn i { font-size: 1.1rem; margin-bottom: 1px; transition: transform .24s; }
.hbtn:hover { background: rgba(255,45,85,.08); color: var(--accent); }
.hbtn:hover i { transform: scale(1.15); }
.hbtn-cart {
  background: linear-gradient(135deg, var(--accent), #ff1745) !important;
  color: #fff !important;
  flex-direction: row !important;
  gap: 8px !important;
  font-size: .8rem !important;
  padding: 10px 20px !important;
  border-radius: 100px !important;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(255,45,85,.3);
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
}
.hbtn-cart:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(255,45,85,.4); }
.cart-badge {
  position: absolute;
  top: 4px;
  right: 10px;
  background: var(--gold);
  color: #000;
  border-radius: 50%;
  width: 17px;
  height: 17px;
  font-size: .6rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--bg);
}

/* ══════════════════════════════════════
   NAV
══════════════════════════════════════ */
.nav-bar {
  background: var(--bg2);
  border-bottom: 1px solid var(--edge);
  display: flex;
  align-items: stretch;
  padding: 0 32px;
  position: relative;
  top: auto;
  z-index: 49;
}
.all-cats-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 20px;
  height: 46px;
  background: linear-gradient(135deg, var(--accent), #ff1745);
  color: #fff;
  border: none;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  font-weight: 600;
  font-size: .8rem;
  letter-spacing: .02em;
  flex-shrink: 0;
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
  margin: 6px 0;
  border-radius: var(--r2);
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(255,45,85,.25);
}
.all-cats-btn::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.15), transparent); opacity: 0; transition: opacity .24s; }
.all-cats-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(255,45,85,.35); }
.all-cats-btn:hover::before { opacity: 1; }

.nav-links {
  display: flex;
  list-style: none;
  overflow-x: auto;
  scrollbar-width: none;
  flex: 1;
}
.nav-links::-webkit-scrollbar { display: none; }
.nav-links li a {
  display: flex;
  align-items: center;
  height: 58px;
  padding: 0 16px;
  font-size: .82rem;
  font-weight: 500;
  color: var(--t2);
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
  position: relative;
}
.nav-links li a::after { content: ''; position: absolute; bottom: -2px; left: 16px; right: 16px; height: 2px; background: linear-gradient(90deg, var(--accent), transparent); transform: scaleX(0); transform-origin: left; transition: transform .24s; }
.nav-links li a:hover,
.nav-links li a.active { color: var(--t1); border-color: var(--accent); }
.nav-links li a:hover::after { transform: scaleX(1); }
.nav-links li a.deal { color: var(--accent2) !important; }
.nav-links li a.deal:hover { border-color: var(--accent2); }

.nav-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-left: auto;
  flex-shrink: 0;
  font-size: .76rem;
  color: var(--t3);
}
.nav-meta i { color: var(--green); }

/* MEGA MENU */
.mega-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  width: 280px;
  background: var(--card-solid);
  border: 1px solid var(--edge2);
  border-radius: var(--r3);
  box-shadow: 0 32px 80px rgba(0,0,0,.8);
  z-index: 600;
  overflow: hidden;
  display: none;
  animation: fadeDown .18s ease;
}
.mega-menu.open { display: block; }
@keyframes fadeDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.mega-menu a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 13px 16px;
  font-size: .82rem;
  color: var(--t2);
  border-bottom: 1px solid var(--edge);
  transition: all .18s;
  position: relative;
}
.mega-menu a::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--accent); transform: scaleY(0); transform-origin: center; transition: transform .18s; }
.mega-menu a:hover { background: rgba(255,45,85,.08); color: var(--accent); padding-left: 20px; }
.mega-menu a:hover::before { transform: scaleY(1); }
.mega-menu a:last-child { border: none; background: rgba(255,45,85,.04); color: var(--accent); font-weight: 600; }
.mega-menu .m-ico { width: 22px; height: 22px; border-radius: 6px; background: rgba(255,45,85,.1); display: flex; align-items: center; justify-content: center; font-size: .72rem; color: var(--accent); flex-shrink: 0; }
.mega-menu .m-arr { margin-left: auto; font-size: .6rem; color: var(--t4); }

/* ══════════════════════════════════════
   HERO
══════════════════════════════════════ */
.hero {
  height: 540px;
  position: relative;
  overflow: hidden;
  background: radial-gradient(ellipse 120% 100% at 50% 100%, rgba(255,45,85,.08), transparent);
}
.hero-slides { display: flex; height: 100%; transition: transform .6s cubic-bezier(.4,0,.2,1); }
.slide {
  min-width: 100%;
  height: 100%;
  position: relative;
  display: flex;
  align-items: center;
  overflow: hidden;
}
.slide-bg { position: absolute; inset: 0; }
.s1 .slide-bg { background: radial-gradient(ellipse 80% 100% at 80% 50%, #1a0010 0%, #0a0a0f 60%); }
.s2 .slide-bg { background: radial-gradient(ellipse 80% 100% at 80% 50%, #00101a 0%, #0a0a0f 60%); }
.s3 .slide-bg { background: radial-gradient(ellipse 80% 100% at 80% 50%, #0f0f00 0%, #0a0a0f 60%); }

.slide-noise {
  position: absolute;
  inset: 0;
  opacity: .025;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size: 200px;
}
.slide-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  pointer-events: none;
}
.s1 .slide-orb { width: 700px; height: 700px; background: rgba(255,45,85,.12); right: -200px; top: -150px; }
.s2 .slide-orb { width: 700px; height: 700px; background: rgba(61,142,248,.1); right: -200px; top: -150px; }
.s3 .slide-orb { width: 700px; height: 700px; background: rgba(245,166,35,.08); right: -200px; top: -150px; }

.slide-word {
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: clamp(120px, 16vw, 220px);
  opacity: .03;
  color: #fff;
  letter-spacing: -.04em;
  pointer-events: none;
  white-space: nowrap;
  line-height: 1;
}

.slide-content {
  position: relative;
  z-index: 2;
  padding: 0 80px;
  max-width: 680px;
  animation: slideInLeft .8s ease-out;
}
@keyframes slideInLeft { from { opacity: 0; transform: translateX(-40px); } to { opacity: 1; transform: translateX(0); } }
.slide-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: 6px 14px;
  border-radius: 100px;
  margin-bottom: 18px;
}
.s1 .slide-eyebrow { background: rgba(255,45,85,.14); color: var(--accent); border: 1px solid rgba(255,45,85,.25); }
.s2 .slide-eyebrow { background: rgba(61,142,248,.12); color: var(--blue); border: 1px solid rgba(61,142,248,.22); }
.s3 .slide-eyebrow { background: rgba(245,166,35,.12); color: var(--gold); border: 1px solid rgba(245,166,35,.22); }

.slide-title {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: clamp(2.4rem, 4.5vw, 4rem);
  line-height: 1;
  letter-spacing: -.025em;
  margin-bottom: 14px;
}
.s1 .slide-hl { color: var(--accent); }
.s2 .slide-hl { color: var(--blue); }
.s3 .slide-hl { color: var(--gold); }

.slide-sub {
  font-size: .95rem;
  color: var(--t2);
  margin-bottom: 28px;
  font-weight: 400;
  max-width: 480px;
  line-height: 1.6;
}
.slide-price-tag {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
  margin-bottom: 28px;
  font-size: .85rem;
  color: var(--t3);
}
.slide-price-tag strong {
  font-family: 'Syne', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--t1);
}
.slide-price-tag .instock { color: var(--green); font-size: .78rem; font-weight: 600; }

.slide-btns { display: flex; gap: 10px; flex-wrap: wrap; }

/* ══════════════════════════════════════
   BUTTONS
══════════════════════════════════════ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  font-weight: 600;
  border-radius: 100px;
  transition: all .22s;
  white-space: nowrap;
  font-size: .85rem;
  padding: 12px 26px;
}
.btn-primary { background: linear-gradient(135deg, var(--accent), #ff1745); color: #fff; position: relative; overflow: hidden; }
.btn-primary::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.2), transparent); opacity: 0; transition: opacity .3s; }
.btn-primary:hover { transform: translateY(-3px); box-shadow: 0 16px 48px rgba(255,45,85,.4); }
.btn-primary:hover::before { opacity: 1; }
.btn-secondary { background: rgba(255,255,255,.08); color: var(--t1); border: 1.5px solid var(--edge2); position: relative; }
.btn-secondary:hover { border-color: var(--accent); background: rgba(255,45,85,.08); color: var(--accent); transform: translateY(-2px); }
.btn-sm { padding: 8px 18px; font-size: .78rem; }
.btn-icon { width: 40px; height: 40px; padding: 0; justify-content: center; border-radius: 50%; }

/* HERO CONTROLS */
.hero-dots {
  position: absolute;
  bottom: 28px;
  left: 80px;
  display: flex;
  gap: 6px;
  z-index: 5;
}
.hero-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(255,255,255,.25);
  border: none;
  cursor: pointer;
  transition: all .3s;
}
.hero-dot.on { width: 24px; border-radius: 3px; background: var(--accent); }
.hero-arrs {
  position: absolute;
  bottom: 24px;
  right: 40px;
  display: flex;
  gap: 8px;
  z-index: 5;
}
.hero-arr {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: 1.5px solid var(--edge2);
  background: rgba(0,0,0,.5);
  color: var(--t1);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .85rem;
  transition: all .2s;
  backdrop-filter: blur(8px);
}
.hero-arr:hover { background: var(--accent); border-color: var(--accent); }

/* ══════════════════════════════════════
   TRUST STRIP
══════════════════════════════════════ */
.trust-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border-bottom: 1px solid var(--edge);
}
.trust-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 22px 28px;
  border-right: 1px solid var(--edge);
  transition: all .24s;
  position: relative;
}
.trust-item::before { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--accent), transparent); transform: scaleX(0); transform-origin: left; transition: transform .24s; }
.trust-item:last-child { border-right: none; }
.trust-item:hover { background: rgba(255,45,85,.04); }
.trust-item:hover::before { transform: scaleX(1); }
.trust-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--r2);
  background: linear-gradient(135deg, rgba(255,45,85,.12), rgba(255,45,85,.06));
  border: 1px solid rgba(255,45,85,.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: var(--accent);
  flex-shrink: 0;
  transition: all .24s;
}
.trust-item:hover .trust-icon { transform: scale(1.1) rotateY(10deg); background: linear-gradient(135deg, rgba(255,45,85,.18), rgba(255,45,85,.1)); }
.trust-title { font-size: .84rem; font-weight: 600; margin-bottom: 2px; }
.trust-sub { font-size: .72rem; color: var(--t3); }

/* ══════════════════════════════════════
   FLASH DEALS
══════════════════════════════════════ */
.flash-bar {
  background: var(--surface);
  border-bottom: 1px solid var(--edge);
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 12px 32px;
  overflow: hidden;
}
.flash-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Syne', sans-serif;
  font-weight: 700;
  font-size: .95rem;
  letter-spacing: -.01em;
  flex-shrink: 0;
  color: var(--accent);
}
.flash-pulse { animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.countdown { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.cd-sep { font-size: 1rem; font-weight: 700; color: var(--t3); }
.cd-box { background: var(--bg); border: 1px solid var(--edge2); border-radius: var(--r); padding: 4px 9px; min-width: 44px; text-align: center; }
.cd-num { font-family: 'Syne', sans-serif; font-size: 1.25rem; font-weight: 700; line-height: 1; display: block; }
.cd-label { font-size: .55rem; color: var(--t4); letter-spacing: .08em; text-transform: uppercase; }
.flash-divider { width: 1px; height: 40px; background: var(--edge); flex-shrink: 0; }
.flash-items { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; flex: 1; }
.flash-items::-webkit-scrollbar { display: none; }
.flash-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, var(--bg2), var(--surface));
  border: 1px solid var(--edge);
  border-radius: var(--r2);
  padding: 10px 16px;
  flex-shrink: 0;
  cursor: pointer;
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
  position: relative;
}
.flash-chip:hover { border-color: var(--accent); background: linear-gradient(135deg, rgba(255,45,85,.1), var(--surface)); transform: translateY(-4px); box-shadow: 0 12px 32px rgba(255,45,85,.2); }
.flash-name { font-size: .75rem; font-weight: 500; color: var(--t1); max-width: 130px; line-height: 1.3; }
.flash-price { font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 700; color: var(--accent); }
.flash-was { font-size: .66rem; color: var(--t3); text-decoration: line-through; }
.flash-badge { background: var(--accent); color: #fff; font-size: .58rem; font-weight: 700; padding: 3px 7px; border-radius: 100px; }

/* ══════════════════════════════════════
   LAYOUT
══════════════════════════════════════ */
.container { max-width: 1400px; margin: 0 auto; padding: 0 32px; }
.section { padding: 48px 0; }
.section-head { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; gap: 16px; }
.section-title {
  font-family: 'Syne', sans-serif;
  font-weight: 700;
  font-size: 1.5rem;
  letter-spacing: -.025em;
  display: flex;
  align-items: center;
  gap: 12px;
}
.section-title::before {
  content: '';
  width: 4px;
  height: 28px;
  background: linear-gradient(180deg, var(--accent), var(--accent2));
  border-radius: 2px;
  flex-shrink: 0;
  box-shadow: 0 0 16px rgba(255,45,85,.4);
}
.section-sub { font-size: .8rem; color: var(--t3); margin-top: 3px; }
.see-all {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: .78rem;
  font-weight: 600;
  color: var(--accent);
  transition: gap .2s;
  white-space: nowrap;
}
.see-all:hover { gap: 9px; }
.pill {
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 100px;
}
.pill-red { background: var(--accent); color: #fff; }
.pill-new { background: rgba(0,212,200,.15); color: var(--cyan); border: 1px solid rgba(0,212,200,.25); }
.pill-hot { background: rgba(245,166,35,.12); color: var(--gold); border: 1px solid rgba(245,166,35,.22); }

/* ══════════════════════════════════════
   CATEGORY GRID
══════════════════════════════════════ */
.cat-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px; }
.cat-tile {
  background: linear-gradient(135deg, var(--surface), rgba(22,22,34,.6));
  border: 1px solid var(--edge);
  border-radius: var(--r3);
  padding: 20px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 11px;
  cursor: pointer;
  transition: all .28s cubic-bezier(.34, 1.56, .64, 1);
  text-align: center;
  position: relative;
  overflow: hidden;
}
.cat-tile::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 50% 0%, rgba(255,45,85,.1), transparent 70%); opacity: 0; transition: opacity .28s; }
.cat-tile:hover { border-color: var(--accent); background: linear-gradient(135deg, rgba(255,45,85,.08), rgba(255,45,85,.02)); transform: translateY(-6px); box-shadow: 0 20px 48px rgba(255,45,85,.25); }
.cat-tile:hover::before { opacity: 1; }
.cat-icon {
  width: 54px;
  height: 54px;
  border-radius: var(--r2);
  background: linear-gradient(135deg, var(--bg2), rgba(15,15,23,.4));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--t3);
  transition: all .28s cubic-bezier(.34, 1.56, .64, 1);
  border: 1px solid var(--edge);
}
.cat-tile:hover .cat-icon { background: linear-gradient(135deg, rgba(255,45,85,.15), rgba(255,45,85,.05)); color: var(--accent); border-color: var(--accent); transform: scale(1.12) rotate(8deg); }
.cat-name { font-size: .72rem; font-weight: 600; color: var(--t2); transition: color .2s; }
.cat-tile:hover .cat-name { color: var(--t1); }

/* ══════════════════════════════════════
   PRODUCT CARDS
══════════════════════════════════════ */
.prod-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.prod-grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
.prod-grid-6 { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; }

.pcard {
  background: linear-gradient(135deg, var(--surface), rgba(22,22,34,.5));
  border: 1px solid var(--edge);
  border-radius: var(--r3);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
  transition: all .28s cubic-bezier(.34, 1.56, .64, 1);
  height: 100%;
}
.pcard::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,45,85,.08), transparent); opacity: 0; transition: opacity .28s; pointer-events: none; z-index: 1; }
.pcard:hover {
  border-color: var(--accent);
  transform: translateY(-8px);
  box-shadow: 0 32px 72px rgba(255,45,85,.3);
}
.pcard:hover::after { opacity: 1; }
.pcard-img {
  height: 190px;
  background: linear-gradient(135deg, var(--bg2), rgba(15,15,23,.6));
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
  transition: all .3s;
}
.pcard-img::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at center, rgba(255,45,85,.05), transparent 70%); opacity: 0; transition: opacity .3s; }
.pcard:hover .pcard-img { background: linear-gradient(135deg, rgba(255,45,85,.08), rgba(255,45,85,.03)); }
.pcard:hover .pcard-img::before { opacity: 1; }
.pcard-img-ico {
  font-size: 4rem;
  color: rgba(255,255,255,.06);
  transition: all .3s cubic-bezier(.34, 1.56, .64, 1);
  position: relative;
  z-index: 1;
}
.pcard:hover .pcard-img-ico { color: rgba(255,45,85,.25); transform: scale(1.18) rotateY(-10deg); }

.pcard-badges {
  position: absolute;
  top: 10px;
  left: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  z-index: 2;
}
.badge {
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 100px;
}
.badge-new { background: rgba(0,212,200,.15); color: var(--cyan); border: 1px solid rgba(0,212,200,.25); }
.badge-sale { background: var(--accent); color: #fff; }
.badge-hot { background: rgba(245,166,35,.15); color: var(--gold); border: 1px solid rgba(245,166,35,.25); }
.badge-out { background: rgba(255,255,255,.06); color: var(--t3); border: 1px solid var(--edge); }

.pcard-wish {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(255,45,85,.2), rgba(0,0,0,.6));
  border: 1px solid rgba(255,45,85,.3);
  color: var(--t3);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: .85rem;
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
  backdrop-filter: blur(12px);
  z-index: 2;
}
.pcard-wish:hover { background: linear-gradient(135deg, var(--accent), rgba(255,45,85,.8)); color: #fff; border-color: var(--accent); transform: scale(1.15); }
}

.pcard-quick {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(to top, rgba(10,10,15,.97) 70%, transparent);
  padding: 24px 12px 12px;
  display: flex;
  gap: 6px;
  transform: translateY(100%);
  transition: transform .28s cubic-bezier(.4,0,.2,1);
  z-index: 3;
}
.pcard:hover .pcard-quick { transform: translateY(0); }

.pcard-body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
.pcard-brand { font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--t4); }
.pcard-name { font-size: .86rem; font-weight: 500; color: var(--t1); line-height: 1.4; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.pcard-stars { font-size: .7rem; color: var(--gold); letter-spacing: .5px; }
.pcard-stars span { color: var(--t4); margin-left: 4px; font-size: .68rem; }
.pcard-price-row { display: flex; align-items: baseline; gap: 7px; flex-wrap: wrap; }
.pcard-price { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; }
.pcard-was { font-size: .75rem; color: var(--t3); text-decoration: line-through; }
.pcard-disc { font-size: .62rem; font-weight: 700; background: rgba(255,45,85,.1); color: var(--accent); padding: 2px 7px; border-radius: 100px; }
.pcard-stock { font-size: .68rem; }
.stock-in { color: var(--green); }
.stock-low { color: var(--gold); }
.stock-out { color: var(--t3); }
.pcard-cta {
  width: 100%;
  background: linear-gradient(135deg, var(--accent), #ff1745);
  border: none;
  color: #fff;
  border-radius: 100px;
  padding: 12px;
  font-family: 'DM Sans', sans-serif;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: auto;
  position: relative;
  overflow: hidden;
}
.pcard-cta::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.2), transparent); opacity: 0; transition: opacity .24s; }
.pcard-cta:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(255,45,85,.35); }
.pcard-cta:hover::before { opacity: 1; }
.pcard-cta:disabled { background: var(--t4); cursor: not-allowed; color: var(--t3); box-shadow: none; }
.pcard-cta:disabled { background: var(--bg2); border-color: var(--edge); color: var(--t4); cursor: not-allowed; }

/* ══════════════════════════════════════
   PROMO BANNERS
══════════════════════════════════════ */
.promo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 8px 0 40px; }
.promo-card {
  border-radius: var(--r4);
  overflow: hidden;
  display: block;
  position: relative;
  cursor: pointer;
}
.promo-inner {
  padding: 32px 28px;
  min-height: 168px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  position: relative;
  overflow: hidden;
  border: 1px solid var(--edge);
  border-radius: var(--r4);
  transition: all .25s;
}
.promo-card:hover .promo-inner { border-color: var(--edge2); transform: translateY(-3px); box-shadow: 0 20px 50px rgba(0,0,0,.5); }
.promo-deco-ico { position: absolute; right: -8px; top: 50%; transform: translateY(-50%); font-size: 7rem; opacity: .05; color: #fff; pointer-events: none; transition: all .3s; }
.promo-card:hover .promo-deco-ico { opacity: .09; transform: translateY(-50%) scale(1.06); right: -4px; }
.promo-tag { font-size: .67rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--t3); margin-bottom: 7px; }
.promo-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.3rem; margin-bottom: 6px; letter-spacing: -.02em; }
.promo-desc { font-size: .78rem; color: var(--t2); margin-bottom: 14px; }
.promo-link { font-size: .75rem; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: gap .2s; }
.promo-card:hover .promo-link { gap: 9px; }
.p1 .promo-inner { background: linear-gradient(135deg, #120010 0%, #1e000a 100%); }
.p1 .promo-title { color: var(--accent); }
.p1 .promo-link { color: var(--accent); }
.p2 .promo-inner { background: linear-gradient(135deg, #000f1c 0%, #001628 100%); }
.p2 .promo-title { color: var(--blue); }
.p2 .promo-link { color: var(--blue); }
.p3 .promo-inner { background: linear-gradient(135deg, #0f0e00 0%, #1c1800 100%); }
.p3 .promo-title { color: var(--gold); }
.p3 .promo-link { color: var(--gold); }

/* ══════════════════════════════════════
   TWO-PANEL
══════════════════════════════════════ */
.two-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 8px 0 40px; }
.panel {
  background: var(--surface);
  border: 1px solid var(--edge);
  border-radius: var(--r4);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--edge);
}
.panel-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; letter-spacing: -.01em; }
.panel-body { padding: 10px 16px; }
.plist-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 13px 0;
  border-bottom: 1px solid var(--edge);
  transition: background .15s;
}
.plist-item:last-child { border: none; }
.plist-thumb {
  width: 62px;
  height: 62px;
  background: var(--bg2);
  border-radius: var(--r2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  color: rgba(255,255,255,.08);
  flex-shrink: 0;
}
.plist-info { flex: 1; min-width: 0; }
.plist-name { font-size: .83rem; font-weight: 500; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 5px; }
.plist-price { font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; }
.plist-was { font-size: .7rem; color: var(--t3); text-decoration: line-through; margin-left: 5px; }
.plist-disc-tag { font-size: .6rem; font-weight: 700; background: rgba(255,45,85,.1); color: var(--accent); padding: 2px 6px; border-radius: 100px; margin-left: 4px; }
.plist-add {
  background: var(--bg2);
  border: 1px solid var(--edge2);
  color: var(--t2);
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all .18s;
  font-size: .8rem;
  flex-shrink: 0;
}
.plist-add:hover { background: var(--accent); border-color: var(--accent); color: #fff; }

/* ══════════════════════════════════════
   PROMO STRIP
══════════════════════════════════════ */
.offer-strip {
  background: linear-gradient(135deg, rgba(255,45,85,.12) 0%, rgba(255,109,53,.08) 50%, rgba(255,45,85,.05) 100%);
  border: 1px solid rgba(255,45,85,.25);
  border-radius: var(--r4);
  padding: 48px 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 48px;
  margin: 8px 0 48px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 20px 64px rgba(255,45,85,.15);
}
.offer-strip::before {
  content: '';
  position: absolute;
  right: -80px;
  top: -80px;
  width: 300px;
  height: 300px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,45,85,.08), transparent 70%);
  pointer-events: none;
}
.offer-strip::after {
  content: '';
  position: absolute;
  left: -40px;
  bottom: -60px;
  width: 200px;
  height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,45,85,.05), transparent 70%);
  pointer-events: none;
}
.offer-eyebrow { font-size: .67rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
.offer-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.9rem; letter-spacing: -.03em; margin-bottom: 6px; }
.offer-sub { font-size: .84rem; color: var(--t2); }
.offer-code { display: inline-block; background: rgba(255,45,85,.1); border: 1px solid rgba(255,45,85,.2); color: var(--accent); font-weight: 700; padding: 2px 10px; border-radius: 6px; font-size: .84rem; }
.offer-btns { display: flex; gap: 10px; flex-shrink: 0; }

/* ══════════════════════════════════════
   BRANDS
══════════════════════════════════════ */
.brands-track-wrap { overflow: hidden; border-top: 1px solid var(--edge); border-bottom: 1px solid var(--edge); padding: 0; }
.brands-track { display: flex; animation: marquee 30s linear infinite; width: max-content; }
.brands-track:hover { animation-play-state: paused; }
.brand-item {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 14px 30px;
  border-right: 1px solid var(--edge);
  color: var(--t4);
  font-weight: 700;
  font-size: .82rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  white-space: nowrap;
  transition: color .2s;
}
.brand-item:hover { color: var(--t2); }
.brand-item i { color: var(--accent); font-size: .8rem; }
@keyframes marquee { from{transform:translateX(0)} to{transform:translateX(-50%)} }

/* ══════════════════════════════════════
   NEWSLETTER
══════════════════════════════════════ */
.newsletter {
  background: linear-gradient(135deg, var(--surface), rgba(22,22,34,.6));
  border: 1px solid rgba(255,45,85,.15);
  border-radius: var(--r4);
  padding: 56px 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 56px;
  margin: 8px 0 48px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(255,45,85,.1);
}
.newsletter::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse 100% 100% at 100% 0%, rgba(61,142,248,.05), rgba(255,45,85,.03), transparent);
  pointer-events: none;
  animation: gradDrift 6s ease-in-out infinite;
}
@keyframes gradDrift { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(8px, -8px); } }
.nl-text { flex: 1; }
.nl-label { font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
.nl-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.75rem; letter-spacing: -.03em; margin-bottom: 8px; }
.nl-sub { font-size: .86rem; color: var(--t2); }
.nl-form { display: flex; gap: 8px; max-width: 420px; flex-shrink: 0; }
.nl-input {
  flex: 1;
  background: var(--bg2);
  border: 1.5px solid var(--edge2);
  border-radius: 100px;
  padding: 12px 20px;
  color: var(--t1);
  font-family: 'DM Sans', sans-serif;
  font-size: .88rem;
  outline: none;
  transition: border-color .2s;
}
.nl-input:focus { border-color: var(--accent); }
.nl-input::placeholder { color: var(--t3); }

/* ══════════════════════════════════════
   FILTER TABS
══════════════════════════════════════ */
.filter-tabs { display: flex; gap: 6px; background: var(--bg2); border: 1px solid var(--edge); border-radius: 100px; padding: 4px; }
.ftab {
  padding: 6px 16px;
  border-radius: 100px;
  font-size: .77rem;
  font-weight: 600;
  color: var(--t3);
  cursor: pointer;
  border: none;
  background: transparent;
  font-family: 'DM Sans', sans-serif;
  transition: all .18s;
  white-space: nowrap;
}
.ftab.on { background: var(--accent); color: #fff; }
.ftab:not(.on):hover { color: var(--t1); }

/* ══════════════════════════════════════
   FOOTER
══════════════════════════════════════ */
footer {
  background: var(--bg2);
  border-top: 1px solid var(--edge);
  margin-top: 48px;
}
.footer-top {
  display: grid;
  grid-template-columns: 260px 1fr 1fr 1fr 1fr;
  gap: 40px;
  padding: 48px 32px;
  max-width: 1400px;
  margin: 0 auto;
}
.footer-brand .logo { margin-bottom: 14px; font-size: 1.5rem; }
.footer-desc { font-size: .8rem; color: var(--t3); line-height: 1.7; margin-bottom: 18px; }
.footer-contact { display: flex; flex-direction: column; gap: 8px; }
.fc-item { display: flex; align-items: flex-start; gap: 9px; font-size: .78rem; color: var(--t2); }
.fc-item i { color: var(--accent); width: 14px; flex-shrink: 0; margin-top: 2px; }
.footer-col-title { font-weight: 700; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 16px; color: var(--t1); }
.footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 9px; }
.footer-col a { font-size: .8rem; color: var(--t3); transition: all .22s; display: flex; align-items: center; gap: 6px; position: relative; }
.footer-col a::before { content: '›'; color: rgba(255,45,85,.5); font-size: 1rem; line-height: 1; transition: transform .22s; }
.footer-col a:hover { color: var(--accent); }
.footer-col a:hover::before { transform: translateX(4px); }
.footer-bottom {
  border-top: 1px solid var(--edge);
  padding: 18px 32px;
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.footer-copy { font-size: .75rem; color: var(--t3); }
.socials { display: flex; gap: 6px; }
.soc-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 1px solid var(--edge);
  background: var(--surface);
  color: var(--t3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
  transition: all .18s;
}
.soc-btn:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.pay-methods { display: flex; gap: 6px; }
.pay-chip { font-size: .63rem; font-weight: 600; padding: 4px 9px; border-radius: var(--r); background: var(--surface); color: var(--t3); border: 1px solid var(--edge); letter-spacing: .02em; }

/* ══════════════════════════════════════
   TOAST
══════════════════════════════════════ */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast-item {
  background: var(--card-solid);
  border: 1px solid var(--edge2);
  border-radius: var(--r3);
  padding: 12px 18px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .83rem;
  min-width: 240px;
  max-width: 320px;
  box-shadow: 0 16px 40px rgba(0,0,0,.8);
  transform: translateX(120%);
  transition: transform .32s cubic-bezier(.4,0,.2,1);
}
.toast-item.visible { transform: translateX(0); }
.toast-icon { font-size: 1.1rem; flex-shrink: 0; }

/* ══════════════════════════════════════
   WA FLOAT
══════════════════════════════════════ */
.wa-btn {
  position: fixed;
  bottom: 28px;
  left: 28px;
  z-index: 8000;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: #25d366;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  box-shadow: 0 4px 24px rgba(37,211,102,.4);
  transition: all .22s;
}
.wa-btn:hover { transform: scale(1.12); box-shadow: 0 8px 32px rgba(37,211,102,.55); }

/* ══════════════════════════════════════
   SCROLL REVEAL
══════════════════════════════════════ */
.reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s cubic-bezier(.25, 0.46, 0.45, 0.94), transform .6s cubic-bezier(.25, 0.46, 0.45, 0.94); }
.reveal.visible { opacity: 1; transform: translateY(0); }

/* ══════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════ */
@media (max-width: 1280px) {
  .prod-grid-5 { grid-template-columns: repeat(4, 1fr); }
  .prod-grid-6 { grid-template-columns: repeat(4, 1fr); }
  .cat-grid { grid-template-columns: repeat(4, 1fr); }
  .footer-top { grid-template-columns: 220px 1fr 1fr 1fr 1fr; gap: 28px; }
}
@media (max-width: 1024px) {
  .container { padding: 0 20px; }
  .hdr { padding: 0 20px; }
  .nav-bar { padding: 0 20px; }
  .flash-bar { padding: 12px 20px; }
  .prod-grid-4 { grid-template-columns: repeat(3, 1fr); }
  .two-panels { grid-template-columns: 1fr; }
  .hero { height: 420px; }
  .slide-content { padding: 0 40px; }
  .footer-top { grid-template-columns: 1fr 1fr 1fr; padding: 36px 20px; }
}
@media (max-width: 768px) {
  .hdr { flex-wrap: wrap; height: auto; padding: 12px 16px; gap: 10px; }
  .logo { order: 1; }
  .search-wrap { order: 3; flex-basis: 100%; max-width: 100%; }
  .hdr-right { order: 2; }
  .nav-bar { padding: 0 12px; flex-wrap: wrap; gap: 0; }
  .all-cats-btn { width: 100%; border-radius: 0; margin: 0; height: 40px; }
  .hero { height: 360px; }
  .slide-content { padding: 0 24px; max-width: 100%; }
  .slide-title { font-size: 2rem; }
  .slide-btns { flex-direction: column; }
  .btn { width: 100%; justify-content: center; }
  .hero-dots { left: 24px; }
  .hero-arrs { right: 16px; }
  .promo-grid { grid-template-columns: 1fr; }
  .prod-grid-4 { grid-template-columns: repeat(2, 1fr); }
  .prod-grid-5 { grid-template-columns: repeat(2, 1fr); }
  .prod-grid-6 { grid-template-columns: repeat(2, 1fr); }
  .cat-grid { grid-template-columns: repeat(4, 1fr); }
  .trust-strip { grid-template-columns: 1fr 1fr; }
  .newsletter { flex-direction: column; padding: 32px 24px; gap: 24px; }
  .nl-form { max-width: 100%; width: 100%; }
  .offer-strip { flex-direction: column; padding: 28px 24px; }
  .offer-btns { width: 100%; flex-direction: column; }
  .footer-top { grid-template-columns: 1fr 1fr; }
  .footer-bottom { flex-direction: column; text-align: center; }
}
@media (max-width: 520px) {
  .cat-grid { grid-template-columns: repeat(3, 1fr); }
  .trust-strip { grid-template-columns: 1fr; }
  .prod-grid-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
  .hero { height: 280px; }
  .slide-content { padding: 0 16px; }
  .slide-title { font-size: 1.65rem; }
  .slide-eyebrow { font-size: .65rem; padding: 5px 10px; }
  .flash-bar { flex-wrap: wrap; gap: 10px; }
  .footer-top { grid-template-columns: 1fr; }
  .section { padding: 32px 0; }
}
</style>
<script>
    // Set global variable for customer login status
    window.customerLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
</script>
</head>
<body>

<!-- ANNOUNCEMENT -->
<div class="announce">
  🚀 FREE DELIVERY on orders over Rs.&nbsp;2,000 &nbsp;·&nbsp; Open Mon–Sun 9am–9pm &nbsp;·&nbsp; Call <a href="tel:+94773987246">+94 77 398 7246</a> &nbsp;·&nbsp; Use <strong>SAP10</strong> for 10% off your first order
</div>

<!-- HEADER -->
<header class="hdr">
  <a href="index.php" class="logo">
    <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers">
  </a>

  <div class="search-wrap">
    <div class="search-form">
      <select class="search-cat" id="sCat">
        <option value="">All Products</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input class="search-inp" type="text" id="sInp" placeholder="Search laptops, printers, CCTV, RAM…">
      <button class="search-btn" onclick="doSearch()"><i class="fas fa-search"></i></button>
    </div>
  </div>

  <div class="hdr-right">
    <a href="<?= isset($_SESSION['customer_id']) ? 'account.php' : 'login.php' ?>" class="hbtn">
      <i class="fas fa-user-circle"></i><?= isset($_SESSION['customer_id']) ? 'Account' : 'Login' ?>
    </a>
    <a href="wishlist.php" class="hbtn">
      <i class="far fa-heart"></i>Wishlist
    </a>
    <a href="cart.php" class="hbtn hbtn-cart">
      <i class="fas fa-bag-shopping"></i>Cart
      <?php if (!empty($_SESSION['cart'])): ?>
        <span class="cart-badge"><?= count($_SESSION['cart']) ?></span>
      <?php endif; ?>
    </a>
  </div>
</header>

<!-- NAV -->
<nav class="nav-bar" style="position: relative;">
  <button class="all-cats-btn" id="catsBtn" onclick="toggleMega()">
    <i class="fas fa-grid-2"></i> All Categories <i class="fas fa-chevron-down" style="font-size:.6rem; margin-left: auto;"></i>
  </button>

  <!-- MEGA MENU -->
  <div class="mega-menu" id="megaMenu">
    <?php
    $catIcons = ['fa-laptop','fa-print','fa-video','fa-microchip','fa-mobile-screen','fa-network-wired','fa-memory','fa-gamepad'];
    foreach ($categories as $i => $cat):
      $ico = $catIcons[$i % count($catIcons)];
    ?>
    <a href="shop.php?category=<?= (int)$cat['category_id'] ?>">
      <span class="m-ico"><i class="fas <?= $ico ?>"></i></span>
      <?= htmlspecialchars($cat['category_name']) ?>
      <i class="fas fa-chevron-right m-arr"></i>
    </a>
    <?php endforeach; ?>
    <a href="shop.php">
      <span class="m-ico"><i class="fas fa-store"></i></span>
      Browse All Products
      <i class="fas fa-arrow-right m-arr" style="color:var(--accent)"></i>
    </a>
  </div>

  <ul class="nav-links">
    <li><a href="index.php" class="active">Home</a></li>
    <li><a href="shop.php">Shop</a></li>
    <li><a href="shop.php?cat=gaming">Gaming</a></li>
    <li><a href="shop.php?cat=laptops">Laptops</a></li>
    <li><a href="shop.php?cat=printers">Printers</a></li>
    <li><a href="shop.php?cat=cctv">CCTV</a></li>
    <li><a href="shop.php?cat=networking">Networking</a></li>
    <li><a href="shop.php?sale=1" class="deal">⚡ Flash Deals</a></li>
    <li><a href="blog.php">Blog</a></li>
  </ul>

  <div class="nav-meta">
    <span><i class="fas fa-circle-check"></i> Free Ship Rs.2000+</span>
    <span><i class="fas fa-phone"></i> +94 33 729 4388</span>
  </div>
</nav>

<!-- HERO SLIDER -->
<section class="hero">
  <div class="hero-slides" id="heroSlides">

    <!-- Slide 1: Gaming -->
    <div class="slide s1">
      <div class="slide-bg"></div>
      <div class="slide-noise"></div>
      <div class="slide-orb"></div>
      <div class="slide-word">GAMING</div>
      <div class="slide-content">
        <div class="slide-eyebrow"><i class="fas fa-fire"></i> Sizzling Deals</div>
        <h1 class="slide-title">Level Up Your<br><span class="slide-hl">Gaming Setup</span><br>for Less</h1>
        <p class="slide-sub">Premium keyboards, mice, headsets & accessories — up to 50% off limited stock.</p>
        <div class="slide-price-tag">Starting from <strong>Rs. 3,500</strong> &nbsp;<span class="instock"><i class="fas fa-circle" style="font-size:.4rem"></i> In Stock</span></div>
        <div class="slide-btns">
          <a href="shop.php?cat=gaming" class="btn btn-primary"><i class="fas fa-gamepad"></i> Shop Gaming</a>
          <a href="shop.php?sale=1" class="btn btn-secondary"><i class="fas fa-tag"></i> All Deals</a>
        </div>
      </div>
    </div>

    <!-- Slide 2: Laptops -->
    <div class="slide s2">
      <div class="slide-bg"></div>
      <div class="slide-noise"></div>
      <div class="slide-orb"></div>
      <div class="slide-word">LAPTOP</div>
      <div class="slide-content">
        <div class="slide-eyebrow"><i class="fas fa-star"></i> Best Sellers</div>
        <h1 class="slide-title">Power Your<br><span class="slide-hl">Productivity</span><br>Today</h1>
        <p class="slide-sub">Intel Core i3–i9 & AMD Ryzen laptops. 12-month warranty on every unit.</p>
        <div class="slide-price-tag">Premium laptops from <strong>Rs. 54,000</strong> &nbsp;<span class="instock"><i class="fas fa-circle" style="font-size:.4rem"></i> 12 Mo. Warranty</span></div>
        <div class="slide-btns">
          <a href="shop.php?cat=laptops" class="btn btn-primary"><i class="fas fa-laptop"></i> Shop Laptops</a>
          <a href="shop.php?cat=desktops" class="btn btn-secondary"><i class="fas fa-desktop"></i> Desktops</a>
        </div>
      </div>
    </div>

    <!-- Slide 3: CCTV -->
    <div class="slide s3">
      <div class="slide-bg"></div>
      <div class="slide-noise"></div>
      <div class="slide-orb"></div>
      <div class="slide-word">CCTV</div>
      <div class="slide-content">
        <div class="slide-eyebrow"><i class="fas fa-shield-halved"></i> Protect &amp; Save</div>
        <h1 class="slide-title">Secure Your<br><span class="slide-hl">Peace of Mind</span><br>Today</h1>
        <p class="slide-sub">Smart CCTV systems with professional installation. Dahua, Hikvision & more.</p>
        <div class="slide-price-tag">Systems from <strong>Rs. 13,500</strong> &nbsp;<span class="instock"><i class="fas fa-circle" style="font-size:.4rem"></i> Free Install</span></div>
        <div class="slide-btns">
          <a href="shop.php?cat=cctv" class="btn btn-primary"><i class="fas fa-video"></i> Shop CCTV</a>
          <a href="shop.php?cat=cctv" class="btn btn-secondary"><i class="fas fa-circle-info"></i> Setup Guides</a>
        </div>
      </div>
    </div>

  </div>

  <div class="hero-dots" id="heroDots">
    <button class="hero-dot on" onclick="slideTo(0)"></button>
    <button class="hero-dot" onclick="slideTo(1)"></button>
    <button class="hero-dot" onclick="slideTo(2)"></button>
  </div>
  <div class="hero-arrs">
    <button class="hero-arr" onclick="slidePrev()"><i class="fas fa-chevron-left"></i></button>
    <button class="hero-arr" onclick="slideNext()"><i class="fas fa-chevron-right"></i></button>
  </div>
</section>

<!-- TRUST STRIP -->
<div class="trust-strip">
  <div class="trust-item">
    <div class="trust-icon"><i class="fas fa-truck-fast"></i></div>
    <div>
      <div class="trust-title">Free Delivery</div>
      <div class="trust-sub">Orders over Rs. 2,000</div>
    </div>
  </div>
  <div class="trust-item">
    <div class="trust-icon"><i class="fas fa-shield-halved"></i></div>
    <div>
      <div class="trust-title">Secure Payment</div>
      <div class="trust-sub">100% encrypted checkout</div>
    </div>
  </div>
  <div class="trust-item">
    <div class="trust-icon"><i class="fas fa-rotate-left"></i></div>
    <div>
      <div class="trust-title">30-Day Returns</div>
      <div class="trust-sub">Hassle-free policy</div>
    </div>
  </div>
  <div class="trust-item">
    <div class="trust-icon"><i class="fas fa-headset"></i></div>
    <div>
      <div class="trust-title">Expert Support</div>
      <div class="trust-sub">Mon–Sun, 9am–9pm</div>
    </div>
  </div>
</div>

<!-- FLASH DEALS BAR -->
<div class="flash-bar">
  <div class="flash-label">
    <i class="fas fa-bolt flash-pulse" style="color:var(--gold)"></i>
    Flash Deals
  </div>
  <div class="countdown">
    <div class="cd-box"><span class="cd-num" id="cdH">08</span><span class="cd-label">hrs</span></div>
    <span class="cd-sep">:</span>
    <div class="cd-box"><span class="cd-num" id="cdM">45</span><span class="cd-label">min</span></div>
    <span class="cd-sep">:</span>
    <div class="cd-box"><span class="cd-num" id="cdS">30</span><span class="cd-label">sec</span></div>
  </div>
  <div class="flash-divider"></div>
  <div class="flash-items">
    <?php if (!empty($featured_products)):
      foreach (array_slice($featured_products, 0, 5) as $i => $fp):
        $discPcts = [13, 18, 22, 15, 20]; $dp = $discPcts[$i]; ?>
      <a href="product.php?id=<?= (int)$fp['product_id'] ?>" class="flash-chip">
        <div>
          <div class="flash-name"><?= htmlspecialchars(mb_substr($fp['product_name'], 0, 34)) ?>…</div>
          <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
            <span class="flash-price">Rs. <?= number_format($fp['selling_price'], 0) ?></span>
            <span class="flash-was">Rs. <?= number_format($fp['selling_price'] * (1 + $dp/100), 0) ?></span>
          </div>
        </div>
        <span class="flash-badge">-<?= $dp ?>%</span>
      </a>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ══ MAIN CONTENT ══ -->
<div class="container">

  <!-- PROMO BANNERS -->
  <div class="promo-grid reveal" style="margin-top:40px">
    <a href="shop.php?cat=gaming" class="promo-card p1">
      <div class="promo-inner">
        <i class="fas fa-gamepad promo-deco-ico"></i>
        <div class="promo-tag">Up to 50% off</div>
        <div class="promo-title">Gaming Collection</div>
        <div class="promo-desc">Keyboards, Mice, Headsets & More</div>
        <div class="promo-link">Shop Now <i class="fas fa-arrow-right"></i></div>
      </div>
    </a>
    <a href="shop.php?cat=laptops" class="promo-card p2">
      <div class="promo-inner">
        <i class="fas fa-laptop promo-deco-ico"></i>
        <div class="promo-tag">New Stock Available</div>
        <div class="promo-title">Laptops & PCs</div>
        <div class="promo-desc">Intel Core i3–i9 & AMD Ryzen</div>
        <div class="promo-link">Shop Now <i class="fas fa-arrow-right"></i></div>
      </div>
    </a>
    <a href="shop.php?cat=printers" class="promo-card p3">
      <div class="promo-inner">
        <i class="fas fa-print promo-deco-ico"></i>
        <div class="promo-tag">Best Prices Guaranteed</div>
        <div class="promo-title">Printers & Ink</div>
        <div class="promo-desc">HP, Canon, Epson — All Models</div>
        <div class="promo-link">Shop Now <i class="fas fa-arrow-right"></i></div>
      </div>
    </a>
  </div>

  <!-- SHOP BY CATEGORY -->
  <section class="section reveal">
    <div class="section-head">
      <div>
        <div class="section-title">Shop by Category</div>
        <div class="section-sub">Find exactly what you need</div>
      </div>
      <a href="shop.php" class="see-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if (!empty($categories)): ?>
    <div class="cat-grid">
      <?php
      $catDefs = [['fa-laptop','Laptops'],['fa-print','Printers'],['fa-video','CCTV'],['fa-microchip','PC Parts'],['fa-mobile-screen','Accessories'],['fa-network-wired','Networking'],['fa-headphones','Audio'],['fa-gamepad','Gaming']];
      foreach ($categories as $i => $cat):
        $d = $catDefs[$i % count($catDefs)]; ?>
      <a href="shop.php?category=<?= (int)$cat['category_id'] ?>" class="cat-tile">
        <div class="cat-icon"><i class="fas <?= $d[0] ?>"></i></div>
        <div class="cat-name"><?= htmlspecialchars($cat['category_name']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:48px;color:var(--t3)">
      <i class="fas fa-inbox" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.4"></i>
      <p>Categories loading…</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- TRENDING THIS WEEK -->
  <section class="section reveal">
    <div class="section-head">
      <div>
        <div class="section-title">Trending This Week <span class="pill pill-hot">HOT</span></div>
        <div class="section-sub">Most popular picks right now</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <div class="filter-tabs">
          <button class="ftab on">All</button>
          <button class="ftab">Laptops</button>
          <button class="ftab">Gaming</button>
          <button class="ftab">Accessories</button>
        </div>
        <a href="shop.php" class="see-all">View All <i class="fas fa-arrow-right"></i></a>
      </div>
    </div>
    <?php if (!empty($featured_products)): ?>
    <div class="prod-grid-4">
      <?php
      $pIcons = ['fa-laptop','fa-microchip','fa-memory','fa-hard-drive','fa-print','fa-desktop','fa-video','fa-keyboard'];
      foreach ($featured_products as $i => $p):
        $was = round($p['selling_price'] * 1.13);
        $hasDisc = ($i % 3 === 0);
        $discPct = 10 + $i * 3;
        $ico = $pIcons[$i % count($pIcons)];
        $stars = $i % 3 == 2 ? '★★★★☆' : '★★★★★';
      ?>
      <div class="pcard" style="transition-delay:<?= $i * .04 ?>s">
        <div class="pcard-img">
          <div class="pcard-badges">
            <?php if ($i < 3): ?><span class="badge badge-new">New</span><?php endif; ?>
            <?php if ($hasDisc): ?><span class="badge badge-sale">-<?= $discPct ?>%</span><?php endif; ?>
            <?php if ($p['qty'] <= 0): ?><span class="badge badge-out">Sold Out</span><?php endif; ?>
          </div>
          <i class="fas <?= $ico ?> pcard-img-ico"></i>
          <button class="pcard-wish"><i class="far fa-heart"></i></button>
          <div class="pcard-quick">
            <a href="product.php?id=<?= (int)$p['product_id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center">
              <i class="fas fa-eye"></i> Quick View
            </a>
          </div>
        </div>
        <div class="pcard-body">
          <div class="pcard-brand"><?= htmlspecialchars($p['brand'] ?? 'Generic') ?></div>
          <div class="pcard-name"><?= htmlspecialchars($p['product_name']) ?></div>
          <div class="pcard-stars"><?= $stars ?> <span>(<?= rand(14, 164) ?>)</span></div>
          <div class="pcard-price-row">
            <span class="pcard-price">Rs. <?= number_format($p['selling_price'], 0) ?></span>
            <?php if ($hasDisc): ?>
              <span class="pcard-was">Rs. <?= number_format($was, 0) ?></span>
              <span class="pcard-disc">Save <?= $discPct ?>%</span>
            <?php endif; ?>
          </div>
          <div class="pcard-stock">
            <?php if ($p['qty'] > 5): ?>
              <span class="stock-in"><i class="fas fa-circle" style="font-size:.35rem;margin-right:4px"></i>In Stock</span>
            <?php elseif ($p['qty'] > 0): ?>
              <span class="stock-low"><i class="fas fa-circle" style="font-size:.35rem;margin-right:4px"></i>Only <?= $p['qty'] ?> left</span>
            <?php else: ?>
              <span class="stock-out"><i class="fas fa-circle" style="font-size:.35rem;margin-right:4px"></i>Out of Stock</span>
            <?php endif; ?>
          </div>
          <?php if ($p['qty'] > 0): ?>
            <button class="pcard-cta add-to-cart" data-id="<?= (int)$p['product_id'] ?>"><i class="fas fa-cart-plus"></i> Add to Cart</button>
          <?php else: ?>
            <button class="pcard-cta" disabled><i class="fas fa-bell"></i> Notify Me</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:64px;color:var(--t3)">
      <i class="fas fa-box-open" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.4"></i>
      <p style="margin-bottom:18px">No products available right now</p>
      <a href="shop.php" class="btn btn-primary">Browse All</a>
    </div>
    <?php endif; ?>
  </section>

  <!-- OFFER STRIP -->
  <div class="offer-strip reveal">
    <div>
      <div class="offer-eyebrow">🎁 Exclusive Member Offer</div>
      <div class="offer-title">Get <span style="color:var(--gold)">10% OFF</span> Your First Order</div>
      <div style="font-size:.86rem;color:var(--t2)">Register free & use code <span class="offer-code">SAP10</span> at checkout.</div>
    </div>
    <div class="offer-btns">
      <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Account</a>
      <a href="shop.php?sale=1" class="btn btn-secondary"><i class="fas fa-tag"></i> Current Deals</a>
    </div>
  </div>

  <!-- TWO PANELS -->
  <div class="two-panels reveal">
    <!-- Featured -->
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title">Featured Products</div>
        <a href="shop.php" class="see-all" style="font-size:.76rem">All <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="panel-body">
        <?php if (!empty($featured_products)):
          $pIco2 = ['fa-laptop','fa-hard-drive','fa-memory','fa-microchip'];
          foreach (array_slice($featured_products, 0, 4) as $i => $p): ?>
        <div class="plist-item">
          <div class="plist-thumb"><i class="fas <?= $pIco2[$i % 4] ?>"></i></div>
          <div class="plist-info">
            <div class="plist-name"><?= htmlspecialchars($p['product_name']) ?></div>
            <div style="color:var(--gold);font-size:.66rem;margin-bottom:4px">★★★★★ <span style="color:var(--t4)">(<?= rand(8,90) ?>)</span></div>
            <div><span class="plist-price">Rs. <?= number_format($p['selling_price'], 0) ?></span></div>
          </div>
          <?php if ($p['qty'] > 0): ?>
            <button class="plist-add add-to-cart" data-id="<?= (int)$p['product_id'] ?>"><i class="fas fa-cart-plus"></i></button>
          <?php else: ?>
            <span class="badge badge-out">Out</span>
          <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <div style="text-align:center;padding:32px;color:var(--t3)">No products</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Special Offers -->
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title" style="color:var(--accent2)">⚡ Special Offers</div>
        <a href="shop.php?sale=1" class="see-all" style="font-size:.76rem;color:var(--accent2)">All <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="panel-body">
        <?php if (!empty($trending_products)):
          $pIco3 = ['fa-video','fa-print','fa-desktop','fa-keyboard'];
          $dPcts = [18, 22, 15, 25];
          foreach (array_slice($trending_products, 0, 4) as $i => $p):
            $was = $p['selling_price'] * (1 + $dPcts[$i % 4] / 100); ?>
        <div class="plist-item">
          <div class="plist-thumb"><i class="fas <?= $pIco3[$i % 4] ?>"></i></div>
          <div class="plist-info">
            <div class="plist-name"><?= htmlspecialchars($p['product_name']) ?></div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:4px;flex-wrap:wrap">
              <span class="plist-price" style="color:var(--accent)">Rs. <?= number_format($p['selling_price'], 0) ?></span>
              <span class="plist-was">Rs. <?= number_format($was, 0) ?></span>
              <span class="plist-disc-tag">-<?= $dPcts[$i % 4] ?>%</span>
            </div>
          </div>
          <?php if ($p['qty'] > 0): ?>
            <button class="plist-add add-to-cart" data-id="<?= (int)$p['product_id'] ?>" style="background:rgba(255,45,85,.1);border-color:rgba(255,45,85,.25);color:var(--accent)"><i class="fas fa-cart-plus"></i></button>
          <?php else: ?>
            <span class="badge badge-out">Out</span>
          <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <div style="text-align:center;padding:32px;color:var(--t3)">No offers</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- LATEST ARRIVALS -->
  <section class="section reveal">
    <div class="section-head">
      <div>
        <div class="section-title">Latest Arrivals <span class="pill pill-new">NEW</span></div>
        <div class="section-sub">Just added to our store</div>
      </div>
      <a href="shop.php?sort=new" class="see-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if (count($featured_products) > 3): ?>
    <div class="prod-grid-5">
      <?php
      $lIcons = ['fa-video','fa-print','fa-desktop','fa-computer-mouse','fa-headphones'];
      foreach (array_slice($featured_products, 3, 5) as $i => $p):
        $ico = $lIcons[$i % count($lIcons)]; ?>
      <div class="pcard">
        <div class="pcard-img" style="height:158px">
          <div class="pcard-badges"><span class="badge badge-new">New</span></div>
          <i class="fas <?= $ico ?> pcard-img-ico"></i>
          <button class="pcard-wish"><i class="far fa-heart"></i></button>
          <div class="pcard-quick">
            <a href="product.php?id=<?= (int)$p['product_id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;font-size:.72rem"><i class="fas fa-eye"></i> View</a>
          </div>
        </div>
        <div class="pcard-body">
          <div class="pcard-brand"><?= htmlspecialchars($p['brand'] ?? 'Generic') ?></div>
          <div class="pcard-name"><?= htmlspecialchars($p['product_name']) ?></div>
          <div class="pcard-price-row"><span class="pcard-price">Rs. <?= number_format($p['selling_price'], 0) ?></span></div>
          <?php if ($p['qty'] > 0): ?>
            <button class="pcard-cta add-to-cart" data-id="<?= (int)$p['product_id'] ?>"><i class="fas fa-cart-plus"></i> Add to Cart</button>
          <?php else: ?>
            <button class="pcard-cta" disabled>Out of Stock</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:64px;color:var(--t3)">
      <i class="fas fa-cubes" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.4"></i>
      <p>More products coming soon</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- BRANDS STRIP -->
  <div class="brands-track-wrap reveal">
    <div class="brands-track">
      <?php
      $brands = [['fa-microchip','ASUS'],['fa-microchip','MSI'],['fa-desktop','Dell'],['fa-laptop','HP'],['fa-laptop','Lenovo'],['fa-microchip','Intel'],['fa-microchip','AMD'],['fa-print','Canon'],['fa-print','Epson'],['fa-video','Dahua'],['fa-gamepad','Logitech'],['fa-memory','Kingston'],['fa-laptop','Acer'],['fa-microchip','Gigabyte']];
      for ($r = 0; $r < 2; $r++): foreach ($brands as $b): ?>
        <div class="brand-item"><i class="fas <?= $b[0] ?>"></i><?= $b[1] ?></div>
      <?php endforeach; endfor; ?>
    </div>
  </div>

  <!-- NEWSLETTER -->
  <div class="newsletter reveal" style="margin-top:40px">
    <div class="nl-text">
      <div class="nl-label">Newsletter</div>
      <div class="nl-title">Exclusive Deals &<br>Tech Updates</div>
      <div class="nl-sub">Join 10,000+ subscribers and get a <strong>10% OFF</strong> coupon instantly on signup.</div>
    </div>
    <div class="nl-form">
      <input type="email" class="nl-input" id="nlEmail" placeholder="Enter your email address…">
      <button class="btn btn-primary" onclick="doSubscribe()" style="flex-shrink:0"><i class="fas fa-paper-plane"></i> Subscribe</button>
    </div>
  </div>

</div><!-- /container -->

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div>
      <div class="footer-brand">
        <div class="logo">SAP<span class="logo-dot">.</span></div>
      </div>
      <p class="footer-desc">Your one-stop tech shop in Gampaha. Laptops, PCs, Printers, CCTV & more — best prices in Sri Lanka, guaranteed.</p>
      <div class="footer-contact">
        <div class="fc-item"><i class="fas fa-location-dot"></i><span>No. 14, Sanasa Ideal Complex, Bauddhaloka Mawatha, Gampaha</span></div>
        <div class="fc-item"><i class="fas fa-phone"></i><span>+94 77 398 7246 / +94 33 729 4388</span></div>
        <div class="fc-item"><i class="fas fa-clock"></i><span>Mon–Sun: 9:00am – 9:00pm</span></div>
      </div>
    </div>
    <div class="footer-col">
      <div class="footer-col-title">Customer</div>
      <ul>
        <li><a href="account.php">My Account</a></li>
        <li><a href="orders.php">Track My Order</a></li>
        <li><a href="wishlist.php">Wish List</a></li>
        <li><a href="cart.php">Shopping Cart</a></li>
        <li><a href="contact.php">Contact Us</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <div class="footer-col-title">About</div>
      <ul>
        <li><a href="about.php">About SAP Computers</a></li>
        <li><a href="blog.php">Tech Blog</a></li>
        <li><a href="reviews.php">Customer Reviews</a></li>
        <li><a href="faqs.php">FAQs</a></li>
        <li><a href="store-location.php">Store Location</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <div class="footer-col-title">Policies</div>
      <ul>
        <li><a href="privacy.php">Privacy Policy</a></li>
        <li><a href="returns.php">Return Policy</a></li>
        <li><a href="terms.php">Terms of Service</a></li>
        <li><a href="shop.php?sale=1">Deals & Offers</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <div class="footer-col-title">Top Categories</div>
      <ul>
        <li><a href="shop.php?cat=laptops">Laptops & PCs</a></li>
        <li><a href="shop.php?cat=printers">Printers & Ink</a></li>
        <li><a href="shop.php?cat=cctv">CCTV Systems</a></li>
        <li><a href="shop.php?cat=gaming">Gaming Gear</a></li>
        <li><a href="shop.php?cat=networking">Networking</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© <?= date('Y') ?> SAP Computers, Gampaha. All rights reserved.</div>
    <div class="socials">
      <a href="https://web.facebook.com/sapgampaha" class="soc-btn" target="_blank"><i class="fab fa-facebook-f"></i></a>
      <a href="https://www.instagram.com/sapcomputers/" class="soc-btn" target="_blank"><i class="fab fa-instagram"></i></a>
      <a href="https://web.whatsapp.com/send?phone=94773987246" class="soc-btn" target="_blank"><i class="fab fa-whatsapp"></i></a>
      <a href="#" class="soc-btn"><i class="fab fa-youtube"></i></a>
    </div>
    <div class="pay-methods">
      <span class="pay-chip">VISA</span>
      <span class="pay-chip">Mastercard</span>
      <span class="pay-chip">Bank Transfer</span>
      <span class="pay-chip">Cash on Delivery</span>
    </div>
  </div>
</footer>

<!-- WA FLOAT -->
<a href="https://web.whatsapp.com/send?phone=94773987246" class="wa-btn" target="_blank" title="Chat on WhatsApp">
  <i class="fab fa-whatsapp"></i>
</a>

<!-- TOASTS -->
<div class="toast-wrap" id="toasts"></div>

<script>
/* ── HERO SLIDER ── */
let cur = 0, autoTimer;
const slides = document.getElementById('heroSlides');
const dots = document.querySelectorAll('.hero-dot');
function slideTo(n) {
  cur = n;
  slides.style.transform = `translateX(-${cur * 100}%)`;
  dots.forEach((d, i) => d.classList.toggle('on', i === cur));
}
function slideNext() { slideTo((cur + 1) % 3); resetAuto(); }
function slidePrev() { slideTo((cur + 2) % 3); resetAuto(); }
function resetAuto() { clearInterval(autoTimer); autoTimer = setInterval(slideNext, 5500); }
resetAuto();

/* ── MEGA MENU ── */
function toggleMega() { document.getElementById('megaMenu').classList.toggle('open'); }
document.addEventListener('click', e => {
  if (!e.target.closest('#catsBtn') && !e.target.closest('#megaMenu'))
    document.getElementById('megaMenu').classList.remove('open');
});

/* ── COUNTDOWN ── */
(function() {
  const end = new Date(Date.now() + (8 * 3600 + 45 * 60 + 30) * 1000);
  setInterval(() => {
    const d = Math.max(0, end - Date.now());
    document.getElementById('cdH').textContent = String(Math.floor(d / 3600000)).padStart(2, '0');
    document.getElementById('cdM').textContent = String(Math.floor(d % 3600000 / 60000)).padStart(2, '0');
    document.getElementById('cdS').textContent = String(Math.floor(d % 60000 / 1000)).padStart(2, '0');
  }, 1000);
})();

/* ── SEARCH ── */
function doSearch() {
  const q = document.getElementById('sInp').value.trim();
  const cat = document.getElementById('sCat').value;
  if (q) window.location.href = 'shop.php?search=' + encodeURIComponent(q) + (cat ? '&category=' + cat : '');
}
document.getElementById('sInp').addEventListener('keypress', e => { if (e.key === 'Enter') doSearch(); });

/* ── FILTER TABS ── */
document.querySelectorAll('.ftab').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.ftab').forEach(t => t.classList.remove('on'));
    this.classList.add('on');
  });
});

/* ── TOAST ── */
function showToast(msg, color = 'var(--green)') {
  const wrap = document.getElementById('toasts');
  const t = document.createElement('div');
  t.className = 'toast-item';
  t.innerHTML = `<i class="fas fa-check-circle toast-icon" style="color:${color}"></i><span>${msg}</span>`;
  wrap.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('visible')));
  setTimeout(() => { t.classList.remove('visible'); setTimeout(() => t.remove(), 380); }, 3000);
}

/* ── ADD TO CART ── */
document.querySelectorAll('.add-to-cart').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.id;
    const orig = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    this.disabled = true;
    try {
      const r = await fetch('cart_add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_to_cart&product_id=${id}&quantity=1`
      });
      const d = await r.json();
      if (d.success) {
        showToast('Added to cart!');
        this.innerHTML = '<i class="fas fa-check"></i> Added!';
        setTimeout(() => { this.innerHTML = orig; this.disabled = false; }, 2000);
      } else {
        showToast(d.message || 'Could not add', 'var(--accent)');
        this.innerHTML = orig;
        this.disabled = false;
      }
    } catch {
      showToast('Sign in to add items', 'var(--accent)');
      this.innerHTML = orig;
      this.disabled = false;
    }
  });
});

/* ── WISHLIST ── */
document.querySelectorAll('.pcard-wish').forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation();
    btn.classList.toggle('wishlisted');
    const on = btn.classList.contains('wishlisted');
    btn.querySelector('i').className = on ? 'fas fa-heart' : 'far fa-heart';
    btn.style.color = on ? 'var(--accent)' : '';
    btn.style.borderColor = on ? 'var(--accent)' : '';
    showToast(on ? 'Added to wishlist' : 'Removed from wishlist', on ? 'var(--accent)' : 'var(--green)');
  });
});

/* ── NEWSLETTER ── */
function doSubscribe() {
  const e = document.getElementById('nlEmail').value.trim();
  if (!e || !e.includes('@')) { showToast('Enter a valid email', 'var(--accent)'); return; }
  showToast('🎉 Subscribed! Your 10% coupon is on its way.');
  document.getElementById('nlEmail').value = '';
}

/* ── SCROLL REVEAL ── */
const io = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
  });
}, { threshold: .06 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>
</body>
</html>