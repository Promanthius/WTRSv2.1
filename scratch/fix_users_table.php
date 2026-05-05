<?php
require_once __DIR__ . '/../includes/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) AFTER experience");
    echo "Column profile_pic added successfully.\n";
} catch(Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}
