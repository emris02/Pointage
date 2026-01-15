<?php
// ===============================
// PARTIAL HEADER — XPERT POINTAGE
// ===============================

$theme      = $APP_SETTINGS['theme'] ?? 'clair';
$font_size  = $APP_SETTINGS['font_size'] ?? 100;

// ===============================
// UTILISATEUR
// ===============================
$userFirstName = $_SESSION['prenom'] ?? 'Admin';
$userLastName  = $_SESSION['nom'] ?? '';
$userName      = htmlspecialchars(trim($userFirstName . ' ' . $userLastName));
$userEmail     = htmlspecialchars($_SESSION['email'] ?? 'admin@xpertpro.com');
$userRole      = $_SESSION['role'] ?? 'admin';

$userInitials  = strtoupper(
    substr($userFirstName, 0, 1) . substr($userLastName, 0, 1)
);

// ===============================
// RÔLE LISIBLE
// ===============================
$displayRole = match ($userRole) {
    'super_admin' => 'Super Administrateur',
    'admin'       => 'Administrateur',
    'manager'     => 'Gestionnaire',
    'rh'          => 'Ressources Humaines',
    'employe'     => 'Employé',
    default       => 'Utilisateur'
};

$isSuperAdmin = ($userRole === 'super_admin');

// ===============================
// ÉTAT DE PAUSE (exemple - à adapter selon votre logique métier)
// ===============================
$isOnBreak = $_SESSION['is_on_break'] ?? false;
$breakTimeRemaining = $_SESSION['break_time_remaining'] ?? 0; // en secondes
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $pageTitle ?? 'XPERT POINTAGE' ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    font-size: <?= (int)$font_size ?>%;
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --dark: #1e293b;
    --border: #e2e8f0;
    --bg: #f5f7fa;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
}

/* ================= HEADER ================= */
.main-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 70px;
    background: linear-gradient(90deg, var(--primary), #3b82f6);
    z-index: 1000;
    box-shadow: 0 2px 12px rgba(0,0,0,.15);
}

.header-container {
    height: 100%;
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* ===== LEFT ===== */
.header-left {
    display: flex;
    align-items: center;
    gap: 18px;
}

.toggle-btn {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    border: none;
    background: rgba(255,255,255,.15);
    color: #fff;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: .2s;
}

.toggle-btn:hover {
    background: rgba(255,255,255,.25);
}

.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 90;
    display: none;
}

.sidebar-overlay.active {
    display: block;
}

.header-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    letter-spacing: .5px;
}

/* ===== RIGHT ===== */
.header-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

/* Minuteur de pause */
.break-timer {
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 20px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: white;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 120px;
    justify-content: center;
}

.break-timer:hover {
    background: rgba(255,255,255,.2);
}

.break-timer.active {
    background: rgba(239, 68, 68, 0.2);
    border-color: var(--danger);
    animation: pulse 2s infinite;
}

.break-timer .icon {
    font-size: 12px;
}

