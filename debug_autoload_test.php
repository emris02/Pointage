<?php
// Register a debug autoloader to see what classes are autoloaded during include
spl_autoload_register(function($class){ echo "DEBUG AUTOLOAD: $class\n"; });

require_once __DIR__ . '/src/models/Pointage.php';

echo "After require_once Pointage.php\n";

$decl = array_filter(get_declared_classes(), function($c){ return stripos($c,'Pointage')!==false; });
print_r($decl);

print_r(get_included_files());
