<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/../migrations/20251218_allow_null_employe_in_pointages.sql');
try {
    $pdo->exec($sql);
    echo "Migration applied successfully (pointages.employe_id NULL)\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
