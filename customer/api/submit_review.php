<?php
/**
 * Submit Review API
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    $customer_id = $_SESSION['customer_id'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Validate inputs
    if (!$product_id || !$rating || !$title || !$comment) {
        $_SESSION['error'] = 'Please fill in all fields';
        header('Location: ../product.php?id=' . $product_id);
        exit;
    }

    // Check if user already reviewed this product
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?");
    $stmt->execute([$product_id, $customer_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'You have already reviewed this product';
        header('Location: ../product.php?id=' . $product_id);
        exit;
    }

    // Get customer name
    $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    $customer_name = $customer['name'];

    // Insert review
    $stmt = $pdo->prepare("
        INSERT INTO reviews (product_id, customer_id, customer_name, rating, title, comment, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $result = $stmt->execute([$product_id, $customer_id, $customer_name, $rating, $title, $comment]);

    if ($result) {
        $_SESSION['success'] = 'Review submitted successfully';
    } else {
        $_SESSION['error'] = 'Error submitting review';
    }

    header('Location: ../product.php?id=' . $product_id);

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error';
    header('Location: ../product.php');
}
?>
