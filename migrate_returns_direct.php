<?php
// Direct database migration - no framework dependencies

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'sap_computers';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read migration file
    $sql = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '007_create_returns_table.sql');
    
    // Split into statements
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
    
    // Execute each statement
    foreach ($statements as $stmt) {
        $conn->exec($stmt);
        echo "[OK] " . substr($stmt, 0, 60) . "...\n";
    }
    
    // Verify table was created
    $result = $conn->query("SHOW TABLES LIKE 'returns'");
    if ($result->fetch()) {
        echo "\n[SUCCESS] Returns table created successfully!\n";
    }
    
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>
