<?php
/**
 * Dashboard administrateur unifié
 * Utilise le service centralisé AdminService et les partials canoniques
 */

require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
require_once 'src/services/AdminService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

// Vérification de l'authentification admin
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    header("Location: login.php");
    exit();
}

$isAdmin = true;
$pageTitle = 'Dashboard Administrateur - Xpert Pro';
$pageHeader = 'Dashboard Administrateur';
$pageDescription = 'Vue d\'ensemble du système de pointage';
$bodyClass = $isAdmin ? 'has-sidebar' : '';

$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SUPER_ADMIN;

// Backwards-compatible variable for views that use camelCase
$isSuperAdmin = $is_super_admin;

// Initialisation du service centralisé
$adminService = new AdminService($pdo);

// Récupération des données via le service
$dashboardData = $adminService->getDashboardData();

// Extraction des données pour les vues avec valeurs par défaut
$stats = $dashboardData['stats'] ?? [];
$employes = $dashboardData['employes'] ?? [];
$admins = $dashboardData['admins'] ?? [];
$demandesData = $dashboardData['demandes'] ?? ['demandes' => [], 'stats' => []];
$demandes = $demandesData['demandes'] ?? [];
$stats_demandes = $demandesData['stats'] ?? ['total' => 0, 'en_attente' => 0, 'approuve' => 0, 'rejete' => 0];
$retards = $dashboardData['retards'] ?? [];
$temps_totaux = $dashboardData['temps_totaux'] ?? [];

// --- POINTAGES : récupération paginée avec département ---
// selected date filter (fall back to today when missing or empty)
$selectedDate = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// optional department filter
$departementFilter = !empty($_GET['departement']) ? $_GET['departement'] : null;

$page_pointage = isset($_GET['page_pointage']) ? max(1, (int)$_GET['page_pointage']) : 1;
$perPagePointage = 10;
$pointagesData = $adminService->getPointagesPaged($selectedDate, $page_pointage, $perPagePointage, $departementFilter);
$pointages = $pointagesData['items'] ?? [];
$pointages_total = (int)($pointagesData['total'] ?? count($pointages));
$pointages_total_pages = max(1, (int)ceil($pointages_total / $perPagePointage));

// --- RETARDS : date + departement filters ---
$page_retard = isset($_GET['page_retard']) ? max(1, (int)$_GET['page_retard']) : 1;
$perPageRetard = 50; // show up to 50 per page by default
$retardDate = !empty($_GET['date_retard']) ? $_GET['date_retard'] : date('Y-m-d');
$dep_retard = !empty($_GET['dep_retard']) ? $_GET['dep_retard'] : null;
$retards = $adminService->getRetards($retardDate, $page_retard, $perPageRetard, $dep_retard);

// --- DEMANDES : pagination server-side ---
$page_demandes = isset($_GET['page_demandes']) ? max(1, (int)$_GET['page_demandes']) : 1;
$perPageDemandes = 10;
$demandesPaged = $adminService->getDemandesPaged($page_demandes, $perPageDemandes);
$demandes = $demandesPaged['items'] ?? [];
$stats_demandes = $demandesPaged['stats'] ?? ['total'=>0,'en_attente'=>0,'approuve'=>0,'rejete'=>0];
$demandes_total = (int)($demandesPaged['total'] ?? count($demandes));
$demandes_total_pages = max(1, (int)ceil($demandes_total / $perPageDemandes));

// --- TEMPS TOTAUX : pagination server-side ---
$page_heures = isset($_GET['page_heures']) ? max(1, (int)$_GET['page_heures']) : 1;
$perPageHeures = 10;
$heuresPaged = $adminService->getTempsTotauxPaged($page_heures, $perPageHeures);
$temps_totaux = $heuresPaged['items'] ?? [];
$temps_totaux_total = (int)($heuresPaged['total'] ?? count($temps_totaux));
$temps_totaux_total_pages = max(1, (int)ceil($temps_totaux_total / $perPageHeures));


// Gestion de la recherche globale
$searchTerm = $_GET['search'] ?? '';
$highlightSearch = !empty($searchTerm);

// Pagination pour les employés
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$totalEmployes = count($employes);
$total_pages = ceil($totalEmployes / $perPage);
$employes = array_slice($employes, ($page - 1) * $perPage, $perPage);

// Variables supplémentaires pour les partials
// liste des départements pour les filtres
$liste_departements = $pdo->query("SELECT DISTINCT departement FROM employes WHERE departement IS NOT NULL ORDER BY departement")->fetchAll(PDO::FETCH_COLUMN);

