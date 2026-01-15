<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json');

try{
    $type = $_GET['type'] ?? 'employe';
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) throw new InvalidArgumentException('id manquant');
    $id = (int)$_GET['id'];

    $date = date('Y-m-d');
    if ($type === 'admin'){
        $stmt = $pdo->prepare("SELECT type, DATE_FORMAT(date_heure, '%H:%i') as time, statut FROM pointages WHERE admin_id = ? AND DATE(date_heure) = ? ORDER BY date_heure DESC LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT type, DATE_FORMAT(date_heure, '%H:%i') as time, statut FROM pointages WHERE employe_id = ? AND DATE(date_heure) = ? ORDER BY date_heure DESC LIMIT 1");
    }
    $stmt->execute([$id, $date]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last) {
        echo json_encode(['success' => true, 'data' => ['last_type' => null, 'last_time' => null, 'status' => 'absent']]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => ['last_type' => $last['type'], 'last_time' => $last['time'], 'status' => $last['statut'] ?? 'présent']]);
} catch (Exception $e){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
