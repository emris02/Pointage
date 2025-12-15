<?php
require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/config/constants.php';
require_once __DIR__ . '/../src/middleware/api_key_middleware.php';
require_once __DIR__ . '/../src/controllers/PointageController.php';

// Force JSON output
header('Content-Type: application/json; charset=utf-8');

// Vérifier la clé API du terminal
try {
	require_terminal_api_key();
} catch (Exception $e) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Terminal non autorisé', 'detail' => $e->getMessage()]);
	exit;
}

try {
	$controller = new PointageController($pdo);
	$result = $controller->processPointage();
	// Ensure we return JSON even if controller returned array
	echo json_encode($result);
} catch (Throwable $t) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Erreur serveur', 'detail' => $t->getMessage()]);
}