<?php
/**
 * Profil Administrateur - Xpert Pro
 * Affichage des informations administrateur (Mali)
 * @version 2.1.0 - Ajout des badges admin avec visualisation en grand
 */

require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';

use Pointage\Services\AuthService;

// Authentification obligatoire
AuthService::requireAuth();

// Vérification des droits administrateur
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    header("Location: login.php?error=unauthorized");
    exit();
}

// Configuration de session
$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN;

// Cible du profil
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_admin_id;

// Récupération de l'administrateur
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$target_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: admin_dashboard_unifie.php?error=admin_not_found#admins');
    exit();
}

// Contrôle d'accès
if ($admin['role'] === ROLE_SUPER_ADMIN && !$is_super_admin) {
    header('Location: admin_dashboard_unifie.php?error=access_denied#admins');
    exit();
}

$is_editing_own = $current_admin_id === $target_id;

// Calcul des statistiques
$stats = [
    'employes_geres' => 0,
    'admins_crees' => 0,
    'demandes_traitees' => 0,
    'pointages_recent' => 0
];

// Admin normal ⇒ employés gérés
if ($admin['role'] === ROLE_ADMIN) {
    // On utilise 'statut' au lieu de 'status' et 'actif' au lieu de 'active'
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employes WHERE statut = 'actif'");
    $stats['employes_geres'] = $stmt->fetch()['count'];
}

// Super admin ⇒ admins créés
if ($admin['role'] === ROLE_SUPER_ADMIN) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins WHERE created_by = ?");
    $stmt->execute([$target_id]);
    $stats['admins_crees'] = $stmt->fetch()['count'];
}

// Demandes traitées (30 derniers jours)
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM demandes 
    WHERE statut != 'en_attente' 
    AND date_demande >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats['demandes_traitees'] = $stmt->fetch()['count'];

// Pointages récents (7 derniers jours)
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM pointages 
    WHERE date_heure >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stats['pointages_recent'] = $stmt->fetch()['count'];

// Dernière activité
$stmt = $pdo->prepare("SELECT last_activity FROM admins WHERE id = ?");
$stmt->execute([$target_id]);
$last_activity = $stmt->fetch()['last_activity'] ?? null;

// Formatage des dates au format malien (JJ/MM/AAAA)
function formatDateMali($date, $showTime = false) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return "—";
    }
    
    if ($showTime) {
        return date('d/m/Y à H:i', strtotime($date));
    }
    return date('d/m/Y', strtotime($date));
}

// Configuration de la page
$pageTitle = "Profil Administrateur - " . htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) . " | Xpert Pro Mali";
$additionalCSS = [
    'assets/css/admin.css?v=' . time(),
    'assets/css/profil.css?v=' . time()
];

$bodyClass = 'has-sidebar';
include 'partials/header.php';
include 'src/views/partials/sidebar_canonique.php';
?>

