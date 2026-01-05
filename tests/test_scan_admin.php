<?php
require __DIR__ . '/../src/config/bootstrap.php';
require __DIR__ . '/../src/services/BadgeManager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query('SELECT id, nom, prenom FROM admins LIMIT 1');
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        echo json_encode(['error' => 'NO_ADMIN']);
        exit(2);
    }

    $res = BadgeManager::regenerateTokenForAdmin((int)$admin['id'], $pdo);
    echo json_encode(['admin' => $admin, 'token' => $res['token']]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
