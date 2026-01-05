<<<<<<< HEAD
<?php
/**
 * Page de déconnexion
 */

require_once 'src/config/bootstrap.php';

$authController = new AuthController($pdo);
$authController->logout();
?>