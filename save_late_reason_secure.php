<?php
// save_late_reason_secure.php - hardened: link justification to a pointage, verify session identity, prevent duplicates
require_once 'db.php';
session_start();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Mauvaise méthode HTTP');

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) throw new Exception('Payload JSON attendu');

    $required = ['pointage_id', 'reason'];
    foreach ($required as $f) {
        if (empty($input[$f])) throw new Exception("Champ $f manquant");
    }

    $pointageId = (int)$input['pointage_id'];
    $reason = trim($input['reason']);
    $comment = trim($input['comment'] ?? '');
    $typeJustif = $input['type'] ?? null; // optional

    // Load pointage
    $stmt = $pdo->prepare('SELECT * FROM pointages WHERE id = ? LIMIT 1');
    $stmt->execute([$pointageId]);
    $pointage = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pointage) throw new Exception('Pointage introuvable');

    // Identity check: if an employe is logged, they can only justify their own pointage
    $sessionEmployeId = $_SESSION['employe_id'] ?? null;
    $sessionAdminId = $_SESSION['admin_id'] ?? null;

    if ($sessionEmployeId) {
        if ((int)$sessionEmployeId !== (int)$pointage['employe_id']) {
            throw new Exception('Vous ne pouvez pas justifier le pointage d\'un autre employé');
        }
        $justifiePar = $sessionEmployeId;
    } elseif ($sessionAdminId) {
        // Admin can justify on behalf; record admin id
        $justifiePar = $sessionAdminId;
    } else {
        throw new Exception('Authentification requise');
    }

    // Check if justification already exists / was applied
    if (!empty($pointage['est_justifie']) || (isset($pointage['retard_justifie']) && $pointage['retard_justifie'] === 'oui')) {
        throw new Exception('Ce pointage a déjà une justification enregistrée');
    }

    // Only allow justification when pointage etat indicates anomaly, or allow admin override
    $etat = $pointage['etat'] ?? 'normal';
    $allowedStates = ['retard', 'depart_anticipé'];
    if (!in_array($etat, $allowedStates) && !$sessionAdminId) {
        throw new Exception('Aucune justification requise pour ce pointage');
    }

    // Persist justification by updating pointages (audit fields available)
    $update = $pdo->prepare(
        'UPDATE pointages SET est_justifie = 1, retard_justifie = ?, commentaire = ?, justifie_par = ?, date_justification = NOW(), type_justification = ? WHERE id = ?'
    );
    $retardJustifie = 'oui';
    $update->execute([$retardJustifie, $reason . ( $comment ? "\n" . $comment : '' ), $justifiePar, $typeJustif, $pointageId]);

    echo json_encode(['success' => true, 'message' => 'Justification enregistrée', 'pointage_id' => $pointageId]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
