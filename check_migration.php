<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    // Check if sale_id column exists
    $result = $db->query("SHOW COLUMNS FROM warranties WHERE Field='sale_id'");
    if ($result->fetch()) {
        echo "✓ sale_id column exists in warranties table<br>";
    } else {
        echo "✗ sale_id column NOT found - applying migration...<br>";
        
        // Apply migration
        $migration_sql = file_get_contents(__DIR__ . '/migrations/006_add_sale_id_to_warranties.sql');
        $statements = array_filter(array_map('trim', explode(';', $migration_sql)), fn($s) => !empty($s));
        
        foreach ($statements as $statement) {
            $db->exec($statement);
            echo "  ✓ " . substr($statement, 0, 60) . "...<br>";
        }
        
        echo "<br>✓ Migration applied!<br>";
    }
    
    // List all columns in warranties table
    echo "<br><strong>Warranties table structure:</strong><br>";
    $result = $db->query("SHOW COLUMNS FROM warranties");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
