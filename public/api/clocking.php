<?php
/**
 * API de Pointage
 * Système de Pointage Professionnel v2.0
 */

require_once '../../config/database.php';
require_once '../../src/Services/PointageService.php';
require_once '../../src/Core/Security/TokenManager.php';

use PointagePro\Services\PointageService;
use PointagePro\Core\Security\TokenManager;

// Configuration CORS et headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérification de la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Méthode non autorisée',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

try {
    // Initialisation des services
    $db = DatabaseConfig::getInstance()->getConnection();
    $tokenManager = new TokenManager(
        DatabaseConfig::SECRET_KEY,
        DatabaseConfig::JWT_SECRET
    );
    $pointageService = new PointageService($db, $tokenManager);
    
    // Récupération des données de la requête
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Données JSON invalides');
    }
    
    // Validation des données requises
    if (empty($input['badge_token'])) {
        throw new Exception('Token de badge requis');
    }
    
    // Contexte de la requête
    $context = [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'device_info' => $input['device_info'] ?? null,
        'latitude' => $input['latitude'] ?? null,
        'longitude' => $input['longitude'] ?? null,
        'timestamp' => $input['timestamp'] ?? date('Y-m-d H:i:s')
    ];
    
    // Traitement du pointage
    $result = $pointageService->processBadgeClocking($input['badge_token'], $context);
    
    // Log de l'opération
    $logLevel = $result['success'] ? 'INFO' : 'WARNING';
    $logMessage = $result['success'] 
        ? "Pointage réussi pour l'employé {$result['employee']['id']}"
        : "Échec de pointage: {$result['error']}";
    
    logSystemEvent($db, $logLevel, $logMessage, [
        'employe_id' => $result['employee']['id'] ?? null,
        'type' => $result['type'] ?? null,
        'ip_address' => $context['ip_address'],
        'user_agent' => $context['user_agent']
    ]);
    
    // Réponse
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log de l'erreur
    error_log("Erreur API Pointage: " . $e->getMessage());
    
    if (isset($db)) {
        logSystemEvent($db, 'ERROR', 'Erreur API Pointage: ' . $e->getMessage(), [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'input' => $input ?? null
        ]);
    }
    
    // Réponse d'erreur
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur interne du serveur',
        'code' => 'INTERNAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Enregistre un événement système
 */
function logSystemEvent(PDO $db, string $level, string $message, array $context = []): void {
    try {
        $sql = "INSERT INTO system_logs (level, message, context, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $level,
            $message,
            json_encode($context),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement du log: " . $e->getMessage());
    }
}