<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scan de Badge - Xpert Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Système de pointage par QR code pour Xpert Pro">

    <!-- Preload des polices si nécessaire -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com/ajax/libs">

    <!-- Bootstrap et icônes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <!-- CSS personnalisé -->
    <link rel="stylesheet" href="assets/css/scan.css?v=1.0">

    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
</head>
<body class="bg-light">
<div class="scan-container container py-4">
    <!-- Header amélioré avec indication de connexion -->
    <div class="text-center mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-start">
                <small class="text-muted" id="current-time">
                    <i class="fas fa-clock me-1"></i>
                    <span id="live-clock"><?= date('H:i') ?></span>
                </small>
            </div>
            <div class="text-end">
                <span id="user-status" class="badge bg-success">
                    <i class="fas fa-wifi me-1"></i> En ligne
                </span>
            </div>
        </div>
        
        <h1 class="fw-bold text-primary mb-2">
            <i class="fas fa-qrcode me-2"></i>Scan de Badge
        </h1>
        <p class="text-muted mb-0">
            Positionnez votre badge QR code devant la caméra
            <span class="d-block small mt-1">
                <i class="fas fa-info-circle me-1"></i>
                Le scanner fonctionne automatiquement
            </span>
        </p>
    </div>

    <!-- Scanner vidéo amélioré -->
    <div class="camera-section mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Scanner</h5>
                    <div class="camera-info">
                        <small class="text-muted" id="camera-name">Caméra arrière</small>
                        <span class="badge bg-info ms-2" id="camera-status">
                            <i class="fas fa-circle fa-xs"></i> Actif
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="video-wrapper mx-auto position-relative">
                    <video id="qr-video" playsinline class="border rounded-3 shadow"></video>
                    <div class="scan-overlay">
                        <div class="scan-frame">
                            <div class="scan-line"></div>
                            <div class="scan-corners">
                                <div class="corner top-left"></div>
                                <div class="corner top-right"></div>
                                <div class="corner bottom-left"></div>
                                <div class="corner bottom-right"></div>
                            </div>
                        </div>
                    </div>
                    <div class="scan-instructions">
                        <i class="fas fa-hand-point-up"></i>
                        <p class="mb-0">Placez le QR code dans le cadre</p>
                    </div>
                </div>
                
                <div id="scan-status" class="scan-status waiting mt-3">
                    <div class="d-flex align-items-center justify-content-center">
                        <div class="spinner-border spinner-border-sm me-2" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <span>Initialisation du scanner...</span>
                    </div>
                </div>
                
                <div id="feedback-message" class="feedback-message mt-2 text-center"></div>

                <!-- Contrôles améliorés -->
                <div class="mt-4 d-flex flex-wrap justify-content-center gap-3" id="scanner-controls">
                    <button id="restart-scan" class="btn btn-warning btn-lg btn-scan" disabled>
                        <i class="fas fa-redo me-2"></i> Redémarrer
                    </button>
                    <button id="switch-camera" class="btn btn-info btn-lg btn-scan" disabled>
                        <i class="fas fa-camera-rotate me-2"></i> Changer
                    </button>
                    <button id="toggle-flash" class="btn btn-secondary btn-lg btn-scan" disabled>
                        <i class="fas fa-lightbulb me-2"></i> Flash
                    </button>
                    <a href="employe_dashboard.php" class="btn btn-outline-primary btn-lg btn-scan">
                        <i class="fas fa-home me-2"></i> Tableau de bord
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques du jour améliorées -->
    <div class="stats-section mb-4">
        <div class="row g-3">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-arrivee shadow-sm h-100">
                    <div class="stat-icon bg-primary bg-opacity-10">
                        <i class="fas fa-sign-in-alt text-primary"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-primary" id="stat-arrivees">0</div>
                        <div class="stat-label">Arrivées</div>
                        <small class="text-muted" id="arrivee-details">Aucune arrivée enregistrée</small>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up text-success" id="arrivee-trend"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-depart shadow-sm h-100">
                    <div class="stat-icon bg-danger bg-opacity-10">
                        <i class="fas fa-sign-out-alt text-danger"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-danger" id="stat-departs">0</div>
                        <div class="stat-label">Départs</div>
                        <small class="text-muted" id="depart-details">Aucun départ enregistré</small>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up text-success" id="depart-trend"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-temps shadow-sm h-100">
                    <div class="stat-icon bg-success bg-opacity-10">
                        <i class="fas fa-clock text-success"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-success" id="stat-temps">00:00</div>
                        <div class="stat-label">Temps moyen</div>
                        <small class="text-muted" id="temps-details">Calcul en cours...</small>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-history text-info"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-retards shadow-sm h-100">
                    <div class="stat-icon bg-warning bg-opacity-10">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value text-warning" id="stat-retards">0</div>
                        <div class="stat-label">Retards</div>
                        <small class="text-muted" id="retard-details">Aucun retard aujourd'hui</small>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-bell text-warning" id="retard-alert"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Historique des pointages amélioré -->
    <div class="history-section">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="mb-2 mb-md-0">
                        <small class="text-muted" id="current-date">
                            <i class="fas fa-calendar me-1"></i>
                            <?= date('d/m/Y') ?>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="max-width: 320px;">
                            <span class="input-group-text">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <select id="range-filter" class="form-select" aria-label="Période">
                                <option value="day">Jour</option>
                                <option value="week" selected>Semaine</option>
                                <option value="month">Mois</option>
                            </select>
                            <input type="date" id="date-filter" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <button id="refresh-history" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <span class="badge bg-primary rounded-pill px-3 py-2" id="history-count">
                            <i class="fas fa-users me-1"></i>
                            <span id="history-count-value">0</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-user me-1"></i> Employé</th>
                                <th><i class="fas fa-clock me-1"></i> Heure</th>
                                <th><i class="fas fa-tag me-1"></i> Type</th>
                                <th><i class="fas fa-map-marker-alt me-1"></i> Localisation</th>
                                <th><i class="fas fa-info-circle me-1"></i> Statut</th>
                            </tr>
                        </thead>
                        <tbody id="pointages-container">
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">Chargement des pointages...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Dernière mise à jour : <span id="last-update">--:--</span>
                    </small>
                    <button id="export-pointages" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i> Exporter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de justification de retard amélioré -->
