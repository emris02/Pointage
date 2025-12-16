<?php
/**
 * ==========================================
 *  HELPERS - NOTIFICATIONS ADMIN
 *  Fichier : helpers/admin_notification.php
 *  Auteur : Moha
 *  Description : Liste et gestion des notifications pour les administrateurs
 * ==========================================
 */

require_once __DIR__ . '/../config/boostrap.php'; // 🔗 Connexion DB

// ---------------------------
// 🔒 Vérification des permissions
// ---------------------------
if (session_status() === PHP_SESSION_NONE) {
     //
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// ---------------------------
// 🔔 Récupération des notifications
// ---------------------------
$notifications = [];

try {
    // ⚙️ Notifications de pointages non lus
    $stmt = $pdo->prepare("
        SELECT p.id, p.type, p.date_heure, e.nom, e.prenom, e.photo,
               TIMESTAMPDIFF(MINUTE, p.date_heure, NOW()) as minutes_ago
        FROM pointages p
        JOIN employes e ON p.employe_id = e.id
        WHERE p.is_read = 0
        ORDER BY p.date_heure DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pointages as $pointage) {
        $notifications[] = [
            'type' => 'pointage',
            'id' => $pointage['id'],
            'title' => 'Nouveau pointage',
            'message' => "{$pointage['prenom']} {$pointage['nom']} a pointé ({$pointage['type']})",
            'time' => $pointage['minutes_ago'],
            'icon' => 'fas fa-clock',
            'color' => 'primary',
            'photo' => $pointage['photo']
        ];
    }

    // ⚙️ Notifications de demandes de badge en attente
    $stmt = $pdo->prepare("
        SELECT d.id, d.type, d.date_demande, e.nom, e.prenom, e.photo, d.statut,
               TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) as heures_attente
        FROM demandes_badge d
        JOIN employes e ON d.employe_id = e.id
        WHERE d.statut = 'en_attente' AND d.is_read = 0
        ORDER BY d.date_demande DESC
        LIMIT 10
    ");
    $stmt->execute();
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($demandes as $demande) {
        $urgence = $demande['heures_attente'] > 24 ? 'urgente' : 'normale';
        $notifications[] = [
            'type' => 'demande',
            'id' => $demande['id'],
            'title' => 'Demande en attente',
            'message' => "{$demande['prenom']} {$demande['nom']} - " . ucfirst($demande['type']),
            'time' => $demande['heures_attente'],
            'icon' => 'fas fa-file-alt',
            'color' => $urgence === 'urgente' ? 'danger' : 'warning',
            'photo' => $demande['photo'],
            'urgence' => $urgence
        ];
    }

    // ⚙️ Notifications de retards
    $stmt = $pdo->prepare("
        SELECT p.id, p.date_heure, e.nom, e.prenom, e.photo,
               TIMESTAMPDIFF(MINUTE, p.date_heure, NOW()) as minutes_ago,
               TIMEDIFF(p.date_heure, CONCAT(DATE(p.date_heure), ' 09:00:00')) as retard
        FROM pointages p
        JOIN employes e ON p.employe_id = e.id
        WHERE p.type = 'arrivee'
          AND TIME(p.date_heure) > '09:00:00'
          AND DATE(p.date_heure) = CURDATE()
        ORDER BY p.date_heure DESC
        LIMIT 5
    ");
    $stmt->execute();
    $retards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($retards as $retard) {
        $minutes = (int) date('i', strtotime($retard['retard']));
        $notifications[] = [
            'type' => 'retard',
            'id' => $retard['id'],
            'title' => 'Retard détecté',
            'message' => "{$retard['prenom']} {$retard['nom']} - {$minutes} min de retard",
            'time' => $retard['minutes_ago'],
            'icon' => 'fas fa-exclamation-triangle',
            'color' => 'danger',
            'photo' => $retard['photo']
        ];
    }

    // 🔄 Tri : plus récent en premier
    usort($notifications, fn($a, $b) => $a['time'] - $b['time']);

} catch (PDOException $e) {
    error_log("Erreur notifications admin : " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .notification-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .notification-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .time-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .urgent { border-left: 4px solid #dc3545; background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%); }
        .normal { border-left: 4px solid #0d6efd; }
        .warning { border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-bell text-primary me-2"></i> Notifications
            </h2>
            <div>
                <button class="btn btn-outline-primary btn-sm" onclick="markAllAsRead()">
                    <i class="fas fa-check-double me-1"></i> Tout marquer comme lu
                </button>
                <a href="../admin_dashboard_unifie.php" class="btn btn-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucune notification</h4>
                <p class="text-muted">Vous êtes à jour !</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($notifications as $notif): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card notification-card <?= $notif['urgence'] ?? 'normal' ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon bg-<?= $notif['color'] ?> bg-opacity-10 text-<?= $notif['color'] ?> me-3">
                                        <i class="<?= $notif['icon'] ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0"><?= htmlspecialchars($notif['title']) ?></h6>
                                            <span class="badge bg-secondary time-badge">
                                                <?php
                                                if ($notif['time'] < 60) echo $notif['time'] . ' min';
                                                elseif ($notif['time'] < 1440) echo floor($notif['time'] / 60) . 'h';
                                                else echo floor($notif['time'] / 1440) . 'j';
                                                ?>
                                            </span>
                                        </div>
                                        <p class="card-text small mb-2"><?= htmlspecialchars($notif['message']) ?></p>
                                        <?php if (!empty($notif['photo'])): ?>
                                            <img src="<?= htmlspecialchars($notif['photo']) ?>" class="notification-avatar me-2" alt="Photo" onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="markAsRead(<?= $notif['id'] ?>, '<?= $notif['type'] ?>')">
                                                <i class="fas fa-check me-1"></i> Marquer comme lu
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ✅ Marquer une notification spécifique
    function markAsRead(id, type) {
        fetch('../mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, type })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const card = event.target.closest('.notification-card');
                card.style.opacity = '0.5';
                setTimeout(() => card.remove(), 400);
            }
        })
        .catch(() => alert('Erreur lors du marquage comme lu.'));
    }

    // ✅ Marquer toutes comme lues
    function markAllAsRead() {
        if (confirm('Marquer toutes les notifications comme lues ?')) {
            fetch('../mark_all_notifications_read.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => { if (data.success) location.reload(); });
        }
    }

    // 🔁 Actualisation automatique toutes les 30 secondes
    setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
