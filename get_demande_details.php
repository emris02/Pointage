<?php
session_start();
require_once 'config.php';
require_once 'db.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$is_super_admin = $_SESSION['super_admin'] ?? false;

// Récupérer les demandes de badge
try {
    // Récupérer les nouvelles demandes (non traitées)
    $stmt_nouvelles = $pdo->prepare("
        SELECT d.*, e.prenom, e.nom, e.photo, 
               TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) AS heures_attente
        FROM demandes_badge d
        JOIN employes e ON d.employe_id = e.id
        WHERE d.statut = 'en_attente'
        ORDER BY d.date_demande DESC
    ");
    $stmt_nouvelles->execute();
    $nouvelles_demandes = $stmt_nouvelles->fetchAll(PDO::FETCH_ASSOC);
    $nombre_nouvelles_demandes = count($nouvelles_demandes);

    // Récupérer les demandes traitées
    $stmt_traitees = $pdo->prepare("
        SELECT d.*, e.prenom, e.nom, e.photo, 
               TIMESTAMPDIFF(HOUR, d.date_demande, d.date_traitement) AS heures_attente
        FROM demandes_badge d
        JOIN employes e ON d.employe_id = e.id
        WHERE d.statut != 'en_attente'
        ORDER BY d.date_traitement DESC
        LIMIT 50
    ");
    $stmt_traitees->execute();
    $demandes_traitees = $stmt_traitees->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $demande_id = $_POST['demande_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($demande_id && $action) {
        try {
            if ($action === 'approve') {
                // Approuver la demande
                $stmt = $pdo->prepare("
                    UPDATE demandes_badge 
                    SET statut = 'approuve', date_traitement = NOW(), admin_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['admin_id'], $demande_id]);
                
                // Logique pour générer le badge ici...
                
            } elseif ($action === 'reject') {
                // Rejeter la demande
                $raison_rejet = $_POST['raison_rejet'] ?? 'Demande refusée';
                
                $stmt = $pdo->prepare("
                    UPDATE demandes_badge 
                    SET statut = 'rejete', date_traitement = NOW(), admin_id = ?, raison_rejet = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['admin_id'], $raison_rejet, $demande_id]);
            }
            
            // Redirection pour éviter la soumission multiple
            header('Location: gestion_demandes_badge.php');
            exit;
            
        } catch (PDOException $e) {
            $error = "Erreur lors du traitement de la demande: " . $e->getMessage();
        }
    }
}