<div class="modal fade" id="justificationModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-clock me-2"></i>Justification de retard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Vous avez pointé votre arrivée après 9h. Veuillez justifier votre retard.
                </div>
                
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Employé</label>
                                <div class="form-control bg-light" id="justificationEmployeName">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Heure de pointage</label>
                                <div class="form-control bg-light" id="justificationPointageTime">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <div class="form-control bg-light" id="justificationAddress">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Département</label>
                                <div class="form-control bg-light" id="justificationDepartment">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Arrivée</label>
                                <div class="form-control bg-light" id="justificationArriveeTime">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Départ</label>
                                <div class="form-control bg-light" id="justificationDepartTime">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Statut</label>
                                <div class="form-control bg-light fw-bold text-warning" id="justificationStatus">-</div>
                            </div>
                    </div>
                </div>
                
                <form id="justificationForm" method="post" action="api/justifier_retard.php" enctype="multipart/form-data">
                    <input type="hidden" id="justificationEmployeId" name="employe_id">
                    <input type="hidden" id="justificationPointageId" name="pointage_id">
                    <input type="hidden" id="justificationDate" name="date">
                    <input type="hidden" id="submitJustificationHidden" name="submit_justification" value="">
                    
                    <div class="mb-3">
                        <label for="justificationReason" class="form-label fw-bold">Raison du retard *</label>
                        <select class="form-select form-select-lg" id="justificationReason" name="raison" required>
                            <option value="">Sélectionnez une raison</option>
                            <option value="transport">🚗 Problème de transport</option>
                            <option value="sante">🏥 Problème de santé</option>
                            <option value="familial">👨‍👩‍👧‍👦 Raison familiale</option>
                            <option value="meteo">☔ Conditions météorologiques</option>
                            <option value="autre">📝 Autre raison</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="justificationDetails" class="form-label fw-bold">Détails supplémentaires</label>
                        <textarea class="form-control" id="justificationDetails" name="details" rows="4" 
                                  placeholder="Précisez les détails de votre retard (optionnel)..."></textarea>
                        <div class="form-text">
                            Maximum 500 caractères. <span id="char-count">0/500</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pièces jointes (optionnel)</label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="justificationFile" name="piece_jointe" accept=".pdf,.jpg,.png,.doc,.docx">
                            <button class="btn btn-outline-secondary" type="button" id="clearFile">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <small class="text-muted">Formats acceptés : PDF, JPG, PNG, DOC (max. 5MB)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="cancelJustificationBtn">
                    <i class="fas fa-times me-2"></i>Annuler
                </button>
                <button type="submit" class="btn btn-warning px-4" id="submitJustification">
                    <i class="fas fa-paper-plane me-2"></i>Soumettre la justification
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast pour notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toast-title">Notification</strong>
            <small id="toast-time">À l'instant</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toast-message">
            Message de notification
        </div>
    </div>
