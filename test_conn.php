<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Testing database connection...</h3>";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p style='color:green; font-weight:bold;'>Success: Connected to database successfully!</p>";
    
    // Perform a quick query
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $count = $stmt->fetchColumn();
    echo "<p>Found $count users in the database.</p>";
} catch (\Exception $e) {
    echo "<p style='color:red; font-weight:bold;'>Error: Could not connect to the database.</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
