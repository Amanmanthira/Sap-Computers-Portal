<?php
require_once '../config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
<title>Diagnostic Test</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
table { border-collapse: collapse; margin: 20px 0; width: 100%; }
table, th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
th { background-color: #f2f2f2; }
code { background: #f4f4f4; padding: 2px 5px; }
</style>
</head>
<body>
<h1>Database Diagnostic Test</h1>\n";

echo "<h2>Connection Test</h2>\n";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=sap_computers", "root", "");
    echo "<p class='success'>✓ Database connection successful</p>\n";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Connection failed: " . $e->getMessage() . "</p>\n";
    exit;
}

echo "<h2>Tables Check</h2>\n";
$tables = ['products', 'categories', 'stock', 'customers'];
foreach ($tables as $table) {
    $result = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($result->rowCount() > 0) {
        echo "<p class='success'>✓ Table <code>$table</code> exists</p>\n";
    } else {
        echo "<p class='error'>✗ Table <code>$table</code> NOT found</p>\n";
    }
}

echo "<h2>Products Table Count</h2>\n";
$count = $pdo->query("SELECT COUNT(*) as cnt FROM products")->fetch()['cnt'];
echo "<p><strong>Total products: " . $count . "</strong></p>\n";

if ($count > 0) {
    echo "<h2>Sample Products</h2>\n";
    $products = $pdo->query("SELECT product_id, product_name, selling_price, category_id FROM products LIMIT 5")->fetchAll();
    echo "<table><tr><th>ID</th><th>Name</th><th>Price</th><th>Category</th></tr>\n";
    foreach ($products as $p) {
        echo "<tr><td>" . $p['product_id'] . "</td><td>" . $p['product_name'] . "</td><td>" . $p['selling_price'] . "</td><td>" . $p['category_id'] . "</td></tr>\n";
    }
    echo "</table>\n";
}

echo "<h2>Stock Table Check</h2>\n";
$stock_count = $pdo->query("SELECT COUNT(*) as cnt FROM stock")->fetch()['cnt'];
echo "<p><strong>Total stock records: " . $stock_count . "</strong></p>\n";

if ($stock_count > 0) {
    $sample = $pdo->query("SELECT product_id, branch_id, quantity FROM stock LIMIT 5")->fetchAll();
    echo "<table><tr><th>Product ID</th><th>Branch ID</th><th>Qty</th></tr>\n";
    foreach ($sample as $s) {
        echo "<tr><td>" . $s['product_id'] . "</td><td>" . $s['branch_id'] . "</td><td>" . $s['quantity'] . "</td></tr>\n";
    }
    echo "</table>\n";
}

echo "<h2>Pagination Test (Simulating shop.php)</h2>\n";
$per_page = 12;
$page = 1;
$offset = 0;

$sql = "SELECT product_id, product_name FROM products ORDER BY product_id DESC LIMIT $per_page OFFSET $offset";
echo "<p>SQL: <code>" . htmlspecialchars($sql) . "</code></p>\n";

$stmt = $pdo->query($sql);
$results = $stmt->fetchAll();
echo "<p><strong>Results returned: " . count($results) . " products</strong></p>\n";

if (count($results) > 0) {
    echo "<table><tr><th>ID</th><th>Name</th></tr>\n";
    foreach ($results as $r) {
        echo "<tr><td>" . $r['product_id'] . "</td><td>" . $r['product_name'] . "</td></tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p class='warning'>⚠ No results returned!</p>\n";
}

echo "<h2>PHP Info</h2>\n";
echo "<p>PHP Version: " . phpversion() . "</p>\n";
echo "<p>PDO Driver: ";
echo implode(', ', PDO::getAvailableDrivers()) . "</p>\n";

echo "</body></html>";
?>