<div class="main-container">

    <main class="main-content py-4">
        <div class="container-fluid px-3 px-md-4">
          <!-- ================= PROFILE HEADER ================= -->
                <header class="profile-header">
                    <div class="profile-info d-flex align-items-center justify-content-between flex-column flex-md-row">
                        
                        <!-- ===== AVATAR ===== -->
                        <div class="profile-avatar-wrapper position-relative mb-4 mb-md-0">
                            <?php
                            if (!empty($admin['photo'])) {
                                if (strpos($admin['photo'], 'uploads/') !== false) {
                                    $avatarSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($admin['photo']));
                                } else {
                                    $avatarSrc = $admin['photo'];
                                }
                            } else {
                                $avatarSrc = 'assets/img/profil.jpg';
                            }
                            ?>

                            <img src="<?= htmlspecialchars($avatarSrc) ?>"
                                alt="<?= htmlspecialchars($admin['prenom'] ?? 'Employé') ?>"
                                class="profile-avatar rounded-circle shadow-lg"
                                onerror="this.src='assets/img/profil.jpg'">

                            <?php
                            $statusClass = ($admin['status'] ?? '') === 'active'
                                ? 'status-online'
                                : 'status-offline';
                            ?>
                            <span class="avatar-status <?= $statusClass ?> border-white border-2"></span>

                            <?php if (empty($admin['photo'])): ?>
                                <div class="avatar-initials rounded-circle <?= $departementConfig['bg'] ?? 'bg-secondary' ?> d-flex align-items-center justify-content-center shadow">
                                    <?= $initiale ?? 'X' ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ===== INFOS PROFIL ===== -->
                        <div class="profile-details ms-0 ms-md-4 text-center text-md-start flex-grow-1">
                            <h1 class="profile-name fw-bold mb-2">
                                <?= htmlspecialchars(($isAdmin['prenom'] ?? '') . ' ' . ($isAdmin['nom'] ?? '')) ?>
                            </h1>

                            <p class="profile-title fs-5 text-muted mb-2">
                                <i class="fas fa-briefcase me-2"></i>
                                <?= htmlspecialchars($admin['poste'] ?? 'Non défini') ?>
                            </p>

                            <div class="profile-department badge <?= $departementConfig['text'] ?? 'text-muted bg-light' ?> px-3 py-2 fs-6">
                                <i class="fas fa-building me-2"></i>
                                <?= htmlspecialchars($departementLabel ?? 'Département inconnu') ?>
                            </div>

                            <div class="profile-meta mt-3 d-flex flex-column flex-sm-row justify-content-center justify-content-md-start gap-2 gap-sm-3">
                                <span class="meta-item badge bg-light text-dark px-3 py-2">
                                    <i class="fas fa-hashtag me-1"></i>
                                    Matricule : XPERT-<?= strtoupper(substr($isAdmin['departement'] ?? 'XXX', 0, 3)) ?><?= $isAdmin['id'] ?? '0' ?>
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
                            <a href="mailto:<?= htmlspecialchars($isAdmin['email'] ?? '') ?>"
                            class="btn btn-primary">
                                <i class="fas fa-envelope me-1"></i> <span class="d-none d-md-inline">Contacter</span>
                            </a>
                        </div>
                    </div>
                </header>

    <!-- ================= ALERTES ================= -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="alert-icon bg-success bg-opacity-10 rounded-circle p-2 me-3">
                    <i class="fas fa-check-circle text-success fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <strong>Succès !</strong>
                    <div class="small"><?= $_GET['success'] === 'profile_updated' ? 'Profil mis à jour avec succès.' : 'Opération réussie.' ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="alert-icon bg-danger bg-opacity-10 rounded-circle p-2 me-3">
                    <i class="fas fa-exclamation-triangle text-danger fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <strong>Erreur !</strong>
                    <div class="small">
                        <?php 
                        if ($_GET['error'] === 'password_mismatch') {
                            echo 'Les mots de passe ne correspondent pas.';
                        } elseif ($_GET['error'] === 'invalid_current') {
                            echo 'Mot de passe actuel incorrect.';
                        } else {
                            echo 'Une erreur est survenue lors de l\'opération.';
                        }
                        ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================= CONTENU PRINCIPAL ================= -->
    <div class="row g-4">
        
        <!-- ===== COLONNE GAUCHE : INFORMATIONS PERSONNELLES ===== -->
        <div class="col-lg-4">
            <div class="card admin-profile-card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0">
                    <h5 class="card-title fw-semibold d-flex align-items-center">
                        <div class="header-icon bg-primary bg-opacity-10 text-primary rounded-2 p-2 me-3">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <span>Informations personnelles</span>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Avatar et identité -->
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block mb-3">
                            <?php 
                            $prenom_safe = $admin['prenom'] ?? '';
                            $nom_safe = $admin['nom'] ?? '';
                            $initials = strtoupper((substr($prenom_safe, 0, 1) ?: '') . (substr($nom_safe, 0, 1) ?: ''));
                            ?>
                            <div class="admin-avatar-initials avatar-xl rounded-circle <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'bg-gradient-super-admin' : 'bg-gradient-admin' ?> text-white d-flex align-items-center justify-content-center shadow-lg position-relative">
                                <?= $initials ?>
                            </div>
                            <!-- Indicateur de statut -->
                            <div class="avatar-status <?= (($admin['statut'] ?? '') === 'actif') ? 'status-online' : 'status-offline' ?> position-absolute bottom-0 end-0 border border-3 border-white shadow-sm"></div>
                        </div>
                        
                        <h4 class="fw-bold mb-2"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></h4>
                        
                        <!-- Badge principal -->
                        <div class="admin-badge-container mb-3">
                            <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                                  <span class="admin-badge super-admin-badge badge-animate" 
                                      data-bs-toggle="modal" 
                                      data-bs-target="#badgeModal"
                                      data-badge-type="super-admin"
                                      data-delay="0">
                                    <i class="fas fa-crown me-2"></i> Super Administrateur
                                </span>
                            <?php else: ?>
                                  <span class="admin-badge admin-badge badge-animate" 
                                      data-bs-toggle="modal" 
                                      data-bs-target="#badgeModal"
                                      data-badge-type="admin"
                                      data-delay="200">
                                    <i class="fas fa-user-shield me-2"></i> Administrateur
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Badges secondaires -->
                        <div class="admin-badges-small d-flex flex-wrap justify-content-center gap-2">
                            <?php
                            // Déterminer la couleur du badge statut
                            $statut_color = (($admin['statut'] ?? '') === 'actif') ? 'success' : ((($admin['statut'] ?? '') === 'inactif') ? 'danger' : 'secondary');
                            ?>
                            <span class="badge bg-<?= $statut_color ?>-subtle text-<?= $statut_color ?> border-0 badge-hover" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#badgeModal"
                                  data-badge-type="<?= (($admin['statut'] ?? '') === 'actif') ? 'active' : 'inactive' ?>">
                                <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars(!empty($admin['statut']) ? ucfirst($admin['statut']) : '—') ?>
                            </span>
                            <span class="badge bg-info-subtle text-info border-0 badge-hover"
                                  data-bs-toggle="modal" 
                                  data-bs-target="#badgeModal"
                                  data-badge-type="verified">
                                <i class="fas fa-shield-check me-1"></i> Vérifié
                            </span>
                            <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                                <span class="badge bg-danger-subtle text-danger border-0 badge-hover"
                                      data-bs-toggle="modal" 
                                      data-bs-target="#badgeModal"
                                      data-badge-type="full-access">
                                    <i class="fas fa-unlock-alt me-1"></i> Accès complet
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning border-0 badge-hover"
                                      data-bs-toggle="modal" 
                                      data-bs-target="#badgeModal"
                                      data-badge-type="limited-access">
                                    <i class="fas fa-lock me-1"></i> Accès limité
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Grille d'informations -->
                    <div class="admin-info-grid">
                            <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="info-icon icon-48 bg-primary bg-opacity-10 text-primary rounded-2 d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-envelope fs-5"></i>
                            </div>
                            <div class="info-content flex-grow-1">
                                <div class="info-label text-muted small mb-1">Email professionnel</div>
                                <a href="mailto:<?= htmlspecialchars($admin['email']) ?>" 
                                   class="info-value fw-semibold text-decoration-none text-primary d-flex align-items-center">
                                    <?= htmlspecialchars($admin['email']) ?>
                                    <i class="fas fa-external-link-alt ms-2 fs-6"></i>
                                </a>
                            </div>
                        </div>
                        
                            <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="info-icon icon-48 bg-info bg-opacity-10 text-info rounded-2 d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-phone fs-5"></i>
                            </div>
                            <div class="info-content flex-grow-1">
                                <div class="info-label text-muted small mb-1">Téléphone</div>
                                <a href="tel:<?= htmlspecialchars($admin['telephone'] ?? '') ?>" 
                                   class="info-value fw-semibold text-decoration-none text-primary d-flex align-items-center">
                                    <?= htmlspecialchars($admin['telephone'] ?? 'Non renseigné') ?>
                                    <i class="fas fa-phone-alt ms-2 fs-6"></i>
                                </a>
                            </div>
                        </div>
                        
                            <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="info-icon icon-48 bg-success bg-opacity-10 text-success rounded-2 d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-map-marker-alt fs-5"></i>
                            </div>
                            <div class="info-content flex-grow-1">
                                <div class="info-label text-muted small mb-1">Localisation</div>
                                <div class="info-value fw-semibold d-flex align-items-center">
                                    <i class="fas fa-flag me-2 text-muted"></i>
                                    Mali, Bamako
                                </div>
                            </div>
                        </div>
                        
                            <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border">
                            <div class="info-icon icon-48 bg-warning bg-opacity-10 text-warning rounded-2 d-flex align-items-center justify-content-center me-3">
                                <i class="fas fa-clock fs-5"></i>
                            </div>
                            <div class="info-content flex-grow-1">
                                <div class="info-label text-muted small mb-1">Fuseau horaire</div>
                                <div class="info-value fw-semibold d-flex align-items-center">
                                    <i class="fas fa-globe-africa me-2 text-muted"></i>
                                    GMT (UTC+0)
                                    <span class="badge bg-light text-dark ms-2 small">Bamako</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== COLONNE CENTRALE : INFORMATIONS DU COMPTE ===== -->
        <div class="col-lg-4">
            <!-- Carte : Informations du compte -->
            <div class="card admin-profile-card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0">
                    <h5 class="card-title fw-semibold d-flex align-items-center">
                        <div class="header-icon bg-info bg-opacity-10 text-info rounded-2 p-2 me-3">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <span>Informations du compte</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <div class="info-item d-flex align-items-center justify-content-between p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="d-flex align-items-center">
                                <div class="info-icon bg-secondary bg-opacity-10 text-secondary rounded-2 d-flex align-items-center justify-content-center me-3 size-40">
                                    <i class="fas fa-hashtag"></i>
                                </div>
                                <div>
                                    <div class="info-label text-muted small mb-1">Identifiant unique</div>
                                    <div class="info-value fw-semibold">#<?= $admin['id'] ?></div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary copy-id-btn" data-id="<?= $admin['id'] ?>">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        
                        <div class="info-item d-flex align-items-center justify-content-between p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="d-flex align-items-center">
                                <div class="info-icon <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'bg-danger bg-opacity-10 text-danger' : 'bg-primary bg-opacity-10 text-primary' ?> rounded-2 d-flex align-items-center justify-content-center me-3 size-40">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <div class="info-label text-muted small mb-1">Rôle</div>
                                    <div class="info-value">
                                        <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                                            <span class="badge bg-gradient-super-admin text-white px-3 py-2 border-0 badge-clickable" 
                                                  data-bs-toggle="modal" 
                                                  data-bs-target="#badgeModal"
                                                  data-badge-type="super-admin">
                                                <i class="fas fa-crown me-1"></i> Super Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-gradient-admin text-white px-3 py-2 border-0 badge-clickable" 
                                                  data-bs-toggle="modal" 
                                                  data-bs-target="#badgeModal"
                                                  data-badge-type="admin">
                                                <i class="fas fa-user-shield me-1"></i> Administrateur
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <span class="badge <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>">
                                <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'Tous droits' : 'Droits limités' ?>
                            </span>
                        </div>
                        
                        <div class="info-item d-flex align-items-center justify-content-between p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="d-flex align-items-center">
                                <div class="info-icon <?= (($admin['statut'] ?? '') === 'actif') ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' ?> rounded-2 d-flex align-items-center justify-content-center me-3 size-40">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div>
                                    <div class="info-label text-muted small mb-1">Statut du compte</div>
                                    <div class="info-value">
                                                                                                <span id="admin-statut" class="badge <?= (($admin['statut'] ?? '') === 'actif') ? 'bg-success' : 'bg-secondary' ?> text-white px-3 py-2 border-0 badge-clickable" 
                                                                                            data-bs-toggle="modal" 
                                                                                            data-bs-target="#badgeModal"
                                                                                            data-badge-type="<?= (($admin['statut'] ?? '') === 'actif') ? 'active' : 'inactive' ?>">
                                            <i class="fas fa-<?= (($admin['statut'] ?? '') === 'actif') ? 'check' : 'times' ?>-circle me-1"></i>
                                            <?= htmlspecialchars(!empty($admin['statut']) ? ucfirst($admin['statut']) : '—') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="status-indicator <?= (($admin['statut'] ?? '') === 'actif') ? 'indicator-active' : 'indicator-inactive' ?>"></div>
                        </div>
                        
                        <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="info-icon bg-primary bg-opacity-10 text-primary rounded-2 d-flex align-items-center justify-content-center me-3 size-40">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="info-label text-muted small mb-1">Compte créé le</div>
                                <div class="info-value fw-semibold d-flex align-items-center justify-content-between">
                                    <span><?= isset($admin['created_at']) ? date('d/m/Y', strtotime($admin['created_at'])) : '—' ?></span>
                                    <?php if (isset($admin['created_at'])): ?>
                                    <span class="badge bg-light text-dark small">
                                        <?= floor((time() - strtotime($admin['created_at'])) / (60 * 60 * 24)) ?> jours
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                            <div class="info-icon bg-warning bg-opacity-10 text-warning rounded-2 d-flex align-items-center justify-content-center me-3 size-40">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="info-label text-muted small mb-1">Dernière connexion</div>
                                <div class="info-value fw-semibold d-flex align-items-center justify-content-between">
                                    <span><?= $last_activity ? formatDateMali($last_activity, true) : "Jamais connecté" ?></span>
                                    <?php if ($last_activity): ?>
                                        <span class="badge <?= (time() - strtotime($last_activity)) < 3600 ? 'bg-success' : 'bg-warning' ?> text-white small">
                                            <?= ceil((time() - strtotime($last_activity)) / 3600) ?>h
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border">
                            <div class="info-icon bg-info bg-opacity-10 text-info rounded-2 d-flex align-items-center justify-content-center me-3 size-40">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="info-label text-muted small mb-1">Dernière mise à jour</div>
                                <div class="info-value fw-semibold">
                                    <?= isset($admin['updated_at']) && $admin['updated_at'] !== $admin['created_at'] ? formatDateMali($admin['updated_at'], true) : '—' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carte : Actions rapides -->
            <div class="card admin-profile-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 text-center">
                    <h5 class="card-title fw-semibold d-flex align-items-center justify-content-center">
                        <div class="header-icon bg-warning bg-opacity-10 text-warning rounded-2 p-2 me-3">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <span>Actions rapides</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="quick-actions d-flex flex-column gap-2">
                        <a href="modifier_admin.php?id=<?= (int)$admin['id'] ?>" class="btn btn-primary w-100 py-2 shadow-sm transition-all">
                            <i class="fas fa-edit me-2"></i> Modifier profil
                        </a>
                        
                        <button class="btn btn-warning w-100 py-2 shadow-sm transition-all" 
                                data-bs-toggle="modal" 
                                data-bs-target="#changePasswordModal">
                            <i class="fas fa-key me-2"></i> Changer mot de passe
                        </button>
                        
                        <a href="admin_dashboard_unifie.php#employes" 
                           class="btn btn-success w-100 py-2 shadow-sm transition-all">
                            <i class="fas fa-users me-2"></i> Gérer employés
                        </a>
                        
                        <a href="admin_dashboard_unifie.php#demandes" 
                           class="btn btn-info w-100 py-2 shadow-sm transition-all">
                            <i class="fas fa-clipboard-list me-2"></i> Voir demandes
                        </a>

                        <!-- Bouton pour afficher le badge -->
                        <button class="btn btn-outline-primary w-100 py-2 shadow-sm transition-all" 
                                id="btn-show-badge"
                                data-bs-toggle="modal" 
                                data-bs-target="#badgeModal"
                                data-badge-type="admin-full">
                            <i class="fas fa-id-card me-2"></i> Afficher le badge
                        </button>

                        <?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
                            <?php if (($admin['statut'] ?? '') === 'actif'): ?>
                                <button class="btn btn-outline-danger w-100 py-2 shadow-sm transition-all" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deactivateModal">
                                    <i class="fas fa-user-slash me-2"></i> Désactiver compte
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success w-100 py-2 shadow-sm transition-all" 
                                        id="btn-activate-admin">
                                    <i class="fas fa-user-check me-2"></i> Activer compte
                                </button>
                                <button class="btn btn-danger w-100 py-2 shadow-sm transition-all" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModalPermanent">
                                    <i class="fas fa-trash-alt me-2"></i> Supprimer définitivement
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== COLONNE DROITE : STATISTIQUES ===== -->
        <div class="col-lg-4">
            <div class="card admin-profile-card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0">
                    <h5 class="card-title fw-semibold d-flex align-items-center">
                        <div class="header-icon bg-success bg-opacity-10 text-success rounded-2 p-2 me-3">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span>Statistiques d'activité</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <?php if ($admin['role'] === ROLE_ADMIN): ?>
                        <div class="stat-card text-center p-3 rounded-3 bg-light-subtle border mb-3 transition-all">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex align-items-center justify-content-center mb-3 size-60">
                                <i class="fas fa-users fs-4"></i>
                            </div>
                            <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['employes_geres'] ?? 0 ?></div>
                            <div class="stat-label text-muted small mb-2">Employés gérés</div>
                            <div class="stat-trend text-success small d-flex align-items-center justify-content-center">
                                <i class="fas fa-chart-line me-1"></i> 
                                <span>Actifs</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                        <div class="stat-card text-center p-3 rounded-3 bg-light-subtle border mb-3 transition-all">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 d-inline-flex align-items-center justify-content-center mb-3 size-60">
                                <i class="fas fa-user-shield fs-4"></i>
                            </div>
                            <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['admins_crees'] ?? 0 ?></div>
                            <div class="stat-label text-muted small mb-2">Admins créés</div>
                            <div class="stat-trend text-muted small d-flex align-items-center justify-content-center">
                                <i class="fas fa-history me-1"></i> Total
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="stat-card text-center p-3 rounded-3 bg-light-subtle border mb-3 transition-all">
                            <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 d-inline-flex align-items-center justify-content-center mb-3 size-60">
                                <i class="fas fa-clipboard-check fs-4"></i>
                            </div>
                            <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['demandes_traitees'] ?? 0 ?></div>
                            <div class="stat-label text-muted small mb-2">Demandes traitées</div>
                            <div class="stat-trend text-success small d-flex align-items-center justify-content-center">
                                <i class="fas fa-arrow-up me-1"></i> 30 jours
                            </div>
                        </div>
                        
                        <div class="stat-card text-center p-3 rounded-3 bg-light-subtle border transition-all">
                            <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 d-inline-flex align-items-center justify-content-center mb-3 size-60">
                                <i class="fas fa-fingerprint fs-4"></i>
                            </div>
                            <div class="stat-number fw-bold fs-3 mb-1"><?= $stats['pointages_recent'] ?? 0 ?></div>
                            <div class="stat-label text-muted small mb-2">Pointages vérifiés</div>
                            <div class="stat-trend text-info small d-flex align-items-center justify-content-center">
                                <i class="fas fa-calendar me-1"></i> 7 derniers jours
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Performance globale</span>
                            <span class="badge badge-performance bg-success text-white px-3 py-2 border-0 badge-clickable" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#badgeModal"
                                  data-badge-type="performance">
                                <i class="fas fa-chart-line me-1"></i> Excellent
                            </span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width: 92%"></div>
                        </div>
                        <div class="text-end text-muted small mt-1">
                            92% de taux de réussite
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= FOOTER D'ACTIONS ================= -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card admin-profile-card action-footer border-0 shadow-sm bg-gradient-primary">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-4 text-center text-lg-start mb-3 mb-lg-0">
                            <h5 class="fw-semibold mb-1 text-white">Actions administratives</h5>
                            <p class="text-white-75 small mb-0">Gérez le compte administrateur</p>
                        </div>
                        <div class="col-lg-8">
                            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-end">
                                <div class="btn-group" role="group">
                                    <a href="modifier_admin.php?id=<?= (int)$admin['id'] ?>" class="btn btn-light px-4 py-2 shadow-sm">
                                        <i class="fas fa-edit me-2"></i> Modifier profil
                                    </a>
                                    <button class="btn btn-light dropdown-toggle dropdown-toggle-split px-3 shadow-sm" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
                                        <span class="visually-hidden">Plus d'actions</span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                                <i class="fas fa-key me-2"></i> Changer mot de passe
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" id="btn-export-profile">
                                                <i class="fas fa-download me-2"></i> Exporter profil
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                
                                <button class="btn btn-outline-light px-4 py-2 shadow-sm" 
                                        id="btn-show-badge-footer"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#badgeModal"
                                        data-badge-type="admin-full">
                                    <i class="fas fa-id-card me-2"></i> Voir le badge
                                </button>

                                <?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
                                    <?php if (($admin['statut'] ?? '') === 'actif'): ?>
                                        <button class="btn btn-warning px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                                    <i class="fas fa-user-slash me-2"></i> Désactiver
                                                </button>
                                    <?php else: ?>
                                        <button class="btn btn-success px-4 py-2 shadow-sm" id="btn-activate-admin-footer">
                                            <i class="fas fa-user-check me-2"></i> Activer
                                        </button>
                                        <button class="btn btn-danger px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#deleteModalPermanent">
                                            <i class="fas fa-trash me-2"></i> Supprimer
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL : BADGE EN GRAND ================= -->
<div class="modal fade" id="badgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-primary text-white border-0 position-relative">
                <div class="modal-header-content w-100">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="fas fa-id-card me-2"></i>
                        <span id="modalBadgeTitle">Badge Administrateur</span>
                    </h5>
                    <p class="modal-subtitle mb-0 text-white-75 small">Identifiant unique et sécurisé</p>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-lg-5">
                <div class="row g-4 align-items-center">
                    <!-- Colonne gauche : Badge visuel -->
                    <div class="col-md-5">
                        <div class="badge-visual-container text-center">
                            <div class="badge-visual-wrapper position-relative mb-4">
                                <div class="badge-large rounded-circle mx-auto shadow-lg position-relative overflow-hidden" 
                                     id="badgeIconLarge" 
                                     style="width: 160px; height: 160px; display: flex; align-items: center; justify-content: center;">
                                    <!-- Icône dynamique -->
                                    <div class="badge-icon-inner w-100 h-100 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user-shield text-white" style="font-size: 4rem;"></i>
                                    </div>
                                    <!-- Effet de brillance -->
                                    <div class="badge-shine position-absolute top-0 start-0 w-100 h-100"></div>
                                </div>
                                
                                <!-- Éléments décoratifs -->
                                <div class="badge-decoration position-absolute top-0 start-0 w-100 h-100">
                                    <div class="decoration-ring ring-1"></div>
                                    <div class="decoration-ring ring-2"></div>
                                    <div class="decoration-ring ring-3"></div>
                                </div>
                            </div>
                            
                            <h3 class="fw-bold mb-2" id="badgeName"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></h3>
                            <div class="badge-type-container mb-3">
                                <span class="badge-type-big px-4 py-2 rounded-pill fw-semibold" id="badgeType">
                                    Administrateur
                                </span>
                            </div>
                            
                            <!-- QR Code -->
                            <div class="qr-code-container mt-4">
                                <div class="qr-code-wrapper position-relative d-inline-block">
                                    <div class="qr-placeholder bg-white rounded-3 p-3 shadow-sm border">
                                        <i class="fas fa-qrcode fa-4x text-primary opacity-75"></i>
                                    </div>
                                    <div class="qr-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-10 rounded-3 opacity-0" 
                                         style="transition: opacity 0.3s;">
                                        <div class="qr-overlay-content text-white">
                                            <i class="fas fa-expand-alt fa-2x"></i>
                                            <div class="small mt-1">Agrandir</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Colonne droite : Détails du badge -->
                    <div class="col-md-7">
                        <div class="badge-details-container">
                            <div class="section-header mb-4">
                                <h4 class="fw-semibold mb-3 d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2 text-primary"></i>
                                    Détails du badge
                                </h4>
                            </div>
                            
                            <div class="badge-info-grid">
                                <!-- Information 1 : Statut -->
                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                                    <div class="info-icon bg-primary bg-opacity-10 text-primary rounded-2 d-flex align-items-center justify-content-center me-3" 
                                         style="width: 48px; height: 48px;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="info-content flex-grow-1">
                                        <div class="info-label text-muted small mb-1">Statut du badge</div>
                                        <div class="info-value fw-semibold d-flex align-items-center justify-content-between">
                                            <span class="badge bg-success px-3 py-2">
                                                <i class="fas fa-shield-check me-1"></i> ACTIF
                                            </span>
                                            <span class="text-success small fw-medium">
                                                <i class="fas fa-clock me-1"></i> Valide
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Information 2 : Identifiant -->
                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                                    <div class="info-icon bg-info bg-opacity-10 text-info rounded-2 d-flex align-items-center justify-content-center me-3" 
                                         style="width: 48px; height: 48px;">
                                        <i class="fas fa-fingerprint"></i>
                                    </div>
                                    <div class="info-content flex-grow-1">
                                        <div class="info-label text-muted small mb-1">Identifiant unique</div>
                                        <div class="info-value fw-semibold d-flex align-items-center justify-content-between">
                                            <span class="text-dark">#ADMIN-<?= str_pad($admin['id'], 4, '0', STR_PAD_LEFT) ?></span>
                                            <button class="btn btn-sm btn-outline-secondary copy-id-btn" data-id="ADMIN-<?= $admin['id'] ?>">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Information 3 : Expiration -->
                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border mb-3">
                                    <div class="info-icon bg-warning bg-opacity-10 text-warning rounded-2 d-flex align-items-center justify-content-center me-3" 
                                         style="width: 48px; height: 48px;">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="info-content flex-grow-1">
                                        <div class="info-label text-muted small mb-1">Date d'expiration</div>
                                        <div class="info-value fw-semibold d-flex align-items-center justify-content-between">
                                            <span>31/12/2024</span>
                                            <span class="badge bg-warning-subtle text-warning">
                                                <i class="fas fa-clock me-1"></i> 180 jours
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Information 4 : Permissions -->
                                <div class="info-item d-flex align-items-center p-3 rounded-3 bg-light-subtle border">
                                    <div class="info-icon bg-success bg-opacity-10 text-success rounded-2 d-flex align-items-center justify-content-center me-3" 
                                         style="width: 48px; height: 48px;">
                                        <i class="fas fa-user-lock"></i>
                                    </div>
                                    <div class="info-content flex-grow-1">
                                        <div class="info-label text-muted small mb-1">Niveau d'accès</div>
                                        <div class="info-value fw-semibold d-flex align-items-center justify-content-between">
                                            <span><?= $admin['role'] === ROLE_SUPER_ADMIN ? 'Super Administrateur' : 'Administrateur' ?></span>
                                            <span class="badge <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?>">
                                                <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'Accès complet' : 'Accès standard' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions du badge -->
                            <div class="badge-actions mt-4 pt-3 border-top">
                                <div class="d-flex flex-wrap gap-2 justify-content-between">
                                    <button type="button" class="btn btn-outline-primary px-4 py-2 d-flex align-items-center">
                                        <i class="fas fa-print me-2"></i>
                                        Imprimer
                                    </button>
                                    <button type="button" class="btn btn-outline-success px-4 py-2 d-flex align-items-center">
                                        <i class="fas fa-download me-2"></i>
                                        Télécharger
                                    </button>
                                    <button type="button" class="btn btn-primary px-4 py-2 d-flex align-items-center">
                                        <i class="fas fa-sync-alt me-2"></i>
                                        Régénérer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Fermer
                </button>
                <div class="modal-footer-info text-muted small ms-auto">
                    <i class="fas fa-info-circle me-1"></i>
                    Le badge est valide pour tous les accès système
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL : MODIFIER PROFIL ================= -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="update_admin_profile.php" method="POST" id="editProfileForm" class="needs-validation" novalidate>
                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                
                <div class="modal-header bg-gradient-primary text-white border-0 position-relative">
                    <div class="modal-header-content">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="fas fa-user-edit me-2"></i>
                            Modifier le profil
                        </h5>
                        <p class="modal-subtitle mb-0 text-white-75 small">Mettez à jour les informations de l'administrateur</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4 p-lg-5">
                    <div class="edit-profile-form">
                        <!-- Section : Identité -->
                        <div class="section mb-4">
                            <h6 class="section-title fw-semibold mb-3 d-flex align-items-center">
                                <div class="section-icon bg-primary bg-opacity-10 text-primary rounded-2 p-2 me-2">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span>Identité</span>
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium required">Nom</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-user text-muted"></i>
                                        </span>
                                        <input type="text" name="nom" class="form-control ps-0" 
                                               value="<?= htmlspecialchars($admin['nom']) ?>" 
                                               required
                                               placeholder="Entrez le nom">
                                        <div class="invalid-feedback">
                                            Veuillez saisir un nom valide.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium required">Prénom</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-user text-muted"></i>
                                        </span>
                                        <input type="text" name="prenom" class="form-control ps-0" 
                                               value="<?= htmlspecialchars($admin['prenom']) ?>" 
                                               required
                                               placeholder="Entrez le prénom">
                                        <div class="invalid-feedback">
                                            Veuillez saisir un prénom valide.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section : Contact -->
                        <div class="section mb-4">
                            <h6 class="section-title fw-semibold mb-3 d-flex align-items-center">
                                <div class="section-icon bg-info bg-opacity-10 text-info rounded-2 p-2 me-2">
                                    <i class="fas fa-address-card"></i>
                                </div>
                                <span>Contact</span>
                            </h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium required">Email professionnel</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-envelope text-muted"></i>
                                        </span>
                                        <input type="email" name="email" class="form-control ps-0" 
                                               value="<?= htmlspecialchars($admin['email']) ?>" 
                                               required
                                               placeholder="exemple@xpertpro.ml">
                                        <div class="invalid-feedback">
                                            Veuillez saisir une adresse email valide.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-medium">Téléphone</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-phone text-muted"></i>
                                        </span>
                                        <input type="tel" name="telephone" class="form-control ps-0" 
                                               value="<?= htmlspecialchars($admin['telephone'] ?? '') ?>"
                                               placeholder="+223 XX XX XX XX"
                                               pattern="[+]?[0-9\s\-]+"
                                               maxlength="20">
                                        <div class="invalid-feedback">
                                            Format téléphone invalide.
                                        </div>
                                    </div>
                                    <div class="form-text text-muted small">
                                        Format international recommandé
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($is_super_admin && !$is_editing_own): ?>
                        <!-- Section : Rôle et Permissions -->
                        <div class="section mb-4">
                            <h6 class="section-title fw-semibold mb-3 d-flex align-items-center">
                                <div class="section-icon bg-warning bg-opacity-10 text-warning rounded-2 p-2 me-2">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <span>Rôle et Permissions</span>
                            </h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium required">Rôle</label>
                                    <div class="role-selector">
                                        <select name="role" class="form-select" required>
                                            <option value="" disabled>Sélectionnez un rôle</option>
                                            <option value="<?= ROLE_ADMIN ?>" <?= $admin['role'] === ROLE_ADMIN ? 'selected' : '' ?> data-description="Accès standard aux fonctionnalités administratives">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-shield me-2 text-primary"></i>
                                                    <span>Administrateur</span>
                                                </div>
                                            </option>
                                            <option value="<?= ROLE_SUPER_ADMIN ?>" <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'selected' : '' ?> data-description="Accès complet avec tous les privilèges">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-crown me-2 text-danger"></i>
                                                    <span>Super Administrateur</span>
                                                </div>
                                            </option>
                                        </select>
                                        <div class="role-description mt-2 p-3 bg-light rounded-2" id="roleDescription">
                                            <!-- Description dynamique -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="fas fa-save me-2"></i>
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL : CHANGER MOT DE PASSE ================= -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="change_admin_password.php" method="POST" id="changePasswordForm" class="needs-validation" novalidate>
                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                
                <div class="modal-header bg-gradient-warning text-white border-0 position-relative">
                    <div class="modal-header-content">
                        <h5 class="modal-title d-flex align-items-center">
                            <i class="fas fa-key me-2"></i>
                            Changer le mot de passe
                        </h5>
                        <p class="modal-subtitle mb-0 text-white-75 small">Définir un nouveau mot de passe sécurisé</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <?php if ($is_editing_own): ?>
                    <div class="password-section mb-4">
                        <label class="form-label fw-medium required">Mot de passe actuel</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="current_password" class="form-control ps-0" required
                                   placeholder="Saisissez votre mot de passe actuel"
                                   id="currentPassword">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="currentPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Mot de passe actuel requis.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="password-section mb-4">
                        <label class="form-label fw-medium required">Nouveau mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="new_password" class="form-control ps-0" 
                                   required minlength="8"
                                   placeholder="Minimum 8 caractères"
                                   id="newPassword"
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="newPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Le mot de passe doit contenir au moins 8 caractères avec majuscule, minuscule et chiffre.
                            </div>
                        </div>
                        <div class="password-requirements mt-2">
                            <div class="requirements-list small text-muted">
                                <div class="requirement d-flex align-items-center mb-1">
                                    <i class="fas fa-circle text-muted me-2" style="font-size: 0.5rem;"></i>
                                    <span>Au moins 8 caractères</span>
                                </div>
                                <div class="requirement d-flex align-items-center mb-1">
                                    <i class="fas fa-circle text-muted me-2" style="font-size: 0.5rem;"></i>
                                    <span>Une majuscule et une minuscule</span>
                                </div>
                                <div class="requirement d-flex align-items-center">
                                    <i class="fas fa-circle text-muted me-2" style="font-size: 0.5rem;"></i>
                                    <span>Au moins un chiffre</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="password-section mb-4">
                        <label class="form-label fw-medium required">Confirmer le mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="confirm_password" class="form-control ps-0" required
                                   placeholder="Confirmez le nouveau mot de passe"
                                   id="confirmPassword">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Les mots de passe doivent correspondre.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Indicateur de force du mot de passe -->
                    <div class="password-strength mt-4">
                        <div class="strength-header d-flex justify-content-between mb-2">
                            <span class="small fw-medium">Force du mot de passe</span>
                            <span class="strength-text small fw-medium" id="strengthText">Faible</span>
                        </div>
                        <div class="strength-meter bg-light rounded" style="height: 8px;">
                            <div class="strength-bar rounded" style="height: 100%; width: 0%;"></div>
                        </div>
                        <div class="strength-indicators d-flex justify-content-between mt-1">
                            <span class="indicator weak"></span>
                            <span class="indicator medium"></span>
                            <span class="indicator strong"></span>
                            <span class="indicator very-strong"></span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-warning px-4 py-2">
                        <i class="fas fa-key me-2"></i>
                        Modifier le mot de passe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL : DÉSACTIVER COMPTE ================= -->
