<<<<<<< HEAD
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
=======
<?php
session_start();
require 'db.php';

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php');
    exit();
}

$message = "";
$formData = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $formData = [
        'nom' => htmlspecialchars(trim($_POST['nom'])),
        'prenom' => htmlspecialchars(trim($_POST['prenom'])),
        'email' => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
        'telephone' => preg_replace('/[^0-9]/', '', $_POST['telephone']),
        'poste' => htmlspecialchars(trim($_POST['poste'])),
        'departement' => htmlspecialchars($_POST['departement']),
        'adresse' => htmlspecialchars(trim($_POST['adresse'])),
        'date_embauche' => $_POST['date_embauche'],
        'contrat_type' => htmlspecialchars(trim($_POST['contrat_type'])),
        'contrat_duree' => htmlspecialchars(trim($_POST['contrat_duree']))
        // 'password' => password_hash($_POST['password'], PASSWORD_BCRYPT) // Comment

    ];

    // Validation
    $errors = [];
    
    if (empty($formData['nom'])) $errors[] = "Le nom est requis";
    if (empty($formData['prenom'])) $errors[] = "Le prénom est requis";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (strlen($formData['telephone']) < 8) $errors[] = "Numéro de téléphone invalide";
    if (empty($_POST['password']) || strlen($_POST['password']) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    if (empty($formData['contrat_type'])) $errors[] = "Le type de contrat est requis";
    if (empty($formData['date_embauche'])) $errors[] = "La date d'embauche est requise";
    if (empty($formData['departement'])) $errors[] = "Le département est requis";


    // Gestion de l'upload d'image
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/employes/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        // Vérification du fichier
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check === false) {
            $errors[] = "Le fichier n'est pas une image";
        } elseif ($_FILES['image']['size'] > 5000000) {
            $errors[] = "L'image est trop volumineuse (max 5MB)";
        } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = "Seuls JPG, JPEG, PNG et GIF sont autorisés";
        } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = $targetFile;
        } else {
            $errors[] = "Erreur lors de l'upload de l'image";
        }
    }

    if (empty($errors)) {
        // Vérification email existant
        $stmt = $pdo->prepare("SELECT id FROM employes WHERE email = ?");
        $stmt->execute([$formData['email']]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert alert-danger">Un employé avec cet email existe déjà.</div>';
        } else {
            // Hachage du mot de passe
            $passwordHash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            
            // Insertion
$stmt = $pdo->prepare("INSERT INTO employes 
(nom, prenom, email, telephone, poste, departement, adresse, password, photo, date_embauche, contrat_type) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$success = $stmt->execute([
    $formData['nom'],
    $formData['prenom'],
    $formData['email'],
    $formData['telephone'],
    $formData['poste'],
    $formData['departement'],
    $formData['adresse'],
    $passwordHash,
    $imagePath,
    $formData['date_embauche'],
    $formData['contrat_type']
]);



            if ($success) {
                $message = '<div class="alert alert-success">Employé ajouté avec succès!</div>';
                $formData = []; // Réinitialiser après succès
            } else {
                $message = '<div class="alert alert-danger">Erreur lors de l\'ajout.</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">'.implode('<br>', $errors).'</div>';
    }
}
// Générer un mot de passe sécurisé automatiquement (12 caractères alphanumériques)
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
}

// Mot de passe généré automatiquement
$plainPassword = generateRandomPassword(12);
$passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Employé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2.5rem;
            margin: 2rem auto;
            max-width: 700px;
        }
        
        .form-title {
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .form-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-color);
        }
        
        .form-control, .form-select {
            border-radius: 5px;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background-color: #e9ecef;
            border-radius: 5px 0 0 5px !important;
        }
        
        .password-toggle {
            cursor: pointer;
            border-radius: 0 5px 5px 0 !important;
        }
        
        .department-icon {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .preview-image {
            max-width: 150px;
            max-height: 150px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        .file-upload-label {
            display: block;
            padding: 0.75rem;
            border: 1px dashed #ced4da;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title"><i class="fas fa-user-plus me-2"></i>Ajouter un Employé</h2>
            
            <?= $message ?>
            
            <form action="" method="POST" enctype="multipart/form-data" autocomplete="off">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prenom" name="prenom" 
                               value="<?= $formData['prenom'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?= $formData['nom'] ?? '' ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= $formData['email'] ?? '' ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                               value="<?= $formData['telephone'] ?? '' ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="poste" class="form-label">Poste <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="poste" name="poste" 
                           value="<?= $formData['poste'] ?? '' ?>" required>
                </div>

                <div class="mb-3">
                    <label for="departement" class="form-label">Département <span class="text-danger">*</span></label>
                    <select name="departement" class="form-select" id="departement" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="depart_formation" <?= ($formData['departement'] ?? '') === 'depart_formation' ? 'selected' : '' ?>>
                            <i class="fas fa-graduation-cap department-icon"></i>Département Formation
                        </option>
                        <option value="depart_communication" <?= ($formData['departement'] ?? '') === 'depart_communication' ? 'selected' : '' ?>>
                            <i class="fas fa-bullhorn department-icon"></i>Département Communication
                        </option>
                        <option value="depart_informatique" <?= ($formData['departement'] ?? '') === 'depart_informatique' ? 'selected' : '' ?>>
                            <i class="fas fa-laptop-code department-icon"></i>Département Informatique
                        </option>
                        <option value="depart_consulting" <?= ($formData['departement'] ?? '') === 'depart_consulting' ? 'selected' : '' ?>>
                            <i class="fas fa-users department-icon"></i>Département GRH
                        </option>
                        <option value="depart_marketing&vente" <?= ($formData['departement'] ?? '') === 'depart_marketing&vente' ? 'selected' : '' ?>>
                            <i class="fas fa-sell department-icon"></i>Département GRH
                        </option>
                        <option value="administration" <?= ($formData['departement'] ?? '') === 'administration' ? 'selected' : '' ?>>
                            <i class="fas fa-building department-icon"></i>Administration
                        </option>
                    </select>
                </div>
                <div class="mb-3">
    <label for="contrat_type" class="form-label">Type de contrat <span class="text-danger">*</span></label>
    <select name="contrat_type" id="contrat_type" class="form-select" required>
        <option value="">-- Sélectionner --</option>
        <option value="CDI" <?= ($formData['contrat_type'] ?? '') === 'CDI' ? 'selected' : '' ?>>CDI</option>
        <option value="CDD" <?= ($formData['contrat_type'] ?? '') === 'CDD' ? 'selected' : '' ?>>CDD</option>
        <option value="Stage" <?= ($formData['contrat_type'] ?? '') === 'Stage' ? 'selected' : '' ?>>Stage</option>
        <option value="Freelance" <?= ($formData['contrat_type'] ?? '') === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
    </select>
</div>

<div class="mb-3">
    <label for="date_embauche" class="form-label">Date d'embauche <span class="text-danger">*</span></label>
    <input type="date" class="form-control" id="date_embauche" name="date_embauche"
           value="<?= $formData['date_embauche'] ?? '' ?>" required>
</div>


                <div class="mb-3">
                    <label for="adresse" class="form-label">Adresse <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= $formData['adresse'] ?? '' ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required
                               oninput="checkPasswordStrength(this.value)">
                        <span class="input-group-text password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="form-text text-muted">Minimum 8 caractères</small>
                        <small id="strengthText" class="form-text"></small>
                    </div>
                    <div id="password-strength" class="password-strength"></div>
                </div>

                <div class="mb-4">
                    <label for="image" class="form-label">Photo de profil</label>
                    <label for="image" class="file-upload-label">
                        <i class="fas fa-camera me-2"></i>
                        <span id="file-name">Choisir une image...</span>
                    </label>
                    <input type="file" name="image" id="image" class="d-none" accept="image/*">
                    <img id="image-preview" class="preview-image" src="#" alt="Aperçu de l'image">
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Ajouter l'employé
                    </button>
                    <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Afficher/masquer le mot de passe
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Formatage automatique du téléphone
        document.getElementById('telephone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + ' ' + value.substring(2);
            }
            if (value.length > 5) {
                value = value.substring(0, 5) + ' ' + value.substring(5);
            }
            if (value.length > 8) {
                value = value.substring(0, 8) + ' ' + value.substring(8);
            }
            e.target.value = value;
        });

        // Aperçu de l'image sélectionnée
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('file-name').textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('image-preview');
                    preview.src = event.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Vérification de la force du mot de passe
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
            
            let color, text;
            switch(strength) {
                case 0:
                    color = '#dc3545';
                    text = 'Très faible';
                    break;
                case 1:
                    color = '#fd7e14';
                    text = 'Faible';
                    break;
                case 2:
                    color = '#ffc107';
                    text = 'Moyen';
                    break;
                case 3:
                    color = '#28a745';
                    text = 'Fort';
                    break;
                case 4:
                    color = '#20c997';
                    text = 'Très fort';
                    break;
                default:
                    color = '#6c757d';
                    text = '';
            }
            
            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.style.backgroundColor = color;
            document.getElementById('strengthText').textContent = text;
            document.getElementById('strengthText').style.color = color;
        }
    </script>
</body>
</html>
>>>>>>> 2fc47109b0d43eb3be3464bd2a12f9f4e8f82762
