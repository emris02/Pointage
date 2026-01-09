<?php
// db.php : Configuration et connexion PDO

// // Constantes de sécurité
// define('SECRET_KEY', 'GroupeXpert2025!'); // Clé secrète pour HMAC
// define('TOKEN_PREFIX', 'XPERT'); // Préfixe pour les tokens
// define('TOKEN_EXPIRATION', 7200); // secondes (2 heures)

// Configuration base

$host     = 'sql103.byethost7.com';
$dbname   = 'b7_39535458_pointage';
$username = 'b7_39535458';
$password = 'Bi6tPXJDDmmRZHm';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Erreur BDD: " . $e->getMessage());
    // Ne PAS exposer le détail en prod
    http_response_code(503);

    // Decide response format: JSON for API/AJAX callers, simple HTML for browser pages
    $isJson = false;
    if (php_sapi_name() !== 'cli') {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if (stripos($accept, 'application/json') !== false || $xhr) {
            $isJson = true;
        }
    }

    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Service indisponible']);
    } else {
        // Minimal friendly HTML page so included views render a user-facing message
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Service indisponible</title>';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#f8f9fa;color:#212529;padding:40px} .card{max-width:720px;margin:32px auto;padding:24px;border-radius:8px;background:#fff;box-shadow:0 4px 18px rgba(0,0,0,.06)}</style>';
        echo '</head><body><div class="card"><h1>Service indisponible</h1><p>Impossible de se connecter à la base de données. Veuillez réessayer plus tard ou contacter l\'administrateur.</p></div></body></html>';
    }
    exit;
}