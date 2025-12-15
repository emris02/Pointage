<?php
// Endpoint qui renvoie les événements (evenements + pointages) pour l'employé connecté
// Renvoie toujours un JSON valide (au moins []) pour éviter les erreurs de parsing côté client
header('Content-Type: application/json; charset=utf-8');

// Charger bootstrap (doit définir $pdo et démarrer la session)
require_once __DIR__ . '/src/config/bootstrap.php';
require_once __DIR__ . '/src/services/AuthService.php';

use Pointage\Services\AuthService;

try {
	AuthService::requireAuth();
} catch (Exception $e) {
	// Non authentifié: renvoyer tableau vide
	echo json_encode([]);
	exit();
}

$employeId = isset($_SESSION['employe_id']) ? (int)$_SESSION['employe_id'] : null;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

$events = [];

// Récupérer événements généraux
try {
	$sql = "SELECT id, titre, description, start_date, end_date, type, employe_id FROM evenements WHERE 1=1";
	if ($start && $end) {
		$sql .= " AND start_date <= :end AND end_date >= :start";
	}
	$sql .= " AND (employe_id IS NULL OR employe_id = :employe_id)";

	$stmt = $pdo->prepare($sql);
	if ($start && $end) {
		$stmt->bindValue(':start', $start);
		$stmt->bindValue(':end', $end);
	}
	$stmt->bindValue(':employe_id', $employeId ?: 0, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $r) {
		$events[] = [
			'id' => 'ev-' . $r['id'],
			'title' => $r['titre'] ?? 'Événement',
			'start' => $r['start_date'],
			'end' => $r['end_date'],
			'allDay' => false,
			'extendedProps' => [
				'description' => $r['description'] ?? null,
				'type' => $r['type'] ?? null,
				'employe_id' => $r['employe_id'] ?? null,
				'source' => 'evenements'
			]
		];
	}
} catch (Exception $e) {
	// Log serveur mais continuer
	error_log('get_evenements_calendrier error (evenements): ' . $e->getMessage());
}

// Récupérer pointages de l'employé
if ($employeId) {
	try {
		$sql2 = "SELECT id, type, date_heure FROM pointages WHERE employe_id = :employe_id";
		if ($start) { $sql2 .= " AND date_heure >= :start"; }
		if ($end) { $sql2 .= " AND date_heure <= :end"; }
		$sql2 .= " ORDER BY date_heure ASC";

		$stmt2 = $pdo->prepare($sql2);
		$stmt2->bindValue(':employe_id', $employeId, PDO::PARAM_INT);
		if ($start) $stmt2->bindValue(':start', $start);
		if ($end) $stmt2->bindValue(':end', $end);
		$stmt2->execute();
		$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows2 as $r2) {
			$isArrivee = ($r2['type'] === 'arrivee');
			$events[] = [
				'id' => 'p-' . $r2['id'],
				'title' => $isArrivee ? 'Arrivée' : 'Départ',
				'start' => date('c', strtotime($r2['date_heure'])),
				'allDay' => false,
				'color' => $isArrivee ? '#2ecc71' : '#f1c40f',
				'extendedProps' => [
					'source' => 'pointage',
					'type' => $r2['type'] ?? null
				]
			];
		}
	} catch (Exception $e) {
		error_log('get_evenements_calendrier error (pointages): ' . $e->getMessage());
	}
}

// Toujours renvoyer du JSON valide
echo json_encode($events ?: []);
exit();

