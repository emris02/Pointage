<?php
/**
 * Page Profil Employé - Xpert Pro
 * Interface moderne et responsive avec toutes les fonctionnalités
 * @version 2.0.0
 */

require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
require_once 'src/services/BadgeManager.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

// Déterminer l'ID de l'employé ciblé
$employe_id = $_GET['id'] ?? $_SESSION['employe_id'];

// Si l'utilisateur connecté n'est pas administrateur, rediriger vers son dashboard employé (pas d'accès à la page profil complète)
if (!(isset($_SESSION['role']) && in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_SUPER_ADMIN]))) {
    header('Location: employe_dashboard.php');
    exit();
}

// Récupération des informations de l'employé
require_once 'src/controllers/EmployeController.php';
require_once 'src/controllers/BadgeController.php';

$employeController = new EmployeController($pdo);
$badgeController = new BadgeController($pdo);
$employe = $employeController->show($employe_id);

if (!$employe) {
    header('Location: admin_dashboard_unifie.php?error=employee_not_found');
    exit();
}

// Récupération ou génération du badge
$badgeToken = null;
$stmt = $pdo->prepare("SELECT * FROM badge_tokens WHERE employe_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$employe_id]);
$badgeToken = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$badgeToken) {
    $result = BadgeManager::regenerateToken($employe_id, $pdo);
    if (isset($result['status']) && $result['status'] === 'success') {
        $badgeToken = [
            'token' => $result['token'],
            'token_hash' => $result['token_hash'],
            'expires_at' => $result['expires_at'],
            'status' => 'active'
        ];
    }
} else {
    $validite = BadgeManager::generateToken($employe_id);
    $badgeToken['message'] = $validite['message'] ?? 'Aucun message disponible';
}

$badge_actif = !empty($badgeToken);

// Calcul de l'ancienneté
$dateEmbauche = new DateTime($employe['date_creation']);
$aujourdhui = new DateTime();
$difference = $dateEmbauche->diff($aujourdhui);
$anciennete = $difference->y . ' an' . ($difference->y > 1 ? 's' : '');
if ($difference->m > 0) {
    $anciennete .= ' et ' . $difference->m . ' mois';
}

// Configuration des couleurs par département
$departementColors = [
    'depart_formation' => ['bg' => 'bg-info', 'text' => 'text-info', 'light' => 'bg-info-light'],
    'depart_communication' => ['bg' => 'bg-warning', 'text' => 'text-warning', 'light' => 'bg-warning-light'],
    'depart_informatique' => ['bg' => 'bg-primary', 'text' => 'text-primary', 'light' => 'bg-primary-light'],
    'depart_grh' => ['bg' => 'bg-success', 'text' => 'text-success', 'light' => 'bg-success-light'],
    'administration' => ['bg' => 'bg-secondary', 'text' => 'text-secondary', 'light' => 'bg-secondary-light']
];

$departementConfig = $departementColors[$employe['departement']] ?? ['bg' => 'bg-dark', 'text' => 'text-dark', 'light' => 'bg-dark-light'];
$departementLabel = ucfirst(str_replace('depart_', '', $employe['departement']));
$initiale = strtoupper(substr($employe['prenom'], 0, 1)) . strtoupper(substr($employe['nom'], 0, 1));

// Récupération des pointages récents
$pointages = $pdo->prepare("
    SELECT type, date_heure 
    FROM pointages 
    WHERE employe_id = ? 
    ORDER BY date_heure DESC 
    LIMIT 10
");
$pointages->execute([$employe_id]);
$derniers_pointages = $pointages->fetchAll(PDO::FETCH_ASSOC);

// Calcul du temps de travail mensuel en sommant les paires arrivée->départ (plus fiable que SUM(temps_total))
$temps_mensuel = $pdo->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, p1.date_heure, p2.date_heure)), 0) as seconds
        FROM pointages p1
        JOIN pointages p2 ON p1.employe_id = p2.employe_id
                AND DATE(p1.date_heure) = DATE(p2.date_heure)
                AND p1.type = 'arrivee' AND p2.type = 'depart' AND p2.date_heure > p1.date_heure
        WHERE p1.employe_id = ?
            AND DATE(p1.date_heure) BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01') AND LAST_DAY(NOW())");
$temps_mensuel->execute([$employe_id]);
$seconds = (int)$temps_mensuel->fetchColumn();

// Formater en HH:MM:SS (heures cumulées, potentiellement > 24h)
$hours = floor($seconds / 3600);
$minutes = floor(($seconds % 3600) / 60);
$secs = $seconds % 60;
$temps_travail = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);

// Calcul des statistiques de pointage (sans retards)
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pointages,
        SUM(CASE WHEN type = 'arrivee' THEN 1 ELSE 0 END) as total_arrivees,
        SUM(CASE WHEN type = 'depart' THEN 1 ELSE 0 END) as total_departs,
        DATE_FORMAT(MIN(date_heure), '%d/%m/%Y') as premiere_arrivee
    FROM pointages 
    WHERE employe_id = ?
");
$stats_stmt->execute([$employe_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Récupération du nombre total de retards depuis la table retards
$retards_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_retards 
    FROM retards 
    WHERE employe_id = ?
");
$retards_stmt->execute([$employe_id]);
$total_retards = $retards_stmt->fetchColumn();

// On peut ensuite ajouter le total_retards dans le tableau $stats pour uniformité
$stats['total_retards'] = $total_retards;

// Maintenant $stats contient : total_pointages, total_arrivees, total_departs, premiere_arrivee et total_retards


// Gestion des actions POST
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['regenerer_badge'])) {
            $result = BadgeManager::regenerateToken($employe_id, $pdo);
            if (isset($result['status']) && $result['status'] === 'success') {
                header("Location: profil_employe.php?id=$employe_id&success=badge_regenerer");
                exit();
            } else {
                $error_message = $result['message'] ?? 'Erreur lors de la régénération.';
            }
        } elseif (isset($_POST['demander_badge'])) {
            $result = BadgeManager::regenerateToken($employe_id, $pdo);
            if (isset($result['status']) && $result['status'] === 'success') {
                header("Location: profil_employe.php?id=$employe_id&success=badge_cree");
                exit();
            } else {
                $error_message = $result['message'] ?? 'Erreur lors de la création.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Erreur: ' . $e->getMessage();
    }
}