.break-timer .time {
    font-family: 'Courier New', monospace;
    font-weight: 600;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Dropdown amélioré */
.user-profile-dropdown {
    position: relative;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 10px;
    transition: .2s;
    background: transparent;
    border: none;
    color: inherit;
}

.user-profile:hover {
    background: rgba(255,255,255,.12);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #fff;
    color: var(--primary);
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-info {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
}

.user-role {
    font-size: 12px;
    color: rgba(255,255,255,.8);
}

/* Dropdown menu */
.dropdown-menu-custom {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 280px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
    border: 1px solid var(--border);
    z-index: 1001;
    opacity: 0;
    transform: translateY(-10px);
    transition: all 0.2s ease;
}

.dropdown-menu-custom.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

.dropdown-header-custom {
    padding: 16px;
    background: linear-gradient(135deg, var(--primary), #3b82f6);
    color: white;
    border-radius: 12px 12px 0 0;
}

.dropdown-body-custom {
    padding: 8px 0;
}

.dropdown-item-custom {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-size: 14px;
}

.dropdown-item-custom:hover {
    background: #f1f5f9;
    color: var(--primary);
}

.dropdown-item-custom i {
    width: 20px;
    margin-right: 10px;
    color: #64748b;
}

.dropdown-divider-custom {
    height: 1px;
    background: var(--border);
    margin: 8px 0;
}

/* ===== CONTENT ===== */
.main-content {
    margin-top: 70px;
    margin-left: 260px;
    min-height: calc(100vh - 70px);
    background: var(--bg);
    transition: margin-left .3s ease;
}

.main-content.sidebar-collapsed {
    margin-left: 70px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .main-content {
        margin-left: 0 !important;
    }
    
    .user-info {
        display: none;
    }
    
    .break-timer .text {
        display: none;
    }
    
    .break-timer {
        min-width: auto;
        padding: 6px;
    }
}

@media (max-width: 576px) {
    .header-container {
        padding: 0 15px;
    }
    
    .header-title {
        font-size: 18px;
    }
    
    .toggle-btn {
        width: 36px;
        height: 36px;
    }
}
</style>
</head>

<body>

<header class="main-header">
    <div class="header-container">

        <div class="header-left">
            <div class="header-title">XPERT POINTAGE</div>
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="header-right">
            <!-- Minuteur de pause -->
            <div class="break-timer <?= $isOnBreak ? 'active' : '' ?>" id="breakTimer">
                <span class="icon"><i class="fas <?= $isOnBreak ? 'fa-coffee' : 'fa-clock' ?>"></i></span>
                <span class="time" id="breakTimeDisplay">
                    <?= $isOnBreak ? gmdate("i:s", $breakTimeRemaining) : '00:00' ?>
                </span>
                <span class="text"><?= $isOnBreak ? 'En pause' : 'Pause' ?></span>
            </div>

            <!-- Dropdown utilisateur -->
            <div class="user-profile-dropdown">
                <button class="user-profile" id="userDropdownToggle">
                    <div class="user-avatar"><?= $userInitials ?></div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($userFirstName) ?></div>
                        <div class="user-role"><?= $displayRole ?></div>
                    </div>
                    <i class="fas fa-chevron-down ms-2" style="font-size: 12px; color: rgba(255,255,255,.7);"></i>
                </button>

                <div class="dropdown-menu-custom" id="userDropdownMenu">
                    <div class="dropdown-header-custom">
                        <strong><?= $userName ?></strong><br>
                        <small><?= $userEmail ?></small>
                    </div>
                    
                    <div class="dropdown-body-custom">
                        <a href="profil.php" class="dropdown-item-custom">
                            <i class="fas fa-user"></i>Mon profil
                        </a>
                        <a href="parametres.php" class="dropdown-item-custom">
                            <i class="fas fa-cog"></i>Paramètres
                        </a>
                        
                        <?php if ($isSuperAdmin): ?>
                        <a href="administration.php" class="dropdown-item-custom">
                            <i class="fas fa-shield-alt"></i>Administration
                        </a>
                        <?php endif; ?>
                        
                        <div class="dropdown-divider-custom"></div>
                        
                        <button class="dropdown-item-custom" id="toggleBreakBtn">
                            <i class="fas <?= $isOnBreak ? 'fa-play' : 'fa-pause' ?>"></i>
                            <?= $isOnBreak ? 'Reprendre le travail' : 'Prendre une pause' ?>
                        </button>
                        
                        <div class="dropdown-divider-custom"></div>
                        
                        <a href="logout.php" class="dropdown-item-custom text-danger">
                            <i class="fas fa-sign-out-alt"></i>Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// ===============================
// GESTION DU DROPDOWN
// ===============================
const userDropdownToggle = document.getElementById('userDropdownToggle');
const userDropdownMenu = document.getElementById('userDropdownMenu');
let dropdownOpen = false;

// Ouvrir/fermer le dropdown
userDropdownToggle?.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdownOpen = !dropdownOpen;
    userDropdownMenu.classList.toggle('show', dropdownOpen);
});

// Fermer le dropdown en cliquant à l'extérieur
document.addEventListener('click', (e) => {
    if (dropdownOpen && !userDropdownToggle.contains(e.target) && !userDropdownMenu.contains(e.target)) {
        dropdownOpen = false;
        userDropdownMenu.classList.remove('show');
    }
});

// Fermer le dropdown en appuyant sur ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && dropdownOpen) {
        dropdownOpen = false;
        userDropdownMenu.classList.remove('show');
    }
});

