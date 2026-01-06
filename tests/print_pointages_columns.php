<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$cols = $pdo->query('SHOW COLUMNS FROM pointages')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . '|' . $c['Type'] . "\n";
}
