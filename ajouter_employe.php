<?php
/**
 * Ajouter un employé - Page avec header/sidebar canoniques
 */
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

$authController = new AuthController($pdo);
if (!$authController->isAdmin()) {
    header('Location: admin_dashboard_unifie.php');
    exit();
}

// Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_DIR', 'uploads/employes/');

// Initialisation des variables
$message = "";
$formData = [];
$errors = [];
$success = false;
$plainPassword = generateRandomPassword(12);

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
}

// Fonction de nettoyage des données
function cleanInput($data, $type = 'text') {
    $data = trim($data);
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'phone':
            return preg_replace('/[^0-9+\-\s]/', '', $data);
        case 'date':
            return $data;
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage et validation des données
    $formData = [
        'nom' => cleanInput($_POST['nom'] ?? '', 'text'),
        'prenom' => cleanInput($_POST['prenom'] ?? '', 'text'),
        'email' => cleanInput($_POST['email'] ?? '', 'email'),
        'telephone' => cleanInput($_POST['telephone'] ?? '', 'phone'),
        'poste' => cleanInput($_POST['poste'] ?? '', 'text'),
        'departement' => cleanInput($_POST['departement'] ?? '', 'text'),
        'adresse' => cleanInput($_POST['adresse'] ?? '', 'text'),
        'date_embauche' => cleanInput($_POST['date_embauche'] ?? '', 'date'),
        'contrat_type' => cleanInput($_POST['contrat_type'] ?? '', 'text'),
        'contrat_duree' => cleanInput($_POST['contrat_duree'] ?? '', 'text'),
        'password' => $_POST['password'] ?? $plainPassword
    ];

    // Validation des champs obligatoires
    $requiredFields = [
        'nom' => "Le nom est requis",
        'prenom' => "Le prénom est requis",
        'email' => "L'email est requis",
        'telephone' => "Le téléphone est requis",
        'poste' => "Le poste est requis",
        'departement' => "Le département est requis",
        'date_embauche' => "La date d'embauche est requise"
    ];

    foreach ($requiredFields as $field => $errorMsg) {
        if (empty($formData[$field])) {
            $errors[] = $errorMsg;
        }
    }

    // Validation email
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }

    // Vérification email unique
    if (!empty($formData['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM employes WHERE email = ?");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = "Un employé avec cet email existe déjà";
        }
    }

    // Validation mot de passe
    if (empty($formData['password']) || strlen($formData['password']) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    }

    // Gestion de l'upload de photo
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
            $errors[] = "Type de fichier non autorisé. Formats acceptés : " . implode(', ', ALLOWED_FILE_TYPES);
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = "Fichier trop volumineux. Taille maximum : " . (MAX_FILE_SIZE / 1024 / 1024) . "MB";
        } else {
            // Créer le dossier s'il n'existe pas
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
            $photoPath = UPLOAD_DIR . uniqid() . '_' . basename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $photoPath)) {
                $errors[] = "Erreur lors de l'upload de la photo";
            }
        }
    }

    // Si aucune erreur, insertion en base
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO employes 
                (nom, prenom, email, telephone, poste, departement, adresse, date_embauche, 
                 contrat_type, contrat_duree, password, photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $passwordHash = password_hash($formData['password'], PASSWORD_BCRYPT);
            
            $stmt->execute([
                $formData['nom'],
                $formData['prenom'],
                $formData['email'],
                $formData['telephone'],
                $formData['poste'],
                $formData['departement'],
                $formData['adresse'],
                $formData['date_embauche'],
                $formData['contrat_type'],
                $formData['contrat_duree'],
                $passwordHash,
                $photoPath
            ]);
            
            $success = true;
            
            $message = '<div class="alert alert-success">✅ Employé créé avec succès !</div>';
            $formData = []; // Vider le formulaire
            $plainPassword = generateRandomPassword(12); // Nouveau mot de passe
            
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">❌ Erreur lors de la création : ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">❌ ' . implode('<br>', $errors) . '</div>';
    }
}

$pageTitle = 'Ajouter un Employé';
$additionalCSS = ['assets/css/admin.css'];
?>

<?php include 'partials/header.php'; ?>
<?php include 'src/views/partials/sidebar_canonique.php'; ?>

