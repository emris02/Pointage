<?php
// Vérification et initialisation des variables
$currentPage = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : 'index.php';
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$isSuperAdmin = ($userRole === 'super_admin');
$isAdmin = in_array($userRole, ['admin', 'super_admin']);

// Indicateur pratique pour savoir si l'on est déjà sur la page dashboard
$isOnDashboard = ($currentPage === 'admin_dashboard_unifie.php');
// Whether we're currently viewing the unified admin dashboard page
$isOnDashboard = ($currentPage === 'admin_dashboard_unifie.php');

// Determine active anchor part (after '#') so anchors persist after reload/navigation
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$activeAnchor = '';
if (strpos($requestUri, '#') !== false) {
    $activeAnchor = substr($requestUri, strpos($requestUri, '#') + 1);
}

/**
 * isActive helper
 * Returns true when the current page and anchor/section match the provided values.
 */
function isActive($page, $section, $currentPage, $activeAnchor) {
    if ($currentPage !== $page) return false;

    // By convention, treat the dashboard view (no hash) as the 'dashboard' section
    if ($page === 'admin_dashboard_unifie.php' && $activeAnchor === '' && $section === 'dashboard') {
        return true;
    }

    return $activeAnchor === $section;
}
?>

<aside class="sidebar-simple">
    <div class="sidebar-header">
        <h5><i class="fas fa-cogs"></i> Admin</h5>
    </div>
    
    <nav>
        <a data-panel="dashboard" href="admin_dashboard_unifie.php"
           class="<?= isActive('admin_dashboard_unifie.php', 'dashboard', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <?php if ($isSuperAdmin): ?>
        <a data-panel="admins" href="<?= $isOnDashboard ? '#admins' : 'admin_dashboard_unifie.php#admins' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'admins', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i>
            <span>Admins</span>
        </a>
        <?php endif; ?>
        
        <a data-panel="employes" href="<?= $isOnDashboard ? '#employes' : 'admin_dashboard_unifie.php#employes' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'employes', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Employés</span>
        </a>
        
        <a data-panel="pointage" href="<?= $isOnDashboard ? '#pointage' : 'admin_dashboard_unifie.php#pointage' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'pointage', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-qrcode"></i>
            <span>Pointage</span>
        </a>
        
        <a data-panel="demandes" href="<?= $isOnDashboard ? '#demandes' : 'admin_dashboard_unifie.php#demandes' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'demandes', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-list-alt"></i>
            <span>Demandes</span>
        </a>
        
        <a data-panel="heures" href="<?= $isOnDashboard ? '#heures' : 'admin_dashboard_unifie.php#heures' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'heures', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-hourglass-half"></i>
            <span>Heures</span>
        </a>
        
        <a data-panel="retard" href="<?= $isOnDashboard ? '#retard' : 'admin_dashboard_unifie.php#retard' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'retard', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-clock"></i>
            <span>Retards</span>
        </a>
        
        <a data-panel="calendrier" href="<?= $isOnDashboard ? '#calendrier' : 'admin_dashboard_unifie.php#calendrier' ?>"
           class="<?= isActive('admin_dashboard_unifie.php', 'calendrier', $currentPage, $activeAnchor) ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendrier</span>
        </a>
        
        <a href="admin_settings.php" class="<?= $currentPage === 'admin_settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Paramètres</span>
        </a>
        
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Déconnexion</span>
        </a>
    </nav>
</aside>

<style>
.sidebar-simple {
    width: 240px;
    background: #fff;
    border-right: 1px solid #eee;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.sidebar-header i {
    margin-right: 10px;
    color: #0672e4;
}

.sidebar-simple nav {
    padding: 15px 0;
}

.sidebar-simple nav a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #555;
    text-decoration: none;
    font-size: 14px;
    border-left: 3px solid transparent;
}

.sidebar-simple nav a:hover {
    background: #f8f9fa;
    color: #0672e4;
    border-left-color: #ccc;
}

.sidebar-simple nav a.active {
    background: #f0f5ff;
    color: #0672e4;
    border-left-color: #0672e4;
    font-weight: 500;
}

.sidebar-simple nav i {
    width: 20px;
    margin-right: 12px;
    font-size: 16px;
}

.sidebar-simple nav .logout {
    color: #dc3545;
    margin-top: 15px;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.sidebar-simple nav .logout:hover {
    background: #ffebee;
    border-left-color: #dc3545;
}

@media (max-width: 768px) {
    .sidebar-simple {
        display: none;
    }
}
</style>