<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/../migrations/20251218_add_admin_id_to_pointages.sql');
try {
    $pdo->exec($sql);
    echo "Migration applied successfully (pointages.admin_id)\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
