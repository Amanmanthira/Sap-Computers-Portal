<?php
/**
 * Wishlist API
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : null;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$customer_id = $_SESSION['customer_id'];

if (!$action || !$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

    if ($action === 'toggle_wishlist') {
        // Check if already in wishlist
        $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$customer_id, $product_id]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Remove from wishlist
            $stmt = $pdo->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$customer_id, $product_id]);
            echo json_encode(['success' => true, 'added' => false]);
        } else {
            // Add to wishlist
            $stmt = $pdo->prepare("INSERT INTO wishlists (customer_id, product_id) VALUES (?, ?)");
            $stmt->execute([$customer_id, $product_id]);
            echo json_encode(['success' => true, 'added' => true]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
