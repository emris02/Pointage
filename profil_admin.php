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

<div class="admin-profile-container">
    <!-- En-tête -->
    <header class="admin-profile-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold mb-2">
                    <i class="fas fa-user-shield me-2 text-primary"></i>
                    Profil Administrateur
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard_unifie.php">Tableau de Bord</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard_unifie.php#admins">Administrateurs</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?>
                        </li>
                    </ol>
                </nav>
            </div>
            
            <div class="btn-group">
                <a href="admin_dashboard_unifie.php#admins" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Retour
                </a>
                
                <?php if ($is_editing_own || $is_super_admin): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fas fa-edit me-1"></i> Modifier
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Messages d'alerte -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-3 fs-5"></i>
            <div class="flex-grow-1">
                <?php 
                switch ($_GET['success']) {
                    case 'profile_updated': echo 'Profil mis à jour avec succès.'; break;
                    case 'password_changed': echo 'Mot de passe modifié avec succès.'; break;
                    default: echo 'Opération réussie.';
                }
                ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
            <div class="flex-grow-1">
                <?php 
                switch ($_GET['error']) {
                    case 'password_mismatch': echo 'Les mots de passe ne correspondent pas.'; break;
                    case 'current_password_wrong': echo 'Mot de passe actuel incorrect.'; break;
                    case 'password_too_short': echo 'Le mot de passe doit contenir au moins 8 caractères.'; break;
                    default: echo 'Une erreur est survenue.';
                }
                ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenu principal -->
    <div class="row g-4">
        <!-- Colonne gauche : Informations personnelles -->
        <div class="col-lg-4">
            <div class="card admin-profile-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-user-circle me-2 text-primary"></i>
                        Informations personnelles
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Avatar/Initiales -->
                    <div class="text-center mb-4">
                        <?php 
                        $initials = strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1));
                        ?>
                        <div class="admin-avatar-initials bg-primary text-white mb-3">
                            <?= $initials ?>
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></h4>
                        
                        <!-- Badge Admin avec effet de zoom -->
                        <div class="admin-badge-container mb-2">
                            <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                                <div class="admin-badge super-admin-badge" data-bs-toggle="modal" data-bs-target="#badgeModal" data-badge-type="super-admin">
                                    <i class="fas fa-crown me-1"></i> Super Administrateur
                                </div>
                            <?php else: ?>
                                <div class="admin-badge admin-badge" data-bs-toggle="modal" data-bs-target="#badgeModal" data-badge-type="admin">
                                    <i class="fas fa-user-shield me-1"></i> Administrateur
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Petits badges supplémentaires -->
                        <div class="admin-badges-small">
                            <span class="badge bg-success badge-hover" data-bs-toggle="modal" data-bs-target="#badgeModal" data-badge-type="active">
                                <i class="fas fa-check-circle me-1"></i> Actif
                            </span>
                            <span class="badge bg-info badge-hover" data-bs-toggle="modal" data-bs-target="#badgeModal" data-badge-type="verified">
                                <i class="fas fa-check me-1"></i> Vérifié
                            </span>
                            <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                                <span class="badge bg-danger badge-hover" data-bs-toggle="modal" data-bs-target="#badgeModal" data-badge-type="full-access">
                                    <i class="fas fa-unlock me-1"></i> Accès complet
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark badge-hover" data-bs-toggle="modal" data-bs-target="#badgeModal" data-badge-type="limited-access">
                                    <i class="fas fa-lock me-1"></i> Accès limité
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope text-primary"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email professionnel</div>
                                <a href="mailto:<?= htmlspecialchars($admin['email']) ?>" class="info-value">
                                    <?= htmlspecialchars($admin['email']) ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone text-primary"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Téléphone</div>
                                <a href="tel:<?= htmlspecialchars($admin['telephone'] ?? '') ?>" class="info-value">
                                    <?= htmlspecialchars($admin['telephone'] ?? 'Non renseigné') ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt text-primary"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Localisation</div>
                                <div class="info-value">Mali, Bamako</div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-clock text-primary"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Fuseau horaire</div>
                                <div class="info-value">GMT (UTC+0)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne centrale : Informations du compte -->
        <div class="col-lg-4">
            <div class="card admin-profile-card mb-4">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        Informations du compte
                    </h5>
                </div>
                <div class="card-body">
                    <div class="account-info">
                        <div class="info-item mb-3">
                            <div class="info-label">
                                <i class="fas fa-hashtag me-2 text-muted"></i> ID
                            </div>
                            <div class="info-value">
                                #<?= $admin['id'] ?>
                            </div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <div class="info-label">
                                <i class="fas fa-shield-alt me-2 text-muted"></i> Rôle
                            </div>
                            <div class="info-value">
                                <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                                    <span class="badge badge-super-admin badge-clickable" 
                                          data-bs-toggle="modal" 
                                          data-bs-target="#badgeModal"
                                          data-badge-type="super-admin">
                                        <i class="fas fa-crown me-1"></i> Super Admin
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-admin badge-clickable" 
                                          data-bs-toggle="modal" 
                                          data-bs-target="#badgeModal"
                                          data-badge-type="admin">
                                        <i class="fas fa-user-shield me-1"></i> Administrateur
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <div class="info-label">
                                <i class="fas fa-circle me-2 text-muted"></i> Statut
                            </div>
                            <div class="info-value">
                                <span id="admin-statut" class="badge <?= $admin['statut'] === 'actif' ? 'badge-active' : 'badge-inactive' ?> badge-clickable" 
                                      data-bs-toggle="modal" 
                                      data-bs-target="#badgeModal"
                                      data-badge-type="<?= $admin['statut'] === 'actif' ? 'active' : 'limited-access' ?>">
                                    <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars(ucfirst($admin['statut'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus me-2 text-muted"></i> Compte créé
                            </div>
                            <div class="info-value">
                                <?= isset($admin['created_at']) ? htmlspecialchars($admin['created_at']) : '—' ?>
                            </div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <div class="info-label">
                                <i class="fas fa-clock me-2 text-muted"></i> Dernière connexion
                            </div>
                            <div class="info-value">
                                <?= $last_activity ? formatDateMali($last_activity, true) : "—" ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-sync-alt me-2 text-muted"></i> Dernière mise à jour
                            </div>
                            <div class="info-value">
                                <?= isset($admin['created_at']) ? htmlspecialchars($admin['created_at']) : '—' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carte : Actions rapides -->
            <div class="card admin-profile-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-bolt me-2 text-warning"></i>
                        Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <button class="btn btn-outline-primary w-100 mb-2" id="btn-edit-admin" onclick="showEditModal()">
                            <i class="fas fa-edit me-2"></i> Modifier le profil
                        </button>
                        
                        <button class="btn btn-outline-warning w-100 mb-2" id="btn-change-password" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key me-2"></i> Changer le mot de passe
                        </button>
                        
                        <a href="admin_dashboard_unifie.php#employes" id="btn-manage-employees" class="btn btn-outline-success w-100 mb-2">
                            <i class="fas fa-users me-2"></i> Gérer les employés
                        </a>
                        
                        <a href="admin_dashboard_unifie.php#demandes" id="btn-view-requests" class="btn btn-outline-info w-100 mb-2">
                            <i class="fas fa-clipboard-list me-2"></i> Voir les demandes
                        </a>
                        
                        <button class="btn btn-outline-secondary w-100 mb-2" id="btn-show-badge">
                            <i class="fas fa-qrcode me-2"></i> Afficher le badge
                        </button>

                        <?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
                            <button class="btn btn-outline-danger w-100 mb-2" id="btn-deactivate-admin" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-user-slash me-2"></i> Désactiver le compte
                            </button>

                            <!-- Activer et Supprimer (visibles uniquement lorsque le compte est inactif) -->
                            <button class="btn btn-outline-success w-100 mb-2" id="btn-activate-admin" style="display:none">
                                <i class="fas fa-user-check me-2"></i> Activer le compte
                            </button>

                            <button class="btn btn-danger w-100 mb-2" id="btn-delete-admin" style="display:none" data-bs-toggle="modal" data-bs-target="#deleteModalPermanent">
                                <i class="fas fa-trash-alt me-2"></i> Supprimer définitivement
                            </button>
                        <?php endif; ?>
                    </div> 
                </div>
            </div>
        </div>

        <!-- Colonne droite : Statistiques -->
        <div class="col-lg-4">
            <div class="card admin-profile-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-chart-bar me-2 text-success"></i>
                        Statistiques d'activité
                    </h5>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <?php if ($admin['role'] === ROLE_ADMIN): ?>
                        <div class="stat-item">
                            <div class="stat-icon bg-primary-light">
                                <i class="fas fa-users text-primary"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?= $stats['employes_geres'] ?></div>
                                <div class="stat-label">Employés gérés</div>
                                <div class="stat-subtext">Total actifs</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($admin['role'] === ROLE_SUPER_ADMIN): ?>
                        <div class="stat-item">
                            <div class="stat-icon bg-danger-light">
                                <i class="fas fa-user-shield text-danger"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?= $stats['admins_crees'] ?></div>
                                <div class="stat-label">Admins créés</div>
                                <div class="stat-subtext">Par ce super admin</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="stat-item">
                            <div class="stat-icon bg-success-light">
                                <i class="fas fa-clipboard-check text-success"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?= $stats['demandes_traitees'] ?></div>
                                <div class="stat-label">Demandes traitées</div>
                                <div class="stat-subtext">30 derniers jours</div>
                            </div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-icon bg-info-light">
                                <i class="fas fa-fingerprint text-info"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?= $stats['pointages_recent'] ?></div>
                                <div class="stat-label">Pointages récents</div>
                                <div class="stat-subtext">7 derniers jours</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Performance globale</span>
                            <span class="badge badge-performance badge-clickable" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#badgeModal"
                                  data-badge-type="performance">
                                <i class="fas fa-chart-line me-1"></i> Excellent
                            </span>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: 92%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal : Badge en grand -->
<div class="modal fade" id="badgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <!-- Contenu dynamique rempli par JavaScript -->
                <div id="badgeContent" class="badge-modal-content">
                    <div class="badge-large mb-3" id="badgeIconLarge"></div>
                    <h4 id="badgeTitle" class="fw-bold mb-2"></h4>
                    <p id="badgeDescription" class="text-muted mb-3"></p>
                    <div class="badge-details" id="badgeDetails"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal : Modifier le profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="update_admin_profile.php" method="POST" id="editProfileForm">
                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Modifier le profil
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nom</label>
                            <input type="text" name="nom" class="form-control" 
                                   value="<?= htmlspecialchars($admin['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prénom</label>
                            <input type="text" name="prenom" class="form-control" 
                                   value="<?= htmlspecialchars($admin['prenom']) ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Téléphone</label>
                            <input type="tel" name="telephone" class="form-control" 
                                   value="<?= htmlspecialchars($admin['telephone'] ?? '') ?>"
                                   placeholder="+223 XX XX XX XX">
                        </div>
                        
                        <?php if ($is_super_admin && !$is_editing_own): ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Rôle</label>
                            <select name="role" class="form-select">
                                <option value="<?= ROLE_ADMIN ?>" <?= $admin['role'] === ROLE_ADMIN ? 'selected' : '' ?>>
                                    Administrateur
                                </option>
                                <option value="<?= ROLE_SUPER_ADMIN ?>" <?= $admin['role'] === ROLE_SUPER_ADMIN ? 'selected' : '' ?>>
                                    Super Administrateur
                                </option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal : Changer le mot de passe -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="change_admin_password.php" method="POST" id="changePasswordForm">
                <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>
                        Changer le mot de passe
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($is_editing_own): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mot de passe actuel</label>
                        <div class="input-group">
                            <input type="password" name="current_password" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" name="new_password" class="form-control" 
                                   required minlength="8"
                                   placeholder="Minimum 8 caractères">
                            <button type="button" class="btn btn-outline-secondary toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Inclure majuscules, minuscules et chiffres</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="password-strength mt-3">
                        <div class="strength-meter">
                            <div class="strength-bar"></div>
                        </div>
                        <div class="strength-text small mt-1"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-1"></i> Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal : Désactiver le compte -->
<?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-slash me-2"></i>
                    Désactiver le compte
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle fa-2x"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="alert-heading">Attention !</h6>
                            <p class="mb-0">
                                Vous êtes sur le point de désactiver le compte administrateur de 
                                <strong><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></strong>.
                                L'administrateur ne pourra plus se connecter.
                            </p>
                        </div>
                    </div>
                </div>
                
                <form action="deactivate_admin.php" method="POST" id="deactivateForm">
                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeactivate" required>
                        <label class="form-check-label" for="confirmDeactivate">
                            Je confirme vouloir désactiver ce compte administrateur
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="deactivateForm" class="btn btn-danger" id="confirmDeactivateBtn" disabled>
                    <i class="fas fa-user-slash me-1"></i> Désactiver
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Hidden forms for activate and delete actions -->
<form id="activateForm" action="activate_admin.php" method="POST" style="display:none">
    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
</form>

<!-- Modal : Supprimer définitivement -->
<?php if ($is_super_admin && !$is_editing_own && $admin['role'] !== ROLE_SUPER_ADMIN): ?>
<div class="modal fade" id="deleteModalPermanent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Supprimer définitivement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <h6 class="alert-heading">Suppression définitive</h6>
                    <p>Cette action supprimera définitivement l'administrateur et toutes ses données associées. Cette opération est irréversible.</p>
                </div>
                <form action="supprimer_admin_def.php" method="POST" id="permanentDeleteForm">
                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                    <div class="mb-3">
                        <label for="confirmText" class="form-label">Tapez <strong>SUPPRIMER</strong> pour confirmer</label>
                        <input type="text" id="confirmText" name="confirm_text" class="form-control" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="permanentDeleteForm" class="btn btn-danger">Supprimer</button>
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
// Configuration
const ADMIN_PROFILE = {
    isEditingOwn: <?= $is_editing_own ? 'true' : 'false' ?>,
    isSuperAdmin: <?= $is_super_admin ? 'true' : 'false' ?>,
    adminName: "<?= addslashes($admin['prenom'] . ' ' . $admin['nom']) ?>",
    adminRole: "<?= $admin['role'] ?>",
    adminId: <?= (int)$admin['id'] ?>,
    adminStatut: "<?= $admin['statut'] ?>"
};

// Données des badges
const BADGES_DATA = {
    'super-admin': {
        title: 'Super Administrateur',
        icon: 'fas fa-crown',
        description: 'Privilèges administratifs complets',
        details: 'Peut gérer tous les administrateurs, paramètres système et accéder à toutes les fonctionnalités.',
        color: '#dc3545',
        bgColor: '#f8d7da',
        textColor: '#721c24'
    },
    'admin': {
        title: 'Administrateur',
        icon: 'fas fa-user-shield',
        description: 'Gestion des employés et demandes',
        details: 'Peut gérer les employés, traiter les demandes et voir les rapports.',
        color: '#0d6efd',
        bgColor: '#cfe2ff',
        textColor: '#052c65'
    },
    'active': {
        title: 'Compte Actif',
        icon: 'fas fa-check-circle',
        description: 'Compte activé et fonctionnel',
        details: 'Le compte est actuellement actif et peut se connecter au système.',
        color: '#198754',
        bgColor: '#d1e7dd',
        textColor: '#0a3622'
    },
    'verified': {
        title: 'Email Vérifié',
        icon: 'fas fa-check',
        description: 'Adresse email confirmée',
        details: 'L\'adresse email a été vérifiée et est valide.',
        color: '#0dcaf0',
        bgColor: '#cff4fc',
        textColor: '#055160'
    },
    'full-access': {
        title: 'Accès Complet',
        icon: 'fas fa-unlock',
        description: 'Accès à toutes les fonctionnalités',
        details: 'A accès à l\'ensemble des fonctionnalités du système sans restriction.',
        color: '#6f42c1',
        bgColor: '#e2d9f3',
        textColor: '#2d1b69'
    },
    'limited-access': {
        title: 'Accès Limité',
        icon: 'fas fa-lock',
        description: 'Accès restreint aux fonctionnalités',
        details: 'Accès limité aux fonctionnalités spécifiques de son rôle.',
        color: '#fd7e14',
        bgColor: '#ffe5d0',
        textColor: '#662d01'
    },
    'performance': {
        title: 'Performance Excellente',
        icon: 'fas fa-chart-line',
        description: 'Performance administrative élevée',
        details: 'Cet administrateur maintient une excellente performance dans ses tâches.',
        color: '#20c997',
        bgColor: '#d1f7eb',
        textColor: '#0a3622'
    }
};

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    console.log('👑 Profil administrateur - Affichage seulement');
    
    initAdminProfile();
    initBadgesSystem();
    initModals();
    initPasswordStrength();
    animateCards(); // Correction : la fonction existe bien
});

// Fonctions principales
function initAdminProfile() {
        animateCards();
        // Initialiser les interactions
        initInteractions();
        updateAdminActionButtons();
        // Mettre à jour les indicateurs en charge
        updateRealTimeStats();
} 

// Affichage dynamique des boutons selon le statut admin
function updateAdminActionButtons() {
        const btnDeactivate = document.getElementById('btn-deactivate-admin');
        const btnActivate = document.getElementById('btn-activate-admin');
        const btnDelete = document.getElementById('btn-delete-admin');
        const btnShowBadge = document.getElementById('btn-show-badge');
        // Prefer the canonical status from the CONFIG (adminStatut), fallback to DOM text
        let statut = typeof ADMIN_PROFILE.adminStatut === 'string' ? ADMIN_PROFILE.adminStatut.toLowerCase() : null;
        if (!statut) {
            statut = document.getElementById('admin-statut')?.textContent?.trim().toLowerCase();
        }
        if (!statut) return;

        if (statut === 'inactif') {
                if (btnDeactivate) btnDeactivate.style.display = 'none';
                if (btnActivate) btnActivate.style.display = '';
                if (btnDelete) btnDelete.style.display = '';
                // Désactiver les autres actions
                setProfileInteractivity(false);
        } else {
                if (btnDeactivate) btnDeactivate.style.display = '';
                if (btnActivate) btnActivate.style.display = 'none';
                if (btnDelete) btnDelete.style.display = 'none';
                // Réactiver les actions
                setProfileInteractivity(true);
        }
        if (btnShowBadge) btnShowBadge.style.display = '';
}

// Affichage du badge QR dans un modal
function showAdminBadgeModal(badge) {
        // badge: { token, token_hash, expires_at, can_regenerate }
        const hasQRious = typeof QRious !== 'undefined';
        const qrImgHtml = hasQRious ? `<img id="admin-badge-qr" alt="QR Code Admin" style="max-width:250px;" />` :
            `<img id="admin-badge-qr" src="https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=${encodeURIComponent(badge.token)}" alt="QR Code Admin" style="max-width:250px;" />`;

        const regenBtnHtml = badge.can_regenerate ? `<button id="regen-badge-btn" class="btn btn-sm btn-outline-primary">Régénérer le badge</button>` : '';

        const modalHtml = `
        <div class="modal fade" id="modalBadgeAdmin" tabindex="-1" aria-labelledby="modalBadgeAdminLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalBadgeAdminLabel">Badge d'accès Administrateur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body text-center">
                        ${qrImgHtml}
                        <p class="mt-3">Présentez ce badge à la borne pour pointer vos heures d'arrivée et de départ.</p>
                        <p class="small text-muted">Exp: ${badge.expires_at || '—'}</p>
                        <p class="small text-break">Token hash: <code id="badge-token-hash">${badge.token_hash || '—'}</code></p>
                        <div class="mt-2">${regenBtnHtml} <button id="copy-token-btn" class="btn btn-sm btn-outline-secondary">Copier le token</button></div>
                    </div>
                </div>
            </div>
        </div>`;

        let modalDiv = document.getElementById('modalBadgeAdmin');
        if (modalDiv) modalDiv.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // If QRious available, render data URL
        if (hasQRious && badge.token) {
            try {
                const qr = new QRious({ value: badge.token, size: 300 });
                const img = document.getElementById('admin-badge-qr');
                img.src = qr.toDataURL();
            } catch (e) {
                console.warn('QR generation failed', e);
            }
        }

        // Copy token
        const copyBtn = document.getElementById('copy-token-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                if (!badge.token) return showNotification('warning', 'Token non disponible');
                navigator.clipboard.writeText(badge.token).then(() => {
                    showNotification('success', 'Token copié dans le presse-papier');
                }).catch(() => showNotification('error', 'Impossible de copier le token'));
            });
        }

        // Regenerate
        const regenBtn = document.getElementById('regen-badge-btn');
        if (regenBtn) {
            regenBtn.addEventListener('click', async function() {
                if (!confirm('Générer un nouveau badge pour cet administrateur ?')) return;
                try {
                    const form = new FormData();
                    form.append('action', 'regenerate');
                    form.append('admin_id', ADMIN_PROFILE.adminId);
                    const resp = await fetch('admin_badge_api.php', { method: 'POST', body: form });
                    const data = await resp.json();
                    if (data.status === 'success' && data.token) {
                        showNotification('success', 'Badge régénéré');
                        // Update modal with new qr
                        const img = document.getElementById('admin-badge-qr');
                        const tokenHashEl = document.getElementById('badge-token-hash');
                        if (img) {
                            if (typeof QRious !== 'undefined') {
                                const qr2 = new QRious({ value: data.token, size: 300 });
                                img.src = qr2.toDataURL();
                            } else {
                                img.src = `https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=${encodeURIComponent(data.token)}`;
                            }
                        }
                        if (tokenHashEl) tokenHashEl.textContent = data.token_hash || '—';
                    } else {
                        showNotification('error', 'Impossible de régénérer le badge');
                    }
                } catch (err) {
                    console.error(err);
                    showNotification('error', 'Erreur lors de la régénération');
                }
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalBadgeAdmin'));
        modal.show();
}

// Système de badges
function initBadgesSystem() {
    // Configurer le modal de badge
    const badgeModal = document.getElementById('badgeModal');
    if (badgeModal) {
        badgeModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const badgeType = button.getAttribute('data-badge-type');
            loadBadgeDetails(badgeType);
        });
    }
    
    // Ajouter des effets de hover sur tous les badges cliquables
    document.querySelectorAll('.badge-clickable, .badge-hover, .admin-badge').forEach(badge => {
        // Effet de survol
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05) translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            this.style.cursor = 'pointer';
            this.style.transition = 'all 0.2s ease';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) translateY(0)';
            this.style.boxShadow = '';
        });
        
        // Effet de clic
        badge.addEventListener('click', function(e) {
            // Animation de clic
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
    
    // Animation des badges principaux
    const mainBadges = document.querySelectorAll('.admin-badge');
    mainBadges.forEach((badge, index) => {
        badge.style.animationDelay = `${index * 0.1}s`;
        badge.classList.add('badge-animate');
    });
}

function loadBadgeDetails(badgeType) {
    const badgeData = BADGES_DATA[badgeType];
    if (!badgeData) return;
    
    // Remplir le modal avec les données du badge
    document.getElementById('badgeTitle').textContent = badgeData.title;
    document.getElementById('badgeDescription').textContent = badgeData.description;
    document.getElementById('badgeDetails').textContent = badgeData.details;
    
    // Créer l'icône du badge en grand
    const badgeIconContainer = document.getElementById('badgeIconLarge');
    badgeIconContainer.innerHTML = `<i class="${badgeData.icon} fa-4x" style="color: ${badgeData.color}"></i>`;
    
    // Appliquer le style au contenu du modal
    const modalContent = document.querySelector('.badge-modal-content');
    modalContent.style.backgroundColor = badgeData.bgColor;
    modalContent.style.color = badgeData.textColor;
    modalContent.style.borderRadius = '1rem';
    modalContent.style.padding = '2rem';
    
    // Ajouter une animation d'entrée
    modalContent.style.animation = 'badgeZoomIn 0.3s ease-out';
}

function initModals() {
    // Modal désactivation
    const confirmCheckbox = document.getElementById('confirmDeactivate');
    const deactivateBtn = document.getElementById('confirmDeactivateBtn');
    
    if (confirmCheckbox && deactivateBtn) {
        confirmCheckbox.addEventListener('change', function() {
            deactivateBtn.disabled = !this.checked;
        });
    }
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
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
    
    // Validation des formulaires
    const editForm = document.getElementById('editProfileForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                showNotification('error', 'Veuillez remplir tous les champs obligatoires');
            }
        });
    }
    
    const passwordForm = document.getElementById('changePasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPass = this.querySelector('input[name="new_password"]');
            const confirmPass = this.querySelector('input[name="confirm_password"]');
            
            if (newPass.value !== confirmPass.value) {
                e.preventDefault();
                showNotification('error', 'Les mots de passe ne correspondent pas');
                return;
            }
            
            if (newPass.value.length < 8) {
                e.preventDefault();
                showNotification('error', 'Le mot de passe doit contenir au moins 8 caractères');
            }
        });
    }
}