// ===============================
// MINUTEUR DE PAUSE
// ===============================
const breakTimer = document.getElementById('breakTimer');
const breakTimeDisplay = document.getElementById('breakTimeDisplay');
const toggleBreakBtn = document.getElementById('toggleBreakBtn');
let breakInterval = null;
let breakTime = <?= $breakTimeRemaining ?>;
let isBreakActive = <?= $isOnBreak ? 'true' : 'false' ?>;

// Fonction pour formater le temps
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Fonction pour démarrer le minuteur
function startBreakTimer(duration = 300) { // 5 minutes par défaut
    if (breakInterval) clearInterval(breakInterval);
    
    isBreakActive = true;
    breakTime = duration;
    breakTimer.classList.add('active');
    breakTimer.querySelector('.icon i').className = 'fas fa-coffee';
    toggleBreakBtn.innerHTML = '<i class="fas fa-play"></i>Reprendre le travail';
    
    // Mettre à jour l'affichage
    breakTimeDisplay.textContent = formatTime(breakTime);
    
    // Démarrer le compte à rebours
    breakInterval = setInterval(() => {
        if (breakTime <= 0) {
            clearInterval(breakInterval);
            endBreakTimer();
            return;
        }
        
        breakTime--;
        breakTimeDisplay.textContent = formatTime(breakTime);
        
        // Notification quand il reste 1 minute
        if (breakTime === 60) {
            showNotification('Il reste 1 minute de pause');
        }
    }, 1000);
}

// Fonction pour arrêter le minuteur
function endBreakTimer() {
    if (breakInterval) {
        clearInterval(breakInterval);
        breakInterval = null;
    }
    
    isBreakActive = false;
    breakTimer.classList.remove('active');
    breakTimer.querySelector('.icon i').className = 'fas fa-clock';
    breakTimeDisplay.textContent = '00:00';
    toggleBreakBtn.innerHTML = '<i class="fas fa-pause"></i>Prendre une pause';
    
    showNotification('Pause terminée ! Reprise du travail.');
}

// Basculer entre pause/reprise
toggleBreakBtn?.addEventListener('click', async () => {
    if (!isBreakActive) {
        // Demander la durée de la pause
        const duration = prompt('Durée de la pause (en minutes) :', '5');
        if (duration && !isNaN(duration) && duration > 0) {
            startBreakTimer(duration * 60);
            
            // Envoyer la requête au serveur (à adapter selon votre backend)
            try {
                const response = await fetch('api/toggle_pause.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start',
                        duration: duration
                    })
                });
                
                if (!response.ok) {
                    console.error('Erreur lors de la mise à jour de la pause');
                }
            } catch (error) {
                console.error('Erreur réseau:', error);
            }
        }
    } else {
        endBreakTimer();
        
        // Envoyer la requête au serveur
        try {
            const response = await fetch('api/toggle_pause.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'stop' })
            });
            
            if (!response.ok) {
                console.error('Erreur lors de la reprise du travail');
            }
        } catch (error) {
            console.error('Erreur réseau:', error);
        }
    }
    
    // Fermer le dropdown après action
    dropdownOpen = false;
    userDropdownMenu.classList.remove('show');
});

// Initialiser le minuteur si la pause est active
if (isBreakActive && breakTime > 0) {
    startBreakTimer(breakTime);
}

// ===============================
// FONCTIONS UTILITAIRES
// ===============================
function showNotification(message, type = 'info') {
    // Vous pouvez intégrer ici votre système de notifications
    console.log(`Notification [${type}]: ${message}`);
    
    // Exemple simple avec alert (à remplacer par votre système)
    if (type === 'warning') {
        alert(message);
    }
}

// ===============================
// GESTION DE LA SIDEBAR
// ===============================
// Fermer le dropdown lors du redimensionnement
window.addEventListener('resize', () => {
    if (window.innerWidth < 992 && dropdownOpen) {
        dropdownOpen = false;
        userDropdownMenu.classList.remove('show');
    }
});
</script>
<script src="assets/js/profil.js"></script>
</body>
</html>