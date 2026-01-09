<?php
/**
 * Ajouter un administrateur - Page avec header/sidebar canoniques
 */
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

$authController = new AuthController($pdo);
if (!$authController->isSuperAdmin()) {
    header('Location: admin_dashboard_unifie.php');
    exit();
}

$message = "";
$formData = []; // Pour conserver les données en cas d'erreur
$success = false;

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
}

// Générer un mot de passe par défaut
$defaultPassword = generateRandomPassword(12);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $formData = [
        'nom' => htmlspecialchars(trim($_POST['nom'])),
        'prenom' => htmlspecialchars(trim($_POST['prenom'])),
        'email' => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
        'telephone' => preg_replace('/[^0-9]/', '', $_POST['telephone']),
        'poste' => htmlspecialchars(trim($_POST['poste'])),
        'departement' => htmlspecialchars($_POST['departement']),
        'role' => $_POST['role'] ?? 'admin',
        'adresse' => htmlspecialchars(trim($_POST['adresse']))
    ];

    // Récupérer le mot de passe ou utiliser le défaut
    $password = !empty($_POST['password']) ? $_POST['password'] : $defaultPassword;
    
    // Validation
    $errors = [];
    
    if (empty($formData['nom'])) $errors[] = "Le nom est requis";
    if (empty($formData['prenom'])) $errors[] = "Le prénom est requis";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (strlen($formData['telephone']) < 8) $errors[] = "Numéro de téléphone invalide";
    if (strlen($password) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères";

    if (empty($errors)) {
        // Vérification email existant
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$formData['email']]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert alert-danger">Un compte avec cet email existe déjà.</div>';
        } else {
            // Hachage du mot de passe
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Insertion
            $stmt = $pdo->prepare("INSERT INTO admins 
                (nom, prenom, email, telephone, poste, departement, adresse, password, role, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            if ($stmt->execute([
                $formData['nom'],
                $formData['prenom'],
                $formData['email'],
                $formData['telephone'],
                $formData['poste'],
                $formData['departement'],
                $formData['adresse'],
                $passwordHash,
                $formData['role'],
                $_SESSION['admin_id']
            ])) {
                $success = true;
                
                $message = '<div class="alert alert-success">✅ Administrateur créé avec succès !</div>';
                $formData = []; // Vider le formulaire
                $defaultPassword = generateRandomPassword(12); // Générer un nouveau mot de passe par défaut
            } else {
                $message = '<div class="alert alert-danger">❌ Erreur lors de la création du compte.</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">❌ ' . implode('<br>', $errors) . '</div>';
    }
}

$pageTitle = 'Ajouter un Administrateur';
$additionalCSS = ['assets/css/admin.css', 'assets/css/profil.css'];
?>

<?php include 'partials/header.php'; ?>
<?php include 'src/views/partials/sidebar_canonique.php'; ?>

<main class="main-content py-4">
    <div class="container-fluid px-3 px-md-4">
        <div class="form-centered-wrapper">
            <div class="form-card-container">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-user-plus me-2"></i>
                            Ajouter un Administrateur
                        </h2>
                        <p class="text-muted mb-0">Créer un nouveau compte administrateur dans le système</p>
                    </div>
                    <div>
                        <a href="admin_dashboard_unifie.php#admins" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour au dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-shield me-2"></i>Informations du nouvel administrateur
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" autocomplete="off">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Nom <span class="text-danger">*</span></label>
                                        <input type="text" name="nom" class="form-control" 
                                               value="<?= htmlspecialchars($formData['nom'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" name="prenom" class="form-control" 
                                               value="<?= htmlspecialchars($formData['prenom'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Téléphone <span class="text-danger">*</span></label>
                                        <input type="tel" name="telephone" class="form-control" 
                                               value="<?= htmlspecialchars($formData['telephone'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Poste</label>
                                        <input type="text" name="poste" class="form-control" 
                                               value="<?= htmlspecialchars($formData['poste'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Département</label>
                                        <select name="departement" class="form-select">
                                            <option value="">Sélectionner un département</option>
                                            <option value="depart_informatique" <?= ($formData['departement'] ?? '') === 'depart_informatique' ? 'selected' : '' ?>>Informatique</option>
                                            <option value="depart_rh" <?= ($formData['departement'] ?? '') === 'depart_rh' ? 'selected' : '' ?>>Ressources Humaines</option>
                                            <option value="depart_finance" <?= ($formData['departement'] ?? '') === 'depart_finance' ? 'selected' : '' ?>>Finance</option>
                                            <option value="depart_communication" <?= ($formData['departement'] ?? '') === 'depart_communication' ? 'selected' : '' ?>>Communication</option>
                                            <option value="depart_consulting" <?= ($formData['departement'] ?? '') === 'depart_consulting' ? 'selected' : '' ?>>Consulting</option>
                                            <option value="depart_marketing&vente" <?= ($formData['departement'] ?? '') === 'depart_marketing&vente' ? 'selected' : '' ?>>Marketing & Vente</option>
                                            <option value="administration" <?= ($formData['departement'] ?? '') === 'administration' ? 'selected' : '' ?>>Administration</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Rôle <span class="text-danger">*</span></label>
                                        <select name="role" class="form-select" required>
                                            <option value="admin" <?= ($formData['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                            <option value="super_admin" <?= ($formData['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Administrateur</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Mot de passe <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" name="password" id="adminPasswordInput" class="form-control" 
                                                   value="<?= htmlspecialchars($defaultPassword) ?>" required 
                                                   placeholder="Minimum 8 caractères">
                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleAdminPassword()" title="Afficher/Masquer le mot de passe">
                                                <i class="fas fa-eye" id="adminPasswordToggleIcon"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="generateAdminPassword()" title="Générer un nouveau mot de passe">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Minimum 8 caractères</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Adresse</label>
                                <textarea name="adresse" class="form-control" rows="3" 
                                          placeholder="Adresse complète de l'administrateur"><?= htmlspecialchars($formData['adresse'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Créer l'administrateur
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Réinitialiser
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Informations et aide -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Informations importantes
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Rôles disponibles :</strong>
                            <ul class="mt-2 mb-0">
                                <li><span class="badge bg-primary">Admin</span> - Gestion des employés et demandes</li>
                                <li><span class="badge bg-danger">Super Admin</span> - Accès complet au système</li>
                            </ul>
                        </div>
                        <div class="mb-3">
                            <strong>Mot de passe :</strong><br>
                            <small class="text-muted">Minimum 8 caractères. Un mot de passe sécurisé est généré automatiquement.</small>
                        </div>
                        <div class="mb-0">
                            <strong>Email :</strong><br>
                            <small class="text-muted">Doit être unique dans le système. Sera utilisé pour la connexion.</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Sécurité
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning alert-sm">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Attention :</strong> Seuls les super administrateurs peuvent créer de nouveaux comptes admin.
                        </div>
                        <div class="alert alert-info alert-sm">
                            <i class="fas fa-key me-2"></i>
                            <strong>Important :</strong> Communiquez le mot de passe à l'administrateur de manière sécurisée.
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>
</main>

<script>
function generateAdminPassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('adminPasswordInput').value = password;
}

function toggleAdminPassword() {
    const passwordInput = document.getElementById('adminPasswordInput');
    const toggleIcon = document.getElementById('adminPasswordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

<?php include 'partials/footer.php'; ?>