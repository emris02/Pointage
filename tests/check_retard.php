<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Provide id parameter, e.g. ?id=12']);
    exit();
}
try {
    $stmt = $pdo->prepare('SELECT * FROM retards WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        exit();
    }
    echo json_encode(['success' => true, 'retard' => $row]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
