<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';

class PointageSystem {
    private PDO $pdo;
    private $logger;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->logger = new PointageLogger();
    }

    public function handlePointageRequest(array $requestData): array {
        try {
            // Récupération robuste du token (JSON, formulaire, champs alternatifs)
            $badgeToken = $requestData['badge_token']
                ?? ($requestData['raw_token'] ?? null)
                ?? ($requestData['token'] ?? null);

            if (!$badgeToken || !is_string($badgeToken) || trim($badgeToken) === '') {
                throw new InvalidArgumentException('Token manquant pour le pointage');
            }
            $badgeToken = trim($badgeToken);
            // Géolocalisation facultative : si présente, on la prend, sinon NULL
            // Support multiple formats: direct fields, scan_data.location, or location object
            $latitude = null;
            $longitude = null;
            if (isset($requestData['latitude']) && isset($requestData['longitude'])) {
                $latitude = (float)$requestData['latitude'];
                $longitude = (float)$requestData['longitude'];
            } elseif (isset($requestData['scan_data']['location'])) {
                $location = $requestData['scan_data']['location'];
                if (isset($location['latitude']) && isset($location['longitude'])) {
                    $latitude = (float)$location['latitude'];
                    $longitude = (float)$location['longitude'];
                }
            } elseif (isset($requestData['location'])) {
                $location = $requestData['location'];
                if (isset($location['latitude']) && isset($location['longitude'])) {
                    $latitude = (float)$location['latitude'];
                    $longitude = (float)$location['longitude'];
                }
            }

            // Si fournie, on vérifie qu'elle n'est pas 0/0 (optionnel)
            if ($latitude !== null && $longitude !== null && $latitude === 0.0 && $longitude === 0.0) {
                throw new InvalidArgumentException("Coordonnées GPS invalides (0,0)");
            }

            // DEBUG : log du token reçu (brut et encodé)
            $logDebug = __DIR__ . '/logs/pointage_debug.log';
            file_put_contents($logDebug, "[".date('Y-m-d H:i:s')."] TOKEN recu : [".$badgeToken."]\n", FILE_APPEND);
            file_put_contents($logDebug, "Lat: ".($latitude ?? 'NULL').", Lng: ".($longitude ?? 'NULL')."\n", FILE_APPEND);

            // Vérification du token et extraction des infos employé
            try {
                $tokenData = BadgeManager::verifyToken($badgeToken, $this->pdo);
            } catch (InvalidArgumentException $e) {
                // Standardiser la réponse pour frontend
                return [
                    'status' => 'invalid',
                    'reason' => 'format',
                    'message' => 'Format de badge invalide',
                    'details' => $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (RuntimeException $e) {
                $msg = $e->getMessage();
                $status = 'error';
                $reason = 'unknown';
                if (stripos($msg, 'expir') !== false) { // expiré
                    $status = 'expired';
                    $reason = 'token_expired';
                } elseif (stripos($msg, 'n\'existe pas') !== false || stripos($msg, 'invalide') !== false) {
                    $status = 'invalid';
                    $reason = 'not_found_or_invalid';
                } elseif (stripos($msg, 'ne vous appartient pas') !== false) {
                    $status = 'refused';
                    $reason = 'ownership_mismatch';
                }
                return [
                    'status' => $status,
                    'reason' => $reason,
                    'message' => $msg,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Ensure expected token data keys
            if (!isset($tokenData['employe_id']) || !isset($tokenData['badge_token_id'])) {
                // Log raw token for debugging
                file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] verifyToken returned unexpected data: " . json_encode($tokenData) . "\n", FILE_APPEND);
                throw new RuntimeException("Données du token incomplètes");
            }

            // Enregistrement du pointage (traitement principal)
            return $this->traiterPointage($tokenData, $latitude, $longitude);
        } catch (Exception $e) {
            $this->logger->logError($e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    private function traiterPointage(array $tokenData, $latitude, $longitude): array {
        $employeId = (int)$tokenData['employe_id'];
        $badgeTokenId = (int)$tokenData['badge_token_id'];
        $dateCourante = date('Y-m-d');

        $this->pdo->beginTransaction();

        try {
            // Vérifier si le badge a déjà été utilisé aujourd'hui (par n'importe quel employé)
            $stmtUsed = $this->pdo->prepare("SELECT * FROM pointages WHERE badge_token_id = ? AND DATE(date_heure) = ? LIMIT 1");
            $stmtUsed->execute([$badgeTokenId, $dateCourante]);
            $usedRow = $stmtUsed->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($usedRow) {
                if ((int)$usedRow['employe_id'] === $employeId) {
                    // badge already used today by same employee -> duplicate attempt
                    $this->logger->logError("Doublon: badge_token_id={$badgeTokenId} déjà utilisé aujourd'hui par employe {$employeId}");
                    $this->pdo->commit();
                    return [
                        'status' => 'duplicate',
                        'reason' => 'already_pointed_today',
                        'message' => 'Vous avez déjà utilisé ce badge pour un pointage aujourd\'hui',
                        'badge_token_id' => $badgeTokenId,
                        'employee_id' => $employeId,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                } else {
                    // badge used by a different employee -> possible misuse
                    $this->logger->logError("Badge utilisé par un autre employé aujourd'hui: badge_token_id={$badgeTokenId} (utilisé par employe {$usedRow['employe_id']})");
                    $this->pdo->commit();
                    return [
                        'status' => 'refused',
                        'reason' => 'badge_used_by_other',
                        'message' => 'Ce badge a déjà été utilisé par un autre employé aujourd\'hui',
                        'badge_token_id' => $badgeTokenId,
                        'first_used_by' => (int)$usedRow['employe_id'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }

            // Récupérer le dernier pointage du jour
            $lastPointage = $this->getLastPointage($employeId, $dateCourante);

            // Déterminer le type de pointage attendu
            $type = $this->determinerTypePointage($lastPointage);

            // Empêcher le même type consécutif (duplicate) — ex: arrival twice
            if ($lastPointage && isset($lastPointage['type']) && $lastPointage['type'] === $type) {
                $this->logger->logError("Tentative de pointage du même type consécutif pour employe {$employeId}");
                $this->pdo->commit();
                return [
                    'status' => 'duplicate',
                    'reason' => 'same_type_consecutive',
                    'message' => 'Pointage du même type déjà enregistré. Vérifiez vos pointages.',
                    'employee_id' => $employeId,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Traiter le pointage
            if ($type === 'arrivee') {
                $response = $this->handleArrival($employeId, $badgeTokenId, $latitude, $longitude);
            } else {
                $response = $this->handleDeparture($employeId, $badgeTokenId, $lastPointage, $latitude, $longitude);
            }

            $this->pdo->commit();

            // Log du pointage
            $this->logger->logPointage(
                $employeId, 
                $type, 
                $response['timestamp'], 
                $tokenData['token']
            );

            return $response;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logError("Erreur traitement: " . $e->getMessage());
            throw $e;
        }
    }

    private function getLastPointage(int $employeId, string $date): ?array {
            $stmt = $this->pdo->prepare("\
                INSERT INTO pointages (\
                    date_heure, employe_id, type, statut, etat, badge_token_id, ip_address, device_info, latitude, longitude\
                ) VALUES (NOW(), ?, 'arrivee', 'présent', ?, ?, ?, ?, ?, ?)\
            ");
            LIMIT 1
        ");
        $stmt->execute([$employeId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function determinerTypePointage(?array $lastPointage): string {
        if (!$lastPointage) {
            return 'arrivee';
        }
        return ($lastPointage['type'] === 'depart') ? 'arrivee' : 'depart';
    }

    private function handleArrival(int $employeId, int $badgeTokenId, $latitude, $longitude): array {
        $now = date('Y-m-d H:i:s');
        $jourSemaine = date('N'); // 1 (lundi) à 7 (dimanche)
        $heureLimite = ($jourSemaine == 6) ? '14:00:00' : '09:00:00';
        $heureLimiteComplete = date('Y-m-d') . ' ' . $heureLimite;
        $isLate = strtotime($now) > strtotime($heureLimiteComplete);
        
        // Vérification stricte : le badge doit appartenir à l'employé et être actif
        $check = $this->pdo->prepare("SELECT id, employe_id FROM badge_tokens WHERE id = ? AND status = 'active' AND expires_at > ?");
        $check->execute([$badgeTokenId, $now]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR badge_token_id non valide ARRIVEE: $badgeTokenId pour employe $employeId\n", FILE_APPEND);
            throw new RuntimeException("Le badge utilisé n'est plus valide ou n'existe pas.");
        }
        if ((int)$row['employe_id'] !== $employeId) {
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR badge_token_id utilisé par un autre employé ARRIVEE: $badgeTokenId pour employe $employeId\n", FILE_APPEND);
            throw new RuntimeException("Ce badge n'est pas le vôtre.");
        }

        // Création automatique d'une justification si retard
        $justificationId = null;
        $etat = 'normal';
        if ($isLate) {
            $etat = 'justifie';
            // On crée d'abord le pointage pour obtenir son id
            // Requête SQL conforme à la structure réelle de la table pointages
            $stmt = $this->pdo->prepare("\
                INSERT INTO pointages (\
                    date_heure, employe_id, type, statut, etat, badge_token_id, ip_address, device_info, latitude, longitude\
                ) VALUES (NOW(), ?, 'arrivee', 'présent', ?, ?, ?, ?, ?, ?)\
            ");
            $stmt->execute([
                $employeId,
                $etat,
                $badgeTokenId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $latitude,
                $longitude
            ]);
            $idPointage = $this->pdo->lastInsertId();
            $stmtJustif = $this->pdo->prepare("INSERT INTO justificatifs (id_pointage, type_justif, description, date_ajout, etat) VALUES (?, 'retard', 'Arrivée après $heureLimite', ?, 'en_attente')");
            $stmtJustif->execute([$idPointage, $now]);
            $justificationId = $this->pdo->lastInsertId();
            // Mise à jour du pointage pour lier la justification
            $stmtUpdate = $this->pdo->prepare("UPDATE pointages SET justification_id = ? WHERE id = ?");
            $stmtUpdate->execute([$justificationId, $idPointage]);
            
            // Notification aux admins pour le justificatif de retard
            $this->notifierAdminsJustificatif($employeId, $idPointage, $justificationId, $now);
            
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] INSERT ARRIVEE+JUSTIF OK pour employe $employeId, badge $badgeTokenId\n", FILE_APPEND);
            return [
                'status' => 'success',
                'type' => 'arrivee',
                'message' => 'Arrivée enregistrée (retard justifié)',
                'retard' => $isLate,
                'timestamp' => $now,
            ];
        }

        // Cas normal (pas de retard)
        // Requête SQL conforme à la structure réelle de la table pointages
        $stmt = $this->pdo->prepare("\
            INSERT INTO pointages (\
                date_heure, employe_id, type, statut, etat, badge_token_id, ip_address, device_info, latitude, longitude\
            ) VALUES (NOW(), ?, 'depart', 'présent', 'normal', ?, ?, ?, ?, ?)\
        ");
        $stmt->execute([
            $employeId,
            $etat,
            $badgeTokenId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $latitude,
            $longitude
        ]);
        file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] INSERT ARRIVEE OK pour employe $employeId, badge $badgeTokenId\n", FILE_APPEND);


            // Récupérer données employé pour retour
            $stmtEmp = $this->pdo->prepare("SELECT id, prenom, nom, poste FROM employes WHERE id = ?");
            $stmtEmp->execute([$employeId]);
            $employe = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: [];

            // Absence si pas d'arrivée avant midi (exemple: policy)
            $absCutoff = date('Y-m-d') . ' 12:00:00';
            $isAbsent = !$isLate && (strtotime($now) > strtotime($absCutoff) && !$isLate);

            return [
                'status' => 'success',
                'type' => 'arrivee',
                'message' => 'Arrivée enregistrée',
                'retard' => $isLate,
                'absence' => $isAbsent,
                'employee' => $employe,
                'badge_token_id' => $badgeTokenId,
                'timestamp' => $now,
            ];
    }

    private function handleDeparture(int $employeId, int $badgeTokenId, array $lastPointage, $latitude, $longitude): array {
        $now = date('Y-m-d H:i:s');
        $heureLimiteDepart = '18:00:00';
        $heureLimiteCompleteDepart = date('Y-m-d') . ' ' . $heureLimiteDepart;
        $isEarlyDeparture = strtotime($now) < strtotime($heureLimiteCompleteDepart);

        if ($lastPointage['type'] !== 'arrivee') {
            throw new LogicException("Incohérence: Dernier pointage n'est pas une arrivée");
        }

        // Vérification stricte : le badge doit appartenir à l'employé et être actif
        $check = $this->pdo->prepare("SELECT id, employe_id FROM badge_tokens WHERE id = ? AND status = 'active' AND expires_at > ?");
        $check->execute([$badgeTokenId, $now]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR badge_token_id non valide DEPART: $badgeTokenId pour employe $employeId\n", FILE_APPEND);
            throw new RuntimeException("Le badge utilisé n'est plus valide ou n'existe pas.");
        }
        if ((int)$row['employe_id'] !== $employeId) {
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR badge_token_id utilisé par un autre employé DEPART: $badgeTokenId pour employe $employeId\n", FILE_APPEND);
            throw new RuntimeException("Ce badge n'est pas le vôtre.");
        }

        // Création automatique d'une justification si départ anticipé
        if ($isEarlyDeparture) {
            $stmtJustif = $this->pdo->prepare("INSERT INTO justificatifs (id_pointage, type_justif, description, date_ajout, etat) VALUES (?, 'depart_anticipé', 'Départ avant $heureLimiteDepart', ?, 'en_attente')");
            $stmtJustif->execute([$lastPointage['id'], $now]);
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] INSERT DEPART+JUSTIF OK pour employe $employeId, badge $badgeTokenId\n", FILE_APPEND);
        }

        // Enregistrement du départ
        // Requête SQL conforme à la structure réelle de la table pointages
        $stmt = $this->pdo->prepare("
            INSERT INTO pointages (
                date_pointage, employe_id, type, statut, etat, badge_token_id, ip_address, device_info, latitude, longitude
            ) VALUES (NOW(), ?, 'depart', 'présent', 'normal', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $employeId,
            $badgeTokenId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $latitude,
            $longitude
        ]);
        
                    // ---------------------------
            // LOGIQUE DU TRIGGER AFTER DEPART
            // ---------------------------

            // 1. Invalider le badge immédiatement après le départ
            $stmtInvalidate = $this->pdo->prepare("
                UPDATE badge_tokens 
                SET expires_at = NOW()
                WHERE employe_id = ? AND expires_at > NOW()
            ");
            $stmtInvalidate->execute([$employeId]);

            // 2. Ajouter un log dans badge_logs
            $stmtLog = $this->pdo->prepare("
                INSERT INTO badge_logs (employe_id, action, details, created_at)
                VALUES (?, 'invalidation', 'Badge invalidé après pointage de départ', NOW())
            ");
            $stmtLog->execute([$employeId]);

            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] BADGE INVALIDÉ + LOG OK pour employe $employeId\n", FILE_APPEND);


            // Recuperer l'employé
            $stmtEmp = $this->pdo->prepare("SELECT id, prenom, nom, poste FROM employes WHERE id = ?");
            $stmtEmp->execute([$employeId]);
            $employe = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: [];

            // calculer temps travail si on a une arrivée sur la journée
            $work = null;
            if (!empty($lastPointage) && $lastPointage['type'] === 'arrivee') {
                $times = $this->calculerTempsTravail($lastPointage['date_heure'], $now);
                $work = $times['temps_travail'];
            }

            return [
                'status' => 'success',
                'type' => 'depart',
                'message' => 'Départ enregistré',
                'early_departure' => $isEarlyDeparture,
                'work_time' => $work,
                'employee' => $employe,
                'badge_token_id' => $badgeTokenId,
                'timestamp' => $now,
            ];
    }

    private function calculerTempsTravail(string $debut, string $fin): array {
        $debutDt = new DateTime($debut);
        $finDt = new DateTime($fin);

        if ($finDt < $debutDt) {
            throw new InvalidArgumentException("Heure de fin antérieure au début");
        }

        $interval = $debutDt->diff($finDt);
        $totalSeconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        // Pause d'1h si > 4h de travail effectif (cf. logique vue en SQL)
        $pauseSeconds = ($totalSeconds > 4 * 3600) ? 3600 : 0;
        $workSeconds = max(0, $totalSeconds - $pauseSeconds);

        return [
            'temps_travail' => gmdate('H:i:s', $workSeconds),
            'temps_pause' => gmdate('H:i:s', $pauseSeconds)
        ];
    }

    /**
     * Notifie tous les admins lorsqu'un justificatif de retard est créé
     */
    private function notifierAdminsJustificatif(int $employeId, int $pointageId, int $justificationId, string $datePointage): void {
        try {
            // Récupérer les informations de l'employé
            $stmtEmp = $this->pdo->prepare("SELECT nom, prenom FROM employes WHERE id = ?");
            $stmtEmp->execute([$employeId]);
            $employe = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            
            if (!$employe) {
                return;
            }
            
            $nomComplet = trim($employe['prenom'] . ' ' . $employe['nom']);
            $heurePointage = date('H:i', strtotime($datePointage));
            $datePointageFormatee = date('d/m/Y', strtotime($datePointage));
            
            // Récupérer tous les admins actifs
            $stmtAdmins = $this->pdo->query("SELECT id FROM admins WHERE role IN ('admin', 'super_admin')");
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
            
            // Créer une notification pour chaque admin
            foreach ($admins as $admin) {
                $titre = "Justificatif de retard - " . $nomComplet;
                $contenu = "L'employé {$nomComplet} a pointé son arrivée en retard le {$datePointageFormatee} à {$heurePointage}. Un justificatif a été créé automatiquement et nécessite votre validation.";
                $lien = "admin_demandes.php?justification_id={$justificationId}";
                
                // Créer une notification pour les admins (employe_id = 0 ou NULL pour notifications globales)
                // La table notifications a les champs: titre, contenu, message, type, etc.
                $stmtNotif = $this->pdo->prepare("
                    INSERT INTO notifications (
                        employe_id, titre, contenu, message, type, pointage_id, lien, lue, date_creation, date
                    ) VALUES (0, ?, ?, ?, 'justif_retard', ?, ?, 0, NOW(), NOW())
                ");
                $stmtNotif->execute([
                    $titre,
                    $contenu,
                    $contenu, // message = contenu pour compatibilité
                    $pointageId,
                    $lien
                ]);
            }
            
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] Notifications envoyées aux admins pour justificatif ID: $justificationId\n", FILE_APPEND);
        } catch (Exception $e) {
            // Log l'erreur mais ne pas bloquer le processus de pointage
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR notification admin: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

class PointageLogger {
    private $logFile;

    public function __construct() {
        $dir = __DIR__ . '/logs/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->logFile = $dir . 'pointage_system.log';
    }

    public function logPointage(int $employeId, string $type, string $timestamp, string $tokenHash) {
        $entry = sprintf(
            "[%s] POINTAGE - Employé: %d | Type: %s | Token: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $employeId,
            strtoupper($type),
            substr($tokenHash, 0, 12) . '...',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }

    public function logError(string $message) {
        $entry = sprintf(
            "[%s] ERREUR - %s | Trace: %s\n",
            date('Y-m-d H:i:s'),
            $message,
            json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))
        );
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
}

// API entry point
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $system = new PointageSystem($pdo);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $system->handlePointageRequest($data);
        // Toujours forcer un JSON valide
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $e) {
        // En cas d'erreur fatale, forcer un JSON propre
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur système: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}