<?php
/**
 * API: Événements de pointage pour FullCalendar (employé connecté)
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

header('Content-Type: application/json');

if (!isset($_SESSION['employe_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$employeId = (int)$_SESSION['employe_id'];

// Période facultative
$start = $_GET['start'] ?? null; // format ISO
$end = $_GET['end'] ?? null;     // format ISO

$params = [':employe_id' => $employeId];
$where = "WHERE employe_id = :employe_id";
if ($start) { $where .= " AND date_heure >= :start"; $params[':start'] = $start; }
if ($end) { $where .= " AND date_heure <= :end"; $params[':end'] = $end; }

$sql = "SELECT id, type, date_heure FROM pointages $where ORDER BY date_heure ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];
foreach ($rows as $row) {
    $isArrivee = ($row['type'] === 'arrivee');
    $events[] = [
        'id' => $row['id'],
        'title' => $isArrivee ? 'Arrivée' : 'Départ',
        'start' => date('c', strtotime($row['date_heure'])),
        'allDay' => false,
        'color' => $isArrivee ? '#2ecc71' : '#f1c40f'
    ];
}

echo json_encode($events);
?>


