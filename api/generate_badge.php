<?php
// api/generate_badge.php
header('Content-Type: application/json');
require_once '../src/config/bootstrap.php';
require_once '../src/controllers/BadgeController.php';

$input = json_decode(file_get_contents('php://input'), true);
$employe_id = $input['employe_id'] ?? null;

if (!$employe_id || !is_numeric($employe_id)) {
    echo json_encode(['success' => false, 'message' => 'ID employé manquant ou invalide.']);
    exit;
}

try {
    $badgeController = new BadgeController($pdo);
    $badge = $badgeController->generateBadge($employe_id);
    if ($badge && !empty($badge['token'])) {
        echo json_encode(['success' => true, 'token' => $badge['token'], 'expires_at' => $badge['expires_at']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Impossible de générer le badge.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
