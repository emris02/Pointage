<?php
// admin_system.php
// Page d'administration système (affichage / diagnostics)

 //

$pageTitle = 'Système - Admin';
$bodyClass = 'admin-system';

// Inclure le header (partiel) qui charge Bootstrap, FontAwesome, etc.
require_once __DIR__ . '/partials/header.php';

// Diagnostic variables
$phpVersion = PHP_VERSION;
$uptime = null;
$dbVersion = 'Non disponible';
$dbNote = '';
$uploadsWritable = is_writable(__DIR__ . '/uploads');
$htaccessExists = file_exists(__DIR__ . '/.htaccess');
$htaccessBlocksUploads = false;
$logsCount = 0;

// Lire .htaccess pour déceler règle bloquante sur uploads
if ($htaccessExists) {
    $ht = file_get_contents(__DIR__ . '/.htaccess');
    if (strpos($ht, "RewriteRule ^uploads/ - [F,L]") !== false || strpos($ht, "RewriteRule ^uploads/") !== false) {
        $htaccessBlocksUploads = true;
    }
}

// Compter fichiers de logs si dossier présent
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    $files = scandir($logsDir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_file($logsDir . '/' . $f)) $logsCount++;
    }
}

// Essayer d'obtenir la version de la base (si le projet expose un PDO via src/config/db.php)
try {
    $dbFile = __DIR__ . '/src/config/db.php';
    if (file_exists($dbFile)) {
        // Le fichier peut définir $pdo, ou retourner une connexion; nous essayons d'inclure sans bruit
        include_once $dbFile;

        // Plusieurs projets définissent $pdo ou $db; tenter quelques variantes
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->query('SELECT VERSION()');
            $ver = $stmt ? $stmt->fetchColumn() : false;
            if ($ver) $dbVersion = $ver;
        } elseif (isset($db) && $db instanceof PDO) {
            $stmt = $db->query('SELECT VERSION()');
            $ver = $stmt ? $stmt->fetchColumn() : false;
            if ($ver) $dbVersion = $ver;
        } else {
            // Tentative: essayer une nouvelle connexion si les constantes sont définies
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $tmp = new PDO($dsn, DB_USER, DB_PASS ?? '');
                $ver = $tmp->query('SELECT VERSION()')->fetchColumn();
                if ($ver) $dbVersion = $ver;
            }
        }
    } else {
        $dbNote = 'Fichier de config DB introuvable (src/config/db.php)';
    }
} catch (Throwable $e) {
    $dbNote = 'Erreur lecture DB: ' . $e->getMessage();
}

// Tentative d'uptime (si disponible sur Windows via exec) — facultative
try {
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        // sous Windows, uptime n'est pas standard — on peut lire via systeminfo, mais ça peut être lent
        $uptime = null;
    } else {
        $u = @shell_exec('uptime -p');
        if ($u) $uptime = trim($u);
    }
} catch (Throwable $e) {
    $uptime = null;
}

?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h4 mb-1">Système</h1>
            <div class="small text-muted">Informations et diagnostics rapides du serveur</div>
        </div>
        <div>
            <a class="btn btn-outline-primary me-2" href="#" onclick="location.reload(); return false;">Rafraîchir</a>
            <a class="btn btn-outline-secondary" href="admin_dashboard_unifie.php">Retour</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">PHP</h5>
                    <p class="mb-1"><strong>Version :</strong> <?= htmlspecialchars($phpVersion) ?></p>
                    <?php if ($uptime): ?>
                        <p class="mb-0"><strong>Uptime :</strong> <?= htmlspecialchars($uptime) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Base de données</h5>
                    <p class="mb-1"><strong>Version :</strong> <?= htmlspecialchars($dbVersion) ?></p>
                    <?php if ($dbNote): ?>
                        <p class="small text-muted mb-0"><?= htmlspecialchars($dbNote) ?></p>
                    <?php endif; ?>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-primary" disabled>Exporter la base (à implémenter)</button>
                        <button class="btn btn-sm btn-outline-danger ms-2" disabled>Réparer (à implémenter)</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Fichiers & logs</h5>
                    <p class="mb-1"><strong>Uploads accessible (writable) :</strong>
                        <?= $uploadsWritable ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>' ?></p>
                    <p class="mb-1"><strong>Nombre de logs :</strong> <?= intval($logsCount) ?></p>
                    <p class="mb-0"><strong>.htaccess :</strong>
                        <?= $htaccessExists ? '<span class="badge bg-secondary">Présent</span>' : '<span class="badge bg-warning">Absent</span>' ?>
                    </p>
                    <?php if ($htaccessBlocksUploads): ?>
                        <p class="small text-muted mt-2">Votre `.htaccess` contient une règle interdisant l'accès direct au dossier `uploads/` — c'est volontaire pour la sécurité. Utilisez un proxy (image.php) pour délivrer des images si nécessaire.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Détails</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th style="width:35%">Chemin du projet</th>
                                    <td><?= htmlspecialchars(__DIR__) ?></td>
                                </tr>
                                <tr>
                                    <th>Utilisateur courant</th>
                                    <td><?= htmlspecialchars($_SESSION['prenom'] ?? 'N/A') ?> <?= htmlspecialchars($_SESSION['nom'] ?? '') ?></td>
                                </tr>
                                <tr>
                                    <th>Theme actif</th>
                                    <td><?= htmlspecialchars($APP_SETTINGS['theme'] ?? 'clair') ?></td>
                                </tr>
                                <tr>
                                    <th>Logs (dossier)</th>
                                    <td><?= is_dir($logsDir) ? htmlspecialchars(realpath($logsDir)) : 'Non configuré' ?></td>
                                </tr>
                                <tr>
                                    <th>Actions disponibles</th>
                                    <td>
                                        <div class="mb-2">
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Vider cache (implémenter)</button>
                                            <button class="btn btn-sm btn-outline-danger ms-2" disabled>Supprimer logs (implémenter)</button>
                                        </div>
                                        <small class="text-muted">Les actions dangereuses sont désactivées par défaut. Je peux ajouter des endpoints sécurisés si vous le souhaitez.</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="mt-4 small text-muted">Note: cette page fournit des informations en lecture seule. Avant d'exécuter des actions (export DB, supprimer logs), il est recommandé d'ajouter des contrôles CSRF et d'authentifier strictement l'utilisateur.</div>

</div>

<?php
require_once __DIR__ . '/partials/footer.php';
