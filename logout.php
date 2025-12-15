<<<<<<< HEAD
<?php
/**
 * Page de déconnexion
 */

require_once 'src/config/bootstrap.php';

$authController = new AuthController($pdo);
$authController->logout();
?>
=======
<?php
// logout.php
require_once 'session_config.php';

// Détruire complètement la session
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header('Location: login.php');
exit;
>>>>>>> 2fc47109b0d43eb3be3464bd2a12f9f4e8f82762
