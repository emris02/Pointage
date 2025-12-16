<?php
/**
 * Page de connexion améliorée
 */

require_once 'src/config/bootstrap.php';

$authController = new AuthController($pdo);

// Si déjà connecté → redirection
if ($authController->isLoggedIn()) {
    $authController->redirectByRole();
}

$message = '';
$messageType = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

$pageTitle = 'Connexion - Xpert Pro';
$bodyClass  = 'login-page';
$additionalCSS = ['assets/css/login.css'];
?>

<?php include 'partials/header.php'; ?>

<div class="login-wrapper">

    <div class="login-card shadow-lg animate-fade">

        <div class="logo-section text-center mb-4">
            <img src="assets/img/xpertpro.png" alt="Xpert Pro" class="login-logo">
            <h3 class="fw-bold mt-3">Bienvenue sur Xpert Pro</h3>
            <p class="text-muted">Connectez-vous à votre espace</p>
        </div>

        <!-- Message d'erreur -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Adresse email</label>
                <div class="input-group input-group-custom">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input 
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="exemple@email.com"
                        required
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                    >
                </div>
            </div>

            <!-- Mot de passe -->
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Mot de passe</label>
                <div class="input-group input-group-custom">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input 
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Votre mot de passe"
                        required
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Options -->
            <div class="d-flex justify-content-between mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                </div>
                <a href="forgot_password.php" class="link-orange">Mot de passe oublié ?</a>
            </div>

            <!-- Bouton -->
            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="fas fa-sign-in-alt me-2"></i> Connexion
            </button>

        </form>

        <div class="text-center mt-4">
            <a href="index.php" class="link-dark text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
            </a>
        </div>

    </div>
</div>

<?php
$inlineJS = "
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
    document.getElementById('email').focus();
});
";
include 'src/views/partials/footer.php';
?>
