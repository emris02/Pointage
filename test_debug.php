<?php
// Quick test to load bootstrap and instantiate AdminService to detect class re-declaration
require_once __DIR__ . '/src/config/bootstrap.php';
require_once __DIR__ . '/src/services/AdminService.php';

try {
    $admin = new AdminService($pdo);
    echo "OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
