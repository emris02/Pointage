<?php
// Vérification du rôle utilisateur
$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
$currentPage = basename($_SERVER['PHP_SELF']);

// Déterminer la page active
$activePage = $currentPage;
if (strpos($currentPage, 'admin_dashboard_unifie') !== false) {
    $activePage = 'dashboard';
}

// Récupérer le menu depuis la session ou définir un menu par défaut
$menuItems = [
    'dashboard' => [
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'Tableau de bord',
        'href' => 'admin_dashboard_unifie',
        'badge' => null,
        'active' => false
    ],
    'admins' => [
        'icon' => 'fas fa-user-shield',
        'label' => 'Administrateurs',
        'href' => $isSuperAdmin ? ($currentPage === 'admin_dashboard_unifie' ? '#admins' : 'admin_dashboard_unifie#admins') : '#',
        'badge' => null,
        'visible' => $isSuperAdmin
    ],
    'employes' => [
        'icon' => 'fas fa-users',
        'label' => 'Employés',
        'href' => $currentPage === 'admin_dashboard_unifie' ? '#employes' : 'admin_dashboard_unifie#employes',
        'badge' => null
    ],
    'demandes' => [
        'icon' => 'fas fa-list-alt',
        'label' => 'Demandes',
        'href' => 'admin_demandes.php',
        'badge' => isset($_SESSION['pending_demandes']) ? $_SESSION['pending_demandes'] : 0
    ],
    'pointage' => [
        'icon' => 'fas fa-qrcode',
        'label' => 'Pointage',
        'href' => 'pointage.php',
        'badge' => null
    ],
    'heures' => [
        'icon' => 'fas fa-hourglass-half',
        'label' => 'Heures',
        'href' => $currentPage === 'admin_dashboard_unifie' ? '#heures' : 'admin_dashboard_unifie#heures',
        'badge' => null
    ],
    'retards' => [
        'icon' => 'fas fa-clock',
        'label' => 'Retards',
        'href' => $currentPage === 'admin_dashboard_unifie' ? '#retard' : 'admin_dashboard_unifie#retard',
        'badge' => isset($_SESSION['today_lates']) ? $_SESSION['today_lates'] : 0
    ],
    'absences' => [
        'icon' => 'fas fa-user-times',
        'label' => 'Absences',
        'href' => $currentPage === 'admin_dashboard_unifie' ? '#absences' : 'admin_dashboard_unifie#absences',
        'badge' => isset($_SESSION['today_absences']) ? $_SESSION['today_absences'] : 0
    ],
    'calendrier' => [
        'icon' => 'fas fa-calendar-alt',
        'label' => 'Calendrier',
        'href' => $currentPage === 'admin_dashboard_unifie' ? '#calendrier' : 'admin_dashboard_unifie#calendrier',
        'badge' => null
    ],
    'conges' => [
        'icon' => 'fas fa-umbrella-beach',
        'label' => 'Congés',
        'href' => 'gestion_conges.php',
        'badge' => null
    ],
    'rapports' => [
        'icon' => 'fas fa-chart-line',
        'label' => 'Rapports',
        'href' => 'rapports.php',
        'badge' => null
    ],
    'parametres' => [
        'icon' => 'fas fa-cog',
        'label' => 'Paramètres',
        'href' => 'admin_settings.php',
        'badge' => null
    ],
    'system' => [
        'icon' => 'fas fa-server',
        'label' => 'Système',
        'href' => 'admin_system.php',
        'badge' => null,
        'visible' => $isSuperAdmin
    ],
    'support' => [
        'icon' => 'fas fa-headset',
        'label' => 'Support',
        'href' => 'support.php',
        'badge' => null
    ]
];

// Marquer la page active
foreach ($menuItems as $key => &$item) {
    if (isset($item['visible']) && !$item['visible']) {
        continue;
    }
    
    $item['active'] = false;
    if ($key === 'dashboard' && $activePage === 'dashboard') {
        $item['active'] = true;
    } elseif (strpos($currentPage, $key) !== false) {
        $item['active'] = true;
    }
}
unset($item);
?>

