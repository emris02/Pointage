<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../BadgeManager.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
        throw new InvalidArgumentException('ID manquant ou invalide');
    }
    $id = (int)$_GET['id'];
    $type = $_GET['type'] ?? 'employe';

    if ($type === 'admin' && method_exists('BadgeManager','regenerateTokenForAdmin')) {
        $res = BadgeManager::regenerateTokenForAdmin($id, $pdo);
    } else {
        $res = BadgeManager::regenerateToken($id, $pdo);
    }

    if (!$res || !isset($res['token']) && !isset($res['token_hash'])) {
        throw new RuntimeException('Impossible de générer le token');
    }

    $token = $res['token'] ?? $res['token_hash'];
    $expires_at = $res['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+5 minutes')); // default 5 min

    echo json_encode(['status'=>'success','token'=>$token,'expires_at'=>$expires_at]);

} catch (Exception $e){
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
