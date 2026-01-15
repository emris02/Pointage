<?php
require_once 'src/config/bootstrap.php';

// Vérification des autorisations
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID est présent
if (!isset($_GET['id'])) {
    header('Location: admin_dashboard_unifie.php');
    exit();
}

$admin_id = (int)$_GET['id'];

// Récupérer les informations actuelles de l'admin
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: admin_dashboard_unifie.php');
    exit();
}

$message = "";
$formData = $admin;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $formData = [
        'id' => $admin_id,
        'nom' => htmlspecialchars(trim($_POST['nom'] ?? '')),
        'prenom' => htmlspecialchars(trim($_POST['prenom'] ?? '')),
        'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
        'telephone' => preg_replace('/[^0-9]/', '', $_POST['telephone'] ?? ''),
        'role' => $_POST['role'] ?? $admin['role']
    ];

    // Validation
    $errors = [];
    if (empty($formData['nom'])) $errors[] = "Le nom est requis";
    if (empty($formData['prenom'])) $errors[] = "Le prénom est requis";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";

    if (empty($errors)) {
        // Mise à jour dans la base de données
        $stmt = $pdo->prepare("UPDATE admins SET 
            nom = ?, prenom = ?, email = ?, telephone = ?, role = ?
            WHERE id = ?");
        
        $success = $stmt->execute([
            $formData['nom'],
            $formData['prenom'],
            $formData['email'],
            $formData['telephone'],
            $formData['role'],
            $admin_id
        ]);

        if ($success) {
            $message = '<div class="alert alert-success">Administrateur mis à jour avec succès.</div>';
            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();
        } else {
            $message = '<div class="alert alert-danger">Erreur lors de la mise à jour.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">'.implode('<br>', $errors).'</div>';
    }
}

$pageTitle = 'Modifier Administrateur';
$additionalCSS = ['assets/css/profil.css'];
include 'partials/header.php';
include 'src/views/partials/sidebar_canonique.php';
?>

<main class="main-content py-4">
    <div class="container-fluid px-3 px-md-4">
        <div class="form-centered-wrapper">
            <div class="form-card-container">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Modifier Administrateur</h5>
                            <small class="text-white-50">ID: <?= htmlspecialchars($admin_id) ?></small>
                        </div>
                        <div>
                            <button class="btn btn-outline-light" id="openDeleteModalBtn" data-admin-id="<?= $admin_id ?>">
                                <i class="fas fa-trash me-1"></i>Supprimer
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?= $message ?>
                        <form action="" method="POST" autocomplete="off">
                            <div class="text-center mb-4">
                                <div class="avatar-preview bg-primary mx-auto" style="width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.5rem;">
                                    <?= strtoupper(substr($admin['prenom'], 0, 1) . substr($admin['nom'], 0, 1)) ?>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?= htmlspecialchars($admin['prenom']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= htmlspecialchars($admin['nom']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?= htmlspecialchars($admin['telephone']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Rôle</label>
                                <select name="role" id="role" class="form-select" required>
                                    <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                    <option value="super_admin" <?= $admin['role'] === 'super_admin' ? 'selected' : '' ?>>Super Administrateur</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Enregistrer
                                </button>
                                <a href="admin_dashboard_unifie.php#admins" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Delete confirmation modal (posts to supprimer_admin_def.php) -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Supprimer l'administrateur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <p>Cette action est irréversible. Pour confirmer, tapez <strong>SUPPRIMER</strong> ci-dessous.</p>
        <form id="deleteAdminForm" method="POST" action="supprimer_admin_def.php">
            <input type="hidden" name="admin_id" id="deleteAdminId" value="">
            <div class="mb-3">
                <input type="text" name="confirm_text" id="deleteConfirmText" class="form-control" placeholder="Tapez SUPPRIMER" required pattern="^SUPPRIMER$">
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" form="deleteAdminForm" class="btn btn-danger">Supprimer définitivement</button>
      </div>
    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>

<script>
document.getElementById('openDeleteModalBtn').addEventListener('click', function(e){
    const id = this.getAttribute('data-admin-id');
    const deleteModalEl = document.getElementById('deleteAdminModal');
    document.getElementById('deleteAdminId').value = id;

    if (!deleteModalEl) return;

    // Defensive bootstrap modal initialization with fallback
    let deleteModal = null;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        try {
            deleteModal = new bootstrap.Modal(deleteModalEl);
        } catch (err) {
            console.error('Bootstrap Modal init failed:', err);
            // Simple fallback: show element by toggling classes
            deleteModalEl.style.display = 'block';
            deleteModalEl.classList.add('show');
            deleteModal = deleteModalEl;
        }
    } else {
        // No Bootstrap available - minimal fallback
        deleteModalEl.style.display = 'block';
        deleteModalEl.classList.add('show');
        deleteModal = deleteModalEl;
    }

    if (deleteModal && typeof deleteModal.show === 'function') {
        deleteModal.show();
    }
});

// Formatage automatique du téléphone
const telInput = document.getElementById('telephone');
if (telInput) {
    telInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 2) value = value.substring(0, 2) + ' ' + value.substring(2);
        if (value.length > 5) value = value.substring(0, 5) + ' ' + value.substring(5);
        if (value.length > 8) value = value.substring(0, 8) + ' ' + value.substring(8);
        e.target.value = value;
    });
}
</script>