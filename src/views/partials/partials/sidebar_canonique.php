<?php
// ==================================================
// CONTEXTE GLOBAL
// ==================================================
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$requestUri  = $_SERVER['REQUEST_URI'] ?? '';
$hasHash = strpos($requestUri, '#') !== false;
// Normalized request path (without query or fragment)
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

// ==================================================
// RÔLES UTILISATEUR
// ==================================================
$userRole      = $_SESSION['role'] ?? '';
$isSuperAdmin  = ($userRole === 'super_admin');
$isAdmin       = in_array($userRole, ['admin', 'super_admin'], true);

// ==================================================
// SECTION ACTIVE (PAGE + ANCRE)
// ==================================================
$activeAnchor = '';
if (strpos($requestUri, '#') !== false) {
    $activeAnchor = substr($requestUri, strpos($requestUri, '#') + 1);
}

// ==================================================
// FONCTIONS UTILITAIRES SÉCURISÉES
// ==================================================
if (!function_exists('isActivePage')) {
    function isActivePage(string $page, string $currentPage): string
    {
        // global request URI/path available to decide active state
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '';

        // Exact filename match
        if ($page === $currentPage) return 'active';

        // If the current request path contains the target page (covers includes, rewritten URLs)
        if ($requestPath && strpos($requestPath, $page) !== false) return 'active';

        // If the page was requested via query param (e.g., index.php?page=admin_dashboard_unifie.php)
        if (isset($_GET['page']) && basename((string)$_GET['page']) === $page) return 'active';

        return '';
    }
}

if (!function_exists('isActiveAnchor')) {
    function isActiveAnchor(string $anchor, string $activeAnchor): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Direct anchor match using previously parsed active anchor
        if ($anchor !== '' && $anchor === $activeAnchor) return 'active';

        // If the request URI contains the anchor fragment
        if ($anchor !== '' && strpos($requestUri, '#'.$anchor) !== false) return 'active';

        return '';
    }
}

// Unified check for sidebar nav items: target page OR anchor OR path-containing anchor
if (!function_exists('isActiveNavItem')) {
    function isActiveNavItem(?string $targetPage, ?string $anchor): string
    {
        global $currentPage, $requestPath, $activeAnchor, $requestUri;

        if ($targetPage && isActivePage($targetPage, $currentPage) === 'active') return 'active';
        if ($anchor && isActiveAnchor($anchor, $activeAnchor) === 'active') return 'active';

        // If URL path contains anchor or page slug
        if ($anchor && $requestPath && strpos($requestPath, $anchor) !== false) return 'active';
        if ($targetPage && $requestPath && strpos($requestPath, $targetPage) !== false) return 'active';

        // Last resort: the raw request URI contains fragment or slug
        if ($anchor && $requestUri && strpos($requestUri, '#'.$anchor) !== false) return 'active';

        return '';
    }
}
?>


<aside class="sidebar" id="sidebar">

    <!-- NAVIGATION -->
    <nav class="sidebar-nav">

        <!-- DASHBOARD -->
          <a href="admin_dashboard_unifie.php"
              title="Tableau de bord"
              class="<?= (!$hasHash ? 'active' : '') ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Tableau de bord</span>
        </a>

        <!-- ADMINS (SUPER ADMIN) -->
        <?php if ($isSuperAdmin): ?>
          <a href="admin_dashboard_unifie.php#admins"
              title="Admins"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'admins') ?>">
            <i class="fas fa-user-shield"></i>
            <span>Admins</span>
        </a>
        <?php endif; ?>

        <!-- EMPLOYÉS -->
          <a href="admin_dashboard_unifie.php#employes"
              title="Employés"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'employes') ?>">
            <i class="fas fa-users"></i>
            <span>Employés</span>
        </a>

        <!-- POINTAGE -->
          <a href="admin_dashboard_unifie.php#pointage"
              title="Pointages"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'pointage') ?>">
            <i class="fas fa-qrcode"></i>
            <span>Pointages</span>
        </a>

        <!-- HEURES -->
          <a href="admin_dashboard_unifie.php#heures"
              title="Heures"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'heures') ?>">
            <i class="fas fa-clock"></i>
            <span>Heures</span>
        </a>

        <!-- RETARDS -->
          <a href="admin_dashboard_unifie.php#retard"
              title="Retards"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'retard') ?>">
            <i class="fas fa-hourglass-half"></i>
            <span>Retards</span>
        </a>

        <!-- DEMANDES -->
          <a href="admin_dashboard_unifie.php#demandes"
              title="Demandes"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'demandes') ?>">
            <i class="fas fa-list-alt"></i>
            <span>Demandes</span>
        </a>

        <!-- CALENDRIER -->
          <a href="admin_dashboard_unifie.php#calendrier"
              title="Calendrier"
              class="<?= isActiveNavItem('admin_dashboard_unifie.php', 'calendrier') ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendrier</span>
        </a>

        <!-- PARAMÈTRES -->
          <a href="admin_settings.php"
              title="Paramètres"
              class="<?= isActiveNavItem('admin_settings.php', null) ?>">
            <i class="fas fa-cog"></i>
            <span>Paramètres</span>
        </a>

        <!-- DÉCONNEXION -->
        <a href="logout.php" title="Déconnexion" class="logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Déconnexion</span>
        </a>

    </nav>
