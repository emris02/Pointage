<?php
// PARTIAL NAVBAR : barre de navigation principale
// Ensure $isAdmin is defined to avoid warnings when header variant doesn't set it
if (!isset($isAdmin)) {
    $isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','super_admin']);
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $isAdmin ? 'admin_dashboard_unifie' : 'employe_dashboard.php' ?>">
            <i class="fas fa-clock me-2"></i> Pointage Xpert Pro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Ajoute ici les liens de navigation dynamiques -->
            </ul>
            <!-- Barre de recherche globale -->
            <form class="d-flex mx-auto" action="global_search.php" method="get" style="max-width:350px;">
                <input class="form-control me-2" type="search" name="q" placeholder="Recherche globale..." aria-label="Recherche">
                <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
            </form>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="profil_admin.php"><i class="fas fa-user"></i> Mon profil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- Fin du partial navbar -->
