<?php
/**
 * Panel Admins - Version Professionnelle & Responsive
 * Accessible uniquement au Super Admin
 */
?>

<div id="admins" class="panel-section" style="display:none;">

<?php if ($isSuperAdmin): ?>

    <div class="card profile-card shadow-sm border">
        <!-- HEADER -->
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-user-shield fa-lg text-primary"></i>
                <h4 class="mb-0 fw-bold">Gestion des Administrateurs</h4>
            </div>

            <a href="ajouter_admin.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Nouvel Admin
            </a>
        </div>

        <div class="card-body">

        <!-- ACTION BAR -->
        <div class="d-flex justify-content-between flex-wrap mb-3 gap-2">

            <!-- EXPORTS -->
            <div class="btn-group">
                <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('admins-table')">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportExcel('admins-table')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>

            <!-- SEARCH -->
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="adminSearch" class="form-control border-start-0" placeholder="Rechercher un admin...">
                </div>
            </div>
        </div>

        <?php if (!empty($admins)): ?>

        <!-- TABLE -->
        <div class="table-responsive" style="max-height: 70vh; overflow-y:auto;">
            <table class="table table-hover align-middle" id="admins-table">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th style="width:60px;">Profil</th>
                        <th>Administrateur</th>
                        <th>Contact</th>
                        <th>Adresse</th>
                        <th>Statut</th>
                        <th>Dernière activité</th>
                    </tr>
                </thead>

                <tbody id="adminsTbody">

                <?php 
                // Calcul du numéro de départ en fonction de la pagination
                $currentPage = max(1, (int)($_GET['page_admins'] ?? 1));
                $perPage = 10;
                $startNumber = (($currentPage - 1) * $perPage) + 1;
                $counter = $startNumber;
                
                foreach ($admins as $admin):
                    // CORRECTION : Vérifier si les clés existent avant d'y accéder
                    $adminId = isset($admin['id']) ? $admin['id'] : 0;
                    $adminPrenom = isset($admin['prenom']) ? $admin['prenom'] : '';
                    $adminNom = isset($admin['nom']) ? $admin['nom'] : '';
                    $adminRole = isset($admin['role']) ? $admin['role'] : 'admin';
                    $adminEmail = isset($admin['email']) ? $admin['email'] : '';
                    
                    // Récupération du téléphone depuis plusieurs champs possibles
                    $adminTelephone = '';
                    if (isset($admin['telephone']) && !empty($admin['telephone'])) {
                        $adminTelephone = $admin['telephone'];
                    } elseif (isset($admin['tel']) && !empty($admin['tel'])) {
                        $adminTelephone = $admin['tel'];
                    } elseif (isset($admin['phone']) && !empty($admin['phone'])) {
                        $adminTelephone = $admin['phone'];
                    } elseif (isset($admin['mobile']) && !empty($admin['mobile'])) {
                        $adminTelephone = $admin['mobile'];
                    } else {
                        $adminTelephone = 'N/A';
                    }
                    
                    $adminAdresse = isset($admin['adresse']) ? $admin['adresse'] : (isset($admin['address']) ? $admin['address'] : 'Non renseignée');
                    $adminLastActivity = isset($admin['last_activity']) ? $admin['last_activity'] : null;
                    $adminPhoto = isset($admin['photo']) ? $admin['photo'] : null;
                    $adminStatus = isset($admin['status']) ? $admin['status'] : 'active';

                    if (!$isSuperAdmin && $adminRole === ROLE_SUPER_ADMIN) continue;

                    // CORRECTION : Vérifier si les premières lettres existent
                    $firstLetterPrenom = !empty($adminPrenom) ? $adminPrenom[0] : '?';
                    $firstLetterNom = !empty($adminNom) ? $adminNom[0] : '?';
                    $initiale = strtoupper($firstLetterPrenom) . strtoupper($firstLetterNom);
                    
                    // CORRECTION : Logique de statut améliorée
                    $isActive = false;
                    $statusText = 'Inactif';
                    $statusClass = 'bg-secondary';
                    
                    if (isset($adminStatus) && $adminStatus === 'active') {
                        $isActive = true;
                        $statusText = 'Actif';
                        $statusClass = 'bg-success';
                    } else if (!empty($adminLastActivity) && $adminLastActivity !== '0000-00-00 00:00:00') {
                        $lastActivityTime = strtotime($adminLastActivity);
                        if ($lastActivityTime !== false) {
                            $isActive = $lastActivityTime > strtotime('-30 minutes');
                            $statusText = $isActive ? 'Actif' : 'Inactif';
                            $statusClass = $isActive ? 'bg-success' : 'bg-secondary';
                        }
                    } else {
                        // Par défaut, les comptes sont actifs
                        $isActive = true;
                        $statusText = 'Actif';
                        $statusClass = 'bg-success';
                    }

                    // Récupération photo ou fallback sur initiales
                    $photo = !empty($adminPhoto) ? htmlspecialchars($adminPhoto) : null;
                    
                    // Formatage de la dernière activité
                    $lastActivityFormatted = 'Jamais';
                    if (!empty($adminLastActivity) && $adminLastActivity !== '0000-00-00 00:00:00') {
                        $activityTime = strtotime($adminLastActivity);
                        if ($activityTime !== false) {
                            $lastActivityFormatted = date('d/m/Y H:i', $activityTime);
                        }
                    }
                    
                    // Formatage du téléphone si disponible
                    $telephoneDisplay = '';
                    if ($adminTelephone !== 'N/A' && !empty($adminTelephone)) {
                        // Formater le numéro pour l'affichage
                        $cleanPhone = preg_replace('/[^0-9]/', '', $adminTelephone);
                        if (strlen($cleanPhone) === 10) {
                            $telephoneDisplay = substr($cleanPhone, 0, 2) . ' ' . 
                                                substr($cleanPhone, 2, 2) . ' ' . 
                                                substr($cleanPhone, 4, 2) . ' ' . 
                                                substr($cleanPhone, 6, 2) . ' ' . 
                                                substr($cleanPhone, 8, 2);
                        } else {
                            $telephoneDisplay = $adminTelephone;
                        }
                    } else {
                        $telephoneDisplay = 'N/A';
                    }
                ?>

                <tr role="button" onclick="window.location.href='profil_admin.php?id=<?= $adminId ?>'">

                    <!-- NUMERO -->
                    <td class="text-muted small fw-bold"><?= $counter++ ?></td>

                    <!-- PROFILE IMAGE -->
                    <td>
                        <?php if ($photo): ?>
                            <img src="<?= $photo ?>" alt="Photo"
                                 class="rounded-circle shadow-sm"
                                 style="width:45px; height:45px; object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center shadow-sm"
                                 style="width:45px; height:45px; font-weight:600;">
                                <?= $initiale ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- NAME -->
                    <td>
                        <div class="fw-semibold mb-0">
                            <?= htmlspecialchars($adminPrenom . ' ' . $adminNom) ?>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars(ucfirst($adminRole)) ?></small>
                    </td>

                    <!-- CONTACT -->
                    <td>
                        <div class="mb-1">
                            <i class="fas fa-envelope me-1 text-primary"></i> 
                            <span class="small"><?= htmlspecialchars($adminEmail) ?></span>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-phone me-1 text-success"></i> 
                            <?= htmlspecialchars($telephoneDisplay) ?>
                        </div>
                    </td>

                    <!-- ADDRESS -->
                    <td>
                        <small class="text-muted">
                            <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                            <?= htmlspecialchars($adminAdresse) ?>
                        </small>
                    </td>

                    <!-- STATUS -->
                    <td>
                        <span class="badge rounded-pill <?= $statusClass ?>">
                            <?= $statusText ?>
                        </span>
                    </td>

                    <!-- ACTIVITY -->
                    <td>
                        <span class="text-muted small"><?= $lastActivityFormatted ?></span>
                    </td>

                </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php
            $perPage = 10;
            $currentPage = max(1, (int)($_GET['page_admins'] ?? 1));
            $totalPages = ceil(count($admins) / $perPage);
        ?>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
            <span class="text-muted small">
                <?= count($admins) ?> administrateur<?= count($admins) > 1 ? 's' : '' ?>
            </span>

            <ul class="pagination pagination-sm mb-0">

                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_admins' => max(1, $currentPage - 1)])) ?>">
                        &laquo; Précédent
                    </a>
                </li>

                <?php 
                // Afficher jusqu'à 5 pages autour de la page courante
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?<?= http_build_query(array_merge($_GET, ['page_admins' => $i])) ?>">
                           <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_admins' => min($totalPages, $currentPage + 1)])) ?>">
                        Suivant &raquo;
                    </a>
                </li>

            </ul>
        </div>

        <?php else: ?>

        <!-- EMPTY STATE -->
        <div class="text-center py-5">
            <i class="fas fa-user-shield fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun administrateur trouvé</h5>
            <p class="text-muted">Commencez par ajouter un nouvel administrateur.</p>

            <a href="ajouter_admin.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Ajouter un admin
            </a>
        </div>

        <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- Message si l'utilisateur n'est pas Super Admin -->
    <div class="alert alert-danger mt-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Vous n'avez pas les permissions nécessaires pour accéder à cette section.
    </div>
<?php endif; ?>
</div>

<style>
/* CORRECTIF CSS pour éviter le conflit de header */
.panel-section {
    position: relative;
    z-index: 1;
}

.card-header {
    position: relative;
    z-index: 2;
}

.table thead.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f8f9fa;
}

/* Assurer que le contenu reste en dessous du header principal */
#admins {
    margin-top: 0;
    padding-top: 0;
}

/* Style pour la pagination */
.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.pagination .page-link {
    color: #0d6efd;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
}

/* Amélioration de l'affichage du contact */
td small.text-muted i {
    min-width: 16px;
}

/* Effet hover sur les lignes */
tr[role="button"]:hover {
    background-color: rgba(13, 110, 253, 0.05);
    cursor: pointer;
    transition: background-color 0.2s;
}
</style>

<script>
// Script de recherche pour filtrer les administrateurs
document.getElementById('adminSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#adminsTbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Fonctions d'export
function exportPDF(tableId) {
    // Implémentation de l'export PDF
    alert('Export PDF en développement...');
}

function exportExcel(tableId) {
    // Implémentation de l'export Excel
    alert('Export Excel en développement...');
}
</script>