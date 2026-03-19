<?php
require_once '../config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    echo "<h2>Simple Product Query Test</h2>\n";
    
    // Test 1: Direct product query
    echo "<h3>Test 1: Direct SELECT from products</h3>\n";
    $stmt = $pdo->prepare("SELECT p.product_id, p.product_name FROM products p LIMIT 5");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Products returned: " . count($products) . "<br>\n";
    foreach ($products as $p) {
        echo "  - " . htmlspecialchars($p['product_name']) . "<br>\n";
    }
    
    echo "\n";
    
    // Test 2: With pagination
    echo "<h3>Test 2: With LIMIT and OFFSET</h3>\n";
    $limit = 12;
    $offset = 0;
    $stmt = $pdo->prepare("SELECT p.product_id, p.product_name FROM products p ORDER BY p.product_id DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Page 1 (LIMIT 12 OFFSET 0): " . count($products) . " products<br>\n";
    
    $offset = 12;
    $stmt = $pdo->prepare("SELECT p.product_id, p.product_name FROM products p ORDER BY p.product_id DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Page 2 (LIMIT 12 OFFSET 12): " . count($products) . " products<br>\n";
    
    echo "\n";
    
    // Test 3: Count
    echo "<h3>Test 3: COUNT query</h3>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total products: " . $result['total'] . "<br>\n";
    
    echo "\n";
    
    // Test 4: Stock query
    echo "<h3>Test 4: Stock aggregation</h3>\n";
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total_qty FROM stock WHERE product_id = ?");
    $stmt->execute([1]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Product 1 total stock: " . $result['total_qty'] . "<br>\n";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
