<?php
/**
 * Update Profile API
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
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $dob = isset($_POST['dob']) ? trim($_POST['dob']) : null;

    if ($name && $email && $phone) {
        $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, dob = ? WHERE id = ?");
        $result = $stmt->execute([$name, $email, $phone, $dob, $customer_id]);

        if ($result) {
            $_SESSION['success'] = 'Profile updated successfully';
            header('Location: ../account.php?tab=profile');
        } else {
            $_SESSION['error'] = 'Error updating profile';
            header('Location: ../account.php?tab=profile');
        }
    } else {
        $_SESSION['error'] = 'Please fill in all required fields';
        header('Location: ../account.php?tab=profile');
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error';
    header('Location: ../account.php?tab=profile');
}
?>
