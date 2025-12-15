<?php
/**
 * API endpoint pour supprimer un employé
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

header('Content-Type: application/json');

// Vérification de l'authentification admin
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupération des données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID employé manquant']);
    exit();
}

$employeId = (int)$input['id'];

try {
    // Utilisation du service centralisé
    $adminService = new AdminService($pdo);
    $result = $adminService->supprimerEmploye($employeId);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
