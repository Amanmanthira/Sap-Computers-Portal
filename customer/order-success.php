<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;
$order = null;
$order_items = [];

if ($order_id && isset($_SESSION['customer_id'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        
        // Get order details
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
        $stmt->execute([$order_id, $_SESSION['customer_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Get order items
            $stmt = $pdo->prepare("
                SELECT oi.*, p.name, p.image FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Silently handle error
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - SAP Computers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container" style="padding: 60px 0; text-align: center;">
        <?php if ($order): ?>
            <div style="background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: 0 auto;">
                <div style="font-size: 60px; color: var(--success-color); margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h1 style="color: var(--dark-text); margin-bottom: 10px;">Order Confirmed!</h1>
                <p style="color: #6b7280; margin-bottom: 30px; font-size: 16px;">
                    Thank you for your order. We'll send you updates via email.
                </p>
                
                <div style="background: var(--light-bg); padding: 20px; border-radius: 8px; text-align: left; margin-bottom: 30px;">
                    <p><strong>Order ID:</strong> #<?= $order['id'] ?></p>
                    <p><strong>Date:</strong> <?= date('M d, Y', strtotime($order['created_at'])) ?></p>
                    <p><strong>Total Amount:</strong> Rs. <?= number_format($order['grand_total'], 2) ?></p>
                    <p><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></p>
                    <p><strong>Status:</strong> <span style="color: var(--warning-color);">Pending</span></p>
                </div>

                <div style="text-align: left;">
                    <h3>Order Items</h3>
                    <div style="margin-top: 15px;">
                        <?php foreach ($order_items as $item): ?>
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                                <span><?= htmlspecialchars($item['name']) ?> x <?= $item['quantity'] ?></span>
                                <span>Rs. <?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <a href="account.php?tab=orders" class="btn btn-primary">View My Orders</a>
                    <a href="shop.php" class="btn btn-outline" style="margin-left: 10px;">Continue Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <div style="background: white; padding: 40px; border-radius: 8px; max-width: 600px; margin: 0 auto;">
                <h1>Order Not Found</h1>
                <p>We couldn't find your order. Please check your email for confirmation.</p>
                <a href="index.php" class="btn btn-primary">Go Home</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
