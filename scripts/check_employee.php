<?php
require __DIR__ . '/../db.php';
try {
    $s = $pdo->prepare('SELECT id, nom, prenom FROM employes WHERE id = ?');
    $s->execute([2]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'row' => $r], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
