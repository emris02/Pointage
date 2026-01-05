<?php
require __DIR__ . '/../db.php';
try {
    $s = $pdo->query("SELECT id,email,password FROM admins LIMIT 1");
    $r = $s->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'admin' => $r], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
