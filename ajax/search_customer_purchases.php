<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Session::requireLogin();
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$loadAll = empty($query) || $query === '*';

try {
    $db = Database::getInstance();
    
    // ===== ONLINE ORDERS =====
    if ($loadAll) {
        // Get all online orders
        $onlineSql = "SELECT c.customer_id, c.name, c.phone, c.email,
                       o.order_id, DATE(o.created_at) as order_date, o.grand_total,
                       oi.product_id, p.product_name, p.brand, p.model, p.warranty_months,
                       oi.quantity, 'online' as purchase_type
                FROM customers c
                INNER JOIN orders o ON c.customer_id = o.customer_id AND o.status = 'completed'
                INNER JOIN order_items oi ON o.order_id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.product_id
                ORDER BY o.created_at DESC
                LIMIT 100";
        $stmt = $db->prepare($onlineSql);
        $stmt->execute([]);
        $onlineResults = $stmt->fetchAll();
    } else {
        // Search online orders by customer name or phone
        $onlineSql = "SELECT c.customer_id, c.name, c.phone, c.email,
                       o.order_id, DATE(o.created_at) as order_date, o.grand_total,
                       oi.product_id, p.product_name, p.brand, p.model, p.warranty_months,
                       oi.quantity, 'online' as purchase_type
                FROM customers c
                INNER JOIN orders o ON c.customer_id = o.customer_id AND o.status = 'completed'
                INNER JOIN order_items oi ON o.order_id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.product_id
                WHERE c.name LIKE ? OR c.phone LIKE ?
                ORDER BY o.created_at DESC
                LIMIT 100";
        $stmt = $db->prepare($onlineSql);
        $stmt->execute(["%{$query}%", "%{$query}%"]);
        $onlineResults = $stmt->fetchAll();
    }

    // ===== POS SALES =====
    if ($loadAll) {
        // Get all POS sales
        $posSql = "SELECT NULL as customer_id, s.customer_name as name, s.customer_phone as phone, NULL as email,
                   s.sale_id as order_id, DATE(s.created_at) as order_date, s.total_amount as grand_total,
                   si.product_id, p.product_name, p.brand, p.model, p.warranty_months,
                   si.quantity, 'pos' as purchase_type
            FROM sales s
            INNER JOIN sale_items si ON s.sale_id = si.sale_id
            LEFT JOIN products p ON si.product_id = p.product_id
            WHERE s.customer_name IS NOT NULL AND s.customer_name != ''
            ORDER BY s.created_at DESC
            LIMIT 100";
        $stmt = $db->prepare($posSql);
        $stmt->execute([]);
        $posResults = $stmt->fetchAll();
    } else {
        // Search POS sales by customer name or phone
        $posSql = "SELECT NULL as customer_id, s.customer_name as name, s.customer_phone as phone, NULL as email,
                   s.sale_id as order_id, DATE(s.created_at) as order_date, s.total_amount as grand_total,
                   si.product_id, p.product_name, p.brand, p.model, p.warranty_months,
                   si.quantity, 'pos' as purchase_type
            FROM sales s
            INNER JOIN sale_items si ON s.sale_id = si.sale_id
            LEFT JOIN products p ON si.product_id = p.product_id
            WHERE (s.customer_name LIKE ? OR s.customer_phone LIKE ?)
            ORDER BY s.created_at DESC
            LIMIT 100";
        $stmt = $db->prepare($posSql);
        $stmt->execute(["%{$query}%", "%{$query}%"]);
        $posResults = $stmt->fetchAll();
    }

    // Merge results
    $allResults = array_merge($onlineResults, $posResults);

    if (empty($allResults)) {
        echo json_encode(['results' => [], 'message' => 'No purchases found']);
        exit;
    }

    // Group by customer
    $customers = [];
    foreach ($allResults as $row) {
        $custKey = ($row['name'] ?? 'Unknown') . '|' . ($row['phone'] ?? '');
        
        if (!isset($customers[$custKey])) {
            $customers[$custKey] = [
                'customer_id' => $row['customer_id'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'orders' => []
            ];
        }
        
        if ($row['order_id']) {
            if (!isset($customers[$custKey]['orders'][$row['order_id']])) {
                $orderDate = strtotime($row['order_date']);
                $purchaseDate = date('Y-m-d', $orderDate);
                $warrantyMonths = (int)($row['warranty_months'] ?? 12);
                $expiryDate = date('Y-m-d', strtotime("+{$warrantyMonths} months", $orderDate));
                $isInWarranty = strtotime($expiryDate) > time();
                
                $customers[$custKey]['orders'][$row['order_id']] = [
                    'order_id' => $row['order_id'],
                    'order_type' => $row['purchase_type'],
                    'order_date' => $purchaseDate,
                    'expiry_date' => $expiryDate,
                    'in_warranty' => $isInWarranty,
                    'total_amount' => (float)$row['grand_total'],
                    'items' => []
                ];
            }
            
            if ($row['product_id']) {
                $customers[$custKey]['orders'][$row['order_id']]['items'][] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'brand' => $row['brand'],
                    'model' => $row['model'],
                    'quantity' => $row['quantity'],
                    'warranty_months' => (int)($row['warranty_months'] ?? 12)
                ];
            }
        }
    }

    // Convert to array format
    $formattedResults = array_values(array_map(function($c) {
        return [
            'customer_id' => $c['customer_id'],
            'name' => $c['name'],
            'phone' => $c['phone'],
            'email' => $c['email'],
            'orders' => array_values($c['orders'])
        ];
    }, $customers));

    echo json_encode([
        'results' => $formattedResults,
        'count' => count($formattedResults)
    ]);

} catch (Exception $e) {
    error_log('Customer search error: ' . $e->getMessage());
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage(), 'results' => []]);
    exit;
}
?>
