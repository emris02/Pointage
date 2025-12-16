<?php
/**
 * Constantes globales de l'application
 */

// Configuration de session
 //
date_default_timezone_set('Africa/Bamako');

// Constantes de sécurité
define('SECRET_KEY', 'GroupeXpert2025!');
define('TOKEN_PREFIX', 'XPERT');
define('TOKEN_EXPIRATION', 7200);

// Clé API pour terminaux (à provisionner sur chaque borne)
define('TERMINAL_API_KEY', 'CHANGE_ME_TERMINAL_KEY_2025');

// DEBUG toujours activé pour le diagnostic
define('DEBUG', true);

// Constantes de l'application
define('APP_NAME', 'Pointage Xpert Pro');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/Pointage');

// Constantes de rôles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_EMPLOYE', 'employe');

// Constantes de pointage
define('POINTAGE_ARRIVEE', 'arrivee');
define('POINTAGE_DEPART', 'depart');

// Chemins
define('ROOT_PATH', dirname(__DIR__, 2));
define('SRC_PATH', ROOT_PATH . '/src');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEWS_PATH', SRC_PATH . '/views');
define('MODELS_PATH', SRC_PATH . '/models');
define('CONTROLLERS_PATH', SRC_PATH . '/controllers');