<?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
<div class="modal fade" id="deactivateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-danger text-white border-0 position-relative">
                <div class="modal-header-content">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="fas fa-user-slash me-2"></i>
                        Désactiver le compte
                    </h5>
                    <p class="modal-subtitle mb-0 text-white-75 small">Action administrative importante</p>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Alerte d'avertissement -->
                <div class="alert alert-danger border-0 shadow-sm">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="alert-heading fw-bold mb-2">Attention ! Action critique</h6>
                            <p class="mb-0">
                                Vous êtes sur le point de désactiver le compte administrateur de 
                                <strong class="text-dark"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Conséquences -->
                <div class="consequences-section mt-4">
                    <h6 class="fw-semibold mb-3 d-flex align-items-center">
                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                        Conséquences
                    </h6>
                    <div class="consequences-list">
                        <div class="consequence-item d-flex align-items-start mb-2">
                            <i class="fas fa-ban text-danger mt-1 me-2"></i>
                            <span>L'administrateur ne pourra plus se connecter au système</span>
                        </div>
                        <div class="consequence-item d-flex align-items-start mb-2">
                            <i class="fas fa-user-times text-danger mt-1 me-2"></i>
                            <span>Tous ses accès seront immédiatement suspendus</span>
                        </div>
                        <div class="consequence-item d-flex align-items-start">
                            <i class="fas fa-history text-danger mt-1 me-2"></i>
                            <span>Les données resteront conservées pour audit</span>
                        </div>
                    </div>
                </div>
                
                <!-- Formulaire de confirmation -->
                <form action="deactivate_admin.php" method="POST" id="deactivateForm">
                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                    
                    <div class="confirmation-section mt-4 pt-3 border-top">
                        <div class="form-check p-3 bg-light rounded-2">
                            <input class="form-check-input" type="checkbox" id="confirmDeactivate" required
                                   style="width: 20px; height: 20px;">
                            <label class="form-check-label ms-3 fw-medium" for="confirmDeactivate">
                                Je confirme vouloir désactiver ce compte administrateur.
                                <span class="text-danger">Cette action est réversible.</span>
                            </label>
                            <div class="form-text text-muted small mt-2">
                                Vous pourrez réactiver ce compte ultérieurement depuis la liste des administrateurs.
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Annuler
                </button>
                <button type="submit" form="deactivateForm" class="btn btn-danger px-4 py-2" id="confirmDeactivateBtn" disabled>
                    <i class="fas fa-user-slash me-2"></i>
                    Désactiver le compte
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL : SUPPRIMER DÉFINITIVEMENT ================= -->
<?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
<div class="modal fade" id="deleteModalPermanent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-dark text-white border-0 position-relative">
                <div class="modal-header-content">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="fas fa-skull-crossbones me-2"></i>
                        Supprimer définitivement
                    </h5>
                    <p class="modal-subtitle mb-0 text-white-75 small">Action irréversible et définitive</p>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Alerte danger extrême -->
                <div class="alert alert-dark border-0 shadow-sm">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-radiation-alt fa-2x text-dark"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="alert-heading fw-bold mb-2 text-danger">SUPPRESSION DÉFINITIVE</h6>
                            <p class="mb-0">
                                Cette action supprimera <strong class="text-dark">définitivement</strong> l'administrateur 
                                <strong class="text-dark"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                                et <u>toutes ses données associées</u>.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des suppressions -->
                <div class="deletion-list mt-4">
                    <h6 class="fw-semibold mb-3 d-flex align-items-center">
                        <i class="fas fa-trash-alt text-danger me-2"></i>
                        Données qui seront supprimées
                    </h6>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex align-items-center">
                            <i class="fas fa-user text-danger me-3"></i>
                            <div>
                                <div class="fw-medium">Compte administrateur</div>
                                <div class="text-muted small">Profil, identifiants, permissions</div>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="fas fa-history text-danger me-3"></i>
                            <div>
                                <div class="fw-medium">Historique d'activité</div>
                                <div class="text-muted small">Toutes les actions et connexions</div>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="fas fa-key text-danger me-3"></i>
                            <div>
                                <div class="fw-medium">Badges et accès</div>
                                <div class="text-muted small">Tous les tokens d'accès générés</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Avertissement final -->
                <div class="final-warning mt-4 pt-3 border-top">
                    <div class="warning-content p-3 bg-danger bg-opacity-10 rounded-2 border-start border-4 border-danger">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                            <h6 class="mb-0 fw-bold text-danger">CETTE ACTION EST IRRÉVERSIBLE</h6>
                        </div>
                        <p class="mb-0 text-dark small">
                            Une fois confirmée, la suppression ne pourra être annulée.
                            Toutes les données seront effacées définitivement.
                        </p>
                    </div>
                </div>
                
                <!-- Formulaire de confirmation -->
                <form action="supprimer_admin_def.php" method="POST" id="permanentDeleteForm" class="mt-4">
                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                    
                    <div class="confirmation-section">
                        <label for="confirmText" class="form-label fw-medium">
                            Pour confirmer, tapez <strong class="text-danger">SUPPRIMER</strong>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-keyboard text-muted"></i>
                            </span>
                            <input type="text" id="confirmText" name="confirm_text" 
                                   class="form-control ps-0" 
                                   required
                                   placeholder="Tapez SUPPRIMER ici"
                                   pattern="^SUPPRIMER$">
                            <div class="invalid-feedback">
                                Vous devez taper exactement "SUPPRIMER" pour confirmer.
                            </div>
                        </div>
                        <div class="form-text text-muted small mt-2">
                            Cette mesure de sécurité empêche les suppressions accidentelles.
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Annuler
                </button>
                <button type="submit" form="permanentDeleteForm" class="btn btn-dark px-4 py-2" id="confirmDeleteBtn">
                    <i class="fas fa-skull-crossbones me-2"></i>
                    Supprimer définitivement
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