// Gestion des messages de succès
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'badge_regenerer':
            $success_message = '✅ Badge régénéré avec succès.';
            break;
        case 'badge_cree':
            $success_message = '✅ Badge créé avec succès.';
            break;
    }
}

// Configuration de la page
$pageTitle = 'Profil de ' . $employe['prenom'] . ' ' . $employe['nom'] . ' | Xpert Pro';
$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_SUPER_ADMIN]);
$additionalCSS = [
    'assets/css/profil.css?v=' . time(),
    // 'assets/css/components/cards.css?v=' . time()
];

$bodyClass = $isAdmin ? 'has-sidebar' : '';

include 'partials/header.php';

if ($isAdmin) {
    include 'src/views/partials/sidebar_canonique.php';
}
?>

<div class="main-container">
    <!-- ================= PROFILE HEADER ================= -->
    <header class="profile-header">
        <div class="profile-info d-flex align-items-center justify-content-between flex-column flex-md-row">
            
            <!-- ===== AVATAR ===== -->
            <div class="profile-avatar-wrapper position-relative mb-4 mb-md-0">
                <?php
                if (!empty($employe['photo'])) {
                    if (strpos($employe['photo'], 'uploads/') !== false) {
                        $avatarSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($employe['photo']));
                    } else {
                        $avatarSrc = $employe['photo'];
                    }
                } else {
                    $avatarSrc = 'assets/img/profil.jpg';
                }
                ?>

                <img src="<?= htmlspecialchars($avatarSrc) ?>"
                     alt="<?= htmlspecialchars($employe['prenom'] ?? 'Employé') ?>"
                     class="profile-avatar rounded-circle shadow-lg"
                     onerror="this.src='assets/img/profil.jpg'">

                <?php
                $statusClass = ($employe['status'] ?? '') === 'active'
                    ? 'status-online'
                    : 'status-offline';
                ?>
                <span class="avatar-status <?= $statusClass ?> border-white border-2"></span>

                <?php if (empty($employe['photo'])): ?>
                    <div class="avatar-initials rounded-circle <?= $departementConfig['bg'] ?? 'bg-secondary' ?> d-flex align-items-center justify-content-center shadow">
                        <?= $initiale ?? 'X' ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== INFOS PROFIL ===== -->
            <div class="profile-details ms-0 ms-md-4 text-center text-md-start flex-grow-1">
                <h1 class="profile-name fw-bold mb-2">
                    <?= htmlspecialchars(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? '')) ?>
                </h1>

                <p class="profile-title fs-5 text-muted mb-2">
                    <i class="fas fa-briefcase me-2"></i>
                    <?= htmlspecialchars($employe['poste'] ?? 'Non défini') ?>
                </p>

                <div class="profile-department badge <?= $departementConfig['text'] ?? 'text-muted bg-light' ?> px-3 py-2 fs-6">
                    <i class="fas fa-building me-2"></i>
                    <?= htmlspecialchars($departementLabel ?? 'Département inconnu') ?>
                </div>

                <div class="profile-meta mt-3 d-flex flex-column flex-sm-row justify-content-center justify-content-md-start gap-2 gap-sm-3">
                    <span class="meta-item badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-hashtag me-1"></i>
                        Matricule : XPERT-<?= strtoupper(substr($employe['departement'] ?? 'XXX', 0, 3)) ?><?= $employe['id'] ?? '0' ?>
                    </span>

                    <span class="meta-item badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Ancienneté : <?= $anciennete ?? 'N/A' ?>
                    </span>
                </div>
            </div>

            <!-- ===== ACTIONS ===== -->
            <div class="profile-actions mt-4 mt-md-0 ms-auto d-flex align-items-center gap-2 flex-wrap justify-content-center">
                <!-- TOGGLE COLLAPSE -->
                <button class="btn btn-light profile-toggle rounded-circle"
                        id="toggleProfileHeader"
                        type="button"
                        title="Réduire / Déployer">
                    <i class="fas fa-chevron-up"></i>
                </button>

                <!-- RETOUR -->
                <a href="<?= $isAdmin ? 'admin_dashboard_unifie.php' : 'employe_dashboard.php' ?>"
                   class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> <span class="d-none d-md-inline">Retour</span>
                </a>

                <!-- CONTACT -->
                <a href="mailto:<?= htmlspecialchars($employe['email'] ?? '') ?>"
                   class="btn btn-primary">
                    <i class="fas fa-envelope me-1"></i> <span class="d-none d-md-inline">Contacter</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="main-content py-4">
        <div class="container-fluid px-3 px-md-4">
            <!-- Messages d'alerte -->
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-3 fs-5"></i>
                    <div class="flex-grow-1">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
                    <div class="flex-grow-1">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section Informations - NOUVELLE DISPOSITION -->
            <div class="row g-4">
                <!-- Colonne gauche : Informations personnelles ET Badge -->
                <div class="col-lg-8">
                    <div class="row g-4">
                        <!-- Carte Informations personnelles -->
                        <div class="col-lg-8">
                            <div class="card profile-card shadow-sm border-0 h-100">
                                <div class="card-header bg-white border-0 pb-0 d-flex align-items-center justify-content-between">
                                    <h5 class="card-title fw-semibold mb-0">
                                        <i class="fas fa-user-circle me-2 text-primary"></i>
                                        Informations personnelles
                                    </h5>
                                    <span class="badge bg-primary-subtle text-primary px-3 py-2">
                                        <i class="fas fa-info-circle me-1"></i> Détails
                                    </span>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="info-grid">
                                        <!-- Ligne 1 : Email et Téléphone -->
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light h-100">
                                                    <div class="info-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <i class="fas fa-envelope fs-5"></i>
                                                    </div>
                                                    <div class="info-content flex-grow-1">
                                                        <div class="info-label text-muted small mb-1">Email professionnel</div>
                                                        <a href="mailto:<?= htmlspecialchars($employe['email']) ?>" class="info-value fw-semibold text-decoration-none text-primary">
                                                            <?= htmlspecialchars($employe['email']) ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light h-100">
                                                    <div class="info-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <i class="fas fa-phone fs-5"></i>
                                                    </div>
                                                    <div class="info-content flex-grow-1">
                                                        <div class="info-label text-muted small mb-1">Téléphone</div>
                                                        <a href="tel:<?= htmlspecialchars($employe['telephone']) ?>" class="info-value fw-semibold text-decoration-none text-primary">
                                                            <?= htmlspecialchars($employe['telephone']) ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Ligne 2 : Adresse et Date d'embauche -->
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light h-100">
                                                    <div class="info-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <i class="fas fa-map-marker-alt fs-5"></i>
                                                    </div>
                                                    <div class="info-content flex-grow-1">
                                                        <div class="info-label text-muted small mb-1">Adresse</div>
                                                        <div class="info-value fw-semibold"><?= htmlspecialchars($employe['adresse']) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light h-100">
                                                    <div class="info-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <i class="fas fa-calendar-plus fs-5"></i>
                                                    </div>
                                                    <div class="info-content flex-grow-1">
                                                        <div class="info-label text-muted small mb-1">Date d'embauche</div>
                                                        <div class="info-value fw-semibold">
                                                            <?= date('d/m/Y', strtotime($employe['date_creation'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Ligne 3 : Statut et Badge intégré -->
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light h-100">
                                                    <div class="info-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <i class="fas fa-id-card fs-5"></i>
                                                    </div>
                                                    <div class="info-content flex-grow-1">
                                                        <div class="info-label text-muted small mb-1">Statut</div>
                                                        <div class="info-value">
                                                            <span class="badge <?= ($employe['status'] ?? '') === 'active' ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
                                                                <?= isset($stats['status']) ? $stats['status'] : 'N/A' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <!-- Badge intégré aux infos perso -->
                                                <div class="info-item badge-item d-flex align-items-center p-3 rounded-3 bg-light h-100">
                                                    <div class="info-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                        <i class="fas fa-id-badge fs-5"></i>
                                                    </div>
                                                    <div class="info-content flex-grow-1">
                                                        <div class="info-label text-muted small mb-1">Badge d'accès</div>
                                                        <div class="info-value d-flex align-items-center justify-content-between">
                                                            <?php if ($badge_actif && isset($badgeToken['token'])): ?>
                                                                <span class="badge bg-success px-3 py-2">
                                                                    <i class="fas fa-check-circle me-1"></i> ACTIF
                                                                </span>
                                                                <small class="text-muted ms-2">Valide jusqu'au <?= date('d/m/Y', strtotime($badgeToken['expires_at'])) ?></small>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary px-3 py-2">
                                                                    <i class="fas fa-times-circle me-1"></i> INACTIF
                                                                </span>
                                                                <small class="text-muted ms-2">Aucun badge actif</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Carte QR Code Badge (à côté des infos) -->
                        <div class="col-lg-4">
                            <div class="card profile-card shadow-sm border-0 h-100">
                                <div class="card-header bg-white border-0 pb-0 text-center">
                                    <h5 class="card-title fw-semibold mb-0">
                                        <i class="fas fa-qrcode me-2 text-success"></i>
                                        QR Code
                                    </h5>
                                </div>
                                <div class="card-body pt-3 d-flex flex-column align-items-center justify-content-center">
                                    <?php if ($badge_actif && isset($badgeToken['token'])): ?>
                                        <div class="qr-code-container position-relative mb-4">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($badgeToken['token']) ?>&format=svg&margin=10&color=2563eb&bgcolor=f8fafc" 
                                                 class="qr-code-img rounded-3 shadow" 
                                                 alt="QR Code du badge"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#badgeModal"
                                                 style="cursor: pointer; transition: transform 0.3s;"
                                                 onmouseover="this.style.transform='scale(1.05)'"
                                                 onmouseout="this.style.transform='scale(1)'">
                                            <div class="qr-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-10 rounded-3 opacity-0" style="transition: opacity 0.3s;">
                                                <i class="fas fa-expand-alt text-white fs-3"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center mt-3">
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-clock me-1"></i>
                                                Valide jusqu'au
                                            </p>
                                            <p class="fw-bold fs-6 mb-3">
                                                <?= date('d/m/Y à H:i', strtotime($badgeToken['expires_at'])) ?>
                                            </p>
                                            
                                            <form method="POST" class="mt-3">
                                                <button type="submit" name="regenerer_badge" class="btn btn-primary btn-sm w-100 py-2">
                                                    <i class="fas fa-sync-alt me-2"></i> Régénérer
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-badge-section py-4 text-center">
                                            <div class="no-badge-icon mb-3">
                                                <i class="fas fa-qrcode fa-4x text-muted opacity-50"></i>
                                            </div>
                                            <h6 class="mb-2 fw-semibold">Badge non disponible</h6>
                                            <p class="text-muted small mb-4">Aucun QR Code actif</p>
                                            
                                            <form method="POST">
                                                <button type="submit" name="demander_badge" class="btn btn-primary btn-sm px-4 py-2">
                                                    <i class="fas fa-plus-circle me-2"></i> Créer
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques du mois - En dessous -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card profile-card shadow-sm border-0">
                                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                                    <h5 class="card-title fw-semibold mb-0">
                                        <i class="fas fa-chart-bar me-2 text-info"></i>
                                        Statistiques du mois
                                    </h5>
                                    <span class="badge bg-info-subtle text-info px-3 py-2">
                                        <?= date('F Y') ?>
                                    </span>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="stats-grid">
                                        <div class="row g-3">
                                            <!-- Heures travaillées -->
                                            <div class="col-md-3 col-6">
                                                <div class="stat-card text-center p-3 rounded-3 bg-light h-100">
                                                    <?php $tt_parts = explode(':', $temps_travail); $display_hrs = ($tt_parts[0] ?? '00') . ':' . ($tt_parts[1] ?? '00'); ?>
                                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                                        <i class="fas fa-clock fs-4"></i>
                                                    </div>
                                                    <div class="stat-number fw-bold fs-3 mb-1"><?= htmlspecialchars($display_hrs) ?>h</div>
                                                    <div class="stat-label text-muted small mb-2">Heures travaillées</div>
                                                    <div class="stat-trend text-success small">
                                                        <i class="fas fa-arrow-up me-1"></i> 12%
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Pointages total -->
                                            <div class="col-md-3 col-6">
                                                <div class="stat-card text-center p-3 rounded-3 bg-light h-100">
                                                    <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                                        <i class="fas fa-calendar-check fs-4"></i>
                                                    </div>
                                                    <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['total_pointages'] ?? 0 ?></div>
                                                    <div class="stat-label text-muted small mb-2">Pointages total</div>
                                                    <div class="stat-trend text-muted small">
                                                        <i class="fas fa-history me-1"></i> Depuis <?= $stats['premiere_arrivee'] ?? 'N/A' ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Arrivées -->
                                            <div class="col-md-3 col-6">
                                                <div class="stat-card text-center p-3 rounded-3 bg-light h-100">
                                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                                        <i class="fas fa-sign-in-alt fs-4"></i>
                                                    </div>
                                                    <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['total_arrivees'] ?? 0 ?></div>
                                                    <div class="stat-label text-muted small mb-2">Arrivées</div>
                                                    <div class="stat-trend text-info small">
                                                        <i class="fas fa-chart-line me-1"></i> 8h30
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Retards -->
                                            <div class="col-md-3 col-6">
                                                <div class="stat-card text-center p-3 rounded-3 bg-light h-100">
                                                    <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                                        <i class="fas fa-exclamation-triangle fs-4"></i>
                                                    </div>
                                                    <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['total_retards'] ?? 0 ?></div>
                                                    <div class="stat-label text-muted small mb-2">Retards</div>
                                                    <div class="stat-trend text-success small">
                                                        <i class="fas fa-arrow-down me-1"></i> 25%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 pt-3 border-top text-center">
                                            <a href="historique_pointages.php?id=<?= $employe['id'] ?>" class="btn btn-outline-primary py-2 px-4">
                                                <i class="fas fa-chart-line me-2"></i> Voir les statistiques détaillées
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne droite : Activités récentes -->
                <div class="col-lg-4">
                    <div class="card profile-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title fw-semibold mb-0">
                                    <i class="fas fa-history me-2 text-secondary"></i>
                                    Activité récente
                                </h5>
                                <span class="badge bg-primary px-3 py-2">
                                    Aujourd'hui
                                </span>
                            </div>
                        </div>
                        <div class="card-body pt-3">
                            <?php if (!empty($derniers_pointages)): ?>
                                <div class="activity-timeline">
                                    <?php foreach ($derniers_pointages as $index => $pointage): 
                                        $isToday = date('Y-m-d', strtotime($pointage['date_heure'])) === date('Y-m-d');
                                        $isArrival = $pointage['type'] === 'arrivee';
                                    ?>
                                        <div class="activity-item <?= $index === 0 ? 'activity-current' : '' ?> d-flex p-3 rounded-3 <?= $index === 0 ? 'bg-primary bg-opacity-5' : 'bg-light' ?> mb-3">
                                            <div class="activity-indicator position-relative me-3">
                                                <div class="activity-dot <?= $isArrival ? 'dot-success' : 'dot-primary' ?> border-2 border-white shadow-sm"></div>
                                                <?php if ($index < count($derniers_pointages) - 1): ?>
                                                    <div class="activity-line"></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-content flex-grow-1">
                                                <div class="activity-header d-flex flex-column flex-sm-row justify-content-between mb-2">
                                                    <div class="activity-title fw-semibold">
                                                        <?= $isArrival ? 'Arrivée au travail' : 'Départ du travail' ?>
                                                        <?php if ($isToday): ?>
                                                            <span class="badge bg-primary-subtle text-primary ms-2">Aujourd'hui</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="activity-time text-muted text-sm-end">
                                                        <?= date('H:i', strtotime($pointage['date_heure'])) ?>
                                                        <?php if (!empty($pointage['duration_formatted'])): ?>
                                                            <div class="text-muted small">Durée: <span class="duration-text"><?= htmlspecialchars($pointage['duration_formatted']) ?></span>
                                                            <?php if (!empty($pointage['ongoing'])): ?>
                                                                <span class="badge bg-success ms-1">En cours</span>
                                                                <div><small>Temps écoulé: <span class="live-timer" data-start="<?= htmlspecialchars($pointage['date_heure']) ?>" data-initial-seconds="<?= (int)($pointage['duration_seconds'] ?? 0) ?>">--:--:--</span></small></div>
                                                            <?php endif; ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="activity-meta d-flex gap-3">
                                                    <span class="meta-item badge bg-light text-dark">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?= date('d/m/Y', strtotime($pointage['date_heure'])) ?>
                                                    </span>
                                                    <span class="meta-item badge bg-light text-dark">
                                                        <i class="fas fa-<?= $isArrival ? 'sun' : 'moon' ?> me-1"></i>
                                                        <?= $isArrival ? 'Matin' : 'Soir' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-4 pt-3 border-top">
                                    <a href="historique_pointages.php?id=<?= $employe['id'] ?>" class="btn btn-link text-primary text-decoration-none fw-semibold">
                                        <i class="fas fa-list me-2"></i> Voir tout l'historique
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state text-center py-5">
                                    <div class="empty-icon mb-3">
                                        <i class="fas fa-clock fa-4x text-muted opacity-50"></i>
                                    </div>
                                    <h5 class="empty-title fw-semibold mb-2">Aucune activité</h5>
                                    <p class="empty-text text-muted mb-0">
                                        Aucun pointage enregistré pour le moment
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Performance -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card profile-card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                            <h5 class="card-title fw-semibold mb-0">
                                <i class="fas fa-trophy me-2 text-warning"></i>
                                Performance et indicateurs
                            </h5>
                            <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                <i class="fas fa-chart-line me-1"></i> Indicateurs clés
                            </span>
                        </div>
                        <div class="card-body pt-3">
                            <div class="row g-3">
                                <!-- Présence -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                                    <div class="metric-card p-3 rounded-3 bg-light h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="metric-icon bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                <i class="fas fa-user-check fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="metric-value fw-bold fs-2 text-success mb-0">98%</div>
                                                <div class="metric-label text-muted small">Présence</div>
                                            </div>
                                        </div>
                                        <div class="metric-progress mt-auto">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: 98%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Taux de retard -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                                    <div class="metric-card p-3 rounded-3 bg-light h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="metric-icon bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                <i class="fas fa-clock fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="metric-value fw-bold fs-2 text-warning mb-0">2.3%</div>
                                                <div class="metric-label text-muted small">Taux de retard</div>
                                            </div>
                                        </div>
                                        <div class="metric-progress mt-auto">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-warning" style="width: 2.3%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Moyenne hebdo -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                                    <div class="metric-card p-3 rounded-3 bg-light h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="metric-icon bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                <i class="fas fa-chart-bar fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="metric-value fw-bold fs-2 text-info mb-0">42.5h</div>
                                                <div class="metric-label text-muted small">Moyenne hebdo</div>
                                            </div>
                                        </div>
                                        <div class="metric-progress mt-auto">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-info" style="width: 85%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Satisfaction -->
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                                    <div class="metric-card p-3 rounded-3 bg-light h-100 d-flex flex-column">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="metric-icon bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                                <i class="fas fa-star fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="metric-value fw-bold fs-2 text-primary mb-0">4.8/5</div>
                                                <div class="metric-label text-muted small">Satisfaction</div>
                                            </div>
                                        </div>
                                        <div class="metric-progress mt-auto">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: 96%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action card -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card profile-card action-card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-lg-4 text-center text-lg-start mb-3 mb-lg-0">
                                    <h5 class="fw-semibold mb-1">Actions administratives</h5>
                                    <p class="text-muted small mb-0">Gérez rapidement le compte de cet employé</p>
                                </div>
                                <div class="col-lg-8">
                                    <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-end">
                                        <!-- Groupe Actions principales -->
                                        <div class="btn-group" role="group">
                                            <a href="modifier_employe.php?id=<?= $employe['id'] ?>" class="btn btn-primary px-4 py-2">
                                                <i class="fas fa-edit me-2"></i> Modifier profil
                                            </a>
                                            <button class="btn btn-primary dropdown-toggle dropdown-toggle-split px-3" type="button" data-bs-toggle="dropdown">
                                                <span class="visually-hidden">Plus d'actions</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                                        <i class="fas fa-key me-2"></i> Réinitialiser mot de passe
                                                    </button>
                                                </li>
                                                <li>
                                                    <a href="historique_pointages.php?id=<?= $employe['id'] ?>" class="dropdown-item">
                                                        <i class="fas fa-history me-2"></i> Voir l'historique
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <!-- Groupe Statut -->
                                        <?php if (($employe['statut'] ?? '') === 'actif'): ?>
                                            <button id="btn-deactivate-employee" class="btn btn-warning px-4 py-2">
                                                <i class="fas fa-user-slash me-2"></i> Désactiver
                                            </button>
                                        <?php else: ?>
                                            <button id="btn-activate-employee" class="btn btn-success px-4 py-2">
                                                <i class="fas fa-user-check me-2"></i> Activer
                                            </button>
                                        <?php endif; ?>

                                        <!-- Suppression -->
                                        <button id="btn-delete-inline" class="btn btn-danger px-4 py-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash me-2"></i> Supprimer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
// Live timers for ongoing sessions
function pad(n){return n<10? '0'+n : n}
function updateTimers(){
    document.querySelectorAll('.live-timer').forEach(function(el){
        const start = el.dataset.start;
        const initial = parseInt(el.dataset.initialSeconds || '0', 10);
        const startTs = Date.parse(start)/1000;
        if (!isNaN(startTs)){
            const now = Math.floor(Date.now()/1000);
            const secs = Math.max(0, initial + (now - startTs));
            const h = Math.floor(secs/3600);
            const m = Math.floor((secs%3600)/60);
            const s = secs%60;
            el.textContent = pad(h)+":"+pad(m)+":"+pad(s);
        }
    });
}
setInterval(updateTimers, 1000);
updateTimers();

// Toggle profile header collapse
document.getElementById('toggleProfileHeader').addEventListener('click', function() {
    const header = document.querySelector('.profile-header');
    const icon = this.querySelector('i');
    const isCollapsed = header.style.maxHeight && header.style.maxHeight !== 'none';
    
    if (isCollapsed) {
        header.style.maxHeight = '';
        header.style.opacity = '1';
        icon.className = 'fas fa-chevron-up';
        this.title = 'Réduire';
    } else {
        header.style.maxHeight = '80px';
        header.style.opacity = '0.9';
        icon.className = 'fas fa-chevron-down';
        this.title = 'Déployer';
    }
});
</script>
<!-- Modal Badge -->
<div class="modal fade" id="badgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white rounded-top">
                <h5 class="modal-title fw-semibold">
                    <i class="fas fa-id-card me-2"></i>
                    Badge d'accès - <?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <?php if ($badge_actif && isset($badgeToken['token'])): ?>
                    <div class="badge-modal-content">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <div class="badge-qr">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($badgeToken['token']) ?>&format=svg&margin=20&color=2563eb&bgcolor=ffff" 
                                         class="img-fluid rounded-3 shadow" 
                                         alt="QR Code">
                                </div>
                            </div>
                            <div class="col-md-6 text-start">
                                <div class="badge-info">
                                    <h4 class="employee-name fw-bold mb-3"><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></h4>
                                    <p class="employee-position text-muted fs-5 mb-2">
                                        <i class="fas fa-briefcase me-2"></i>
                                        <?= htmlspecialchars($employe['poste']) ?>
                                    </p>
                                    <p class="employee-department <?= $departementConfig['text'] ?> fs-5 mb-4">
                                        <i class="fas fa-building me-2"></i>
                                        <?= htmlspecialchars($departementLabel) ?>
                                    </p>
                                </div>
                                
                                <div class="badge-details mt-4">
                                    <div class="detail-item d-flex align-items-center mb-3">
                                        <i class="fas fa-hashtag me-3 text-primary fs-5"></i>
                                        <div>
                                            <div class="text-muted small">Matricule</div>
                                            <div class="fw-bold">XPERT-<?= strtoupper(substr($employe['departement'], 0, 3)) ?><?= $employe['id'] ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item d-flex align-items-center">
                                        <i class="fas fa-clock me-3 text-primary fs-5"></i>
                                        <div>
                                            <div class="text-muted small">Date d'expiration</div>
                                            <div class="fw-bold"><?= date('d/m/Y à H:i', strtotime($badgeToken['expires_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning shadow-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Aucun badge actif disponible
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light rounded-bottom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <?php if ($badge_actif): ?>
                    <button type="button" class="btn btn-primary" onclick="printBadge()">
                        <i class="fas fa-print me-1"></i> Imprimer
                    </button>
                    <button type="button" class="btn btn-success" onclick="downloadQRCode()">
                        <i class="fas fa-download me-1"></i> Télécharger
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Réinitialisation mot de passe -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="reset_password.php" method="POST" id="resetPasswordForm">
                <div class="modal-header bg-primary text-white rounded-top">
                    <h5 class="modal-title fw-semibold">
                        <i class="fas fa-key me-2"></i>
                        Réinitialiser le mot de passe
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
                    
                    <div class="alert alert-info shadow-sm mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle me-3 fs-4"></i>
                            <div>
                                Le nouveau mot de passe sera envoyé par email à l'employé.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control py-2" name="new_password" id="newPassword" 
                                   placeholder="Saisir le nouveau mot de passe" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="newPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted">Minimum 8 caractères avec majuscule, minuscule et chiffre</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control py-2" name="confirm_password" id="confirmPassword" 
                                   placeholder="Confirmer le mot de passe" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="password-strength mt-4">
                        <div class="strength-meter bg-light rounded" style="height: 6px;">
                            <div class="strength-bar rounded" style="height: 100%; width: 0%;"></div>
                        </div>
                        <div class="strength-text small mt-2 text-muted"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white rounded-top">
                <h5 class="modal-title fw-semibold">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmer la suppression
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger shadow-sm">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle fa-2x"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="alert-heading fw-bold">Attention !</h5>
                            <p class="mb-0">
                                Vous êtes sur le point de supprimer l'employé <strong><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></strong>.
                                Cette action est irréversible et supprimera toutes les données associées.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="confirmDelete" style="width: 20px; height: 20px;">
                    <label class="form-check-label ms-2 fw-semibold" for="confirmDelete">
                        Je confirme vouloir supprimer cet employé
                    </label>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger px-4" id="deleteEmployeeBtn" disabled>
                    <i class="fas fa-trash me-1"></i> Supprimer définitivement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Configuration
const PROFILE_CONFIG = {
    employeId: <?= $employe['id'] ?>,
    employeName: "<?= addslashes($employe['prenom'] . ' ' . $employe['nom']) ?>",
    badgeActive: <?= $badge_actif ? 'true' : 'false' ?>,
    badgeExpiresAt: "<?= $badgeToken['expires_at'] ?? '' ?>",
    qrCodeUrl: "<?= $badge_actif ? 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($badgeToken['token']) : '' ?>"
};

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    console.log('👤 Profil employé initialisé');
    
    initProfile();
    initModals();
    initAnimations();
    initBadgeTimer();
});

// Fonctions principales
function initProfile() {
    // Animation d'entrée
    animateCards();
    
    // Initialiser les interactions
    initInteractions();
    
    // Mettre à jour les indicateurs
    updateIndicators();
}

function initModals() {
    // Modal suppression
    const confirmCheckbox = document.getElementById('confirmDelete');
    const deleteBtn = document.getElementById('deleteEmployeeBtn');
    
    if (confirmCheckbox && deleteBtn) {
        confirmCheckbox.addEventListener('change', function() {
            deleteBtn.disabled = !this.checked;
        });
        
        deleteBtn.addEventListener('click', function() {
            deleteEmployee();
        });
    }
    
    // Modal mot de passe
    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            resetPassword();
        });
    }
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    
    // Vérification force mot de passe
    const newPassword = document.getElementById('newPassword');
    if (newPassword) {
        newPassword.addEventListener('input', checkPasswordStrength);
    }
}

function initAnimations() {
    // Observer pour animations au scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    // Observer les cartes
    document.querySelectorAll('.profile-card').forEach(card => {
        observer.observe(card);
    });
}

function animateCards() {
    const cards = document.querySelectorAll('.profile-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('card-animate');
    });
}

