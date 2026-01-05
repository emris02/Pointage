<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$r = $pdo->query('SHOW CREATE TABLE badge_tokens')->fetch(PDO::FETCH_ASSOC);
echo $r['Create Table'] . "\n";