$additionalCSS = ['assets/css/admin.css'];
?>


<?php include 'partials/header.php'; ?>
<?php include 'src/views/partials/sidebar_canonique.php'; ?>

<div class="row">
    <div class="container-fluid p-0">
        <div class="row g-0 flex-nowrap" style="min-height:100vh;">
            <!-- Main Content -->
            <main class="main-content">
                <?php if ($highlightSearch): ?>
                <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-search me-2"></i>
                    <strong>Recherche :</strong> "<?= htmlspecialchars($searchTerm) ?>" - Résultats mis en évidence dans les tableaux
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div id="dashboard" class="panel-section">
                <div class="dashboard-header card shadow-sm mb-4 p-4 bg-white rounded-4 border-0" style="margin-top: 0 !important;">
                    
                    <!-- Cards statistiques RH connectées à la base -->
                    <div class="row g-1 mb-2" style="margin-bottom:15px !important; margin-top:15px !important;">
                        <div class="col-md-6 col-lg-3">
                            <div class="card stat-card total h-100" style="border-radius: 8px; min-height: 50px; background: rgba(67,97,238,0.18); box-shadow: 0 1px 4px rgba(67,97,238,0.08); margin-bottom:10px;">
                                <div class="card-body text-center py-2 px-1">
                                    <div class="mb-1">
                                        <i class="fas fa-users" style="font-size:1.3rem;color:#0672e4;"></i>
                                    </div>
                                    <div class="stat-count fw-bold" style="font-size:1.3rem;color:#0672e4;" id="count-employes"><?= $stats['total_employes'] ?></div>
                                    <div class="text-primary" style="font-size:0.85rem;opacity:0.8;">Total employés</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card stat-card approuve h-100" style="border-radius: 8px; min-height: 50px; background: rgba(76,201,240,0.18); box-shadow: 0 1px 4px rgba(76,201,240,0.08); margin-bottom:10px;">
                                <div class="card-body text-center py-2 px-1">
                                    <div class="mb-1">
                                        <i class="fas fa-user-check" style="font-size:1.3rem;color:#4cc9f0;"></i>
                                    </div>
                                    <div class="stat-count fw-bold" style="font-size:1.3rem;color:#4cc9f0;" id="count-presents"><?= $stats['present_today'] ?></div>
                                    <div class="text-info" style="font-size:0.85rem;opacity:0.8;">Présents aujourd'hui</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card stat-card en_attente h-100" style="border-radius: 8px; min-height: 50px; background: rgba(248,150,30,0.18); box-shadow: 0 1px 4px rgba(248,150,30,0.08); margin-bottom:10px;">
                                <div class="card-body text-center py-2 px-1">
                                    <div class="mb-1">
                                        <i class="fas fa-user-times" style="font-size:1.3rem;color:#f8961e;"></i>
                                    </div>
                                    <div class="stat-count fw-bold" style="font-size:1.3rem;color:#f8961e;" id="count-absents"><?= $stats['absents_today'] ?></div>
                                    <div class="text-warning" style="font-size:0.85rem;opacity:0.8;">Absents aujourd'hui</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="card stat-card rejete h-100" style="border-radius: 8px; min-height: 50px; background: rgba(249,65,68,0.18); box-shadow: 0 1px 4px rgba(249,65,68,0.08); margin-bottom:10px;">
                                <div class="card-body text-center py-2 px-1">
                                    <div class="mb-1">
                                        <i class="fas fa-clock" style="font-size:1.3rem;color:#f94144;"></i>
                                    </div>
                                    <div class="stat-count fw-bold" style="font-size:1.3rem;color:#f94144;" id="count-retards"><?= $stats['retards_today'] ?></div>
                                    <div class="text-danger" style="font-size:0.85rem;opacity:0.8;">Retards du jour</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>
                    </div>

                    <!-- PANELS DYNAMIQUES -->
                    <div class="dashboard-content" style="margin-top:15px;">
                        
                        <!-- Panel Pointage -->
                        <?php include 'src/views/pages/panel_pointage.php'; ?>
                        
                        <!-- Panel Heures -->
                        <?php include 'src/views/pages/panel_heures.php'; ?>

                        <!-- Panel Temps Totaux (liste paginée) -->
                        <div id="temps_totaux" class="panel-section" style="display:none;">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Temps totaux par employé</h5>
                                    <div class="small text-muted">Affichage paginé</div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($temps_totaux)): ?>
                                    <div class="table-responsive" style="max-height:60vh; overflow-y:auto;">
                                        <table class="table table-sm table-hover" id="temps-totaux-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Prénom</th>
                                                    <th>Nom</th>
                                                    <th>Email</th>
                                                    <th class="text-center">Temps total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($temps_totaux as $t): ?>
                                                <?php $raw_tt = $t['total_travail'] ?? '00:00:00'; $parts_tt = explode(':', $raw_tt); $display_tt = ($parts_tt[0] ?? '00') . ':' . ($parts_tt[1] ?? '00'); ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($t['prenom'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($t['nom'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($t['email'] ?? '') ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($display_tt) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination simple -->
                                    <?php if ($temps_totaux_total_pages > 1): ?>
                                    <nav aria-label="Temps totaux pagination" class="mt-3">
                                        <ul class="pagination pagination-sm">
                                            <?php for ($p = 1; $p <= $temps_totaux_total_pages; $p++): ?>
                                                <li class="page-item <?= $p === $page_heures ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page_heures=<?= $p ?>#temps_totaux"><?= $p ?></a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>

                                    <?php else: ?>
                                        <div class="alert alert-info">Aucune donnée disponible.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Panel Demandes -->
                        <?php include 'src/views/pages/panel_demandes.php'; ?>
                        
                        <!-- Panel Employés -->
                        <?php include 'src/views/pages/panel_employes.php'; ?>
                        
                        <!-- Panel Admins -->
                        <?php include 'src/views/pages/panel_admins.php'; ?>
                        
                        <!-- Panel Retards -->
                        <?php include 'src/views/pages/panel_retards.php'; ?>
                        
                        <!-- Panel Calendrier -->
                        <div id="calendrier" class="panel-section" style="display:none;">
                            <div class="container my-4">
                                <div class="filter-card shadow-sm p-4 mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="mb-0">Calendrier des événements</h4>
                                        <button type="button" class="btn btn-primary btn-sm" id="addEventBtn">
                                            <i class="fas fa-plus me-1"></i> Ajouter un événement
                                        </button>
                                    </div>
                                    <p class="text-muted mb-3" style="font-size:0.98em;">
                                        <i class="fas fa-info-circle me-1"></i> Cliquez sur une date pour ajouter un événement (réunion, congé, formation, autre).<br>
                                        <i class="fas fa-mouse-pointer me-1"></i> Cliquez sur un événement pour voir le détail.<br>
                                        <i class="fas fa-arrows-alt me-1"></i> Glissez-déposez pour déplacer un événement.
                                    </p>
                                    <div id="calendar-admin"></div>
                                    <div id="calendar-loading" class="text-center my-3" style="display:none;">
                                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal ajout/édition événement -->
                            <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form id="eventForm">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="eventModalLabel">Nouvel événement</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="id" id="evt-id">
                                                <div class="mb-3">
                                                    <label class="form-label">Titre</label>
                                                    <input type="text" class="form-control" name="titre" id="evt-title" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Type</label>
                                                    <select class="form-select" name="type" id="evt-type" required>
                                                        <option value="reunion">Réunion</option>
                                                        <option value="congé">Congé</option>
                                                        <option value="formation">Formation</option>
                                                        <option value="autre">Autre</option>
                                                    </select>
                                                </div>
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label">Début</label>
                                                        <input type="datetime-local" class="form-control" name="start_date" id="evt-start" required>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Fin</label>
                                                        <input type="datetime-local" class="form-control" name="end_date" id="evt-end" required>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="description" id="evt-desc" rows="3"></textarea>
                                                </div>
                                                <div class="mt-3">
                                                    <label class="form-label">Employé (optionnel)</label>
                                                    <input type="number" class="form-control" name="employe_id" id="evt-employe-id" placeholder="ID employé (ou vide)">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-danger d-none" id="deleteEventBtn">Supprimer</button>
                                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- Scripts communs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
// ============================================
// DASHBOARD PANEL MANAGEMENT SYSTEM
// ============================================

class PanelManager {
    constructor() {
        this.panels = ["pointage", "retard", "heures", "employes", "demandes"<?php if ($is_super_admin): ?>, "admins"<?php endif; ?>, "calendrier"];
        this.validPanels = new Set(this.panels);
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializePanel();
    }

    setupEventListeners() {
        // Navigation par boutons
        document.addEventListener('DOMContentLoaded', () => {
            this.setupButtonListeners();
            this.setupDateFilter();
            this.setupSearchFunctionality();
            this.setupClickableRows();
            this.animateCounters();
            this.setupHighlighting();
        });

        // Navigation par hash/URL
        window.addEventListener('hashchange', () => this.handleHashChange());
        window.addEventListener('popstate', () => this.handleHashChange());
        
        // Événement personnalisé pour les panels
        window.addEventListener('panel:shown', (e) => this.onPanelShown(e));
    }

    setupButtonListeners() {
        document.querySelectorAll('.btn-nav, [data-panel]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const panelId = btn.dataset.panel || 
                               btn.getAttribute('href')?.replace('#', '') || 
                               btn.id?.replace('Btn', '').toLowerCase();
                
                if (panelId && this.validPanels.has(panelId)) {
                    this.switchPanel(panelId, btn);
                }
            });
        });
    }

    setupDateFilter() {
        const dateInput = document.getElementById('dateInput');
        const dateFilterForm = document.getElementById('dateFilterForm');
        
        if (dateInput && dateFilterForm) {
            dateInput.addEventListener('change', () => {
                dateFilterForm.classList.add('submitting');
                setTimeout(() => dateFilterForm.submit(), 100);
            });
        }
    }

    async switchPanel(panelId, btn = null) {
        try {
            console.debug(`Switching to panel: ${panelId}`);
            
            // Masquer tous les panels
            this.panels.forEach(id => {
                const panel = document.getElementById(id);
                if (panel) {
                    panel.style.display = 'none';
                    panel.classList.remove('active-panel');
                }
            });

            // Afficher le panel cible
            const activePanel = document.getElementById(panelId);
            if (!activePanel) {
                throw new Error(`Panel "${panelId}" not found`);
            }

            activePanel.style.display = 'block';
            activePanel.classList.add('active-panel');
            
            // Mettre à jour l'interface
            this.updateActiveButton(btn, panelId);
            this.updateUrl(panelId);
            this.persistPanel(panelId);
            
            // Déclencher l'événement panel:shown
            window.dispatchEvent(new CustomEvent('panel:shown', { 
                detail: { panelId, timestamp: Date.now() }
            }));

        } catch (error) {
            console.error('Panel switch error:', error);
            this.showError(`Impossible d'afficher le panel "${panelId}"`);
        }
    }

    updateActiveButton(btn, panelId) {
        // Réinitialiser tous les boutons de navigation relatifs aux panels
        // inclut les boutons JS (.btn-nav) et les ancres server-side (.sidebar-simple nav a)
        document.querySelectorAll('.btn-nav, .sidebar-simple nav a').forEach(b => {
            b.classList.remove('active');
            // ne pas réinitialiser tous les styles globaux, seulement les styles propres à l'active
            b.style.backgroundColor = '';
            b.style.color = '';
            b.style.borderLeft = '';
            b.style.fontWeight = '';
            b.style.transition = '';
        });

        // Trouver le bouton correspondant si non fourni
        if (!btn) {
            btn = document.querySelector(`[data-panel="${panelId}"]`) ||
                  document.querySelector(`a[href="#${panelId}"]`) ||
                  document.querySelector(`#${panelId}Btn`);
        }

        // Appliquer le style actif
        if (btn) {
            btn.classList.add('active');
            Object.assign(btn.style, {
                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                color: '#ffff',
                borderLeft: '3px solid #0d6efd',
                fontWeight: '600',
                transition: 'all 0.3s ease'
            });
        }
    }

    updateUrl(panelId) {
        const newUrl = window.location.pathname + '#' + panelId;
        if (window.location.hash !== '#' + panelId) {
            window.history.replaceState(null, '', newUrl);
        }
    }

    persistPanel(panelId) {
        try {
            sessionStorage.setItem('lastPanel', panelId);
            localStorage.setItem('preferredPanel', panelId);
        } catch (e) {
            console.warn('Storage not available:', e);
        }
    }

    initializePanel() {
        // Attendre que le DOM soit complètement prêt
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.loadInitialPanel());
        } else {
            setTimeout(() => this.loadInitialPanel(), 50);
        }
    }

    loadInitialPanel() {
        let panel = 'pointage';
        
        // Priorité 1: Hash URL
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            if (this.validPanels.has(hash)) {
                panel = hash;
            }
        }
        // Priorité 2: Session storage
        else if (sessionStorage.getItem('lastPanel')) {
            const last = sessionStorage.getItem('lastPanel');
            if (this.validPanels.has(last)) {
                panel = last;
            }
        }
        // Priorité 3: Local storage (préférence utilisateur)
        else if (localStorage.getItem('preferredPanel')) {
            const preferred = localStorage.getItem('preferredPanel');
            if (this.validPanels.has(preferred)) {
                panel = preferred;
            }
        }

        console.debug(`Initializing panel: ${panel}`);
        this.switchPanel(panel);
    }

    handleHashChange() {
        const hash = window.location.hash.substring(1);
        if (hash && this.validPanels.has(hash)) {
            this.switchPanel(hash);
        }
    }

    onPanelShown(event) {
        console.debug(`Panel shown: ${event.detail.panelId}`);
        // Ici vous pouvez ajouter du code spécifique à chaque panel
        // Par exemple, initialiser un calendrier si c'est le panel calendrier
        if (event.detail.panelId === 'calendrier') {
            this.initializeCalendar();
        }
    }

    initializeCalendar() {
        // Initialisation spécifique au calendrier
        if (typeof FullCalendar !== 'undefined') {
            // Code d'initialisation du calendrier
        }
    }

    showError(message) {
        const existingError = document.querySelector('.panel-error');
        if (existingError) existingError.remove();

        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-warning alert-dismissible fade show panel-error mt-3';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.dashboard-content') || document.body;
        container.prepend(errorDiv);

        setTimeout(() => {
            errorDiv.classList.remove('show');
            setTimeout(() => errorDiv.remove(), 300);
        }, 5000);
    }

    // ============================================
    // SEARCH FUNCTIONALITY
    // ============================================

    setupSearchFunctionality() {
        // Recherche employés
        this.setupTableSearch('employeeSearch', 'employes-table');
        this.setupTableSearch('adminSearch', 'admins-table');
        
        // Recherche générique pour tous les tableaux avec data-searchable
        document.querySelectorAll('input[data-search-target]').forEach(input => {
            const target = input.dataset.searchTarget;
            this.setupTableSearch(input.id, target);
        });
    }

    setupTableSearch(inputId, tableId) {
        const searchInput = document.getElementById(inputId);
        const table = document.getElementById(tableId);

        if (searchInput && table) {
            // Debounce pour meilleures performances
            let timeout;
            searchInput.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.filterTable(table, searchInput.value.toLowerCase());
                }, 300);
            });

            // Reset avec bouton
            const resetBtn = searchInput.nextElementSibling?.querySelector('.search-reset');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    this.filterTable(table, '');
                });
            }
        }
    }

    filterTable(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matches = searchTerm === '' || text.includes(searchTerm);
            row.style.display = matches ? '' : 'none';
            row.classList.toggle('search-match', matches && searchTerm !== '');
            
            if (matches) visibleCount++;
        });

        // Mettre à jour le compteur de résultats
        const counter = table.parentElement.querySelector('.search-results');
        if (counter) {
            counter.textContent = `${visibleCount} résultat(s)`;
            counter.style.display = visibleCount === rows.length ? 'none' : 'block';
        }
    }

    // ============================================
    // TABLE FUNCTIONALITY
    // ============================================

    setupClickableRows() {
        document.querySelectorAll('table tr[data-href]').forEach(row => {
            row.addEventListener('click', (e) => {
                // Ignorer les clics sur les liens, boutons et inputs
                if (e.target.tagName === 'A' || 
                    e.target.tagName === 'BUTTON' || 
                    e.target.tagName === 'INPUT' ||
                    e.target.closest('a') || 
                    e.target.closest('button') || 
                    e.target.closest('.no-click')) {
                    return;
                }

                const url = row.dataset.href;
                if (url) {
                    window.location.href = url;
                }
            });
            
            // Effet hover
            row.style.cursor = 'pointer';
            row.addEventListener('mouseenter', () => {
                row.style.backgroundColor = 'rgba(13, 110, 253, 0.06)';
            });
            row.addEventListener('mouseleave', () => {
                row.style.backgroundColor = '';
            });
        });
    }

    // ============================================
    // EXPORT FUNCTIONALITY
    // ============================================

    exportPDF(tableId) {
        try {
            if (typeof jsPDF === 'undefined') {
                throw new Error('jsPDF library not loaded');
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });

            doc.text(`Export - ${new Date().toLocaleDateString()}`, 14, 16);
            
            doc.autoTable({
                html: `#${tableId}`,
                margin: { top: 30 },
                styles: { fontSize: 8 },
                headStyles: { fillColor: [22, 160, 133] }
            });

            doc.save(`${tableId}_${Date.now()}.pdf`);
        } catch (error) {
            console.error('PDF export error:', error);
            this.showError('Erreur lors de l\'export PDF');
        }
    }

    exportExcel(tableId) {
        try {
            if (typeof XLSX === 'undefined') {
                throw new Error('SheetJS library not loaded');
            }

            const table = document.getElementById(tableId);
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            
            XLSX.utils.book_append_sheet(wb, ws, "Export");
            XLSX.writeFile(wb, `${tableId}_${Date.now()}.xlsx`);
        } catch (error) {
            console.error('Excel export error:', error);
            this.showError('Erreur lors de l\'export Excel');
        }
    }

    // ============================================
    // ANIMATIONS & EFFECTS
    // ============================================

    animateCounters() {
        const counters = [
            { id: 'count-demandes-total', color: '#0672e4' },
            { id: 'count-demandes-attente', color: '#f8961e' },
            { id: 'count-demandes-approuve', color: '#4cc9f0' },
            { id: 'count-demandes-rejete', color: '#f94144' }
        ];

        counters.forEach(counter => {
            const el = document.getElementById(counter.id);
            if (!el) return;

            const endValue = parseInt(el.dataset.value || el.textContent.replace(/\D/g, '') || 0);
            this.animateValue(el, 0, endValue, 1200, counter.color);
        });
    }

    animateValue(element, start, end, duration, color) {
        const startTime = Date.now();
        const endTime = startTime + duration;

        const update = () => {
            const now = Date.now();
            const progress = Math.min((now - startTime) / duration, 1);
            const current = Math.floor(start + (end - start) * this.easeOutQuad(progress));

            element.textContent = current.toLocaleString();
            element.style.color = color;

            if (now < endTime) {
                requestAnimationFrame(update);
            } else {
                element.textContent = end.toLocaleString();
            }
        };

        requestAnimationFrame(update);
    }

    easeOutQuad(t) {
        return t * (2 - t);
    }

    setupHighlighting() {
        <?php if ($highlightSearch && !empty($searchTerm)): ?>
        const searchTerm = '<?= addslashes($searchTerm) ?>'.toLowerCase();
        if (searchTerm.trim()) {
            this.highlightText(searchTerm);
            this.scrollToFirstMatch();
        }
        <?php endif; ?>
    }

    highlightText(searchTerm) {
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        const nodes = [];
        let node;
        while (node = walker.nextNode()) {
            if (node.textContent.toLowerCase().includes(searchTerm) &&
                !node.parentElement.closest('script, style, noscript')) {
                nodes.push(node);
            }
        }

        nodes.forEach(textNode => {
            const span = document.createElement('span');
            const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            span.innerHTML = textNode.textContent.replace(regex, '<mark class="search-highlight">$1</mark>');
            textNode.parentNode.replaceChild(span, textNode);
        });
    }

    scrollToFirstMatch() {
        const firstMatch = document.querySelector('.search-highlight');
        if (firstMatch) {
            setTimeout(() => {
                firstMatch.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest'
                });
                
                // Animation de pulse
                firstMatch.classList.add('pulse-highlight');
                setTimeout(() => firstMatch.classList.remove('pulse-highlight'), 2000);
            }, 500);
        }
    }
}

// ============================================
// INITIALIZATION
// ============================================

// Attendre que les bibliothèques externes soient chargées
window.addEventListener('load', () => {
    // Initialiser le gestionnaire de panels
    window.panelManager = new PanelManager();
    
    // Exposer les fonctions d'export globalement
    window.exportPDF = (tableId) => panelManager.exportPDF(tableId);
    window.exportExcel = (tableId) => panelManager.exportExcel(tableId);
});

// CSS à ajouter pour les effets
const style = document.createElement('style');
style.textContent = `
    .search-highlight {
        background-color: #fff3cd;
        padding: 1px 3px;
        border-radius: 3px;
        box-shadow: 0 0 0 1px rgba(0,0,0,0.1);
    }
    
    .pulse-highlight {
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
        70% { box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
    }
    
    .search-results {
        font-size: 0.875rem;
        color: #6c757d;
        font-style: italic;
        margin-top: 0.5rem;
    }
    
    .search-match {
        background-color: rgba(13, 110, 253, 0.05) !important;
    }
    
    .active-panel {
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .submitting {
        opacity: 0.7;
        pointer-events: none;
    }
`;
document.head.appendChild(style);
</script>

<?php
$additionalJS = ['assets/js/admin.js'];
include 'partials/footer.php';
?>
