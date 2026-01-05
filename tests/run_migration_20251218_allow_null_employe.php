<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/../migrations/20251218_allow_null_employe_in_badge_tokens.sql');
try {
    $pdo->exec($sql);
    echo "Migration applied successfully (allow null employe_id)\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
