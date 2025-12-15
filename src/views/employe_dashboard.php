<?php
/**
 * Vue du dashboard employé
 */

require_once '../src/config/bootstrap.php';

// Vérification de l'authentification
$authController = new AuthController($pdo);
if (!$authController->isLoggedIn() || $authController->isAdmin()) {
    header("Location: login.php");
    exit();
}

$isAdmin = false;
$pageTitle = 'Mon Dashboard - Xpert Pro';
$pageHeader = 'Mon Dashboard';
$pageDescription = 'Votre espace personnel de pointage';

// Récupération des données
$employeId = $_SESSION['employe_id'];
$pointageController = new PointageController($pdo);
$employeController = new EmployeController($pdo);

$employeStats = $employeController->getStats($employeId);
$employe = $employeController->show($employeId) ?? ($employeStats['employe'] ?? []);
$departement = $employe['departement'] ?? 'N/A';
$todayPointages = $pointageController->getEmployeHistory($employeId, date('Y-m-d'), date('Y-m-d'));
$workHours = $pointageController->getEmployeHistory($employeId, date('Y-m-01'), date('Y-m-d'));

$additionalCSS = ['public/assets/css/employe.css'];
?>

<?php include 'partials/header.php'; ?>

<div class="row">
    <!-- Informations personnelles -->
<div class="profile-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-user me-2"></i>Mon Profil</h4>
                    <div class="position-relative">
                        <button class="btn btn-light position-relative" 
                                id="notificationDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false">
                            <i class="fas fa-bell fa-lg"></i>
                            <?php if (count($notifications) > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= count($notifications) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications récentes</h6></li>
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="detail_pointage.php?id=<?= $notification['id'] ?>">
                                            <div class="notification-icon bg-primary">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <div class="ms-2">
                                                <div class="notification-title">
                                                    <?= htmlspecialchars($notification['titre'] ?? 'Notification') ?>
                                                </div>
                                                <div class="notification-content small">
                                                    <?= htmlspecialchars($notification['contenu'] ?? 'Contenu indisponible') ?>
                                                </div>
                                                <div class="notification-time text-muted small">
                                                    <?= date('d/m/Y H:i', strtotime($notification['date'] ?? 'now')) ?>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li class="text-center py-2">
                                    <a href="notifications.php" class="btn btn-link">Voir toutes les notifications</a>
                                </li>
                            <?php else: ?>
                                <li class="text-center py-3">
                                    <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                                    <p class="mb-0 text-muted">Aucune notification</p>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card-content">
                <div class="row">
                    <div class="col-md-8">
                        <div class="profile-section">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-4">
                                    <?php
                                    // Afficher la photo si disponible, sinon afficher les initiales
                                    $photoSrc = '';
                                    if (!empty($employe['photo'])) {
                                        $photoSrc = htmlspecialchars($employe['photo']);
                                        if (strpos($employe['photo'], 'uploads/') !== false) {
                                            $photoSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($employe['photo']));
                                        }
                                    }
                                    ?>
                                    <?php if ($photoSrc): ?>
                                        <img src="<?= $photoSrc ?>" class="profile-avatar me-4" alt="Photo de profil">
                                    <?php else: ?>
                                        <?php $initials = strtoupper(substr($employe['prenom'] ?? '', 0, 1) . substr($employe['nom'] ?? '', 0, 1)); ?>
                                        <div class="profile-avatar rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-4" style="width:72px;height:72px;font-weight:700;font-size:1.2rem;">
                                            <?= $initials ?: 'NA' ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <h3><?= htmlspecialchars($employe['prenom']) ?> <?= htmlspecialchars($employe['nom']) ?></h3>
                                        <p class="text-accent mb-2"><?= htmlspecialchars($employe['poste']) ?> | <?= htmlspecialchars($departement) ?></p>
                                        <div class="d-flex">
                                            <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editPhotoModal">
                                                <i class="fas fa-camera me-1"></i> Changer photo
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPasswordModal">
                                                <i class="fas fa-lock me-1"></i> Modifier mot de passe
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="profile-details-vertical">
                                    <div class="detail-item">
                                        <div class="detail-label">Matricule</div>
                                        <div class="detail-value">XPERT-<?= strtoupper(substr($employe['departement'], 0, 3)) ?><?= $employe['id'] ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Email</div>
                                        <div class="detail-value"><?= htmlspecialchars($employe['email']) ?></div>
                                    </div>
                                    
