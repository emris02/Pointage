<?php
require_once 'db.php';
require_once 'BadgeManager.php'; // Utilisation de la classe BadgeManager améliorée

date_default_timezone_set('Europe/Paris');

class ConflictException extends Exception {
    public array $payload = [];
    public function __construct(string $message = "", array $payload = []){
        parent::__construct($message);
        $this->payload = $payload;
    }
}

class PointageSystem {
    private PDO $pdo;
    private $logger;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->logger = new PointageLogger();
    }

    public function handlePointageRequest(array $requestData): array {
        try {
            // Validation basique
            if (empty($requestData['badge_token'])) {
                throw new InvalidArgumentException("Token manquant pour le pointage");
            }

            // Vérification du token avec BadgeManager
             $tokenData = BadgeManager::verifyToken($requestData['badge_token'], $this->pdo);
            
            if (!$tokenData['valid']) {
                throw new RuntimeException($tokenData['error'] ?? "Token invalide");
            }

            return $this->traiterPointage($tokenData);
        } catch (Exception $e) {
            $this->logger->logError($e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    private function traiterPointage(array $tokenData): array {
        $employeId = (int)$tokenData['employe_id'];
        $dateCourante = date('Y-m-d');

        $this->pdo->beginTransaction();

        try {
            // Vérifier le dernier pointage
            $lastPointage = $this->getLastPointage($employeId, $dateCourante);
            
            // Déterminer le type de pointage
            $type = $this->determinerTypePointage($lastPointage);

            // Si le dernier est une arrivée et qu'on tente autre chose que 'arrivee', on exige une confirmation
            if ($lastPointage && $lastPointage['type'] === 'arrivee' && $type !== 'arrivee') {
                throw new ConflictException('Pointage en conflit : une arrivée a été enregistrée récemment. Validation requise (Pause / Départ / Annuler).', ['last' => $lastPointage]);
            }
            
            // Traiter selon le type
            if ($type === 'arrivee') {
                $response = $this->handleArrival($employeId);
            } else {
                $response = $this->handleDeparture($employeId, $lastPointage);
            }

            $this->pdo->commit();
            
            // Journalisation
            $this->logger->logPointage(
                $employeId, 
                $type, 
                $response['timestamp'], 
                $tokenData['token_hash']
            );
            
            return $response;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logError("Erreur traitement: " . $e->getMessage());
            throw $e;
        }
    }

    private function getLastPointage(int $employeId, string $date): ?array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM pointages
            WHERE employe_id = ? 
            AND DATE(date_heure) = ?
            ORDER BY date_heure DESC 
            LIMIT 1
        ");
        $stmt->execute([$employeId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function determinerTypePointage(?array $lastPointage): string {
        if (!$lastPointage) {
            return 'arrivee';
        }

        // Dernier pointage est un départ -> nouvelle arrivée
        if ($lastPointage['type'] === 'depart') {
            return 'arrivee';
        }

        // Dernier pointage est une arrivée -> départ
        return 'depart';
    }

    private function handleArrival(int $employeId): array {
        $now = date('Y-m-d H:i:s');
        $heureLimite = date('Y-m-d') . ' 09:00:00';
        $isLate = strtotime($now) > strtotime($heureLimite);

        $etat = $isLate ? 'retard' : 'normal';

        $stmt = $this->pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, retard_cause, etat) VALUES (?, ?, 'arrivee', ?, ?)");
        $stmt->execute([
            $now,
            $employeId,
            $isLate ? "Arrivée après 09h00" : null,
            $etat
        ]);

        $pointageId = (int)$this->pdo->lastInsertId();

        return [
            'status' => 'success',
            'type' => 'arrivee',
            'message' => 'Arrivée enregistrée',
            'retard' => $isLate,
            'etat' => $etat,
            'pointage_id' => $pointageId,
            'require_justification' => $isLate,
            'timestamp' => $now,
        ];
    }

    private function handleDeparture(int $employeId, array $lastPointage): array {
        $now = date('Y-m-d H:i:s');

        // Vérifier la cohérence
        if ($lastPointage['type'] !== 'arrivee') {
            throw new LogicException("Incohérence: Dernier pointage n'est pas une arrivée");
        }

        // Calcul du temps travaillé avec pause
        $workData = $this->calculerTempsTravail(
            $lastPointage['date_heure'], 
            $now
        );

        $regulationDeparture = date('Y-m-d') . ' 18:00:00';
        $isEarly = strtotime($now) < strtotime($regulationDeparture);
        $etat = $isEarly ? 'depart_anticipé' : 'normal';

        $stmt = $this->pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, temps_total, temps_pause, etat) VALUES (?, ?, 'depart', ?, ?, ?)");
        $stmt->execute([
            $now,
            $employeId,
            $workData['temps_travail'],
            $workData['temps_pause'],
            $etat
        ]);

        // Expirer le badge actif
        $expireStmt = $this->pdo->prepare("UPDATE badge_tokens SET status = 'expired', expires_at = ? WHERE employe_id = ? AND status = 'active'");
        $expireStmt->execute([$now, $employeId]);

        // Générer et insérer un nouveau badge/token
        require_once 'BadgeManager.php';
        $newTokenData = BadgeManager::generateToken($employeId);
        $insertStmt = $this->pdo->prepare("INSERT INTO badge_tokens (employe_id, token, token_hash, created_at, expires_at, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $insertStmt->execute([
            $employeId,
            $newTokenData['token'],
            $newTokenData['token_hash'],
            $now,
            $newTokenData['expires_at']
        ]);

        $pointageId = (int)$this->pdo->lastInsertId();

        return [
            'status' => 'success',
            'type' => 'depart',
            'message' => 'Départ enregistré. Nouveau badge généré.',
            'temps_travail' => $workData['temps_travail'],
            'temps_pause' => $workData['temps_pause'],
            'etat' => $etat,
            'pointage_id' => $pointageId,
            'require_justification' => $isEarly,
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
        $totalMinutes = ($interval->h * 60) + $interval->i;
        
        // Calcul de la pause (1h si >6h de travail)
        $pauseMinutes = ($totalMinutes > 360) ? 60 : 0;
        
        // Temps effectif
        $minutesTravail = max(0, $totalMinutes - $pauseMinutes);
        
        return [
            'temps_travail' => sprintf('%02d:%02d:00', floor($minutesTravail / 60), $minutesTravail % 60),
            'temps_pause' => sprintf('%02d:%02d:00', floor($pauseMinutes / 60), $pauseMinutes % 60)
        ];
    }
}

class PointageLogger {
    private $logFile;
    
    public function __construct() {
        $this->logFile = __DIR__ . 'pointage_system.log';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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

// Gestionnaire de requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $system = new PointageSystem($pdo);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $system->handlePointageRequest($data);
        echo json_encode($response);
        exit;
    } catch (ConflictException $ce) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'code' => 'NEEDS_CONFIRMATION',
            'message' => $ce->getMessage(),
            'details' => $ce->payload
        ]);
        exit;
    } catch (Throwable $e) {
        $response = [
            'status' => 'error',
            'message' => 'Erreur système: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    echo json_encode($response);
    exit;
}
?>