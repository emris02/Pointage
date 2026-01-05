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
$range = $_GET['range'] ?? 'day';

// Validation simple du format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Date invalide'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Calculer la période selon le range
try {
    if ($range === 'week') {
        $dt = new DateTime($date);
        // Start Monday
        $day = (int)$dt->format('N'); // 1 (Mon) - 7 (Sun)
        $monday = clone $dt;
        $monday->modify('-' . ($day - 1) . ' days');
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $start = $monday->format('Y-m-d');
        $end = $sunday->format('Y-m-d');
    } elseif ($range === 'month') {
        $dt = new DateTime($date);
        $first = new DateTime($dt->format('Y-m-01'));
        $last = new DateTime($dt->format('Y-m-t'));
        $start = $first->format('Y-m-d');
        $end = $last->format('Y-m-d');
    } else {
        $start = $date;
        $end = $date;
    }

    // Récupérer les pointages pour la période (chronologique) pour pouvoir agréger par utilisateur (employé ou admin)
    // Utiliser des placeholders distincts pour éviter des problèmes de réutilisation de noms de paramètres
    $stmt = $pdo->prepare("\n        SELECT p.id, 'employe' as user_type, e.id as user_id, e.nom, e.prenom, e.departement, NULL as role, p.type, p.date_heure\n        FROM pointages p\n        JOIN employes e ON p.employe_id = e.id\n        WHERE DATE(p.date_heure) BETWEEN :start_emp AND :end_emp AND e.statut = 'actif'\n        UNION ALL\n        SELECT p.id, 'admin' as user_type, a.id as user_id, a.nom, a.prenom, NULL as departement, a.role, p.type, p.date_heure\n        FROM pointages p\n        JOIN admins a ON p.admin_id = a.id\n        WHERE DATE(p.date_heure) BETWEEN :start_admin AND :end_admin AND a.statut = 'actif'\n        ORDER BY date_heure ASC\n    ");
    $stmt->execute([':start_emp' => $start, ':end_emp' => $end, ':start_admin' => $start, ':end_admin' => $end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pointages = [];
    $stats = [
        'total_pointages' => 0,
        'arrivees' => 0,
        'departs' => 0,
        'temps_travail' => '00:00'
    ];

    // Construire des résumés par utilisateur (clé: type_id)
    $byUser = [];
    foreach ($rows as $r) {
        $key = ($r['user_type'] ?? 'employe') . '_' . (int)($r['user_id'] ?? 0);
        if (!isset($byUser[$key])) {
            $byUser[$key] = [
                'user_type' => $r['user_type'] ?? 'employe',
                'user_id' => (int)($r['user_id'] ?? 0),
                'nom' => $r['nom'] ?? '',
                'prenom' => $r['prenom'] ?? '',
                'departement' => $r['departement'] ?? null,
                'role' => $r['role'] ?? null,
                'arrivees' => [],
                'departs' => [],
                'pauses' => [],
                'last_pause_debut' => null
            ];
        }

        $dt = new DateTime($r['date_heure']);
        $time = $dt->format('H:i');

        // Ajouter au tableau brut pour compatibilité
        $pointages[] = [
            'nom' => $r['nom'] ?? '',
            'prenom' => $r['prenom'] ?? '',
            'departement' => $r['departement'] ?? ($r['role'] ?? 'Non spécifié'),
            'type' => $r['type'],
            'date' => (new DateTime($r['date_heure']))->format('Y-m-d'),
            'heure' => $time,
            'user_type' => $r['user_type'] ?? 'employe',
            'user_id' => (int)($r['user_id'] ?? 0),
            'role' => $r['role'] ?? null
        ];

        $stats['total_pointages']++;
        if ($r['type'] === 'arrivee') {
            $stats['arrivees']++;
            $byUser[$key]['arrivees'][] = $dt;
        } elseif ($r['type'] === 'depart') {
            $stats['departs']++;
            $byUser[$key]['departs'][] = $dt;
        } elseif ($r['type'] === 'pause_debut') {
            $byUser[$key]['last_pause_debut'] = $dt;
        } elseif ($r['type'] === 'pause_fin') {
            if ($byUser[$key]['last_pause_debut'] instanceof DateTime) {
                $byUser[$key]['pauses'][] = [
                    'debut' => $byUser[$key]['last_pause_debut'],
                    'fin' => $dt
                ];
                $byUser[$key]['last_pause_debut'] = null;
            }
        }
    }

    // Calculer les résumés et phrases
    $summaries = [];
    foreach ($byUser as $k => $info) {
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
        $departmentText = $info['departement'] ?? ($info['role'] ? ucfirst(str_replace('_', ' ', $info['role'])) : 'Non spécifié');
        $parts = [];
        if ($arrivee) $parts[] = "arrivé à $arrivee";
        if ($pauseMinutes > 0) $parts[] = "a pris {$pauseMinutes}min de pause";
        if ($depart) $parts[] = "est reparti à $depart";

        $sentence = $fullname . ' • ' . $departmentText;
        if (!empty($parts)) {
            $sentence .= ' — ' . implode(' • ', $parts);
        }

        $summaries[] = [
            'user_type' => $info['user_type'],
            'user_id' => $info['user_id'],
            'fullname' => $fullname,
            'departement' => $departmentText,
            'arrivee' => $arrivee,
            'depart' => $depart,
            'pause_minutes' => $pauseMinutes,
            'sentence' => $sentence,
            'role' => $info['role'] ?? null
        ];
    }

    // Calculer statistiques supplémentaires : retards et temps de travail moyen
    $retardsCount = 0;
    $workDurations = []; // minutes per user

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
    // Log detailed exception for debugging
    error_log("get_pointages.php exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'code' => 'EXCEPTION',
        'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
}
?>