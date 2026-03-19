<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Helper.php';
require_once 'includes/customer_functions.php';

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// Check if customer is logged in, if not redirect to login
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

// Calculate totals
$cart = $_SESSION['cart'];
$cart_items = [];
$total = 0;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    foreach ($cart as $product_id => $quantity) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['cart_quantity'] = $quantity;
            $product['subtotal'] = $product['price'] * $quantity;
            $cart_items[] = $product;
            $total += $product['subtotal'];
        }
    }
    
    // Get customer info
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$tax = $total * 0.15;
$shipping = $total > 2000 ? 0 : 200;
$grand_total = $total + $tax + $shipping;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SAP Computers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="checkout-container">
        <div class="container">
            <h1>Checkout</h1>

            <div class="checkout-wrapper">
                <div class="checkout-form">
                    <h2>Shipping Information</h2>
                    <form method="POST" action="api/process_order.php" id="checkoutForm">
                        <div class="form-section">
                            <h3>Personal Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name:</label>
                                    <input type="text" name="full_name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email:</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Phone:</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Shipping Address</h3>
                            <div class="form-group">
                                <label>Street Address:</label>
                                <input type="text" name="street_address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>City:</label>
                                    <input type="text" name="city" value="<?= htmlspecialchars($customer['city'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Postal Code:</label>
                                    <input type="text" name="postal_code" value="<?= htmlspecialchars($customer['postal_code'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Country:</label>
                                <input type="text" name="country" value="Pakistan" required>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Payment Method</h3>
                            <div class="payment-options">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="card" checked>
                                    <span>Credit/Debit Card</span>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="bank_transfer">
                                    <span>Bank Transfer</span>
                                </label>
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="cod">
                                    <span>Cash on Delivery</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-section">
                            <label class="checkbox-label">
                                <input type="checkbox" name="agree_terms" required>
                                I agree to the Terms & Conditions and Privacy Policy
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">Place Order</button>
                    </form>
                </div>

                <aside class="checkout-summary">
                    <h2>Order Review</h2>
                    
                    <div class="order-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <img src="../assets/images/products/<?= htmlspecialchars($item['image']) ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                     onerror="this.src='assets/images/placeholder.jpg'">
                                <div class="item-info">
                                    <h4><?= htmlspecialchars($item['name']) ?></h4>
                                    <p>Rs. <?= number_format($item['price'], 2) ?> x <?= $item['cart_quantity'] ?></p>
                                </div>
                                <span class="item-price">Rs. <?= number_format($item['subtotal'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>Rs. <?= number_format($total, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (15%):</span>
                        <span>Rs. <?= number_format($tax, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span><?= $shipping > 0 ? 'Rs. ' . number_format($shipping, 2) : 'FREE' ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>Rs. <?= number_format($grand_total, 2) ?></span>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/app.js"></script>
</body>
</html>
