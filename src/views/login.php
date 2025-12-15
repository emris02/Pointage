<?php
/**
 * Vue de connexion
 */

$pageTitle = 'Connexion - Xpert Pro';
$bodyClass = 'login-page';
$additionalCSS = ['public/assets/css/login.css'];
?>

<?php include 'partials/header.php'; ?>

<div class="login-container">
    <div class="login-card">
        <div class="logo-container">
            <img src="public/assets/img/xpertpro.png" alt="Xpert Pro" class="img-fluid">
            <h2 class="mb-4">Connexion à votre espace</h2>
        </div>

        <?php include 'partials/alerts.php'; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="votre@email.com" required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
            </div>

            <div class="mb-3 position-relative">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Votre mot de passe" required>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                </div>
                <a href="forgot_password.php" class="text-decoration-none">Mot de passe oublié ?</a>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Se connecter
                </button>
            </div>
        </form>

        <div class="footer-links">
            <p><a href="index.php" class="text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> Retour à l'accueil
            </a></p>
        </div>
    </div>
</div>

<?php
$inlineJS = "
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Focus automatique sur le champ email
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('email').focus();
});
";
include 'partials/footer.php';
?>