<script>
// ================= CONFIGURATION =================
const APP_CONFIG = {
    // Configuration admin
    admin: {
        id: <?= (int)$admin['id'] ?>,
        name: "<?= addslashes($admin['prenom'] . ' ' . $admin['nom']) ?>",
        role: "<?= $admin['role'] ?>",
        status: "<?= addslashes($admin['statut'] ?? '') ?>",
        email: "<?= addslashes($admin['email'] ?? '') ?>",
        telephone: "<?= addslashes($admin['telephone'] ?? '') ?>"
    },
    
    // Permissions
    permissions: {
        isEditingOwn: <?= $is_editing_own ? 'true' : 'false' ?>,
        isSuperAdmin: <?= $is_super_admin ? 'true' : 'false' ?>
    },
    
    // URLs API
    endpoints: {
        toggleStatus: 'toggle_admin_status.php',
        deletePermanent: 'supprimer_admin_def.php',
        changePassword: 'change_admin_password.php'
    },
    
    // Configuration UI
    ui: {
        animationDuration: 300,
        notificationDuration: 3000
    }
};

// Données des badges avec effets visuels
const BADGES_CONFIG = {
    'super-admin': {
        title: 'Super Administrateur',
        icon: 'fas fa-crown',
        description: 'Privilèges administratifs complets',
        details: 'Peut gérer tous les administrateurs, paramètres système et accéder à toutes les fonctionnalités.',
        gradient: 'linear-gradient(135deg, #dc3545 0%, #b02a37 100%)',
        bgColor: '#f8d7da',
        textColor: '#721c24',
        animation: 'pulse-gold'
    },
    'admin': {
        title: 'Administrateur',
        icon: 'fas fa-user-shield',
        description: 'Gestion des employés et demandes',
        details: 'Peut gérer les employés, traiter les demandes et voir les rapports.',
        gradient: 'linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%)',
        bgColor: '#cfe2ff',
        textColor: '#052c65',
        animation: 'pulse-blue'
    },
    'active': {
        title: 'Compte Actif',
        icon: 'fas fa-check-circle',
        description: 'Compte activé et fonctionnel',
        details: 'Le compte est actuellement actif et peut se connecter au système.',
        gradient: 'linear-gradient(135deg, #198754 0%, #146c43 100%)',
        bgColor: '#d1e7dd',
        textColor: '#0a3622',
        animation: 'pulse-green'
    },
    'inactive': {
        title: 'Compte Inactif',
        icon: 'fas fa-times-circle',
        description: 'Compte désactivé temporairement',
        details: 'Le compte est actuellement désactivé et ne peut pas se connecter.',
        gradient: 'linear-gradient(135deg, #6c757d 0%, #545b62 100%)',
        bgColor: '#e2e3e5',
        textColor: '#2b2f32',
        animation: 'pulse-gray'
    },
    'verified': {
        title: 'Email Vérifié',
        icon: 'fas fa-shield-check',
        description: 'Adresse email confirmée',
        details: 'L\'adresse email a été vérifiée et est valide.',
        gradient: 'linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%)',
        bgColor: '#cff4fc',
        textColor: '#055160',
        animation: 'pulse-cyan'
    }
};

