<?php
// API: Confirmer un départ anticipé
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../src/config/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur : configuration introuvable']);
    exit;
}

@date_default_timezone_set('Europe/Paris');

try{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Méthode non autorisée');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = $input['type'] ?? 'employe';
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $reason = trim($input['reason'] ?? '');

    if (!$id) {
        http_response_code(400);
        throw new Exception('ID manquant');
    }
    if (empty($reason)) {
        http_response_code(400);
        throw new Exception('Motif requis');
    }
    if (!in_array($type, ['employe','admin'], true)) {
        http_response_code(400);
        throw new Exception('Type invalide');
    }

    $now = date('Y-m-d H:i:s');
    $current_date = date('Y-m-d');
    $idField = $type === 'admin' ? 'admin_id' : 'employe_id';

    // Vérifier qu'une arrivée existe aujourd'hui
    $stmt = $pdo->prepare("SELECT date_heure FROM pointages WHERE $idField = ? AND DATE(date_heure) = ? AND type = 'arrivee' ORDER BY date_heure DESC LIMIT 1");
    $stmt->execute([$id, $current_date]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$last) {
        http_response_code(400);
        throw new Exception("Départ non autorisé sans arrivée préalable");
    }

    $start = new DateTime($last['date_heure']);
    $end = new DateTime($now);
    $diff = $start->diff($end);
    $seconds = ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
    $pause = $seconds > 4 * 3600 ? 3600 : 0;
    $seconds -= $pause;
    $temps_total = gmdate('H:i:s', $seconds);

    // Insérer le pointage de départ (utiliser colonnes présentes)
    if ($type === 'admin'){
        $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, admin_id, type, temps_total, etat, commentaire) VALUES (?, ?, 'depart', ?, 'présent', ?)");
        $stmt->execute([$now, $id, $temps_total, $reason]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, temps_total, etat, commentaire) VALUES (?, ?, 'depart', ?, 'présent', ?)");
        $stmt->execute([$now, $id, $temps_total, $reason]);
    }

    $pointId = $pdo->lastInsertId();

    echo json_encode(['status'=>'success','message'=>'Départ anticipé enregistré','temps_total'=>$temps_total,'timestamp'=>$now,'pointage_id'=>$pointId]);
} catch (Throwable $e) {
    $code = http_response_code() ?: 500;
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
