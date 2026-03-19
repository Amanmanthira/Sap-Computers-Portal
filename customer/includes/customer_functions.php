<?php
/**
 * Customer Website Functions
 */

function formatPrice($price) {
    return 'Rs. ' . number_format($price, 2);
}

function getCartTotal() {
    $total = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            // This would need to be updated with actual product prices
            $total += $quantity;
        }
    }
    return $total;
}

function addToCart($product_id, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

function removeFromCart($product_id) {
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

function isInWishlist($product_id, $customer_id) {
    try {
        require_once '../config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$customer_id, $product_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function addToWishlist($product_id, $customer_id) {
    try {
        require_once '../config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        
        // Check if already in wishlist
        if (isInWishlist($product_id, $customer_id)) {
            return false;
        }
        
        $stmt = $pdo->prepare("INSERT INTO wishlists (customer_id, product_id) VALUES (?, ?)");
        return $stmt->execute([$customer_id, $product_id]);
    } catch (Exception $e) {
        return false;
    }
}

function removeFromWishlist($product_id, $customer_id) {
    try {
        require_once '../config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?");
        return $stmt->execute([$customer_id, $product_id]);
    } catch (Exception $e) {
        return false;
    }
}
?>
