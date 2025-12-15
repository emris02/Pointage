<<<<<<< HEAD
<?php
/**
 * Dashboard administrateur - Redirection vers la version unifiée
 */
header("Location: admin_dashboard_unifie.php");
exit();

// Vérification de l'authentification
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    header("Location: login.php");
    exit();
}

$isAdmin = true;
$pageTitle = 'Dashboard Administrateur - Xpert Pro';
$pageHeader = 'Dashboard Administrateur';
$pageDescription = 'Vue d\'ensemble du système de pointage';

// Flag super admin (cohérence avec les partials)
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN;

// Récupération des données
$pointageController = new PointageController($pdo);
$employeController = new EmployeController($pdo);

$todayStats = $pointageController->getStats();
$lateArrivals = $pointageController->getLateArrivals();
$todayPointages = $pointageController->getTodayPointages();
$employes = $employeController->index();

// Valeurs par défaut pour éviter panels vides / notices
$grouped = $grouped ?? [];
$temps_totaux = $temps_totaux ?? [];
$demandes = $demandes ?? [];
$stats = $stats ?? ['total' => 0, 'en_attente' => 0, 'approuve' => 0, 'rejete' => 0];
$unread_count = $unread_count ?? 0;

$additionalCSS = ['assets/css/admin.css'];
?>

<?php include 'partials/header.php'; ?>
<?php include 'src/views/partials/sidebar_canonique.php'; ?>

