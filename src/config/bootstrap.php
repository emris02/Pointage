<?php
/**
 * Bootstrap de l'application
 * Fichier d'initialisation principal
 */

/**
 * === Gestion de la session (UNIQUE) ===
 * Évite les erreurs en production (ByetHost)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chargement des constantes
require_once __DIR__ . '/constants.php';

// Chargement de la connexion à la base de données
require_once __DIR__ . '/db.php';

// Autoloader simple pour les classes
spl_autoload_register(function ($class) {
    $directories = [
        MODELS_PATH,
        CONTROLLERS_PATH,
        SRC_PATH . '/services'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Configuration des erreurs
if (defined('DEBUG') && DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Compatibilité : alias pour la classe Pointage namespacée
if (class_exists('\PointagePro\Models\Pointage')) {
    if (!class_exists('Pointage')) {
        class_alias('\PointagePro\Models\Pointage', 'Pointage');
    }
    if (!class_exists('Pointage\\Models\\Pointage')) {
        class_alias('\PointagePro\Models\Pointage', 'Pointage\\Models\\Pointage');
    }
}

// Charger les paramètres d'administration (table `settings`) si disponible
$APP_SETTINGS = [];
try {
    if (class_exists('Settings', true) && isset($pdo)) {
        $settingsModel = new Settings($pdo);
        $rows = $settingsModel->getAll();
        foreach ($rows as $r) {
            // colonne attendue: cle, valeur
            $APP_SETTINGS[$r['cle']] = $r['valeur'];
        }
    }
} catch (Throwable $e) {
    // Ne pas casser l'app si la table n'existe pas ou erreur DB
    $APP_SETTINGS = [];
}
