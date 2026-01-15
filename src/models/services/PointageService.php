<?php
/**
 * Service de Pointage
 * Système de Pointage Professionnel v2.0
 */

namespace PointagePro\Services;

use PDO;
use Exception;
use PointagePro\Models\Pointage;
use PointagePro\Models\Employee;
use PointagePro\Core\Security\TokenManager;

class PointageService {
    private PDO $db;
    private Pointage $pointageModel;
    private Employee $employeeModel;
    private TokenManager $tokenManager;
    
    public function __construct(PDO $db, TokenManager $tokenManager) {
        $this->db = $db;
        $this->pointageModel = new Pointage($db);
        $this->employeeModel = new Employee($db);
        $this->tokenManager = $tokenManager;
    }
    
    /**
     * Traite un pointage via badge
     */
    public function processBadgeClocking(string $token, array $context = []): array {
        $this->db->beginTransaction();
        
        try {
            // 1. Valider le token
            $tokenData = $this->tokenManager->validateBadgeToken($token);
            
            // 2. Vérifier l'employé
            $employee = $this->employeeModel->findById($tokenData['employe_id']);
            if (!$employee) {
                throw new Exception("Employé non trouvé");
            }
            
            // 3. Déterminer le type de pointage
            $clockingType = $this->determineClockingType($tokenData['employe_id']);
            
            // 4. Valider les règles métier
            $this->validateClockingRules($tokenData['employe_id'], $clockingType, $context);

            
            // 5. Enregistrer le pointage
            $pointageData = [
                'employe_id' => $tokenData['employe_id'],
                'type' => $clockingType,
                'timestamp' => date('Y-m-d H:i:s'),
                'device_info' => $context['device_info'] ?? null,
                'ip_address' => $context['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
                'location_lat' => $context['latitude'] ?? null,
                'location_lng' => $context['longitude'] ?? null,
                'badge_token_id' => $this->getBadgeTokenId($token)
            ];
            
            $pointageId = $this->pointageModel->record($pointageData);
            
            // 6. Invalider le token utilisé
            $this->invalidateUsedToken($token);
            
            // 7. Générer un nouveau token si nécessaire
            $newToken = null;
            if ($clockingType === Pointage::TYPE_DEPARTURE) {
                $newTokenData = $this->tokenManager->generateBadgeToken($tokenData['employe_id']);
                $this->saveBadgeToken($tokenData['employe_id'], $newTokenData);
                $newToken = $newTokenData['token'];
            }
            
            $this->db->commit();
            
            // 8. Préparer la réponse
            return [
                'success' => true,
                'pointage_id' => $pointageId,
                'type' => $clockingType,
                'employee' => [
                    'id' => $employee['id'],
                    'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                    'department' => $employee['department']
                ],
                'timestamp' => $pointageData['timestamp'],
                'new_token' => $newToken,
                'message' => $this->getClockingMessage($clockingType, $employee)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Détermine le type de pointage à effectuer
     */
    private function determineClockingType(int $employeeId): string {
        $today = date('Y-m-d');
        
        // Récupérer le dernier pointage du jour
        $sql = "SELECT COALESCE(date_heure,date_pointage,created_at) as timestamp, type FROM pointages 
                WHERE employe_id = ? AND DATE(COALESCE(date_heure,date_pointage,created_at)) = ?
                ORDER BY COALESCE(date_heure,date_pointage,created_at) DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $today]);
        $lastClocking = $stmt->fetch();
        
        if (!$lastClocking) {
            return Pointage::TYPE_ARRIVAL;
        }
        
        // Logique de détermination du type
        return match($lastClocking['type']) {
            Pointage::TYPE_ARRIVAL => Pointage::TYPE_DEPARTURE,
            Pointage::TYPE_DEPARTURE => Pointage::TYPE_ARRIVAL,
            Pointage::TYPE_BREAK_START => Pointage::TYPE_BREAK_END,
            Pointage::TYPE_BREAK_END => Pointage::TYPE_DEPARTURE,
            default => Pointage::TYPE_ARRIVAL
        };
    }
    
    /**
     * Valide les règles métier pour le pointage
     */
    private function validateClockingRules(int $employeeId, string $type, array $context): void {
        $today = date('Y-m-d');
        
        // Règle 1: Pas plus de 2 pointages du même type par jour
        $sql = "SELECT COUNT(*) FROM pointages 
                WHERE employe_id = ? AND DATE(COALESCE(date_heure,date_pointage,created_at)) = ? AND type = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $today, $type]);
        $count = $stmt->fetchColumn();
        
        if ($count >= 2) {
            throw new Exception("Limite de pointages atteinte pour ce type aujourd'hui");
        }
        
        // Règle 2: Délai minimum entre deux pointages (5 minutes)
        $sql = "SELECT COALESCE(date_heure,date_pointage,created_at) as timestamp FROM pointages 
                WHERE employe_id = ? 
                ORDER BY COALESCE(date_heure,date_pointage,created_at) DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId]);
        $lastTimestamp = $stmt->fetchColumn();
        
        if ($lastTimestamp) {
            $timeDiff = time() - strtotime($lastTimestamp);
            if ($timeDiff < 300) { // 5 minutes
                throw new Exception("Délai minimum entre pointages non respecté");
            }
        }
        
        // Règle 3: Validation géographique si activée
        if (!empty($context['latitude']) && !empty($context['longitude'])) {
            $this->validateLocation($employeeId, $context['latitude'], $context['longitude']);
        }
    }
    
    /**
     * Valide la localisation du pointage
     */
    private function validateLocation(int $employeeId, float $lat, float $lng): void {
        // Récupérer les zones autorisées pour l'employé
        $sql = "SELECT latitude, longitude, radius FROM authorized_locations al
                JOIN employee_locations el ON al.id = el.location_id
                WHERE el.employe_id = ? AND al.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId]);
        $locations = $stmt->fetchAll();
        
        if (empty($locations)) {
            return; // Pas de restriction géographique
        }
        
        // Vérifier si l'employé est dans une zone autorisée
        foreach ($locations as $location) {
            $distance = $this->calculateDistance(
                $lat, $lng,
                $location['latitude'], $location['longitude']
            );
            
            if ($distance <= $location['radius']) {
                return; // Dans une zone autorisée
            }
        }
        
        throw new Exception("Pointage non autorisé depuis cette localisation");
    }
    
    /**
     * Calcule la distance entre deux points GPS
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371000; // Rayon de la Terre en mètres
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Récupère l'ID du token de badge
     */
    private function getBadgeTokenId(string $token): ?int {
        $tokenHash = hash('sha256', $token);
        
        $sql = "SELECT id FROM badge_tokens WHERE token_hash = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tokenHash]);
        
        return $stmt->fetchColumn() ?: null;
    }
    
    /**
     * Invalide un token utilisé
     */
    private function invalidateUsedToken(string $token): void {
        $tokenHash = hash('sha256', $token);
        
        $sql = "UPDATE badge_tokens SET 
                status = 'used', 
                used_at = NOW() 
                WHERE token_hash = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tokenHash]);
    }
    
    /**
     * Sauvegarde un nouveau token de badge
     */
    private function saveBadgeToken(int $employeeId, array $tokenData): void {
        $sql = "INSERT INTO badge_tokens (
            employe_id, token_hash, type, created_at, expires_at, status
        ) VALUES (?, ?, ?, NOW(), ?, 'active')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $employeeId,
            $tokenData['token_hash'],
            $tokenData['type'],
            $tokenData['expires_at']
        ]);
    }
    
    /**
     * Génère un message de confirmation
     */
    private function getClockingMessage(string $type, array $employee): string {
        $name = $employee['first_name'] . ' ' . $employee['last_name'];
        $time = date('H:i');
        
        return match($type) {
            Pointage::TYPE_ARRIVAL => "Bonjour {$name}, arrivée enregistrée à {$time}",
            Pointage::TYPE_DEPARTURE => "Au revoir {$name}, départ enregistré à {$time}",
            Pointage::TYPE_BREAK_START => "Pause commencée à {$time}",
            Pointage::TYPE_BREAK_END => "Reprise du travail à {$time}",
            default => "Pointage enregistré à {$time}"
        };
    }
    
    /**
     * Génère un rapport de pointage détaillé
     */
    public function generateDetailedReport(array $filters = []): array {
        return $this->pointageModel->generateReport($filters);
    }
    
    /**
     * Récupère les statistiques de pointage
     */
    public function getStatistics(int $employeeId, string $period = '30days'): array {
        return $this->employeeModel->getStatistics($employeeId, $period);
    }
}