<aside class="sidebar-admin">
    <!-- Logo et en-tête -->
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="logo-text">
                <h3 class="mb-0 fw-bold">Xpert Pro</h3>
                <p class="mb-0 small opacity-75">Administration</p>
            </div>
        </div>
        
        <!-- Bouton de réduction -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- Menu principal -->
    <nav class="sidebar-nav">
        <div class="nav-section">
            <h6 class="section-title">PRINCIPAL</h6>
            <ul class="nav flex-column">
                <?php foreach (['dashboard', 'admins', 'employes', 'demandes'] as $key): 
                    if (isset($menuItems[$key]) && (!isset($menuItems[$key]['visible']) || $menuItems[$key]['visible'])): 
                        $item = $menuItems[$key];
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                        <div class="nav-icon">
                            <i class="<?= $item['icon'] ?>"></i>
                        </div>
                        <span class="nav-label"><?= $item['label'] ?></span>
                        <?php if ($item['badge']): ?>
                        <span class="nav-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        <?php if ($item['active']): ?>
                        <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; endforeach; ?>
            </ul>
        </div>

        <!-- Section Pointage et temps -->
        <div class="nav-section">
            <h6 class="section-title">POINTAGE & TEMPS</h6>
            <ul class="nav flex-column">
                <?php foreach (['pointage', 'heures', 'retards', 'absences', 'calendrier'] as $key): 
                    if (isset($menuItems[$key])): 
                        $item = $menuItems[$key];
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                        <div class="nav-icon">
                            <i class="<?= $item['icon'] ?>"></i>
                        </div>
                        <span class="nav-label"><?= $item['label'] ?></span>
                        <?php if ($item['badge']): ?>
                        <span class="nav-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        <?php if ($item['active']): ?>
                        <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; endforeach; ?>
            </ul>
        </div>

        <!-- Section Gestion -->
        <div class="nav-section">
            <h6 class="section-title">GESTION</h6>
            <ul class="nav flex-column">
                <?php foreach (['conges', 'rapports'] as $key): 
                    if (isset($menuItems[$key])): 
                        $item = $menuItems[$key];
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                        <div class="nav-icon">
                            <i class="<?= $item['icon'] ?>"></i>
                        </div>
                        <span class="nav-label"><?= $item['label'] ?></span>
                        <?php if ($item['badge']): ?>
                        <span class="nav-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        <?php if ($item['active']): ?>
                        <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; endforeach; ?>
            </ul>
        </div>

        <!-- Section Système -->
        <div class="nav-section">
            <h6 class="section-title">SYSTÈME</h6>
            <ul class="nav flex-column">
                <?php foreach (['parametres', 'system', 'support'] as $key): 
                    if (isset($menuItems[$key]) && (!isset($menuItems[$key]['visible']) || $menuItems[$key]['visible'])): 
                        $item = $menuItems[$key];
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= $item['href'] ?>">
                        <div class="nav-icon">
                            <i class="<?= $item['icon'] ?>"></i>
                        </div>
                        <span class="nav-label"><?= $item['label'] ?></span>
                        <?php if ($item['badge']): ?>
                        <span class="nav-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        <?php if ($item['active']): ?>
                        <span class="nav-indicator"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; endforeach; ?>
            </ul>
        </div>
    </nav>

    <!-- Pied de sidebar -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                $userInitials = isset($_SESSION['prenom'], $_SESSION['nom'])
                    ? strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1))
                    : 'AD';
                echo $userInitials;
                ?>
            </div>
            <div class="user-details">
                <h6 class="mb-0">
                    <?= isset($_SESSION['prenom']) ? htmlspecialchars($_SESSION['prenom']) : 'Admin' ?>
                </h6>
                <small class="text-muted">
                    <?= isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?>
                </small>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar-admin');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    
    // État initial
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
    }
    
    // Toggle sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            
            // Animer l'icône
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        });
    }
    
    // Overlay pour mobile
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show-mobile');
            sidebarOverlay.classList.remove('active');
        });
    }
    
    // Animation des liens actifs
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Supprimer active de tous les liens
            navLinks.forEach(l => l.classList.remove('active'));
            // Ajouter active au lien cliqué
            this.classList.add('active');
            
            // Fermer le sidebar sur mobile
            if (window.innerWidth < 992) {
                sidebar.classList.remove('show-mobile');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('active');
                }
            }
        });
    });
    
    // Gestion du responsive
    function handleResponsive() {
        if (window.innerWidth < 992) {
            sidebar.classList.add('mobile');
            sidebar.classList.remove('collapsed');
        } else {
            sidebar.classList.remove('mobile', 'show-mobile');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
        }
    }
    
    // Écouter le redimensionnement
    window.addEventListener('resize', handleResponsive);
    handleResponsive(); // Initial call
    
    // Ajouter un effet de hover sur les éléments de menu
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateX(5px)';
            }
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
});
</script>

