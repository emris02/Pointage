<?php
// Simple migration helper: copy non-empty date_pointage -> date_heure
// USAGE (local dev):
// 1) php scripts/migrate_date_pointage_to_date_heure.php --preview
// 2) php scripts/migrate_date_pointage_to_date_heure.php --apply
// Always make a DB backup before applying in production.

require_once __DIR__ . '/../src/config/bootstrap.php'; // should set $pdo

if (!isset($pdo) || !$pdo instanceof PDO) {
    echo "PDO connection not found. Ensure src/config/bootstrap.php defines \$pdo.\n";
    exit(1);
}

$options = getopt('', ['preview', 'apply']);
$preview = isset($options['preview']);
$apply = isset($options['apply']);

if (!$preview && !$apply) {
    echo "Usage: php scripts/migrate_date_pointage_to_date_heure.php --preview|--apply\n";
    exit(1);
}

// Count affected rows
$stmt = $pdo->query("SELECT COUNT(*) FROM pointages WHERE (date_heure IS NULL OR date_heure = '0000-00-00 00:00:00') AND (date_pointage IS NOT NULL AND date_pointage != '0000-00-00 00:00:00')");
$count = (int)$stmt->fetchColumn();

echo "Found $count row(s) with date_pointage but missing date_heure.\n";

if ($preview) {
    // Show a sample
    $s = $pdo->query("SELECT id, date_pointage, date_heure FROM pointages WHERE (date_heure IS NULL OR date_heure = '0000-00-00 00:00:00') AND (date_pointage IS NOT NULL AND date_pointage != '0000-00-00 00:00:00') ORDER BY id LIMIT 20");
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("id=%d, date_pointage=%s, date_heure=%s\n", $r['id'], $r['date_pointage'], $r['date_heure']);
    }
    echo "\nPreview only — no change applied. To apply, run with --apply.\n";
    exit(0);
}

if ($apply) {
    echo "Applying migration...\n";
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE pointages SET date_heure = date_pointage WHERE (date_heure IS NULL OR date_heure = '0000-00-00 00:00:00') AND (date_pointage IS NOT NULL AND date_pointage != '0000-00-00 00:00:00')");
        $update->execute();
        $affected = $update->rowCount();
        $pdo->commit();
        echo "Migration applied. Rows updated: $affected\n";
        echo "Reminder: consider keeping a DB backup and removing the redundant column if no longer needed.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error applying migration: " . $e->getMessage() . "\n";
        exit(1);
    }
}

exit(0);
