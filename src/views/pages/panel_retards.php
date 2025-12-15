<?php
/**
 * PANEL RETARDS – Version Professionnelle & Responsive
 * - Récupère les retards du mois en cours OU d'une date sélectionnée
 * - Filtre par département
 * - Classe automatiquement les retards du plus long au plus court
 */
?>

<div id="retard" class="panel-section" style="display:none;">

<div class="card shadow-sm mb-4 border-0">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-clock fa-lg text-danger"></i>
            <h4 class="fw-semibold mb-0">Retards du mois / date sélectionnée</h4>
        </div>
    </div>

    <div class="card-body">

        <!-- FILTRES -->
        <form method="get" class="row g-3 mb-4">

            <!-- Filtre date -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Sélectionner une date :</label>
                <input type="date"
                       name="date_retard"
                       value="<?= htmlspecialchars($_GET['date_retard'] ?? '') ?>"
                       class="form-control shadow-sm">
            </div>

            <!-- Filtre département -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Département :</label>
                <select name="dep_retard" class="form-select shadow-sm">
                    <option value="">Tous</option>
                    <?php foreach ($liste_departements as $dep): ?>
                        <option value="<?= htmlspecialchars($dep) ?>"
                            <?= (isset($_GET['dep_retard']) && $_GET['dep_retard'] === $dep) ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('depart_', '', $dep)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Boutons -->
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button class="btn btn-primary shadow-sm">
                    <i class="fas fa-filter me-1"></i> Filtrer
                </button>
                <a href="admin_dashboard_unifie.php" class="btn btn-secondary shadow-sm">
                    <i class="fas fa-sync-alt me-1"></i> Réinitialiser
                </a>
            </div>
        </form>

        <?php if (!empty($retards)): ?>

        <!-- TABLE -->
        <div class="table-responsive" style="max-height:70vh; overflow-y:auto;">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width:60px;">Avatar</th>
                        <th>Employé</th>
                        <th class="text-center">Département</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Retard</th>
                        <th class="text-center">État / Cause</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($retards as $retard):

                    // Données
                    $prenom = $retard['prenom'] ?? '';
                    $nom    = $retard['nom'] ?? '';
                    $initiales = strtoupper(substr($prenom,0,1) . substr($nom,0,1));

                    // Retard (min)
                    $minutes = 0;
                    if (isset($retard['retard_minutes'])) {
                        $minutes = (int)$retard['retard_minutes'];
                    } elseif (!empty($retard['retard'])) {
                        $p = explode(':', $retard['retard']); // HH:MM:SS
                        if (count($p) >= 2) {
                            $minutes = $p[0]*60 + $p[1];
                        }
                    }

                    // Date
                    $dateRaw = $retard['date_heure'] ?? null;
                    $dateAff = $dateRaw ? date('d/m/Y', strtotime($dateRaw)) : '—';

                    // Département
                    $dep = $retard['departement'] ?? 'Non défini';
                    $depLabel = ucfirst(str_replace('depart_', '', $dep));

                    $depClass = [
                        'depart_informatique'    => 'bg-primary',
                        'depart_communication'   => 'bg-warning',
                        'depart_marketing&vente' => 'bg-success',
                        'depart_formation'       => 'bg-info',
                        'depart_consulting'      => 'bg-success',
                        'administration'         => 'bg-secondary',
                    ][$dep] ?? 'bg-dark';

                ?>

                <tr onclick="window.location.href='profil_employe.php?id=<?= (int)$retard['employe_id'] ?>'">

                    <!-- AVATAR -->
                    <td>
                        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center shadow-sm"
                             style="width:42px;height:42px;font-weight:600;">
                            <?= $initiales ?>
                        </div>
                    </td>

                    <!-- NOM -->
                    <td class="fw-semibold"><?= htmlspecialchars("$prenom $nom") ?></td>

                    <!-- DEPARTEMENT -->
                    <td class="text-center">
                        <span class="badge <?= $depClass ?> px-3 py-2"><?= $depLabel ?></span>
                    </td>

                    <!-- DATE -->
                    <td class="text-center">
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-calendar-day me-1"></i><?= $dateAff ?>
                        </span>
                    </td>

                    <!-- RETARD -->
                    <td class="text-center">
                        <span class="badge bg-danger px-3 py-2">
                            <i class="fas fa-clock me-1"></i><?= $minutes ?> min
                        </span>
                    </td>

                    <!-- CAUSE -->
                    <td class="text-center">
                        <?php if (!empty($retard['retard_justifie'])): ?>
                            <span class="badge bg-success px-3 py-2">
                                <i class="fas fa-check-circle me-1"></i>
                                <?= htmlspecialchars($retard['retard_cause'] ?: 'Justifié') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger px-3 py-2">
                                <i class="fas fa-exclamation-triangle me-1"></i> Non justifié
                            </span>
                        <?php endif; ?>
                    </td>

                </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php
        $currentPage = isset($_GET['page_retard']) ? max(1, (int)$_GET['page_retard']) : 1;
        $totalPages  = max(1, ceil(count($retards) / 10));
        ?>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted"><?= count($retards) ?> retard(s)</span>

            <ul class="pagination pagination-sm mb-0">

                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_retard'=>$currentPage-1])) ?>">
                        &laquo; Précédent
                    </a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?<?= http_build_query(array_merge($_GET, ['page_retard'=>$i])) ?>">
                           <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page_retard'=>$currentPage+1])) ?>">
                       Suivant &raquo;
                    </a>
                </li>

            </ul>
        </div>

        <?php else: ?>

        <!-- EMPTY STATE -->
        <div class="text-center py-5">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h5 class="fw-bold text-success">Aucun retard</h5>
            <p class="text-muted">Aucun employé n’est en retard pour cette période.</p>
        </div>

        <?php endif; ?>

    </div>
</div>

</div>