<div class="row">
    <!-- Statistiques principales -->
    <div class="container-fluid p-0">
    <div class="row g-0 flex-nowrap" style="min-height:100vh;">
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header card shadow-sm mb-4 p-4 bg-white rounded-4 border-0" style="margin-top: 0 !important;">
                
            <!-- Cards statistiques RH connectées à la base -->
            <?php
            // Récupération des statistiques RH en temps réel
            $today = date('Y-m-d');
            // Utiliser des variables dédiées pour éviter les conflits avec la pagination
            $total_employes_stats = $pdo->query("SELECT COUNT(*) FROM employes")->fetchColumn();
            $present_today = $pdo->query("SELECT COUNT(DISTINCT employe_id) FROM pointages WHERE type = 'arrivee' AND DATE(date_heure) = '$today'")->fetchColumn();
            $retards_today = $pdo->query("SELECT COUNT(*) FROM pointages WHERE type = 'arrivee' AND TIME(date_heure) > '09:00:00' AND DATE(date_heure) = '$today'")->fetchColumn();
            $absents = $total_employes_stats - $present_today;
            ?>
            <div class="row g-1 mb-2" style="margin-bottom:15px !important; margin-top:15px !important;">
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card total h-100" style="border-radius: 8px; min-height: 50px; background: rgba(67,97,238,0.18); box-shadow: 0 1px 4px rgba(67,97,238,0.08); margin-bottom:10px;">
                        <div class="card-body text-center py-2 px-1">
                            <div class="mb-1">
                                <i class="fas fa-users" style="font-size:1.3rem;color:#0672e4;"></i>
                            </div>
                            <div class="stat-count fw-bold" style="font-size:1.3rem;color:#0672e4;" id="count-employes"><?= (int)$total_employes_stats ?></div>
                            <div class="text-primary" style="font-size:0.85rem;opacity:0.8;">Total employés</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card approuve h-100" style="border-radius: 8px; min-height: 50px; background: rgba(76,201,240,0.18); box-shadow: 0 1px 4px rgba(76,201,240,0.08); margin-bottom:10px;">
                        <div class="card-body text-center py-2 px-1">
                            <div class="mb-1">
                                <i class="fas fa-user-check" style="font-size:1.3rem;color:#4cc9f0;"></i>
                            </div>
                            <div class="stat-count fw-bold" style="font-size:1.3rem;color:#4cc9f0;" id="count-presents"><?= (int)$present_today ?></div>
                            <div class="text-info" style="font-size:0.85rem;opacity:0.8;">Présents aujourd'hui</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card en_attente h-100" style="border-radius: 8px; min-height: 50px; background: rgba(248,150,30,0.18); box-shadow: 0 1px 4px rgba(248,150,30,0.08); margin-bottom:10px;">
                        <div class="card-body text-center py-2 px-1">
                            <div class="mb-1">
                                <i class="fas fa-user-times" style="font-size:1.3rem;color:#f8961e;"></i>
                            </div>
                            <div class="stat-count fw-bold" style="font-size:1.3rem;color:#f8961e;" id="count-absents"><?= (int)$absents ?></div>
                            <div class="text-warning" style="font-size:0.85rem;opacity:0.8;">Absents aujourd'hui</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card stat-card rejete h-100" style="border-radius: 8px; min-height: 50px; background: rgba(249,65,68,0.18); box-shadow: 0 1px 4px rgba(249,65,68,0.08); margin-bottom:10px;">
                        <div class="card-body text-center py-2 px-1">
                            <div class="mb-1">
                                <i class="fas fa-clock" style="font-size:1.3rem;color:#f94144;"></i>
                            </div>
                            <div class="stat-count fw-bold" style="font-size:1.3rem;color:#f94144;" id="count-retards"><?= (int)$retards_today ?></div>
                            <div class="text-danger" style="font-size:0.85rem;opacity:0.8;">Retards du jour</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANELS DYNAMIQUES (pointage, demandes, employés, admins, retards, heures, etc.) -->
            <div class="dashboard-content" style="margin-top:15px;">
                <!-- Les panels existants sont conservés et modernisés ci-dessous -->

                <!-- SECTION POINTAGE -->
                <div id="pointage" class="panel-section active-panel" style="display:block;">
                    <?php // Affichage du panel Pointage (restauré)
                    if (!empty($grouped)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h4>Historique des Pointages (<?= $unread_count ?> nouveau(x))</h4>
                            <i class="fas fa-tachometer-alt me-2"></i>
                        </div>
                        <div class="card-body">
                            <div class="mb-2 d-flex gap-2">
                                <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('pointage-table')">📄 Export PDF</button>
                                <button class="btn btn-outline-success btn-sm" onclick="exportExcel('pointage-table')">📊 Export Excel</button>
                            </div>
                            <form method="get" class="mb-3 d-flex gap-2" id="dateFilterForm">
                                <input type="date" name="date" id="dateInput" class="form-control" value="<?= isset($_GET['date']) ? $_GET['date'] : '' ?>">
                                <a href="admin_dashboard_unifie.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
                            </form>
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
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-4">Aucun pointage trouvé pour la date sélectionnée.</div>
                    <?php endif; ?>
                </div>

                <!-- SECTION HEURES -->
                <div id="heures" class="panel-section" style="display:none;">
                    <?php // Affichage du panel Heures (restauré)
                    if ($temps_totaux): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                            <h4>Temps total travaillé par employé</h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-2 d-flex gap-2">
                                <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('heures-table')">📄 Export PDF</button>
                                <button class="btn btn-outline-success btn-sm" onclick="exportExcel('heures-table')">📊 Export Excel</button>
                            </div>
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
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-4">Aucune donnée disponible.</div>
                    <?php endif; ?>
                </div>

                <!-- SECTION DEMANDES (affichage du partial) -->
                <div id="demandes" class="panel-section" style="display:none;">
<script>
// Animation des compteurs statistiques DEMANDES
document.addEventListener('DOMContentLoaded', function() {
    function animateCount(id, end, color, duration = 1200) {
        const el = document.getElementById(id);
        if (!el) return;
        let start = 0;
        const step = Math.max(1, Math.ceil(end / (duration / 20)));
        el.style.transition = 'color 0.4s';
        el.style.color = color;
        function update() {
            start += step;
            if (start >= end) {
                el.textContent = end;
            } else {
                el.textContent = start;
                setTimeout(update, 20);
            }
        }
        update();
    }
    animateCount('count-demandes-total', parseInt(document.getElementById('count-demandes-total').dataset.value || 0), '#0672e4');
    animateCount('count-demandes-attente', parseInt(document.getElementById('count-demandes-attente').dataset.value || 0), '#f8961e');
    animateCount('count-demandes-approuve', parseInt(document.getElementById('count-demandes-approuve').dataset.value || 0), '#4cc9f0');
    animateCount('count-demandes-rejete', parseInt(document.getElementById('count-demandes-rejete').dataset.value || 0), '#f94144');
});
</script>
                    <?php
                    // Correction de l'erreur : initialisation de $total_demandes si non défini
                    if (!isset($total_demandes)) {
                        $total_demandes = isset($demandes) ? count($demandes) : 0;
                    }
                    ?>
                    <div class="container-fluid px-0">
                        <div class="card mb-4 w-100">
                            <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">Gestion des demandes</h4>
                                <i class="fas fa-tasks me-2"></i>
                            </div>
                            <div class="card-body" style="padding-left: 5px; padding-right: 5px;">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card stat-card total h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-muted mb-2">Total Demandes</h6>
                                                        <h3 class="mb-0"><span id="count-demandes-total" data-value="<?= (int)($stats['total'] ?? 0) ?>">0</span></h3>
                                                        <small class="text-muted"><?= date('d M Y') ?></small>
                                                    </div>
                                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                                        <i class="fas fa-list-alt text-primary fs-4"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card stat-card en_attente h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-muted mb-2">En Attente</h6>
                                                        <h3 class="mb-0"><span id="count-demandes-attente" data-value="<?= (int)($stats['en_attente'] ?? 0) ?>">0</span></h3>
                                                        <small class="text-muted">Non traitées</small>
                                                    </div>
                                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                                        <i class="fas fa-clock text-warning fs-4"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card stat-card approuve h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-muted mb-2">Approuvées</h6>
                                                        <h3 class="mb-0"><span id="count-demandes-approuve" data-value="<?= (int)($stats['approuve'] ?? 0) ?>">0</span></h3>
                                                        <small class="text-muted">Ce mois-ci</small>
                                                    </div>
                                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                                        <i class="fas fa-check-circle text-success fs-4"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="card stat-card rejete h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="text-muted mb-2">Rejetées</h6>
                                                        <h3 class="mb-0"><span id="count-demandes-rejete" data-value="<?= (int)($stats['rejete'] ?? 0) ?>">0</span></h3>
                                                        <small class="text-muted">Ce mois-ci</small>
                                                    </div>
                                                    <div class="bg-danger bg-opacity-10 p-3 rounded">
                                                        <i class="fas fa-times-circle text-danger fs-4"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-3">
                                    <div class="card-header bg-white border-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des demandes</h5>
                                            <span class="badge bg-primary">
                                                <?= $total_demandes ?> demande(s) trouvée(s)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                <div class="table-responsive">
                                            <table class="table table-hover align-middle" id="demandesTableDash">
                                                <thead class="table-light">
                                            <tr>
                                                <th>Employé</th>
                                                <th>Type</th>
                                                <th>Date</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                                <?php foreach ($demandes as $demande): ?>
                                                    <?php
                                                $nomComplet = trim(($demande['prenom'] ?? '') . ' ' . ($demande['nom'] ?? ''));
                                                $poste = $demande['poste'] ?? '';
                                                $departement = $demande['departement'] ?? '';
                                                $type = $demande['type'] ?? '';
                                                $dateDemande = !empty($demande['date_demande']) ? date('d/m/Y H:i', strtotime($demande['date_demande'])) : '';
                                                $statut = $demande['statut'] ?? '';
                                                        $heuresEcoulees = $demande['heures_ecoulees'] ?? 0;
                                                $isUrgent = ($heuresEcoulees < 24 && $statut === 'en_attente');
                                                $badgeClass = [
                                                    'approuve' => 'success',
                                                    'rejete' => 'danger',
                                                    'en_attente' => 'warning'
                                                ][$statut] ?? 'secondary';
                                                $badgeIcon = [
                                                    'approuve' => 'check',
                                                    'rejete' => 'times',
                                                    'en_attente' => 'clock'
                                                ][$statut] ?? 'question';
                                                $initiales = strtoupper(substr($demande['prenom'] ?? '',0,1) . substr($demande['nom'] ?? '',0,1));
                                            ?>
                                            <tr class="<?= $isUrgent ? 'table-danger' : '' ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                                <?php if (!empty($demande['photo'])): ?>
                                                                    <img src="<?= htmlspecialchars($demande['photo']) ?>"
                                                                         class="avatar me-3"
                                                                         style="width:40px;height:40px;object-fit:cover;border-radius:50%;"
                                                                         alt="Photo de <?= htmlspecialchars($nomComplet) ?>"
                                                                         onerror="this.src='assets/img/profil.png'">
                                                        <?php else: ?>
                                                                    <div class="avatar-initials bg-secondary text-white d-flex align-items-center justify-content-center me-3"
                                                                         style="width:40px;height:40px;border-radius:50%;font-weight:bold;">
                                                                <?= $initiales ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-0"><?= htmlspecialchars($nomComplet) ?></h6>
                                                                    <small class="text-muted">
                                                                        <?= htmlspecialchars($poste) ?> • <?= htmlspecialchars($departement) ?>
                                                                    </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                                <?= htmlspecialchars(ucfirst($type)) ?>
                                                            </span>
                                                </td>
                                                <td>
                                                    <?= $dateDemande ?>
                                                            <?php if ($isUrgent): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger ms-2" aria-label="Demande urgente">URGENT</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $badgeClass ?> badge-status">
                                                                <i class="fas fa-<?= $badgeIcon ?> me-1"></i>
                                                                <?= htmlspecialchars(ucfirst($statut)) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                            <button class="btn btn-sm btn-outline-primary btn-action view-details details-btn"
                                                                    data-id="<?= (int)$demande['id'] ?>"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#detailsModal"
                                                                    title="Détails de la demande">
                                                        <i class="fas fa-eye me-1"></i> Détails
                                                    </button>
                                                            <?php if ($demande['statut'] === 'en_attente'): ?>
                                                                <button class="btn btn-sm btn-success btn-action ms-1" onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'approuve')">
                                                            <i class="fas fa-check"></i> Accorder
                                                        </button>
                                                                <button class="btn btn-sm btn-danger btn-action ms-1" onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'rejete')">
                                                            <i class="fas fa-times"></i> Rejeter
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

        <!-- SECTION ADMINS -->
        <div id="admins" class="panel-section" style="display:none;">
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
                                                <a href="profil_admin.php?id=<?= $admin['id'] ?>"
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

        <div id="employes" class="panel-section" style="display:none;">
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
                                        <?php
                                        $photoSrc = '';
                                        if (!empty($e['photo'])) {
                                            $photoSrc = htmlspecialchars($e['photo']);
                                            if (strpos($e['photo'], 'uploads/') !== false) {
                                                $photoSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($e['photo']));
                                            }
                                        }
                                        if (!empty($photoSrc)): ?>
                                            <img src="<?= $photoSrc ?>"
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
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-success"
                                                                title="Régénérer le badge"
                                                                onclick="regenerateBadgeFor(<?= (int)$e['id'] ?>)">
                                                            <i class="fas fa-id-card"></i>
                                                        </button>
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
        <div id="retard" class="panel-section" style="display:none;">
            <div class="card mb-4">
                        <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                            <h4>Retards à justifier</h4>
                    <i class="fas fa-clock me-2"></i>
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
<div id="calendrier" class="panel-section" style="display:none;">
                    <div class="container my-4">
                        <div class="filter-card shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Calendrier des événements</h4>
                <button type="button" class="btn btn-primary btn-sm" id="addEventBtn">
                    <i class="fas fa-plus me-1"></i> Ajouter un événement
                </button>
            </div>
            <p class="text-muted mb-3" style="font-size:0.98em;">
                <i class="fas fa-info-circle me-1"></i> Cliquez sur une date pour ajouter un événement (réunion, congé, formation, autre).<br>
                <i class="fas fa-mouse-pointer me-1"></i> Cliquez sur un événement pour voir le détail.<br>
                <i class="fas fa-arrows-alt me-1"></i> Glissez-déposez pour déplacer un événement.
                            </p>
                            <div id="calendar-admin"></div>
            <div id="calendar-loading" class="text-center my-3" style="display:none;">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div>
                            </div>

            <!-- Modal ajout événement amélioré -->
                            <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                  <form id="addEventForm">
                    <div class="modal-header bg-primary text-white">
                      <h5 class="modal-title" id="addEventModalLabel">
                        <i class="fas fa-calendar-plus me-2"></i>Ajouter un événement
                      </h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                            </div>
                                            <div class="modal-body">
                      <div class="row">
                        <div class="col-md-6">
                                                <div class="mb-3">
                            <label for="eventTitle" class="form-label fw-bold">Titre de l'événement *</label>
                            <input type="text" class="form-control" id="eventTitle" name="titre" required placeholder="Ex: Réunion équipe RH">
                                                </div>
                                                <div class="mb-3">
                            <label for="eventType" class="form-label fw-bold">Type d'événement *</label>
                            <select class="form-select" id="eventType" name="type" required>
                              <option value="">Sélectionner un type</option>
                              <option value="réunion">📅 Réunion</option>
                              <option value="congé">🏖️ Congé</option>
                              <option value="formation">📚 Formation</option>
                              <option value="événement">🎉 Événement</option>
                              <option value="maintenance">🔧 Maintenance</option>
                              <option value="autre">📌 Autre</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                            <label for="eventDate" class="form-label fw-bold">Date *</label>
                            <input type="date" class="form-control" id="eventDate" name="date" required>
                                                </div>
                        </div>
                        <div class="col-md-6">
                                                <div class="mb-3">
                            <label for="eventTime" class="form-label">Heure de début</label>
                            <input type="time" class="form-control" id="eventTime" name="heure">
                                                </div>
                                                <div class="mb-3">
                            <label for="eventEndTime" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="eventEndTime" name="heure_fin">
                          </div>
                          <div class="mb-3">
                            <label for="eventPriority" class="form-label">Priorité</label>
                            <select class="form-select" id="eventPriority" name="priorite">
                              <option value="normale">🟢 Normale</option>
                              <option value="importante">🟡 Importante</option>
                              <option value="urgente">🔴 Urgente</option>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label for="eventDesc" class="form-label fw-bold">Description</label>
                        <textarea class="form-control" id="eventDesc" name="description" rows="3" placeholder="Décrivez l'événement, les participants, les objectifs..."></textarea>
                      </div>
                      <div class="mb-3">
                        <label for="eventLocation" class="form-label">Lieu</label>
                        <input type="text" class="form-control" id="eventLocation" name="lieu" placeholder="Ex: Salle de réunion A, Bureau 203...">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Annuler
                      </button>
                      <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Ajouter l'événement
                      </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

            <!-- Modal détails événement -->
            <div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="eventDetailsModalLabel">
                      <i class="fas fa-calendar-check me-2"></i>Détails de l'événement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                  <div class="modal-body" id="eventDetailsContent">
                    <!-- Le contenu sera rempli dynamiquement -->
                    </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="deleteEventBtn">
                      <i class="fas fa-trash me-1"></i>Supprimer
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
    </div>
