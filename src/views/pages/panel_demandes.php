<?php
/**
 * PANEL DEMANDES — Version PRO, optimisée & responsive
 */
?>

<div id="demandes" class="panel-section" style="display:none;">
<div class="container-fluid px-0">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-tasks fa-lg text-primary"></i>
            <h4 class="mb-0 fw-bold">Gestion des demandes</h4>
        </div>
        <span class="badge bg-primary fs-6 shadow-sm"><?= count($demandes) ?> demande(s)</span>
    </div>

    <div class="card-body">

        <!-- STATISTIQUES -->
        <div class="row g-3 mb-4">

            <?php
            $total      = (int)($stats_demandes['total'] ?? 0);
            $attente    = (int)($stats_demandes['en_attente'] ?? 0);
            $approuve   = (int)($stats_demandes['approuve'] ?? 0);
            $rejete     = (int)($stats_demandes['rejete'] ?? 0);

            $statList = [
                [
                    "label" => "Total demandes",
                    "value" => $total,
                    "icon"  => "list-alt",
                    "color" => "primary",
                    "desc"  => date('d M Y')
                ],
                [
                    "label" => "En attente",
                    "value" => $attente,
                    "icon"  => "clock",
                    "color" => "warning",
                    "desc"  => "Non traitées"
                ],
                [
                    "label" => "Approuvées",
                    "value" => $approuve,
                    "icon"  => "check-circle",
                    "color" => "success",
                    "desc"  => "Ce mois-ci"
                ],
                [
                    "label" => "Rejetées",
                    "value" => $rejete,
                    "icon"  => "times-circle",
                    "color" => "danger",
                    "desc"  => "Ce mois-ci"
                ]
            ];
            ?>

            <?php foreach ($statList as $s): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2"><?= $s["label"] ?></h6>
                                <h3 class="fw-bold mb-0">
                                    <span data-value="<?= $s["value"] ?>">0</span>
                                </h3>
                                <small class="text-muted"><?= $s["desc"] ?></small>
                            </div>
                            <div class="stat-icon bg-<?= $s["color"] ?> bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-<?= $s["icon"] ?> text-<?= $s["color"] ?> fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                            <script>
                            // Change demande status via AJAX POST to demandes.php
                            async function changeDemandeStatus(id, statut, btn) {
                                if (!confirm('Confirmer la modification du statut ?')) return;

                                const form = new FormData();
                                form.append('action', 'update_status');
                                form.append('id', id);
                                form.append('statut', statut);
                                form.append('commentaire', '');

                                try {
                                    const resp = await fetch('demandes.php', {
                                        method: 'POST',
                                        body: form,
                                        credentials: 'same-origin'
                                    });

                                    if (!resp.ok) throw new Error('Erreur réseau ' + resp.status);

                                    // On veut recharger la ligne et les compteurs sans full reload
                                    // Option simple: refresh the page section by reloading the dashboard panel
                                    // Here we will update the badge and counters optimistically

                                    // Update badge in the row
                                    const row = btn.closest('tr');
                                    if (row) {
                                        const badge = row.querySelector('.demande-statut');
                                        if (badge) {
                                            badge.textContent = statut.charAt(0).toUpperCase() + statut.slice(1);
                                            badge.className = 'badge px-3 py-2 demande-statut';
                                            if (statut === 'approuve') badge.classList.add('bg-success');
                                            else if (statut === 'rejete') badge.classList.add('bg-danger');
                                            else badge.classList.add('bg-warning');
                                        }
                                    }

                                    // Update stats counters in the cards
                                    document.querySelectorAll('[data-value]').forEach(el => {
                                        const v = parseInt(el.getAttribute('data-value') || '0', 10);
                                        // find which stat this element represents by checking parent text
                                        const label = el.closest('.card').querySelector('h6')?.textContent?.trim() || '';
                                        if (label.includes('Total')) {
                                            el.textContent = (v > 0 ? v + 0 : v + 1); // conservative increment
                                        } else if (label.includes('En attente')) {
                                            // decrement en_attente, increment target
                                            el.textContent = Math.max(0, v - 1);
                                        } else if (label.includes('Approuvées') && statut === 'approuve') {
                                            el.textContent = v + 1;
                                        } else if (label.includes('Rejetées') && statut === 'rejete') {
                                            el.textContent = v + 1;
                                        }
                                    });

                                } catch (e) {
                                    console.error('Erreur changement statut:', e);
                                    alert('Impossible de modifier le statut: ' + e.message);
                                }
                            }
                            </script>
            <?php endforeach; ?>

        </div>

        <!-- LISTE DES DEMANDES -->
        <div class="card shadow-sm mb-3">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Liste des demandes</h5>
                <span class="badge bg-primary shadow-sm"><?= count($demandes) ?> demande(s)</span>
            </div>

            <div class="card-body">

                <?php if (!empty($demandes)): ?>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="demandesTableDash">
                        <thead class="table-light">
                            <tr>
                                <th style="width:250px;">Employé</th>
                                <th style="width:150px;">Type</th>
                                <th style="width:170px;">Date</th>
                                <th style="width:140px;">Statut</th>
                                <th style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php foreach ($demandes as $demande):

                            $prenom      = htmlspecialchars($demande['prenom'] ?? '');
                            $nom         = htmlspecialchars($demande['nom'] ?? '');
                            $poste       = htmlspecialchars($demande['poste'] ?? '');
                            $departement = htmlspecialchars($demande['departement'] ?? 'Non défini');

                            $type        = htmlspecialchars($demande['type'] ?? '');
                            $statut      = htmlspecialchars($demande['statut'] ?? '');
                            $date        = !empty($demande['date_demande']) ? date('d/m/Y H:i', strtotime($demande['date_demande'])) : '';

                            $heures      = (int)($demande['heures_ecoulees'] ?? 0);
                            $isUrgent    = ($statut === 'en_attente' && $heures < 24);

                            $badgeClass = [
                                'approuve' => 'success',
                                'rejete' => 'danger',
                                'en_attente' => 'warning'
                            ][$statut] ?? 'secondary';

                            $photo = $demande['photo'] ?? null;
                            $initiales = strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));

                            // generer src
                            $photoSrc = null;
                            if (!empty($photo)) {
                                $photoSrc = htmlspecialchars(
                                    strpos($photo, 'uploads/') !== false
                                        ? dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($photo))
                                        : $photo
                                );
                            }
                        ?>

                        <tr class="clickable-row <?= $isUrgent ? 'table-danger' : '' ?>"
                            onclick="window.location.href='demandes.php?id=<?= (int)$demande['id'] ?>'">

                            <!-- EMPLOYÉ -->
                            <td class="d-flex align-items-center gap-3">

                                <!-- AVATAR -->
                                <?php if ($photoSrc): ?>
                                    <img src="<?= $photoSrc ?>"
                                        class="rounded-circle shadow-sm"
                                        style="width:45px;height:45px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex justify-content-center align-items-center shadow-sm"
                                        style="width:45px;height:45px;font-weight:600;font-size:1rem;">
                                        <?= $initiales ?>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div class="fw-semibold"><?= $prenom . ' ' . $nom ?></div>
                                    <small class="text-muted"><?= $poste ?> • <?= $departement ?></small>
                                </div>
                            </td>

                            <!-- TYPE -->
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1">
                                    <?= ucfirst($type) ?>
                                </span>
                            </td>

                            <!-- DATE -->
                            <td>
                                <?= $date ?>
                                <?php if ($isUrgent): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger ms-2">Urgent</span>
                                <?php endif; ?>
                            </td>

                            <!-- STATUT -->
                            <td class="demande-statut-td">
                                <span class="badge bg-<?= $badgeClass ?> px-3 py-2 demande-statut" data-statut="<?= htmlspecialchars($statut) ?>">
                                    <?= ucfirst($statut) ?>
                                </span>
                            </td>

                            <!-- ACTIONS -->
                            <td>
                                <div class="btn-group" role="group">
                                    <?php if ($statut !== 'approuve'): ?>
                                    <button class="btn btn-sm btn-success" onclick="changeDemandeStatus(<?= (int)$demande['id'] ?>, 'approuve', this)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($statut !== 'rejete'): ?>
                                    <button class="btn btn-sm btn-danger" onclick="changeDemandeStatus(<?= (int)$demande['id'] ?>, 'rejete', this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>

                                    <a href="demandes.php?id=<?= (int)$demande['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </td>

                        </tr>

                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php
                    $currentPageDemandes = max(1, (int)($_GET['page_demandes'] ?? 1));
                    $totalPagesDemandes  = $demandes_total_pages ?? 1;
                    $totalDemandes       = $demandes_total ?? count($demandes);
                ?>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
                    <span class="text-muted small"><?= $totalDemandes ?> demande(s)</span>

                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPageDemandes <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_demandes'=>$currentPageDemandes - 1])) ?>">
                                &laquo; Précédent
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPagesDemandes; $i++): ?>
                        <li class="page-item <?= $i == $currentPageDemandes ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_demandes'=>$i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $currentPageDemandes >= $totalPagesDemandes ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_demandes'=>$currentPageDemandes + 1])) ?>">
                                Suivant &raquo;
                            </a>
                        </li>
                    </ul>
                </div>

                <?php else: ?>

                <!-- EMPTY STATE -->
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune demande trouvée</h5>
                    <p class="text-muted">Toutes les demandes sont traitées ou aucune n’a encore été soumise.</p>
                </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
</div>
