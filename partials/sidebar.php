<?php
// Vérification du rôle utilisateur
$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h4>
            <i class="fas fa-cogs me-2"></i> Administration
        </h4>
    </div>

    <?php $isOnDashboard = ($currentPage === 'admin_dashboard_unifie'); ?>
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard_unifie">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>

            <!-- Visible uniquement pour le super admin -->
            <?php if ($isSuperAdmin): ?>
            <li class="nav-item">
                <?php $href = $isOnDashboard ? '#admins' : 'admin_dashboard_unifie#admins'; ?>
                <a class="nav-link btn-nav <?= $isOnDashboard ? '' : '' ?>" href="<?= $href ?>">
                    <i class="fas fa-user-shield me-2"></i> Admin
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <?php $href = $isOnDashboard ? '#employes' : 'admin_dashboard_unifie#employes'; ?>
                <a class="nav-link btn-nav" href="<?= $href ?>">
                    <i class="fas fa-users me-2"></i> Employés
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="admin_demandes.php">
                    <i class="fas fa-list-alt me-2"></i> Demandes
                </a>
            </li>

            <li class="nav-item">
                <?php $href = $isOnDashboard ? '#heures' : 'admin_dashboard_unifie#heures'; ?>
                <a class="nav-link btn-nav" href="<?= $href ?>">
                    <i class="fas fa-hourglass-half me-2"></i> Heures
                </a>
            </li>

            <li class="nav-item">
                <?php $href = $isOnDashboard ? '#retard' : 'admin_dashboard_unifie#retard'; ?>
                <a class="nav-link btn-nav" href="<?= $href ?>">
                    <i class="fas fa-clock me-2"></i> Retards
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="pointage.php">
                    <i class="fas fa-qrcode me-2"></i> Pointage
                </a>
            </li>

            <li class="nav-item">
                <?php $href = $isOnDashboard ? '#calendrier' : 'admin_dashboard_unifie#calendrier'; ?>
                <a class="nav-link btn-nav" href="<?= $href ?>">
                    <i class="fas fa-calendar-alt me-2"></i> Calendrier
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="admin_settings.php">
                    <i class="fas fa-cog me-2"></i> Paramètres
                </a>
            </li>
        </ul>
    </nav>
</aside>

<style>
/* === STYLE MODERNE ET RESPONSIVE === */
.sidebar {
    background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
    min-height: 100vh;
    width: 250px;
    position: fixed;
    top: 0;
    left: 0;
    padding-top: 20px;
    transition: all 0.3s ease-in-out;
    box-shadow: 2px 0 10px rgba(0,0,0,0.2);
    z-index: 100;
}

.sidebar-header {
    padding: 0 20px 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    color: #f8f9fa;
    font-weight: 600;
}

.sidebar-nav .nav-link {
    color: #bdc3c7;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    font-size: 0.95rem;
}

    .sidebar-nav .nav-link:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
    border-left: 3px solid #6c5ce7; /* purple accent on hover */
}

    .sidebar-nav .nav-link.active {
    color: #fff;
    background: rgba(108, 92, 231, 0.08); /* subtle purple tint instead of gold transparent */
    border-left: 3px solid #6c5ce7; /* solid purple accent */
    font-weight: 600;
}

.sidebar-nav i {
    width: 20px;
    text-align: center;
}

/* === RESPONSIVE === */
@media (max-width: 992px) {
    .sidebar {
        width: 70px;
    }

    .sidebar-header h4 {
        font-size: 0;
    }

    .sidebar-nav .nav-link {
        justify-content: center;
        padding: 10px;
    }

    .sidebar-nav .nav-link span,
    .sidebar-nav .nav-link i + span {
        display: none;
    }
}
</style>
