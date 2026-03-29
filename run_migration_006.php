<?php
require_once __DIR__ . '/includes/bootstrap.php';

$migration = file_get_contents(__DIR__ . '/migrations/006_add_sale_id_to_warranties.sql');

try {
    $db = Database::getInstance();
    $statements = array_filter(array_map('trim', explode(';', $migration)), fn($s) => !empty($s));
    
    foreach ($statements as $statement) {
        $db->prepare($statement)->execute();
        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
    }
    
    echo "\n✓ Migration 006 applied successfully!\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}
?>
