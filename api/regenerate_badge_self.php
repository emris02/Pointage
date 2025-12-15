<?php
/**
 * API: Régénération de badge par l'employé connecté (self-service)
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

header('Content-Type: application/json');

// Vérifier que c'est bien un employé connecté (pas admin-only)
if (!isset($_SESSION['employe_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès réservé aux employés connectés']);
    exit();
}

$employeId = (int)$_SESSION['employe_id'];

try {
    $result = BadgeManager::regenerateToken($employeId, $pdo);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>


