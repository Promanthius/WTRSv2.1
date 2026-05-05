<?php
require 'includes/db.php';
$stmt = $pdo->query('DESCRIBE users');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
