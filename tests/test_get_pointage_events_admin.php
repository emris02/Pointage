<?php
require __DIR__ . '/../src/config/bootstrap.php';

// Pick an active admin if available
$admin = $pdo->query("SELECT id, nom, prenom FROM admins WHERE statut='actif' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    echo "SKIP: No active admin found in DB\n";
    exit(0);
}

// Set up session to simulate admin login
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['admin_id'] = (int)$admin['id'];

// Query the API directly
$_GET['start'] = date('Y-m-01');
$_GET['end'] = date('Y-m-t');

ob_start();
include __DIR__ . '/../api/get_pointage_events.php';
$out = ob_get_clean();

echo "API output (truncated):\n";
$decoded = json_decode($out, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "FAIL: Invalid JSON returned\n";
    echo $out . "\n";
    exit(1);
}

echo "Events returned: " . count($decoded) . "\n";
foreach (array_slice($decoded, 0, 5) as $e) {
    echo sprintf("- %s: %s (%s)\n", $e['id'] ?? '-', $e['title'] ?? '-', $e['start'] ?? '-');
}

exit(0);