<style>
/* =======================================================
   VARIABLES
======================================================= */
:root {
    --sidebar-bg: #1e293b;
    --sidebar-text: #94a3b8;
    --sidebar-text-active: #ffffff;
    --sidebar-border: #334155;
    --sidebar-accent: #3b82f6;
    --sidebar-accent-light: rgba(59, 130, 246, 0.1);
    --sidebar-width: 280px;
    --sidebar-collapsed: 80px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --radius: 10px;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* =======================================================
   SIDEBAR PRINCIPAL
======================================================= */
.sidebar-admin {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    transition: var(--transition);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
    border-right: 1px solid var(--sidebar-border);
    overflow-y: auto;
}

.sidebar-admin.collapsed {
    width: var(--sidebar-collapsed);
}

.sidebar-admin.collapsed .sidebar-header .logo-text,
.sidebar-admin.collapsed .nav-label,
.sidebar-admin.collapsed .section-title,
.sidebar-admin.collapsed .user-details,
.sidebar-admin.collapsed .nav-badge {
    display: none;
}

.sidebar-admin.collapsed .sidebar-header {
    justify-content: center;
    padding: 20px 0;
}

.sidebar-admin.collapsed .sidebar-toggle i {
    transform: rotate(180deg);
}

/* =======================================================
   EN-TÊTE DU SIDEBAR
======================================================= */
.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid var(--sidebar-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--sidebar-accent), #2563eb);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    box-shadow: var(--shadow);
}

.logo-text h3 {
    font-size: 1.5rem;
    color: white;
    letter-spacing: -0.5px;
}

.logo-text p {
    font-size: 0.8rem;
    opacity: 0.8;
}

.sidebar-toggle {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--sidebar-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--sidebar-text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
}

.sidebar-toggle:hover {
    background: var(--sidebar-accent-light);
    color: white;
    border-color: var(--sidebar-accent);
}

/* =======================================================
   NAVIGATION
======================================================= */
.sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 30px;
    padding: 0 20px;
}

.section-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--sidebar-text);
    opacity: 0.7;
    margin-bottom: 12px;
    font-weight: 600;
}

.sidebar-admin.collapsed .nav-section {
    text-align: center;
    padding: 0 10px;
}

/* =======================================================
   ITEMS DE NAVIGATION
======================================================= */
.nav-item {
    margin-bottom: 4px;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: var(--radius);
    color: var(--sidebar-text);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.05);
    color: white;
    transform: translateX(4px);
}

.nav-link.active {
    background: var(--sidebar-accent-light);
    color: var(--sidebar-text-active);
}

.nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 24px;
    background: var(--sidebar-accent);
    border-radius: 0 2px 2px 0;
}

.nav-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.nav-label {
    flex: 1;
    font-size: 0.95rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-badge {
    background: var(--sidebar-accent);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
    margin-left: 8px;
}

.nav-indicator {
    width: 8px;
    height: 8px;
    background: var(--sidebar-accent);
    border-radius: 50%;
    margin-left: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* =======================================================
   PIED DE SIDEBAR
======================================================= */
.sidebar-footer {
    padding: 20px;
    border-top: 1px solid var(--sidebar-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.user-details h6 {
    font-size: 0.9rem;
    color: white;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details small {
    font-size: 0.75rem;
    opacity: 0.7;
}

.logout-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: var(--transition);
}

.logout-btn:hover {
    background: #ef4444;
    color: white;
    transform: rotate(90deg);
}

/* =======================================================
   OVERLAY MOBILE
======================================================= */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1049;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* =======================================================
   RESPONSIVE MOBILE
======================================================= */
@media (max-width: 991.98px) {
    .sidebar-admin {
        transform: translateX(-100%);
        width: 280px;
        transition: transform 0.3s ease;
    }
    
    .sidebar-admin.show-mobile {
        transform: translateX(0);
    }
    
    .sidebar-admin.mobile {
        transform: translateX(-100%);
    }
    
    .sidebar-toggle {
        display: none;
    }
    
    .main-content-container {
        margin-left: 0 !important;
    }
}

@media (min-width: 992px) {
    .sidebar-admin:not(.collapsed) ~ .main-content-container {
        margin-left: var(--sidebar-width);
    }
    
    .sidebar-admin.collapsed ~ .main-content-container {
        margin-left: var(--sidebar-collapsed);
    }
    
    .sidebar-overlay {
        display: none !important;
    }
}

/* =======================================================
   CONTENU PRINCIPAL
======================================================= */
.main-content-container {
    transition: var(--transition);
    padding-left: 1rem;
    padding-right: 1rem;
}

/* =======================================================
   ANIMATIONS
======================================================= */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* =======================================================
   PERSONNALISATION SCROLLBAR
======================================================= */
.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* =======================================================
   ACCESSIBILITÉ
======================================================= */
@media (prefers-reduced-motion: reduce) {
    .sidebar-admin,
    .nav-link,
    .sidebar-toggle,
    .logout-btn {
        transition: none !important;
        animation: none !important;
    }
}
</style>