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

<div class="profile-container">
    <!-- En-tête du profil -->
    <header class="profile-header">
        <!-- Bannière du profil -->
        <div class="profile-banner"></div>
        
        <div class="profile-info d-flex align-items-center justify-content-between">
            
            <!-- Avatar et statut -->
            <div class="profile-avatar-wrapper position-relative">
                <?php 
                // Déterminer la source de l'image, fallback si vide ou fichier inexistant
                if (!empty($employe['photo'])) {
                    // Use the safe image proxy when photo path is in uploads
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
                    class="profile-avatar"
                    onerror="this.src='assets/img/profil.jpg'"> <!-- fallback si image non trouvée -->
                
                <?php 
                // Vérifier si la clé 'status' existe pour éviter le warning
                $statusClass = isset($employe['status']) && $employe['status'] === 'active' 
                                ? 'status-online' 
                                : 'status-offline';
                ?>
                <span class="avatar-status <?= $statusClass ?>"></span>
                
                <?php 
                // Si pas d'image, afficher les initiales
                if (empty($employe['photo'])): ?>
                    <div class="avatar-initials <?= $departementConfig['bg'] ?? 'bg-secondary' ?>">
                        <?= $initiale ?? 'X' ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Informations du profil -->
            <div class="profile-details ms-3">
                <h1 class="profile-name"><?= htmlspecialchars(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? '')) ?></h1>
                
                <p class="profile-title">
                    <i class="fas fa-briefcase me-2"></i>
                    <?= htmlspecialchars($employe['poste'] ?? 'Non défini') ?>
                </p>
                
                <div class="profile-department <?= $departementConfig['text'] ?? 'text-muted' ?>">
                    <i class="fas fa-building me-2"></i>
                    <?= htmlspecialchars($departementLabel ?? 'Département inconnu') ?>
                </div>
                
                <div class="profile-meta mt-2">
                    <span class="meta-item">
                        <i class="fas fa-hashtag me-1"></i>
                        Matricule: XPERT-<?= strtoupper(substr($employe['departement'] ?? 'XXX', 0, 3)) ?><?= $employe['id'] ?? '0' ?>
                    </span>
                    <span class="meta-item ms-3">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Ancienneté: <?= $anciennete ?? 'N/A' ?>
                    </span>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="profile-actions ms-auto d-flex gap-2">
                <a href="<?= $isAdmin ? 'admin_dashboard_unifie.php' : 'employe_dashboard.php' ?>" 
                class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Retour
                </a>
                
                <a href="mailto:<?= htmlspecialchars($employe['email'] ?? '') ?>" 
                class="btn btn-primary">
                    <i class="fas fa-envelope me-1"></i> Contacter
                </a>
                
                <?php if (!empty($isAdmin)): ?>
                    <!-- Actions moved to the central action card below -->
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="profile-content">
        <div class="container-fluid">
            <!-- Messages d'alerte -->
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
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
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
                    <div class="flex-grow-1">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section Informations -->
            <div class="row g-4">
                <!-- Colonne gauche : Informations personnelles -->
                <div class="col-lg-4">
                    <div class="card profile-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-user-circle me-2 text-primary"></i>
                                Informations personnelles
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-envelope text-primary"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Email professionnel</div>
                                        <a href="mailto:<?= htmlspecialchars($employe['email']) ?>" class="info-value">
                                            <?= htmlspecialchars($employe['email']) ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-phone text-primary"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Téléphone</div>
                                        <a href="tel:<?= htmlspecialchars($employe['telephone']) ?>" class="info-value">
                                            <?= htmlspecialchars($employe['telephone']) ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Adresse</div>
                                        <div class="info-value"><?= htmlspecialchars($employe['adresse']) ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-calendar-plus text-primary"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="info-label">Date d'embauche</div>
                                        <div class="info-value">
                                            <?= date('d/m/Y', strtotime($employe['date_creation'])) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-icon">
                                        <i class="fas fa-id-card text-primary"></i>
                                    </div>
                                    <div class="info-content">
                                        <div class="bg-secondary">
                                            Status: <?php echo isset($stats['status']) ? $stats['status'] : 'N/A'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section Badge -->
                    <div class="card profile-card mt-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-id-card me-2 text-success"></i>
                                Badge d'accès
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($badge_actif && isset($badgeToken['token'])): ?>
                                <div class="badge-section">
                                    <div class="qr-code-container mb-4">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($badgeToken['token']) ?>&format=svg&margin=10&color=2563eb&bgcolor=f8fafc" 
                                             class="qr-code-img" 
                                             alt="QR Code du badge"
                                             data-bs-toggle="modal" 
                                             data-bs-target="#badgeModal">
                                        <div class="qr-overlay">
                                            <i class="fas fa-expand-alt"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="badge-status">
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> ACTIF
                                        </span>
                                    </div>
                                    
                                    <div class="badge-expiry mt-3">
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-clock me-1"></i>
                                            Valide jusqu'au
                                        </p>
                                        <p class="fw-bold">
                                            <?= date('d/m/Y à H:i', strtotime($badgeToken['expires_at'])) ?>
                                        </p>
                                    </div>
                                    
                                    <form method="POST" class="mt-4">
                                        <button type="submit" name="regenerer_badge" class="btn btn-primary w-100">
                                            <i class="fas fa-sync-alt me-2"></i> Régénérer le badge
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="no-badge-section">
                                    <div class="no-badge-icon">
                                        <i class="fas fa-id-card fa-3x text-muted"></i>
                                    </div>
                                    <h5 class="mt-3 mb-2">Badge non disponible</h5>
                                    <p class="text-muted mb-4">Aucun badge actif trouvé</p>
                                    
                                    <form method="POST">
                                        <button type="submit" name="demander_badge" class="btn btn-primary">
                                            <i class="fas fa-plus-circle me-2"></i> Créer un badge
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonne centrale : Statistiques -->
                <div class="col-lg-4">
                    <div class="card profile-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-chart-bar me-2 text-info"></i>
                                Statistiques du mois
                            </h5>
                            <span class="badge bg-light text-dark">
                                <?= date('F Y') ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <div class="stat-icon bg-primary-light">
                                        <i class="fas fa-clock text-primary"></i>
                                    </div>
                                    <div class="stat-content">
                                        <?php $tt_parts = explode(':', $temps_travail); $display_hrs = ($tt_parts[0] ?? '00') . ':' . ($tt_parts[1] ?? '00'); ?>
                                        <div class="stat-number"><?= htmlspecialchars($display_hrs) ?>h</div>
                                        <div class="stat-label">Heures travaillées</div>
                                        <div class="stat-trend text-success">
                                            <i class="fas fa-arrow-up me-1"></i> 12% vs mois dernier
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon bg-success-light">
                                        <i class="fas fa-calendar-check text-success"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?= $stats['total_pointages'] ?? 0 ?></div>
                                        <div class="stat-label">Pointages total</div>
                                        <div class="stat-trend">
                                            <i class="fas fa-history me-1"></i> Depuis <?= $stats['premiere_arrivee'] ?? 'N/A' ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon bg-warning-light">
                                        <i class="fas fa-sign-in-alt text-warning"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?= $stats['total_arrivees'] ?? 0 ?></div>
                                        <div class="stat-label">Arrivées</div>
                                        <div class="stat-trend text-info">
                                            Moyenne: 8h30
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon bg-danger-light">
                                        <i class="fas fa-sign-out-alt text-danger"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?= $stats['total_departs'] ?? 0 ?></div>
                                        <div class="stat-label">Départs</div>
                                        <div class="stat-trend text-info">
                                            Moyenne: 17h45
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon bg-secondary-light">
                                        <i class="fas fa-exclamation-triangle text-secondary"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number"><?= $stats['total_retards'] ?? 0 ?></div>
                                        <div class="stat-label">Retards</div>
                                        <div class="stat-trend text-success">
                                            <i class="fas fa-arrow-down me-1"></i> 25% vs mois dernier
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="historique_pointages.php?id=<?= $employe['id'] ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-chart-line me-2"></i> Voir les statistiques détaillées
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne droite : Activités récentes -->
                <div class="col-lg-4">
                    <div class="card profile-card h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title">
                                    <i class="fas fa-history me-2 text-secondary"></i>
                                    Activité récente
                                </h5>
                                <span class="badge bg-primary">
                                    Aujourd'hui
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($derniers_pointages)): ?>
                                <div class="activity-timeline">
                                    <?php foreach ($derniers_pointages as $index => $pointage): 
                                        $isToday = date('Y-m-d', strtotime($pointage['date_heure'])) === date('Y-m-d');
                                        $isArrival = $pointage['type'] === 'arrivee';
                                    ?>
                                        <div class="activity-item <?= $index === 0 ? 'activity-current' : '' ?>">
                                            <div class="activity-indicator">
                                                <div class="activity-dot <?= $isArrival ? 'dot-success' : 'dot-primary' ?>"></div>
                                                <?php if ($index < count($derniers_pointages) - 1): ?>
                                                    <div class="activity-line"></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-header">
                                                    <div class="activity-title">
                                                        <?= $isArrival ? 'Arrivée au travail' : 'Départ du travail' ?>
                                                        <?php if ($isToday): ?>
                                                            <span class="badge bg-primary-subtle text-primary ms-2">Aujourd'hui</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="activity-time">
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
                                                <div class="activity-meta">
                                                    <span class="meta-item">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?= date('d/m/Y', strtotime($pointage['date_heure'])) ?>
                                                    </span>
                                                    <span class="meta-item">
                                                        <i class="fas fa-<?= $isArrival ? 'sun' : 'moon' ?> me-1"></i>
                                                        <?= $isArrival ? 'Matin' : 'Soir' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="historique_pointages.php?id=<?= $employe['id'] ?>" class="btn btn-link text-primary">
                                        <i class="fas fa-list me-2"></i> Voir tout l'historique
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-clock fa-3x text-muted"></i>
                                    </div>
                                    <h5 class="empty-title mt-3">Aucune activité</h5>
                                    <p class="empty-text text-muted">
                                        Aucun pointage enregistré pour le moment
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action card (centrée entre Badge et Stats) -->
            <div class="row mt-3">
                <div class="col-lg-4 offset-lg-4 col-md-8 offset-md-2">
                    <div class="card profile-card action-card text-center shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">Actions administratives</h6>
                            <p class="text-muted small mb-3">Effectuez rapidement des actions sur le compte de cet employé</p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <a href="modifier_employe.php?id=<?= $employe['id'] ?>" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-edit me-1"></i> Modifier
                                </a>

                                <button class="btn btn-secondary btn-lg" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                    <i class="fas fa-key me-1"></i> Réinitialiser
                                </button>

                                <?php if (($employe['statut'] ?? '') === 'actif'): ?>
                                    <button id="btn-deactivate-employee" class="btn btn-warning btn-lg">
                                        <i class="fas fa-user-slash me-1"></i> Désactiver
                                    </button>
                                <?php else: ?>
                                    <button id="btn-activate-employee" class="btn btn-success btn-lg">
                                        <i class="fas fa-user-check me-1"></i> Activer
                                    </button>
                                <?php endif; ?>

                                <button id="btn-delete-inline" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash me-1"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Performance -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card profile-card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-trophy me-2 text-warning"></i>
                                Performance et indicateurs
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="metric-card">
                                        <div class="metric-value">98%</div>
                                        <div class="metric-label">Présence</div>
                                        <div class="metric-progress">
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: 98%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="metric-card">
                                        <div class="metric-value">2.3%</div>
                                        <div class="metric-label">Taux de retard</div>
                                        <div class="metric-progress">
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: 2.3%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="metric-card">
                                        <div class="metric-value">42.5h</div>
                                        <div class="metric-label">Moyenne hebdo</div>
                                        <div class="metric-progress">
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-info" style="width: 85%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="metric-card">
                                        <div class="metric-value">4.8/5</div>
                                        <div class="metric-label">Satisfaction</div>
                                        <div class="metric-progress">
                                            <div class="progress" style="height: 6px;">
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
</script>

