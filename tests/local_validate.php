<?php
// tests/local_validate.php - quick local validation script
// Usage: php tests/local_validate.php

require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Pointage.php';
require_once __DIR__ . '/../src/models/Employe.php';
require_once __DIR__ . '/../src/controllers/EmployeController.php';
require_once __DIR__ . '/../src/controllers/PointageController.php';
require_once __DIR__ . '/../src/controllers/EmployeController.php';

try {
    // Instantiate models and controllers
    $pointage = new Pointage($pdo);
    $employe = new Employe($pdo);
    $empController = new EmployeController($pdo);
    $pointageController = new PointageController($pdo);

    echo "OK: Instantiated Pointage, Employe, EmployeController, PointageController\n";
    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
