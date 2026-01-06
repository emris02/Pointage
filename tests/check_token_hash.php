<?php
require __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $hash = $argv[1] ?? '7d8f96f0b9b6d845f8a212ca5dbf20898251964c26069946c3005afb6f58b087';
    $stmt = $pdo->prepare('SELECT * FROM badge_tokens WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['hash' => $hash, 'row' => $row], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
