<?php
// scan_qr.php - Version adaptée à votre structure de table
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

// Valider les données requises
if (!isset($input['badge_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données badge manquantes']);
    exit();
}

try {
    // Inclure la configuration de la base de données
    require_once __DIR__ . '/../src/config/bootstrap.php';
    require_once __DIR__ . '/../src/services/BadgeManager.php';
    
    // Le client envoie la chaîne complète du badge (format attendu: id|random|timestamp|version|signature)
    $rawToken = trim($input['badge_data']);
    if (empty($rawToken)) {
        throw new Exception('Format de badge invalide');
    }

    // Extrait l'ID fourni dans le token (sécurité supplémentaire)
    $parts = explode('|', $rawToken);
    if (count($parts) < 2) {
        throw new Exception('Format de badge invalide');
    }

    $employeIdFromToken = intval($parts[0]);

    // Vérifier et récupérer les données du token en base via BadgeManager
    $tokenData = BadgeManager::verifyToken($rawToken, $pdo);
    $employeId = (int)($tokenData['employe_id'] ?? $employeIdFromToken);

    // Déterminer le type de pointage: priorité au client si fourni (valide), sinon fallback serveur
    $allowedTypes = ['arrivee', 'depart', 'pause_debut', 'pause_fin'];
    $typePointage = 'arrivee';
    if (!empty($input['type']) && in_array($input['type'], $allowedTypes, true)) {
        $typePointage = $input['type'];
    } else {
        // Fallback serveur basé sur l'heure
        $hourNow = (int)date('H');
        if ($hourNow < 12) {
            $typePointage = 'arrivee';
        } elseif ($hourNow >= 17) {
            $typePointage = 'depart';
        } else {
            $typePointage = ($hourNow < 14) ? 'pause_debut' : 'pause_fin';
        }
    }
    
    // Vérifier si l'employé existe
    $stmt = $pdo->prepare('SELECT id, nom, prenom, matricule, departement, adresse FROM employes WHERE id = :id');
    $stmt->execute([':id' => $employeId]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employe) {
        echo json_encode([
            'success' => false,
            'message' => 'Employé non trouvé'
        ]);
        exit();
    }
    
    $heureActuelle = date('Y-m-d H:i:s');
    $dateAujourdhui = date('Y-m-d');
    
    // Vérifier les doublons récents (même type dans les 30 dernières minutes)
    $stmt = $pdo->prepare('
        SELECT id, date_heure 
        FROM pointages 
        WHERE employe_id = :employe_id 
        AND type = :type
        AND DATE(date_heure) = :date
        AND TIMESTAMPDIFF(MINUTE, date_heure, :now) < 30
        ORDER BY date_heure DESC
        LIMIT 1
    ');
    
    $stmt->execute([
        ':employe_id' => $employeId,
        ':type' => $typePointage,
        ':date' => $dateAujourdhui,
        ':now' => $heureActuelle
    ]);
    
    $dernierPointage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dernierPointage) {
        $heureDer = date('H:i', strtotime($dernierPointage['date_heure']));
        $diffMinutes = round((strtotime($heureActuelle) - strtotime($dernierPointage['date_heure'])) / 60);
        
        echo json_encode([
            'success' => false,
            'message' => "Pointage $typePointage déjà effectué à $heureDer (il y a $diffMinutes minutes)"
        ]);
        exit();
    }
    
    // Calculer le retard (uniquement pour les arrivées après 9h)
    $retardMinutes = 0;
    $isLate = false;
    
    if ($typePointage === 'arrivee') {
        $heureLimite = strtotime($dateAujourdhui . ' 09:00:00');
        $heurePointage = strtotime($heureActuelle);
        $retardMinutes = max(0, floor(($heurePointage - $heureLimite) / 60));
        $isLate = $retardMinutes > 0;
    }
    
    // Déterminer l'état et le statut
    $etat = $isLate ? 'retard' : 'normal';
    $statut = 'présent';
    
    // Extraire les infos du device si disponibles
    $deviceInfo = '';
    if (isset($input['device_info'])) {
        $deviceInfo = json_encode($input['device_info']);
    }
    
    // Préparer les données pour l'insertion
    $data = [
        ':employe_id' => $employeId,
        ':type' => $typePointage,
        ':date_heure' => $heureActuelle,
        ':retard_minutes' => $retardMinutes,
        ':etat' => $etat,
        ':statut' => $statut,
        ':device_info' => $deviceInfo
    ];
    
    // Construire la requête SQL
    $sql = 'INSERT INTO pointages (
        employe_id, 
        type, 
        date_heure, 
        retard_minutes, 
        etat, 
        statut, 
        device_info
    ) VALUES (
        :employe_id, 
        :type, 
        :date_heure, 
        :retard_minutes, 
        :etat, 
        :statut, 
        :device_info
    )';
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($data);
    
    if ($success) {
        $pointageId = $pdo->lastInsertId();
        
        // Pour les arrivées avec retard, nous pourrions aussi créer une entrée dans retard_cause
        if ($isLate && $retardMinutes > 0) {
            // Optionnel: créer une entrée de retard à justifier
            $stmt = $pdo->prepare('
                UPDATE pointages 
                SET retard_cause = :cause, 
                    est_justifie = 0 
                WHERE id = :id
            ');
            
            $stmt->execute([
                ':cause' => "Retard de {$retardMinutes} minutes - À justifier",
                ':id' => $pointageId
            ]);
        }
        
        // Récupérer les heures d'arrivée et de départ pour aujourd'hui si existantes
        $stmtA = $pdo->prepare("SELECT DATE_FORMAT(MIN(date_heure), '%H:%i') as arrivee_time FROM pointages WHERE employe_id = :id AND DATE(date_heure) = :date AND type = 'arrivee'");
        $stmtA->execute([':id' => $employeId, ':date' => $dateAujourdhui]);
        $arriveeRow = $stmtA->fetch(PDO::FETCH_ASSOC);

        $stmtD = $pdo->prepare("SELECT DATE_FORMAT(MAX(date_heure), '%H:%i') as depart_time FROM pointages WHERE employe_id = :id AND DATE(date_heure) = :date AND type = 'depart'");
        $stmtD->execute([':id' => $employeId, ':date' => $dateAujourdhui]);
        $departRow = $stmtD->fetch(PDO::FETCH_ASSOC);

        $response = [
            'success' => true,
            'message' => 'Pointage enregistré avec succès',
            'data' => [
                'pointage_id' => $pointageId,
                'employe_id' => $employeId,
                'nom' => $employe['nom'],
                'prenom' => $employe['prenom'],
                'matricule' => $employe['matricule'],
                'departement' => $employe['departement'] ?? null,
                'adresse' => $employe['adresse'] ?? null,
                'employe' => [
                    'full_name' => trim(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? '')),
                    'adresse' => $employe['adresse'] ?? null,
                    'departement' => $employe['departement'] ?? null,
                    'arrivee_time' => $arriveeRow['arrivee_time'] ?? null,
                    'depart_time' => $departRow['depart_time'] ?? null,
                    'status' => $isLate ? 'En retard' : 'À l\'heure'
                ],
                'type' => $typePointage,
                'heure' => date('H:i', strtotime($heureActuelle)),
                'date' => date('d/m/Y', strtotime($heureActuelle)),
                'retard_minutes' => $retardMinutes,
                'is_late' => $isLate,
                'etat' => $etat,
                'statut' => $statut
            ]
        ];
        
        // Si retard, ajouter un message
        if ($isLate && $retardMinutes > 0) {
            $response['warning'] = "Attention : Retard de {$retardMinutes} minutes";
            $response['retard'] = true;
            $response['needs_justification'] = true;
        }

        // Régénération facultative du badge: après un départ ou si l'heure est passée >= 19:00
        try {
            $currentHour = (int)date('H');
            if ($typePointage === 'depart' || $currentHour >= 19) {
                // Régénère le token et renvoie le nouveau token dans la réponse
                $regen = BadgeManager::regenerateToken($employeId, $pdo);
                if (!empty($regen['token'])) {
                    $response['badge_regenerated'] = true;
                    $response['new_badge'] = [
                        'token' => $regen['token'],
                        'token_hash' => $regen['token_hash'] ?? null,
                        'expires_at' => $regen['expires_at'] ?? null
                    ];
                }
            }
        } catch (Throwable $e) {
            // Ne pas bloquer l'enregistrement du pointage si la régénération échoue
            $response['badge_regenerated'] = false;
            $response['badge_regen_error'] = $e->getMessage();
        }

        echo json_encode($response);
        
    } else {
        $error = $stmt->errorInfo();
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement',
            'error' => $error[2] ?? 'Erreur inconnue'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>