<?php
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
require_once 'src/models/Admin.php';
require_once 'src/services/BadgeManager.php';

use Pointage\Services\AuthService;

AuthService::requireAuth();
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    header('Location: login.php?error=unauthorized');
    exit();
}

$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_admin_id;

$adminModel = new Admin($pdo);
$admin = $adminModel->getById($target_id);
if (!$admin) {
    header('Location: admin_dashboard_unifie.php?error=admin_not_found#admins');
    exit();
}

// Handle regenerate request (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate' && isset($_POST['admin_id'])) {
    $admin_id = (int)$_POST['admin_id'];
    $allowed = ($admin_id === $current_admin_id) || $authController->isSuperAdmin($current_admin_id);
    if (!$allowed) {
        // Log unauthorized attempt
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $msg = sprintf("[%s] Tentative régénération non autorisée: target=%d par admin=%d\n", date('Y-m-d H:i:s'), $admin_id, $current_admin_id);
        file_put_contents($logDir . '/admin_actions.log', $msg, FILE_APPEND);

        header('Location: admin_badge.php?id=' . $admin_id . '&error=unauthorized'); exit();
    }

    try {
        $res = BadgeManager::regenerateTokenForAdmin($admin_id, $pdo);
        // If regenerate returned a badge_id, we can show it in the redirect as info
        $query = 'success=badge_regenerated';
        if (!empty($res['badge_id'])) {
            $query .= '&badge_id=' . urlencode($res['badge_id']);
        }
        header('Location: admin_badge.php?id=' . $admin_id . '&' . $query);
        exit();
    } catch (Throwable $e) {
        error_log('admin_badge regen error: ' . $e->getMessage());
        header('Location: admin_badge.php?id=' . $admin_id . '&error=regen_failed');
        exit();
    }
}

// If accessed directly via GET in browser, redirect to profile page where modal is available
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Location: profil_admin.php?id=' . $target_id);
    exit();
}

// Fetch active token if present (for API consumers or internal rendering)
$stmt = $pdo->prepare("SELECT * FROM badge_tokens WHERE admin_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$target_id]);
$tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

// Simple view
$pageTitle = "Badge Administrateur - " . htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']);
include 'partials/header.php';
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">Badge Administrateur - <?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></h1>
        <a href="profil_admin.php?id=<?= $admin['id'] ?>" class="btn btn-outline-secondary">Retour</a>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success']==='badge_regenerated'): ?>
        <div class="alert alert-success">
            Badge régénéré avec succès.
            <?php if (!empty($_GET['badge_id'])): ?>
                <div class="small text-muted mt-1">Nouvel ID : <strong><?= htmlspecialchars($_GET['badge_id']) ?></strong></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">Erreur: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <?php if ($tokenRow): ?>
        <div class="card p-4">
            <h4>Badge actif</h4>
            <p><strong>Badge ID:</strong> <?= htmlspecialchars($admin['badge_id'] ?? '—') ?></p>
            <p><strong>Expire le:</strong> <?= htmlspecialchars($tokenRow['expires_at']) ?></p>
            <div id="qr" style="width:160px;height:160px;margin-bottom:10px;"></div>
            <pre class="small"><?= htmlspecialchars($tokenRow['token']) ?></pre>
            <?php $canRegenerate = ($current_admin_id === $admin['id']) || $authController->isSuperAdmin($current_admin_id); ?>
            <?php if ($canRegenerate): ?>
                <form method="post">
                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                    <input type="hidden" name="action" value="regenerate">
                    <button class="btn btn-warning">Régénérer le badge</button>
                </form>
            <?php else: ?>
                <button class="btn btn-warning" disabled title="Seul le propriétaire du badge ou un super admin peut régénérer">Régénérer le badge</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card p-4">
            <h4>Aucun badge actif</h4>
            <p>Ce compte ne possède pas actuellement de badge actif. Vous pouvez en créer un.</p>
            <form method="post">
                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                <input type="hidden" name="action" value="regenerate">
                <button class="btn btn-primary">Générer un badge</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@latest/build/qrcode.min.js"></script>
<script>
<?php if ($tokenRow): ?>
(function(){
    const target = document.getElementById('qr');
    if (!target) return;
    const tokenData = JSON.stringify({ adminId: <?= $admin['id'] ?>, token: "<?= addslashes($tokenRow['token']) ?>", expires: "<?= $tokenRow['expires_at'] ?>" });
    // ensure a canvas child and render into it
    const canvas = document.createElement('canvas');
    canvas.className = 'qr-canvas';
    canvas.style.maxWidth = '160px';
    canvas.style.width = '100%';
    target.innerHTML = '';
    target.appendChild(canvas);
    try {
        QRCode.toCanvas(canvas, tokenData, {width:160, margin:1}, function(err) { if (err) console.error(err); });
    } catch (e) { console.error('QR render error', e); }
})();
<?php endif; ?>
</script>
<?php include 'partials/footer.php';