<!-- Modals -->
<!-- Modal Badge -->
<div class="modal fade" id="badgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-id-card me-2"></i>
                    Badge d'accès - <?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <?php if ($badge_actif && isset($badgeToken['token'])): ?>
                    <div class="badge-modal-content">
                        <div class="badge-qr mb-4">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode($badgeToken['token']) ?>&format=svg&margin=20&color=2563eb&bgcolor=ffff" 
                                 class="img-fluid" 
                                 alt="QR Code">
                        </div>
                        
                        <div class="badge-info">
                            <h4 class="employee-name mb-2"><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></h4>
                            <p class="employee-position text-muted">
                                <i class="fas fa-briefcase me-1"></i>
                                <?= htmlspecialchars($employe['poste']) ?>
                            </p>
                            <p class="employee-department <?= $departementConfig['text'] ?>">
                                <i class="fas fa-building me-1"></i>
                                <?= htmlspecialchars($departementLabel) ?>
                            </p>
                        </div>
                        
                        <div class="badge-details mt-4">
                            <div class="detail-item">
                                <i class="fas fa-hashtag me-2 text-muted"></i>
                                <span>Matricule: XPERT-<?= strtoupper(substr($employe['departement'], 0, 3)) ?><?= $employe['id'] ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock me-2 text-muted"></i>
                                <span>Valide jusqu'au <?= date('d/m/Y à H:i', strtotime($badgeToken['expires_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Aucun badge actif disponible
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
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
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>
                        Réinitialiser le mot de passe
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Le nouveau mot de passe sera envoyé par email à l'employé.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="newPassword" 
                                   placeholder="Saisir le nouveau mot de passe" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="newPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 caractères avec majuscule, minuscule et chiffre</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmer le mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" 
                                   placeholder="Confirmer le mot de passe" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirmPassword">
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
                    <button type="submit" class="btn btn-primary">
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
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmer la suppression
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
                            <h5 class="alert-heading">Attention !</h5>
                            <p class="mb-0">
                                Vous êtes sur le point de supprimer l'employé <strong><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></strong>.
                                Cette action est irréversible et supprimera toutes les données associées.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        Je confirme vouloir supprimer cet employé
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="deleteEmployeeBtn" disabled>
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

