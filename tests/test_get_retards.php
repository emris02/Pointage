<?php
require __DIR__ . '/../src/config/bootstrap.php';
require __DIR__ . '/../src/services/AdminService.php';

$svc = new AdminService($pdo);
$date = date('Y-m-d');

echo "Testing getRetards for date $date\n";
$rows = $svc->getRetards($date, 1, 50, null);

echo "Found " . count($rows) . " retards\n";
foreach ($rows as $r) {
    $type = !empty($r['admin_id']) ? 'ADMIN' : 'EMP';
    $id = $r['admin_id'] ?? $r['employe_id'] ?? $r['person_id'] ?? 'n/a';
    $min = $r['retard_minutes'] ?? (isset($r['retard']) ? $r['retard'] : '-');
    $statut = $r['retard_statut'] ?? ($r['retard_justifie'] ?? null);
    echo sprintf("%s %s %s min statut=%s cause=%s\n", $type, $r['prenom'] ?? '', $r['nom'] ?? '', $min, $statut, $r['retard_raison'] ?? $r['retard_cause'] ?? '-');
}
