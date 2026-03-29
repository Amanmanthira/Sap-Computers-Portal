<?php
// TEMPORARY MIGRATION RUNNER - DELETE AFTER USE
// This file is meant to be accessed via browser to apply migrations

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_GET['apply'])) {
    echo "Migration runner ready. Access with ?apply=006 to run migration 006.";
    exit;
}

$migration_num = $_GET['apply'];
$migration_file = __DIR__ . "/migrations/{$migration_num}_*.sql";
$files = glob($migration_file);

if (empty($files)) {
    echo "Migration file not found for: {$migration_num}";
    exit;
}

$migration_file = $files[0];
$sql = file_get_contents($migration_file);

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
    
    foreach ($statements as $statement) {
        echo "Executing: " . substr($statement, 0, 80) . "...<br>";
        $db->exec($statement);
    }
    
    echo "<br><strong style='color:green;'>✓ Migration applied successfully!</strong>";
} catch (Exception $e) {
    echo "<strong style='color:red;'>✗ Error: " . $e->getMessage() . "</strong>";
}
?>
