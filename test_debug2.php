<?php
// Debug script to inspect class and included files around AdminService instantiation
require_once __DIR__ . '/src/config/bootstrap.php';

echo "Before instantiation:\n";
echo "class Pointage namespaced exists? " . (class_exists('PointagePro\\Models\\Pointage', false) ? 'yes' : 'no') . "\n";
echo "class Pointage global exists? " . (class_exists('Pointage', false) ? 'yes' : 'no') . "\n";
echo "Included files initially:\n";
print_r(get_included_files());

require_once __DIR__ . '/src/services/AdminService.php';

echo "\nInstantiating AdminService...\n";
$before_files = get_included_files();
try {
    $admin = new AdminService($pdo);
    echo "Instantiated OK\n";
} catch (Throwable $e) {
    echo "Caught Throwable: " . $e->getMessage() . "\n";
}

echo "\nIncluded files after attempt:\n";
print_r(get_included_files());

echo "\nDeclared classes containing 'Pointage':\n";
$decl = array_filter(get_declared_classes(), function($c){ return stripos($c,'Pointage')!==false; });
print_r($decl);
