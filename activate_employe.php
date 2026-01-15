<?php
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
require_once 'src/models/Employe.php';

use Pointage\Services\AuthService;

AuthService::requireAuth();
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_SUPER_ADMIN]);

if (!$is_admin) {
    header('Location: admin_dashboard_unifie.php?error=unauthorized'); exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['employe_id'])) {
    header('Location: admin_dashboard_unifie.php?error=invalid_request'); exit();
}

$employe_id = (int)$_POST['employe_id'];
$employeModel = new Employe($pdo);
$target = $employeModel->getById($employe_id);
if (!$target) { header('Location: admin_dashboard_unifie.php?error=employee_not_found'); exit(); }

// Detect AJAX requests (fetch/XHR) so we can return JSON and avoid full redirects when used in-page
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    $success = $employeModel->update($employe_id, ['statut' => 'actif']);
    if ($success) {
        $logDir = __DIR__ . '/logs'; if (!is_dir($logDir)) mkdir($logDir,0755,true);
        $msg = sprintf("[%s] Employe activé: id=%d par admin_id=%d\n", date('Y-m-d H:i:s'), $employe_id, $current_admin_id);
        file_put_contents($logDir . '/admin_actions.log', $msg, FILE_APPEND);

        // Try to ensure a badge exists when activating (regenerate if missing)
        $badgeInfo = null;
        try {
            require_once __DIR__ . '/src/services/BadgeManager.php';
            $regen = BadgeManager::regenerateToken($employe_id, $pdo);
            $badgeInfo = ['active' => true, 'expires_at' => $regen['expires_at'] ?? null];
        } catch (Throwable $e) {
            error_log('activate_employe (badge regen): ' . $e->getMessage());
        }

        // If this is an AJAX request, return JSON so the client can show an in-page notification without redirect
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Employé activé', 'statut' => 'actif', 'badge' => $badgeInfo]);
            exit();
        }

        // Determine return target (allow only internal paths) but avoid redirecting to panel_employes.php to prevent unwanted navigation
        $returnTo = $_POST['return_to'] ?? $_SERVER['HTTP_REFERER'] ?? null;
        if ($returnTo && (strpos($returnTo, 'profil_employe.php') !== false || strpos($returnTo, 'admin_dashboard_unifie.php') !== false)) {
            header('Location: ' . $returnTo . (strpos($returnTo, '?') !== false ? '&' : '?') . 'success=employe_activated');
            exit();
        }
        header('Location: profil_employe.php?id=' . $employe_id . '&success=employe_activated'); exit();
    } else {
        // Failure handling
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Impossible d\'activer l\'employé']);
            exit();
        }

        $returnTo = $_POST['return_to'] ?? $_SERVER['HTTP_REFERER'] ?? null;
        if ($returnTo && (strpos($returnTo, 'profil_employe.php') !== false || strpos($returnTo, 'admin_dashboard_unifie.php') !== false)) {
            header('Location: ' . $returnTo . (strpos($returnTo, '?') !== false ? '&' : '?') . 'error=activate_failed');
            exit();
        }
        header('Location: profil_employe.php?id=' . $employe_id . '&error=activate_failed'); exit();
    }
} catch (Throwable $e) {
    error_log('activate_employe: ' . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
        exit();
    }
    header('Location: profil_employe.php?id=' . $employe_id . '&error=activate_failed'); exit();
}