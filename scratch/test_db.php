<?php
require_once __DIR__ . '/includes/db.php';
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "Connection successful. User count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