<style>
/* Variables CSS */
:root {
    --primary-color: #0672e4;
    --primary-light: #dbeafe;
    --secondary-color: #ffff;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --light-color: #f8fafc;
    --dark-color: #2c3e50;
    --border-radius: 12px;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 30px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
}

/* Structure principale */
.profile-container {
    min-height: 100vh;
    /* Use central page background variable so theme controls canvas appearance */
    background: var(--page-bg-gradient, var(--page-bg)) !important;
}

.profile-header {
    position: relative;
    margin-bottom: 30px;
}

.profile-banner {
    height: 200px;
    background: linear-gradient(135deg, var(--secondary-color) 0%, #ffff 100%);
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    
}

.profile-info {
    position: relative;
    max-width: 1300px;
    margin: -190px auto 0;
    padding: 0 20px;
    display: flex;
    align-items: flex-end;
    gap: 30px;
}

/* Avatar */
.profile-avatar-wrapper {
    position: relative;
}

.profile-avatar {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    border: 6px solid white;
    box-shadow: var(--shadow-lg);
    object-fit: cover;
}

.avatar-initials {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    border: 6px solid white;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 700;
    color: white;
}

.avatar-status {
    position: absolute;
    bottom: 20px;
    right: 20px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 3px solid white;
}

.status-online {
    background-color: var(--success-color);
}

.status-offline {
    background-color: var(--secondary-color);
}

/* Détails du profil */
.profile-details {
    flex: 1;
    padding-bottom: 20px;
}

.profile-name {
    font-size: 36px;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 8px;
}

.profile-title {
    font-size: 18px;
    color: var(--secondary-color);
    margin-bottom: 12px;
}

.profile-department {
    display: inline-block;
    padding: 6px 16px;
    background: var(--primary-light);
    border-radius: 20px;
    font-weight: 600;
    margin-bottom: 20px;
}

.profile-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    color: var(--secondary-color);
    font-size: 14px;
}

