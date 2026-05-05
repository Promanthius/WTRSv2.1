<?php
require 'includes/db.php';
$pdo->exec("ALTER TABLE users ADD COLUMN student_id VARCHAR(50) NULL AFTER role, ADD COLUMN course VARCHAR(150) NULL AFTER college, ADD COLUMN year_level VARCHAR(20) NULL AFTER course;");
echo "Done";
