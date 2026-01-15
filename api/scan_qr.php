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
    // Support both employe and admin token types
    $userType = $tokenData['user_type'] ?? 'employe';
    $userId = (int)($tokenData['user_id'] ?? $employeIdFromToken);

    // Déterminer le type de pointage pour les QR codes : uniquement ARRIVEE ou DEPART (pas de pause via QR)
    // Ignorer toute demande de pause envoyée par le client
    if (!empty($input['type']) && in_array($input['type'], ['pause_debut','pause_fin'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Les pauses ne sont pas autorisées via QR. Utilisez l\'icône Pause dans l\'en-tête.',
            'code' => 'NO_PAUSE_VIA_QR'
        ]);
        exit();
    }

    // Déterminer le type en fonction des pointages existants aujourd'hui :
    // - si aucune arrivée enregistrée aujourd'hui => ARRIVEE
    // - sinon => DEPART
    $dateAujourdhui = date('Y-m-d');
    if ($userType === 'admin') {
        $stmtCount = $pdo->prepare("SELECT
            SUM(type = 'arrivee') as has_arrivee,
            SUM(type = 'depart') as has_depart,
            COUNT(*) as total
            FROM pointages WHERE admin_id = :id AND DATE(date_heure) = :date");
    } else {
        $stmtCount = $pdo->prepare("SELECT
            SUM(type = 'arrivee') as has_arrivee,
            SUM(type = 'depart') as has_depart,
            COUNT(*) as total
            FROM pointages WHERE employe_id = :id AND DATE(date_heure) = :date");
    }
    $stmtCount->execute([':id' => $userId, ':date' => $dateAujourdhui]);
    $cntRow = $stmtCount->fetch(PDO::FETCH_ASSOC);

    if (!$cntRow || intval($cntRow['has_arrivee']) === 0) {
        $typePointage = 'arrivee';
    } else {
        $typePointage = 'depart';
    }
    
    // Vérifier si l'utilisateur (employé ou admin) existe
    if ($userType === 'admin') {
        $stmt = $pdo->prepare('SELECT id, nom, prenom, email, role FROM admins WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !in_array($user['role'] ?? '', ['admin','super_admin'], true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Administrateur non autorisé ou introuvable'
            ]);
            exit();
        }
    } else {
        $stmt = $pdo->prepare('SELECT id, nom, prenom, matricule, departement, adresse FROM employes WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'Employé non trouvé'
            ]);
            exit();
        }
    }
    
    $heureActuelle = date('Y-m-d H:i:s');
    $dateAujourdhui = date('Y-m-d');
    
    // Vérifier le dernier pointage (pour détecter conflit post-arrivée)
    if ($userType === 'admin') {
        $lastSql = "SELECT id, type, date_heure FROM pointages WHERE admin_id = :user_id AND DATE(date_heure) = :date ORDER BY date_heure DESC LIMIT 1";
        $dupSql = "SELECT id, date_heure FROM pointages WHERE admin_id = :user_id AND type = :type AND DATE(date_heure) = :date AND TIMESTAMPDIFF(MINUTE, date_heure, :now) < 30 ORDER BY date_heure DESC LIMIT 1";
    } else {
        $lastSql = "SELECT id, type, date_heure FROM pointages WHERE employe_id = :user_id AND DATE(date_heure) = :date ORDER BY date_heure DESC LIMIT 1";
        $dupSql = "SELECT id, date_heure FROM pointages WHERE employe_id = :user_id AND type = :type AND DATE(date_heure) = :date AND TIMESTAMPDIFF(MINUTE, date_heure, :now) < 30 ORDER BY date_heure DESC LIMIT 1";
    }

    // Récupérer le dernier pointage
    $stmtLast = $pdo->prepare($lastSql);
    $stmtLast->execute([':user_id' => $userId, ':date' => $dateAujourdhui]);
    $lastPointage = $stmtLast->fetch(PDO::FETCH_ASSOC);

    // Si le dernier pointage est une arrivée et qu'on tente une autre action (ex: départ, pause),
    // on renvoie 409 pour forcer une validation explicite côté client
    if ($lastPointage && $lastPointage['type'] === 'arrivee' && $typePointage !== 'arrivee') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Pointage en conflit : une arrivée a été enregistrée récemment. Validation requise (Pause / Départ / Annuler).',
            'code' => 'NEEDS_CONFIRMATION',
            'last' => $lastPointage
        ]);
        exit();
    }

    // Vérifier les doublons récents (même type dans les 30 dernières minutes)
    $stmt = $pdo->prepare($dupSql);
    $stmt->execute([
        ':user_id' => $userId,
        ':type' => $typePointage,
        ':date' => $dateAujourdhui,
        ':now' => $heureActuelle
    ]);

    $dernierPointage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dernierPointage) {
        $heureDer = date('H:i', strtotime($dernierPointage['date_heure']));
        $diffMinutes = round((strtotime($heureActuelle) - strtotime($dernierPointage['date_heure'])) / 60);

        // Tentative de régénération automatique du badge lorsqu'un départ a déjà été effectué
        $badgeRegen = null;
        $badgeRegenError = null;
        try {
            if ($userType === 'admin') {
                $regen = BadgeManager::regenerateTokenForAdmin($userId, $pdo);
            } else {
                $regen = BadgeManager::regenerateToken($userId, $pdo);
            }
            if (!empty($regen['token'])) {
                $badgeRegen = [
                    'token' => $regen['token'],
                    'token_hash' => $regen['token_hash'] ?? null,
                    'expires_at' => $regen['expires_at'] ?? null
                ];
            }
        } catch (Throwable $e) {
            $badgeRegenError = $e->getMessage();
            error_log('scan_qr.php badge regen on duplicate failed: ' . $e->getMessage());
        }

        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "Pointage $typePointage déjà effectué à $heureDer (il y a $diffMinutes minutes)",
            'code' => 'POINTAGE_DUPLICATE',
            'details' => ['last' => $dernierPointage],
            'badge_regenerated' => $badgeRegen ? true : false,
            'new_badge' => $badgeRegen,
            'badge_regen_error' => $badgeRegenError
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
    
    // Préparer les données pour l'insertion (gérer admin_id ou employe_id dynamiquement)
    $data = [
        ':user_id' => $userId,
        ':type' => $typePointage,
        ':date_heure' => $heureActuelle,
        ':retard_minutes' => $retardMinutes,
        ':etat' => $etat,
        ':statut' => $statut,
        ':device_info' => $deviceInfo
    ];

    // Construire la requête SQL en fonction du type d'utilisateur
    if ($userType === 'admin') {
        $sql = 'INSERT INTO pointages (admin_id, type, date_heure, retard_minutes, etat, statut, device_info) VALUES (:user_id, :type, :date_heure, :retard_minutes, :etat, :statut, :device_info)';
    } else {
        $sql = 'INSERT INTO pointages (employe_id, type, date_heure, retard_minutes, etat, statut, device_info) VALUES (:user_id, :type, :date_heure, :retard_minutes, :etat, :statut, :device_info)';
    }

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($data);
    
    if ($success) {
        $pointageId = $pdo->lastInsertId();
        
        // Pour les arrivées avec retard, nous pourrions aussi créer une entrée dans retard_cause
        if ($isLate && $retardMinutes > 0) {
            // Créer une entrée dans `retards` pour suivre la justification
            $stmtRet = $pdo->prepare("INSERT INTO retards (pointage_id, employe_id, raison, details, statut, date_soumission) VALUES (:pointage_id, :employe_id, 'retard', :details, 'en_attente', NOW())");
            $stmtRet->execute([
                ':pointage_id' => $pointageId,
                ':employe_id' => $userType === 'admin' ? null : $userId,
                ':details' => "Retard de {$retardMinutes} minutes - À justifier"
            ]);

            // Marquer le pointage comme nécessitant justification
            $stmt = $pdo->prepare('UPDATE pointages SET est_justifie = 0 WHERE id = :id');
            $stmt->execute([':id' => $pointageId]);

            // Indiquer au client qu'une justification est requise
            $response['warning'] = $response['warning'] ?? "Attention : Retard de {$retardMinutes} minutes";
            $response['retard'] = true;
            $response['needs_justification'] = true;
            $response['code'] = 'NEED_JUSTIFICATION';
        }

        // Si départ effectué avant 18:00, demander une justification (similaire au retard)
        if ($typePointage === 'depart') {
            $currentHour = (int)date('H');
            if ($currentHour < 18) {
                // Marquer la sortie comme nécessitant justification
                // Enregistrer une demande de justification dans `retards`
                $stmtRet = $pdo->prepare("INSERT INTO retards (pointage_id, employe_id, raison, details, statut, date_soumission) VALUES (:pointage_id, :employe_id, 'depart_anticipé', :details, 'en_attente', NOW())");
                $stmtRet->execute([
                    ':pointage_id' => $pointageId,
                    ':employe_id' => $userType === 'admin' ? null : $userId,
                    ':details' => 'Départ avant 18:00 - À justifier'
                ]);

                $stmt = $pdo->prepare('UPDATE pointages SET est_justifie = 0 WHERE id = :id');
                $stmt->execute([':id' => $pointageId]);

                $response['warning'] = 'Départ enregistré avant 18:00 ; une justification est requise.';
                $response['needs_justification'] = true;
                $response['code'] = 'NEED_JUSTIFICATION';
            }
        }
        
        // Récupérer les heures d'arrivée et de départ pour aujourd'hui si existantes
        // Récupérer les heures d'arrivée et de départ pour aujourd'hui si existantes (géré pour admin ou employé)
        $idField = ($userType === 'admin') ? 'admin_id' : 'employe_id';

        $stmtA = $pdo->prepare("SELECT DATE_FORMAT(MIN(date_heure), '%H:%i') as arrivee_time FROM pointages WHERE $idField = :id AND DATE(date_heure) = :date AND type = 'arrivee'");
        $stmtA->execute([':id' => $userId, ':date' => $dateAujourdhui]);
        $arriveeRow = $stmtA->fetch(PDO::FETCH_ASSOC);

        $stmtD = $pdo->prepare("SELECT DATE_FORMAT(MAX(date_heure), '%H:%i') as depart_time FROM pointages WHERE $idField = :id AND DATE(date_heure) = :date AND type = 'depart'");
        $stmtD->execute([':id' => $userId, ':date' => $dateAujourdhui]);
        $departRow = $stmtD->fetch(PDO::FETCH_ASSOC);

        $response = [
            'success' => true,
            'message' => 'Pointage enregistré avec succès',
            'data' => [
                'pointage_id' => $pointageId,
                'user_type' => $userType,
                'user_id' => $userId,
                'nom' => $user['nom'] ?? $user['email'] ?? null,
                'prenom' => $user['prenom'] ?? null,
                'matricule' => $user['matricule'] ?? null,
                'departement' => $user['departement'] ?? null,
                'adresse' => $user['adresse'] ?? null,
                // Include role information for admins so the scanner can display account type more precisely
                'role' => isset($user['role']) ? $user['role'] : null,
                'employe' => [
                    'full_name' => trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')),
                    'adresse' => $user['adresse'] ?? null,
                    'departement' => $user['departement'] ?? null,
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

        // Calculer rapidement quelques statistiques d'aujourd'hui pour cet utilisateur
        $stmtStats = $pdo->prepare("SELECT 
            COUNT(*) as total_pointages, 
            SUM(type = 'arrivee') as arrivees,
            SUM(type = 'depart') as departs,
            SUM(CASE WHEN retard_minutes > 0 THEN 1 ELSE 0 END) as retards 
            FROM pointages WHERE $idField = :id AND DATE(date_heure) = :date");
        $stmtStats->execute([':id' => $userId, ':date' => $dateAujourdhui]);
        $s = $stmtStats->fetch(PDO::FETCH_ASSOC);

        // Calculer temps de travail approximatif (si arrivée et départ disponibles)
        $tempsTravail = '00:00';
        if (!empty($arriveeRow['arrivee_time']) && !empty($departRow['depart_time'])) {
            $a = DateTime::createFromFormat('H:i', $arriveeRow['arrivee_time']);
            $d = DateTime::createFromFormat('H:i', $departRow['depart_time']);
            if ($a && $d) {
                $diff = $d->getTimestamp() - $a->getTimestamp();
                if ($diff > 0) {
                    $mins = intval($diff / 60);
                    $h = floor($mins / 60);
                    $m = $mins % 60;
                    $tempsTravail = sprintf('%02d:%02d', $h, $m);
                }
            }
        }

        $response['stats'] = [
            'total_pointages' => (int)($s['total_pointages'] ?? 0),
            'arrivees' => (int)($s['arrivees'] ?? 0),
            'departs' => (int)($s['departs'] ?? 0),
            'retards' => (int)($s['retards'] ?? 0),
            'temps_travail' => $tempsTravail
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
                if ($userType === 'admin') {
                    $regen = BadgeManager::regenerateTokenForAdmin($userId, $pdo);
                } else {
                    $regen = BadgeManager::regenerateToken($userId, $pdo);
                }
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
            'message' => 'Erreur lors de l\'enregistrement. Veuillez réessayer ou contacter l\'administrateur.',
            'code' => 'DB_ERROR',
            'error' => $error[2] ?? 'Erreur inconnue'
        ]);
    }
}
catch (InvalidArgumentException $e) {
    // Mauvais format envoyé par le client
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'BADGE_FORMAT'
    ]);
} catch (RuntimeException $e) {
    // Erreurs attendues liées au badge (expiré, invalide, utilisateur introuvable)
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'BADGE_INVALID'
    ]);
} catch (Throwable $e) {
    // Erreur inattendue - logguer pour investigation
    error_log("scan_qr.php exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur. Veuillez réessayer ou contacter le support.',
        'code' => 'EXCEPTION'
    ]);
}
?>