// ================= GESTION DES NOTIFICATIONS =================
class NotificationManager {
    static show(type, message, title = 'Notification') {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: APP_CONFIG.ui.notificationDuration,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            
            return Toast.fire({
                icon: type,
                title: title,
                text: message
            });
        } else {
            // Fallback simple
            alert(`${title}: ${message}`);
        }
    }

    static async confirm(message, title = 'Confirmation') {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: title,
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler',
                reverseButtons: true
            });
            
            return result.isConfirmed;
        } else {
            return confirm(`${title}: ${message}`);
        }
    }

    static showLoader(message = 'Chargement...') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: message,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    }

    static hideLoader() {
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
    }

    static showError(message, title = 'Erreur') {
        this.show('error', message, title);
    }
}

// ================= GESTION DE L'INTERFACE UTILISATEUR =================
class UIManager {
    // Animation des cartes
    static animateCards() {
        const cards = document.querySelectorAll('.admin-profile-card, .stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    // Mise à jour des boutons d'action
    static updateActionButtons() {
        const status = (APP_CONFIG.admin.status || '').toLowerCase();
        const isActive = status.includes('actif');
        const isInactive = status.includes('inactif');
        const isDeleted = status.includes('supprim');

        // Sélectionner tous les boutons concernés
        const elements = {
            deactivate: document.querySelector('[data-bs-target="#deactivateModal"]'),
            activate: document.getElementById('btn-activate-admin'),
            activateFooter: document.getElementById('btn-activate-admin-footer'),
            deleteBtn: document.querySelector('[data-bs-target="#deleteModalPermanent"]')
        };

        // Si supprimé -> masquer tout
        if (isDeleted) {
            Object.values(elements).forEach(el => { if (el) el.style.display = 'none'; });
            // Mettre à jour badge
            const badge = document.getElementById('admin-statut');
            if (badge) {
                badge.className = 'badge bg-danger text-white px-3 py-2 border-0';
                badge.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Supprimé';
            }
            this.setProfileInteractivity(false);
            return;
        }

        // Appliquer la visibilité selon le statut
        if (elements.deactivate) elements.deactivate.style.display = isInactive ? 'none' : '';
        if (elements.activate) elements.activate.style.display = isInactive ? '' : 'none';
        if (elements.activateFooter) elements.activateFooter.style.display = isInactive ? '' : 'none';
        if (elements.deleteBtn) elements.deleteBtn.style.display = isInactive ? '' : 'none';

        // Mettre à jour l'interactivité (les actions sont désactivées si le compte est inactif)
        this.setProfileInteractivity(isActive);
    }

    // Mettre à jour le badge de statut
    static updateStatusBadge(newStatus) {
        const badge = document.getElementById('admin-statut');
        if (!badge) return;
        
        const isActive = newStatus === 'actif';
        const badgeConfig = isActive ? BADGES_CONFIG.active : BADGES_CONFIG.inactive;
        
        // Mettre à jour les classes
        badge.className = 'badge px-3 py-2 border-0 badge-clickable';
        badge.classList.add(isActive ? 'bg-success' : 'bg-secondary');
        
        // Mettre à jour le contenu
        badge.innerHTML = `
            <i class="${badgeConfig.icon} me-1"></i>
            ${isActive ? 'Actif' : 'Inactif'}
        `;
        
        // Mettre à jour l'attribut data
        badge.setAttribute('data-badge-type', isActive ? 'active' : 'inactive');
        
        // Animation
        badge.style.animation = 'badgePulse 0.5s ease';
        setTimeout(() => badge.style.animation = '', 500);
    }

    // Mise à jour des stats en temps réel
    static startRealTimeUpdates() {
        // Heure locale
        const updateTime = () => {
            const now = new Date();
            const options = { 
                timeZone: 'Africa/Bamako',
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit',
                hour12: false 
            };
            
            const timeString = now.toLocaleTimeString('fr-ML', options);
            document.querySelectorAll('.current-time').forEach(el => {
                el.textContent = timeString;
            });
        };
        
        updateTime();
        setInterval(updateTime, 1000);
    }

    // Système de badges
    static initBadgesSystem() {
        const badgeModal = document.getElementById('badgeModal');
        if (badgeModal) {
            badgeModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const badgeType = button.getAttribute('data-badge-type');
                this.loadBadgeDetails(badgeType);
            });
        }
        
        // Effets hover sur les badges
        document.querySelectorAll('.badge-clickable, .badge-hover').forEach(badge => {
            badge.addEventListener('mouseenter', () => {
                badge.style.transform = 'scale(1.05) translateY(-2px)';
                badge.style.boxShadow = '0 6px 15px rgba(0,0,0,0.1)';
                badge.style.transition = 'all 0.2s ease';
            });
            
            badge.addEventListener('mouseleave', () => {
                badge.style.transform = 'scale(1) translateY(0)';
                badge.style.boxShadow = '';
            });
        });
    }

