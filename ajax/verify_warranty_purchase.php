<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Session::requireSeller();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

try {
    $warranty_id = (int)($_POST['warranty_id'] ?? 0);
    $order_id = (int)($_POST['order_id'] ?? 0);

    if (!$warranty_id || !$order_id) {
        echo json_encode(['error' => 'Missing warranty_id or order_id']);
        exit;
    }

    $warrantyModel = new WarrantyModel();

    // Verify the purchase
    $result = $warrantyModel->verifyAndLinkPurchase($warranty_id, $order_id, Session::get('user_id'));

    if ($result) {
        // Get updated warranty info
        $warranty = $warrantyModel->findById($warranty_id);
        echo json_encode([
            'success' => true,
            'message' => 'Warranty verified successfully',
            'warranty' => [
                'purchase_date' => $warranty['purchase_date'],
                'warranty_expiry_date' => $warranty['warranty_expiry_date'],
                'verified_at' => $warranty['verified_at']
            ]
        ]);
    } else {
        echo json_encode(['error' => 'Failed to verify purchase']);
    }

} catch (Exception $e) {
    error_log('Warranty verification error: ' . $e->getMessage());
    echo json_encode(['error' => 'Verification failed: ' . $e->getMessage()]);
}
?>