<!-- 📱 Téléphone -->
                <div class="detail-item">
                        <div class="detail-label">Téléphone</div>
                        <div class="detail-value" id="phoneDisplay"><?= htmlspecialchars($employe['telephone']) ?></div>
                        <input type="text" class="form-control d-none" id="phoneInput" value="<?= htmlspecialchars($employe['telephone']) ?>">    
                        <button class="btn btn-sm btn-outline-primary edit-inline"><i class="fas fa-edit"></i></button>

                        <button class="btn btn-sm btn-success d-none" id="savePhoneBtn" onclick="saveField('telephone', 'phone')">
                        <i class="fas fa-check"></i>
                        </button>
                </div>

<!-- 🏠 Adresse -->
                <div class="detail-item">
                        <div class="detail-label">Adresse</div>
                        <div class="detail-value" id="addressDisplay"><?= htmlspecialchars($employe['adresse']) ?></div>
                        <textarea class="form-control d-none" id="addressInput"><?= htmlspecialchars($employe['adresse']) ?></textarea>
                        <button class="btn btn-sm btn-outline-primary edit-inline"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-success d-none" id="saveAddressBtn" onclick="saveField('adresse', 'address')">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
                                  
                                    <div class="detail-item">
                                        <div class="detail-label">Embauché le</div>
                                        <div class="detail-value"><?= date('d/m/Y', strtotime($employe['date_creation'])) ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Dernier pointage</div>
                                        <div class="detail-value"><?= !empty($derniers_pointages) ? $derniers_pointages[0]['heure'] . ' (' . ucfirst($derniers_pointages[0]['type']) . ')' : 'Aucun' ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Département</div>
                                        <div class="detail-value"><?= htmlspecialchars($departement) ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-user-edit me-1"></i> Modifier mon profil
                                    </button>

                                    <button class="btn btn-outline-info">
                                        <i class="fas fa-question-circle me-1"></i> Demander de l'aide
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="badge-section">
                            <div class="badge-header">
                                <h5><i class="fas fa-id-card me-2"></i>Badge d'accès - <span id="badge-type-label"><?= $badge_type ?></span></h5>
                                <div class="employee-id" id="badge-employee-id">XPERT-<?= strtoupper(substr($employe['departement'], 0, 3)) ?><?= $employe['id'] ?></div>
                            </div>
                            
                <?php if ($badge_actif): ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($employe['token'] ?? '') ?>" 
                             class="badge-qr mb-1" 
                             alt="Badge d'accès"
                             data-bs-toggle="modal" 
                             data-bs-target="#badgeModal">        
                        <div class="badge-label small fw-bold">Badge actif</div>
                        <div class="badge-expiry <?= (strtotime($employe['expires_at']) - time()) < 3600 ? 'badge-expiry-warning' : '' ?>">
                            Valide jusqu'au <?= date('d/m/Y à H:i', strtotime($employe['expires_at'])) ?>
                        </div>
                        
                        <div id="badge-timer" class="small fw-bold mt-1"></div>
                
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#badgeModal">
                                <i class="fas fa-expand me-1"></i> Voir en grand
                            </button>
                            <button class="btn btn-sm btn-outline-success flex-grow-1">
                                <i class="fas fa-print me-1"></i> Imprimer
                            </button>
                        </div>
                                
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success mt-2">
        <?= $_SESSION['success_message'] ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php else: ?>
    <div class="no-badge">
        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
        <p class="mb-3">Aucun badge actif</p>
        <div id="badge-success-message"></div>
        <div class="alert alert-info mt-2" id="badge-info-message">
            <strong>À propos du badge :</strong><br>
            <ul class="mb-0 ps-3">
                <li>Un badge d'accès est personnel et unique : il permet de pointer vos arrivées et départs.</li>
                <li>La création du badge est immédiate : cliquez sur "Demander un badge" pour générer votre premier badge.</li>
                <li>Un badge est valide pour une durée limitée (généralement 24h ou selon la politique de l'entreprise).</li>
                <li>Après chaque pointage de départ, un nouveau badge est automatiquement généré pour la prochaine session.</li>
                <li>En cas d'expiration ou de perte, vous pouvez régénérer un badge à tout moment.</li>
                <li>Présentez le QR code de votre badge à l'entrée/sortie pour valider votre présence.</li>
            </ul>
        </div>
        <button id="demanderBadgeBtn" type="button" class="btn btn-primary w-100">
            <i class="fas fa-sync-alt me-2"></i>Demander un badge
        </button>
        <div id="badge-loader" class="text-center mt-2" style="display:none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <div>Génération du badge...</div>
        </div>
    </div>
<?php endif; ?>
                        <div class="additional-info mt-4">
                            <h5><i class="fas fa-info-circle me-2"></i> Informations complémentaires</h5>
                            <ul class="info-list">
                                <li>
                                    <i class="fas fa-medal text-primary"></i>
                                    <span>Ancienneté: <?= date('Y') - date('Y', strtotime($employe['date_creation'])) ?> ans</span>
                                </li>
                                <li>
                                    <i class="fas fa-calendar-check text-success"></i>
                                    <span>Congés restants: 
                                        <?php
                                        $stmt = $pdo->prepare("SELECT jours_restants FROM conges WHERE employe_id = ?");
                                        $stmt->execute([$employe_id]);
                                        $conges = $stmt->fetch();
                                        echo $conges ? htmlspecialchars($conges['jours_restants']) : '12';
                                        ?> jours
                                    </span>
                                </li>
                                <li>
                                    <i class="fas fa-file-contract text-info"></i>
                                    <span>Contrat: <?= htmlspecialchars($employe['type_contrat'] ?? 'CDI') ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-user-tie text-warning"></i>
                                    <span>Manager: 
                                        <?php
                                        $stmt = $pdo->prepare("SELECT CONCAT(prenom, ' ', nom) AS manager 
                                                              FROM admins 
                                                              WHERE poste = ? AND role = 'manager' 
                                                              LIMIT 1");
                                        $stmt->execute([$employe['departement']]);
                                        $manager = $stmt->fetch();
                                        echo $manager ? htmlspecialchars($manager['manager']) : 'Non assigné';
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                <h5><?= htmlspecialchars($employeStats['employe']['prenom'] . ' ' . $employeStats['employe']['nom']) ?></h5>
                <p class="text-muted"><?= htmlspecialchars($employeStats['employe']['poste']) ?></p>
                <a href="profil.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i> Modifier mon profil
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistiques du jour -->
        
        <div class="stats-grid">
            <div class="stat-card">
                <h5><i class="fas fa-clock me-2"></i> Présence ce mois</h5>
                <h2><?= $stats['jours_presents'] ?></h2>
                <small>Jours de présence</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-calendar-check me-2"></i> Pointages</h5>
                <h2><?= count($pointages) ?></h2>
                <small>Enregistrements</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> Retards</h5>
                <h2><?= $stats['jours_retards'] ?></h2>
                <small>Jours avec retard</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-user-times me-2"></i> Absences</h5>
                <h2><?= $stats['jours_absents'] ?></h2>
                <small>Jours sans pointage</small>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="calendar-card" id="calendar-section">
                    <div class="card-header">
                        <h4><i class="fas fa-calendar-alt me-2"></i> Calendrier de présence</h4>
                    </div>
                    <div class="card-content">
                        <div class="calendar-header">
                            <div class="calendar-title">
                                <i class="fas fa-calendar me-2"></i> <?= $mois_annee ?>
                            </div>
                            <div class="calendar-nav">
                                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>#calendar-section" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="<?= $allow_next ? '?month='.$next_month.'&year='.$next_year.'#calendar-section' : '#' ?>" 
                                   class="btn btn-sm btn-outline-primary ms-2 <?= !$allow_next ? 'disabled' : '' ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="days-header">
                            <?php foreach ($jours_semaine as $jour): ?>
                                <div><?= $jour ?></div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="calendar-grid">
                            <?php
                            $firstDayOfMonth = date('N', strtotime("$selected_year-$selected_month-01"));
                            for ($i = 1; $i < $firstDayOfMonth; $i++): ?>
                                <div class="day-box future"></div>
                            <?php endfor;
                            
                            // Réinitialiser les stats pour recalcul avec demi-journées
                            $stats = [
                                'jours_presents' => 0,
                                'jours_retards' => 0,
                                'jours_absents' => 0,
                                'jours_weekend' => 0,
                                'demi_jours' => 0
                            ];
                            
                            for ($day = 1; $day <= $daysInMonth; $day++):
                                $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
                                $class = 'future';
                                $status = '';
                                $isToday = ($day == date('j') && $selected_month == date('m') && $selected_year == date('Y'));
                                $dayOfWeek = date('N', strtotime($date));
                                $isHalfDay = ($dayOfWeek == 6); // Samedi = demi-journée
                                
                                if ($dayOfWeek == 7) { // Dimanche
                                    $class = 'weekend';
                                    $status = 'Week-end';
                                    $stats['jours_weekend']++;
                                } elseif (strtotime($date) <= time()) {
                                    if ($isHalfDay) {
                                        // Traitement spécial pour les samedis (demi-journées)
                                        $isPresent = false;
                                        $isLate = false;
                                        
                                        if (isset($pointages_par_jour[$date]) && !empty($pointages_par_jour[$date]['arrivee'])) {
                                            $heureArriveeTimestamp = strtotime($pointages_par_jour[$date]['arrivee']);
                                            
                                            // Vérifier si l'employé est arrivé avant 14h pour la demi-journée
                                            if ($heureArriveeTimestamp !== false && $heureArriveeTimestamp < strtotime('14:00:00')) {
                                                $isPresent = true;
                                                
                                                // Vérifier s'il est arrivé après 9h pour un retard
                                                if ($heureArriveeTimestamp > strtotime('09:00:00')) {
                                                    $class = 'retard';
                                                    $status = 'Retard';
                                                    $isLate = true;
                                                } else {
                                                    $class = 'presence';
                                                    $status = 'Présent';
                                                }
                                            }
                                        }
                                        
                                        if ($isPresent) {
                                            $stats['jours_presents'] += 0.5;
                                            if ($isLate) {
                                                $stats['jours_retards'] += 0.5;
                                            }
                                        } else {
                                            $class = 'absence';
                                            $status = 'Absent';
                                            $stats['jours_absents'] += 0.5;
                                        }
                                        
                                        $stats['demi_jours']++;
                                    } else {
                                        // Journée complète (lundi-vendredi)
                                        if (isset($pointages_par_jour[$date]) && !empty($pointages_par_jour[$date]['arrivee'])) {
                                            $heureArriveeTimestamp = strtotime($pointages_par_jour[$date]['arrivee']);
                                            if ($heureArriveeTimestamp !== false) {
                                                if ($heureArriveeTimestamp > strtotime('09:00:00')) {
                                                    $class = 'retard';
                                                    $status = 'Retard';
                                                    $stats['jours_retards']++;
                                                } else {
                                                    $class = 'presence';
                                                    $status = 'Présent';
                                                }
                                                $stats['jours_presents']++;
                                            }
                                        } else {
                                            $class = 'absence';
                                            $status = 'Absent';
                                            $stats['jours_absents']++;
                                        }
                                    }
                                }
                                ?>
                                <div class="day-box <?= $class ?> <?= $isToday ? 'today' : '' ?> <?= $isHalfDay ? 'half-day' : '' ?>"
                                     title="<?= htmlspecialchars("Jour $day - $status") ?>">
                                    <span class="day-number"><?= $day ?></span>
                                    <?php if ($status): ?>
                                        <span class="day-status"><?= $status ?></span>
                                    <?php endif; ?>
                                    <?php if ($isHalfDay): ?>
                                        <span class="half-day-indicator">½</span>
                                    <?php endif; ?>
                                </div>
                            <?php endfor;
                            
                            $lastDayOfMonth = date('N', strtotime("$selected_year-$selected_month-$daysInMonth"));
                            if ($lastDayOfMonth < 7) {
                                for ($i = $lastDayOfMonth; $i < 7; $i++) {
                                    echo '<div class="day-box future"></div>';
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                            <span class="status-badge bg-success">
                                <i class="fas fa-circle"></i> Présent <?= $stats['jours_presents'] ?> j
                            </span>
                            <span class="status-badge bg-warning">
                                <i class="fas fa-clock"></i> Retard <?= $stats['jours_retards'] ?> j
                            </span>
                            <span class="status-badge bg-danger">
                                <i class="fas fa-times"></i> Absent <?= $stats['jours_absents'] ?> j
                            </span>
                            <span class="status-badge bg-secondary">
                                <i class="fas fa-umbrella-beach"></i> Week-end <?= $stats['jours_weekend'] ?> j
                            </span>
                            <?php if ($stats['demi_jours'] > 0): ?>
                            <span class="status-badge bg-info">
                                <i class="fas fa-clock-half"></i> Demi-journées: <?= $stats['demi_jours'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
<div class="row mt-4">
    <!-- Pointage rapide -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-qrcode me-2"></i>
                    Pointage rapide
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="qr-scanner-container mb-3">
                    <div id="qr-reader" style="width: 100%; max-width: 300px; margin: 0 auto;"></div>
                </div>
                <p class="text-muted">Scannez votre QR code pour pointer</p>
                <a href="pointage.php" class="btn btn-primary">
                    <i class="fas fa-qrcode me-2"></i> Ouvrir le scanner
                </a>
            </div>
        </div>
    </div>
    
    <!-- Derniers pointages -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>
                    Mes pointages aujourd'hui
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($todayPointages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <p>Aucun pointage aujourd'hui</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($todayPointages as $pointage): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-<?= $pointage['type'] === 'arrivee' ? 'sign-in-alt text-success' : 'sign-out-alt text-warning' ?> me-2"></i>
                                <span class="fw-bold"><?= ucfirst($pointage['type']) ?></span>
                            </div>
                            <span class="text-muted"><?= date('H:i', strtotime($pointage['created_at'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Graphique des heures -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Mes heures ce mois
                </h5>
            </div>
            <div class="card-body">
                <canvas id="hoursChart" width="400" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

$additionalJS = ['public/assets/js/employe.js', 'public/assets/js/qr-scanner.min.js', 'assets/js/calendrier.js'];
$inlineJS .= "\n// expose employeId for calendar scripts\nwindow.employeId = " . json_encode($employeId) . ";\nwindow.isAdmin = false;";
$inlineJS = "
// Initialisation du scanner QR
const html5QrcodeScanner = new Html5QrcodeScanner(
    'qr-reader',
    { fps: 10, qrbox: { width: 250, height: 250 } },
    false
);

html5QrcodeScanner.render(onScanSuccess, onScanFailure);

function onScanSuccess(decodedText, decodedResult) {
    // Traiter le pointage
    processPointage(decodedText);
}

function onScanFailure(error) {
    // Gestion des erreurs de scan
    console.log('Erreur de scan:', error);
}

function processPointage(token) {
    fetch('api/pointage.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({token: token})
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

// Graphique des heures (exemple avec Chart.js)
const ctx = document.getElementById('hoursChart').getContext('2d');
const hoursChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
        datasets: [{
            label: 'Heures travaillées',
            data: [8, 7.5, 8.5, 8, 7, 0, 0],
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
";
include 'partials/footer.php';
?>

<!-- Modal ajout/édition d'événement (employé) -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="eventForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Nouvel événement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="evt-id">
                    <div class="mb-3">
                        <label class="form-label">Titre</label>
                        <input type="text" class="form-control" name="titre" id="evt-title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="evt-type" required>
                            <option value="reunion">Réunion</option>
                            <option value="congé">Congé</option>
                            <option value="formation">Formation</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Début</label>
                            <input type="datetime-local" class="form-control" name="start_date" id="evt-start" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fin</label>
                            <input type="datetime-local" class="form-control" name="end_date" id="evt-end" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="evt-desc" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="employe_id" id="evt-employe-id" value="<?= htmlspecialchars($employeId) ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
