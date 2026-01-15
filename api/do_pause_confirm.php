<?php
// API: Confirmer une pause depuis un terminal de scan
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../src/config/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur : configuration introuvable']);
    exit;
}


try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Méthode non autorisée');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = $input['type'] ?? 'employe';
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $minutes = isset($input['minutes']) ? (int)$input['minutes'] : 30;
    $justification = trim($input['justification'] ?? '');

    if (!$id) {
        http_response_code(400);
        throw new Exception('ID manquant');
    }
    if (!in_array($type, ['employe', 'admin'], true)) {
        http_response_code(400);
        throw new Exception('Type invalide');
    }

    $now = date('Y-m-d H:i:s');

    // --- 1️⃣ Insérer la pause dans la table `pauses` ---
    $type_pause = $input['pause_type'] ?? 'autre';
    $empId = $type === 'employe' ? $id : ($input['employe_id'] ?? $id);
    $adminId = $type === 'admin' ? $id : ($input['admin_id'] ?? null);

    if (empty($empId)) {
        http_response_code(400);
        throw new Exception('Employé introuvable pour la pause');
    }

    $stmtPause = $pdo->prepare(
        "INSERT INTO pauses (type_pause, date_debut, justification, employe_id, admin_id, pointage_id)
         VALUES (:type_pause, :date_debut, :justification, :emp_id, :admin_id, 0)"
    );

    $stmtPause->execute([
        ':type_pause' => in_array($type_pause, ['dejeuner','course','fatigue','malaise','commission','autre']) ? $type_pause : 'autre',
        ':date_debut' => $now,
        ':justification' => $justification,
        ':emp_id' => $empId,
        ':admin_id' => $adminId
    ]);

    $pauseId = $pdo->lastInsertId();

    // --- 2️⃣ Créer le pointage associé pour le dashboard (pause_debut) ---
    $comment = "Pause confirmée ({$minutes}min)" . ($justification ? " - $justification" : '');

    $stmtPoint = $pdo->prepare(
        "INSERT INTO pointages (date_heure, employe_id, admin_id, type, etat, statut, commentaire, pause_debut)
         VALUES (:date_heure, :emp_id, :admin_id, 'pause_debut', 'pause', 'présent', :commentaire, :pause_debut)"
    );

    $stmtPoint->execute([
        ':date_heure' => $now,
        ':emp_id' => $empId,
        ':admin_id' => $adminId,
        ':commentaire' => $comment,
        ':pause_debut' => $now
    ]);

    $pointageId = $pdo->lastInsertId();

    // Lier la pause au pointage
    $stmtUpdatePause = $pdo->prepare("UPDATE pauses SET pointage_id = :pointage_id WHERE id = :id");
    $stmtUpdatePause->execute([':pointage_id' => $pointageId, ':id' => $pauseId]);

    // --- 3️⃣ Enregistrer une demande de retard/justification si fournie (table `retards`) ---
    if (!empty($justification)) {
        $stmtRetard = $pdo->prepare(
            "INSERT INTO retards (pointage_id, employe_id, raison, details, statut, date_soumission, admin_traitant_id)
             VALUES (:pointage_id, :emp_id, :raison, :details, 'en_attente', NOW(), :admin_id)"
        );
        $stmtRetard->execute([
            ':pointage_id' => $pointageId,
            ':emp_id' => $empId,
            ':raison' => 'pause',
            ':details' => $justification,
            ':admin_id' => $adminId
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Pause enregistrée',
        'pause_id' => $pauseId,
        'pointage_id' => $pointageId,
        'minutes' => $minutes,
        'timestamp' => $now
    ]);

} catch (Throwable $e) {
    $code = http_response_code() ?: 500;
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