</aside>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const links = document.querySelectorAll('.sidebar-nav a');

    function updateActive() {
        const hash = window.location.hash.replace('#', '');
        const path = window.location.pathname || '';
        const currentFile = path.split('/').pop();

        // 1. Nettoyage total
        links.forEach(l => l.classList.remove('active'));

        // 2. If a section is targeted via hash, prefer links with that anchor first
        if (hash) {
            const anchorMatch = Array.from(links).find(l => (l.getAttribute('href') || '').includes('#' + hash));
            if (anchorMatch) { anchorMatch.classList.add('active'); return; }
        }

        // 3. If a link matches the current pathname (prefer exact file match without hashes)
        if (currentFile) {
            // Prefer links that point directly to the file (no fragment)
            const byFileNoHash = Array.from(links).find(l => {
                try {
                    const href = l.getAttribute('href') || '';
                    if (href.includes('#')) return false; // skip anchors here
                    const hrefFile = href.split('/').pop().split('?')[0];
                    return hrefFile === currentFile;
                } catch (e) { return false; }
            });
            if (byFileNoHash) { byFileNoHash.classList.add('active'); return; }

            // Fallback: accept links that contain the file name even if they include an anchor
            const byFileWithHash = Array.from(links).find(l => {
                try {
                    const href = l.getAttribute('href') || '';
                    const hrefFile = href.split('/').pop().split('?')[0].split('#')[0];
                    return hrefFile === currentFile;
                } catch (e) { return false; }
            });
            if (byFileWithHash) { byFileWithHash.classList.add('active'); return; }

            // 3b. If no exact file match, try slug matching (useful for pages like demandes.php -> link containing 'demandes')
            const slug = currentFile.replace(/\.[^/.]+$/, ''); // remove extension
            if (slug) {
                const bySlug = Array.from(links).find(l => {
                    try {
                        const href = (l.getAttribute('href') || '').toLowerCase();
                        return href.includes(slug.toLowerCase());
                    } catch (e) { return false; }
                });
                if (bySlug) { bySlug.classList.add('active'); return; }
            }
        }

        // 3. If a section is targeted via hash
        if (hash) {
            const target = document.querySelector(`.sidebar-nav a[href*="#${hash}"]`);
            if (target) {
                target.classList.add('active');
                return;
            }
        }

        // 4. Fallback → Dashboard
        const dashboard = document.querySelector('.sidebar-nav a[href$="admin_dashboard_unifie.php"]');
        dashboard?.classList.add('active');
    }

    // Au chargement
    updateActive();

    // Au clic
    links.forEach(link => {
        link.addEventListener('click', () => {
            setTimeout(updateActive, 0);
        });
    });

    // Si l’URL change (#hash)
    window.addEventListener('hashchange', updateActive);
});
</script>


<!-- ================================================== -->
<!-- STYLE SIDEBAR -->
<!-- ================================================== -->
<style>
/* Base sidebar layout */
.sidebar {
    width: 260px;
    height: calc(100vh - 70px);
    top: 70px;
    background: #ffffff;
    border-right: 1px solid #eaeaea;
    position: fixed;
    left: 0;
    display: flex;
    flex-direction: column;
    z-index: 900; /* below header (header uses 1000) */
    transition: width 0.25s ease, transform 0.25s ease;
    box-shadow: 2px 0 8px rgba(0,0,0,0.05);
    overflow: hidden;
}
.sidebar-nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 10px 0;
    gap: 4px;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin: 2px 8px;
    border-radius: 0px;
    color: #5d6d7e;
    text-decoration: none;
    font-size: 14px;
    border-left: 3px solid transparent;
    transition: background .15s ease, color .15s ease, padding .15s ease;
    overflow: hidden;
}

.sidebar-nav a i {
    width: 28px;
    min-width: 28px;
    text-align: center;
    font-size: 18px;
    color: inherit;
}

.sidebar-nav a span {
    display: inline-block;
    vertical-align: middle;
    white-space: nowrap;
    transition: opacity .2s ease, transform .2s ease;
}

.sidebar-nav a.active {
    background: #ebf5fb;
    color: #2980b9;
    font-weight: 500;
    border-left-color: #2980b9;
}

.sidebar-nav .logout {
    margin-top: auto;
    color: #e74c3c;
    border-top: 1px solid #eaeaea;
}

/* Collapsed state: only show icons */
.sidebar.collapsed {
    width: 70px;
}

.sidebar.collapsed .logo span,
.sidebar.collapsed .sidebar-nav a span {
    opacity: 0;
    transform: translateX(-6px);
    width: 0 !important;
    margin: 0 !important;
    pointer-events: none;
}

.sidebar.collapsed .sidebar-top {
    padding: 10px 8px;
}

.sidebar.collapsed .logo {
    justify-content: center;
}

.sidebar.collapsed .logo img {
    margin-right: 0;
}

.sidebar.collapsed .sidebar-nav a {
    justify-content: center;
    padding: 10px 6px;
}

.sidebar.collapsed .sidebar-nav a i {
    margin: 0;
}

.sidebar.collapsed .sidebar-nav a.active {
    border-left-color: transparent;
}

/* Main content shift when sidebar present */
.main-content {
    margin-left: 260px;
    transition: margin-left 0.25s ease;
}

.main-content.sidebar-collapsed {
    margin-left: 70px;
}

/* Mobile behaviour */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
        position: fixed;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0 !important;
    }
}
</style>
