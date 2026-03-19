<?php
/**
 * Change Password API
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
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validate passwords match
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match';
        header('Location: ../account.php?tab=profile');
        exit;
    }

    // Get current password hash
    $stmt = $pdo->prepare("SELECT password FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    // Verify current password
    if (!password_verify($current_password, $customer['password'])) {
        $_SESSION['error'] = 'Current password is incorrect';
        header('Location: ../account.php?tab=profile');
        exit;
    }

    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE customers SET password = ? WHERE id = ?");
    $result = $stmt->execute([$hashed_password, $customer_id]);

    if ($result) {
        $_SESSION['success'] = 'Password changed successfully';
    } else {
        $_SESSION['error'] = 'Error changing password';
    }

    header('Location: ../account.php?tab=profile');

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error';
    header('Location: ../account.php?tab=profile');
}
?>
