<?php
require_once 'src/config/bootstrap.php';
require_once 'src/controllers/NotificationController.php';

if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$employe_id = $_SESSION['employe_id'] ?? null;
$notificationController = new NotificationController($pdo);
$notifications = $employe_id ? $notificationController->getByEmploye($employe_id, 50, false) : [];
$unreadCount = $employe_id ? $notificationController->countUnread($employe_id) : 0;

$pageTitle = 'Notifications';
include 'partials/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-bell me-2"></i> Mes Notifications</h4>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger rounded-pill" id="notif-unread-count"><?= $unreadCount ?></span>
                        <button class="btn btn-sm btn-outline-secondary" id="markAllBtn">Marquer toutes lues</button>
                    </div>
                </div>
                <ul class="list-group list-group-flush" id="notifList">
                    <?php if (count($notifications) === 0): ?>
                        <li class="list-group-item text-center text-muted">Aucune notification</li>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <li class="list-group-item d-flex align-items-start justify-content-between <?= $n['lu'] ? 'read' : 'unread' ?>" data-id="<?= $n['id'] ?>">
                                <div class="d-flex gap-3">
                                    <div class="notif-item-icon bg-<?= $n['type'] === 'retard' ? 'warning' : ($n['type'] === 'arrivee_manquante' ? 'danger' : 'primary') ?> text-white d-flex align-items-center justify-content-center rounded" style="width:44px;height:44px">
                                        <i class="fas <?= $n['type'] === 'retard' ? 'fa-clock' : ($n['type'] === 'arrivee_manquante' ? 'fa-user-times' : 'fa-bell') ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold mb-1"><?= htmlspecialchars($n['titre']) ?></div>
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($n['contenu']) ?></div>
                                        <div class="small text-secondary"><i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($n['date'] ?? $n['date_creation'] ?? 'now')) ?></div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-2">
                                    <?php if (!$n['lu']): ?>
                                        <button class="btn btn-sm btn-outline-success mark-read" data-id="<?= $n['id'] ?>" title="Marquer comme lue"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger delete-notif" data-id="<?= $n['id'] ?>" title="Supprimer"><i class="fas fa-trash"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const api = 'api/notifications.php';

    document.querySelectorAll('.mark-read').forEach(btn => {
        btn.addEventListener('click', async function(){
            const id = this.dataset.id;
            const res = await fetch(api, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'mark_read', id }) });
            const data = await res.json();
            if (data.success) {
                const li = this.closest('li');
                li.classList.remove('unread');
                this.remove();
                const badge = document.getElementById('notif-unread-count');
                let val = parseInt(badge.textContent || '0', 10);
                if (val > 0) badge.textContent = val - 1;
            }
        });
    });

    document.querySelectorAll('.delete-notif').forEach(btn => {
        btn.addEventListener('click', async function(){
            if (!confirm('Supprimer cette notification ?')) return;
            const id = this.dataset.id;
            const res = await fetch(api, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'delete', notification_id: id }) });
            const data = await res.json();
            if (data.success) {
                const li = this.closest('li');
                li.remove();
            }
        });
    });

    const markAllBtn = document.getElementById('markAllBtn');
    markAllBtn && markAllBtn.addEventListener('click', async function(){
        const res = await fetch(api, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'mark_all' }) });
        const data = await res.json();
        if (data.success) {
            document.querySelectorAll('#notifList .unread').forEach(li => li.classList.remove('unread'));
            document.getElementById('notif-unread-count').textContent = '0';
        }
    });
});
</script>

<?php include 'partials/footer.php'; ?>