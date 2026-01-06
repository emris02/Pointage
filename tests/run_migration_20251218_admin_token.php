<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/../migrations/20251218_add_admin_id_to_badge_tokens.sql');
try {
    $pdo->exec($sql);
    echo "Migration (badge_tokens admin_id) applied successfully\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