</div>
            </div>
        </div>
    </div>
</div>
</script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<!-- Scripts communs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
// Régénération/Création de badge pour un employé (admin)
function regenerateBadgeFor(employeId) {
    if (!confirm('Régénérer/Créer le badge pour cet employé ?')) return;
    fetch('api/regenerate_badge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ employe_id: employeId })
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.success) {
            alert('Badge régénéré avec succès. Expire le: ' + (data.expires_at || 'bientôt'));
        } else {
            alert('Échec régénération: ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(() => alert('Erreur réseau lors de la régénération du badge.'));
}

// Ajout d'événement calendrier (déjà pris en charge par addEventBtn si présent)
document.getElementById('addEventBtn')?.addEventListener('click', function() {
    // Cette action est déjà gérée dans assets/js/calendrier.js via showEventModal()
});
</script>

<script>
// Gestion du changement de date
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('dateInput');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            document.getElementById('dateFilterForm').submit();
        });
    }
});

// Navigation entre les panneaux avec persistance avancée
function switchPanel(panelId, btn) {
    // Liste dynamique des panels visibles (sans notifications, admins si non super_admin)
    let panels = ["pointage", "retard", "heures", "employes", "demandes"];
    <?php if ($is_super_admin): ?>
    panels.splice(3, 0, "admins");
    <?php endif; ?>
    panels.push("calendrier");
    // Masquer tous les panels
    panels.forEach(id => {
        const panel = document.getElementById(id);
        if (panel) {
            panel.style.display = 'none';
            panel.classList.remove('active-panel');
        }
    });
    // Afficher le panel demandé
    const activePanel = document.getElementById(panelId);
    if (activePanel) {
        activePanel.style.display = 'block';
        activePanel.classList.add('active-panel');
    }
    // Mettre à jour le hash de l'URL (toujours sans préfixe)
    window.location.hash = panelId;
    // Persister le panel actif dans le sessionStorage
    sessionStorage.setItem('lastPanel', panelId);
    // Gérer l'état actif des boutons
    document.querySelectorAll('.btn-nav').forEach(b => b.classList.remove('active'));
    // Trouver le bouton correspondant de façon robuste (préférer data-panel)
    if (!btn) {
        btn = document.querySelector('[data-panel="' + panelId + '"]') || document.querySelector('.btn-nav[href="#' + panelId + '"]');
    }
    if (btn) btn.classList.add('active');
}

