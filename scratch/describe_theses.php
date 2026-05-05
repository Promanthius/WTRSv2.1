<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("DESCRIBE theses");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
