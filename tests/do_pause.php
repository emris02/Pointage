<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['id']) || !isset($data['type'])) throw new InvalidArgumentException('Paramètres manquants');
    $id = (int)$data['id'];
    $type = $data['type'];
    $minutes = isset($data['minutes']) ? (int)$data['minutes'] : 30;

    $now = date('Y-m-d H:i:s');
    if ($type === 'admin'){
        $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, admin_id, type, etat, statut, commentaire) VALUES (?, ?, 'pause_debut', 'pause', 'présent', ?)");
        $stmt->execute([$now, $id, "Pause test {$minutes} minutes"]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, etat, statut, commentaire) VALUES (?, ?, 'pause_debut', 'pause', 'présent', ?)");
        $stmt->execute([$now, $id, "Pause test {$minutes} minutes"]);
    }

    echo json_encode(['status'=>'success','message'=>'Pause enregistrée','minutes'=>$minutes,'timestamp'=>$now]);
} catch (Exception $e){ http_response_code(400); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
