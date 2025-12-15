<?php
/**
 * PANEL EMPLOYÉS – Version Professionnelle & Responsive
 * Compatible avec le style des panels Admins + Demandes
 */
?>

<div id="employes" class="panel-section" style="display:none;">

<?php if ($is_super_admin || $_SESSION['role'] === 'admin'): ?>

<div class="card shadow-sm">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-users fa-lg text-primary"></i>
            <h4 class="fw-bold mb-0">Gestion des Employés</h4>
        </div>

        <a href="ajouter_employe.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> Nouvel Employé
        </a>
    </div>

    <div class="card-body">

        <!-- ACTION BAR -->
        <div class="d-flex justify-content-between flex-wrap mb-3 gap-2">

            <!-- EXPORT -->
            <div class="btn-group">
                <button class="btn btn-outline-danger btn-sm" onclick="exportPDF('employes-table')">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="exportExcel('employes-table')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>

            <!-- SEARCH -->
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" id="employeeSearch"
                           class="form-control border-start-0"
                           placeholder="Rechercher un employé...">
                </div>
            </div>

        </div>

        <!-- TABLE EMPLOYÉS -->
        <?php if (!empty($employes)): ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="employes-table">
                <thead class="table-light">
                    <tr>
                        <th>Profil</th>
                        <th>Nom complet</th>
                        <th>Poste</th>
                        <th>Contact</th>
                        <th>Département</th>
                        <th>Statut</th>
                        <th>Date d'embauche</th>
                        <th>Type de contrat</th>
                        <th>Durée</th>
                        <th>Salaire</th>
                        <th>Adresse</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($employes as $e):

                    $prenom = htmlspecialchars($e['prenom'] ?? '');
                    $nom = htmlspecialchars($e['nom'] ?? '');
                    $initiales = strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));

                    /* PHOTO */
                    $photoSrc = null;
                    if (!empty($e['photo'])) {
                        $photoSrc = htmlspecialchars(
                            strpos($e['photo'], 'uploads/') !== false
                                ? dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($e['photo']))
                                : $e['photo']
                        );
                    }

                    /* DEPARTEMENT MAPPING */
                    $depRaw = $e['departement'] ?? 'inconnu';
                    $deps = [
                        'depart_formation'       => ['label'=>'Formation',       'class'=>'bg-info'],
                        'depart_communication'   => ['label'=>'Communication',   'class'=>'bg-warning'],
                        'depart_informatique'    => ['label'=>'Informatique',    'class'=>'bg-primary'],
                        'depart_consulting'      => ['label'=>'Consulting',      'class'=>'bg-success'],
                        'depart_marketing&vente' => ['label'=>'Marketing & Vente','class'=>'bg-success'],
                        'administration'         => ['label'=>'Administration',   'class'=>'bg-secondary']
                    ];

                    $depLabel = $deps[$depRaw]['label'] ?? ucfirst($depRaw);
                    $depClass = $deps[$depRaw]['class'] ?? 'bg-dark';

                    /* CONTRAT */
                    $typeContrat  = htmlspecialchars($e['contrat_type'] ?? $e['type_contrat'] ?? '—');
                    $dureeContrat = htmlspecialchars($e['contrat_duree'] ?? $e['duree_contrat'] ?? '—');

                    /* SALAIRE */
                    $salaireValue = $e['salaire'] ?? $e['salary'] ?? $e['salaire_brut'] ?? null;
                    $salaire = $salaireValue ? number_format($salaireValue, 0, ',', ' ') . ' FCFA' : '—';

                ?>

                <tr class="clickable-row" onclick="window.location.href='profil_employe.php?id=<?= $e['id'] ?>'">

                    <!-- PHOTO -->
                    <td>
                        <?php if ($photoSrc): ?>
                            <img src="<?= $photoSrc ?>" class="rounded-circle shadow-sm"
                                 style="width:45px;height:45px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center shadow-sm"
                                 style="width:45px;height:45px;font-weight:600;">
                                 <?= $initiales ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- NOM -->
                    <td class="fw-semibold"><?= $prenom . ' ' . $nom ?></td>

                    <!-- POSTE -->
                    <td><?= htmlspecialchars($e['poste'] ?? '—') ?></td>

                    <!-- CONTACT -->
                    <td>
                        <a href="mailto:<?= htmlspecialchars($e['email'] ?? '') ?>"
                           class="text-decoration-none fw-medium">
                           <?= htmlspecialchars($e['email'] ?? '—') ?>
                        </a><br>
                        <small class="text-muted"><?= htmlspecialchars($e['telephone'] ?? '—') ?></small>
                    </td>

                    <!-- DÉPARTEMENT -->
                    <td>
                        <span class="badge <?= $depClass ?>"><?= $depLabel ?></span>
                    </td>

                    <!-- STATUT -->
                    <td>
                        <?php $isActive = ($e['statut'] ?? '') === 'actif'; ?>
                        <span class="badge bg-<?= $isActive ? 'success' : 'danger' ?>">
                            <?= $isActive ? 'Actif' : 'Inactif' ?>
                        </span>
                    </td>

                    <!-- EMBUCHE -->
                    <td><?= !empty($e['date_embauche']) ? date('d/m/Y', strtotime($e['date_embauche'])) : '—' ?></td>

                    <!-- TYPE CONTRAT -->
                    <td><?= $typeContrat ?></td>

                    <!-- DURÉE CONTRAT -->
                    <td><?= $dureeContrat ?></td>

                    <!-- SALAIRE -->
                    <td><?= $salaire ?></td>

                    <!-- ADRESSE -->
                    <td><?= htmlspecialchars($e['adresse'] ?? '—') ?></td>

                </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php
            $perPage      = 10;
            $currentPage  = max(1, (int)($_GET['page'] ?? 1));
            $totalPages   = max(1, (int)ceil(count($employes) / $perPage));
        ?>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
            <span class="text-muted small">
                <?= count($employes) ?> employé<?= count($employes) > 1 ? 's' : '' ?>
            </span>

            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$currentPage - 1])) ?>">
                        &laquo; Précédent
                    </a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>">
                       <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>

                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?<?= http_build_query(array_merge($_GET, ['page'=>$currentPage + 1])) ?>">
                        Suivant &raquo;
                    </a>
                </li>
            </ul>
        </div>

        <?php else: ?>

        <!-- EMPTY -->
        <div class="text-center py-5">
            <i class="fas fa-users fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun employé trouvé</h5>
            <p class="text-muted">Ajoutez votre premier employé pour commencer.</p>

            <a href="ajouter_employe.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Ajouter un employé
            </a>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php endif; ?>
</div>
