<?php
require_once '../config/config.php';

echo "<h2>SAP Computers Database Diagnostic</h2>\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    echo "<p style='color:green'>✓ Database connection successful</p>\n\n";
    
    // Test 1: Check categories
    echo "<h3>Categories Check</h3>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total categories: <strong>" . $count . "</strong><br>\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT category_id, category_name FROM categories LIMIT 10");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<ul>\n";
        foreach ($results as $c) {
            echo "  <li>(" . $c['category_id'] . ") " . htmlspecialchars($c['category_name']) . "</li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "\n";
    
    // Test 2: Check products
    echo "<h3>Products Check</h3>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total products: <strong>" . $count . "</strong><br>\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT product_id, product_name, status FROM products LIMIT 10");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<ul>\n";
        foreach ($results as $p) {
            echo "  <li>(" . $p['product_id'] . ") " . htmlspecialchars($p['product_name']) . " [" . $p['status'] . "]</li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "\n";
    
    // Test 3: Check stock
    echo "<h3>Stock Check</h3>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stock");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total stock records: <strong>" . $count . "</strong><br>\n";
    
    echo "\n";
    
    // Test 4: Run the featured products query (same as index.php)
    echo "<h3>Featured Products Query (index.php)</h3>\n";
    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.brand, p.model, p.selling_price, 
               p.category_id, COALESCE(SUM(s.quantity), 0) as total_qty
        FROM products p
        LEFT JOIN stock s ON p.product_id = s.product_id
        GROUP BY p.product_id, p.product_name, p.brand, p.model, p.selling_price, p.category_id
        ORDER BY p.product_id DESC LIMIT 8
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Featured products returned: <strong>" . count($products) . "</strong><br>\n";
    if (count($products) > 0) {
        echo "<ul>\n";
        foreach ($products as $p) {
            echo "  <li>" . htmlspecialchars($p['product_name']) . " - Rs. " . number_format($p['selling_price'], 2) . " (Stock: " . (int)$p['total_qty'] . ")</li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "\n";
    
    // Test 5: Check categories for homepage
    echo "<h3>Categories for Homepage</h3>\n";
    $stmt = $pdo->prepare("SELECT category_id, category_name, description FROM categories LIMIT 6");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Categories returned: <strong>" . count($categories) . "</strong><br>\n";
    if (count($categories) > 0) {
        echo "<ul>\n";
        foreach ($categories as $c) {
            echo "  <li>(" . $c['category_id'] . ") " . htmlspecialchars($c['category_name']) . "</li>\n";
        }
        echo "</ul>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