<div class="main-content" style="margin-left: 250px; padding: 20px; width: calc(100% - 250px);">
    <div class="container mt-4 mx-auto" style="max-width: 1400px;">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-user-plus me-2"></i>
                            Ajouter un Employé
                        </h2>
                        <p class="text-muted mb-0">Créer un nouveau compte employé dans le système</p>
                    </div>
                    <div>
                        <a href="admin_dashboard_unifie.php#employes" class="btn btn-outline-secondary">
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
                            <i class="fas fa-users me-2"></i>Informations du nouvel employé
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" autocomplete="off">
                            <!-- Informations personnelles -->
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-user me-2"></i>Informations personnelles
                            </h6>
                            
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

                            <div class="mb-3">
                                <label class="form-label fw-bold">Adresse</label>
                                <textarea name="adresse" class="form-control" rows="3" 
                                          placeholder="Adresse complète de l'employé"><?= htmlspecialchars($formData['adresse'] ?? '') ?></textarea>
                            </div>

                            <!-- Informations professionnelles -->
                            <hr>
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-briefcase me-2"></i>Informations professionnelles
                            </h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Poste <span class="text-danger">*</span></label>
                                        <input type="text" name="poste" class="form-control" 
                                               value="<?= htmlspecialchars($formData['poste'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Département <span class="text-danger">*</span></label>
                                        <select name="departement" class="form-select" required>
                                            <option value="">Sélectionner un département</option>
                                            <option value="depart_informatique" <?= ($formData['departement'] ?? '') === 'depart_informatique' ? 'selected' : '' ?>>Informatique</option>
                                            <option value="depart_rh" <?= ($formData['departement'] ?? '') === 'depart_rh' ? 'selected' : '' ?>>Ressources Humaines</option>
                                            <option value="depart_finance" <?= ($formData['departement'] ?? '') === 'depart_finance' ? 'selected' : '' ?>>Finance</option>
                                            <option value="depart_communication" <?= ($formData['departement'] ?? '') === 'depart_communication' ? 'selected' : '' ?>>Communication</option>
                                            <option value="depart_consulting" <?= ($formData['departement'] ?? '') === 'depart_consulting' ? 'selected' : '' ?>>Consulting</option>
                                            <option value="depart_marketing&vente" <?= ($formData['departement'] ?? '') === 'depart_marketing&vente' ? 'selected' : '' ?>>Marketing & Vente</option>
                                            <option value="depart_formation" <?= ($formData['departement'] ?? '') === 'depart_formation' ? 'selected' : '' ?>>Formation</option>
                                            <option value="administration" <?= ($formData['departement'] ?? '') === 'administration' ? 'selected' : '' ?>>Administration</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Date d'embauche <span class="text-danger">*</span></label>
                                        <input type="date" name="date_embauche" class="form-control" 
                                               value="<?= htmlspecialchars($formData['date_embauche'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Type de contrat</label>
                                        <select name="contrat_type" class="form-select">
                                            <option value="">Sélectionner</option>
                                            <option value="CDI" <?= ($formData['contrat_type'] ?? '') === 'CDI' ? 'selected' : '' ?>>CDI</option>
                                            <option value="CDD" <?= ($formData['contrat_type'] ?? '') === 'CDD' ? 'selected' : '' ?>>CDD</option>
                                            <option value="Stage" <?= ($formData['contrat_type'] ?? '') === 'Stage' ? 'selected' : '' ?>>Stage</option>
                                            <option value="Freelance" <?= ($formData['contrat_type'] ?? '') === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Durée du contrat</label>
                                        <input type="text" name="contrat_duree" class="form-control" 
                                               value="<?= htmlspecialchars($formData['contrat_duree'] ?? '') ?>" 
                                               placeholder="Ex: 6 mois, 1 an, Indéterminé">
                                    </div>
                                </div>
                            </div>

                            <!-- Photo et mot de passe -->
                            <hr>
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-camera me-2"></i>Photo et accès
                            </h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Photo de profil</label>
                                        <input type="file" name="photo" class="form-control" accept="image/*">
                                        <small class="text-muted">Formats acceptés : JPG, PNG, GIF (max 5MB)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Mot de passe <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" name="password" id="passwordInput" class="form-control" 
                                                   value="<?= htmlspecialchars($plainPassword) ?>" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()" title="Afficher/Masquer le mot de passe">
                                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="generatePassword()" title="Générer un nouveau mot de passe">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Minimum 8 caractères</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Créer l'employé
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
                            <strong>Départements disponibles :</strong>
                            <ul class="mt-2 mb-0">
                                <li>Informatique</li>
                                <li>Ressources Humaines</li>
                                <li>Finance</li>
                                <li>Communication</li>
                                <li>Consulting</li>
                                <li>Marketing & Vente</li>
                                <li>Formation</li>
                                <li>Administration</li>
                            </ul>
                        </div>
                        <div class="mb-3">
                            <strong>Types de contrats :</strong>
                            <ul class="mt-2 mb-0">
                                <li>CDI (Contrat à Durée Indéterminée)</li>
                                <li>CDD (Contrat à Durée Déterminée)</li>
                                <li>Stage</li>
                                <li>Freelance</li>
                            </ul>
                        </div>
                        <div class="mb-0">
                            <strong>Photo :</strong><br>
                            <small class="text-muted">Optionnelle. Formats acceptés : JPG, PNG, GIF (max 5MB)</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Sécurité
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info alert-sm">
                            <i class="fas fa-key me-2"></i>
                            <strong>Mot de passe :</strong> Un mot de passe sécurisé est généré automatiquement.
                        </div>
                        <div class="alert alert-warning alert-sm">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important :</strong> Communiquez le mot de passe à l'employé de manière sécurisée.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('passwordInput').value = password;
}

function togglePassword() {
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('passwordToggleIcon');
            
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