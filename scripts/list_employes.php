<?php
require __DIR__ . '/../db.php';
try {
    $s = $pdo->query('SELECT id,nom,prenom,email FROM employes LIMIT 20');
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true, 'rows'=>$rows], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