</div>

<!-- Scripts -->
<!-- bootstrap loaded from footer -->
<script src="assets/js/qr-scanner.umd.min.js"></script>
<script type="module" src="assets/js/scan.js?v=1.0"></script>

<script>
// Initialisation et fonctions utilitaires
document.addEventListener('DOMContentLoaded', function() {
    // Horloge en temps réel
    function updateClock() {
        const now = new Date();
        document.getElementById('live-clock').textContent = 
            now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }
    setInterval(updateClock, 1000);
    updateClock();
    
    // Mettre à jour la date actuelle
    document.getElementById('current-date').textContent = 
        new Date().toLocaleDateString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    
    // Gestion du compteur de caractères
    const detailsTextarea = document.getElementById('justificationDetails');
    if (detailsTextarea) {
        detailsTextarea.addEventListener('input', function() {
            const charCount = this.value.length;
            document.getElementById('char-count').textContent = charCount + '/500';
            if (charCount > 500) {
                this.value = this.value.substring(0, 500);
            }
        });
    }
    
    // Gestion du fichier
    const fileInput = document.getElementById('justificationFile');
    const clearFileBtn = document.getElementById('clearFile');
    
    if (clearFileBtn && fileInput) {
        clearFileBtn.addEventListener('click', function() {
            fileInput.value = '';
        });
    }
    
    // Fonction pour afficher les toasts
    window.showToast = function(title, message, type = 'info') {
        const toastEl = document.getElementById('liveToast');
        const toastTitle = document.getElementById('toast-title');
        const toastMessage = document.getElementById('toast-message');
        const toastTime = document.getElementById('toast-time');
        
        // Configurer le toast selon le type
        toastEl.className = 'toast';
        switch(type) {
            case 'success':
                toastEl.classList.add('bg-success', 'text-white');
                break;
            case 'error':
                toastEl.classList.add('bg-danger', 'text-white');
                break;
            case 'warning':
                toastEl.classList.add('bg-warning');
                break;
            default:
                toastEl.classList.add('bg-primary', 'text-white');
        }
        
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toastTime.textContent = new Date().toLocaleTimeString('fr-FR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    };
    
    // Rafraîchissement automatique toutes les 60 secondes
    setInterval(function() {
        if (window.refreshPointages) {
            window.refreshPointages();
        }
    }, 60000);
    
    // Mettre à jour le timestamp de dernière mise à jour
    setInterval(function() {
        const now = new Date();
        document.getElementById('last-update').textContent = 
            now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }, 1000);
});
</script>
</body>
</html>