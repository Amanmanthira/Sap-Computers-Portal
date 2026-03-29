<?php
// Direct migration application - run via CLI
require_once __DIR__ . '/config/config.php';

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // Check if sale_id column exists
    $check = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='warranties' AND COLUMN_NAME='sale_id'");
    if (!$check->fetch()) {
        echo "[MIGRATE] sale_id column missing, adding it...\n";
        
        // Add sale_id column
        $db->exec("ALTER TABLE warranties ADD COLUMN sale_id INT NULL AFTER order_id");
        echo "[OK] Added sale_id column\n";
        
        // Add foreign key constraint
        $db->exec("ALTER TABLE warranties ADD CONSTRAINT warranties_ibfk_sale FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE SET NULL");
        echo "[OK] Added foreign key constraint for sale_id\n";
        
        // Make order_id nullable
        $db->exec("ALTER TABLE warranties MODIFY COLUMN order_id INT NULL");
        echo "[OK] Made order_id nullable\n";
    } else {
        echo "[INFO] sale_id column already exists\n";
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS=1");
    
    // Show final structure
    $result = $db->query("SHOW COLUMNS FROM warranties");
    echo "\n[STRUCTURE] Warranties table columns:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")" . ($row['Null'] === 'YES' ? " [NULL]" : "") . "\n";
    }
    
    echo "\n[SUCCESS] Migration complete!\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>