function initPasswordStrength() {
    const passwordInput = document.querySelector('input[name="new_password"]');
    const strengthContainer = document.querySelector('.password-strength');
    
    if (passwordInput && strengthContainer) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length === 0) {
                strengthContainer.style.display = 'none';
                return;
            }
            
            strengthContainer.style.display = 'block';
            
            let strength = 0;
            let text = '';
            let color = '';
            
            // Vérifications de force
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            strength = Math.min(strength, 100);
            
            // Déterminer le niveau
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
            const strengthBar = strengthContainer.querySelector('.strength-bar');
            const strengthText = strengthContainer.querySelector('.strength-text');
            
            strengthBar.style.width = strength + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = `Force du mot de passe : ${text}`;
            strengthText.style.color = color;
        });
    }
}

function initInteractions() {
    // Hover sur les cartes de stats
    document.querySelectorAll('.stat-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
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

function animateCards() {
    const cards = document.querySelectorAll('.admin-profile-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('card-animate');
    });
}

function updateRealTimeStats() {
    // Heure locale du Mali (Bamako)
    const updateTime = () => {
        const now = new Date();
        // Mali est en GMT (UTC+0)
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
        
        // Date du Mali
        const dateOptions = {
            timeZone: 'Africa/Bamako',
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        const dateString = now.toLocaleDateString('fr-ML', dateOptions);
        document.querySelectorAll('.current-date').forEach(el => {
            el.textContent = dateString;
        });
    };
    
    updateTime();
    setInterval(updateTime, 1000);
}

function showEditModal() {
    const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    modal.show();
}

function showNotification(type, message, title = 'Notification') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    
    Toast.fire({
        icon: type,
        title: title,
        text: message
    });
}

// Utility: show a "Compte désactivé" notification
function showAccountDisabled() {
    showNotification('error', 'Compte désactivé. Cette action n\'est pas autorisée.', 'Compte désactivé');
}

// Enable or disable profile actions (hover allowed but clicks blocked when disabled)
function setProfileInteractivity(enabled) {
    const quickActions = document.querySelector('.quick-actions');
    if (!quickActions) return;

    // Elements that should remain clickable even when account is inactive
    const allowedIds = ['btn-activate-admin', 'btn-delete-admin', 'btn-show-badge'];

    // Select all actionable child buttons and anchors
    const elems = quickActions.querySelectorAll('button, a');
    elems.forEach(el => {
        const id = el.id || '';
        const shouldAllow = allowedIds.includes(id);

        if (!enabled && !shouldAllow) {
            el.classList.add('disabled-clickable');
            // Prevent default clicks and show message
            if (!el._disabledHandler) {
                el.addEventListener('click', el._disabledHandler = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showAccountDisabled();
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

// Hook up special buttons
document.addEventListener('DOMContentLoaded', function() {
    // Show badge
    const showBadgeBtn = document.getElementById('btn-show-badge');
    if (showBadgeBtn) {
        showBadgeBtn.addEventListener('click', async function() {
            if (ADMIN_PROFILE.adminStatut === 'inactif') return showAccountDisabled();
            try {
                const res = await fetch(`admin_badge_api.php?id=${ADMIN_PROFILE.adminId}`);
                const data = await res.json();

                if (data.status === 'success') {
                    const badge = {
                        token: data.token?.token || null,
                        token_hash: data.token?.token_hash || null,
                        expires_at: data.token?.expires_at || null,
                        can_regenerate: data.admin?.can_regenerate || false
                    };
                    if (badge.token) {
                        showAdminBadgeModal(badge);
                        return;
                    }

                    if (badge.can_regenerate) {
                        if (confirm('Aucun badge actif. Générer un nouveau badge d\'accès maintenant ?')) {
                            const form = new FormData();
                            form.append('action', 'regenerate');
                            form.append('admin_id', ADMIN_PROFILE.adminId);

                            const regenRes = await fetch('admin_badge_api.php', { method: 'POST', body: form });
                            const regenData = await regenRes.json();
                            if (regenData.status === 'success' && regenData.token) {
                                const regenBadge = { token: regenData.token, token_hash: regenData.token_hash || null, expires_at: regenData.expires_at || null, can_regenerate: true };
                                showAdminBadgeModal(regenBadge);
                            } else {
                                showNotification('error', 'Impossible de générer le badge', 'Erreur');
                            }
                        }
                        return;
                    }
                }


                showNotification('warning', 'Badge non disponible', 'Information');
            } catch (e) {
                console.error(e);
                showNotification('error', 'Impossible de récupérer le badge', 'Erreur');
            }
        });
    }

    // Activate (AJAX)
    const activateBtn = document.getElementById('btn-activate-admin');
    if (activateBtn) {
        activateBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            if (!confirm('Confirmer l\'activation de ce compte administrateur ?')) return;
            try {
                const form = new FormData();
                form.append('admin_id', ADMIN_PROFILE.adminId);
                const resp = await fetch('activate_admin.php', { method: 'POST', body: form, redirect: 'follow' });
                // The backend redirects back with ?success=admin_activated on success
                if (resp.ok && resp.url && resp.url.indexOf('success=admin_activated') !== -1) {
                    ADMIN_PROFILE.adminStatut = 'actif';
                    const statutEl = document.getElementById('admin-statut');
                    if (statutEl) {
                        statutEl.classList.remove('badge-inactive');
                        statutEl.classList.add('badge-active');
                        statutEl.innerHTML = '<i class="fas fa-check-circle me-1"></i> Actif';
                    }
                    updateAdminActionButtons();
                    showNotification('success', 'Compte activé', 'Succès');
                } else {
                    showNotification('error', 'Impossible d\'activer le compte', 'Erreur');
                }
            } catch (err) {
                console.error(err);
                showNotification('error', 'Erreur réseau', 'Erreur');
            }
        });
    }

    // Deactivate (AJAX)
    const deactivateForm = document.getElementById('deactivateForm');
    if (deactivateForm) {
        deactivateForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(deactivateForm);
            try {
                const resp = await fetch('deactivate_admin.php', { method: 'POST', body: formData, redirect: 'follow' });
                if (resp.ok && resp.url && resp.url.indexOf('success=admin_deactivated') !== -1) {
                    ADMIN_PROFILE.adminStatut = 'inactif';
                    const statutEl = document.getElementById('admin-statut');
                    if (statutEl) {
                        statutEl.classList.remove('badge-active');
                        statutEl.classList.add('badge-inactive');
                        statutEl.innerHTML = '<i class="fas fa-check-circle me-1"></i> Inactif';
                    }
                    // Close modal
                    const deleteModalEl = document.getElementById('deleteModal');
                    const bsModal = bootstrap.Modal.getInstance(deleteModalEl);
                    if (bsModal) bsModal.hide();
                    updateAdminActionButtons();
                    showNotification('success', 'Compte désactivé', 'Succès');
                } else {
                    showNotification('error', 'Impossible de désactiver le compte', 'Erreur');
                }
            } catch (err) {
                console.error(err);
                showNotification('error', 'Erreur réseau', 'Erreur');
            }
        });
    }

    // When the deactivate/activate visibility changes, enforce interactivity on load
    setTimeout(() => {
        // Initial enforcement based on statut
        updateAdminActionButtons();
    }, 50);

    // Guard badge-clickable global behavior when account is inactive
    document.querySelectorAll('.badge-clickable').forEach(b => {
        b.addEventListener('click', function(e) {
            if (ADMIN_PROFILE.adminStatut === 'inactif') {
                e.preventDefault();
                e.stopPropagation();
                showAccountDisabled();
            }
        });
    });
});



// Gestion des états de chargement pour les formulaires
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        const originalWidth = submitBtn.offsetWidth;
        
        submitBtn.style.width = originalWidth + 'px';
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Traitement...';
        submitBtn.disabled = true;
        
        // Restaurer après 10 secondes max (au cas où)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            submitBtn.style.width = '';
        }, 10000);
    }
});
</script>

