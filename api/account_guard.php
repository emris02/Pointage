<?php
// api/account_guard.php
// Central guard to enforce that the current account is active.

/**
 * Check session-based account active state and if inactive return JSON error and exit.
 */
function account_guard_api()
{
    // Bootstrap provides $pdo
    if (!isset($GLOBALS['pdo'])) {
        // try to include bootstrap if available
        $possible = __DIR__ . '/../src/config/bootstrap.php';
        if (file_exists($possible)) require_once $possible;
    }

    $pdo = $GLOBALS['pdo'] ?? null;

    // If a session user is present, enforce their statut
    $userId = $_SESSION['employe_id'] ?? $_SESSION['admin_id'] ?? null;
    $isAdmin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

    if ($userId && $pdo) {
        $table = $isAdmin ? 'admins' : 'employes';
        try {
            $stmt = $pdo->prepare("SELECT statut FROM $table WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $statut = $r['statut'] ?? null;
            if ($statut !== 'actif') {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Compte désactivé. Seule la suppression ou la réactivation est autorisée.', 'code' => 'ACCOUNT_INACTIVE']);
                exit();
            }
        } catch (Throwable $e) {
            // If DB fails, be conservative and block
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la vérification du statut du compte', 'error' => $e->getMessage()]);
            exit();
        }
    }
}

/**
 * Utility: check active status for any user by type/id (for token-based endpoints)
 */
function is_user_active($pdo, $userType, $userId)
{
    if (!$pdo || !$userType || !$userId) return false;
    $table = ($userType === 'admin') ? 'admins' : 'employes';
    try {
        $stmt = $pdo->prepare("SELECT statut FROM $table WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($r['statut']) && $r['statut'] === 'actif';
    } catch (Throwable $e) {
        return false;
    }
}

// Run the guard automatically when included from API endpoints
// (endpoints that perform token-based lookup can call is_user_active directly after resolving token)
if (basename($_SERVER['PHP_SELF']) !== 'account_guard.php') {
    account_guard_api();
}
