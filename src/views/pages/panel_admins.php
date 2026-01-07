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
                        <th style="width:60px;">Profil</th>
                        <th>Administrateur</th>
                        <th>Contact</th>
                        <th>Adresse</th>
                        <th>Statut</th>
                        <th>Dernière activité</th>
                    </tr>
                </thead>

                <tbody id="adminsTbody">

                <?php foreach ($admins as $admin):

                    if (!$isSuperAdmin && $admin['role'] === ROLE_SUPER_ADMIN) continue;

                    $initiale = strtoupper($admin['prenom'][0] ?? '?') . strtoupper($admin['nom'][0] ?? '?');
                    $isActive = $admin['last_activity'] && strtotime($admin['last_activity']) > strtotime('-30 minutes');

                    // Récupération photo ou fallback sur initiales
                    $photo = !empty($admin['photo'])
                        ? htmlspecialchars($admin['photo'])
                        : null;
                ?>

                <tr role="button" onclick="window.location.href='profil_admin.php?id=<?= $admin['id'] ?>'">

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
                            <?= htmlspecialchars($admin['prenom'].' '.$admin['nom']) ?>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars(ucfirst($admin['role'])) ?></small>
                    </td>

                    <!-- CONTACT -->
                    <td>
                        <div><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($admin['email']) ?></div>
                        <div class="text-muted small">
                            <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($admin['telephone'] ?? 'N/A') ?>
                        </div>
                    </td>

                    <!-- ADDRESS -->
                    <td>
                        <small class="text-muted">
                            <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                            <?= htmlspecialchars($admin['adresse'] ?: 'Non renseignée') ?>
                        </small>
                    </td>

                    <!-- STATUS -->
                    <td>
                        <span class="badge rounded-pill <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $isActive ? 'Actif' : 'Inactif' ?>
                        </span>
                    </td>

                    <!-- ACTIVITY -->
                    <td>
                        <?= $admin['last_activity']
                            ? date('d/m/Y H:i', strtotime($admin['last_activity']))
                            : '<span class="text-muted">Jamais</span>' ?>
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
                       href="?<?= http_build_query(array_merge($_GET, ['page_admins'=>$currentPage - 1])) ?>">
                        &laquo; Précédent
                    </a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?<?= http_build_query(array_merge($_GET, ['page_admins'=>$i])) ?>">
                           <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_admins'=>$currentPage + 1])) ?>">
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

<?php endif; ?>
</div>
