<?php

// Inclure les modèles nécessaires
require_once __DIR__ . '/../models/Employe.php';
require_once __DIR__ . '/../models/Pointage.php';

class PointageController
{
    private $badgeModel;
    private $pointageModel;
    private $employeModel;

    public function __construct($db)
    {
        // Initialisation des modèles avec la connexion DB
        // Use the actual model class names present in src/models
        $this->badgeModel = new Badge($db);
        $this->pointageModel = new Pointage($db);
        $this->employeModel = new Employe($db);
    }

    /**
     * Pointage principal - endpoint API
     */
    public function processPointage(): array
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            return $this->processPointageFromArray($data);
        } catch (Exception $e) {
            http_response_code(400);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Core logic extracted to allow direct calls from tests
     */
    public function processPointageFromArray(?array $data): array
    {
        header('Content-Type: application/json');
        if (!$data || !isset($data['token'])) {
            throw new InvalidArgumentException('Token manquant');
        }
        // Vérifier le token
        try {
            $tokenData = $this->badgeModel->verifyToken($data['token']);
        } catch (Exception $e) {
            http_response_code(401);
            return ['success' => false, 'message' => 'Badge invalide ou inactif', 'detail' => $e->getMessage()];
        }
        $employeId = $tokenData['employe_id'];

        // Log du scan (historique des scans, même si refus)
        $this->logScanAttempt($employeId, $tokenData['badge_token_id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $data);
        // Collecte de la localisation
        $location = $this->getLocationData($data);

        // Déterminer le type de pointage
        $type = $this->determinePointageType($employeId);
        if (!$type) {
            throw new RuntimeException('Impossible de déterminer le type de pointage');
        }

        // Vérification anti-doublon
        if (!$this->pointageModel->canPoint($employeId, $type)) {
            http_response_code(409);
            $this->logAudit($employeId, $tokenData['badge_token_id'] ?? null, $type, 'duplicate_today');

            return $this->buildErrorResponse($employeId, $tokenData, $type, $location, 'Pointage déjà effectué aujourd\'hui pour ce type');
        }

        // Enregistrer le pointage avec toutes les données nécessaires
        $pointageData = [
            'employe_id' => $employeId,
            'type' => $type,
            'statut' => 'présent',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'badge_token_id' => $tokenData['badge_token_id'] ?? null,
            'etat' => 'normal'
        ];
        
        // Ajouter les données de localisation si disponibles (support lat/lon et latitude/longitude)
        if ($location) {
            if (isset($location['latitude']) && isset($location['longitude'])) {
                $pointageData['latitude'] = (float)$location['latitude'];
                $pointageData['longitude'] = (float)$location['longitude'];
            } elseif (isset($location['lat']) && isset($location['lon'])) {
                $pointageData['latitude'] = (float)$location['lat'];
                $pointageData['longitude'] = (float)$location['lon'];
            }
        }
        
        $pointageId = $this->pointageModel->create($pointageData);

        // Calculer les heures travaillées
        $workHours = $this->pointageModel->calculateWorkHours($employeId, date('Y-m-d'));

        // Journaliser le succès
        $this->logAudit($employeId, $tokenData['badge_token_id'] ?? null, $type, 'success');

        return $this->buildSuccessResponse($employeId, $tokenData, $type, $location, $pointageId, $workHours);
    }

    /**
     * Récupère les données de localisation
     */
    private function getLocationData(array $data): array
    {
        $location = [
            'lat' => null,
            'lon' => null,
            'precision' => null,
            'source' => null,
            'address' => null
        ];
        
        if (!empty($data['location']) && isset($data['location']['lat'], $data['location']['lon'])) {
            $location['lat'] = floatval($data['location']['lat']);
            $location['lon'] = floatval($data['location']['lon']);
            $location['precision'] = isset($data['location']['precision']) ? floatval($data['location']['precision']) : null;
            $location['source'] = 'GPS';
        } else {
            // Fallback: géolocalisation par IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                try {
                    $resp = @file_get_contents("http://ip-api.com/json/" . urlencode($ip) . "?fields=status,message,lat,lon,city,regionName,country");
                    if ($resp) {
                        $json = json_decode($resp, true);
                        if (!empty($json) && isset($json['status']) && $json['status'] === 'success') {
                            $location['lat'] = isset($json['lat']) ? floatval($json['lat']) : null;
                            $location['lon'] = isset($json['lon']) ? floatval($json['lon']) : null;
                            $location['source'] = 'IP';
                            $location['address'] = trim(implode(', ', array_filter([
                                $json['city'] ?? null, 
                                $json['regionName'] ?? null, 
                                $json['country'] ?? null
                            ])));
                        }
                    }
                } catch (Exception $e) {
                    // Ignorer les erreurs réseau
                }
            }
        }

        return $location;
    }

    /**
     * Détermine le type de pointage
     */
    private function determinePointageType(int $employeId): string
    {
        // Déduire le type depuis le dernier pointage connu
        $last = $this->pointageModel->getLastByEmploye($employeId);
        if (!$last) {
            return POINTAGE_ARRIVEE;
        }

        return $last['type'] === POINTAGE_ARRIVEE ? POINTAGE_DEPART : POINTAGE_ARRIVEE;
    }

    /**
     * Log les tentatives de scan (qu'elles réussissent ou non)
     */
    private function logScanAttempt(int $employeId = null, $badgeTokenId = null, string $ip = 'unknown', array $payload = []): void {
        $logDir = __DIR__ . '/../../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'scan_history.log';
        $line = sprintf("[%s] employe=%s badge=%s ip=%s payload=%s\n",
            date('Y-m-d H:i:s'),
            $employeId ?? 'null',
            $badgeTokenId ?? 'null',
            $ip,
            json_encode($payload)
        );
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Construit la réponse d'erreur
     */
    private function buildErrorResponse(int $employeId, array $tokenData, string $type, array $location, string $message): array
    {
        $now = time();
        $jourFr = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'][date('N', $now)-1];
        
        return [
            'success' => false,
            'message' => $message,
            'code' => 'already_pointed_today',
            'employeeId' => $employeId,
            'badgeId' => $tokenData['badge_token_id'] ?? null,
            'prenom' => $tokenData['prenom'] ?? null,
            'nom' => $tokenData['nom'] ?? null,
            'date' => date('Y-m-d', $now),
            'jour' => $jourFr,
            'heure' => date('H:i:s', $now),
            'event' => $type === POINTAGE_ARRIVEE ? 'arrival' : 'departure',
            'location' => $location
        ];
    }

    /**
     * Construit la réponse de succès
     */
    private function buildSuccessResponse(int $employeId, array $tokenData, string $type, array $location, $pointageId, $workHours): array
    {
        $now = time();
        $jourFr = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'][date('N', $now)-1];
        
        return [
            'success' => true,
            'message' => 'Pointage enregistré avec succès',
            'employeeId' => $employeId,
            'badgeId' => $tokenData['badge_token_id'] ?? null,
            'prenom' => $tokenData['prenom'] ?? null,
            'nom' => $tokenData['nom'] ?? null,
            'date' => date('Y-m-d', $now),
            'jour' => $jourFr,
            'heure' => date('H:i:s', $now),
            'event' => $type === POINTAGE_ARRIVEE ? 'arrival' : 'departure',
            'location' => $location,
            'pointage_id' => $pointageId,
            'work_hours' => $workHours
        ];
    }

        /**
         * Récupère l'historique d'un employé sur une période (utilisé par les vues)
         * Si startDate ou endDate sont null, on prend le mois courant par défaut.
         */
        public function getEmployeHistory(int $employeId, ?string $startDate = null, ?string $endDate = null, ?int $limit = null): array {
            $startDate = $startDate ?: date('Y-m-01');
            $endDate = $endDate ?: date('Y-m-d');

            $rows = $this->pointageModel->getByEmployeAndPeriod($employeId, $startDate, $endDate);

            // Trier par date décroissante (plus récent d'abord)
            usort($rows, function($a, $b) {
                $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                return $tb <=> $ta;
            });

            if ($limit !== null && is_int($limit)) {
                return array_slice($rows, 0, $limit);
            }

            return $rows;
        }
    /**
     * Génère un rapport de pointages
     */
    public function generateReport(?int $employeId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? date('Y-m-01');
        $endDate = $endDate ?? date('Y-m-d');

        $report = [
            'summary' => [],
            'details' => []
        ];
        
        if ($employeId) {
            // Rapport pour un employé spécifique
            $pointages = $this->pointageModel->getByEmployeAndPeriod($employeId, $startDate, $endDate);
            $employe = $this->employeModel->getById($employeId);
            
            $report['summary'] = [
                'employe' => $employe,
                'total_pointages' => count($pointages),
                'total_hours' => $this->calculateTotalHours($pointages)
            ];
            $report['details'] = $pointages;
        } else {
            // Rapport global
            $employes = $this->employeModel->getAll();
            $globalPointages = [];
            
            foreach ($employes as $employe) {
                $pointages = $this->pointageModel->getByEmployeAndPeriod($employe['id'], $startDate, $endDate);
                $globalPointages = array_merge($globalPointages, $pointages);
            }
            
            $report['summary'] = [
                'total_employes' => count($employes),
                'total_pointages' => count($globalPointages),
                'total_hours' => $this->calculateTotalHours($globalPointages)
            ];
            $report['details'] = $globalPointages;
        }
        
        return $report;
    }

    /**
     * Calcule le total des heures travaillées
     */
    private function calculateTotalHours(array $pointages): array
    {
        $totalMinutes = 0;
        $openArrive = null;

        foreach ($pointages as $pointage) {
            // Use date_heure when available, fall back to created_at
            $ts = $pointage['date_heure'] ?? $pointage['created_at'] ?? null;
            if (!$ts) continue;

            try {
                $dt = new DateTime($ts);
            } catch (Exception $e) {
                continue;
            }

            if (($pointage['type'] ?? '') === POINTAGE_ARRIVEE) {
                if ($openArrive === null) {
                    $openArrive = $dt;
                } else {
                    // Consecutive arrival -> close previous at this timestamp
                    $diffSec = $dt->getTimestamp() - $openArrive->getTimestamp();
                    if ($diffSec > 0) $totalMinutes += intval(round($diffSec / 60));
                    $openArrive = $dt;
                }
            } elseif (($pointage['type'] ?? '') === POINTAGE_DEPART) {
                if ($openArrive !== null) {
                    $diffSec = $dt->getTimestamp() - $openArrive->getTimestamp();
                    if ($diffSec > 0) $totalMinutes += intval(round($diffSec / 60));
                    $openArrive = null;
                }
            }
        }

        // Close open arrival to end of day if needed
        if ($openArrive !== null) {
            // If last pointage belongs to today, use now, else close at 23:59:59 of that day
            $day = $openArrive->format('Y-m-d');
            if ($day === date('Y-m-d')) {
                $depart = new DateTime();
            } else {
                $depart = DateTime::createFromFormat('Y-m-d H:i:s', $day . ' 23:59:59');
                if (!$depart) $depart = new DateTime($day . ' 23:59:59');
            }
            $diffSec = $depart->getTimestamp() - $openArrive->getTimestamp();
            if ($diffSec > 0) $totalMinutes += intval(round($diffSec / 60));
        }
        
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        
        return [
            'total_minutes' => $totalMinutes,
            'hours' => $hours,
            'minutes' => $minutes,
            'formatted' => sprintf('%02d:%02d', $hours, $minutes)
        ];
    }

    /**
     * Supprime un pointage
     */
    public function deletePointage(int $pointageId): array
    {
        try {
            $success = $this->pointageModel->delete($pointageId);
            
            return [
                'success' => $success,
                'message' => $success ? 'Pointage supprimé avec succès' : 'Erreur lors de la suppression'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Log d'audit
     */
    private function logAudit(int $employeId, $badgeTokenId, string $type, string $result): void
    {
        $logDir = __DIR__ . '/../../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'pointage_audit.log';
        $line = sprintf(
            "[%s] employe=%d badge=%s type=%s result=%s ip=%s\n",
            date('Y-m-d H:i:s'),
            $employeId,
            $badgeTokenId ?? 'null',
            $type,
            $result,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

// Définition des constantes
if (!defined('POINTAGE_ARRIVEE')) {
    define('POINTAGE_ARRIVEE', 'arrivee');
}

if (!defined('POINTAGE_DEPART')) {
    define('POINTAGE_DEPART', 'depart');
}