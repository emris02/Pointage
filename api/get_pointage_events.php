<?php
/**
 * API: Événements de pointage pour FullCalendar
 * Supporte les sessions employe et admin et renvoie des événements agrégés par jour
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

header('Content-Type: application/json');

// Déterminer l'utilisateur connecté (employe ou admin)
if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$userType = isset($_SESSION['admin_id']) ? 'admin' : 'employe';
$userId = (int)($_SESSION['admin_id'] ?? $_SESSION['employe_id']);

// Période facultative (format YYYY-MM-DD)
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

$params = [];
$where = '';
if ($userType === 'employe') {
    $where = 'WHERE employe_id = :uid';
} else {
    $where = 'WHERE admin_id = :uid';
}
$params[':uid'] = $userId;

if ($start) { $where .= " AND DATE(date_heure) >= :start"; $params[':start'] = $start; }
if ($end) { $where .= " AND DATE(date_heure) <= :end"; $params[':end'] = $end; }

$sql = "SELECT type, date_heure FROM pointages $where ORDER BY date_heure ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agréger par date
$byDate = [];
foreach ($rows as $r) {
    $dt = new DateTime($r['date_heure']);
    $date = $dt->format('Y-m-d');
    if (!isset($byDate[$date])) {
        $byDate[$date] = [
            'nb' => 0,
            'first' => $dt,
            'last' => $dt
        ];
    }
    $byDate[$date]['nb']++;
    if ($dt < $byDate[$date]['first']) $byDate[$date]['first'] = $dt;
    if ($dt > $byDate[$date]['last']) $byDate[$date]['last'] = $dt;
}

$events = [];
foreach ($byDate as $date => $info) {
    $events[] = [
        'id' => 'pointage_' . $date,
        'title' => $info['nb'] . ' pointage' . ($info['nb'] > 1 ? 's' : ''),
        'start' => $date,
        'allDay' => true,
        'color' => '#2ecc71',
        'extendedProps' => [
            'type' => 'pointage',
            'nb_pointages' => $info['nb'],
            'premier' => $info['first']->format('H:i'),
            'dernier' => $info['last']->format('H:i')
        ]
    ];
}

echo json_encode($events);
?>


