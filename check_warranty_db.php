<?php
require_once __DIR__ . '/config/config.php';

$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
    DB_USER,
    DB_PASS
);

// First, let's check if sale_id exists
$result = $db->query("SHOW COLUMNS FROM warranties WHERE Field='sale_id'");
$sale_id_exists = (bool)$result->fetch();

// Check warranty_expiry_date for NULL values
$result = $db->query("SELECT COUNT(*) as null_count FROM warranties WHERE warranty_expiry_date IS NULL");
$null_expiry = $result->fetch(PDO::FETCH_ASSOC);

// Get the latest warranty to inspect
$result = $db->query("SELECT warranty_id, warranty_number, order_id, purchase_date, warranty_expiry_date FROM warranties ORDER BY warranty_id DESC LIMIT 1");
$latest = $result->fetch(PDO::FETCH_ASSOC);

// Output as JSON for reliability
$output = [
    'sale_id_column_exists' => $sale_id_exists,
    'null_expiry_count' => (int)$null_expiry['null_count'],
    'latest_warranty' => $latest,
    'migration_needed' => !$sale_id_exists
];

file_put_contents('/tmp/warranty_check.json', json_encode($output, JSON_PRETTY_PRINT));
echo json_encode($output);
?>