<style>
/* Variables spécifiques au profil admin */
.admin-profile-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #2124287a;
    /* use central page background variable so theme controls canvas appearance */
    background: var(--page-bg-gradient, var(--page-bg)) !important;
    min-height: 100vh;
    padding: 0.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

.admin-profile-header {
    margin-bottom: 2.5rem;
}

/* Avatar administrateur */
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
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

/* Badges Administrateur */
.admin-badge-container {
    margin-bottom: 1rem;
}

.admin-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    border: 2px solid transparent;
}

.admin-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.super-admin-badge {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border-color: #dc3545;
}

.admin-badge {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    color: white;
    border-color: #0d6efd;
}

.admin-badges-small {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 0.5rem;
}

.badge-hover {
    transition: all 0.2s ease;
    cursor: pointer;
    border: 1px solid transparent;
}

.badge-hover:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.badge-clickable {
    cursor: pointer;
    transition: all 0.2s ease;
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
    transition: width 0.3s, height 0.3s;
}

.badge-clickable:active::after {
    width: 100px;
    height: 100px;
}

/* Badges spécifiques */
.badge-super-admin {
    background: #dc3545;
    color: white;
}

.badge-admin {
    background: #0d6efd;
    color: white;
}

.badge-active {
    background: #198754;
    color: white;
}

