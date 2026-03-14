<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Session::requireLogin();

header('Content-Type: application/json');

$q = Helper::sanitize($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$db = Database::getInstance();
$like = "%{$q}%";
$results = [];

// Search products
$stmt = $db->prepare("SELECT p.product_id, p.product_name, p.brand, p.model, c.category_name
    FROM products p LEFT JOIN categories c ON p.category_id=c.category_id
    WHERE p.product_name LIKE ? OR p.brand LIKE ? OR p.model LIKE ?
    LIMIT 5");
$stmt->execute([$like,$like,$like]);
foreach ($stmt->fetchAll() as $row) {
    $results[] = [
        'title'    => $row['product_name'],
        'subtitle' => $row['brand'] . ' — ' . $row['category_name'],
        'icon'     => 'bi-box-seam-fill',
        'url'      => '/sap-computers/products.php?search=' . urlencode($q),
    ];
}

// Search suppliers
if (Session::get('user_role') === 'seller') {
    $stmt = $db->prepare("SELECT supplier_id, supplier_name, contact_person FROM suppliers WHERE supplier_name LIKE ? OR contact_person LIKE ? LIMIT 3");
    $stmt->execute([$like,$like]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'title'    => $row['supplier_name'],
            'subtitle' => 'Supplier — ' . $row['contact_person'],
            'icon'     => 'bi-truck',
            'url'      => '/sap-computers/suppliers.php',
        ];
    }

    // Search GRN
    $stmt = $db->prepare("SELECT g.grn_id, g.grn_number, s.supplier_name FROM grn g JOIN suppliers s ON g.supplier_id=s.supplier_id WHERE g.grn_number LIKE ? OR s.supplier_name LIKE ? LIMIT 3");
    $stmt->execute([$like,$like]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'title'    => $row['grn_number'],
            'subtitle' => 'GRN — ' . $row['supplier_name'],
            'icon'     => 'bi-receipt-cutoff',
            'url'      => '/sap-computers/grn.php?view=' . $row['grn_id'],
        ];
    }
}

echo json_encode(['results' => array_slice($results, 0, 8)]);