// Afficher le bon panneau au chargement selon le hash ou sessionStorage
window.addEventListener('DOMContentLoaded', () => {
    let panel = 'pointage';
    // Liste des panels valides (sans notifications)
    let validPanels = ["pointage", "retard", "heures", "employes", "demandes"<?php if ($is_super_admin): ?>, "admins"<?php endif; ?>, "calendrier"];
    if (window.location.hash) {
        let hash = window.location.hash.replace('#', '');
        if (validPanels.includes(hash)) {
            panel = hash;
        }
    } else if (sessionStorage.getItem('lastPanel')) {
        const last = sessionStorage.getItem('lastPanel');
        if (validPanels.includes(last)) {
            panel = last;
        }
    }
    const btn = document.querySelector('[data-panel="' + panel + '"]') || document.querySelector('.btn-nav[href="#' + panel + '"]');
    switchPanel(panel, btn);
});

// Persistance du panel après action (pagination, reload, etc.)
window.addEventListener('popstate', function() {
    let panel = 'pointage';
    let validPanels = ["pointage", "retard", "heures", "employes", "demandes"<?php if ($is_super_admin): ?>, "admins"<?php endif; ?>, "calendrier"];
    if (window.location.hash) {
        let hash = window.location.hash.replace('#', '');
        if (validPanels.includes(hash)) {
            panel = hash;
        }
    } else if (sessionStorage.getItem('lastPanel')) {
        const last = sessionStorage.getItem('lastPanel');
        if (validPanels.includes(last)) {
            panel = last;
        }
    }
    const btn = document.querySelector('[data-panel="' + panel + '"]') || document.querySelector('.btn-nav[href="#' + panel + '"]');
    switchPanel(panel, btn);
});

