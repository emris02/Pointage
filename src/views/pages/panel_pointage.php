<?php
/**
 * PANEL POINTAGE – Version Professionnelle & Responsive
 * Historique des pointages avec filtres, stats, avatars intelligents et pagination propre
 */
?>

<div id="pointage" class="panel-section" style="display:none;">

<?php if (!empty($pointages)): ?>

<div class="card shadow-sm">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-calendar-check fa-lg text-primary"></i>
            <h4 class="fw-bold mb-0">Historique des pointages</h4>
        </div>

        <!-- Boutons export -->
        <div class="d-flex gap-2">
            <button class="btn btn-light btn-sm shadow-sm" onclick="exportPDF('pointage-table')">
                <i class="fas fa-file-pdf me-1 text-danger"></i> PDF
            </button>
            <button class="btn btn-light btn-sm shadow-sm" onclick="exportExcel('pointage-table')">
                <i class="fas fa-file-excel me-1 text-success"></i> Excel
            </button>
        </div>
    </div>

    <div class="card-body">

        <!-- FILTRES -->
        <form method="get" class="row g-2 mb-3 align-items-end">

            <div class="col-md-4">
                <label class="form-label fw-semibold">Filtrer par date :</label>
                <input type="date" name="date" value="<?= htmlspecialchars($_GET['date'] ?? date('Y-m-d')) ?>"
                    class="form-control shadow-sm">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Département :</label>
                <select name="departement" class="form-select shadow-sm">
                    <option value="">Tous les départements</option>
                    <?php foreach ($liste_departements as $dep): ?>
                        <option value="<?= htmlspecialchars($dep) ?>"
                            <?= isset($_GET['departement']) && $_GET['departement'] === $dep ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('depart_', '', $dep)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary mt-auto shadow-sm">
                    <i class="fas fa-search me-1"></i> Filtrer
                </button>
                <a href="admin_dashboard_unifie.php" class="btn btn-secondary mt-auto shadow-sm">
                    <i class="fas fa-sync-alt me-1"></i> Réinitialiser
                </a>
            </div>

        </form>

        <!-- TABLE -->
        <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
            <table class="table table-hover table-striped align-middle" id="pointage-table" style="cursor:pointer;">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 60px;">Avatar</th>
                        <th>Nom complet</th>
                        <th class="text-center">Département</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Arrivée</th>
                        <th class="text-center">Départ</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($pointages as $entry):

                    $initiale = strtoupper(substr($entry['prenom'],0,1) . substr($entry['nom'],0,1));

                    // Avatar
                    $photoSrc = '';
                    if (!empty($entry['photo'])) {
                        $photoSrc = htmlspecialchars($entry['photo']);
                        if (strpos($entry['photo'], 'uploads/') !== false) {
                            $photoSrc = dirname($_SERVER['SCRIPT_NAME']).'/image.php?f='.urlencode(basename($entry['photo']));
                        }
                    }

                    // Département badge
                    $dep = $entry['departement'] ?? 'Non défini';
                    $depLabel = ucfirst(str_replace('depart_', '', $dep));
                    $depClass = [
                        'depart_informatique'    => 'bg-primary',
                        'depart_communication'   => 'bg-warning',
                        'depart_marketing&vente' => 'bg-success',
                        'depart_formation'       => 'bg-info',
                        'depart_consulting'      => 'bg-success',
                        'administration'         => 'bg-secondary'
                    ][$dep] ?? 'bg-dark';

                ?>

                <tr onclick="window.location.href='profil_employe.php?id=<?= (int)$entry['employe_id'] ?>'">

                    <!-- AVATAR -->
                    <td>
                        <?php if ($photoSrc): ?>
                            <img src="<?= $photoSrc ?>" class="rounded-circle shadow-sm"
                                 style="width:42px;height:42px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm"
                                 style="width:42px;height:42px;font-weight:bold;">
                                <?= $initiale ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- NOM -->
                    <td class="fw-semibold">
                        <?= htmlspecialchars($entry['prenom'].' '.$entry['nom']) ?>
                    </td>

                    <!-- DÉPARTEMENT -->
                    <td class="text-center">
                        <span class="badge <?= $depClass ?> px-3 py-2">
                            <?= htmlspecialchars($depLabel) ?>
                        </span>
                    </td>

                    <!-- DATE -->
                    <td class="text-center">
                        <?php if (!empty($entry['date']) && strtotime($entry['date']) !== false): ?>
                            <?= date('d/m/Y', strtotime($entry['date'])) ?>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>

                    <!-- ARRIVÉE -->
                    <td class="text-center">
                        <?php if ($entry['arrivee']): ?>
                            <span class="badge bg-success px-3 py-2">
                                <i class="fas fa-sign-in-alt me-1"></i><?= $entry['arrivee'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- DÉPART -->
                    <td class="text-center">
                        <?php if ($entry['depart']): ?>
                            <span class="badge bg-danger px-3 py-2">
                                <i class="fas fa-sign-out-alt me-1"></i><?= $entry['depart'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>

                </tr>

                <?php endforeach; ?>

                </tbody>

            </table>
        </div>

        <!-- PAGINATION -->
        <?php
            $currentPagePointage   = $page_pointage ?? (isset($_GET['page_pointage']) ? max(1,(int)$_GET['page_pointage']) : 1);
            $totalPagesPointage    = $pointages_total_pages ?? 1;
            $totalPointagesCount   = $pointages_total ?? count($pointages);
        ?>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted">
                <?= $totalPointagesCount ?> pointage<?= $totalPointagesCount > 1 ? 's' : '' ?>
                pour <?= htmlspecialchars($selectedDate) ?>
            </span>

            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $currentPagePointage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_pointage'=>$currentPagePointage-1])) ?>">
                       &laquo; Précédent
                    </a>
                </li>

                <?php for ($i=1; $i <= $totalPagesPointage; $i++): ?>
                    <li class="page-item <?= $i == $currentPagePointage ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?<?= http_build_query(array_merge($_GET, ['page_pointage'=>$i])) ?>">
                           <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $currentPagePointage >= $totalPagesPointage ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_pointage'=>$currentPagePointage+1])) ?>">
                        Suivant &raquo;
                    </a>
                </li>
            </ul>
        </div>

    </div>
</div>

<?php else: ?>

    <div class="alert alert-info mt-4 shadow-sm">
        <i class="fas fa-info-circle me-2"></i>
        Aucun pointage trouvé pour les filtres sélectionnés.
    </div>

<?php endif; ?>

</div>
