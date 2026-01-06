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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['admin_id']) || empty($_POST['confirm_text'])) {
    header('Location: admin_dashboard_unifie.php?error=invalid_request#admins');
    exit();
}

$admin_id = (int)$_POST['admin_id'];
$confirm = trim($_POST['confirm_text']);

if (strtoupper($confirm) !== 'SUPPRIMER') {
    header('Location: profil_admin.php?id=' . $admin_id . '&error=confirm_text_invalid#admins');
    exit();
}

$adminModel = new Admin($pdo);
$target = $adminModel->getById($admin_id);
if (!$target) {
    header('Location: admin_dashboard_unifie.php?error=admin_not_found#admins');
    exit();
}

// Safety: do not allow deletion of super admin or self-delete
if ($target['role'] === ROLE_SUPER_ADMIN) {
    header('Location: profil_admin.php?id=' . $admin_id . '&error=cannot_delete_super#admins');
    exit();
}
if ($admin_id === $current_admin_id) {
    header('Location: profil_admin.php?id=' . $admin_id . '&error=cannot_delete_self#admins');
    exit();
}

try {
    // Remove associated badge tokens
    $pdo->prepare('DELETE FROM badge_tokens WHERE admin_id = ?')->execute([$admin_id]);

    // Delete admin record
    $success = $adminModel->delete($admin_id);

    if ($success) {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $msg = sprintf("[%s] Admin supprimé: id=%d par admin_id=%d\n", date('Y-m-d H:i:s'), $admin_id, $current_admin_id);
        file_put_contents($logDir . '/admin_actions.log', $msg, FILE_APPEND);

        header('Location: admin_dashboard_unifie.php?success=admin_deleted#admins');
        exit();
    } else {
        header('Location: profil_admin.php?id=' . $admin_id . '&error=delete_failed#admins');
        exit();
    }
} catch (Throwable $e) {
    error_log('supprimer_admin_def: ' . $e->getMessage());
    header('Location: profil_admin.php?id=' . $admin_id . '&error=delete_failed#admins');
    exit();
}
