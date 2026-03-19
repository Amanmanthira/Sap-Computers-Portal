<?php
/**
 * Update Address API
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
    $street_address = isset($_POST['street_address']) ? trim($_POST['street_address']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';

    if ($street_address && $city && $postal_code) {
        $stmt = $pdo->prepare("UPDATE customers SET address = ?, city = ?, postal_code = ?, country = ? WHERE id = ?");
        $result = $stmt->execute([$street_address, $city, $postal_code, $country, $customer_id]);

        if ($result) {
            $_SESSION['success'] = 'Address updated successfully';
        } else {
            $_SESSION['error'] = 'Error updating address';
        }
    } else {
        $_SESSION['error'] = 'Please fill in all required fields';
    }

    header('Location: ../account.php?tab=address');

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error';
    header('Location: ../account.php?tab=address');
}
?>