    static loadBadgeDetails(badgeType) {
        const badgeConfig = BADGES_CONFIG[badgeType];
        if (!badgeConfig) return;
        
        // Mettre à jour le modal
        const elements = {
            title: document.getElementById('modalBadgeTitle'),
            name: document.getElementById('badgeName'),
            type: document.getElementById('badgeType'),
            icon: document.getElementById('badgeIconLarge')
        };
        
        if (elements.title) elements.title.textContent = badgeConfig.title;
        if (elements.name) elements.name.textContent = APP_CONFIG.admin.name;
        if (elements.type) elements.type.textContent = badgeConfig.title;
        
        if (elements.icon) {
            const iconInner = elements.icon.querySelector('.badge-icon-inner');
            if (iconInner) {
                iconInner.innerHTML = `<i class="${badgeConfig.icon} fa-4x" style="color: white"></i>`;
                iconInner.style.background = badgeConfig.gradient;
            }
        }

        // Générer ou mettre à jour le QR code (utilise QRious)
        try {
            const qrWrapper = document.querySelector('.qr-code-wrapper');
            if (qrWrapper) {
                let canvas = qrWrapper.querySelector('#badgeQrCanvas');
                const qrValue = `ADMIN:${APP_CONFIG.admin.id}:${APP_CONFIG.admin.email}`;

                if (!canvas) {
                    // Créer un canvas et l'insérer
                    canvas = document.createElement('canvas');
                    canvas.id = 'badgeQrCanvas';
                    canvas.width = 160;
                    canvas.height = 160;
                    const placeholder = qrWrapper.querySelector('.qr-placeholder');
                    if (placeholder) {
                        placeholder.innerHTML = '';
                        placeholder.appendChild(canvas);
                        placeholder.style.display = 'flex';
                        placeholder.style.alignItems = 'center';
                        placeholder.style.justifyContent = 'center';
                        placeholder.style.background = 'white';
                    }
                }

                // Utiliser QRious si disponible
                if (typeof QRious !== 'undefined') {
                    // Si on a déjà un instance attachée, remplacer la value
                    if (canvas._qrInstance) {
                        canvas._qrInstance.value = qrValue;
                    } else {
                        const qr = new QRious({
                            element: canvas,
                            value: qrValue,
                            size: 160,
                            level: 'H'
                        });
                        canvas._qrInstance = qr;
                    }

                    // Cliquer pour agrandir (ouvre un modal SweetAlert avec l'image)
                    qrWrapper.style.cursor = 'pointer';
                    qrWrapper.onclick = () => {
                        const dataUrl = canvas.toDataURL();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'QR Code',
                                html: `<img src="${dataUrl}" style="max-width:100%;height:auto;border-radius:8px"/>\n<p class=\"small mt-2\">${Utils.escapeHtml(qrValue)}</p>`,
                                showCloseButton: true,
                                showConfirmButton: false,
                                width: 360
                            });
                        } else {
                            const w = window.open();
                            w.document.write(`<img src="${dataUrl}"/>`);
                        }
                    };
                }
            }
        } catch (e) {
            console.error('Erreur génération QR:', e);
        }
    }

    static setProfileInteractivity(enabled) {
        const quickActions = document.querySelector('.quick-actions');
        if (!quickActions) return;

        // Éléments qui doivent rester cliquables même quand le compte est inactif
        const allowedIds = ['btn-activate-admin', 'btn-activate-admin-footer', 'btn-show-badge', 'btn-show-badge-footer'];

        const elems = quickActions.querySelectorAll('button, a');
        elems.forEach(el => {
            const id = el.id || '';
            const shouldAllow = allowedIds.includes(id);

            if (!enabled && !shouldAllow) {
                el.classList.add('disabled-clickable');
                // Empêcher les clics et afficher un message
                if (!el._disabledHandler) {
                    el.addEventListener('click', el._disabledHandler = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        NotificationManager.show('error', 'Compte désactivé. Cette action n\'est pas autorisée.', 'Compte désactivé');
                    });
                }
            } else {
                el.classList.remove('disabled-clickable');
                if (el._disabledHandler) {
                    el.removeEventListener('click', el._disabledHandler);
                    delete el._disabledHandler;
                }
            }
        });
    }
}

// ================= GESTION DES MODALS ET FORMULAIRES =================
class FormManager {
    static initModals() {
        // Modal de désactivation
        const confirmCheckbox = document.getElementById('confirmDeactivate');
        const deactivateBtn = document.getElementById('confirmDeactivateBtn');
        
        if (confirmCheckbox && deactivateBtn) {
            confirmCheckbox.addEventListener('change', () => {
                deactivateBtn.disabled = !confirmCheckbox.checked;
            });
            deactivateBtn.disabled = true;
        }
        
        // Toggle visibilité mot de passe
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetId = e.target.closest('button').getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = e.target.closest('button').querySelector('i');
                
                if (input && icon) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                    }
                }
            });
        });
        
        // Validation des formulaires
        this.initFormValidations();
    }

    static initFormValidations() {
        // Formulaire changement mot de passe
        const passwordForm = document.getElementById('changePasswordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', (e) => {
                const newPass = passwordForm.querySelector('input[name="new_password"]');
                const confirmPass = passwordForm.querySelector('input[name="confirm_password"]');
                
                if (newPass && confirmPass && newPass.value !== confirmPass.value) {
                    e.preventDefault();
                    NotificationManager.show('error', 'Les mots de passe ne correspondent pas');
                    return;
                }
            });
        }
    }

    static initPasswordStrength() {
        const passwordInput = document.querySelector('input[name="new_password"]');
        const strengthContainer = document.querySelector('.password-strength');
        
        if (passwordInput && strengthContainer) {
            passwordInput.addEventListener('input', (e) => {
                const password = e.target.value;
                
                if (!password) {
                    strengthContainer.style.display = 'none';
                    return;
                }
                
                strengthContainer.style.display = 'block';
                
                // Calcul de la force
                let score = 0;
                if (password.length >= 8) score += 25;
                if (/[A-Z]/.test(password)) score += 25;
                if (/[a-z]/.test(password)) score += 25;
                if (/[0-9]/.test(password)) score += 25;
                if (/[^A-Za-z0-9]/.test(password)) score += 25;
                
                score = Math.min(score, 100);
                
                // Déterminer le niveau
                const levels = [
                    { min: 0, max: 25, text: 'Très faible', color: '#dc3545', class: 'very-weak' },
                    { min: 26, max: 50, text: 'Faible', color: '#fd7e14', class: 'weak' },
                    { min: 51, max: 75, text: 'Moyen', color: '#ffc107', class: 'medium' },
                    { min: 76, max: 99, text: 'Fort', color: '#20c997', class: 'strong' },
                    { min: 100, max: 100, text: 'Très fort', color: '#198754', class: 'very-strong' }
                ];
                
                const level = levels.find(l => score >= l.min && score <= l.max);
                
                // Mettre à jour l'UI
                const strengthBar = strengthContainer.querySelector('.strength-bar');
                const strengthText = strengthContainer.querySelector('.strength-text');
                
                if (strengthBar) {
                    strengthBar.style.width = score + '%';
                    strengthBar.style.backgroundColor = level.color;
                }
                
                if (strengthText) {
                    strengthText.textContent = level.text;
                    strengthText.style.color = level.color;
                }
                
                // Mettre à jour les indicateurs
                strengthContainer.querySelectorAll('.indicator').forEach((indicator, index) => {
                    indicator.className = 'indicator';
                    if (index < levels.indexOf(level) + 1) {
                        indicator.classList.add('active');
                        indicator.style.backgroundColor = level.color;
                    }
                });
            });
        }
    }

    static async handleFormSubmit(e) {
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Traitement...';
            submitBtn.disabled = true;
            
            // Restauration automatique après 10s
            const restoreBtn = () => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            };
            
            setTimeout(restoreBtn, 10000);
            
            // Si le formulaire a un gestionnaire asynchrone, ne pas le restaurer tout de suite
            if (form.id === 'deactivateForm' || form.id === 'permanentDeleteForm') {
                // La restauration sera faite par le gestionnaire spécifique
                return;
            }
            
            // Pour les autres formulaires, restauration après soumission
            form.addEventListener('submit', restoreBtn, { once: true });
        }
    }
}

// ================= GESTION DES ACTIONS ADMIN =================
class ActionManager {

    static async toggleAdminStatus(action, options = {}) {
        if (this.isProcessing) {
            NotificationManager.show('info', 'Une action est déjà en cours', 'Veuillez patienter');
            return;
        }
        
        try {
            this.isProcessing = true;
            const skipConfirm = options.skipConfirm === true;
            if (!skipConfirm) {
                const confirmationMessage = action === 'activate' 
                    ? 'Confirmer l\'activation de ce compte administrateur ?'
                    : 'Confirmer la désactivation de ce compte administrateur ?';
                const confirmed = await NotificationManager.confirm(confirmationMessage);
                if (!confirmed) {
                    this.isProcessing = false;
                    return;
                }
            }
            
            // Afficher le loader
            NotificationManager.showLoader(action === 'activate' ? 'Activation en cours...' : 'Désactivation en cours...');
            
            // Préparer les données
            const formData = new FormData();
            formData.append('admin_id', APP_CONFIG.admin.id);
            formData.append('action', action);
            
            // Appel API
            const response = await this.callAPI(APP_CONFIG.endpoints.toggleStatus, formData);
            
            if (response.success) {
                // Déterminer le nouveau statut renvoyé par l'API si présent
                const newStatus = (response.data && response.data.new_status) ? String(response.data.new_status).toLowerCase() : (action === 'activate' ? 'actif' : 'inactif');
                APP_CONFIG.admin.status = newStatus;

                // Mettre à jour l'UI
                UIManager.updateStatusBadge(APP_CONFIG.admin.status);
                UIManager.updateActionButtons();

                // Fermer les modals
                this.closeAllModals();

                // Notification visuelle selon le type d'action
                if (newStatus && newStatus.includes('actif')) {
                    NotificationManager.show('success', response.message || 'Le compte administrateur a été activé avec succès', 'Succès');
                } else if (newStatus && newStatus.includes('inactif')) {
                    NotificationManager.show('warning', response.message || 'Le compte administrateur a été désactivé', 'Avertissement');
                } else if (newStatus && newStatus.includes('supprim')) {
                    NotificationManager.show('error', response.message || 'Le compte administrateur a été supprimé', 'Supprimé');
                } else {
                    NotificationManager.show('success', response.message || 'Action réussie', 'Succès');
                }
                
            } else {
                console.error('toggleAdminStatus response error:', response);
                const userMsg = response && response.message ? response.message : 'Une erreur est survenue';
                // Afficher une alerte claire, sans JSON brut
                NotificationManager.show('error', userMsg, 'Erreur');
            }
            
        } catch (error) {
            console.error('Erreur lors du changement de statut:', error);
            NotificationManager.show('error', error.message || 'Erreur technique', 'Erreur');
        } finally {
            this.isProcessing = false;
            NotificationManager.hideLoader();
        }
    }

