<?php
// process_badge.php
// Endpoint pour valider un badge scanné via QR code
require_once __DIR__ . '/../src/services/BadgeManager.php';
require_once __DIR__ . '/../src/config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aucun badge fourni.']);
    exit;
}

try {
    $result = BadgeManager::verifyToken($token, $pdo);
    if ($result['status'] === 'success') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Badge valide.',
            'employe_id' => $result['employe_id'],
            'expires_at' => $result['expires_at'],
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => $result['message'] ?? 'Le QR code scanné n\'est pas un badge valide.',
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur serveur: ' . $e->getMessage(),
    ]);
}