function initInteractions() {
    // Hover sur les cartes de stats
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Click sur QR code
    const qrCode = document.querySelector('.qr-code-img');
    if (qrCode) {
        qrCode.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('badgeModal'));
            modal.show();
        });
    }

    // Profile actions: activation / deactivation / suppression
    const btnActivate = document.getElementById('btn-activate-employee');
    const btnDeactivate = document.getElementById('btn-deactivate-employee');
    const deleteEmployeeBtn = document.getElementById('deleteEmployeeBtn');
    const confirmDelete = document.getElementById('confirmDelete');

    function updateStatusUI(isActive) {
        // avatar status
        const avatar = document.querySelector('.avatar-status');
        if (avatar) {
            avatar.classList.toggle('status-online', isActive);
            avatar.classList.toggle('status-offline', !isActive);
        }

        // Update dropdown actions to show correct toggle
        const actionContainer = document.querySelector('.profile-actions');
        if (!actionContainer) return;
        const existingActivate = document.getElementById('btn-activate-employee');
        const existingDeactivate = document.getElementById('btn-deactivate-employee');

        if (isActive) {
            if (existingActivate) existingActivate.remove();
            if (!existingDeactivate) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-warning';
                btn.id = 'btn-deactivate-employee';
                btn.innerHTML = '<i class="fas fa-user-slash me-1"></i> Désactiver';
                btn.dataset.id = PROFILE_CONFIG.employeId;
                actionContainer.insertBefore(btn, actionContainer.querySelector('.dropdown'));
                btn.addEventListener('click', handleDeactivate);
            }
        } else {
            if (existingDeactivate) existingDeactivate.remove();
            if (!existingActivate) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-success';
                btn.id = 'btn-activate-employee';
                btn.innerHTML = '<i class="fas fa-user-check me-1"></i> Activer';
                btn.dataset.id = PROFILE_CONFIG.employeId;
                actionContainer.insertBefore(btn, actionContainer.querySelector('.dropdown'));
                btn.addEventListener('click', handleActivate);
            }
        }
    }

    async function handleActivate(e) {
        e.preventDefault();
        const confirm = await Swal.fire({
            title: 'Activer l\'employé ?',
            text: 'L\'employé pourra à nouveau se connecter et être pointé.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Oui, activer'
        });
        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch('activate_employe.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ employe_id: PROFILE_CONFIG.employeId })
            });
            if (!resp.ok) {
                const txt = await resp.text();
                Swal.fire({ title: 'Erreur', text: txt || 'Erreur serveur', icon: 'error' });
                return;
            }
            let json;
            try {
                json = await resp.json();
            } catch (parseErr) {
                Swal.fire({ title: 'Erreur', text: 'Réponse inattendue du serveur', icon: 'error' });
                return;
            }

            if (json.success) {
                Swal.fire({ title: 'Activé', text: json.message || 'Employé activé', icon: 'success', timer: 1800, showConfirmButton: false });
                updateStatusUI(true);
            } else {
                Swal.fire({ title: 'Erreur', text: json.message || 'Impossible d\'activer', icon: 'error' });
            }
        } catch (err) {
            Swal.fire({ title: 'Erreur', text: 'Erreur réseau ou serveur', icon: 'error' });
        }
    }

    async function handleDeactivate(e) {
        e.preventDefault();
        const confirm = await Swal.fire({
            title: 'Désactiver l\'employé ?',
            text: 'L\'employé ne pourra plus se connecter ni être pointé.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Oui, désactiver'
        });
        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch('deactivate_employe.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ employe_id: PROFILE_CONFIG.employeId })
            });
            if (!resp.ok) {
                const txt = await resp.text();
                Swal.fire({ title: 'Erreur', text: txt || 'Erreur serveur', icon: 'error' });
                return;
            }
            let json;
            try {
                json = await resp.json();
            } catch (parseErr) {
                Swal.fire({ title: 'Erreur', text: 'Réponse inattendue du serveur', icon: 'error' });
                return;
            }

            if (json.success) {
                Swal.fire({ title: 'Désactivé', text: json.message || 'Employé désactivé', icon: 'success', timer: 1800, showConfirmButton: false });
                updateStatusUI(false);
            } else {
                Swal.fire({ title: 'Erreur', text: json.message || 'Impossible de désactiver', icon: 'error' });
            }
        } catch (err) {
            Swal.fire({ title: 'Erreur', text: 'Erreur réseau ou serveur', icon: 'error' });
        }
    }

    // Bind buttons if they exist
    if (btnActivate) btnActivate.addEventListener('click', handleActivate);
    if (btnDeactivate) btnDeactivate.addEventListener('click', handleDeactivate);

    // Delete: enable/disable button based on the confirmation checkbox
    if (confirmDelete && deleteEmployeeBtn) {
        confirmDelete.addEventListener('change', function() {
            deleteEmployeeBtn.disabled = !this.checked;
        });

        deleteEmployeeBtn.addEventListener('click', async function() {
            const conf = await Swal.fire({
                title: 'Supprimer définitivement ?',
                text: `Supprimer ${PROFILE_CONFIG.employeName} ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer'
            });
            if (!conf.isConfirmed) return;

            try {
                const resp = await fetch('api/delete_employe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id: PROFILE_CONFIG.employeId })
                });
                const json = await resp.json();
                if (json.success) {
                    Swal.fire({ title: 'Supprimé', text: json.message || 'Employé supprimé', icon: 'success', timer: 1500, showConfirmButton: false });
                    // Redirect to admin list after a short delay
                    setTimeout(() => { window.location.href = 'admin_dashboard_unifie.php?success=employe_deleted'; }, 1000);
                } else {
                    Swal.fire({ title: 'Erreur', text: json.message || 'Impossible de supprimer', icon: 'error' });
                }
            } catch (err) {
                Swal.fire({ title: 'Erreur', text: 'Erreur réseau ou serveur', icon: 'error' });
            }
        });
    }
}


function initBadgeTimer() {
    if (!PROFILE_CONFIG.badgeExpiresAt) return;
    
    const timerElement = document.createElement('div');
    timerElement.className = 'badge-timer';
    const badgeSection = document.querySelector('.badge-expiry');
    
    if (badgeSection) {
        badgeSection.appendChild(timerElement);
        
        function updateTimer() {
            const expiresAt = new Date(PROFILE_CONFIG.badgeExpiresAt);
            const now = new Date();
            const diff = expiresAt.getTime() - now.getTime();
            
            if (diff > 0) {
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                let timeText = '';
                if (days > 0) {
                    timeText = `${days}j ${hours}h ${minutes}m`;
                    timerElement.className = 'badge-timer badge-timer-safe';
                } else if (hours > 0) {
                    timeText = `${hours}h ${minutes}m ${seconds}s`;
                    timerElement.className = 'badge-timer badge-timer-warning';
                } else {
                    timeText = `${minutes}m ${seconds}s`;
                    timerElement.className = 'badge-timer badge-timer-danger';
                }
                
                timerElement.innerHTML = `<i class="fas fa-clock me-1"></i> Expire dans ${timeText}`;
            } else {
                timerElement.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> BADGE EXPIRÉ';
                timerElement.className = 'badge-timer badge-timer-expired';
            }
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    }
}

function updateIndicators() {
    // Mettre à jour les indicateurs de temps réel
    const updateTime = () => {
        const now = new Date();
        document.querySelectorAll('.current-time').forEach(el => {
            el.textContent = now.toLocaleTimeString();
        });
    };
    
    updateTime();
    setInterval(updateTime, 1000);
}

// Fonctions utilitaires
function printBadge() {
    const printWindow = window.open('', '_blank');
    
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Badge - ${PROFILE_CONFIG.employeName}</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Inter', sans-serif;
                    background: #f8fafc;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                
                .badge-print {
                    background: white;
                    border-radius: 16px;
                    padding: 40px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.1);
                    max-width: 400px;
                    text-align: center;
                    border: 1px solid #e2e8f0;
                }
                
                .badge-header {
                    margin-bottom: 30px;
                }
                
                .badge-title {
                    color: #2563eb;
                    font-size: 24px;
                    font-weight: 700;
                    margin-bottom: 8px;
                }
                
                .badge-subtitle {
                    color: #64748b;
                    font-size: 14px;
                }
                
                .qr-container {
                    padding: 20px;
                    background: #f8fafc;
                    border-radius: 12px;
                    margin: 30px 0;
                    display: inline-block;
                }
                
                .qr-code {
                    width: 220px;
                    height: 220px;
                }
                
                .employee-info {
                    margin: 30px 0;
                }
                
                .employee-name {
                    font-size: 22px;
                    font-weight: 700;
                    color: #2c3e50;
                    margin-bottom: 8px;
                }
                
                .employee-position {
                    color: #64748b;
                    margin-bottom: 4px;
                }
                
                .employee-department {
                    color: #2563eb;
                    font-weight: 600;
                }
                
                .badge-footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                }
                
                .validity {
                    color: #64748b;
                    font-size: 14px;
                }
                
                .print-date {
                    font-size: 12px;
                    color: #94a3b8;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="badge-print">
                <div class="badge-header">
                    <h1 class="badge-title">Xpert Pro</h1>
                    <p class="badge-subtitle">Badge d'accès professionnel</p>
                </div>
                
                <div class="qr-container">
                    <img src="${PROFILE_CONFIG.qrCodeUrl}&format=svg" 
                         alt="QR Code" 
                         class="qr-code">
                </div>
                
                <div class="employee-info">
                    <h2 class="employee-name">${PROFILE_CONFIG.employeName}</h2>
                    <p class="employee-position"><?= htmlspecialchars($employe['poste']) ?></p>
                    <p class="employee-department"><?= htmlspecialchars($departementLabel) ?></p>
                </div>
                
                <div class="badge-footer">
                    <p class="validity">
                        <strong>Valide jusqu'au :</strong><br>
                        <?= $badge_actif ? date('d/m/Y à H:i', strtotime($badgeToken['expires_at'])) : 'N/A' ?>
                    </p>
                    <p class="print-date">
                        Imprimé le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}
                    </p>
                </div>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

function downloadQRCode() {
    if (!PROFILE_CONFIG.qrCodeUrl) return;
    
    const link = document.createElement('a');
    link.href = PROFILE_CONFIG.qrCodeUrl + '&format=png&download=1';
    link.download = `badge-${PROFILE_CONFIG.employeName.replace(/\s+/g, '-')}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function checkPasswordStrength() {
    const password = this.value;
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    let strength = 0;
    let text = '';
    let color = '';
    
    // Vérifications
    if (password.length >= 8) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[a-z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    if (/[^A-Za-z0-9]/.test(password)) strength += 25;
    
    // Limiter à 100%
    strength = Math.min(strength, 100);
    
    // Déterminer le texte et la couleur
    if (strength < 25) {
        text = 'Très faible';
        color = '#dc3545';
    } else if (strength < 50) {
        text = 'Faible';
        color = '#fd7e14';
    } else if (strength < 75) {
        text = 'Moyen';
        color = '#ffc107';
    } else if (strength < 100) {
        text = 'Fort';
        color = '#20c997';
    } else {
        text = 'Très fort';
        color = '#198754';
    }
    
    // Mettre à jour l'interface
    strengthBar.style.width = strength + '%';
    strengthBar.style.backgroundColor = color;
    strengthText.textContent = text;
    strengthText.style.color = color;
}

function deleteEmployee() {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        html: `Cette action supprimera définitivement <strong>${PROFILE_CONFIG.employeName}</strong> et toutes ses données.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        reverseButtons: true,
        background: '#fff',
        backdrop: 'rgba(0,0,0,0.5)'
    }).then((result) => {
        if (result.isConfirmed) {
            // Simuler l'appel API
            Swal.fire({
                title: 'Suppression en cours...',
                text: 'Veuillez patienter',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Simuler délai API
            setTimeout(() => {
                Swal.fire({
                    title: 'Supprimé !',
                    text: 'L\'employé a été supprimé avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'admin_dashboard_unifie.php';
                });
            }, 1500);
        }
    });
}

function resetPassword() {
    const form = document.getElementById('resetPasswordForm');
    const formData = new FormData(form);
    
    // Validation
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_password');
    
    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Les mots de passe ne correspondent pas.'
        });
        return;
    }
    
    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Le mot de passe doit contenir au moins 8 caractères.'
        });
        return;
    }
    
    // Simuler l'envoi
    Swal.fire({
        title: 'Réinitialisation...',
        text: 'Envoi du nouveau mot de passe en cours',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'Succès !',
            html: `Un nouveau mot de passe a été envoyé à <strong>${PROFILE_CONFIG.employeName}</strong>`,
            confirmButtonText: 'OK'
        }).then(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal'));
            modal.hide();
            form.reset();
        });
    }, 2000);
}

// Gestion des états de chargement
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn && !form.classList.contains('prevent-loading')) {
        const originalText = submitBtn.innerHTML;
        const originalWidth = submitBtn.offsetWidth;
        
        submitBtn.style.width = originalWidth + 'px';
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Traitement...';
        submitBtn.disabled = true;
        
        // Restaurer après 10 secondes max
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            submitBtn.style.width = '';
        }, 10000);
    }
});
</script>

<?php
$additionalJS = [];
include 'partials/footer.php';
?>