    static async deleteAdminPermanently() {
        if (this.isProcessing) {
            NotificationManager.show('info', 'Une action est déjà en cours', 'Veuillez patienter');
            return;
        }
        
        try {
            const form = document.getElementById('permanentDeleteForm');
            const confirmText = document.getElementById('confirmText');
            
            if (!confirmText || confirmText.value !== 'SUPPRIMER') {
                NotificationManager.show('error', 'Vous devez taper "SUPPRIMER" pour confirmer', 'Validation requise');
                return;
            }
            
            const confirmed = await NotificationManager.confirm(
                'Êtes-vous ABSOLUMENT SÛR de vouloir supprimer définitivement ce compte ? Cette action est irréversible.'
            );
            
            if (!confirmed) {
                return;
            }
            
            this.isProcessing = true;
            
            // Afficher le loader
            NotificationManager.showLoader('Suppression en cours...');
            
            // Appel API
            const formData = new FormData(form);
            const response = await this.callAPI(APP_CONFIG.endpoints.deletePermanent, formData);
            
            if (response.success) {
                // Fermer le modal
                this.closeModal('deleteModalPermanent');
                
                // Notification de succès
                NotificationManager.show('success', response.message, 'Suppression réussie');
                
                // Redirection après délai
                setTimeout(() => {
                    window.location.href = 'admin_dashboard_unifie.php#admins';
                }, 1500);
                
            } else {
                NotificationManager.show('error', response.message, 'Erreur');
            }
            
        } catch (error) {
            console.error('Erreur lors de la suppression:', error);
            NotificationManager.show('error', 'Erreur: ' + error.message, 'Erreur technique');
            
        } finally {
            this.isProcessing = false;
            NotificationManager.hideLoader();
        }
    }

