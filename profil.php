
<?php
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
use Pointage\Services\AuthService;
AuthService::requireAuth();

$authController = new AuthController($pdo);
$role = $_SESSION['role'] ?? '';
$isAdmin = $authController->isAdmin();
$isSuperAdmin = $authController->isSuperAdmin();
$isEmploye = ($role === ROLE_EMPLOYE);

// Récupération des infos selon le rôle
if ($isEmploye) {
    $userId = $_SESSION['employe_id'];
    $userController = new EmployeController($pdo);
    $user = $userController->show($userId);
    $updateFn = function($id, $data) use ($userController) { return $userController->update($id, $data); };
    $userType = 'Employé';
    $css = ['assets/css/employe.css'];
} else {
    $userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
    $userController = new AdminController($pdo);
    $user = $userController->show($userId);
    $updateFn = function($id, $data) use ($userController) { return $userController->update($id, $data); };
    $userType = $isSuperAdmin ? 'Super Admin' : 'Admin';
    $css = ['assets/css/admin.css'];
}

// Traitement de la modification
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'poste' => trim($_POST['poste'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];
    if ($isEmploye) {
        $data['email_pro'] = trim($_POST['email_pro'] ?? '');
        $data['telephone'] = trim($_POST['telephone'] ?? '');
    }
    if (!empty($_POST['password'])) {
        $data['password'] = $_POST['password'];
    }
    if ($isAdmin && isset($_POST['infos_sup'])) {
        $data['infos_sup'] = trim($_POST['infos_sup']);
    }
    // Gestion upload photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $dest = 'uploads/employes/' . $filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $data['photo'] = $filename;
        }
    }
    $result = $updateFn($userId, $data);
    if ($result['success']) {
        $message = '<div class="alert alert-success">Profil mis à jour avec succès.</div>';
        $user = $userController->show($userId);
    } else {
        $message = '<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>';
    }
}

$pageTitle = 'Mon Profil';
$additionalCSS = $css;
include 'src/views/partials/header.php';
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Profil <?= htmlspecialchars($userType) ?></h4>
                </div>
                <div class="card-body">
                    <?= $message ?>
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3 text-center">
                            <?php if (!empty($user['photo'])):
                                $photo = $user['photo'];
                                $imgSrc = 'uploads/employes/' . $photo;
                                if (strpos($imgSrc, 'uploads/') !== false) {
                                    $imgSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($photo));
                                }
                            ?>
                                <img src="<?= $imgSrc ?>" class="rounded-circle mb-2" width="80" height="80" alt="Photo de profil">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-5x text-muted mb-2"></i>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="poste" class="form-label">Poste</label>
                            <input type="text" class="form-control" id="poste" name="poste" value="<?= htmlspecialchars($user['poste'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email personnel</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <?php if ($isEmploye): ?>
                        <div class="mb-3">
                            <label for="email_pro" class="form-label">Email professionnel</label>
                            <input type="email" class="form-control" id="email_pro" name="email_pro" value="<?= htmlspecialchars($user['email_pro'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="telephone" class="form-label">Numéro de téléphone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="anciennete" class="form-label">Ancienneté</label>
                            <input type="text" class="form-control" id="anciennete" name="anciennete" value="<?= htmlspecialchars($user['anciennete'] ?? '') ?>" readonly>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Laisser vide pour ne pas changer">
                        </div>
                        <?php if ($isAdmin): ?>
                        <div class="mb-3">
                            <label for="infos_sup" class="form-label">Informations supplémentaires (modifiables uniquement par l'admin)</label>
                            <textarea class="form-control" id="infos_sup" name="infos_sup" rows="2"><?= htmlspecialchars($user['infos_sup'] ?? '') ?></textarea>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'src/views/partials/footer.php'; ?>
