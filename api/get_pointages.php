<?php
/**
 * API PUBLIQUE: Récupère les pointages pour affichage public
 * Ne nécessite PAS d'authentification
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../src/config/bootstrap.php';

$date = $_GET['date'] ?? date('Y-m-d');

// Validation simple du format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Date invalide'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Récupérer les pointages du jour (chronologique) pour pouvoir agréger par employé
    $stmt = $pdo->prepare("
        SELECT 
            e.id AS employe_id,
            e.nom, 
            e.prenom, 
            e.departement,
            p.type,
            p.date_heure
        FROM pointages p 
        JOIN employes e ON p.employe_id = e.id 
        WHERE DATE(p.date_heure) = ? 
        AND e.statut = 'actif'
        ORDER BY p.date_heure ASC
    ");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pointages = [];
    $stats = [
        'total_pointages' => 0,
        'arrivees' => 0,
        'departs' => 0,
        'temps_travail' => '00:00'
    ];
    // Construire des résumés par employé
    $byEmployee = [];
    foreach ($rows as $r) {
        $eid = (int)$r['employe_id'];
        if (!isset($byEmployee[$eid])) {
            $byEmployee[$eid] = [
                'employe_id' => $eid,
                'nom' => $r['nom'],
                'prenom' => $r['prenom'],
                'departement' => $r['departement'] ?? 'Non spécifié',
                'arrivees' => [],
                'departs' => [],
                'pauses' => [], // array of ['debut'=>datetime,'fin'=>datetime]
                'last_pause_debut' => null
            ];
        }

        $dt = new DateTime($r['date_heure']);
        $time = $dt->format('H:i');

        // Ajouter au tableau brut pour compatibilité
        $pointages[] = [
            'nom' => $r['nom'],
            'prenom' => $r['prenom'],
            'departement' => $r['departement'] ?? 'Non spécifié',
            'type' => $r['type'],
            'heure' => $time
        ];

        $stats['total_pointages']++;
        if ($r['type'] === 'arrivee') {
            $stats['arrivees']++;
            $byEmployee[$eid]['arrivees'][] = $dt;
        } elseif ($r['type'] === 'depart') {
            $stats['departs']++;
            $byEmployee[$eid]['departs'][] = $dt;
        } elseif ($r['type'] === 'pause_debut') {
            $byEmployee[$eid]['last_pause_debut'] = $dt;
        } elseif ($r['type'] === 'pause_fin') {
            if ($byEmployee[$eid]['last_pause_debut'] instanceof DateTime) {
                $byEmployee[$eid]['pauses'][] = [
                    'debut' => $byEmployee[$eid]['last_pause_debut'],
                    'fin' => $dt
                ];
                $byEmployee[$eid]['last_pause_debut'] = null;
            }
        }
    }

    // Calculer les résumés et phrases
    $summaries = [];
    foreach ($byEmployee as $eid => $info) {
        // première arrivée (si multiple)
        $arrivee = null;
        if (!empty($info['arrivees'])) {
            usort($info['arrivees'], function($a, $b){ return $a <=> $b; });
            $arrivee = $info['arrivees'][0]->format('H:i');
        }

        // dernier départ
        $depart = null;
        if (!empty($info['departs'])) {
            usort($info['departs'], function($a, $b){ return $a <=> $b; });
            $depart = end($info['departs'])->format('H:i');
        }

        // total pauses en minutes
        $pauseMinutes = 0;
        foreach ($info['pauses'] as $p) {
            $interval = $p['debut']->diff($p['fin']);
            $pauseMinutes += ($interval->h * 60) + $interval->i;
        }

        // Construire la phrase simple
        $fullname = trim(($info['prenom'] ?? '') . ' ' . ($info['nom'] ?? ''));
        $departmentText = $info['departement'] ?? 'Non spécifié';
        $parts = [];
        if ($arrivee) $parts[] = "arrivé à $arrivee";
        if ($pauseMinutes > 0) $parts[] = "a pris {$pauseMinutes}min de pause";
        if ($depart) $parts[] = "est reparti à $depart";

        $sentence = $fullname . ' • ' . $departmentText;
        if (!empty($parts)) {
            $sentence .= ' — ' . implode(' • ', $parts);
        }

        $summaries[] = [
            'employe_id' => $eid,
            'fullname' => $fullname,
            'departement' => $departmentText,
            'arrivee' => $arrivee,
            'depart' => $depart,
            'pause_minutes' => $pauseMinutes,
            'sentence' => $sentence
        ];
    }

    // Calculer statistiques supplémentaires : retards et temps de travail moyen
    $retardsCount = 0;
    $workDurations = []; // minutes per employee

    foreach ($summaries as $s) {
        if (!empty($s['arrivee'])) {
            // consider late if arrival after 09:00
            $arr = DateTime::createFromFormat('H:i', $s['arrivee']);
            if ($arr && intval($arr->format('H')) >= 9 && intval($arr->format('H')) > 9 || ($arr && intval($arr->format('H')) == 9 && intval($arr->format('i')) > 0)) {
                $retardsCount++;
            }
        }

        if (!empty($s['arrivee']) && !empty($s['depart'])) {
            $a = DateTime::createFromFormat('H:i', $s['arrivee']);
            $d = DateTime::createFromFormat('H:i', $s['depart']);
            if ($a && $d) {
                $diff = ($d->getTimestamp() - $a->getTimestamp()) / 60; // minutes
                $worked = max(0, intval($diff) - intval($s['pause_minutes']));
                $workDurations[] = $worked;
            }
        }
    }

    $avgWork = 0;
    if (count($workDurations) > 0) {
        $avgWork = intval(array_sum($workDurations) / count($workDurations));
    }

    // Format average as HH:MM
    $hours = floor($avgWork / 60);
    $mins = $avgWork % 60;
    $avgFormatted = sprintf('%02d:%02d', $hours, $mins);

    $stats['retards'] = $retardsCount;
    $stats['temps_travail'] = $avgFormatted;

    echo json_encode([
        'success' => true,
        'date' => $date,
        'pointages' => $pointages,
        'summaries' => $summaries,
        'stats' => $stats,
        'updated_at' => date('H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur',
        'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
}
?>