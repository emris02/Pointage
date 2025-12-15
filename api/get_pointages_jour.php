<?php
/**
 * API: Récupère les pointages de l'employé connecté par jour, classés par heure
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

header('Content-Type: application/json');

if (!isset($_SESSION['employe_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$employeId = (int)$_SESSION['employe_id'];
$date = $_GET['date'] ?? date('Y-m-d'); // Date au format Y-m-d

try {
    // Récupérer les pointages de l'employé pour la date spécifiée, classés par heure
    $stmt = $pdo->prepare("
        SELECT 
            id,
            type,
            DATE_FORMAT(date_heure, '%H:%i:%s') as heure,
            DATE_FORMAT(date_heure, '%d/%m/%Y') as date_formatee,
            date_heure,
            etat,
            statut,
            retard_cause,
            latitude,
            longitude,
            TIMESTAMPDIFF(MINUTE, CONCAT(DATE(date_heure), ' 09:00:00'), date_heure) as retard_minutes
        FROM pointages 
        WHERE employe_id = ? 
        AND DATE(date_heure) = ?
        ORDER BY date_heure ASC
    ");
    
    $stmt->execute([$employeId, $date]);
    $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiser les pointages par jour
    $pointagesParJour = [];
    foreach ($pointages as $pointage) {
        $jour = $pointage['date_formatee'];
        if (!isset($pointagesParJour[$jour])) {
            $pointagesParJour[$jour] = [];
        }
        $pointagesParJour[$jour][] = $pointage;
    }
    
    // Calculer les statistiques du jour
    $stats = [
        'total_pointages' => count($pointages),
        'arrivees' => 0,
        'departs' => 0,
        'retards' => 0,
        'temps_travail' => null
    ];
    
    $arrivee = null;
    foreach ($pointages as $pointage) {
        if ($pointage['type'] === 'arrivee') {
            $stats['arrivees']++;
            $arrivee = $pointage['date_heure'];
            if ($pointage['retard_minutes'] > 0) {
                $stats['retards']++;
            }
        } elseif ($pointage['type'] === 'depart') {
            $stats['departs']++;
            if ($arrivee) {
                $debut = new DateTime($arrivee);
                $fin = new DateTime($pointage['date_heure']);
                $diff = $debut->diff($fin);
                $totalMinutes = ($diff->h * 60) + $diff->i;
                // Pause d'1h si > 4h
                $pauseMinutes = ($totalMinutes > 240) ? 60 : 0;
                $workMinutes = max(0, $totalMinutes - $pauseMinutes);
                $stats['temps_travail'] = sprintf('%02d:%02d', floor($workMinutes / 60), $workMinutes % 60);
                $arrivee = null;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'pointages' => $pointagesParJour,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des pointages: ' . $e->getMessage()
    ]);
}
?>

