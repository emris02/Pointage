<?php
session_start();
require 'db.php';

// Initialisation des variables de session
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = $_SESSION['user_id'] ?? 0;
}

// Sécurité : restriction d'accès
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: login.php");
    exit();
}

$is_super_admin = ($_SESSION['role'] === 'super_admin');
$message = "";
$employe_id = $_SESSION['employe_id'] ?? 0; // Initialisation de $employe_id

// Suppression d'un admin (uniquement super admin)
if (isset($_GET['delete_admin']) && $is_super_admin) {
    $admin_id = (int)$_GET['delete_admin'];
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ? AND role = 'admin'");
    $message = $stmt->execute([$admin_id])
        ? '<div class="alert alert-success">✅ Admin supprimé avec succès.</div>'
        : '<div class="alert alert-danger">❌ Échec de la suppression de l\'admin.</div>';
}

// Suppression d'un employé
if (isset($_GET['delete_employe'])) {
    $employe_id = (int)$_GET['delete_employe'];
    $stmt = $pdo->prepare("DELETE FROM employes WHERE id = ?");
    $message = $stmt->execute([$employe_id])
        ? '<div class="alert alert-success">✅ Employé supprimé avec succès.</div>'
        : '<div class="alert alert-danger">❌ Échec de la suppression de l\'employé.</div>';
}

// Pagination pour les employés
$employes_per_page = 5; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_index = ($page - 1) * $employes_per_page;

// Récupération du nombre total d'employés
$total_employes = $pdo->query("SELECT COUNT(*) FROM employes")->fetchColumn();
$total_pages = ceil($total_employes / $employes_per_page);

// Récupération des données des employés
$employes = $pdo->query("
    SELECT e.*, MAX(p.date_heure) AS last_pointage
    FROM employes e
    LEFT JOIN pointages p ON e.id = p.employe_id
    GROUP BY e.id
    ORDER BY last_pointage ASC
    LIMIT $employes_per_page OFFSET $start_index
")->fetchAll();

// Récupération des données des admins
$admins = $is_super_admin ? $pdo->query("SELECT * FROM admins WHERE role = 'admin'")->fetchAll() : [];

// Filtrage par date
$date_filter = "";
$params = [];
if (!empty($_GET['date'])) {
    $date_filter = "WHERE DATE(p.date_heure) = :date";
    $params[':date'] = $_GET['date'];
}

// Compte des pointages non lus
$unread_count = $pdo->query("SELECT COUNT(*) FROM pointages WHERE is_read = 0")->fetchColumn();

// Liste des pointages
$sql_pointages = "
    SELECT p.id, e.nom, e.prenom, p.type, p.date_heure
    FROM pointages p
    JOIN employes e ON p.employe_id = e.id
    $date_filter
    ORDER BY p.date_heure DESC
";
$stmt_pointages = $pdo->prepare($sql_pointages);
$stmt_pointages->execute($params);
$pointages = $stmt_pointages->fetchAll();

// Groupement par employé et date
$grouped = [];
foreach ($pointages as $p) {
    $dateKey = date('Y-m-d', strtotime($p['date_heure']));
    $key = $p['prenom'] . '|' . $p['nom'] . '|' . $dateKey;

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'prenom' => $p['prenom'],
            'nom' => $p['nom'],
            'date' => date('d/m/Y', strtotime($p['date_heure'])),
            'arrivee' => null,
            'depart' => null
        ];
    }

    if ($p['type'] === 'arrivee') {
        $grouped[$key]['arrivee'] = date('H:i:s', strtotime($p['date_heure']));
    } elseif ($p['type'] === 'depart') {
        $grouped[$key]['depart'] = date('H:i:s', strtotime($p['date_heure']));
    }
}

