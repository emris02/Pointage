<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json');

try{
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    if (empty($data['id']) || !isset($data['type']) || empty($data['reason'])) throw new InvalidArgumentException('Paramètres manquants');
    $id = (int)$data['id'];
    $type = $data['type'];
    $reason = trim($data['reason']);

    // For test purpose we will insert a depart entry and mark type_justification
    $now = date('Y-m-d H:i:s');

    if ($type === 'admin'){
        $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, admin_id, type, etat, statut, commentaire) VALUES (?, ?, 'depart', 'present', 'présent', ?)");
        $stmt->execute([$now, $id, $reason]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, etat, statut, commentaire) VALUES (?, ?, 'depart', 'present', 'présent', ?)");
        $stmt->execute([$now, $id, $reason]);
    }

    // For test purpose, also create a retards row to simulate a pending justification
    $pointId = $pdo->lastInsertId();
    $stmt2 = $pdo->prepare("INSERT INTO retards (pointage_id, employe_id, raison, details, statut, date_soumission) VALUES (?, ?, 'depart_anticipé', ?, 'en_attente', NOW())");
    $stmt2->execute([$pointId, $type === 'admin' ? null : $id, $reason]);

    echo json_encode(['status'=>'success','message'=>'Départ anticipé enregistré','timestamp'=>$now]);
} catch (Exception $e){ http_response_code(400); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
