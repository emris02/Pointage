<?php
/**
 * Sidebar pour l'interface d'administration
 */
$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_SUPER_ADMIN]);
$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN;
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h4 class="text-white">
            <i class="fas fa-cogs me-2"></i>
            Administration
        </h4>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <!-- Main dashboard anchor (works as full page link) -->
            <li class="nav-item">
                <a data-panel="pointage" class="nav-link btn-nav" href="admin_dashboard_unifie.php#pointage">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a data-panel="employes" class="nav-link btn-nav" href="admin_dashboard_unifie.php#employes">
                    <i class="fas fa-users me-2"></i>
                    Employés
                </a>
            </li>

            <li class="nav-item">
                <a data-panel="demandes" class="nav-link btn-nav" href="admin_dashboard_unifie.php#demandes">
                    <i class="fas fa-list-alt me-2"></i>
                    Demandes
                </a>
            </li>

            <li class="nav-item">
                <a data-panel="heures" class="nav-link btn-nav" href="admin_dashboard_unifie.php#heures">
                    <i class="fas fa-hourglass-half me-2"></i>
                    Heures
                </a>
            </li>

            <li class="nav-item">
                <a data-panel="retard" class="nav-link btn-nav" href="admin_dashboard_unifie.php#retard">
                    <i class="fas fa-clock me-2"></i>
                    Retards
                </a>
            </li>

            <?php if ($isSuperAdmin): ?>
            <li class="nav-item">
                <a data-panel="admins" class="nav-link btn-nav" href="admin_dashboard_unifie.php#admins">
                    <i class="fas fa-user-shield me-2"></i>
                    Admins
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a data-panel="calendrier" class="nav-link btn-nav" href="admin_dashboard_unifie.php#calendrier">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Calendrier
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="admin_settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Paramètres
                </a>
            </li>
        </ul>
    </nav>
