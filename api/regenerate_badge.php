<?php
/**
 * API endpoint pour régénérer le badge d'un employé
 */
require_once '../src/config/bootstrap.php';
require_once '../src/services/AuthService.php';
require_once '../src/services/BadgeManager.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

header('Content-Type: application/json');

// Vérifier que l'utilisateur est admin
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$employeId = $input['employe_id'] ?? null;

if (!$employeId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID employé requis']);
    exit();
}

try {
    // Vérifier que l'employé existe
    $stmt = $pdo->prepare("SELECT id, prenom, nom FROM employes WHERE id = ?");
    $stmt->execute([$employeId]);
    $employe = $stmt->fetch();
    
    if (!$employe) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
        exit();
    }
    
    // Régénérer le badge
    $result = BadgeManager::regenerateToken($employeId, $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Badge régénéré avec succès',
        'data' => [
            'employe_id' => $employeId,
            'employe_nom' => $employe['prenom'] . ' ' . $employe['nom'],
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'generated_at' => $result['generated_at']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la régénération du badge: ' . $e->getMessage()
    ]);
}
?>
