<?php
 //
require 'db.php';

// Fonction de génération de badge intégrée
function generateBadgeToken($employe_id) {
    $random = bin2hex(random_bytes(16));
    $timestamp = time();
    $data = "$employe_id|$random|$timestamp";
    $signature = hash_hmac('sha256', $data, SECRET_KEY);
    
    return [
        'token' => "$employe_id|$random|$timestamp|$signature",
        'expires_at' => date('Y-m-d H:i:s', $timestamp + 7200) // 2h
    ];
}

// Vérification des droits admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Traitement des actions sur les demandes
if (isset($_POST['action_demande'])) {
    $demande_id = (int)$_POST['id_demande'];
    $action = $_POST['action_demande'];
    $commentaire = $_POST['commentaire'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM demandes_badge WHERE id = ?");
    $stmt->execute([$demande_id]);
    $demande = $stmt->fetch();
    
    if ($demande) {
        if ($action === 'approuve') {
            // Générer un nouveau badge
            $tokenData = generateBadgeToken($demande['employe_id']);
            
            // Enregistrer le badge
            $stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token_hash, created_at, expires_at) 
                                  VALUES (?, ?, NOW(), ?)");
            $stmt->execute([
                $demande['employe_id'],
                hash('sha256', $tokenData['token']),
                $tokenData['expires_at']
            ]);
            
            // Mettre à jour le statut de la demande
            $stmt = $pdo->prepare("UPDATE demandes_badge SET statut = 'approuve', raison = ? WHERE id = ?");
            $stmt->execute([$raison, $demande_id]);
            
            $_SESSION['success_message'] = "✅ Demande approuvée et badge généré pour l'employé.";
        } 
        elseif ($action === 'rejete') {
            $stmt = $pdo->prepare("UPDATE demandes_badge SET statut = 'rejete', raison = ? WHERE id = ?");
            $stmt->execute([$raison, $demande_id]);
            $_SESSION['success_message'] = "❌ Demande rejetée.";
        }
        
        header("Location: gestion_demandes_badge.php");
        exit();
    } else {
        $_SESSION['error_message'] = "⚠️ Demande introuvable.";
        header("Location: gestion_demandes_badge.php");
        exit();
    }
}

// Récupérer les demandes avec pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Compter le nombre total de demandes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_badge");
$stmt->execute();
$total_demandes = $stmt->fetchColumn();
$total_pages = ceil($total_demandes / $limit);