    static async callAPI(endpoint, formData) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            // Vérifier le type de contenu
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Réponse non-JSON reçue: ${text.substring(0, 100)}`);
            }
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || `Erreur HTTP ${response.status}`);
            }
            
            return data;
            
        } catch (error) {
            console.error('Erreur API:', error);
            throw error;
        }
    }

    static closeAllModals() {
        if (typeof bootstrap === 'undefined') return;
        
        document.querySelectorAll('.modal.show').forEach(modalEl => {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });
    }

    static closeModal(modalId) {
        const modalEl = document.getElementById(modalId);
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
    }
}

// Compatibility: définir la propriété statique pour anciens navigateurs
ActionManager.isProcessing = false;

// ================= FONCTIONS UTILITAIRES =================
class Utils {
    // Copier un ID dans le presse-papier
    static async handleCopyId(e) {
        const button = e.target.closest('button');
        const id = button.getAttribute('data-id');
        
        try {
            await navigator.clipboard.writeText(id);
            NotificationManager.show('success', 'ID copié dans le presse-papier', 'Copié');
            
            // Feedback visuel
            button.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-copy"></i>';
            }, 2000);
            
        } catch (error) {
            console.error('Erreur de copie:', error);
            NotificationManager.show('error', 'Impossible de copier l\'ID', 'Erreur');
        }
    }

    // Échapper le HTML pour affichage sécurisé
    static escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

}

    // Toggle du header de profil (attaché à Utils pour compatibilité)
    Utils.toggleProfileHeader = function() {
        const header = document.querySelector('.profile-header');
        const toggleBtn = document.getElementById('toggleProfileHeader');
        const icon = toggleBtn ? toggleBtn.querySelector('i') : null;
        if (!toggleBtn || !header) return;
        if (header.classList.contains('collapsed')) {
            header.classList.remove('collapsed');
            if (icon) icon.className = 'fas fa-chevron-up';
            header.style.maxHeight = '500px';
        } else {
            header.classList.add('collapsed');
            if (icon) icon.className = 'fas fa-chevron-down';
            header.style.maxHeight = '150px';
        }
    };

    // Export du profil (attaché à Utils)
    Utils.handleExportProfile = async function() {
        try {
            NotificationManager.showLoader('Génération de l\'export...');
            // Simuler un temps de traitement
            await new Promise(resolve => setTimeout(resolve, 1000));
            NotificationManager.show('success', 'Export généré avec succès. Téléchargement lancé.', 'Export réussi');
        } catch (error) {
            NotificationManager.show('error', 'Erreur lors de l\'export: ' + error.message, 'Erreur');
        } finally {
            NotificationManager.hideLoader();
        }
    };

// ================= ADMIN PROFILE MANAGER =================
class AdminProfileManager {
    constructor() {
        this.initialized = false;
    }

    async initialize() {
        try {
            console.log('🚀 Initialisation du profil administrateur...');
            
            // Vérifier les dépendances
            await this.checkDependencies();
            
            // Initialiser les composants
            this.initUIComponents();
            this.initEventHandlers();
            this.initAdminActions();
            
            // Mettre à jour l'interface
            this.updateUI();
            
            this.initialized = true;
            console.log('✅ Profil administrateur initialisé avec succès');
            
        } catch (error) {
            console.error('❌ Erreur d\'initialisation:', error);
            NotificationManager.showError('Erreur d\'initialisation: ' + error.message);
            throw error;
        }
    }

    async checkDependencies() {
        // Vérifier Bootstrap
        if (typeof bootstrap === 'undefined') {
            throw new Error('Bootstrap n\'est pas chargé correctement');
        }
        
        // Vérifier SweetAlert2
        if (typeof Swal === 'undefined') {
            console.warn('SweetAlert2 n\'est pas disponible, notifications basiques utilisées');
        }
        
        // Tester la connexion API
        await this.testAPIConnection();
    }

    async testAPIConnection() {
        try {
            const response = await fetch(APP_CONFIG.endpoints.toggleStatus, {
                method: 'HEAD',
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.warn(`⚠️ API ${APP_CONFIG.endpoints.toggleStatus} peut être inaccessible`);
            }
        } catch (error) {
            console.warn('⚠️ Test de connexion API échoué:', error.message);
        }
    }

    initUIComponents() {
        // Animations des cartes
        UIManager.animateCards();
        
        // Initialiser le système de badges
        UIManager.initBadgesSystem();
        
        // Initialiser les modals
        FormManager.initModals();
        
        // Initialiser la force des mots de passe
        FormManager.initPasswordStrength();
        
        // Initialiser les interactions
        this.initInteractions();
        
        // Mettre à jour les stats en temps réel
        UIManager.startRealTimeUpdates();
    }

    initEventHandlers() {
        // Gestionnaire global pour les états de chargement
        document.addEventListener('submit', FormManager.handleFormSubmit);
        
        // Gestionnaire pour copier les IDs
        document.querySelectorAll('.copy-id-btn').forEach(btn => {
            btn.addEventListener('click', Utils.handleCopyId);
        });
        
        // Gestionnaire pour le toggle du header
        const toggleBtn = document.getElementById('toggleProfileHeader');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', Utils.toggleProfileHeader);
        }
        
        // Gestionnaire pour l'export de profil
        const exportBtn = document.getElementById('btn-export-profile');
        if (exportBtn) {
            exportBtn.addEventListener('click', Utils.handleExportProfile);
        }
    }

    initInteractions() {
        // Hover sur les cartes de stats
        document.querySelectorAll('.stat-card').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 10px 20px rgba(0,0,0,0.1)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        });
        
        // Animation des boutons d'action
        document.querySelectorAll('.quick-actions .btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(4px)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    }

    initAdminActions() {
        // Cette fonction est maintenant gérée par initSpecificActions
        // Maintenue pour la compatibilité
    }

    updateUI() {
        UIManager.updateActionButtons();
        
        // Mettre à jour les indicateurs de statut
        const statusIndicator = document.querySelector('.status-indicator');
        if (statusIndicator) {
            const isActive = APP_CONFIG.admin.status === 'actif';
            statusIndicator.className = `status-indicator ${isActive ? 'indicator-active' : 'indicator-inactive'}`;
        }
    }
}

// ================= INITIALISATION AU CHARGEMENT =================
document.addEventListener('DOMContentLoaded', async () => {
    console.log('📋 Initialisation du système de profil administrateur');
    
    try {
        // Créer et initialiser le manager
        const adminManager = new AdminProfileManager();
        await adminManager.initialize();
        
        // Initialiser les actions spécifiques
        initSpecificActions();
        
        console.log('🎉 Système complètement initialisé');
        
    } catch (error) {
        console.error('💥 Erreur critique d\'initialisation:', error);
        NotificationManager.showError('Erreur d\'initialisation: ' + error.message);
    }
});

// Initialisation des actions spécifiques
function initSpecificActions() {
    // Bouton d'activation
    const activateBtn = document.getElementById('btn-activate-admin');
    const activateFooterBtn = document.getElementById('btn-activate-admin-footer');
    
    if (activateBtn) {
        activateBtn.addEventListener('click', () => ActionManager.toggleAdminStatus('activate', { skipConfirm: true }));
    }
    
    if (activateFooterBtn) {
        activateFooterBtn.addEventListener('click', () => ActionManager.toggleAdminStatus('activate', { skipConfirm: true }));
    }
    
    // Formulaire de désactivation
    const deactivateForm = document.getElementById('deactivateForm');
    if (deactivateForm) {
        // Activer le bouton de confirmation uniquement si la checkbox est cochée
        const confirmCheckbox = document.getElementById('confirmDeactivate');
        const confirmBtn = document.getElementById('confirmDeactivateBtn');
        if (confirmCheckbox && confirmBtn) {
            confirmCheckbox.addEventListener('change', () => {
                confirmBtn.disabled = !confirmCheckbox.checked;
            });
        }

        deactivateForm.addEventListener('submit', (e) => {
            e.preventDefault();
            // Le modal contient déjà une confirmation via checkbox => pas de second modal
            ActionManager.toggleAdminStatus('deactivate', { skipConfirm: true });
        });
    }
    
    // Formulaire de suppression définitive
    const deleteForm = document.getElementById('permanentDeleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            ActionManager.deleteAdminPermanently();
        });
    }
    
    // Bouton d'affichage de badge
    const showBadgeBtn = document.getElementById('btn-show-badge');
    const showBadgeFooterBtn = document.getElementById('btn-show-badge-footer');
    
    if (showBadgeBtn) {
        showBadgeBtn.addEventListener('click', () => {
            // Ici, vous pouvez ajouter la logique pour afficher un badge personnalisé
            NotificationManager.show('info', 'Fonctionnalité badge en développement', 'Information');
        });
    }
    
    if (showBadgeFooterBtn) {
        showBadgeFooterBtn.addEventListener('click', () => {
            NotificationManager.show('info', 'Fonctionnalité badge en développement', 'Information');
        });
    }
}

    // Exposer certaines fonctions globalement si nécessaire
    window.AdminProfile = {
    config: APP_CONFIG,
    notifications: NotificationManager,
    actions: ActionManager,
    ui: UIManager,
    utils: Utils
    };
</script>

<style>
/* ============================
   VARIABLES DU THÈME CLAIR
   ============================ */
:root {
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --primary-light: #eef2ff;
    --secondary: #6c757d;
    --success: #28a745;
    --info: #17a2b8;
    --warning: #ffc107;
    --danger: #dc3545;
    --dark: #2d3748;
    --light: #f8f9fa;
    --light-gray: #e9ecef;
    --border-color: #dee2e6;
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --gradient-primary: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
    --gradient-success: linear-gradient(135deg, #28a745 0%, #218838 100%);
    --gradient-danger: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    --gradient-warning: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
}

/* ============================
   STRUCTURE PRINCIPALE
   ============================ */
.profile-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #2d3748;
    background: linear-gradient(135deg, #f5f7ff 0%, #f0f2ff 100%);
    min-height: 100vh;
    padding: 1.5rem 1rem;
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
    overflow-x: hidden;
}

.profile-container::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 400px;
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.05) 0%, rgba(58, 86, 212, 0.03) 100%);
    z-index: -1;
    pointer-events: none;
}

/* ============================
   HEADER & EN-TÊTE
   ============================ */
.profile-header {
    margin-bottom: 2.5rem;
    animation: slideDown 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ============================
   AVATAR ADMINISTRATEUR
   ============================ */
.admin-avatar-initials {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 auto;
    border: 4px solid white;
    box-shadow: var(--shadow-lg);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.admin-avatar-initials::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
    transform: rotate(45deg);
    transition: transform 0.6s ease;
}

.admin-avatar-initials:hover {
    transform: scale(1.05);
    box-shadow: 0 15px 35px rgba(67, 97, 238, 0.3);
}

.admin-avatar-initials:hover::before {
    transform: rotate(45deg) translateX(100%);
}

.bg-gradient-super-admin {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
}

.bg-gradient-admin {
    background: var(--gradient-primary);
}

/* ============================
   BADGES ADMINISTRATEUR
   ============================ */
.admin-badge-container {
    margin: 1.5rem 0;
}

.admin-badge {
    display: inline-block;
    padding: 0.625rem 1.25rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.95rem;
    letter-spacing: 0.3px;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
    border: none;
    position: relative;
    overflow: hidden;
    animation: badgeAppear 0.5s ease-out;
    animation-delay: var(--animation-delay, 0s);
    animation-fill-mode: both;
}

@keyframes badgeAppear {
    from {
        opacity: 0;
        transform: scale(0.8) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.super-admin-badge {
    background: var(--gradient-danger);
    color: white;
}

.admin-badge {
    background: var(--gradient-primary);
    color: white;
}

.admin-badge::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.2) 50%, transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.admin-badge:hover::after {
    transform: translateX(100%);
}

.admin-badge:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.admin-badges-small {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 0.75rem;
}

.badge-hover {
    transition: var(--transition);
    cursor: pointer;
    border: 1px solid transparent;
    animation: badgeFloat 4s ease-in-out infinite;
    animation-delay: calc(var(--animation-delay, 0s) * 0.5);
}

@keyframes badgeFloat {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

.badge-hover:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: var(--shadow-sm);
}

.badge-clickable {
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.badge-clickable::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.4s, height 0.4s;
}

.badge-clickable:active::after {
    width: 120px;
    height: 120px;
}

/* ============================
   CARTES PROFESSIONNELLES
   ============================ */
.admin-profile-card {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    transition: var(--transition);
    overflow: hidden;
    position: relative;
    animation: cardAppear 0.6s ease-out;
    animation-fill-mode: both;
}

@keyframes cardAppear {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.admin-profile-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s ease;
}

.admin-profile-card:hover::before {
    transform: scaleX(1);
}

.admin-profile-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(67, 97, 238, 0.2);
}

.admin-profile-card .card-header {
    background: linear-gradient(135deg, #f8fafc 0%, white 100%);
    border-bottom: 1px solid var(--border-color);
    padding: 1.25rem 1.5rem;
    position: relative;
}

.admin-profile-card .card-header::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 1.5rem;
    right: 1.5rem;
    height: 2px;
    background: var(--gradient-primary);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.admin-profile-card:hover .card-header::after {
    transform: scaleX(1);
}

.admin-profile-card .card-body {
    padding: 1.5rem;
}

/* ============================
   GRILLE D'INFORMATIONS
   ============================ */
.admin-info-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    transition: var(--transition);
    animation: infoItemAppear 0.5s ease-out;
    animation-fill-mode: both;
}

@keyframes infoItemAppear {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.info-item:hover {
    background: white;
    border-color: var(--primary);
    transform: translateX(4px);
    box-shadow: var(--shadow-sm);
}

.info-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.25rem;
    transition: var(--transition);
}

.info-item:hover .info-icon {
    transform: scale(1.1) rotate(5deg);
    background: var(--primary);
    color: white;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.75rem;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--dark);
}

.info-value a {
    color: var(--primary);
    text-decoration: none;
    transition: color 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.info-value a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* ============================
   STATISTIQUES
   ============================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}

.stat-card {
    padding: 1.5rem;
    background: white;
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    transition: var(--transition);
    text-align: center;
    animation: statCardAppear 0.6s ease-out;
    animation-fill-mode: both;
}

@keyframes statCardAppear {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: var(--radius-md);
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    transition: var(--transition);
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
    background: var(--primary);
    color: white;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--secondary);
    margin-bottom: 0.25rem;
}

.stat-trend {
    font-size: 0.8rem;
    color: var(--success);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

/* ============================
   ACTIONS RAPIDES
   ============================ */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-actions .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.875rem 1rem;
    border-radius: var(--radius-md);
    font-weight: 500;
    transition: var(--transition);
    gap: 0.5rem;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.quick-actions .btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.quick-actions .btn:hover::before {
    width: 300px;
    height: 300px;
}

.quick-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.quick-actions .btn-primary {
    background: var(--gradient-primary);
    color: white;
}

.quick-actions .btn-warning {
    background: var(--gradient-warning);
    color: #212529;
}

.quick-actions .btn-success {
    background: var(--gradient-success);
    color: white;
}

.quick-actions .btn-danger {
    background: var(--gradient-danger);
    color: white;
}

.quick-actions .btn-outline-primary {
    background: transparent;
    color: var(--primary);
    border-color: var(--primary);
}

.quick-actions .btn-outline-primary:hover {
    background: var(--primary);
    color: white;
}

/* ============================
   ANIMATIONS GLOBALES
   ============================ */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Classes d'animation */
.animate-fade-in {
    animation: fadeIn 0.6s ease-out;
}

.animate-slide-right {
    animation: slideInRight 0.6s ease-out;
}

.animate-slide-left {
    animation: slideInLeft 0.6s ease-out;
}

/* ============================
   EFFETS DE PULSATION
   ============================ */
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
    }
}

.pulse-animation {
    animation: pulse 2s infinite;
}

/* ============================
   LOADING SKELETON
   ============================ */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: var(--radius-sm);
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* ============================
   RESPONSIVE DESIGN
   ============================ */
@media (max-width: 1200px) {
    .admin-profile-container {
        max-width: 100%;
        padding: 1.25rem;
    }
}

@media (max-width: 992px) {
    .admin-profile-container {
        padding: 1rem;
    }
    
    .admin-avatar-initials {
        width: 100px;
        height: 100px;
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .admin-profile-card .card-body {
        padding: 1.25rem;
    }
}

@media (max-width: 768px) {
    .admin-profile-container {
        padding: 0.75rem;
    }
    
    .admin-profile-header {
        margin-bottom: 1.5rem;
    }
    
    .admin-avatar-initials {
        width: 80px;
        height: 80px;
        font-size: 1.5rem;
    }
    
    .admin-badge {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .info-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .info-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .quick-actions .btn {
        padding: 0.75rem;
        font-size: 0.9rem;
    }
    
    .admin-profile-card .card-body {
        padding: 1rem;
    }
    
    .admin-profile-card .card-header {
        padding: 1rem;
    }
}

@media (max-width: 576px) {
    .admin-profile-container {
        padding: 0.5rem;
    }
    
    .admin-avatar-initials {
        width: 70px;
        height: 70px;
        font-size: 1.25rem;
    }
    
    .admin-badge {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
    
    .admin-badges-small {
        flex-direction: column;
        align-items: center;
    }
}

/* ============================
   TRANSITIONS DE PAGES
   ============================ */
.page-transition-enter {
    opacity: 0;
    transform: translateY(20px);
}

.page-transition-enter-active {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 0.5s, transform 0.5s;
}

.page-transition-exit {
    opacity: 1;
    transform: translateY(0);
}

.page-transition-exit-active {
    opacity: 0;
    transform: translateY(-20px);
    transition: opacity 0.5s, transform 0.5s;
}

/* ============================
   ACCESSIBILITÉ
   ============================ */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* ============================
   IMPRESSION
   ============================ */
@media print {
    .admin-profile-container {
        background: white !important;
        padding: 0 !important;
    }
    
    .admin-profile-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
    
    .quick-actions,
    .admin-badge:hover,
    .info-item:hover,
    .stat-card:hover {
        transform: none !important;
    }
    
    .no-print {
        display: none !important;
    }
}

/* ============================
   ÉTATS DE CHARGEMENT
   ============================ */
.loading-state {
    pointer-events: none;
    opacity: 0.7;
    position: relative;
}

.loading-state::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.7);
    z-index: 10;
}

.loading-state::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    margin: -20px 0 0 -20px;
    border: 3px solid var(--primary-light);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    z-index: 11;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
/* Animations supplémentaires */
@keyframes badgePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes pulse-gold {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}

@keyframes pulse-blue {
    0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
    100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
}

@keyframes pulse-green {
    0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); }
    100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
}

/* Styles pour les indicateurs de force de mot de passe */
.indicator {
    width: 20px;
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.indicator.active {
    transform: scaleY(1.2);
}

/* Transitions fluides */
.admin-profile-card,
.stat-card,
.btn,
.badge {
    transition: all 0.3s ease;
}

/* Effet de survol amélioré */
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Responsive amélioré */
@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-actions {
        width: 100%;
        justify-content: center;
        margin-top: 1rem;
    }
    
    .quick-actions .btn {
        width: 100%;
    }
}

/* Styles pour les états de chargement */
.btn-loading {
    position: relative;
    color: transparent !important;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-top: -10px;
    margin-left: -10px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Amélioration des modals */
.modal-content {
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
}

/* Classes utilitaires */
.disabled-clickable {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
}

.indicator-active {
    background-color: #198754;
    animation: pulseActive 2s infinite;
}

.indicator-inactive {
    background-color: #6c757d;
}

@keyframes pulseActive {
    0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); }
    100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
}


/* ============================
   TOOLTIPS PERSONNALISÉS
   ============================ */
.tooltip-custom {
    position: relative;
}

.tooltip-custom::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem 0.75rem;
    background: var(--dark);
    color: white;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
    z-index: 1000;
    pointer-events: none;
}

.tooltip-custom:hover::after {
    opacity: 1;
    visibility: visible;
}
</style>
            </div>
        </main>
    </div>

<?php include 'partials/footer.php'; ?>