// Marquer les pointages comme lus
if (!empty($pointages)) {
    $ids = array_column($pointages, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE pointages SET is_read = 1 WHERE id IN ($placeholders) AND is_read = 0")->execute($ids);
}

// Temps total mensuel travaillé par employé
$employes_data = $pdo->query("
    SELECT e.id, e.nom, e.prenom, e.email FROM employes e
")->fetchAll();

$temps_totaux = [];
foreach ($employes_data as $e) {
    $stmt = $pdo->prepare("SELECT type, date_heure FROM pointages WHERE employe_id = ? ORDER BY date_heure ASC");
    $stmt->execute([$e['id']]);
    $points = $stmt->fetchAll();

    $total_seconds = 0;
    $arrivee_time = null;

    foreach ($points as $p) {
        if ($p['type'] === 'arrivee') {
            $arrivee_time = strtotime($p['date_heure']);
        } elseif ($p['type'] === 'depart' && $arrivee_time) {
            $depart_time = strtotime($p['date_heure']);
            $total_seconds += $depart_time - $arrivee_time;
            $arrivee_time = null;
        }
    }

    $temps_totaux[] = [
        'nom' => $e['nom'],
        'prenom' => $e['prenom'],
        'email' => $e['email'],
        'total_travail' => gmdate('H:i:s', $total_seconds)
    ];
}

// Récapitulatif journalier par employé
$sql = "
SELECT
    e.nom,
    e.prenom,
    DATE(p.date_heure) AS jour,
    (SELECT TIME(CONVERT_TZ(MIN(p1.date_heure), '+00:00', '+00:00'))
     FROM pointages p1
     WHERE p1.employe_id = p.employe_id
       AND DATE(p1.date_heure) = DATE(p.date_heure)
       AND p1.type = 'arrivee') AS arrivee,
    (SELECT TIME(CONVERT_TZ(MAX(p2.date_heure), '+00:00', '+00:00'))
     FROM pointages p2
     WHERE p2.employe_id = p.employe_id
       AND DATE(p2.date_heure) = DATE(p.date_heure)
       AND p2.type = 'depart') AS depart,
    SEC_TO_TIME(SUM(
        CASE
            WHEN p.type = 'depart' THEN TIME_TO_SEC(p.temps_total)
            ELSE 0
        END
    )) AS temps_total_jour
FROM pointages p
JOIN employes e ON p.employe_id = e.id
WHERE p.date_heure BETWEEN '2025-05-01' AND '2025-05-31'
GROUP BY p.employe_id, DATE(p.date_heure)
ORDER BY e.nom, jour ASC
";
$data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Nettoyage des badges expirés
function nettoyerBadges($pdo) {
    try {
        // Suppression des badges expirés depuis plus de 24h
        $stmt1 = $pdo->prepare("DELETE FROM badge_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt1->execute();
        
        // Conservation du dernier badge valide par employé
        $stmt2 = $pdo->prepare("
            DELETE bt1 FROM badge_tokens bt1
            INNER JOIN (
                SELECT employe_id, MAX(generated_at) as last_gen
                FROM badge_tokens
                WHERE expires_at > NOW()
                GROUP BY employe_id
            ) bt2 ON bt1.employe_id = bt2.employe_id
            WHERE bt1.generated_at < bt2.last_gen
        ");
        $stmt2->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur nettoyage badges: " . $e->getMessage());
        return false;
    }
}

nettoyerBadges($pdo);

// Récupération des demandes de badges en attente
$demandes_badge = $pdo->query("
    SELECT d.*, e.nom, e.prenom, e.email, e.poste, e.photo,
           TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) as heures_attente
    FROM demandes_badge d
    JOIN employes e ON d.employe_id = e.id
    WHERE d.statut = 'en_attente'
    ORDER BY 
        CASE WHEN heures_attente > 24 THEN 0 ELSE 1 END,
        d.date_demande ASC
")->fetchAll();

// Récupérer les demandes en attente
$stmt = $pdo->prepare("
    SELECT d.*, e.nom, e.prenom, e.email, e.poste
    FROM demandes_badge d
    JOIN employes e ON d.employe_id = e.id
    WHERE d.statut = 'en_attente'
    ORDER BY d.date_demande ASC
");
$stmt->execute();
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des messages non lus
$unread_messages = 0;
if ($employe_id > 0) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM message_destinataires 
        WHERE destinataire_id = :employe_id AND lu = 0
    ");
    $stmt->execute([':employe_id' => $employe_id]);
    $unread_messages = $stmt->fetchColumn();
}
// Récupérer les nouvelles demandes de badge (non lues)
$stmt_demandes = $pdo->prepare("
    SELECT d.id, d.employe_id, d.date_demande, d.raison, 
           e.nom, e.prenom, e.photo,
           TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) as heures_attente
    FROM demandes_badge d
    JOIN employes e ON d.employe_id = e.id
    WHERE d.statut = 'en_attente'
    AND d.is_read = 0
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt_demandes->execute();
$nouvelles_demandes = $stmt_demandes->fetchAll(PDO::FETCH_ASSOC);
$nombre_nouvelles_demandes = count($nouvelles_demandes);

// Mettre à jour le compteur de notifications
$total_notifications = $unread_count + $nombre_nouvelles_demandes;

// Configuration de la pagination
$itemsPerPage = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // S'assurer que la page est au moins 1
$offset = ($page - 1) * $itemsPerPage;

// Requête pour compter le nombre total d'éléments
$totalQuery = "SELECT COUNT(*) FROM employes";
$totalItems = $pdo->query($totalQuery)->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Requête principale avec pagination
$query = "SELECT * FROM employes LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="admin.css">
    <style>
      
    </style>
</head>
<body>
<div class="dashboard-container">
    <div class="dashboard-header">
       <h2 class="text-center mb-4">Bienvenue, <?php if (isset($_SESSION['prenom']) && is_string($_SESSION['prenom']) && isset($_SESSION['nom']) && is_string($_SESSION['nom'])): ?><?= ucfirst($_SESSION['prenom']) ?> <?= strtoupper($_SESSION['nom']) ?><?php else: ?><?= ucfirst($_SESSION['role']) ?><?php endif; ?> 👋</h2>
         
<!-- Barre de navigation améliorée -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary py-1">
    <div class="container-fluid">
        <!-- Partie gauche - Navigation principale -->
        <div class="d-flex flex-grow-1 gap-2">
            <button class="btn btn-nav active" onclick="switchPanel('pointage', this)">📋 Pointage</button>
            <button class="btn btn-nav" onclick="switchPanel('retard', this)">⏱ Retard</button>
            <button class="btn btn-nav" onclick="switchPanel('heures', this)">⏱ Heures</button>
            <?php if ($is_super_admin): ?>
                <button class="btn btn-nav" onclick="switchPanel('admins', this)">👮 Admins</button>
            <?php endif; ?>
            <button class="btn btn-nav" onclick="switchPanel('employes', this)">👷 Employés</button>
        </div>

        <!-- Partie droite - Outils utilisateur -->
        <div class="d-flex align-items-center gap-3">
            <!-- Messagerie -->
            <div class="nav-icon-container position-relative">
                <a class="nav-icon" href="messagerie.php" title="Messagerie">
                    <i class="fas fa-envelope fa-lg"></i>
                    <?php if (isset($_SESSION['employe_id']) && $unread > 0): ?>
                        <span class="notification-badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Notifications -->
            <div class="dropdown nav-icon-container">
                <a class="nav-icon" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" title="Notifications">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if ($total_notifications > 0): ?>
                        <span class="notification-badge"><?= $total_notifications ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Dropdown des notifications -->
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationsDropdown">
                    <!-- En-tête -->
                    <li class="dropdown-header bg-light py-2 d-flex justify-content-between">
                        <strong>Notifications</strong>
                        <?php if ($total_notifications > 0): ?>
                            <a href="#" class="mark-all-read text-primary" data-type="all">
                                <small>Marquer tout comme lu</small>
                            </a>
                        <?php endif; ?>
                    </li>
                    
                    <!-- Demandes de badge -->
                    <?php if ($nombre_nouvelles_demandes > 0): ?>
                        <li class="dropdown-header bg-light py-2 d-flex justify-content-between">
                            <strong>Demandes de badge</strong>
                            <a href="#" class="mark-all-read text-primary" data-type="badge">
                                <small>Marquer comme lu</small>
                            </a>
                        </li>
                        
                        <?php foreach ($nouvelles_demandes as $demande): ?>
                            <li>
                                <a class="dropdown-item" href="admin_demandes_badge.php">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="avatar-container">
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
                                                <strong>Nouvelle demande</strong>
                                                <small class="text-muted"><?= date('H:i', strtotime($demande['date_demande'])) ?></small>
                                            </div>
                                            <div class="text-truncate small">
                                                <?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?>
                                            </div>
                                            <div class="text-truncate small text-muted mt-1">
                                                <?= htmlspecialchars($demande['raison']) ?>
                                            </div>
                                            <?php if ($demande['heures_attente'] > 24): ?>
                                                <span class="badge bg-danger mt-1">Urgent</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider m-0"></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Messages non lus -->
                    <?php if ($unread_messages > 0): ?>
                        <li class="dropdown-header bg-light py-2 d-flex justify-content-between">
                            <strong>Messages</strong>
                            <a href="#" class="mark-all-read text-primary" data-type="message">
                                <small>Marquer comme lu</small>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="messagerie.php">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="notification-icon bg-primary">
                                        <i class="fas fa-envelope text-white"></i>
                                    </div>
                                    <div>
                                        <strong><?= $unread_messages ?> nouveau(x) message(s)</strong>
                                        <div class="small text-muted">Cliquez pour voir vos messages</div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider m-0"></li>
                    <?php endif; ?>
                    
                    <!-- Aucune notification -->
                    <?php if ($total_notifications === 0): ?>
                        <li class="text-center py-3">
                            <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                            <p class="mb-0 text-muted">Aucune notification</p>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Footer -->
                    <li class="dropdown-footer text-center py-2 bg-light">
                        <a href="admin_demandes_badge.php" class="text-primary small">Voir toutes les demandes</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
        <script>
        // Marquer les notifications comme lues
        document.querySelectorAll('.mark-all-read').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const type = this.dataset.type;
                
                fetch('mark_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        admin_id: <?= $_SESSION['admin_id'] ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            });
        });
        </script>
    </div>

    <div class="dashboard-content">
        <!-- SECTION POINTAGE -->
        <div id="panel-pointage" class="panel-section active-panel">
            <div class="card mb-4">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h4>Historique des Pointages (<?= $unread_count ?> nouveau(x))</h4>
                </div>
                <div class="card-body">
                    <div class="mb-2 d-flex gap-2">
                        <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('pointage-table')">📄 Export PDF</button>
                        <button class="btn btn-outline-success btn-sm" onclick="exportExcel('pointage-table')">📊 Export Excel</button>
                    </div>
                    <form method="get" class="mb-3 d-flex gap-2" id="dateFilterForm">
                        <input type="date" name="date" id="dateInput" class="form-control" value="<?= isset($_GET['date']) ? $_GET['date'] : '' ?>">
                        <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
                    </form>

                    <?php
                    // Configuration pagination
                    $perPage = 15;
                    $currentPage = isset($_GET['page_pointage']) ? max(1, (int)$_GET['page_pointage']) : 1;
                    $offset = ($currentPage - 1) * $perPage;
                    
                    // Modifier votre requête SQL pour inclure LIMIT
                    $sql = "SELECT ... LIMIT $offset, $perPage";
                    ?>

                    <?php if (!empty($grouped)): ?>
                    <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                        <table class="table table-bordered table-hover" id="pointage-table">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Nom et Prénom</th>
                                    <th>Date</th>
                                    <th>Heure d'arrivée</th>
                                    <th>Heure de départ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($grouped as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['prenom']) ?> <?= htmlspecialchars($entry['nom']) ?></td>
                                    <td><?= $entry['date'] ?></td>
                                    <td><?= $entry['arrivee'] ? '<span class="badge bg-success">'.$entry['arrivee'].'</span>' : '<span class="text-muted">Non pointé</span>' ?></td>
                                    <td><?= $entry['depart'] ? '<span class="badge bg-danger">'.$entry['depart'].'</span>' : '<span class="text-muted">Non pointé</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    
                    $totalItems = 0;// Nombre total d'items sans pagination
                    $totalPages = ceil($totalItems / $perPage);
                    ?>
                    
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_pointage' => $currentPage - 1])) ?>" aria-label="Previous">
                                        &laquo; Précédent
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_pointage' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_pointage' => $currentPage + 1])) ?>" aria-label="Next">
                                        Suivant &raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php else: ?>
                        <p>Aucun pointage trouvé pour la date sélectionnée.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SECTION HEURES -->
        <div id="panel-heures" class="panel-section">
            <div class="card mb-4">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <h5>Temps total travaillé par employé</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2 d-flex gap-2">
                        <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('heures-table')">📄 Export PDF</button>
                        <button class="btn btn-outline-success btn-sm" onclick="exportExcel('heures-table')">📊 Export Excel</button>
                    </div>
                    
                    <?php
                    // Configuration pagination
                    $perPageHeures = 10;
                    $currentPageHeures = isset($_GET['page_heures']) ? max(1, (int)$_GET['page_heures']) : 1;
                    $offsetHeures = ($currentPageHeures - 1) * $perPageHeures;
                    
                    // Modifier votre requête SQL pour inclure LIMIT
                    $sqlHeures = "SELECT ... LIMIT $offsetHeures, $perPageHeures";
                    ?>
                    
                    <?php if ($temps_totaux): ?>
                    <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                        <table class="table table-bordered table-sm table-hover" id="heures-table">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Prénom</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Temps total</th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php foreach ($temps_totaux as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['prenom']) ?></td>
                            <td><?= htmlspecialchars($t['nom']) ?></td>
                            <td><?= htmlspecialchars($t['email']) ?></td>
                            <td><?= $t['total_travail'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    $totalItemsHeures = 0; // Initialisation
                    $totalPagesHeures = ceil($totalItemsHeures / $perPageHeures);
                    ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($currentPageHeures > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_heures' => $currentPageHeures - 1])) ?>" aria-label="Previous">
                                        &laquo; Précédent
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPagesHeures; $i++): ?>
                                <li class="page-item <?= $i === $currentPageHeures ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_heures' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($currentPageHeures < $totalPagesHeures): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_heures' => $currentPageHeures + 1])) ?>" aria-label="Next">
                                        Suivant &raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php else: ?>
                        <p>Aucune donnée disponible.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SECTION ADMINS -->
        <div id="panel-admins" class="panel-section">
            <?php if ($is_super_admin): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-shield me-2"></i>
                            <h4 class="mb-0">Gestion des Administrateurs</h4>
                        </div>
                        <div>
                            <a href="ajouter_admin.php" class="btn btn-light btn-sm">
                                <i class="fas fa-plus-circle me-1"></i> Nouvel Admin
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('admins-table')">
                                    <i class="fas fa-file-pdf me-1"></i> Export PDF
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="exportExcel('admins-table')">
                                    <i class="fas fa-file-excel me-1"></i> Export Excel
                                </button>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" id="adminSearch" class="form-control" placeholder="Rechercher...">
                                </div>
                            </div>
                        </div>

                        <?php if (count($admins) > 0): ?>
                        <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                            <table class="table table-hover align-middle" id="admins-table">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 50px;"></th>
                                        <th>Nom</th>
                                        <th>Contact</th>
                                        <th>Statut</th>
                                        <th>Dernière activité</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($admins as $admin):
                                    $initiale = strtoupper(substr($admin['prenom'], 0, 1)) . strtoupper(substr($admin['nom'], 0, 1));
                                    $isActive = false;
                                    if ($admin['last_activity'] !== null) {
                                        $isActive = strtotime($admin['last_activity']) > strtotime('-30 minutes');
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="avatar-circle bg-primary d-flex align-items-center justify-content-center"
                                                 style="width: 40px; height: 40px;">
                                                <span class="text-white"><?= $initiale ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($admin['role']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($admin['email']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($admin['telephone'] ?? 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $isActive ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= ($admin['last_activity'] !== null) ? date('d/m/Y H:i', strtotime($admin['last_activity'])) : 'Jamais' ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="modifier_admin.php?id=<?= $admin['id'] ?>"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete_admin=<?= $admin['id'] ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')"
                                                   title="Supprimer">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                                <a href="reset_password_admin.php?id=<?= $admin['id'] ?>"
                                                   class="btn btn-sm btn-outline-warning"
                                                   title="Réinitialiser mot de passe">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination dynamique -->
                        <?php
                        $perPageAdmins = 10;
                        $currentPageAdmins = isset($_GET['page_admins']) ? max(1, (int)$_GET['page_admins']) : 1;
                        $totalPagesAdmins = ceil(count($admins) / $perPageAdmins);
                        ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted">
                                <?= count($admins) ?> admin<?= count($admins) > 1 ? 's' : '' ?> trouvé<?= count($admins) > 1 ? 's' : '' ?>
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $currentPageAdmins <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_admins' => $currentPageAdmins - 1])) ?>" tabindex="-1">
                                            &laquo; Précédent
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPagesAdmins; $i++): ?>
                                        <li class="page-item <?= $i == $currentPageAdmins ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_admins' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $currentPageAdmins >= $totalPagesAdmins ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_admins' => $currentPageAdmins + 1])) ?>">
                                            Suivant &raquo;
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-shield fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun administrateur trouvé</h5>
                            <p class="text-muted">Commencez par ajouter un nouvel administrateur</p>
                            <a href="ajouter_admin.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus-circle me-1"></i> Ajouter un admin
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="panel-employes" class="panel-section  active-panel">
<?php if ($is_super_admin || $_SESSION['role'] === 'admin'): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-users me-2"></i> <h4 class="mb-0">Gestion des Employés</h4>
                </div>
                <div>
                    <a href="ajouter_employe.php" class="btn btn-light btn-sm">
                        <i class="fas fa-plus-circle me-1"></i> Nouvel Employé
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('employes-table')">
                            <i class="fas fa-file-pdf me-1"></i> Export PDF
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="exportExcel('employes-table')">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="employeeSearch" class="form-control" placeholder="Rechercher...">
                        </div>
                    </div>
                </div>

                <?php if (count($employes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="employes-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;"></th>
                                <th>Nom</th>
                                <th>Contact</th>
                                <th>Département</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employes as $e):
                                $initiale = strtoupper(substr($e['prenom'], 0, 1)) . strtoupper(substr($e['nom'], 0, 1));
                                $departementClass = [
                                    'depart_formation' => 'bg-info',
                                    'depart_communication' => 'bg-warning',
                                    'depart_informatique' => 'bg-primary',
                                    'depart_consulting' => 'bg-success',
                                    'depart_marketing&vente' => 'bg-success',
                                    'administration' => 'bg-secondary'
                                ][$e['departement']] ?? 'bg-dark';
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($e['photo'])): ?>
                                            <img src="<?= htmlspecialchars($e['photo']) ?>"
                                                 class="rounded-circle"
                                                 width="40" height="40"
                                                 alt="<?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?>"
                                                 style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle <?= $departementClass ?> d-flex align-items-center justify-content-center"
                                                 style="width: 40px; height: 40px;">
                                                <span class="text-white"><?= $initiale ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($e['poste']) ?></small>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="mailto:<?= htmlspecialchars($e['email']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($e['email']) ?>
                                            </a>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($e['telephone'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $departementClass ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('depart_', '', $e['departement']))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="profil_employe.php?id=<?= $e['id'] ?>"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Voir profil">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?delete_employe=<?= $e['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Supprimer cet employé ?')"
                                               title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination pagination-sm justify-content-end mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1" aria-disabled="true"><i class="fas fa-chevron-left"></i> Précédent</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i> Suivant</a>
                        </li>
                    </ul>
                </nav>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun employé trouvé</h5>
                        <p class="text-muted">Commencez par ajouter un nouvel employé</p>
                        <a href="ajouter_employe.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle me-1"></i> Ajouter un employé
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

        <!-- SECTION RETARD -->
        <div id="panel-retard" class="panel-section">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h4><i class="fas fa-clock me-2"></i> Retards à justifier</h4>
                </div>
                <div class="card-body">
                    <?php
                    // Configuration pagination
                    $perPageRetard = 10;
                    $currentPageRetard = isset($_GET['page_retard']) ? max(1, (int)$_GET['page_retard']) : 1;
                    $offsetRetard = ($currentPageRetard - 1) * $perPageRetard;
                    
                    $retards = $pdo->query("
                        SELECT p.id, e.prenom, e.nom, p.date_heure, 
                               TIMEDIFF(p.date_heure, CONCAT(DATE(p.date_heure), ' 09:00:00')) as retard,
                               p.retard_cause, p.retard_justifie
                        FROM pointages p
                        JOIN employes e ON p.employe_id = e.id
                        WHERE p.type = 'arrivee' 
                        AND TIME(p.date_heure) > '09:00:00'
                        ORDER BY p.date_heure DESC
                        LIMIT $offsetRetard, $perPageRetard
                    ")->fetchAll();
                    ?>
                    
                    <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                        <table class="table table-striped">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Employé</th>
                                    <th>Date</th>
                                    <th>Retard</th>
                                    <th>Cause</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($retards as $retard): 
                                    $minutes = date('i', strtotime($retard['retard']));
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($retard['prenom'].' '.$retard['nom']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($retard['date_heure'])) ?></td>
                                        <td><?= $minutes ?> minutes</td>
                                        <td>
                                            <?php if ($retard['retard_justifie']): ?>
                                                <?= htmlspecialchars($retard['retard_cause']) ?>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Non justifié</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$retard['retard_justifie']): ?>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rappelModal<?= $retard['id'] ?>">
                                                    <i class="fas fa-bell"></i> Rappeler
                                                </button>
                                                
                                                <!-- Modal Rappel -->
                                                <div class="modal fade" id="rappelModal<?= $retard['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Envoyer un rappel</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form action="envoyer_rappel.php" method="POST">
                                                                <input type="hidden" name="pointage_id" value="<?= $retard['id'] ?>">
                                                                <div class="modal-body">
                                                                    <p>Employé: <?= htmlspecialchars($retard['prenom'].' '.$retard['nom']) ?></p>
                                                                    <p>Retard: <?= $minutes ?> minutes le <?= date('d/m/Y', strtotime($retard['date_heure'])) ?></p>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Message personnalisé (optionnel)</label>
                                                                        <textarea name="message" class="form-control" rows="3"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                    <button type="submit" class="btn btn-primary">Envoyer rappel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    $totalItemsRetard = 0; // Nombre total de retards sans pagination
                    $totalPagesRetard = ceil($totalItemsRetard / $perPageRetard);
                    ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($currentPageRetard > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_retard' => $currentPageRetard - 1])) ?>" aria-label="Previous">
                                        &laquo; Précédent
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPagesRetard; $i++): ?>
                                <li class="page-item <?= $i === $currentPageRetard ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_retard' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($currentPageRetard < $totalPagesRetard): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_retard' => $currentPageRetard + 1])) ?>" aria-label="Next">
                                        Suivant &raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <div class="dashboard-footer">
        <a href="logout.php" class="btn btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')">Déconnexion</a>
    </div>
    </div>
</div>

<!-- Scripts communs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
// Gestion du changement de date
document.getElementById('dateInput').addEventListener('change', function() {
    document.getElementById('dateFilterForm').submit();
});

// Navigation entre les panneaux
function switchPanel(panelId, btn) {
    const panels = ['pointage', 'retard', 'heures', 'admins', 'employes'];
    
    panels.forEach(id => {
        const panel = document.getElementById('panel-' + id);
        panel.style.display = 'none';
        panel.classList.remove('active-panel');
    });
    
    const activePanel = document.getElementById('panel-' + panelId);
    if (activePanel) {
        activePanel.style.display = 'block';
        activePanel.classList.add('active-panel');
    }
    
    document.querySelectorAll('.btn-nav').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
}

// FONCTIONS EXPORTATION
function exportPDF(tableId) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({ html: '#' + tableId });
    doc.save(tableId + '.pdf');
}

function exportExcel(tableId) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
    XLSX.writeFile(wb, tableId + ".xlsx");
}

// Recherche dans les tableaux
document.addEventListener('DOMContentLoaded', function() {
    // Recherche employés
    const employeSearch = document.getElementById('employeSearch');
    if (employeSearch) {
        employeSearch.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#employes-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    }
    
    // Recherche admins
    const adminSearch = document.getElementById('adminSearch');
    if (adminSearch) {
        adminSearch.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#admins-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    }
    
    // Infinite scroll (optionnel)
    const tableContainers = document.querySelectorAll('.table-responsive');
    tableContainers.forEach(container => {
        container.addEventListener('scroll', function() {
            const { scrollTop, scrollHeight, clientHeight } = this;
            const threshold = 100;
            
            if (scrollHeight - scrollTop - clientHeight < threshold) {
                // Implémenter le chargement supplémentaire ici
            }
        });
    });
    
    // Activer le premier panneau au chargement
    switchPanel('pointage');
});

// Gestion de la modal de justification
function openJustifyModal(date, type) {
    document.getElementById('justifyDate').value = date;
    document.getElementById('justifyType').value = type;
    
    if (type === 'retard') {
        document.getElementById('justifyModalTitle').textContent = 'Justifier le retard du ' + date;
    } else {
        document.getElementById('justifyModalTitle').textContent = 'Autoriser l\'absence du ' + date;
    }
    
    document.getElementById('estJustifie').checked = false;
    document.getElementById('commentaire').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('justifyModal'));
    modal.show();
}
</script>
</body>