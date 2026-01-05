<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/../migrations/20251218_add_statut_to_admins.sql');
try {
    $pdo->exec($sql);
    echo "Migration applied successfully\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
