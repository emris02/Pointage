<?php
// Exemple de service d'authentification pour Pointage
namespace Pointage\Services;

class AuthService {
    public static function isAuthenticated() {
        return isset($_SESSION['employe_id']) || isset($_SESSION['admin_id']);
    }

    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header('Location: /pointage/login.php');
            exit;
        }
    }

    public static function getUserId() {
        return $_SESSION['employe_id'] ?? $_SESSION['admin_id'] ?? null;
    }

    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }
}
