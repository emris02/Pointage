<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

$controller = new EmployeController($pdo);
$res = $controller->getStats(21);
echo json_encode($res, JSON_PRETTY_PRINT);