/* Actions */
.profile-actions {
    padding-bottom: 20px;
    display: flex;
    gap: 12px;
}

/* Cartes */
.profile-card {
    background: white;
    border-radius: var(--border-radius);
    border: none;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    overflow: hidden;
}

.profile-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.profile-card .card-header {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 24px;
}

.profile-card .card-body {
    padding: 24px;
}

/* Grid d'informations */
.info-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.card-title .h4 { 
    color: #ffff;
    }

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 12px;
    text-transform: uppercase;
    color: var(--secondary-color);
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 16px;
    color: var(--dark-color);
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.info-value:hover {
    color: var(--primary-color);
}

/* Section Badge */
.badge-section {
    padding: 10px;
}

.qr-code-container {
    position: relative;
    display: inline-block;
}

.qr-code-img {
    width: 200px;
    height: 200px;
    border-radius: 12px;
    cursor: pointer;
    transition: var(--transition);
}

.qr-code-img:hover {
    transform: scale(1.05);
}

.qr-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(37, 99, 235, 0.9);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: var(--transition);
}

.qr-code-container:hover .qr-overlay {
    opacity: 1;
}

.qr-overlay i {
    color: white;
    font-size: 32px;
}

.no-badge-section {
    padding: 40px 20px;
}

.no-badge-icon {
    color: #cbd5e1;
}

/* Statistiques */
.stats-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--light-color);
    border-radius: 12px;
    transition: var(--transition);
}

