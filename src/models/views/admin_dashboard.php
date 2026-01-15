<?php
/**
 * Vue du dashboard administrateur
 */

require_once '../src/config/bootstrap.php';

// Vérification de l'authentification
$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    header("Location: login.php");
    exit();
}

$isAdmin = true;
$pageTitle = 'Dashboard Administrateur - Xpert Pro';
$pageHeader = 'Dashboard Administrateur';
$pageDescription = 'Vue d\'ensemble du système de pointage';

// Récupération des données
$pointageController = new PointageController($pdo);
$employeController = new EmployeController($pdo);

$todayStats = $pointageController->getStats();
$lateArrivals = $pointageController->getLateArrivals();
$todayPointages = $pointageController->getTodayPointages();
$employes = $employeController->index();

$additionalCSS = ['public/assets/css/admin.css'];
?>

<?php include 'partials/header.php'; ?>

<div class="row">
    <!-- Statistiques principales -->
    <div class="col-12">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $todayStats['total_pointages'] ?? 0 ?></h4>
                                <p class="card-text">Pointages aujourd'hui</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $todayStats['arrivees'] ?? 0 ?></h4>
                                <p class="card-text">Arrivées</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-sign-in-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $todayStats['departs'] ?? 0 ?></h4>
                                <p class="card-text">Départs</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-sign-out-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $todayStats['employes_presents'] ?? 0 ?></h4>
                                <p class="card-text">Employés présents</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Pointages récents -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>
                    Pointages récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($todayPointages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <p>Aucun pointage aujourd'hui</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employé</th>
                                    <th>Type</th>
                                    <th>Heure</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($todayPointages, 0, 10) as $pointage): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-2">
                                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($pointage['prenom'] . ' ' . $pointage['nom']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($pointage['poste']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $pointage['type'] === 'arrivee' ? 'success' : 'warning' ?>">
                                            <i class="fas fa-<?= $pointage['type'] === 'arrivee' ? 'sign-in-alt' : 'sign-out-alt' ?> me-1"></i>
                                            <?= ucfirst($pointage['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('H:i', strtotime($pointage['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deletePointage(<?= $pointage['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Arrivées en retard -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Arrivées en retard
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($lateArrivals)): ?>
                    <div class="text-center text-success py-3">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <p class="mb-0">Aucun retard aujourd'hui</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($lateArrivals as $late): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($late['prenom'] . ' ' . $late['nom']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($late['poste']) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="text-danger fw-bold">
                                    +<?= $late['retard_minutes'] ?> min
                                </div>
                                <small class="text-muted">
                                    <?= date('H:i', strtotime($late['heure_pointage'])) ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$additionalJS = ['public/assets/js/admin.js'];
$inlineJS = "
function deletePointage(pointageId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce pointage ?')) {
        fetch('api/delete_pointage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: pointageId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}
";
include 'partials/footer.php';
?>
