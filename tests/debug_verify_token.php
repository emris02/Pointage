<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';

$token = 'ADM8|e4330b0898ecf5ac0acfa5f76f7d73db|1766096456|3|c4e49f1cdd4ac2c770e79fdeaa2057ac4c61620af37475c0452e3c3985fe3470';
try {
    $res = BadgeManager::verifyToken($token, $pdo);
    echo "OK\n";
    print_r(array_keys($res));
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
