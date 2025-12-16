<?php
// PARTIAL HEADER : balises <head>, liens CSS, navigation principale

$theme = $APP_SETTINGS['theme'] ?? 'clair';
$font_size = $APP_SETTINGS['font_size'] ?? 100;

// Informations utilisateur
$userName = isset($_SESSION['prenom']) && isset($_SESSION['nom']) 
    ? htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom'])
    : 'Administrateur';
$userInitials = isset($_SESSION['prenom'], $_SESSION['nom'])
    ? strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1))
    : 'AD';
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
$isSuperAdmin = ($userRole === 'super_admin');
$userFirstName = isset($_SESSION['prenom']) ? htmlspecialchars($_SESSION['prenom']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Xpert Pro - Dashboard' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/main.css">

    <?php if (isset($additionalCSS)): 
        $basePathCss = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    ?>
        <?php foreach ($additionalCSS as $css): 
            $isExternalCss = preg_match('#^https?://#i', $css);
            $href = $isExternalCss ? $css : ($basePathCss . '/' . ltrim($css, '/'));
        ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($href) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/xpertpro.png">
    
    <style>
    :root { 
        font-size: <?= intval($font_size) ?>%; 
        --primary: #0672e4;
        --primary-dark: #3a56d4;
        --primary-light: #eef2ff;
        --danger: #ef4444;
        --dark: #1e293b;
        --light: #f8fafc;
        --border: #e2e8f0;
        --text: #ffff;
        --text-light: #64748b;
    }
    
    body {
        background-color: var(--light);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    </style>
</head>
<body class="<?= $bodyClass ?? '' ?>">

<!-- Header avec dropdown Bootstrap -->
<header class="header-dashboard">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-2">
        <div class="container-fluid px-3">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="admin_dashboard_unifie.php">
                <div class="logo-wrapper me-2">
                    <i class="fas fa-clock text-primary fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">Xpert Pro</div>
                    <div class="small text-muted">Dashboard</div>
                </div>
            </a>

            <!-- Dropdown Utilisateur - Bootstrap -->
            <div class="dropdown">
                <button class="btn user-dropdown-btn d-flex align-items-center" 
                        type="button" 
                        id="userDropdownBtn"
                        data-bs-toggle="dropdown" 
                        aria-expanded="false">
                    
                    <!-- Avatar selon l'écran -->
                    <div class="user-avatar-wrapper">
                        <div class="user-avatar d-none d-lg-flex">
                            <?= $userInitials ?>
                        </div>
                        <div class="user-avatar-mobile d-lg-none">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <!-- Infos utilisateur (desktop seulement) -->
                    <div class="user-info d-none d-lg-block text-start ms-2 me-3">
                        <div class="user-name fw-medium"><?= $userFirstName ?></div>
                        <div class="user-role small text-muted">
                            <?= $isSuperAdmin ? 'Super Admin' : 'Admin' ?>
                        </div>
                    </div>
                    
                    <!-- Icône flèche -->
                    <i class="fas fa-chevron-down dropdown-arrow ms-1"></i>
                </button>
                
                <!-- Menu Dropdown -->
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" 
                    aria-labelledby="userDropdownBtn">
                    
                    <!-- Header -->
                    <li class="dropdown-header p-3 bg-light rounded-top">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar-lg me-3">
                                <?= $userInitials ?>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-semibold"><?= $userName ?></h6>
                                <small class="text-muted">
                                    <?= $isSuperAdmin ? 'Super Administrateur' : 'Administrateur' ?>
                                </small>
                            </div>
                        </div>
                    </li>
                    
                    <li><hr class="dropdown-divider mx-3 my-2"></li>
                    
                    <!-- Liens -->
                    <li>
                        <a class="dropdown-item py-2 px-3" href="profil_admin.php">
                            <i class="fas fa-user-circle me-3 text-primary"></i>
                            Mon profil
                        </a>
                    </li>
                    
                    <li>
                        <a class="dropdown-item py-2 px-3" href="admin_settings.php">
                            <i class="fas fa-cog me-3 text-primary"></i>
                            Paramètres
                        </a>
                    </li>
                    
                    <?php if ($isSuperAdmin): ?>
                    <li>
                        <a class="dropdown-item py-2 px-3" href="admin_system.php">
                            <i class="fas fa-server me-3 text-primary"></i>
                            Système
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li><hr class="dropdown-divider mx-3 my-2"></li>
                    
                    <!-- Déconnexion -->
                    <li>
                        <a class="dropdown-item py-2 px-3 text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-3"></i>
                            Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<!-- Contenu principal -->
<main class="main-content-container">

<!-- Overlay pour mobile (quand dropdown ouvert) -->
<div class="dropdown-overlay" id="dropdownOverlay"></div>

<!-- Scripts -->
<!-- Bootstrap JS Bundle (avec Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" 
        crossorigin="anonymous"></script>

<script>
// Gestion du dropdown
document.addEventListener('DOMContentLoaded', function() {
    const dropdownBtn = document.getElementById('userDropdownBtn');
    const dropdown = dropdownBtn.closest('.dropdown');
    const dropdownMenu = dropdown.querySelector('.dropdown-menu');
    const dropdownArrow = dropdown.querySelector('.dropdown-arrow');
    const overlay = document.getElementById('dropdownOverlay');
    
    // Vérifier que Bootstrap est chargé
    if (typeof bootstrap !== 'undefined') {
        // Initialiser le dropdown Bootstrap
        const bsDropdown = new bootstrap.Dropdown(dropdownBtn);
        
        // Animation de la flèche
        dropdownBtn.addEventListener('show.bs.dropdown', function() {
            dropdownArrow.style.transform = 'rotate(180deg)';
            dropdownArrow.style.transition = 'transform 0.3s ease';
            
            // Afficher l'overlay sur mobile
            if (window.innerWidth < 992) {
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
        
        dropdownBtn.addEventListener('hide.bs.dropdown', function() {
            dropdownArrow.style.transform = 'rotate(0deg)';
            
            // Cacher l'overlay sur mobile
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Fermer le dropdown en cliquant sur l'overlay (mobile)
        overlay.addEventListener('click', function() {
            bsDropdown.hide();
        });
        
        // Fermer le dropdown en cliquant en dehors (desktop)
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target) && dropdownMenu.classList.contains('show')) {
                bsDropdown.hide();
            }
        });
        
        // Empêcher la fermeture quand on clique dans le dropdown
        dropdownMenu.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        
    } else {
        console.error('Bootstrap non chargé');
        
        // Fallback si Bootstrap n'est pas chargé
        dropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isOpen = dropdownMenu.classList.contains('show');
            
            // Fermer tous les autres dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== dropdownMenu) {
                    menu.classList.remove('show');
                    menu.previousElementSibling.querySelector('.dropdown-arrow').style.transform = 'rotate(0deg)';
                }
            });
            
            // Toggle ce dropdown
            dropdownMenu.classList.toggle('show');
            dropdownArrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
            
            // Gérer overlay mobile
            if (window.innerWidth < 992) {
                if (!isOpen) {
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
        
        // Fermer au clic extérieur
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target) && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.style.transform = 'rotate(0deg)';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Overlay mobile
        overlay.addEventListener('click', function() {
            dropdownMenu.classList.remove('show');
            dropdownArrow.style.transform = 'rotate(0deg)';
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // Gérer le redimensionnement
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});
</script>

<style>
/* =======================================================
   VARIABLES GLOBALES
======================================================= */
:root {
    --primary: #0672e4;
    --primary-dark: #3a56d4;
    --primary-light: #eef2ff;
    --danger: #ef4444;
    --dark: #1e293b;
    --light: #f8fafc;
    --border: #e2e8f0;
    --text: #ffff;
    --text-light: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 8px;
    --transition: all 0.3s ease;
}

/* =======================================================
   HEADER
======================================================= */
.header-dashboard {
    position: sticky;
    top: 0;
    z-index: 1030;
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.navbar {
    min-height: 70px;
}

.logo-wrapper {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* =======================================================
   BOUTON DROPDOWN
======================================================= */
.user-dropdown-btn {
    background: white !important;
    border: 1px solid var(--border) !important;
    border-radius: var(--radius) !important;
    padding: 0.5rem 1rem !important;
    color: var(--text) !important;
    transition: var(--transition) !important;
    box-shadow: none !important;
    outline: none !important;
}

.user-dropdown-btn:hover {
    background: var(--primary-light) !important;
    border-color: var(--primary) !important;
    color: var(--primary) !important;
}

.user-dropdown-btn:focus {
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15) !important;
    border-color: var(--primary) !important;
}

.user-dropdown-btn[aria-expanded="true"] {
    background: var(--primary-light) !important;
    border-color: var(--primary) !important;
    color: var(--primary) !important;
}

/* =======================================================
   AVATARS
======================================================= */
.user-avatar-wrapper {
    position: relative;
}

.user-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.user-avatar-mobile {
    width: 36px;
    height: 36px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.user-avatar-lg {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.1rem;
}

/* =======================================================
   INFOS UTILISATEUR
======================================================= */
.user-info {
    min-width: 100px;
}

.user-name {
    font-size: 0.95rem;
    line-height: 1.2;
    color: var(--dark);
}

.user-role {
    font-size: 0.8rem;
    line-height: 1.2;
}

/* =======================================================
   ICÔNE FLÈCHE
======================================================= */
.dropdown-arrow {
    color: var(--text-light);
    font-size: 0.8rem;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin-left: 4px;
}

/* =======================================================
   MENU DROPDOWN
======================================================= */
.dropdown-menu {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.5rem;
    box-shadow: var(--shadow-lg);
    min-width: 280px;
    animation: dropdownFadeIn 0.2s ease-out;
}

@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-header {
    background: var(--primary-light);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    padding: 1rem !important;
}

.dropdown-header h6 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.dropdown-header small {
    font-size: 0.85rem;
    color: var(--text-light);
}

.dropdown-divider {
    border-color: var(--border);
    opacity: 0.5;
    margin: 0.5rem 1rem;
}

/* =======================================================
   ITEMS DROPDOWN
======================================================= */
.dropdown-item {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin: 0.15rem 0;
    font-size: 0.95rem;
    transition: var(--transition);
    display: flex;
    align-items: center;
}

.dropdown-item:hover {
    background: var(--primary-light);
    color: var(--primary);
    transform: translateX(3px);
}

.dropdown-item:focus {
    background: var(--primary-light);
    color: var(--primary);
    outline: 2px solid var(--primary);
    outline-offset: -2px;
}

.dropdown-item i {
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
    margin-right: 12px;
}

.dropdown-item.text-danger {
    color: var(--danger) !important;
}

.dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger) !important;
}

/* =======================================================
   OVERLAY MOBILE
======================================================= */
.dropdown-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1029;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.dropdown-overlay.active {
    display: block;
    opacity: 1;
}

/* =======================================================
   RESPONSIVE MOBILE
======================================================= */
@media (max-width: 991.98px) {
    /* Header fixe */
    .header-dashboard {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
    }
    
    /* Ajustement du bouton */
    .user-dropdown-btn {
        padding: 0.4rem 0.8rem !important;
    }
    
    /* Cacher le texte sur mobile */
    .user-info {
        display: none !important;
    }
    
    /* Logo plus petit */
    .logo-wrapper {
        width: 36px;
        height: 36px;
    }
    
    .navbar-brand .fw-bold {
        font-size: 1.1rem;
    }
    
    .navbar-brand .small {
        font-size: 0.75rem;
    }
    
    /* Dropdown mobile */
    .dropdown-menu {
        position: fixed !important;
        top: 75px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: calc(100% - 30px) !important;
        max-width: 400px !important;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        border-radius: 16px;
        z-index: 1031 !important;
    }
    
    /* Espacement pour le header fixe */
    .main-content-container {
        margin-top: 70px;
    }
}

/* Très petits écrans */
@media (max-width: 575.98px) {
    .container-fluid {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    
    .logo-wrapper {
        width: 32px;
        height: 32px;
    }
    
    .navbar-brand .fw-bold {
        font-size: 1rem;
    }
    
    .user-dropdown-btn {
        padding: 0.35rem 0.7rem !important;
    }
    
    .dropdown-menu {
        width: calc(100% - 20px) !important;
        border-radius: 14px;
    }
}

/* =======================================================
   CONTENU PRINCIPAL
======================================================= */
.main-content-container {
    min-height: calc(100vh - 70px);
    padding: 1.5rem;
    background: var(--light);
}

@media (max-width: 991.98px) {
    .main-content-container {
        padding: 1rem;
        min-height: calc(100vh - 70px);
    }
}

/* =======================================================
   ACCESSIBILITÉ
======================================================= */
@media (prefers-reduced-motion: reduce) {
    .dropdown-arrow,
    .dropdown-menu,
    .dropdown-item,
    .dropdown-overlay {
        transition: none !important;
        animation: none !important;
    }
}

/* =======================================================
   IMPRESSION
======================================================= */
@media print {
    .header-dashboard,
    .dropdown-overlay {
        display: none !important;
    }
    
    .main-content-container {
        margin-top: 0;
        padding: 0;
        min-height: auto;
    }
}
</style>