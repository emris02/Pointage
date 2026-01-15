<?php
// Middleware simple pour valider la présence d'une clé API terminal
require_once __DIR__ . '/../config/constants.php';

function require_terminal_api_key(): void {
    $headerKey = null;
    // Support both HTTP_X_API_KEY and Apache style
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $headerKey = $_SERVER['HTTP_X_API_KEY'];
    } elseif (!empty($_SERVER['X-API-KEY'])) {
        $headerKey = $_SERVER['X-API-KEY'];
    }

    if (!$headerKey || !hash_equals(TERMINAL_API_KEY, $headerKey)) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'API key manquante ou invalide']);
        exit;
    }
}
