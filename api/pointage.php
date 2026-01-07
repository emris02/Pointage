<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';
require_once __DIR__ . '/../src/services/WorkTimeCalculator.php';

class PointageSystem {
    private PDO $pdo;
    private PointageLogger $logger;
    private WorkTimeCalculator $workTimeCalculator;
    private array $config;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->logger = new PointageLogger();
        $this->workTimeCalculator = new WorkTimeCalculator($pdo);
        $this->loadConfig();
    }

    private function loadConfig(): void {
        // Chargement des paramètres de configuration
        $this->config = [
            'working_hours' => [
                'monday_friday' => [
                    'start' => '08:00:00',
                    'end' => '17:00:00',
                    'late_threshold' => '09:00:00',
                    'early_departure_threshold' => '16:00:00'
                ],
                'saturday' => [
                    'start' => '08:00:00',
                    'end' => '12:00:00',
                    'late_threshold' => '08:30:00',
                    'early_departure_threshold' => '11:30:00'
                ],
                'sunday' => [
                    'start' => null,
                    'end' => null,
                    'late_threshold' => null,
                    'early_departure_threshold' => null
                ]
            ],
            'break_policy' => [
                'min_work_for_break' => 4 * 3600, // 4 heures en secondes
                'break_duration' => 3600, // 1 heure en secondes
                'auto_break_enabled' => true
            ],
            'validation' => [
                'max_distance_meters' => 1000, // Distance max pour validation géo
                'allow_remote' => true
            ]
        ];
    }

    public function handlePointageRequest(array $requestData): array {
        try {
            // 1. Validation et extraction des données
            $extractedData = $this->extractAndValidateRequestData($requestData);
            
            if (isset($extractedData['error'])) {
                return $this->formatErrorResponse($extractedData['error']);
            }

            // 2. Vérification du token
            $tokenData = $this->verifyBadgeToken($extractedData['badge_token']);
            
            if (isset($tokenData['error'])) {
                return $this->formatTokenErrorResponse($tokenData);
            }

            // 3. Validation de la session (éviter les doublons)
            $sessionCheck = $this->checkSession($tokenData['employe_id'], $extractedData['session_id'] ?? null);
            if (!$sessionCheck['valid']) {
                return $sessionCheck['response'];
            }

            // 4. Traitement du pointage
            $result = $this->processPointage($tokenData, $extractedData);

            // 5. Mettre à jour les statistiques
            $this->updateStatistics($tokenData['employe_id']);

            return $result;

        } catch (Exception $e) {
            $this->logger->logError('Erreur système', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'system_error',
                'message' => 'Une erreur système est survenue',
                'timestamp' => date('Y-m-d H:i:s'),
                'debug_id' => uniqid('ERR_', true)
            ];
        }
    }

    private function extractAndValidateRequestData(array $data): array {
        // Extraction du token depuis plusieurs sources possibles
        $badgeToken = $this->extractToken($data);
        
        if (empty($badgeToken)) {
            return ['error' => [
                'code' => 'TOKEN_REQUIRED',
                'message' => 'Token de badge manquant',
                'details' => 'Le champ badge_token est requis'
            ]];
        }

        // Extraction de la géolocalisation
        $location = $this->extractLocation($data);
        
        // Validation de la géolocalisation si fournie
        if ($location && !$this->validateLocation($location)) {
            return ['error' => [
                'code' => 'INVALID_LOCATION',
                'message' => 'Coordonnées GPS invalides',
                'details' => 'Les coordonnées doivent être valides et non nulles'
            ]];
        }

        // Extraction des métadonnées
        $metadata = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => $data['session_id'] ?? session_id(),
            'device_info' => $data['device_info'] ?? null
        ];

        return [
            'badge_token' => $badgeToken,
            'location' => $location,
            'metadata' => $metadata
        ];
    }

    private function extractToken(array $data): ?string {
        $token = $data['badge_token'] 
            ?? $data['token'] 
            ?? $data['raw_token'] 
            ?? ($data['scan_data']['token'] ?? null);

        if ($token && is_string($token)) {
            $token = trim($token);
            return !empty($token) ? $token : null;
        }

        return null;
    }

    private function extractLocation(array $data): ?array {
        $latitude = null;
        $longitude = null;

        // Essayer plusieurs formats
        if (isset($data['latitude']) && isset($data['longitude'])) {
            $latitude = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
        } elseif (isset($data['location'])) {
            $loc = $data['location'];
            if (is_array($loc)) {
                $latitude = filter_var($loc['latitude'] ?? $loc['lat'] ?? null, FILTER_VALIDATE_FLOAT);
                $longitude = filter_var($loc['longitude'] ?? $loc['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            }
        } elseif (isset($data['scan_data']['location'])) {
            $loc = $data['scan_data']['location'];
            $latitude = filter_var($loc['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($loc['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        }

        if ($latitude !== false && $longitude !== false && 
            $latitude !== null && $longitude !== null &&
            abs($latitude) <= 90 && abs($longitude) <= 180) {
            
            // Rejeter les coordonnées 0,0
            if ($latitude == 0 && $longitude == 0) {
                return null;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $data['accuracy'] ?? $data['scan_data']['accuracy'] ?? null
            ];
        }

        return null;
    }

    private function validateLocation(array $location): bool {
        // Vérification basique des coordonnées
        if (!isset($location['latitude'], $location['longitude'])) {
            return false;
        }

        // Coordonnées 0,0 sont invalides
        if ($location['latitude'] == 0 && $location['longitude'] == 0) {
            return false;
        }

        // Vérifier les plages valides
        if ($location['latitude'] < -90 || $location['latitude'] > 90 ||
            $location['longitude'] < -180 || $location['longitude'] > 180) {
            return false;
        }

        return true;
    }

    private function verifyBadgeToken(string $token): array {
        try {
            $tokenData = BadgeManager::verifyToken($token, $this->pdo);
            
            if (!isset($tokenData['employe_id'], $tokenData['badge_token_id'])) {
                throw new RuntimeException("Données du token incomplètes");
            }

            return $tokenData;

        } catch (InvalidArgumentException $e) {
            return ['error' => [
                'type' => 'invalid_format',
                'message' => $e->getMessage()
            ]];
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $errorType = $this->classifyTokenError($message);
            
            return ['error' => [
                'type' => $errorType,
                'message' => $message
            ]];
        }
    }

    private function classifyTokenError(string $message): string {
        $lowerMessage = strtolower($message);
        
        if (strpos($lowerMessage, 'expir') !== false) {
            return 'token_expired';
        } elseif (strpos($lowerMessage, 'n\'existe pas') !== false || 
                  strpos($lowerMessage, 'invalide') !== false) {
            return 'token_invalid';
        } elseif (strpos($lowerMessage, 'ne vous appartient pas') !== false) {
            return 'token_ownership_mismatch';
        } elseif (strpos($lowerMessage, 'inactif') !== false) {
            return 'token_inactive';
        }
        
        return 'token_verification_failed';
    }

    private function formatErrorResponse(array $error): array {
        $response = [
            'status' => 'error',
            'code' => $error['code'],
            'message' => $error['message'],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (isset($error['details'])) {
            $response['details'] = $error['details'];
        }

        return $response;
    }

    private function formatTokenErrorResponse(array $tokenError): array {
        $errorType = $tokenError['error']['type'] ?? 'unknown';
        $statusMap = [
            'token_expired' => 'expired',
            'token_invalid' => 'invalid',
            'token_ownership_mismatch' => 'refused',
            'token_inactive' => 'inactive',
            'invalid_format' => 'invalid'
        ];

        return [
            'status' => $statusMap[$errorType] ?? 'error',
            'reason' => $errorType,
            'message' => $tokenError['error']['message'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function checkSession(int $employeId, ?string $sessionId): array {
        if (!$sessionId) {
            return ['valid' => true];
        }

        // Vérifier si une session récente existe (dans les 30 dernières secondes)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM pointage_sessions 
            WHERE employe_id = ? 
            AND session_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");
        
        $stmt->execute([$employeId, $sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            return [
                'valid' => false,
                'response' => [
                    'status' => 'duplicate',
                    'reason' => 'recent_session_exists',
                    'message' => 'Une session de pointage récente existe déjà',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }

        // Enregistrer la nouvelle session
        $stmt = $this->pdo->prepare("
            INSERT INTO pointage_sessions (employe_id, session_id, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE created_at = NOW()
        ");
        
        $stmt->execute([$employeId, $sessionId]);

        return ['valid' => true];
    }

    private function processPointage(array $tokenData, array $extractedData): array {
        $employeId = (int)$tokenData['employe_id'];
        $badgeTokenId = (int)$tokenData['badge_token_id'];
        $today = date('Y-m-d');

        $this->pdo->beginTransaction();

        try {
            // 1. Vérifier si le badge a déjà été utilisé aujourd'hui
            $duplicateCheck = $this->checkBadgeDuplicate($badgeTokenId, $today, $employeId);
            
            if ($duplicateCheck['is_duplicate']) {
                $this->pdo->commit();
                return $duplicateCheck['response'];
            }

            // 2. Récupérer le dernier pointage du jour
            $lastPointage = $this->getLastPointage($employeId, $today);

            // 3. Déterminer le type de pointage
            $pointageType = $this->determinePointageType($lastPointage);

            // 4. Validation de la séquence des pointages
            $sequenceValid = $this->validatePointageSequence($lastPointage, $pointageType);
            
            if (!$sequenceValid['valid']) {
                $this->pdo->commit();
                return $sequenceValid['response'];
            }

            // 5. Traitement selon le type
            if ($pointageType === 'arrivee') {
                $result = $this->processArrival(
                    $employeId, 
                    $badgeTokenId, 
                    $extractedData['location'], 
                    $extractedData['metadata']
                );
            } else {
                $result = $this->processDeparture(
                    $employeId, 
                    $badgeTokenId, 
                    $lastPointage, 
                    $extractedData['location'], 
                    $extractedData['metadata']
                );
            }

            $this->pdo->commit();

            // 6. Log du pointage
            $this->logger->logPointage($employeId, $pointageType, $result['timestamp'], $tokenData['token']);

            // 7. Notification si nécessaire
            $this->handleNotifications($employeId, $pointageType, $result);

            return $result;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function checkBadgeDuplicate(int $badgeTokenId, string $date, int $employeId): array {
        $stmt = $this->pdo->prepare("
            SELECT p.*, e.prenom, e.nom 
            FROM pointages p
            LEFT JOIN employes e ON e.id = p.employe_id
            WHERE p.badge_token_id = ? 
            AND DATE(p.date_heure) = ? 
            LIMIT 1
        ");
        
        $stmt->execute([$badgeTokenId, $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            return ['is_duplicate' => false];
        }

        if ((int)$existing['employe_id'] === $employeId) {
            return [
                'is_duplicate' => true,
                'response' => [
                    'status' => 'duplicate',
                    'reason' => 'badge_already_used_today',
                    'message' => 'Vous avez déjà utilisé ce badge aujourd\'hui',
                    'existing_pointage' => [
                        'id' => $existing['id'],
                        'type' => $existing['type'],
                        'time' => $existing['date_heure'],
                        'employee' => trim($existing['prenom'] . ' ' . $existing['nom'])
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            return [
                'is_duplicate' => true,
                'response' => [
                    'status' => 'refused',
                    'reason' => 'badge_used_by_other',
                    'message' => 'Ce badge a déjà été utilisé par un autre employé aujourd\'hui',
                    'used_by' => trim($existing['prenom'] . ' ' . $existing['nom']),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
    }

    private function getLastPointage(int $employeId, string $date): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pointages 
            WHERE employe_id = ? 
            AND DATE(date_heure) = ? 
            ORDER BY date_heure DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$employeId, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    private function determinePointageType(?array $lastPointage): string {
        if (!$lastPointage || $lastPointage['type'] === 'depart') {
            return 'arrivee';
        }
        return 'depart';
    }

    private function validatePointageSequence(?array $lastPointage, string $type): array {
        if (!$lastPointage) {
            return ['valid' => true];
        }

        // Empêcher deux pointages du même type consécutifs
        if ($lastPointage['type'] === $type) {
            return [
                'valid' => false,
                'response' => [
                    'status' => 'invalid_sequence',
                    'reason' => 'consecutive_same_type',
                    'message' => 'Pointage du même type déjà enregistré',
                    'last_pointage' => [
                        'type' => $lastPointage['type'],
                        'time' => $lastPointage['date_heure']
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }

        return ['valid' => true];
    }

    private function processArrival(int $employeId, int $badgeTokenId, ?array $location, array $metadata): array {
        $now = new DateTime();
        $today = $now->format('Y-m-d');
        
        // Vérifier si l'employé a déjà pointé son arrivée aujourd'hui
        $stmt = $this->pdo->prepare("
            SELECT id FROM pointages 
            WHERE employe_id = ? 
            AND DATE(date_heure) = ? 
            AND type = 'arrivee'
            LIMIT 1
        ");
        
        $stmt->execute([$employeId, $today]);
        if ($stmt->fetch()) {
            throw new LogicException("Arrivée déjà enregistrée aujourd'hui");
        }

        // Vérifier la validité du badge
        $this->validateBadgeForArrival($badgeTokenId, $employeId);

        // Déterminer si l'arrivée est en retard
        $isLate = $this->isLateArrival($now);
        $etat = $isLate ? 'retard' : 'normal';
        $justificationId = null;

        // Enregistrer le pointage d'arrivée
        $pointageId = $this->insertPointage([
            'employe_id' => $employeId,
            'badge_token_id' => $badgeTokenId,
            'type' => 'arrivee',
            'etat' => $etat,
            'location' => $location,
            'metadata' => $metadata
        ]);

        // Créer un justificatif si retard
        if ($isLate) {
            $justificationId = $this->createLateJustification($pointageId, $now);
            $this->notifyAdminsForLateArrival($employeId, $pointageId, $justificationId, $now);
        }

        // Récupérer les infos de l'employé
        $employeeInfo = $this->getEmployeeInfo($employeId);

        return [
            'status' => 'success',
            'type' => 'arrivee',
            'message' => $isLate ? 'Arrivée enregistrée (retard justifié)' : 'Arrivée enregistrée',
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'employee' => $employeeInfo,
            'is_late' => $isLate,
            'justification_id' => $justificationId,
            'pointage_id' => $pointageId
        ];
    }

    private function processDeparture(int $employeId, int $badgeTokenId, array $lastPointage, ?array $location, array $metadata): array {
        $now = new DateTime();
        
        // Vérifier qu'il y a bien une arrivée avant le départ
        if ($lastPointage['type'] !== 'arrivee') {
            throw new LogicException("Départ impossible sans arrivée préalable");
        }

        // Vérifier la validité du badge
        $this->validateBadgeForDeparture($badgeTokenId, $employeId);

        // Déterminer si le départ est anticipé
        $isEarlyDeparture = $this->isEarlyDeparture($now);
        
        // Enregistrer le pointage de départ
        $pointageId = $this->insertPointage([
            'employe_id' => $employeId,
            'badge_token_id' => $badgeTokenId,
            'type' => 'depart',
            'etat' => $isEarlyDeparture ? 'depart_anticipé' : 'normal',
            'location' => $location,
            'metadata' => $metadata
        ]);

        // Créer un justificatif si départ anticipé
        if ($isEarlyDeparture) {
            $this->createEarlyDepartureJustification($lastPointage['id'], $now);
        }

        // Calculer le temps de travail
        $workTime = $this->workTimeCalculator->calculateWorkTime(
            $lastPointage['date_heure'],
            $now->format('Y-m-d H:i:s'),
            $employeId
        );

        // Invalider le badge (logique métier)
        $this->invalidateBadgeAfterDeparture($employeId);

        // Récupérer les infos de l'employé
        $employeeInfo = $this->getEmployeeInfo($employeId);

        return [
            'status' => 'success',
            'type' => 'depart',
            'message' => $isEarlyDeparture ? 'Départ enregistré (anticipé)' : 'Départ enregistré',
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'employee' => $employeeInfo,
            'is_early_departure' => $isEarlyDeparture,
            'work_time' => $workTime,
            'pointage_id' => $pointageId
        ];
    }

    private function validateBadgeForArrival(int $badgeTokenId, int $employeId): void {
        $stmt = $this->pdo->prepare("
            SELECT id, employe_id, expires_at 
            FROM badge_tokens 
            WHERE id = ? 
            AND status = 'active' 
            AND expires_at > NOW()
        ");
        
        $stmt->execute([$badgeTokenId]);
        $badge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$badge) {
            throw new RuntimeException("Le badge utilisé n'est plus valide ou n'existe pas");
        }

        if ((int)$badge['employe_id'] !== $employeId) {
            throw new RuntimeException("Ce badge n'est pas le vôtre");
        }
    }

    private function validateBadgeForDeparture(int $badgeTokenId, int $employeId): void {
        // Même validation que pour l'arrivée
        $this->validateBadgeForArrival($badgeTokenId, $employeId);
    }

    private function isLateArrival(DateTime $arrivalTime): bool {
        $dayOfWeek = $arrivalTime->format('N'); // 1=lundi, 7=dimanche
        $hour = $arrivalTime->format('H:i:s');
        
        $threshold = $this->config['working_hours'][$dayOfWeek == 6 ? 'saturday' : 'monday_friday']['late_threshold'];
        
        if (!$threshold) {
            return false;
        }
        
        return $hour > $threshold;
    }

    private function isEarlyDeparture(DateTime $departureTime): bool {
        $dayOfWeek = $departureTime->format('N');
        $hour = $departureTime->format('H:i:s');
        
        $threshold = $this->config['working_hours'][$dayOfWeek == 6 ? 'saturday' : 'monday_friday']['early_departure_threshold'];
        
        if (!$threshold) {
            return false;
        }
        
        return $hour < $threshold;
    }

    private function insertPointage(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO pointages (
                date_heure, employe_id, type, etat, badge_token_id,
                ip_address, device_info, latitude, longitude, created_at
            ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['employe_id'],
            $data['type'],
            $data['etat'],
            $data['badge_token_id'],
            $data['metadata']['ip_address'],
            $data['metadata']['user_agent'],
            $data['location']['latitude'] ?? null,
            $data['location']['longitude'] ?? null
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    private function createLateJustification(int $pointageId, DateTime $arrivalTime): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO justificatifs (
                id_pointage, type_justif, description, date_ajout, etat
            ) VALUES (?, 'retard', ?, NOW(), 'en_attente')
        ");
        
        $description = "Arrivée à " . $arrivalTime->format('H:i');
        $stmt->execute([$pointageId, $description]);
        
        return (int)$this->pdo->lastInsertId();
    }

    private function createEarlyDepartureJustification(int $pointageId, DateTime $departureTime): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO justificatifs (
                id_pointage, type_justif, description, date_ajout, etat
            ) VALUES (?, 'depart_anticipé', ?, NOW(), 'en_attente')
        ");
        
        $description = "Départ à " . $departureTime->format('H:i');
        $stmt->execute([$pointageId, $description]);
    }

    private function notifyAdminsForLateArrival(int $employeId, int $pointageId, int $justificationId, DateTime $arrivalTime): void {
        try {
            $employeeInfo = $this->getEmployeeInfo($employeId);
            
            $titre = "Justificatif de retard - " . $employeeInfo['full_name'];
            $contenu = "L'employé {$employeeInfo['full_name']} a pointé son arrivée en retard à {$arrivalTime->format('H:i')}. Un justificatif a été créé automatiquement.";
            
            // Récupérer tous les admins
            $stmt = $this->pdo->query("SELECT id FROM admins WHERE role IN ('admin', 'super_admin')");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($admins as $admin) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO notifications (
                        admin_id, titre, contenu, type, pointage_id, lien, lue, date_creation
                    ) VALUES (?, ?, ?, 'justif_retard', ?, ?, 0, NOW())
                ");
                
                $lien = "admin/justifications.php?id={$justificationId}";
                $stmt->execute([$admin['id'], $titre, $contenu, $pointageId, $lien]);
            }
        } catch (Exception $e) {
            // Log mais ne pas bloquer
            $this->logger->logError('Notification admin échouée', ['error' => $e->getMessage()]);
        }
    }

    private function invalidateBadgeAfterDeparture(int $employeId): void {
        // Logique d'invalidation du badge après départ
        $stmt = $this->pdo->prepare("
            UPDATE badge_tokens 
            SET expires_at = NOW(),
                status = 'used',
                last_used_at = NOW()
            WHERE employe_id = ? 
            AND status = 'active'
        ");
        
        $stmt->execute([$employeId]);
        
        // Log de l'action
        $stmt = $this->pdo->prepare("
            INSERT INTO badge_logs (employe_id, action, details, created_at)
            VALUES (?, 'invalidation', 'Badge invalidé après pointage de départ', NOW())
        ");
        
        $stmt->execute([$employeId]);
    }

    private function getEmployeeInfo(int $employeId): array {
        $stmt = $this->pdo->prepare("
            SELECT id, prenom, nom, poste, email, departement 
            FROM employes 
            WHERE id = ?
        ");
        
        $stmt->execute([$employeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return [];
        }
        
        return [
            'id' => $employee['id'],
            'full_name' => trim($employee['prenom'] . ' ' . $employee['nom']),
            'first_name' => $employee['prenom'],
            'last_name' => $employee['nom'],
            'position' => $employee['poste'],
            'email' => $employee['email'],
            'department' => $employee['departement']
        ];
    }

    private function handleNotifications(int $employeId, string $pointageType, array $result): void {
        // Logique de notification (email, push, etc.)
        // À implémenter selon vos besoins
    }

    private function updateStatistics(int $employeId): void {
        // Mettre à jour les statistiques de l'employé
        // À implémenter selon vos besoins
    }
}

class WorkTimeCalculator {
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $this->config = [
            'break_threshold' => 4 * 3600, // 4 heures
            'break_duration' => 3600, // 1 heure
            'working_days' => [1, 2, 3, 4, 5, 6], // lundi-samedi
            'daily_work_hours' => 8 * 3600 // 8 heures
        ];
    }

    public function calculateWorkTime(string $startTime, string $endTime, int $employeId): array {
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);
        
        if ($end < $start) {
            throw new InvalidArgumentException("L'heure de fin doit être après l'heure de début");
        }

        $interval = $start->diff($end);
        $totalSeconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;

        // Calculer la pause automatique si nécessaire
        $breakSeconds = $this->calculateAutomaticBreak($totalSeconds, $employeId, $start);
        $workSeconds = max(0, $totalSeconds - $breakSeconds);

        return [
            'total_duration' => gmdate('H:i:s', $totalSeconds),
            'break_duration' => gmdate('H:i:s', $breakSeconds),
            'work_duration' => gmdate('H:i:s', $workSeconds),
            'total_seconds' => $totalSeconds,
            'break_seconds' => $breakSeconds,
            'work_seconds' => $workSeconds
        ];
    }

    private function calculateAutomaticBreak(int $totalSeconds, int $employeId, DateTime $start): int {
        // Si le temps de travail est inférieur au seuil, pas de pause
        if ($totalSeconds <= $this->config['break_threshold']) {
            return 0;
        }

        // Vérifier si l'employé a déjà pris une pause aujourd'hui
        $hasTakenBreak = $this->hasTakenBreakToday($employeId, $start->format('Y-m-d'));
        
        if ($hasTakenBreak) {
            return 0; // Déjà pris sa pause
        }

        // Enregistrer automatiquement la pause
        $this->recordAutomaticBreak($employeId, $start, $totalSeconds);

        return $this->config['break_duration'];
    }

    private function hasTakenBreakToday(int $employeId, string $date): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM pauses 
            WHERE employe_id = ? 
            AND DATE(debut) = ? 
            AND type = 'auto'
        ");
        
        $stmt->execute([$employeId, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }

    private function recordAutomaticBreak(int $employeId, DateTime $start, int $workDuration): void {
        $breakStart = clone $start;
        $breakStart->modify("+{$this->config['break_threshold']} seconds");
        
        $breakEnd = clone $breakStart;
        $breakEnd->modify("+{$this->config['break_duration']} seconds");

        $stmt = $this->pdo->prepare("
            INSERT INTO pauses (
                employe_id, debut, fin, duree, type, auto_generated, created_at
            ) VALUES (?, ?, ?, ?, 'auto', 1, NOW())
        ");
        
        $stmt->execute([
            $employeId,
            $breakStart->format('Y-m-d H:i:s'),
            $breakEnd->format('Y-m-d H:i:s'),
            $this->config['break_duration']
        ]);
    }
}

class PointageLogger {
    private string $logDir;
    private array $logFiles;

    public function __construct() {
        $this->logDir = __DIR__ . '/../logs/pointage/';
        $this->ensureLogDirectory();
        $this->logFiles = [
            'system' => $this->logDir . 'system.log',
            'pointages' => $this->logDir . 'pointages.log',
            'errors' => $this->logDir . 'errors.log',
            'debug' => $this->logDir . 'debug.log'
        ];
    }

    private function ensureLogDirectory(): void {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public function logPointage(int $employeId, string $type, string $timestamp, string $tokenHash): void {
        $entry = json_encode([
            'timestamp' => $timestamp,
            'type' => 'pointage',
            'employee_id' => $employeId,
            'pointage_type' => $type,
            'token_hash' => substr($tokenHash, 0, 12),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], JSON_UNESCAPED_UNICODE);

        $this->writeLog('pointages', $entry);
    }

    public function logError(string $message, array $context = []): void {
        $entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'error',
            'message' => $message,
            'context' => $context,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ], JSON_UNESCAPED_UNICODE);

        $this->writeLog('errors', $entry);
    }

    public function logDebug(string $message, array $data = []): void {
        $entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'debug',
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);

        $this->writeLog('debug', $entry);
    }

    private function writeLog(string $type, string $entry): void {
        if (isset($this->logFiles[$type])) {
            file_put_contents($this->logFiles[$type], $entry . PHP_EOL, FILE_APPEND);
        }
    }
}

// Point d'entrée API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Initialiser le système
        $system = new PointageSystem($pdo);
        
        // Récupérer les données
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?? [];
        
        // Ajouter les données POST si nécessaire
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }
        
        // Traiter la requête
        $response = $system->handlePointageRequest($data);
        
        // Envoyer la réponse
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Throwable $e) {
        http_response_code(500);
        
        $response = [
            'status' => 'system_error',
            'message' => 'Une erreur critique est survenue',
            'error_id' => uniqid('CRIT_', true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // En production, ne pas exposer les détails d'erreur
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    exit;
}