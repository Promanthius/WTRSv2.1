<?php
require 'includes/db.php';
$pdo->exec("ALTER TABLE users
  ADD COLUMN IF NOT EXISTS student_id VARCHAR(50) NULL AFTER role,
  ADD COLUMN IF NOT EXISTS course VARCHAR(150) NULL AFTER college,
  ADD COLUMN IF NOT EXISTS year_level VARCHAR(20) NULL AFTER course;");
echo "Done";
