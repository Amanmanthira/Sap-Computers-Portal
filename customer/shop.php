<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$page     = isset($_GET['page'])     ? max(1,(int)$_GET['page'])          : 1;
$category = isset($_GET['category']) ? (int)$_GET['category']             : null;
$search   = isset($_GET['search'])   ? trim($_GET['search'])              : '';
$sort     = isset($_GET['sort'])     ? $_GET['sort']                      : 'newest';
$min_price= isset($_GET['min_price'])? (int)$_GET['min_price']           : 0;
$max_price= isset($_GET['max_price'])? (int)$_GET['max_price']           : 500000;
$in_stock = isset($_GET['in_stock']) ? (bool)$_GET['in_stock']           : false;
$per_page = 12;
$offset   = ($page-1)*$per_page;
$is_logged_in = isset($_SESSION['customer_id']);

$products    = [];
$categories  = [];
$total       = 0;
$total_pages = 1;

try {
    $pdo = Database::getInstance();

    // Build WHERE
    $where  = ['1=1'];
    $params = [];
    if ($category) { $where[] = 'p.category_id = ?'; $params[] = $category; }
    if ($search)   { $where[] = '(p.product_name LIKE ? OR p.brand LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($min_price) { $where[] = 'p.selling_price >= ?'; $params[] = $min_price; }
    if ($max_price < 500000) { $where[] = 'p.selling_price <= ?'; $params[] = $max_price; }
    $wStr = implode(' AND ', $where);

    // ORDER BY
    $orderMap = ['newest'=>'p.product_id DESC','price-low'=>'p.selling_price ASC','price-high'=>'p.selling_price DESC','name'=>'p.product_name ASC'];
    $order = $orderMap[$sort] ?? 'p.product_id DESC';

    // Total count
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $wStr");
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page-1)*$per_page;

    // Products
    $sql = "SELECT p.product_id, p.product_name, p.brand, p.model, p.selling_price, p.category_id,
                   c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE $wStr ORDER BY $order LIMIT $per_page OFFSET $offset";
    $pStmt = $pdo->prepare($sql);
    $pStmt->execute($params);
    $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    // Stock
    foreach ($products as &$prod) {
        $s = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock WHERE product_id=?");
        $s->execute([$prod['product_id']]);
        $prod['qty'] = (int)$s->fetchColumn();
    }

    // Max price for slider
    $maxDb = (int)$pdo->query("SELECT COALESCE(MAX(selling_price),500000) FROM products")->fetchColumn();

    // Categories
    $categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

    // Active category name
    $activeCatName = '';
    if ($category) {
        foreach ($categories as $c) {
            if ($c['category_id'] == $category) { $activeCatName = $c['category_name']; break; }
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $maxDb = 500000;
}

// Build query string helper
function qStr(array $extra=[], array $remove=[]) {
    $p = $_GET;
    foreach ($remove as $k) unset($p[$k]);
    foreach ($extra as $k=>$v) $p[$k]=$v;
    return http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $activeCatName ? htmlspecialchars($activeCatName).' — ' : '' ?>Shop<?= $search ? ' — '.htmlspecialchars($search) : '' ?> | SAP Computers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800;900&family=Barlow+Condensed:wght@500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --void:#060608;--base:#0c0c12;--surface:#111118;--card:#16161f;--lift:#1e1e2a;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.13);
  --red:#e8192c;--red2:#ff3347;--red-glow:rgba(232,25,44,.2);
  --orange:#ff6a00;--yellow:#ffd600;--green:#00c96b;--cyan:#00d4ff;
  --t1:#f0f0f8;--t2:rgba(240,240,248,.62);--t3:rgba(240,240,248,.32);--t4:rgba(240,240,248,.1);
  --r:6px;--r2:10px;--r3:14px;
}
html{scroll-behavior:smooth}
body{font-family:'Barlow',sans-serif;background:var(--base);color:var(--t1);overflow-x:hidden;font-size:14px;line-height:1.5}
a{color:inherit;text-decoration:none}

/* ─ PROMO BAR */
.promo{background:var(--red);color:#fff;text-align:center;padding:7px 16px;font-size:.78rem;font-weight:600;letter-spacing:.03em}
.promo a{color:#fff;text-decoration:underline;margin-left:6px}
.promo-sep{opacity:.5;margin:0 14px}

/* ─ HEADER */
.hdr{background:var(--void);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:200;display:flex;align-items:center;gap:14px;padding:10px 20px;height:66px}
.logo{font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:1.9rem;letter-spacing:-.02em;flex-shrink:0;white-space:nowrap}
.logo img,.logo-img{height:40px;max-width:180px;width:auto;display:block}
.logo-red{color:var(--red)}
.search-bar{flex:1;display:flex;border:1.5px solid var(--border2);border-radius:var(--r);overflow:hidden;transition:border-color .2s;max-width:580px}
.search-bar:focus-within{border-color:var(--red)}
.search-cat{background:var(--lift);border:none;border-right:1px solid var(--border);color:var(--t2);font-family:'Barlow',sans-serif;font-size:.78rem;padding:0 10px;height:42px;cursor:pointer;min-width:100px;outline:none;font-weight:600}
.search-inp{flex:1;background:var(--surface);border:none;color:var(--t1);font-family:'Barlow',sans-serif;font-size:.9rem;padding:0 14px;height:42px;outline:none}
.search-inp::placeholder{color:var(--t3)}
.search-go{background:var(--red);border:none;color:#fff;padding:0 18px;height:42px;cursor:pointer;font-size:.95rem;flex-shrink:0;transition:background .2s}
.search-go:hover{background:var(--red2)}
.hdr-acts{display:flex;gap:4px;margin-left:auto;align-items:center;flex-shrink:0}
.hact{display:flex;flex-direction:column;align-items:center;gap:1px;padding:6px 12px;border-radius:var(--r);color:var(--t2);cursor:pointer;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;transition:all .18s;position:relative;background:transparent;border:none;font-family:'Barlow',sans-serif}
.hact i{font-size:1.05rem}
.hact:hover{background:var(--surface);color:var(--t1)}
.hact-cart{background:var(--red)!important;color:#fff!important;flex-direction:row!important;gap:8px!important;font-size:.83rem!important;padding:8px 16px!important}
.hact-cart:hover{background:var(--red2)!important}
.hbadge{position:absolute;top:-2px;right:-2px;background:var(--yellow);color:#000;border-radius:50%;width:16px;height:16px;font-size:.58rem;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid var(--void)}

/* ─ NAV */
.nav{background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:stretch;position:sticky;top:66px;z-index:190;padding:0 20px}
.cats-btn{background:var(--red);color:#fff;border:none;font-family:'Barlow',sans-serif;font-weight:700;font-size:.82rem;letter-spacing:.04em;padding:0 18px;display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:uppercase;flex-shrink:0}
.nav-links{display:flex;list-style:none;overflow-x:auto;scrollbar-width:none}
.nav-links::-webkit-scrollbar{display:none}
.nav-links a{display:flex;align-items:center;padding:0 16px;height:44px;color:var(--t2);font-size:.82rem;font-weight:600;letter-spacing:.02em;white-space:nowrap;border-bottom:2.5px solid transparent;transition:all .18s}
.nav-links a:hover,.nav-links a.active{color:var(--t1);border-color:var(--red)}
.nav-deal{color:var(--orange)!important}
.nav-r{margin-left:auto;display:flex;align-items:center;gap:10px;flex-shrink:0;padding-left:12px}
.ntag{font-size:.7rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:3px 9px;border-radius:3px}
.ntag-g{background:rgba(0,201,107,.12);color:var(--green);border:1px solid rgba(0,201,107,.3)}
.ntag-p{color:var(--t3);font-size:.76rem;font-weight:600}
.ntag-p i{color:var(--green);margin-right:3px}

/* ─ BREADCRUMB */
.bc{background:var(--surface);border-bottom:1px solid var(--border);padding:10px 20px;font-size:.8rem;color:var(--t3);display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.bc a{color:var(--t2);transition:color .18s}
.bc a:hover{color:var(--red)}
.bc-sep{color:var(--t4)}
.bc-current{color:var(--t1);font-weight:600}

/* ─ SHOP HEADER BAR */
.shop-hdr{background:var(--card);border-bottom:1px solid var(--border);padding:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.shop-hdr-l h1{font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:1.6rem;text-transform:uppercase;letter-spacing:-.01em;display:flex;align-items:center;gap:10px}
.shop-hdr-l h1::before{content:'';width:4px;height:24px;background:var(--red);border-radius:2px;flex-shrink:0}
.shop-hdr-l p{font-size:.82rem;color:var(--t2);margin-top:4px}
.active-filters{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.af-tag{display:inline-flex;align-items:center;gap:6px;background:rgba(232,25,44,.1);border:1px solid rgba(232,25,44,.25);border-radius:3px;padding:3px 10px;font-size:.72rem;font-weight:700;color:var(--red)}
.af-tag a{color:var(--t3);font-size:.65rem;transition:color .18s;margin-left:2px}
.af-tag a:hover{color:var(--red)}
.view-btns{display:flex;gap:4px}
.vbtn{width:34px;height:34px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface);color:var(--t3);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.85rem;transition:all .18s}
.vbtn.on,.vbtn:hover{background:var(--red);border-color:var(--red);color:#fff}

/* ─ LAYOUT */
.wrap{max-width:1380px;margin:0 auto;padding:0 20px}
.shop-wrap{display:grid;grid-template-columns:260px 1fr;gap:20px;padding:20px 0 40px;align-items:start}

/* ─ SIDEBAR */
.sidebar{display:flex;flex-direction:column;gap:14px;position:sticky;top:116px}
.sb-panel{background:var(--card);border:1px solid var(--border);border-radius:var(--r2);overflow:hidden}
.sb-head{padding:11px 14px;background:rgba(0,0,0,.25);border-bottom:1px solid var(--border);font-weight:800;font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;display:flex;align-items:center;justify-content:space-between;gap:8px}
.sb-head i{color:var(--red);font-size:.75rem}
.sb-head a{font-size:.68rem;font-weight:600;color:var(--t3);letter-spacing:.04em;transition:color .18s}
.sb-head a:hover{color:var(--red)}
.sb-body{padding:12px}

/* SEARCH */
.sb-search{display:flex;gap:6px}
.sb-inp{flex:1;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--r);padding:8px 12px;color:var(--t1);font-family:'Barlow',sans-serif;font-size:.82rem;outline:none;transition:border-color .2s}
.sb-inp:focus{border-color:var(--red)}
.sb-inp::placeholder{color:var(--t3)}
.sb-go{background:var(--red);border:none;border-radius:var(--r);color:#fff;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;font-size:.85rem;transition:background .18s}
.sb-go:hover{background:var(--red2)}

/* CAT TREE */
.cat-list{display:flex;flex-direction:column;gap:1px;max-height:320px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:var(--border) transparent}
.cat-list::-webkit-scrollbar{width:3px}
.cat-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px}
.cat-item{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:var(--r);cursor:pointer;font-size:.82rem;color:var(--t2);transition:all .18s}
.cat-item:hover,.cat-item.on{background:rgba(232,25,44,.08);color:var(--t1)}
.cat-item.on{border-left:2px solid var(--red);padding-left:8px;font-weight:700}
.cat-item .cat-n{display:flex;align-items:center;gap:8px}
.cat-item .cat-n i{font-size:.78rem;color:var(--red);width:14px}
.cat-badge{background:var(--surface);border:1px solid var(--border);border-radius:3px;padding:1px 6px;font-size:.62rem;font-weight:700;color:var(--t3);flex-shrink:0}
.cat-item.on .cat-badge{background:rgba(232,25,44,.12);border-color:rgba(232,25,44,.25);color:var(--red)}

/* PRICE RANGE */
.price-row{display:flex;gap:8px;margin-bottom:12px}
.price-inp{flex:1;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--r);padding:7px 10px;color:var(--t1);font-family:'Barlow Condensed',sans-serif;font-size:.95rem;font-weight:700;outline:none;width:0;transition:border-color .2s;text-align:center}
.price-inp:focus{border-color:var(--red)}
.price-sep{color:var(--t3);font-size:.8rem;align-self:center;flex-shrink:0}
.range-wrap{position:relative;height:20px;margin-bottom:8px}
.range-track{position:absolute;top:8px;left:0;right:0;height:4px;background:var(--surface);border-radius:2px}
.range-fill{position:absolute;top:8px;height:4px;background:var(--red);border-radius:2px}
input[type=range].price-range{-webkit-appearance:none;appearance:none;width:100%;background:transparent;position:absolute;top:0;pointer-events:none;height:20px}
input[type=range].price-range::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:var(--red);border:2px solid var(--void);cursor:pointer;pointer-events:all;box-shadow:0 0 8px var(--red-glow)}
input[type=range].price-range::-moz-range-thumb{width:16px;height:16px;border-radius:50%;background:var(--red);border:2px solid var(--void);cursor:pointer;pointer-events:all}
.price-apply{width:100%;padding:8px;background:var(--red);border:none;border-radius:var(--r);color:#fff;font-family:'Barlow',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;transition:background .18s;letter-spacing:.03em}
.price-apply:hover{background:var(--red2)}

/* STOCK / SORT in sidebar */
.sb-option{display:flex;align-items:center;gap:8px;padding:7px 0;cursor:pointer;font-size:.82rem;color:var(--t2);border-bottom:1px solid var(--border);transition:color .18s}
.sb-option:last-child{border:none}
.sb-option:hover{color:var(--t1)}
.sb-option input[type=checkbox]{width:15px;height:15px;accent-color:var(--red);cursor:pointer;flex-shrink:0}
.sb-option.sel{color:var(--t1);font-weight:600}

/* POPULAR TAGS */
.tag-cloud{display:flex;flex-wrap:wrap;gap:6px}
.ptag{background:var(--surface);border:1px solid var(--border);border-radius:3px;padding:4px 10px;font-size:.72rem;font-weight:600;color:var(--t2);cursor:pointer;transition:all .18s}
.ptag:hover{border-color:var(--red);color:var(--red);background:rgba(232,25,44,.06)}

/* ─ MAIN AREA */
.main-area{display:flex;flex-direction:column;gap:14px}

/* ─ SORT / FILTER BAR */
.sort-bar{background:var(--card);border:1px solid var(--border);border-radius:var(--r2);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.results{font-size:.82rem;color:var(--t2);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.results strong{color:var(--t1)}
.sort-r{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.sort-lbl{font-size:.76rem;font-weight:700;color:var(--t2);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
.sort-sel{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--r);padding:7px 12px;color:var(--t1);font-family:'Barlow',sans-serif;font-size:.82rem;outline:none;cursor:pointer;transition:border-color .2s;min-width:170px}
.sort-sel:focus{border-color:var(--red)}
.view-btns{display:flex;gap:4px}
.vbtn{width:32px;height:32px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface);color:var(--t3);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.78rem;transition:all .18s}
.vbtn.on,.vbtn:hover{background:var(--red);border-color:var(--red);color:#fff}
.mobile-filter-btn{display:none;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--r);padding:7px 14px;color:var(--t1);font-family:'Barlow',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;align-items:center;gap:7px;transition:all .18s}
.mobile-filter-btn:hover{border-color:var(--red);color:var(--red)}

/* ─ PRODUCTS GRID */
.pg{display:grid;gap:12px}
.pg.g3{grid-template-columns:repeat(3,1fr)}
.pg.g4{grid-template-columns:repeat(4,1fr)}
.pg.list{grid-template-columns:1fr}

/* ─ PRODUCT CARD — GRID */
.pc{background:var(--card);border:1px solid var(--border);border-radius:var(--r2);overflow:hidden;transition:all .28s;display:flex;flex-direction:column;position:relative}
.pc:hover{border-color:rgba(232,25,44,.32);transform:translateY(-5px);box-shadow:0 16px 48px rgba(0,0,0,.7),0 0 0 1px rgba(232,25,44,.08)}
.pc-img{height:175px;background:var(--surface);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;flex-shrink:0}
.pc-ico{font-size:3.4rem;color:var(--t4);transition:all .28s}
.pc:hover .pc-ico{color:rgba(232,25,44,.22);transform:scale(1.08)}
.pc-badges{position:absolute;top:8px;left:8px;display:flex;flex-direction:column;gap:4px;z-index:2}
.pb{font-size:.58rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:2px 7px;border-radius:2px}
.pb-new{background:var(--cyan);color:#000}.pb-sale{background:var(--red);color:#fff}.pb-hot{background:var(--orange);color:#fff}.pb-out{background:var(--lift);color:var(--t3);border:1px solid var(--border)}
.pc-wish{position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.55);border:1px solid var(--border);color:var(--t3);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.78rem;transition:all .2s;backdrop-filter:blur(4px);z-index:2}
.pc-wish:hover,.pc-wish.wl{color:var(--red);border-color:var(--red);background:rgba(232,25,44,.15)}
.pc-overlay{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(6,6,8,.96),transparent);padding:10px 8px 8px;display:flex;gap:5px;transform:translateY(101%);transition:transform .28s;z-index:3}
.pc:hover .pc-overlay{transform:translateY(0)}
.pc-body{padding:12px;flex:1;display:flex;flex-direction:column}
.pc-brand{font-size:.62rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--t3);margin-bottom:4px}
.pc-cat{font-size:.6rem;font-weight:600;color:var(--t3);margin-bottom:3px;letter-spacing:.04em}
.pc-name{font-size:.83rem;font-weight:600;color:var(--t1);line-height:1.35;flex:1;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pc-stars{color:var(--yellow);font-size:.66rem;letter-spacing:.5px;margin-bottom:8px}
.pc-stars span{color:var(--t3);font-size:.66rem;margin-left:4px}
.pc-pr{display:flex;align-items:baseline;flex-wrap:wrap;gap:6px;margin-bottom:7px}
.pc-price{font-family:'Barlow Condensed',sans-serif;font-size:1.2rem;font-weight:900}
.pc-was{font-size:.72rem;color:var(--t3);text-decoration:line-through}
.pc-disc{font-size:.6rem;font-weight:800;background:rgba(232,25,44,.11);color:var(--red);padding:2px 5px;border-radius:2px}
.pc-stk{font-size:.67rem;margin-bottom:10px;display:flex;align-items:center;gap:5px}
.stk-in{color:var(--green)}.stk-out{color:var(--t3)}.stk-low{color:var(--orange)}
.pc-cta{width:100%;background:rgba(232,25,44,.09);border:1.5px solid rgba(232,25,44,.22);color:var(--red);border-radius:var(--r);padding:8px;font-family:'Barlow',sans-serif;font-size:.78rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;margin-top:auto}
.pc-cta:hover{background:var(--red);color:#fff;border-color:var(--red)}
.pc-cta:disabled{background:var(--surface);border-color:var(--border);color:var(--t3);cursor:not-allowed}
.btn-ov{background:var(--red);color:#fff;border:none;padding:8px 14px;border-radius:var(--r);font-weight:700;cursor:pointer;font-size:.76rem;transition:all .2s;flex:1;text-align:center;display:flex;align-items:center;justify-content:center;gap:5px}
.btn-ov:hover{background:var(--red2)}
.btn-ov-g{background:var(--surface);border:1px solid var(--border);color:var(--t2);flex-shrink:0;padding:8px 12px}
.btn-ov-g:hover{background:var(--lift);color:var(--t1)}

/* ─ LIST VIEW CARD */
.pg.list .pc{flex-direction:row;height:auto}
.pg.list .pc-img{width:160px;height:auto;min-height:140px;flex-shrink:0}
.pg.list .pc-body{padding:16px}
.pg.list .pc-name{-webkit-line-clamp:3}
.pg.list .pc-cta{width:auto;padding:8px 22px}
.pg.list .pc-overlay{display:none}
.pg.list .pc:hover{transform:translateY(-3px)}

/* ─ EMPTY STATE */
.empty{text-align:center;padding:70px 40px;color:var(--t3)}
.empty i{font-size:3.5rem;margin-bottom:16px;display:block;opacity:.4}
.empty h3{font-size:1.1rem;font-weight:700;margin-bottom:8px;color:var(--t2)}
.empty p{font-size:.85rem;margin-bottom:20px}
.empty a{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:var(--red);color:#fff;border-radius:var(--r);font-weight:700;transition:background .18s;font-size:.88rem}
.empty a:hover{background:var(--red2)}

/* ─ PAGINATION */
.paging{display:flex;gap:5px;justify-content:center;align-items:center;flex-wrap:wrap;padding-top:8px}
.paging a,.paging span{min-width:34px;height:34px;padding:4px 8px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border);border-radius:var(--r);background:var(--card);color:var(--t2);font-size:.8rem;font-weight:700;transition:all .2s;cursor:pointer}
.paging a:hover{border-color:var(--red);color:var(--red);background:rgba(232,25,44,.05)}
.paging span.on{background:var(--red);color:#fff;border-color:var(--red)}
.paging span.ellipsis{background:transparent;border-color:transparent;color:var(--t3);cursor:default}
.paging-info{font-size:.78rem;color:var(--t3);text-align:center;margin-top:8px}

/* ─ TOAST */
.toasts{position:fixed;bottom:22px;right:22px;z-index:9999;display:flex;flex-direction:column;gap:7px}
.toast{background:var(--card);border:1px solid var(--border2);border-radius:var(--r2);padding:11px 16px;display:flex;align-items:center;gap:10px;font-size:.82rem;min-width:240px;box-shadow:0 8px 32px rgba(0,0,0,.7);transform:translateX(110%);transition:transform .35s cubic-bezier(.4,0,.2,1)}
.toast.in{transform:translateX(0)}

/* ─ DRAWER (mobile filter) */
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:500;opacity:0;pointer-events:none;transition:opacity .3s}
.drawer-overlay.open{opacity:1;pointer-events:all}
.drawer{position:fixed;left:0;top:0;bottom:0;width:300px;background:var(--card);border-right:1px solid var(--border2);z-index:501;transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);overflow-y:auto;padding:20px}
.drawer.open{transform:translateX(0)}
.drawer-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.drawer-head h3{font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.2rem;text-transform:uppercase}
.drawer-close{width:34px;height:34px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface);color:var(--t2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.9rem;transition:all .18s}
.drawer-close:hover{background:var(--red);border-color:var(--red);color:#fff}

/* ─ WHATSAPP */
.wa-float{position:fixed;bottom:24px;left:24px;z-index:8000;background:#25d366;color:#fff;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;box-shadow:0 4px 20px rgba(37,211,102,.4);transition:all .2s}
.wa-float:hover{transform:scale(1.1)}

/* ─ RESPONSIVE */
@media(max-width:1100px){.pg.g4{grid-template-columns:repeat(3,1fr)}}
@media(max-width:1024px){
  .shop-wrap{grid-template-columns:1fr}
  .sidebar{display:none;position:static}
  .mobile-filter-btn{display:flex}
}
@media(max-width:768px){
  .pg.g3,.pg.g4{grid-template-columns:repeat(2,1fr)}
  .pg.list .pc{flex-direction:column}
  .pg.list .pc-img{width:100%;height:160px}
  .hdr{flex-wrap:wrap;height:auto;padding:10px}
  .search-bar{order:3;max-width:100%;width:100%}
  .shop-hdr{padding:14px 20px}
  .shop-hdr-l h1{font-size:1.3rem}
}
@media(max-width:520px){
  .pg.g3,.pg.g4{grid-template-columns:1fr 1fr}
  .sort-lbl{display:none}
  .sort-sel{min-width:0;width:100%}
  .sort-bar{gap:10px}
}
</style>
<script>
    // Set global variable for customer login status
    window.customerLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
</script>
</head>
<body>

<!-- PROMO -->
<div class="promo">
  🚀 FREE DELIVERY on orders over Rs. 2,000
  <span class="promo-sep">|</span>
  Use code <strong>SAP10</strong> — 10% off your first order!
  <span class="promo-sep">|</span>
  <a href="tel:+94773987246">+94 77 398 7246</a>
</div>

<!-- HEADER -->
<header class="hdr">
  <a href="index.php" class="logo">
    <img src="https://sapcomputers.lk/storage/2025/05/cropped-site-logo-WHITE.png" alt="SAP Computers" class="logo-img">
  </a>
  <div class="search-bar">
    <select class="search-cat" id="sCat">
      <option value="">All Products</option>
      <?php foreach($categories as $c): ?>
        <option value="<?=(int)$c['category_id']?>" <?=$category==(int)$c['category_id']?'selected':''?>><?=htmlspecialchars($c['category_name'])?></option>
      <?php endforeach; ?>
    </select>
    <input class="search-inp" type="text" id="sInp" value="<?=htmlspecialchars($search)?>" placeholder="Search laptops, printers, CCTV, RAM, cables...">
    <button class="search-go" onclick="doSearch()"><i class="fas fa-search"></i></button>
  </div>
  <div class="hdr-acts">
    <a href="<?=isset($_SESSION['customer_id'])?'account.php':'login.php'?>" class="hact">
      <i class="fas fa-user-circle"></i><?=isset($_SESSION['customer_id'])?'Account':'Login'?>
    </a>
    <a href="wishlist.php" class="hact"><i class="far fa-heart"></i>Wishlist</a>
    <a href="cart.php" class="hact hact-cart">
      <i class="fas fa-shopping-cart"></i>Cart
      <?php if(!empty($_SESSION['cart'])): ?>
        <span class="hbadge"><?=count($_SESSION['cart'])?></span>
      <?php endif; ?>
    </a>
  </div>
</header>

<!-- NAV -->
<nav class="nav">
  <button class="cats-btn" onclick="window.location='shop.php'"><i class="fas fa-bars"></i> All Categories</button>
  <ul class="nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="shop.php" class="active">Shop</a></li>
    <li><a href="shop.php?cat=gaming">Gaming</a></li>
    <li><a href="shop.php?cat=laptops">Laptops</a></li>
    <li><a href="shop.php?cat=printers">Printers</a></li>
    <li><a href="shop.php?cat=cctv">CCTV</a></li>
    <li><a href="shop.php?cat=networking">Networking</a></li>
    <li><a href="shop.php?sale=1" class="nav-deal">🔥 Flash Deals</a></li>
  </ul>
  <div class="nav-r">
    <span class="ntag ntag-g"><i class="fas fa-truck"></i> Free Ship Rs.2000+</span>
    <span class="ntag-p"><i class="fas fa-phone-alt"></i>+94 33 729 4388</span>
  </div>
</nav>

<!-- BREADCRUMB -->
<div class="bc">
  <a href="index.php"><i class="fas fa-home"></i> Home</a>
  <span class="bc-sep"><i class="fas fa-chevron-right" style="font-size:.55rem"></i></span>
  <a href="shop.php">Shop</a>
  <?php if($activeCatName): ?>
    <span class="bc-sep"><i class="fas fa-chevron-right" style="font-size:.55rem"></i></span>
    <span class="bc-current"><?=htmlspecialchars($activeCatName)?></span>
  <?php elseif($search): ?>
    <span class="bc-sep"><i class="fas fa-chevron-right" style="font-size:.55rem"></i></span>
    <span class="bc-current">Search: "<?=htmlspecialchars($search)?>"</span>
  <?php else: ?>
    <span class="bc-sep"><i class="fas fa-chevron-right" style="font-size:.55rem"></i></span>
    <span class="bc-current">All Products</span>
  <?php endif; ?>
</div>

<!-- SHOP HEADER -->
<div class="shop-hdr">
  <div class="shop-hdr-l">
    <h1>
      <?php if($activeCatName): ?>
        <i class="fas fa-layer-group" style="font-size:1.2rem;color:var(--red)"></i>
        <?=htmlspecialchars($activeCatName)?>
      <?php elseif($search): ?>
        <i class="fas fa-search" style="font-size:1.2rem;color:var(--red)"></i>
        Results for "<?=htmlspecialchars($search)?>"
      <?php else: ?>
        <i class="fas fa-store" style="font-size:1.2rem;color:var(--red)"></i>
        All Products
      <?php endif; ?>
    </h1>
    <p><?=$total?> product<?=$total!=1?'s':''?> found<?=$total_pages>1?' — page '.$page.' of '.$total_pages:''?></p>
    <!-- Active Filters -->
    <?php if($category || $search || $min_price > 0 || $max_price < 500000): ?>
    <div class="active-filters">
      <?php if($activeCatName): ?>
        <span class="af-tag"><i class="fas fa-layer-group"></i><?=htmlspecialchars($activeCatName)?><a href="shop.php?<?=qStr([],['category','page'])?>"><i class="fas fa-times"></i></a></span>
      <?php endif; ?>
      <?php if($search): ?>
        <span class="af-tag"><i class="fas fa-search"></i>"<?=htmlspecialchars($search)?>"<a href="shop.php?<?=qStr([],['search','page'])?>"><i class="fas fa-times"></i></a></span>
      <?php endif; ?>
      <?php if($min_price > 0 || $max_price < 500000): ?>
        <span class="af-tag"><i class="fas fa-tag"></i>Rs.<?=number_format($min_price,0)?>–Rs.<?=number_format($max_price,0)?><a href="shop.php?<?=qStr([],['min_price','max_price','page'])?>"><i class="fas fa-times"></i></a></span>
      <?php endif; ?>
      <a href="shop.php" style="font-size:.72rem;color:var(--t3);font-weight:600;display:flex;align-items:center;gap:4px;transition:color .18s;padding:3px 8px;border-radius:3px;border:1px solid var(--border)"><i class="fas fa-times"></i> Clear All</a>
    </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <div class="view-btns" id="viewBtns">
      <button class="vbtn on" title="3-column grid" onclick="setView('g3',this)"><i class="fas fa-th-large"></i></button>
      <button class="vbtn" title="4-column grid" onclick="setView('g4',this)"><i class="fas fa-th"></i></button>
      <button class="vbtn" title="List view" onclick="setView('list',this)"><i class="fas fa-list"></i></button>
    </div>
  </div>
</div>

<!-- MAIN WRAP -->
<div class="wrap">
<div class="shop-wrap">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">

    <!-- SEARCH -->
    <div class="sb-panel">
      <div class="sb-head"><span><i class="fas fa-search"></i> Search</span></div>
      <div class="sb-body">
        <form method="GET" action="shop.php">
          <?php if($category): ?><input type="hidden" name="category" value="<?=(int)$category?>"><?php endif; ?>
          <?php if($sort!='newest'): ?><input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>"><?php endif; ?>
          <div class="sb-search">
            <input class="sb-inp" type="text" name="search" placeholder="Search products..." value="<?=htmlspecialchars($search)?>">
            <button class="sb-go" type="submit"><i class="fas fa-search"></i></button>
          </div>
        </form>
      </div>
    </div>

    <!-- CATEGORIES -->
    <div class="sb-panel">
      <div class="sb-head">
        <span><i class="fas fa-layer-group"></i> Categories</span>
        <?php if($category): ?><a href="shop.php">All</a><?php endif; ?>
      </div>
      <div class="sb-body" style="padding:8px">
        <div class="cat-list">
          <?php
          $catIcos=['fa-laptop','fa-print','fa-video','fa-microchip','fa-mobile-alt','fa-network-wired','fa-memory','fa-gamepad','fa-desktop','fa-headphones','fa-hdd','fa-keyboard','fa-mouse','fa-server','fa-wifi','fa-camera','fa-plug','fa-battery-full'];
          foreach($categories as $i=>$cat):
            $ico=$catIcos[$i%count($catIcos)];
            $isOn=$category==(int)$cat['category_id'];
          ?>
          <?php
            $catUrl = 'shop.php?category='.(int)$cat['category_id'];
            if($sort!='newest') $catUrl .= '&sort='.urlencode($sort);
            if($search) $catUrl .= '&search='.urlencode($search);
          ?>
          <a href="<?=$catUrl?>" class="cat-item<?=$isOn?' on':''?>">
            <span class="cat-n"><i class="fas <?=$ico?>"></i><?=htmlspecialchars($cat['category_name'])?></span>
            <?php if($isOn): ?><i class="fas fa-check" style="font-size:.65rem;color:var(--red)"></i><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- PRICE RANGE -->
    <div class="sb-panel">
      <div class="sb-head"><span><i class="fas fa-tag"></i> Price Range</span></div>
      <div class="sb-body">
        <form method="GET" action="shop.php" id="priceForm">
          <?php if($category): ?><input type="hidden" name="category" value="<?=(int)$category?>"><?php endif; ?>
          <?php if($search): ?><input type="hidden" name="search" value="<?=htmlspecialchars($search)?>"><?php endif; ?>
          <input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>">
          <input type="hidden" name="page" value="1">
          <div class="price-row">
            <input type="number" class="price-inp" name="min_price" id="pMin" value="<?=(int)$min_price?>" placeholder="Min" min="0" max="<?=$maxDb?>">
            <span class="price-sep">—</span>
            <input type="number" class="price-inp" name="max_price" id="pMax" value="<?=$max_price<500000?(int)$max_price:$maxDb?>" placeholder="Max" min="0" max="<?=$maxDb?>">
          </div>
          <div class="range-wrap">
            <div class="range-track"></div>
            <div class="range-fill" id="rangeFill"></div>
            <input type="range" class="price-range" id="rMin" min="0" max="<?=$maxDb?>" value="<?=(int)$min_price?>" oninput="syncRange('min',this.value)">
            <input type="range" class="price-range" id="rMax" min="0" max="<?=$maxDb?>" value="<?=$max_price<500000?(int)$max_price:$maxDb?>" oninput="syncRange('max',this.value)">
          </div>
          <button type="submit" class="price-apply"><i class="fas fa-filter"></i> Apply Price Filter</button>
        </form>
      </div>
    </div>

    <!-- AVAILABILITY -->
    <div class="sb-panel">
      <div class="sb-head"><span><i class="fas fa-check-circle"></i> Availability</span></div>
      <div class="sb-body" style="padding:6px 12px">
        <label class="sb-option<?=$in_stock?' sel':''?>">
          <input type="checkbox" id="inStockCb" <?=$in_stock?'checked':''?> onchange="toggleStock(this)">
          <span><i class="fas fa-circle" style="font-size:.45rem;color:var(--green)"></i> In Stock Only</span>
        </label>
      </div>
    </div>

    <!-- POPULAR TAGS -->
    <div class="sb-panel">
      <div class="sb-head"><span><i class="fas fa-hashtag"></i> Popular Tags</span></div>
      <div class="sb-body">
        <div class="tag-cloud">
          <?php foreach(['Laptop','Gaming','Printer','CCTV','SSD','RAM','Mouse','Keyboard','Monitor','Router','Headphone','Webcam'] as $tag): ?>
            <a href="shop.php?search=<?=urlencode($tag)?>" class="ptag"><?=htmlspecialchars($tag)?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-area">

    <!-- SORT BAR -->
    <div class="sort-bar">
      <div class="results">
        <strong><?=$total?></strong> product<?=$total!=1?'s':''?> found
        <?php if($search||$activeCatName): ?>
          <span>—</span>
          <?php if($search): ?><span>for "<strong><?=htmlspecialchars($search)?></strong>"</span><?php endif; ?>
          <?php if($activeCatName): ?><span>in <strong><?=htmlspecialchars($activeCatName)?></strong></span><?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="sort-r">
        <button class="mobile-filter-btn" onclick="openDrawer()"><i class="fas fa-sliders-h"></i> Filters</button>
        <span class="sort-lbl">Sort:</span>
        <select class="sort-sel" onchange="setSort(this.value)">
          <option value="newest"    <?=$sort=='newest'   ?'selected':''?>>Newest First</option>
          <option value="price-low" <?=$sort=='price-low'?'selected':''?>>Price: Low → High</option>
          <option value="price-high"<?=$sort=='price-high'?'selected':''?>>Price: High → Low</option>
          <option value="name"      <?=$sort=='name'     ?'selected':''?>>Name A → Z</option>
        </select>
        <div class="view-btns" id="vb2">
          <button class="vbtn on" title="Grid" onclick="setView('g3',this,true)"><i class="fas fa-th-large"></i></button>
          <button class="vbtn" title="4-col" onclick="setView('g4',this,true)"><i class="fas fa-th"></i></button>
          <button class="vbtn" title="List" onclick="setView('list',this,true)"><i class="fas fa-list"></i></button>
        </div>
      </div>
    </div>

    <!-- PRODUCT GRID -->
    <?php if(!empty($products)): ?>
    <div class="pg g3" id="prodGrid">
      <?php
      $picos=['fa-laptop','fa-microchip','fa-memory','fa-hdd','fa-print','fa-desktop','fa-video','fa-keyboard','fa-mouse','fa-headphones','fa-network-wired','fa-mobile-alt'];
      foreach($products as $i=>$p):
        $ico=$picos[$i%count($picos)];
        $hasDisc=($i%4===0);
        $disc=10+($i%3)*8;
        $wasPrice=round($p['selling_price']*(1+$disc/100));
      ?>
      <div class="pc" style="animation-delay:<?=($i%12)*.04?>s">
        <div class="pc-img">
          <div class="pc-badges">
            <?php if($i<3): ?><span class="pb pb-new">New</span><?php endif; ?>
            <?php if($hasDisc): ?><span class="pb pb-sale">-<?=$disc?>%</span><?php endif; ?>
            <?php if($p['qty']<=0): ?><span class="pb pb-out">Sold Out</span><?php endif; ?>
          </div>
          <i class="fas <?=$ico?> pc-ico"></i>
          <button class="pc-wish" onclick="toggleWish(this,event)"><i class="far fa-heart"></i></button>
          <div class="pc-overlay">
            <button class="btn-ov add-to-cart" data-id="<?=(int)$p['product_id']?>"><i class="fas fa-cart-plus"></i> Add to Cart</button>
            <a href="product.php?id=<?=(int)$p['product_id']?>" class="btn-ov btn-ov-g" title="View Details"><i class="fas fa-eye"></i></a>
          </div>
        </div>
        <div class="pc-body">
          <div class="pc-brand"><?=htmlspecialchars($p['brand']??'Generic')?></div>
          <?php if(!empty($p['category_name'])): ?><div class="pc-cat"><?=htmlspecialchars($p['category_name'])?></div><?php endif; ?>
          <div class="pc-name"><?=htmlspecialchars($p['product_name'])?></div>
          <div class="pc-stars">★★★★<?=$i%3==2?'☆':'★'?> <span>(<?=rand(14,214)?>)</span></div>
          <div class="pc-pr">
            <span class="pc-price">Rs. <?=number_format($p['selling_price'],0)?></span>
            <?php if($hasDisc): ?>
              <span class="pc-was">Rs. <?=number_format($wasPrice,0)?></span>
              <span class="pc-disc">Save <?=$disc?>%</span>
            <?php endif; ?>
          </div>
          <div class="pc-stk">
            <?php if($p['qty']>5): ?>
              <span class="stk-in"><i class="fas fa-circle" style="font-size:.38rem"></i> In Stock</span>
            <?php elseif($p['qty']>0): ?>
              <span class="stk-low"><i class="fas fa-circle" style="font-size:.38rem"></i> Only <?=$p['qty']?> left!</span>
            <?php else: ?>
              <span class="stk-out"><i class="fas fa-circle" style="font-size:.38rem"></i> Out of Stock</span>
            <?php endif; ?>
          </div>
          <?php if($p['qty']>0): ?>
            <button class="pc-cta add-to-cart" data-id="<?=(int)$p['product_id']?>"><i class="fas fa-cart-plus"></i> Add to Cart</button>
          <?php else: ?>
            <button class="pc-cta" disabled><i class="fas fa-bell"></i> Notify When Available</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- PAGINATION -->
    <?php if($total_pages>1): ?>
    <div>
      <div class="paging">
        <?php
        $qs = function($p) use($category,$search,$sort,$min_price,$max_price) {
          $params = ['page'=>$p];
          if($category) $params['category']=$category;
          if($search) $params['search']=$search;
          if($sort!='newest') $params['sort']=$sort;
          if($min_price>0) $params['min_price']=$min_price;
          if($max_price<500000) $params['max_price']=$max_price;
          return 'shop.php?'.http_build_query($params);
        };
        // Prev
        if($page>1): ?>
          <a href="<?=$qs(1)?>" title="First"><i class="fas fa-angle-double-left"></i></a>
          <a href="<?=$qs($page-1)?>" title="Previous"><i class="fas fa-angle-left"></i></a>
        <?php endif;
        // Page nums
        $start=max(1,$page-2); $end=min($total_pages,$page+2);
        if($start>1): ?><span class="ellipsis">…</span><?php endif;
        for($i=$start;$i<=$end;$i++):
          if($i==$page): ?><span class="on"><?=$i?></span>
          <?php else: ?><a href="<?=$qs($i)?>"><?=$i?></a><?php endif;
        endfor;
        if($end<$total_pages): ?><span class="ellipsis">…</span><?php endif;
        // Next
        if($page<$total_pages): ?>
          <a href="<?=$qs($page+1)?>" title="Next"><i class="fas fa-angle-right"></i></a>
          <a href="<?=$qs($total_pages)?>" title="Last"><i class="fas fa-angle-double-right"></i></a>
        <?php endif; ?>
      </div>
      <div class="paging-info">Page <?=$page?> of <?=$total_pages?> &mdash; <?=$total?> products total</div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty">
      <i class="fas fa-search"></i>
      <h3>No products found</h3>
      <p>Try adjusting your search or filter criteria to find what you're looking for.</p>
      <a href="shop.php"><i class="fas fa-store"></i> Browse All Products</a>
    </div>
    <?php endif; ?>

  </main>
</div><!-- /shop-wrap -->
</div><!-- /wrap -->

<!-- MOBILE FILTER DRAWER -->
<div class="drawer-overlay" id="dOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
  <div class="drawer-head">
    <h3><i class="fas fa-sliders-h" style="color:var(--red);margin-right:8px"></i>Filters</h3>
    <button class="drawer-close" onclick="closeDrawer()"><i class="fas fa-times"></i></button>
  </div>
  <!-- Search -->
  <div style="margin-bottom:20px">
    <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--t2);margin-bottom:10px"><i class="fas fa-search" style="color:var(--red);margin-right:6px"></i>Search</div>
    <form method="GET" action="shop.php">
      <?php if($category): ?><input type="hidden" name="category" value="<?=(int)$category?>"><?php endif; ?>
      <div class="sb-search">
        <input class="sb-inp" type="text" name="search" placeholder="Search..." value="<?=htmlspecialchars($search)?>" style="flex:1">
        <button class="sb-go" type="submit"><i class="fas fa-search"></i></button>
      </div>
    </form>
  </div>
  <!-- Categories in drawer -->
  <div style="margin-bottom:20px">
    <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--t2);margin-bottom:10px"><i class="fas fa-layer-group" style="color:var(--red);margin-right:6px"></i>Categories</div>
    <div class="cat-list" style="max-height:220px">
      <?php $allUrl='shop.php'; if($sort!='newest') $allUrl.='?sort='.urlencode($sort); ?>
      <a href="<?=$allUrl?>" class="cat-item<?=!$category?' on':''?>"><?php unset($allUrl); ?>
        <span class="cat-n"><i class="fas fa-store"></i>All Products</span>
      </a>
      <?php foreach($categories as $i=>$cat):
        $ico=$catIcos[$i%count($catIcos)];
        $isOn=$category==(int)$cat['category_id'];
      ?>
      <?php
        $dCatUrl='shop.php?category='.(int)$cat['category_id'];
        if($sort!='newest') $dCatUrl.='&sort='.urlencode($sort);
      ?>
      <a href="<?=$dCatUrl?>" class="cat-item<?=$isOn?' on':''?>"><?php unset($dCatUrl); ?>
        <span class="cat-n"><i class="fas <?=$ico?>"></i><?=htmlspecialchars($cat['category_name'])?></span>
        <?php if($isOn): ?><i class="fas fa-check" style="font-size:.65rem;color:var(--red)"></i><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Price in drawer -->
  <div style="margin-bottom:20px">
    <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--t2);margin-bottom:10px"><i class="fas fa-tag" style="color:var(--red);margin-right:6px"></i>Price Range</div>
    <form method="GET" action="shop.php">
      <?php if($category): ?><input type="hidden" name="category" value="<?=(int)$category?>"><?php endif; ?>
      <?php if($search): ?><input type="hidden" name="search" value="<?=htmlspecialchars($search)?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?=htmlspecialchars($sort)?>">
      <div class="price-row">
        <input type="number" class="price-inp" name="min_price" value="<?=(int)$min_price?>" placeholder="Min">
        <span class="price-sep">—</span>
        <input type="number" class="price-inp" name="max_price" value="<?=$max_price<500000?(int)$max_price:$maxDb?>" placeholder="Max">
      </div>
      <button type="submit" class="price-apply"><i class="fas fa-filter"></i> Apply</button>
    </form>
  </div>
  <!-- In stock in drawer -->
  <div>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem;color:var(--t2)">
      <input type="checkbox" style="width:16px;height:16px;accent-color:var(--red)" <?=$in_stock?'checked':''?> onchange="toggleStock(this)">
      <span><i class="fas fa-circle" style="font-size:.45rem;color:var(--green);margin-right:4px"></i>In Stock Only</span>
    </label>
  </div>
</div>

<!-- WHATSAPP -->
<a href="https://web.whatsapp.com/send?phone=94773987246" class="wa-float" target="_blank">
  <i class="fab fa-whatsapp"></i>
</a>

<!-- TOASTS -->
<div class="toasts" id="toasts"></div>

<script>
// SEARCH
function doSearch(){
  const q=document.getElementById('sInp').value.trim();
  const cat=document.getElementById('sCat').value;
  if(q||cat){
    const p=new URLSearchParams();
    if(q) p.set('search',q);
    if(cat) p.set('category',cat);
    window.location='shop.php?'+p.toString();
  }
}
document.getElementById('sInp').addEventListener('keypress',e=>{if(e.key==='Enter')doSearch();});

// SORT
function setSort(v){
  const p=new URLSearchParams(window.location.search);
  p.set('sort',v);p.set('page','1');
  window.location='shop.php?'+p.toString();
}

// VIEW
function setView(mode,btn,fromBar2=false){
  document.getElementById('prodGrid').className='pg '+mode;
  // Sync both button groups
  ['viewBtns','vb2'].forEach(id=>{
    const c=document.getElementById(id);
    if(c) c.querySelectorAll('.vbtn').forEach(b=>b.classList.remove('on'));
  });
  btn.classList.add('on');
  // Mirror to other bar
  const bars=['viewBtns','vb2'];
  bars.forEach(id=>{
    const c=document.getElementById(id);
    if(c){
      const idx=[...btn.parentElement.querySelectorAll('.vbtn')].indexOf(btn);
      const counterpart=c.querySelectorAll('.vbtn')[idx];
      if(counterpart) counterpart.classList.add('on');
    }
  });
  localStorage.setItem('sapView',mode);
}

// Restore view
(function(){
  const sv=localStorage.getItem('sapView');
  if(sv&&sv!=='g3'){
    const g=document.getElementById('prodGrid');
    if(g) g.className='pg '+sv;
    const idx={g3:0,g4:1,list:2}[sv]??0;
    ['viewBtns','vb2'].forEach(id=>{
      const c=document.getElementById(id);
      if(c){c.querySelectorAll('.vbtn').forEach(b=>b.classList.remove('on'));const btn=c.querySelectorAll('.vbtn')[idx];if(btn)btn.classList.add('on');}
    });
  }
})();

// PRICE RANGE SLIDER
(function(){
  const rMin=document.getElementById('rMin'),rMax=document.getElementById('rMax');
  const pMin=document.getElementById('pMin'),pMax=document.getElementById('pMax');
  const fill=document.getElementById('rangeFill');
  const MAX=rMin?parseInt(rMin.max):500000;
  function update(){
    if(!rMin||!rMax) return;
    const lo=parseInt(rMin.value),hi=parseInt(rMax.value);
    if(lo>hi){rMin.value=hi;if(pMin)pMin.value=hi;return;}
    const loPct=lo/MAX*100,hiPct=hi/MAX*100;
    if(fill){fill.style.left=loPct+'%';fill.style.width=(hiPct-loPct)+'%';}
    if(pMin)pMin.value=lo;
    if(pMax)pMax.value=hi;
  }
  if(rMin) rMin.addEventListener('input',update);
  if(rMax) rMax.addEventListener('input',update);
  if(pMin) pMin.addEventListener('input',()=>{if(rMin)rMin.value=pMin.value;update();});
  if(pMax) pMax.addEventListener('input',()=>{if(rMax)rMax.value=pMax.value;update();});
  update();
})();

window.syncRange=function(side,val){};

// IN STOCK
function toggleStock(cb){
  const p=new URLSearchParams(window.location.search);
  if(cb.checked) p.set('in_stock','1'); else p.delete('in_stock');
  p.set('page','1');
  window.location='shop.php?'+p.toString();
}

// MOBILE DRAWER
function openDrawer(){document.getElementById('drawer').classList.add('open');document.getElementById('dOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeDrawer(){document.getElementById('drawer').classList.remove('open');document.getElementById('dOverlay').classList.remove('open');document.body.style.overflow='';}

// WISHLIST
function toggleWish(btn,e){
  e.stopPropagation();
  btn.classList.toggle('wl');
  btn.querySelector('i').className=btn.classList.contains('wl')?'fas fa-heart':'far fa-heart';
  toast(btn.classList.contains('wl')?'Added to wishlist':'Removed from wishlist',btn.classList.contains('wl')?'var(--red)':'var(--green)');
}

// ADD TO CART
document.querySelectorAll('.add-to-cart').forEach(btn=>{
  btn.addEventListener('click',async function(e){
    e.stopPropagation();
    const id=this.dataset.id,orig=this.innerHTML;
    this.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    this.disabled=true;
    try{
      const r=await fetch('api/cart.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=add_to_cart&product_id=${id}&quantity=1`});
      const d=await r.json();
      if(d.success){toast('Added to cart!');this.innerHTML='<i class="fas fa-check"></i> Added!';setTimeout(()=>{this.innerHTML=orig;this.disabled=false;},2000);}
      else{toast(d.message||'Could not add','var(--red)');this.innerHTML=orig;this.disabled=false;}
    }catch{toast('Sign in to add items','var(--red)');this.innerHTML=orig;this.disabled=false;}
  });
});

// TOAST
function toast(msg,clr='var(--green)'){
  const c=document.getElementById('toasts'),t=document.createElement('div');
  t.className='toast';
  t.innerHTML=`<i class="fas fa-check-circle" style="color:${clr};font-size:1rem"></i><span>${msg}</span>`;
  c.appendChild(t);
  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('in')));
  setTimeout(()=>{t.classList.remove('in');setTimeout(()=>t.remove(),380);},3000);
}
</script>
</body>
</html>