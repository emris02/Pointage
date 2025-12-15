<?php
/**
 * Test de validation des badges
 * Ce script teste la logique de validation des tokens QR
 */

require_once 'src/config/bootstrap.php';
require_once 'src/services/BadgeManager.php';

echo "<h2>Test de validation des badges</h2>\n";

// Test avec le format QR reçu
$testToken = "6|5c7f8dfe65887b8da836ebf9959a9f54|1760953390|3|db9d0b99d398dbc18401a2db3383ed12aa7d672ebbd6c619712b1e939cbed61b";

echo "<h3>Token de test:</h3>\n";
echo "<pre>" . htmlspecialchars($testToken) . "</pre>\n";

echo "<h3>Analyse du token:</h3>\n";
$parts = explode('|', $testToken);
echo "<ul>\n";
foreach ($parts as $i => $part) {
    echo "<li>Partie $i: " . htmlspecialchars($part) . "</li>\n";
}
echo "</ul>\n";

echo "<h3>Test de validation:</h3>\n";
try {
    $result = BadgeManager::verifyToken($testToken, $pdo);
    echo "<div style='color: green;'>✅ Token valide!</div>\n";
    echo "<pre>" . print_r($result, true) . "</pre>\n";
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

echo "<h3>Logs de debug:</h3>\n";
$debugFile = __DIR__ . '/src/services/logs/badge_verify_debug.log';
if (file_exists($debugFile)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($debugFile)) . "</pre>\n";
} else {
    echo "<p>Aucun log de debug trouvé.</p>\n";
}
?>