// Après une action AJAX (approuver/rejeter), rester sur le même panel/page
function reloadPanelAfterAction() {
    const hash = window.location.hash;
    if (hash) {
        location.href = location.pathname + location.search + hash;
    } else {
        location.reload();
    }
}

// Écouteur sur les clics de la sidebar pour basculer les panneaux sans rechargement
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-nav').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href') || '';
            const panelId = href.startsWith('#') ? href.substring(1) : href;
            if (panelId) {
                switchPanel(panelId, this);
            }
        });
    });
});

// Gérer les changements de hash (ex: navigation arrière/avant ou ancre externe)
window.addEventListener('hashchange', function() {
    const hash = window.location.hash.replace('#','');
    if (hash) {
        const btn = document.querySelector('[data-panel="' + hash + '"]') || document.querySelector('.btn-nav[href="#' + hash + '"]');
        switchPanel(hash, btn);
    }
});

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
});

// SCRIPT TOGGLE SIDEBAR
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('main');
    
    // Créer l'overlay pour mobile
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    // Récupérer l'état de la sidebar depuis localStorage
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

    // Appliquer l'état initial
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
        sidebarToggle.classList.add('rotated');
    }

    // Fonction pour basculer la sidebar
    function toggleSidebar() {
        console.log('Toggle sidebar appelé'); // Debug
        
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        sidebarToggle.classList.toggle('rotated');
        
        // Sauvegarder l'état dans localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        
        // Gestion mobile
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        console.log('Sidebar collapsed:', sidebar.classList.contains('collapsed')); // Debug
    }

    // Événement click sur le bouton toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Clic sur sidebar toggle'); // Debug
            toggleSidebar();
        });
    }

    // Fermer la sidebar en cliquant sur l'overlay (mobile)
    overlay.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    });

    // Gestion du redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    });

    // Raccourci clavier (Ctrl/Cmd + B)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            toggleSidebar();
        }
    });

    // Vérifier que les éléments existent
    console.log('Sidebar toggle:', sidebarToggle); // Debug
    console.log('Sidebar:', sidebar); // Debug
    console.log('Main content:', mainContent); // Debug
});

// Marquer toutes les notifications comme lues
function markAllNotificationsRead() {
    fetch('delete_notifications.php')
        .then(() => location.reload());
}
</script>
</div>

<?php
$additionalJS = ['assets/js/admin.js'];
$inlineJS = "
function deletePointage(pointageId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce pointage ?')) {
        fetch('api/delete_pointage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: pointageId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}
";
include 'partials/footer.php';
?>
=======
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
>>>>>>> 2fc47109b0d43eb3be3464bd2a12f9f4e8f82762