.stat-card:hover {
    background: white;
    box-shadow: var(--shadow-sm);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.bg-primary-light { background-color: #dbeafe; }
.bg-success-light { background-color: #d1fae5; }
.bg-warning-light { background-color: #fef3c7; }
.bg-danger-light { background-color: #fee2e2; }
.bg-secondary-light { background-color: #e2e8f0; }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: var(--secondary-color);
    margin-bottom: 4px;
}

.stat-trend {
    font-size: 12px;
    font-weight: 600;
}

/* Timeline d'activités */
.activity-timeline {
    position: relative;
    padding-left: 24px;
}

.activity-item {
    position: relative;
    padding-bottom: 24px;
}

.activity-current .activity-dot {
    transform: scale(1.2);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
}

.activity-indicator {
    position: absolute;
    left: -24px;
    top: 0;
}

.activity-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--primary-color);
    border: 3px solid white;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.dot-success { background: var(--success-color); }
.dot-primary { background: var(--primary-color); }

.activity-line {
    position: absolute;
    left: 7px;
    top: 16px;
    width: 2px;
    height: calc(100% - 16px);
    background: #e2e8f0;
}

.activity-content {
    background: var(--light-color);
    border-radius: 12px;
    padding: 16px;
    transition: var(--transition);
}

.activity-item:hover .activity-content {
    background: white;
    box-shadow: var(--shadow-sm);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.activity-title {
    font-weight: 600;
    color: var(--dark-color);
    flex: 1;
}

.activity-time {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 14px;
}

.activity-meta {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: var(--secondary-color);
}

.meta-item {
    display: flex;
    align-items: center;
}

/* Métriques de performance */
.metric-card {
    text-align: center;
    padding: 20px;
    background: var(--light-color);
    border-radius: 12px;
    transition: var(--transition);
}

.metric-card:hover {
    transform: translateY(-5px);
    background: white;
    box-shadow: var(--shadow-md);
}

.metric-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 8px;
}

.metric-label {
    font-size: 14px;
    color: var(--secondary-color);
    margin-bottom: 12px;
}

.metric-progress {
    height: 6px;
}

/* Timer badge */
.badge-timer {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 8px;
}

.badge-timer-safe {
    background: #d1fae5;
    color: var(--success-color);
}

.badge-timer-warning {
    background: #fef3c7;
    color: var(--warning-color);
}

.badge-timer-danger {
    background: #fee2e2;
    color: var(--danger-color);
}

.badge-timer-expired {
    background: var(--danger-color);
    color: white;
}

/* États vides */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    color: #cbd5e1;
}

.empty-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 8px;
}

.empty-text {
    font-size: 14px;
    color: var(--secondary-color);
}

/* Animations */
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
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
}

/* Responsive */
@media (max-width: 992px) {
    .profile-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-top: -120px;
    }
    
    .profile-details {
        text-align: center;
        padding-bottom: 0;
    }
    
    .profile-meta {
        justify-content: center;
    }
    
    .profile-actions {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .profile-avatar,
    .avatar-initials {
        width: 140px;
        height: 140px;
    }
    
    .profile-name {
        font-size: 28px;
    }
}

@media (max-width: 768px) {
    .profile-banner {
        height: 160px;
    }
    
    .profile-name {
        font-size: 24px;
    }
    
    .profile-title {
        font-size: 16px;
    }
    
    .profile-meta {
        flex-direction: column;
        gap: 8px;
    }
    
    .stats-container {
        gap: 12px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
    }
    
    .stat-number {
        font-size: 20px;
    }
}

@media (max-width: 576px) {
    .profile-banner {
        height: 140px;
    }
    
    .profile-avatar,
    .avatar-initials {
        width: 120px;
        height: 120px;
    }
    
    .qr-code-img {
        width: 160px;
        height: 160px;
    }
    
    .metric-value {
        font-size: 24px;
    }
}

/* Accessibilité */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Mode sombre */
@media (prefers-color-scheme: dark) {
    :root {
        --light-color: #2c3e50;
        --dark-color: #f1f5f9;
        --secondary-color: #94a3b8;
    }
    
    .profile-container {
        background: #7f8c8d
    }
    
    .profile-card {
        background: #ffff;
        border: 1px solid #475569;
    }
    
    .profile-card .card-header {
        background: #ffff;
        border-bottom-color: #475569;
    }
    
    .info-value {
        color: #e2e8f0;
    }
    
    .stat-card,
    .activity-content,
    .metric-card {
        background: #475569;
    }
}
</style>

<?php
$additionalJS = [];
include 'partials/footer.php';
?>