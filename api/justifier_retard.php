<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../src/config/bootstrap.php';

    // Accept multipart/form-data or application/x-www-form-urlencoded
    $employeId = $_POST['employe_id'] ?? null;
    $pointageId = $_POST['pointage_id'] ?? null;
    $date = $_POST['date'] ?? null;
    $raison = trim($_POST['raison'] ?? '');
    $details = trim($_POST['details'] ?? '');

    if (empty($pointageId) || empty($employeId) || empty($raison)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données manquantes (pointage_id, employe_id, raison)']);
        exit();
    }

    // Vérifier que le pointage existe et appartient bien à l'utilisateur
    $stmt = $pdo->prepare('SELECT id, employe_id, admin_id, type, date_heure FROM pointages WHERE id = :id');
    $stmt->execute([':id' => $pointageId]);
    $pointage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pointage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pointage introuvable']);
        exit();
    }

    $belongs = false;
    if (!empty($pointage['employe_id']) && intval($pointage['employe_id']) === intval($employeId)) $belongs = true;
    if (!empty($pointage['admin_id']) && intval($pointage['admin_id']) === intval($employeId)) $belongs = true;

    if (!$belongs) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas autorisé à justifier ce pointage']);
        exit();
    }

    // Gérer fichier joint (optionnel)
    $filePath = null;
    if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/justifications/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
        $dest = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
            $filePath = 'uploads/justifications/' . $fileName;
        }
    }

    // Insérer une ligne dans la table retards (statut en_attente)
    $stmt = $pdo->prepare('INSERT INTO retards (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$pointageId, $employeId, $raison, $details, $filePath, 'en_attente']);

    // Marquer le pointage comme ayant une justification en attente
    $stmtUp = $pdo->prepare('UPDATE pointages SET est_justifie = 0, commentaire = COALESCE(commentaire, ?) WHERE id = ?');
    $com = "Justification soumise: {$raison}";
    $stmtUp->execute([$com, $pointageId]);

    echo json_encode(['success' => true, 'message' => 'Justification soumise et en attente de validation']);

} catch (Throwable $e) {
    error_log('justifier_retard.php exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la soumission de la justification']);
}
