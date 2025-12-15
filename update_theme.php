<?php
require_once __DIR__ . '/src/config/bootstrap.php';
require_once __DIR__ . '/src/services/ParametreService.php';

// Vérifier session
if (empty($_SESSION['admin_id']) && empty($_SESSION['employe_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$userId = $_SESSION['admin_id'] ?? $_SESSION['employe_id'];
$userType = isset($_SESSION['admin_id']) ? 'admin' : 'employe';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['theme'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètre manquant']);
    exit();
}

$theme = in_array($data['theme'], ['clair','sombre','auto']) ? $data['theme'] : 'clair';

try {
    $ps = new ParametreService($pdo);
    $ok = $ps->setUserParam($userId, 'theme', $theme, $userType);
    if ($ok) {
        // Mettre à jour la session pour navigation courante
        $_SESSION['theme'] = $theme;
        echo json_encode(['success' => true, 'theme' => $theme]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur base']);
        exit();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
