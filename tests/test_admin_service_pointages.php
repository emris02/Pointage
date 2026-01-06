<?php
require __DIR__ . '/../src/config/bootstrap.php';
require __DIR__ . '/../src/services/AdminService.php';

$svc = new AdminService($pdo);
$date = date('Y-m-d');

echo "Testing AdminService::getPointages for date $date\n";
$rows = $svc->getPointages($date);
echo "Found " . count($rows) . " rows\n";
foreach ($rows as $r) {
    $type = !empty($r['admin_id']) ? 'ADMIN' : 'EMP';
    $id = $r['admin_id'] ?? $r['employe_id'] ?? 'n/a';
    echo sprintf("%s - %s %s (%s) arrivee=%s depart=%s\n", $type, $r['prenom'] ?? '', $r['nom'] ?? '', $id, $r['arrivee'] ?? '-', $r['depart'] ?? '-');
}

echo "\nTesting AdminService::getPointagesPaged for date $date\n";
$page = 1;
$per = 20;
$pg = $svc->getPointagesPaged($date, $page, $per, null, null);
echo "Total combined: " . $pg['total'] . " — items on page: " . count($pg['items']) . "\n";
foreach ($pg['items'] as $r) {
    $type = !empty($r['admin_id']) ? 'ADMIN' : 'EMP';
    $id = $r['admin_id'] ?? $r['employe_id'] ?? 'n/a';
    echo sprintf("%s - %s %s (%s) arrivee=%s depart=%s\n", $type, $r['prenom'] ?? '', $r['nom'] ?? '', $id, $r['arrivee'] ?? '-', $r['depart'] ?? '-');
}
