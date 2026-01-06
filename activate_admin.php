<?php
require_once 'src/config/bootstrap.php';
require_once 'src/models/Admin.php';
require_once 'src/services/AuthService.php';

use Pointage\Services\AuthService;

AuthService::requireAuth();

$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN;

if (!$is_super_admin) {
    header('Location: admin_dashboard_unifie.php?error=unauthorized#admins');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['admin_id'])) {
    header('Location: admin_dashboard_unifie.php?error=invalid_request#admins');
    exit();
}

$admin_id = (int)$_POST['admin_id'];

// Prevent self-activation checks not necessary (super admin re-activating others OK)

$adminModel = new Admin($pdo);
$target = $adminModel->getById($admin_id);
if (!$target) {
    header('Location: admin_dashboard_unifie.php?error=admin_not_found#admins');
    exit();
}

try {
    $success = $adminModel->update($admin_id, ['statut' => 'actif']);
    if ($success) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $msg = sprintf("[%s] Admin activé: id=%d par admin_id=%d\n", date('Y-m-d H:i:s'), $admin_id, $current_admin_id);
        file_put_contents($logDir . '/admin_actions.log', $msg, FILE_APPEND);

        header('Location: profil_admin.php?id=' . $admin_id . '&success=admin_activated#admins');
        exit();
    } else {
        header('Location: profil_admin.php?id=' . $admin_id . '&error=activate_failed#admins');
        exit();
    }
} catch (Throwable $e) {
    error_log('activate_admin: ' . $e->getMessage());
    header('Location: profil_admin.php?id=' . $admin_id . '&error=activate_failed#admins');
    exit();
}
