<?php
/**
 * Page de connexion professionnelle - Xpert Pro
 */

require_once 'src/config/bootstrap.php';

$authController = new AuthController($pdo);

// Si déjà connecté → redirection selon rôle
if ($authController->isLoggedIn()) {
    $authController->redirectByRole();
}

// Initialisation des messages
$message = '';
$messageType = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->login();

    if ($result['success']) {
        $_SESSION['message'] = $result['message'];
        $_SESSION['message_type'] = 'success';
        $_SESSION['message_icon'] = 'check-circle';
        header("Location: " . $result['redirect']);
        exit();
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Meta et CSS spécifiques
$pageTitle = 'Connexion - Xpert Pro';
$bodyClass  = 'login-page';
$additionalCSS = ['assets/css/login.css'];

include 'partials/header.php';
?>

<div class="login-wrapper d-flex justify-content-center align-items-center min-vh-100">

    <div class="login-card shadow-lg animate-fade p-4 rounded-4" style="max-width: 420px; width: 100%;">

        <div class="logo-section text-center mb-4">
            <img src="assets/img/xpertpro.png" alt="Xpert Pro Logo" class="login-logo mb-2" style="max-height: 80px;">
            <h3 class="fw-bold mt-2">Bienvenue sur Xpert Pro</h3>
            <p class="text-muted">Connectez-vous à votre espace sécurisé</p>
        </div>

        <!-- Affichage des messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>

            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Adresse email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input 
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="exemple@email.com"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        aria-describedby="emailHelp"
                    >
                </div>
                <div id="emailHelp" class="form-text">Nous ne partagerons jamais votre email.</div>
            </div>

            <!-- Mot de passe -->
            <div class="mb-3 position-relative">
                <label for="password" class="form-label fw-semibold">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input 
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Votre mot de passe"
                        required
                    >
                    <button type="button" class="btn btn-outline-secondary btn-sm password-toggle" 
                            onclick="togglePassword()" aria-label="Afficher/Masquer le mot de passe">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Options -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                </div>
                <a href="forgot_password.php" class="link-orange">Mot de passe oublié ?</a>
            </div>

            <!-- Bouton de connexion -->
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fas fa-sign-in-alt me-2"></i> Connexion
            </button>

        </form>

        <div class="text-center mt-4">
            <a href="index.php" class="link-dark text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i> Retour à l'accueil
            </a>
        </div>

    </div>
</div>

<?php
// JS inline pour l'UI/UX
$inlineJS = <<<JS
function togglePassword() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');

    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const emailInput = document.getElementById('email');
    if(emailInput) emailInput.focus();
});
JS;

include 'partials/footer.php';
?>
