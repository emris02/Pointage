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