.badge-performance {
    background: #20c997;
    color: white;
}

/* Modal Badge */
.badge-modal-content {
    transition: all 0.3s ease;
}

.badge-large {
    animation: badgePulse 2s infinite;
}

@keyframes badgePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes badgeZoomIn {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes badgeFloat {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
}

.badge-animate {
    animation: badgeFloat 3s ease-in-out infinite;
    animation-delay: var(--animation-delay, 0s);
}

/* Cartes */
.admin-profile-card {
    background: white;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    /* box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); */
    transition: all 0.3s ease;
    overflow: hidden;
    /* margin-bottom: 1.5rem; */
}

.admin-profile-card:hover {
    /* box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); */
    transform: translateY(-2px);
}

.admin-profile-card .card-header {
    background: linear-gradient(135deg, #f8fafc 0%, white 100%);
    border-bottom: 1px solid #e2e8f0;
    padding: 1.25rem 1.5rem;
}

.admin-profile-card .card-body {
    padding: 0.5rem;
}

/* Grille d'informations */
.admin-info-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 0.75rem;
    transition: background-color 0.2s ease;
}

.info-item:hover {
    background: #f1f5f9;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 0.75rem;
    background: #dbeafe;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.125rem;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 0.875rem;
    font-weight: 500;
    color: #2c3e50;
}
.card-title .h4 .icon {
    color: #ffff;
    font-size: 0.8rem;
    font-weight: 500;
    color: #ffffff;
}

.info-value a {
    color: #2563eb;
    text-decoration: none;
    transition: color 0.2s ease;
}

.info-value a:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

/* Informations du compte */
.account-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.account-info .info-item {
    background: transparent;
    padding: 0.5rem 0;
    border-radius: 0;
    border-bottom: 1px solid #e2e8f0;
}

.account-info .info-item:last-child {
    border-bottom: none;
}

.account-info .info-label {
    width: 140px;
    flex-shrink: 0;
}

/* Statistiques */
.stats-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem;
    background: #f8fafc;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: white;
    border-color: #3b82f6;
    transform: translateX(4px);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.bg-primary-light { background-color: #dbeafe; }
.bg-danger-light { background-color: #fee2e2; }
.bg-success-light { background-color: #d1fae5; }
.bg-info-light { background-color: #dbeafe; }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 1.75rem;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 0.125rem;
}

.stat-subtext {
    font-size: 0.75rem;
    color: #94a3b8;
}

/* Actions rapides */
.quick-actions .btn {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    border: 2px solid;
    font-weight: 500;
    transition: all 0.3s ease;
    text-align: left;
}

.quick-actions .btn:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Indicateur de force du mot de passe */
.password-strength {
    display: none;
}

.strength-meter {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.strength-bar {
    height: 100%;
    width: 0;
    border-radius: 3px;
    transition: width 0.3s ease, background-color 0.3s ease;
}

/* Animation des cartes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-animate {
    animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    opacity: 0;
}

/* Responsive */
@media (max-width: 992px) {
    .admin-profile-container {
        padding: 0.5rem;
    }
    
    .admin-avatar-initials {
        width: 100px;
        height: 100px;
        font-size: 2rem;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .admin-badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

@media (max-width: 768px) {
    .admin-profile-container {
        padding: 0.5rem;
    }
    
    .admin-profile-header .d-flex {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .admin-profile-card .card-body {
        padding: 0.5rem;
    }
    
    .account-info .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .account-info .info-label {
        width: 100%;
    }
    
    .quick-actions .btn {
        justify-content: center;
        text-align: center;
    }
    
    .admin-badges-small {
        flex-direction: column;
        align-items: center;
    }
}

@media (max-width: 576px) {
    .admin-profile-container {
        padding: 0.75rem;
    }
    
    .admin-avatar-initials {
        width: 80px;
        height: 80px;
        font-size: 1.5rem;
    }
    
    .info-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .info-icon {
        margin: 0 auto;
    }
    
    .stat-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .stat-icon {
        margin: 0 auto;
    }
    
    .admin-badge {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
    }
}

/* Mode sombre */
@media (prefers-color-scheme: dark) {
    .admin-profile-container {
        background: #7f8c8d
        color: #f1f5f9;
    }
}

/* Disabled action (hover allowed but not clickable) */
.disabled-action {
    opacity: 0.85;
}
.disabled-action .btn,
.disabled-action a {
    pointer-events: auto; /* keep hover */
}
.disabled-action .btn.disabled-clickable,
.disabled-action a.disabled-clickable {
    cursor: default;
}

/* Classe pour gérer les éléments non cliquables (tooltip-style) */
.disabled-clickable {
    pointer-events: auto;
    cursor: default !important;
    opacity: 0.9;
}



    
    .admin-profile-card {
        background: #2c3e50;
        border-color: #ffff;
    }
    
    .admin-profile-card .card-header {
        background: linear-gradient(135deg, #2c3e50 0%, #ffff 100%);
        border-color: #475569;
    }
    
    .admin-avatar-initials {
        border-color: #ffff;
    }
    
    .info-item,
    .stat-item {
        background: #ffff;
        border-color: #475569;
    }
    
    .info-item:hover,
    .stat-item:hover {
        background: #475569;
    }
    
    .info-value,
    .stat-number {
        color: #f1f5f9;
    }
    
    .info-label,
    .stat-label {
        color: #94a3b8;
    }
    
    .info-icon {
        background: #475569;
        color: #60a5fa;
    }
    
    .account-info .info-item {
        border-color: #475569;
    }
    
    .quick-actions .btn {
        background: #ffff;
    }
    
    .quick-actions .btn:hover {
        background: #475569;
    }
    
    /* Badges en mode sombre */
    .admin-badge {
        opacity: 0.9;
    }
    
    .admin-badge:hover {
        opacity: 1;
    }
}
</style>

<?php include 'partials/footer.php'; ?>