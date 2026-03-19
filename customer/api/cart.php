<?php
/**
 * Cart API - Handle add, remove, update cart operations
 */

session_start();
header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : null;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Initialize cart session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {
    case 'add_to_cart':
        if ($product_id && $quantity > 0) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = $quantity;
            }
            echo json_encode(['success' => true, 'message' => 'Product added to cart']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
        }
        break;

    case 'remove_from_cart':
        if ($product_id && isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            echo json_encode(['success' => true, 'message' => 'Product removed from cart']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not in cart']);
        }
        break;

    case 'update_quantity':
        if ($product_id && $quantity > 0) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] = $quantity;
                echo json_encode(['success' => true, 'message' => 'Cart updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not in cart']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        }
        break;

    case 'clear_cart':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'message' => 'Cart cleared']);
        break;

    case 'get_cart_count':
        $count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
