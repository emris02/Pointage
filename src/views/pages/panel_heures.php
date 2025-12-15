<?php
/**
 * PANEL HEURES – Version Professionnelle & Responsive
 */

require_once __DIR__ . '/../../controllers/HeuresController.php';

// Charger PDO si non chargé
if (!isset($pdo) || !($pdo instanceof PDO)) {
    @include_once __DIR__ . '/../../config/bootstrap.php';
}

$hc = new HeuresController($pdo);

$month = $_GET['month'] ?? date('Y-m');
$overview = $hc->getOverviewForMonth($month);

$employees       = $overview['employees'] ?? [];
$totalHours      = $overview['total_hours'] ?? '00:00';
$totalPointages  = $overview['total_pointages'] ?? 0;
$totalArrivals   = $overview['total_arrivals'] ?? 0;
$presentToday    = $overview['present_today'] ?? 0;

// Format mois lisible : 2025-02 => Février 2025
$dtObj = DateTime::createFromFormat('Y-m', $month);
$moisEntier = $dtObj ? $dtObj->format('F Y') : $month;

// Traduction mois FR
$moisFR = [
    "January"=>"Janvier","February"=>"Février","March"=>"Mars","April"=>"Avril",
    "May"=>"Mai","June"=>"Juin","July"=>"Juillet","August"=>"Août",
    "September"=>"Septembre","October"=>"Octobre","November"=>"Novembre","December"=>"Décembre"
];
$moisEntier = str_replace(array_keys($moisFR), array_values($moisFR), $moisEntier);
?>


<div id="heures" class="panel-section" style="display:none;">

<div class="card shadow-sm">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-clock fa-lg text-primary"></i>
                <h4 class="fw-bold mb-0">Heures du mois – <?= htmlspecialchars($moisEntier) ?></h4>
            </div>

            <div class="small text-muted mt-1">
                <span class="me-3"><i class="fas fa-user-check text-success"></i> Présents aujourd'hui : <strong><?= (int)$presentToday ?></strong></span>
                <span class="me-3"><i class="fas fa-fingerprint text-info"></i> Pointages : <strong><?= (int)$totalPointages ?></strong></span>
                <span><i class="fas fa-sign-in-alt text-primary"></i> Arrivées : <strong><?= (int)$totalArrivals ?></strong></span>
            </div>
        </div>

        <!-- Total heures -->
        <div class="text-end">
            <div class="fw-bold small text-muted">Total heures cumulées</div>
            <div class="badge bg-dark text-white px-4 py-2 fs-6 shadow-sm">
                <?= htmlspecialchars($totalHours) ?>
            </div>
        </div>
    </div>

    <div class="card-body">

        <!-- CHOIX DU MOIS -->
        <div class="d-flex justify-content-end mb-3">
            <form method="GET" class="d-flex gap-2">
                <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control form-control-sm">
                <button class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- TABLE -->
        <div class="table-responsive" style="max-height:70vh; overflow-y:auto;">
            <table class="table table-hover table-striped align-middle" id="heures-table">

                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width:65px">Profil</th>
                        <th>Nom complet</th>
                        <th>Département</th>
                        <th class="text-center">Temps total</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $departementColors = [
                    'depart_formation'       => 'bg-info',
                    'depart_communication'   => 'bg-warning',
                    'depart_informatique'    => 'bg-primary',
                    'depart_consulting'      => 'bg-success',
                    'depart_marketing&vente' => 'bg-success',
                    'administration'         => 'bg-secondary'
                ];
                ?>

                <?php foreach ($employees as $e):

                    $id    = (int)($e['id'] ?? 0);
                    $prenom = htmlspecialchars($e['prenom'] ?? '');
                    $nom    = htmlspecialchars($e['nom'] ?? '');
                    $initiales = strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));

                    $temps = htmlspecialchars(substr($e['total_travail'] ?? '00:00:00', 0, 5));

                    // Départements
                    $depRaw = $e['departement'] ?? 'inconnu';
                    $depLabel = ucfirst(str_replace('depart_', '', $depRaw));
                    $depClass = $departementColors[$depRaw] ?? 'bg-dark';

                    // Photo
                    $photoUrl = null;
                    if (!empty($e['photo'])) {
                        $photo = $e['photo'];
                        $photoUrl = (strpos($photo, 'uploads') !== false)
                            ? dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($photo))
                            : $photo;
                        $photoUrl = htmlspecialchars($photoUrl);
                    }
                ?>

                <tr class="clickable-row" onclick="window.location.href='profil_employe.php?id=<?= $id ?>'">
                    <!-- PHOTO -->
                    <td>
                        <?php if ($photoUrl): ?>
                            <img src="<?= $photoUrl ?>" class="rounded-circle shadow-sm"
                                 width="48" height="48" style="object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center shadow-sm"
                                 style="width:48px;height:48px;font-weight:600;">
                                 <?= $initiales ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- NOM -->
                    <td class="fw-semibold"><?= $prenom . ' ' . $nom ?></td>

                    <!-- DÉPARTEMENT -->
                    <td class="text-center">
                        <span class="badge <?= $depClass ?>"><?= htmlspecialchars($depLabel) ?></span>
                    </td>

                    <!-- TEMPS -->
                    <td class="text-center">
                        <span class="badge bg-dark px-3 py-2 text-white fs-6"><?= $temps ?></span>
                    </td>
                </tr>

                <?php endforeach; ?>

                </tbody>

            </table>
        </div>

    </div>

</div>

</div>
