<?php
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
require_once 'src/models/Admin.php';
require_once 'src/services/BadgeManager.php';

use Pointage\Services\AuthService;

AuthService::requireAuth();
$authController = new AuthController($pdo);
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Ensure request is for admins only
if (!$authController->isAdmin()) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$adminModel = new Admin($pdo);

if ($method === 'GET') {
    $target_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_admin_id;

    $admin = $adminModel->getById($target_id);
    if (!$admin) {
        http_response_code(404);
        echo json_encode(['status'=>'error','message'=>'admin_not_found']);
        exit();
    }

    // permission: only owner or super admin can see full token
    $canSeeToken = ($current_admin_id === $target_id) || $authController->isSuperAdmin($current_admin_id);

    $stmt = $pdo->prepare("SELECT * FROM badge_tokens WHERE admin_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$target_id]);
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $canRegenerate = ($current_admin_id === $target_id) || $authController->isSuperAdmin($current_admin_id);

    $data = [
        'status' => 'success',
        'admin' => [
            'id' => $admin['id'],
            'prenom' => $admin['prenom'],
            'nom' => $admin['nom'],
            'badge_id' => $admin['badge_id'] ?? null,
            'badge_expires' => $admin['badge_expires'] ?? null,
            'statut' => $admin['statut'] ?? null,
            'can_regenerate' => $canRegenerate
        ],
        'token' => null
    ];

    if ($tokenRow) {
        $data['token'] = [
            'token' => $canSeeToken ? $tokenRow['token'] : null,
            'token_hash' => $tokenRow['token_hash'] ?? null,
            'expires_at' => $tokenRow['expires_at'] ?? null
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

if ($method === 'POST') {
    // Expect action=regenerate and admin_id
    $action = $_POST['action'] ?? '';
    if ($action !== 'regenerate' || empty($_POST['admin_id'])) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'invalid_request']);
        exit();
    }

    $target_id = (int)$_POST['admin_id'];
    // Permission check
    if (!((int)$current_admin_id === $target_id || $authController->isSuperAdmin($current_admin_id))) {
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'unauthorized']);
        exit();
    }

    try {
        $res = BadgeManager::regenerateTokenForAdmin($target_id, $pdo);
        // Return minimal info
        echo json_encode([
            'status' => 'success',
            'badge_id' => $res['badge_id'] ?? null,
            'token' => $res['token'] ?? null,
            'expires_at' => $res['expires_at'] ?? null
        ]);
        exit();
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('admin_badge_api regen error: ' . $e->getMessage());
        echo json_encode(['status'=>'error','message'=>'regen_failed']);
        exit();
    }
}

http_response_code(405);
echo json_encode(['status'=>'error','message'=>'method_not_allowed']);
exit();
