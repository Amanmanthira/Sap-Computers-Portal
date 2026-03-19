<?php
/**
 * Process Order API
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request');
}

$customer_id = $_SESSION['customer_id'];
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (empty($cart)) {
    header('Location: ../cart.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    // Get form data
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $street_address = isset($_POST['street_address']) ? trim($_POST['street_address']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'card';

    // Validate required fields
    if (!$full_name || !$email || !$phone || !$street_address || !$city) {
        header('Location: ../checkout.php?error=missing_fields');
        exit;
    }

    // Calculate totals
    $total_amount = 0;
    $item_count = 0;

    foreach ($cart as $product_id => $quantity) {
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        if ($product) {
            $total_amount += $product['price'] * $quantity;
            $item_count += $quantity;
        }
    }

    // Add tax and shipping
    $tax = $total_amount * 0.15;
    $shipping = $total_amount > 2000 ? 0 : 200;
    $grand_total = $total_amount + $tax + $shipping;

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders (customer_id, item_count, total_amount, tax, shipping, grand_total, payment_method, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$customer_id, $item_count, $total_amount, $tax, $shipping, $grand_total, $payment_method]);
    $order_id = $pdo->lastInsertId();

    // Add order items
    foreach ($cart as $product_id => $quantity) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            SELECT ?, ?, ?, price FROM products WHERE id = ?
        ");
        $stmt->execute([$order_id, $product_id, $quantity, $product_id]);

        // Update product quantity
        $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$quantity, $product_id]);
    }

    // Update customer info
    $stmt = $pdo->prepare("
        UPDATE customers SET 
            name = ?, email = ?, phone = ?, 
            address = ?, city = ?, postal_code = ?
        WHERE id = ?
    ");
    $stmt->execute([$full_name, $email, $phone, $street_address, $city, $postal_code, $customer_id]);

    // Clear cart
    unset($_SESSION['cart']);

    // Redirect to success page
    header('Location: ../order-success.php?order_id=' . $order_id);
    exit;

} catch (PDOException $e) {
    header('Location: ../checkout.php?error=database');
    exit;
}
?>
