<?php
require __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $adminId = $argv[1] ?? 3;
    $stmt = $pdo->prepare('SELECT id, token_hash, token, status, created_at, expires_at, revoked_at FROM badge_tokens WHERE admin_id = ? ORDER BY created_at DESC');
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
