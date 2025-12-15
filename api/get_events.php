<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

header('Content-Type: application/json');

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

if (!$start || !$end) {
    echo json_encode([]);
    exit();
}

// Filtrer par employé si demandé (pour l'espace employé)
$employeId = isset($_GET['employe_id']) ? (int)$_GET['employe_id'] : null;

$sql = "SELECT id, titre, description, start_date, end_date, type, employe_id
        FROM evenements
        WHERE start_date <= :end AND end_date >= :start";
if ($employeId) {
    $sql .= " AND (employe_id IS NULL OR employe_id = :employe_id)";
}

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
if ($employeId) $stmt->bindValue(':employe_id', $employeId, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adapter au format FullCalendar
$events = array_map(function($r) {
    return [
        'id' => (string)$r['id'],
        'title' => $r['titre'],
        'start' => $r['start_date'],
        'end' => $r['end_date'],
        'extendedProps' => [
            'description' => $r['description'],
            'type' => $r['type'],
            'employe_id' => $r['employe_id']
        ]
    ];
}, $rows);

echo json_encode($events);
exit();
?>


