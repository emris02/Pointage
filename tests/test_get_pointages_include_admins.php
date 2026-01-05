<?php
require __DIR__ . '/../src/config/bootstrap.php';

try {
    $stmt = $pdo->prepare("SELECT p.id, COALESCE(p.date_heure, p.date_pointage) AS date_heure, p.type, p.admin_id, a.nom, a.prenom FROM pointages p LEFT JOIN admins a ON p.admin_id = a.id WHERE DATE(COALESCE(p.date_heure, p.date_pointage)) = DATE(NOW()) AND p.admin_id IS NOT NULL LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'row' => $row], JSON_UNESCAPED_UNICODE);
        exit(0);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucun pointage admin trouvé aujourd\'hui'], JSON_UNESCAPED_UNICODE);
        exit(2);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