// Récupérer les demandes paginées
$stmt = $pdo->prepare("
    SELECT d.*, e.nom, e.prenom, e.email, e.poste, e.departement, e.photo,
           b.token_hash AS dernier_badge
    FROM demandes_badge d
    JOIN employes e ON d.employe_id = e.id
    LEFT JOIN (
        SELECT employe_id, token_hash
        FROM badge_tokens
        WHERE employe_id IN (
            SELECT employe_id
            FROM demandes_badge
        )
        ORDER BY created_at DESC
        LIMIT 1
    ) b ON e.id = b.employe_id
    ORDER BY 
        CASE WHEN d.statut = 'en_attente' THEN 0 ELSE 1 END,
        d.date_demande DESC
    LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();

$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'approuve' THEN 1 ELSE 0 END) AS approuve,
        SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) AS rejete
    FROM demandes_badge
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des demandes de badges</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
       
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header-bar {
            background: linear-gradient(135deg, var(--color-primary), #3a0ca3);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.12);
        }
        
        .stat-attente { border-left: 5px solid var(--color-warning); }
        .stat-approuve { border-left: 5px solid var(--color-success); }
        .stat-rejete { border-left: 5px solid var(--color-danger); }
        
        .demande-card {
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .demande-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .demande-urgente {
            border-left: 4px solid var(--color-danger);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }
        
        .employee-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .badge-status {
            padding: 0.35em 0.65em;
            font-size: 0.85em;
            border-radius: 50rem;
        }
        
        .action-btn {
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
          <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    <div class="header-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">
                    <i class="fas fa-id-card-alt me-2"></i>Gestion des demandes de badges
                </h1>
                <div>
                    <a href="admin_dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i> Retour au tableau de bord
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mb-5">
        <!-- Messages de notification -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card stat-attente">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase text-muted">En attente</h6>
                                <h2 class="mb-0"><?= $stats['en_attente'] ?></h2>
                            </div>
                            <div class="icon-circle bg-warning text-white">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card stat-approuve">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase text-muted">Approuvées</h6>
                                <h2 class="mb-0"><?= $stats['approuve'] ?></h2>
                            </div>
                            <div class="icon-circle bg-success text-white">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card stat-rejete">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase text-muted">Rejetées</h6>
                                <h2 class="mb-0"><?= $stats['rejete'] ?></h2>
                            </div>
                            <div class="icon-circle bg-danger text-white">
                                <i class="fas fa-times-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-uppercase text-white opacity-75">Total</h6>
                                <h2 class="mb-0 text-white"><?= $stats['total'] ?></h2>
                            </div>
                            <div class="icon-circle bg-white text-primary">
                                <i class="fas fa-id-card fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtres et actions -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un employé...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente">En attente</option>
                            <option value="approuve">Approuvé</option>
                            <option value="rejete">Rejeté</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button id="filterPending" class="btn btn-warning w-100">
                            <i class="fas fa-exclamation-triangle me-1"></i> Urgences (>48h)
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des demandes -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Liste des demandes</h5>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="demandesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Employé</th>
                                <th>Département</th>
                                <th>Date demande</th>
                                <th>Dernier badge</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandes as $demande): 
                                $isUrgent = (strtotime('now') - strtotime($demande['date_demande'])) > 172800; // 48h
                            ?>
                            <tr class="<?= $isUrgent && $demande['statut'] === 'en_attente' ? 'demande-urgente' : '' ?>"
                                data-statut="<?= $demande['statut'] ?>"
                                data-employe="<?= strtolower($demande['prenom'] . ' ' . $demande['nom']) ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($demande['photo'])): ?>
                                            <img src="<?= htmlspecialchars($demande['photo']) ?>" alt="Photo" class="employee-photo me-3">
                                        <?php else: ?>
                                            <div class="employee-photo bg-secondary d-flex align-items-center justify-content-center me-3">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($demande['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars(ucfirst(str_replace('depart_', '', $demande['departement']))) ?></td>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?>
                                    <?php if ($demande['statut'] === 'en_attente' && $isUrgent): ?>
                                        <div class="badge bg-danger mt-1">Urgent</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($demande['dernier_badge']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $badge_class = [
                                            'en_attente' => 'warning',
                                            'approuve' => 'success',
                                            'rejete' => 'danger'
                                        ][$demande['statut']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge_class ?> badge-status">
                                        <?= [
                                            'en_attente' => 'En attente',
                                            'approuve' => 'Approuvé',
                                            'rejete' => 'Rejeté'
                                        ][$demande['statut']] ?? $demande['statut'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($demande['statut'] === 'en_attente'): ?>
                                            <button class="btn btn-sm btn-success action-btn accepter-btn" 
                                                    data-id="<?= $demande['id'] ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#actionModal"
                                                    data-action="approuve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger action-btn rejeter-btn" 
                                                    data-id="<?= $demande['id'] ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#actionModal"
                                                    data-action="rejete">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-info action-btn details-btn" 
                                                data-id="<?= $demande['id'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Détails -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de la demande</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalDetailsContent">
                    <!-- Contenu chargé via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Action -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="actionForm">
                    <input type="hidden" name="id_demande" id="formDemandeId">
                    <input type="hidden" name="action_demande" id="formAction">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="actionModalTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" 
                                      placeholder="Ajouter un commentaire (optionnel)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn" id="actionSubmitBtn"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion des modals
        const detailsModal = document.getElementById('detailsModal');
        const actionModal = document.getElementById('actionModal');
        const modalDetailsContent = document.getElementById('modalDetailsContent');
        const actionForm = document.getElementById('actionForm');
        const formDemandeId = document.getElementById('formDemandeId');
        const formAction = document.getElementById('formAction');
        const actionModalTitle = document.getElementById('actionModalTitle');
        const actionSubmitBtn = document.getElementById('actionSubmitBtn');
        
        // Filtres
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const filterPendingBtn = document.getElementById('filterPending');
        
        // Charger les détails d'une demande
        document.querySelectorAll('.details-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const demandeId = this.dataset.id;
                fetch(`get_demande_details.php?id=${demandeId}`)
                    .then(response => response.text())
                    .then(html => {
                        modalDetailsContent.innerHTML = html;
                    });
            });
        });
        
        // Gestion des actions (accepter/rejeter)
        document.querySelectorAll('.accepter-btn, .rejeter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const demandeId = this.dataset.id;
                const action = this.dataset.action;
                
                formDemandeId.value = demandeId;
                formAction.value = action;
                
                if (action === 'approuve') {
                    actionModalTitle.textContent = 'Approuver la demande';
                    actionSubmitBtn.textContent = 'Approuver';
                    actionSubmitBtn.className = 'btn btn-success';
                } else {
                    actionModalTitle.textContent = 'Rejeter la demande';
                    actionSubmitBtn.textContent = 'Rejeter';
                    actionSubmitBtn.className = 'btn btn-danger';
                }
            });
        });
        
        // Filtrer par statut
        statusFilter.addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('#demandesTable tbody tr');
            
            rows.forEach(row => {
                if (!status || row.dataset.statut === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Recherche d'employé
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#demandesTable tbody tr');
            
            rows.forEach(row => {
                const employeName = row.dataset.employe.toLowerCase();
                if (employeName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Filtre urgences
        filterPendingBtn.addEventListener('click', function() {
            const rows = document.querySelectorAll('#demandesTable tbody tr');
            
            rows.forEach(row => {
                if (row.classList.contains('demande-urgente')) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    </script>
</body>
</html>