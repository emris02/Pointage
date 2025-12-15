<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
if (!class_exists('Settings')) {
    echo "Settings model not found\n";
    exit(0);
}
$sm = new Settings($pdo);
$rows = $sm->getAll();
if (empty($rows)) {
    echo "No settings found\n";
    exit(0);
}
foreach ($rows as $r) {
    echo $r['cle'] . " => " . $r['valeur'] . "\n";
}
