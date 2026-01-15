<?php
// Content-only fragment for admin demandes list. Expects variables prepared by the data-prep partial:
// $demandes, $total_demandes, $total_pages, $page, $stats, $filtre_statut, $filtre_type, $filtre_date
// This file contains only the HTML fragment for #mainDemandesContent
?>
<div id="mainDemandesContent">
    <!-- Stat cards -->
    <div class="row">
        <div class='col-md-6 col-lg-3'>
            <div class='card stat-card total h-100'>
                <div class='card-body'>
                    <div class='d-flex justify-content-between align-items-center'>
                        <div>
                            <h6 class='text-muted mb-2'>Total</h6>
                            <h3 class='mb-0'><?= $stats['total'] ?></h3>
                            <small class='text-muted'>Toutes périodes</small>
                        </div>
                        <div class='bg-primary bg-opacity-10 p-3 rounded'>
                            <i class='fas fa-list text-primary fs-4'></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Other stat cards (en_attente, approuve, rejete) reused from partial if needed -->
    </div>

    <!-- Filters -->
    <div class='filter-card mb-4'>
        <h5 class='mb-3'><i class='fas fa-filter me-2'></i>Filtres</h5>
        <form id='filterForm' class='row g-3'>
            <div class='col-md-3'>
                <label class='form-label'>Statut</label>
                <select name='statut' class='form-select'>
                    <option value='tous'>Tous</option>
                    <option value='en_attente' <?= $filtre_statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                    <option value='approuve' <?= $filtre_statut === 'approuve' ? 'selected' : '' ?>>Approuvé</option>
                    <option value='rejete' <?= $filtre_statut === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                </select>
            </div>
            <div class='col-md-3'>
                <label class='form-label'>Type</label>
                <select name='type' class='form-select'>
                    <option value='tous'>Tous</option>
                    <option value='conge' <?= $filtre_type === 'conge' ? 'selected' : '' ?>>Congé</option>
                    <option value='retard' <?= $filtre_type === 'retard' ? 'selected' : '' ?>>Retard</option>
                    <option value='absence' <?= $filtre_type === 'absence' ? 'selected' : '' ?>>Absence</option>
                </select>
            </div>
            <div class='col-md-3'>
                <label class='form-label'>Date</label>
                <input type='date' name='date' class='form-control' value='<?= htmlspecialchars($filtre_date, ENT_QUOTES, 'UTF-8') ?>'>
            </div>
            <div class='col-md-3 d-flex align-items-end'>
                <button type='submit' class='btn btn-primary me-2'><i class='fas fa-filter me-1'></i>Filtrer</button>
                <a href='admin_demandes.php' class='btn btn-outline-secondary'>Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- Demandes List -->
    <div class='card'>
        <div class='card-header bg-white border-0'>
            <div class='d-flex justify-content-between align-items-center'>
                <h5 class='mb-0'><i class='fas fa-list me-2'></i>Liste des demandes</h5>
                <span class='badge bg-primary'>
                    <?= $total_demandes ?> demande(s) trouvée(s)
                </span>
            </div>
        </div>
        <div class='card-body'>
            <?php if (empty($demandes)): ?>
                <div class='text-center py-5'>
                    <i class='fas fa-inbox fa-4x text-muted mb-3'></i>
                    <h5 class='mt-3'>Aucune demande trouvée</h5>
                    <p class='text-muted'>Aucune demande ne correspond à vos critères de recherche</p>
                    <a href='admin_demandes.php' class='btn btn-primary mt-2'>
                        <i class='fas fa-sync-alt me-1'></i> Réinitialiser les filtres
                    </a>
                </div>
            <?php else: ?>
                <div class='table-responsive'>
                    <table class='table table-hover align-middle' id='demandesTable'>
                        <thead class='table-light'>
                            <tr>
                                <th>Employé</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($demandes as $demande): ?>
                            <?php
                                $nomComplet = trim(($demande['prenom'] ?? '') . ' ' . ($demande['nom'] ?? ''));
                                $poste = $demande['poste'] ?? '';
                                $departement = $demande['departement'] ?? '';
                                $type = $demande['type'] ?? '';
                                $dateDemande = !empty($demande['date_demande']) ? date('d/m/Y H:i', strtotime($demande['date_demande'])) : '';
                                $statut = $demande['statut'] ?? '';
                                $heuresEcoulees = $demande['heures_ecoulees'] ?? 0;
                                $isUrgent = ($heuresEcoulees < 24 && $statut === 'en_attente');
                                $badgeClass = [
                                    'approuve' => 'success',
                                    'rejete' => 'danger',
                                    'en_attente' => 'warning'
                                ][$statut] ?? 'secondary';
                                $badgeIcon = [
                                    'approuve' => 'check',
                                    'rejete' => 'times',
                                    'en_attente' => 'clock'
                                ][$statut] ?? 'question';
                                $initiales = strtoupper(substr($demande['prenom'] ?? '',0,1) . substr($demande['nom'] ?? '',0,1));
                            ?>
                            <tr class="<?= $isUrgent ? 'table-danger' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($demande['photo'])):
                                            $imgSrc = htmlspecialchars($demande['photo']);
                                            if (strpos($demande['photo'], 'uploads/') !== false) {
                                                $imgSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($demande['photo']));
                                            }
                                        ?>
                                            <img src="<?= $imgSrc ?>"
                                                 class="avatar me-3"
                                                 style="width:40px;height:40px;object-fit:cover;border-radius:50%;"
                                                 alt="Photo de <?= htmlspecialchars($nomComplet) ?>">
                                        <?php else: ?>
                                            <div class="avatar-initials bg-secondary text-white d-flex align-items-center justify-content-center me-3"
                                                 style="width:40px;height:40px;border-radius:50%;font-weight:bold;">
                                                <?= $initiales ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($nomComplet) ?></h6>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($poste) ?> • <?= htmlspecialchars($departement) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary">
                                        <?= htmlspecialchars(ucfirst($type)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $dateDemande ?>
                                    <?php if ($isUrgent): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger ms-2" aria-label="Demande urgente">URGENT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $badgeClass ?> badge-status">
                                        <i class="fas fa-<?= $badgeIcon ?> me-1"></i>
                                        <?= htmlspecialchars(ucfirst($statut)) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary btn-action view-details details-btn"
                                            data-id="<?= (int)$demande['id'] ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#detailsModal"
                                            title="Détails de la demande">
                                        <i class="fas fa-eye me-1"></i> Détails
                                    </button>
                                    <?php if ($demande['statut'] === 'en_attente'): ?>
                                        <button class="btn btn-sm btn-success btn-action ms-1" onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'approuve')">
                                            <i class="fas fa-check"></i> Accorder
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action ms-1" onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'rejete')">
                                            <i class="fas fa-times"></i> Rejeter
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = $queryParams ? '&' . http_build_query($queryParams) : '';
                    ?>
                    <nav class="mt-4" aria-label="Pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= max(1, $page-1) . $queryString ?>" aria-label="Page précédente">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php
                                $range = 2;
                                $start = max(1, $page - $range);
                                $end = min($total_pages, $page + $range);

                                if ($start > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1' . $queryString . '">1</a></li>';
                                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                }

                                for ($i = $start; $i <= $end; $i++) {
                                    $active = $i == $page ? ' active' : '';
                                    echo '<li class="page-item' . $active . '">';
                                    if ($active) {
                                        echo '<span class="page-link">' . $i . ' <span class="visually-hidden">(page courante)</span></span>';
                                    } else {
                                        echo '<a class="page-link" href="?page=' . $i . $queryString . '">' . $i . '</a>';
                                    }
                                    echo '</li>';
                                }

                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $queryString . '">' . $total_pages . '</a></li>';
                                }
                            ?>
                            <li class="page-item<?= $page >= $total_pages ? ' disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= min($total_pages, $page+1) . $queryString ?>" aria-label="Page suivante">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                        <div class="text-center text-muted small mt-2">
                            Page <strong><?= $page ?></strong> sur <strong><?= $total_pages ?></strong>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Détails de la demande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="modalDetailsContent">
            <!-- Contenu chargé dynamiquement -->
          </div>
        </div>
      </div>
    </div>
</div>