// Calculer les notifications (pour la navbar)
$total_notifications = $nombre_nouvelles_demandes;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des demandes de badge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --avatar-size: 50px;
            --badge-urgent: #dc3545;
            --badge-new: #0d6efd;
            --badge-approved: #198754;
            --badge-rejected: #6c757d;
        }
        
        .avatar {
            width: var(--avatar-size);
            height: var(--avatar-size);
            border-radius: 50%;
            object-fit: cover;
        }
        
        .avatar-placeholder {
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .badge-status {
            padding: 0.5rem 0.8rem;
            border-radius: 20px;
            font-weight: normal;
        }
        
        .badge-new {
            background: var(--badge-new);
            color: white;
        }
        
        .badge-urgent {
            background: var(--badge-urgent);
            color: white;
            animation: pulse 1.5s infinite;
        }
        
        .badge-approved {
            background: var(--badge-approved);
            color: white;
        }
        
        .badge-rejected {
            background: var(--badge-rejected);
            color: white;
        }
        
        .demande-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .demande-new {
            border-left-color: var(--badge-new);
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .demande-urgent {
            border-left-color: var(--badge-urgent);
            background-color: rgba(220, 53, 69, 0.05);
        }
        
        .demande-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .action-buttons .btn {
            min-width: 100px;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 100px;
            height: 2px;
            background: #0d6efd;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; // Inclure la barre de navigation améliorée ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Gestion des demandes de badge</h1>
            <span class="badge bg-primary rounded-pill"><?= $nombre_nouvelles_demandes ?> nouvelle(s)</span>
        </div>

        <!-- Demandes en attente -->
        <div class="mb-5">
            <h3 class="section-title">Demandes en attente</h3>
            
            <?php if ($nombre_nouvelles_demandes > 0): ?>
                <div class="row g-4">
                    <?php foreach ($nouvelles_demandes as $demande): ?>
                        <div class="col-md-6">
                            <div class="card demande-card <?= $demande['heures_attente'] > 24 ? 'demande-urgent' : 'demande-new' ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-start gap-3 mb-3">
                                        <div class="flex-shrink-0">
                                            <?php if (!empty($demande['photo'])): ?>
                                                <img src="<?= htmlspecialchars($demande['photo']) ?>" alt="Photo" class="avatar">
                                            <?php else: ?>
                                                <div class="avatar avatar-placeholder">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <h5 class="card-title mb-1"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></h5>
                                                <div>
                                                    <?php if ($demande['heures_attente'] > 24): ?>
                                                        <span class="badge badge-status badge-urgent">URGENT</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-status badge-new">NOUVEAU</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <p class="card-text text-muted small mb-2">
                                                <i class="far fa-clock me-1"></i> 
                                                <?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?>
                                                (il y a <?= $demande['heures_attente'] ?> heures)
                                            </p>
                                            <p class="card-text"><?= htmlspecialchars($demande['raison']) ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <small class="text-muted">ID: #<?= $demande['id'] ?></small>
                                                <div class="action-buttons d-flex gap-2">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="demande_id" value="<?= $demande['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check me-1"></i> Approuver
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                            data-id="<?= $demande['id'] ?>">
                                                        <i class="fas fa-times me-1"></i> Refuser
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>Aucune demande en attente</h4>
                    <p class="mb-0">Toutes les demandes ont été traitées.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Demandes traitées -->
        <div class="mb-4">
            <h3 class="section-title">Demandes traitées</h3>
            
            <?php if (!empty($demandes_traitees)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employé</th>
                                <th>Demande</th>
                                <th>Date demande</th>
                                <th>Date traitement</th>
                                <th>Statut</th>
                                <th>Administrateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandes_traitees as $demande): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($demande['photo'])): ?>
                                                <img src="<?= htmlspecialchars($demande['photo']) ?>" alt="Photo" class="avatar" style="width: 40px; height: 40px;">
                                            <?php else: ?>
                                                <div class="avatar avatar-placeholder" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars(substr($demande['raison'], 0, 50)) ?>...</td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($demande['date_traitement'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($demande['statut'] === 'approuve'): ?>
                                            <span class="badge badge-status badge-approved">Approuvé</span>
                                        <?php else: ?>
                                            <span class="badge badge-status badge-rejected">Rejeté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>Admin #<?= $demande['admin_id'] ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h4>Aucune demande traitée</h4>
                    <p class="mb-0">Les demandes traitées apparaîtront ici.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour refuser une demande -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Refuser la demande</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="demande_id" id="modalDemandeId">
                        <input type="hidden" name="action" value="reject">
                        
                        <div class="mb-3">
                            <label for="raisonRejet" class="form-label">Raison du refus</label>
                            <textarea class="form-control" id="raisonRejet" name="raison_rejet" rows="3" required></textarea>
                        </div>
                        <p class="text-muted small">Cette raison sera communiquée à l'employé concerné.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Confirmer le refus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialiser le modal pour refuser une demande
        const rejectModal = document.getElementById('rejectModal');
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const demandeId = button.getAttribute('data-id');
            const modalDemandeId = document.getElementById('modalDemandeId');
            modalDemandeId.value = demandeId;
        });
        
        // Marquer une notification comme lue
        document.querySelectorAll('.mark-as-read').forEach(btn => {
            btn.addEventListener('click', function() {
                const demandeId = this.dataset.id;
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        demande_id: demandeId,
                        admin_id: <?= $_SESSION['admin_id'] ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.closest('.demande-card').classList.remove('demande-new', 'demande-urgent');
                        this.remove();
                    }
                });
            });
        });
    </script>
</body>
</html>