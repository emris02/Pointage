<?php
/**
 * Tableau de bord employé
 * Système de Pointage Professionnel v2.0
 */

 //
require_once '../../config/database.php';
require_once '../../src/models/Employee.php';
require_once '../../src/models/Pointage.php';
require_once '../../src/Core/Security/TokenManager.php';

use PointagePro\Models\Employee;
use PointagePro\Models\Pointage;
use PointagePro\Core\Security\TokenManager;

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

try {
    // Initialisation
    $db = DatabaseConfig::getInstance()->getConnection();
    $employeeModel = new Employee($db);
    $pointageModel = new Pointage($db);
    $tokenManager = new TokenManager(
        DatabaseConfig::SECRET_KEY,
        DatabaseConfig::JWT_SECRET
    );
    
    // Récupération des données de l'employé
    $employee = $employeeModel->findById($_SESSION['user_id']);
    if (!$employee) {
        throw new Exception("Employé non trouvé");
    }
    
    // Génération du token de badge actuel
    $badgeToken = $tokenManager->generateBadgeToken($employee['id']);
    
    // Statistiques de l'employé
    $stats = $employeeModel->getStatistics($employee['id'], '30days');
    
    // Historique des pointages récents
    $recentClockings = $pointageModel->getHistory($employee['id'], [
        'date_from' => date('Y-m-d', strtotime('-7 days'))
    ]);
    
    // Pointages du jour
    $todayClockings = $pointageModel->getHistory($employee['id'], [
        'date_from' => date('Y-m-d'),
        'date_to' => date('Y-m-d')
    ]);
    
} catch (Exception $e) {
    error_log("Erreur dashboard employé: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?= htmlspecialchars($employee['first_name'] ?? '') ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <!-- PWA -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3498db">
    
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-clock me-2"></i>
                PointagePro
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-home me-1"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="scanner.php">
                            <i class="fas fa-qrcode me-1"></i>Scanner
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="fas fa-history me-1"></i>Historique
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($employee['first_name'] ?? '') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i>Profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>
            
            <!-- En-tête avec informations employé -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-1">
                                        Bonjour, <?= htmlspecialchars($employee['first_name']) ?> !
                                    </h2>
                                    <p class="mb-0">
                                        <i class="fas fa-briefcase me-2"></i>
                                        <?= htmlspecialchars($employee['position']) ?> - 
                                        <?= htmlspecialchars($employee['department_name'] ?? $employee['department']) ?>
                                    </p>
                                    <small class="opacity-75">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('l j F Y') ?>
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="h3 mb-0" id="currentTime"></div>
                                    <small class="opacity-75">Heure actuelle</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="text-primary mb-2">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                            <h4 class="mb-1"><?= $stats['working_days'] ?? 0 ?></h4>
                            <small class="text-muted">Jours travaillés (30j)</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <h4 class="mb-1"><?= $stats['avg_daily_hours'] ?? '00:00:00' ?></h4>
                            <small class="text-muted">Moyenne quotidienne</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="text-warning mb-2">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <h4 class="mb-1"><?= $stats['late_arrivals'] ?? 0 ?></h4>
                            <small class="text-muted">Retards (30j)</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="text-info mb-2">
                                <i class="fas fa-plus-circle fa-2x"></i>
                            </div>
                            <h4 class="mb-1"><?= $stats['overtime_hours'] ?? '00:00:00' ?></h4>
                            <small class="text-muted">Heures sup. (30j)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Badge QR Code -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-qrcode me-2"></i>
                                Mon Badge
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <div id="qrcode" class="mb-3"></div>
                            
                            <div class="badge bg-success mb-2">
                                <i class="fas fa-check-circle me-1"></i>
                                Badge Actif
                            </div>
                            
                            <p class="small text-muted mb-3">
                                <i class="fas fa-clock me-1"></i>
                                Expire le <?= date('d/m/Y à H:i', strtotime($badgeToken['expires_at'])) ?>
                            </p>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="openScanner()">
                                    <i class="fas fa-camera me-2"></i>
                                    Scanner maintenant
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="regenerateBadge()">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    Régénérer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pointages du jour -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-day me-2"></i>
                                Aujourd'hui
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($todayClockings)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                                    <p>Aucun pointage aujourd'hui</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($todayClockings as $clocking): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-<?= getClockingColor($clocking['type']) ?>">
                                                <i class="fas fa-<?= getClockingIcon($clocking['type']) ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1"><?= getClockingLabel($clocking['type']) ?></h6>
                                                <p class="mb-0 text-muted">
                                                    <?= date('H:i', strtotime($clocking['timestamp'])) ?>
                                                    <?php if ($clocking['is_late']): ?>
                                                        <span class="badge bg-warning ms-2">
                                                            Retard: <?= $clocking['late_minutes'] ?>min
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Historique récent -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Historique récent
                            </h5>
                            <a href="history.php" class="btn btn-sm btn-outline-primary">
                                Voir tout
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentClockings)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-3"></i>
                                    <p>Aucun historique</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($recentClockings, 0, 5) as $clocking): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= getClockingLabel($clocking['type']) ?></h6>
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y H:i', strtotime($clocking['timestamp'])) ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?= getClockingColor($clocking['type']) ?>">
                                                    <i class="fas fa-<?= getClockingIcon($clocking['type']) ?>"></i>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const BADGE_TOKEN = '<?= $badgeToken['token'] ?? '' ?>';
        const API_BASE_URL = '../api';
        
        // Génération du QR Code
        document.addEventListener('DOMContentLoaded', function() {
            if (BADGE_TOKEN) {
                QRCode.toCanvas(document.getElementById('qrcode'), BADGE_TOKEN, {
                    width: 200,
                    height: 200,
                    margin: 2,
                    color: {
                        dark: '#000000',
                        light: '#FFFFFF'
                    }
                }, function(error) {
                    if (error) {
                        console.error('Erreur génération QR Code:', error);
                        document.getElementById('qrcode').innerHTML = 
                            '<div class="alert alert-warning">Erreur génération QR Code</div>';
                    }
                });
            }
            
            // Mise à jour de l'heure
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
        });
        
        // Mise à jour de l'heure actuelle
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Ouvrir le scanner
        function openScanner() {
            window.location.href = 'scanner.php';
        }
        
        // Régénérer le badge
        async function regenerateBadge() {
            if (!confirm('Voulez-vous régénérer votre badge ? L\'ancien badge sera invalidé.')) {
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE_URL}/regenerate-badge.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        employe_id: <?= $employee['id'] ?>
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Badge régénéré avec succès', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.error || 'Erreur lors de la régénération', 'danger');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion', 'danger');
            }
        }
        
        // Afficher une alerte
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.styles.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }
        
        // Service Worker pour PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../sw.js')
                .then(registration => console.log('SW registered'))
                .catch(error => console.log('SW registration failed'));
        }
    </script>
</body>
</html>

<?php
// Fonctions utilitaires
function getClockingColor($type) {
    return match($type) {
        'arrival' => 'success',
        'departure' => 'primary',
        'break_start' => 'warning',
        'break_end' => 'info',
        default => 'secondary'
    };
}

function getClockingIcon($type) {
    return match($type) {
        'arrival' => 'sign-in-alt',
        'departure' => 'sign-out-alt',
        'break_start' => 'pause',
        'break_end' => 'play',
        default => 'clock'
    };
}

function getClockingLabel($type) {
    return match($type) {
        'arrival' => 'Arrivée',
        'departure' => 'Départ',
        'break_start' => 'Début pause',
        'break_end' => 'Fin pause',
        default => 'Pointage'
    };
}
?>