<?php
require __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $stmt = $pdo->query('DESCRIBE pointages');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
