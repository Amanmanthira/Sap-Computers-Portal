<?php
require_once __DIR__ . '/includes/bootstrap.php';

try {
    $db = Database::getInstance();
    
    // Check if returns table exists
    $result = $db->prepare("SHOW TABLES LIKE 'returns'")->execute();
    $tableExists = $result->fetch() !== false;
    
    if (!$tableExists) {
        echo "[MIGRATE] Creating returns table...\n";
        
        $migration = file_get_contents(__DIR__ . '/migrations/007_create_returns_table.sql');
        $statements = array_filter(array_map('trim', explode(';', $migration)), fn($s) => !empty($s));
        
        foreach ($statements as $stmt) {
            $db->prepare($stmt)->execute();
            echo "  ✓ Query executed\n";
        }
        
        echo "\n✓ Migration 007 applied successfully!\n";
    } else {
        echo "✓ Returns table already exists\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
