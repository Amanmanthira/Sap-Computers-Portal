<?php
require_once __DIR__ . '/config/config.php';

$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
    DB_USER,
    DB_PASS
);

// Check current structure
$result = $db->query("SHOW COLUMNS FROM warranties");
$columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);

echo "<strong>Current warranties columns:</strong><br>";
foreach ($columns as $col) {
    echo "  • " . $col . "<br>";
}

if (in_array('sale_id', $columns)) {
    echo "<br>✓ sale_id column already exists<br>";
} else {
    echo "<br>✗ sale_id column missing - applying migration...<br><br>";
    
    try {
        // Read migration file
        $sql = file_get_contents(__DIR__ . '/migrations/006_add_sale_id_to_warranties.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
        
        $db->beginTransaction();
        
        foreach ($statements as $statement) {
            echo "Executing: " . substr($statement, 0, 80) . "...<br>";
            $db->exec($statement);
        }
        
        $db->commit();
        echo "<br>✓ Migration applied successfully!<br><br>";
        
        // Show updated structure
        $result = $db->query("SHOW COLUMNS FROM warranties");
        echo "<strong>Updated warranties columns:</strong><br>";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  • " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        }
        
    } catch (Exception $e) {
        echo "<br>✗ Error: " . $e->getMessage() . "<br>";
        $db->rollBack();
    }
}
?>
