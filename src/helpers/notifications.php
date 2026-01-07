<?php
/**
 * ==========================================
 *  HELPERS - NOTIFICATION SYSTEM
 *  Fichier : helpers/notification.php
 *  Auteur : Moha
 *  Description : Gestion des notifications pour les employés
 * ==========================================
 */

require_once __DIR__ . '/../config/boostrap.php'; // 🔗 Connexion à la base de données

// ---------------------------
// 🔒 Vérification de session
// ---------------------------
if (session_status() === PHP_SESSION_NONE) {
     //
}

if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
$employe_id = $_SESSION['employe_id'] ?? 0;

// ---------------------------
// 📨 Récupération des notifications
// ---------------------------
try {
    $stmt = $pdo->prepare("
        SELECT 
            n.id, n.titre, n.contenu, n.type, n.lu, 
            n.date, n.date_creation, n.lien, 
            p.type AS pointage_type, p.date_heure 
        FROM notifications n 
        LEFT JOIN pointages p ON n.pointage_id = p.id 
        WHERE n.employe_id = ? 
        ORDER BY n.lu ASC, n.date DESC, n.date_creation DESC 
        LIMIT 20
    ");
    $stmt->execute([$employe_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de base de données (notifications) : " . $e->getMessage());
    $notifications = [];
}

// ---------------------------
// 🔔 Compte des notifications non lues
// ---------------------------
function countUnreadNotifications(int $employe_id): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE employe_id = ? AND lu = 0");
        $stmt->execute([$employe_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur comptage notifications : " . $e->getMessage());
        return 0;
    }
}

// ---------------------------
// 🆕 Création d'une notification
// ---------------------------
/**
 * Crée une notification pour un employé.
 *
 * @param PDO $pdo Connexion PDO
 * @param int $employe_id ID de l'employé concerné
 * @param string $titre Titre de la notification
 * @param string $contenu Message ou contenu de la notification
 * @param string $type Type de notification (info, retard, succès, pointage_manqué, etc.)
 * @param int|null $pointage_id ID du pointage concerné (facultatif)
 * @param string|null $lien Lien vers une page (facultatif)
 * @return bool Succès ou échec de la création
 */
function creer_notification(PDO $pdo, int $employe_id, string $titre, string $contenu, string $type = 'info', ?int $pointage_id = null, ?string $lien = null): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (employe_id, titre, contenu, type, pointage_id, lien, lu, date, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ");
        return $stmt->execute([$employe_id, $titre, $contenu, $type, $pointage_id, $lien]);
    } catch (PDOException $e) {
        error_log('Erreur lors de la création de notification : ' . $e->getMessage());
        return false;
    }
}

// ---------------------------
// 🖼️ Icône selon le type
// ---------------------------
function getNotificationIcon(string $type): string {
    switch ($type) {
        case 'retard':
            return '<i class="fas fa-clock text-warning"></i>';
        case 'pointage_manqué':
            return '<i class="fas fa-user-times text-danger"></i>';
        case 'succès':
            return '<i class="fas fa-check-circle text-success"></i>';
        case 'erreur':
            return '<i class="fas fa-times-circle text-danger"></i>';
        case 'info':
        default:
            return '<i class="fas fa-info-circle text-primary"></i>';
    }
}

// ---------------------------
// ⏰ Formatage de la date
// ---------------------------
function formatDate(string $date): string {
    return date('d/m/Y H:i', strtotime($date));
}

// ---------------------------
// 🔁 Marquer une notification comme lue
// ---------------------------
function markNotificationAsRead(int $notif_id): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ?");
        return $stmt->execute([$notif_id]);
    } catch (PDOException $e) {
        error_log("Erreur mise à jour notification : " . $e->getMessage());
        return false;
    }
}

// ---------------------------
// 📋 Exemple d’affichage rapide (si besoin standalone)
// ---------------------------

if (basename($_SERVER['PHP_SELF']) === 'notifications.php') :
    $unreadNotificationCount = countUnreadNotifications($employe_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Gestion RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            background: #fff;
            border-radius: 1rem;
        }
        .list-group-item.unread {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
        }
        .list-group-item.read {
            opacity: 0.7;
        }
        .notification-time {
            font-size: 0.85em;
        }
        @media (max-width: 600px) {
            .fw-bold.text-truncate {
                white-space: normal !important;
            }
            .card {
                border-radius: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-bell me-2"></i> Mes Notifications</h4>
                        <span class="badge bg-danger rounded-pill" id="notif-badge"><?= $unreadNotificationCount ?></span>
                    </div>
                    <ul class="list-group list-group-flush" id="notif-list">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li class="list-group-item d-flex align-items-center flex-wrap <?= $notification['lu'] ? 'read' : 'unread' ?>" data-id="<?= $notification['id'] ?>">
                                    <span class="me-3 fs-4"><?= getNotificationIcon($notification['type']) ?></span>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="fw-bold text-truncate" title="<?= htmlspecialchars($notification['titre']) ?>"><?= htmlspecialchars($notification['titre']) ?></div>
                                        <div class="small text-muted text-break mb-1"><?= htmlspecialchars($notification['contenu']) ?></div>
                                        <div class="notification-time small text-secondary"><i class="far fa-clock me-1"></i><?= formatDate($notification['date']) ?></div>
                                    </div>
                                    <?php if (!$notification['lu']): ?>
                                        <button class="btn btn-sm btn-outline-success mark-read-btn ms-2 mt-2 mt-md-0" data-id="<?= $notification['id'] ?>"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted">Aucune notification</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Dynamique : marquer comme lue sans recharger la page
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const notifId = this.dataset.id;
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + notifId
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const li = this.closest('li');
                        li.classList.remove('unread');
                        li.classList.add('read');
                        this.remove();
                        const badge = document.getElementById('notif-badge');
                        let count = parseInt(badge.textContent, 10);
                        if (count > 0) badge.textContent = count - 1;
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
<?php endif; ?>
