<?php
require_once '../config/config.php';

echo "<h1>Product Count & Display Test</h1>\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    echo "<p style='color:green'>✓ Connected to database</p>\n\n";
    
    // Test 1: Count
    $total = (int)$pdo->query("SELECT COUNT(*) as total FROM products")->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<h3>COUNT Result: <strong>" . $total . "</strong> products</h3>\n";
    
    if ($total == 0) {
        echo "<p style='color:red'>ERROR: No products in database at all!</p>\n";
        exit;
    }
    
    // Test 2: Simple SELECT
    echo "<h3>Testing Simple SELECT</h3>\n";
    $sql = "SELECT product_id, product_name FROM products LIMIT 5";
    $result = $pdo->query($sql);
    $products = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Query: " . htmlspecialchars($sql) . "<br>\n";
    echo "Results returned: " . count($products) . "<br>\n";
    if (count($products) > 0) {
        echo "<ul>\n";
        foreach ($products as $p) {
            echo "  <li>ID: " . $p['product_id'] . " - " . htmlspecialchars($p['product_name']) . "</li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "\n";
    
    // Test 3: With LIMIT OFFSET
    echo "<h3>Testing With LIMIT OFFSET (Page 1)</h3>\n";
    $per_page = 12;
    $offset = 0;
    $sql = "SELECT product_id, product_name FROM products ORDER BY product_id DESC LIMIT $per_page OFFSET $offset";
    $result = $pdo->query($sql);
    $products = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Query: " . htmlspecialchars($sql) . "<br>\n";
    echo "Results: " . count($products) . " products<br>\n";
    
    // Test 4: Page 2
    echo "<h3>Testing Page 2 (OFFSET 12)</h3>\n";
    $offset = 12;
    $sql = "SELECT product_id, product_name FROM products ORDER BY product_id DESC LIMIT $per_page OFFSET $offset";
    $result = $pdo->query($sql);
    $products = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Query: " . htmlspecialchars($sql) . "<br>\n";
    echo "Results: " . count($products) . " products<br>\n";
    if (count($products) > 0) {
        echo "<ul>\n";
        foreach ($products as $p) {
            echo "  <li>ID: " . $p['product_id'] . " - " . htmlspecialchars($p['product_name']) . "</li>\n";
        }
        echo "</ul>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>
