<?php
/**
 * Modèle Pointage
 * Système de Pointage Professionnel v2.0
 */

namespace PointagePro\Models;

use PDO;
use DateTime;
use Exception;

class Pointage {
    private PDO $db;
    
    // Types de pointage
    public const TYPE_ARRIVAL = 'arrival';
    public const TYPE_DEPARTURE = 'departure';
    public const TYPE_BREAK_START = 'break_start';
    public const TYPE_BREAK_END = 'break_end';
    
    // Statuts
    public const STATUS_VALID = 'valid';
    public const STATUS_LATE = 'late';
    public const STATUS_EARLY = 'early';
    public const STATUS_OVERTIME = 'overtime';
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Enregistre un pointage
     */
    public function record(array $data): int {
        $this->db->beginTransaction();
        
        try {
            // Validation des données
            $this->validatePointageData($data);
            
            // Calcul automatique des heures et statuts
            $calculatedData = $this->calculatePointageData($data);
            
            $sql = "INSERT INTO pointages (
                employee_id, type, timestamp, location_lat, location_lng,
                device_info, ip_address, badge_token_id, status,
                worked_hours, break_duration, overtime_hours, is_late,
                late_minutes, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['employee_id'],
                $data['type'],
                $data['timestamp'] ?? date('Y-m-d H:i:s'),
                $data['location_lat'] ?? null,
                $data['location_lng'] ?? null,
                $data['device_info'] ?? null,
                $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
                $data['badge_token_id'] ?? null,
                $calculatedData['status'],
                $calculatedData['worked_hours'],
                $calculatedData['break_duration'],
                $calculatedData['overtime_hours'],
                $calculatedData['is_late'],
                $calculatedData['late_minutes'],
                $data['notes'] ?? null
            ]);
            
            $pointageId = $this->db->lastInsertId();
            
            // Mise à jour des statistiques employé
            $this->updateEmployeeStats($data['employee_id']);
            
            $this->db->commit();
            
            return $pointageId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Valide les données de pointage
     */
    private function validatePointageData(array $data): void {
        if (empty($data['employee_id'])) {
            throw new Exception("ID employé requis");
        }
        
        if (empty($data['type']) || !in_array($data['type'], [
            self::TYPE_ARRIVAL, self::TYPE_DEPARTURE, 
            self::TYPE_BREAK_START, self::TYPE_BREAK_END
        ])) {
            throw new Exception("Type de pointage invalide");
        }
        
        // Vérifier que l'employé existe
        $stmt = $this->db->prepare("SELECT id FROM employees WHERE id = ? AND status = 'active'");
        $stmt->execute([$data['employee_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Employé non trouvé ou inactif");
        }
    }
    
    /**
     * Calcule les données automatiques du pointage
     */
    private function calculatePointageData(array $data): array {
        $employeeId = $data['employee_id'];
        $type = $data['type'];
        $timestamp = new DateTime($data['timestamp'] ?? 'now');
        $today = $timestamp->format('Y-m-d');
        
        $result = [
            'status' => self::STATUS_VALID,
            'worked_hours' => null,
            'break_duration' => null,
            'overtime_hours' => null,
            'is_late' => false,
            'late_minutes' => 0
        ];
        
        // Récupérer les horaires de travail de l'employé
        $schedule = $this->getEmployeeSchedule($employeeId, $timestamp->format('N'));
        
        if ($type === self::TYPE_ARRIVAL) {
            // Vérifier le retard
            if ($schedule && $timestamp->format('H:i:s') > $schedule['start_time']) {
                $result['is_late'] = true;
                $result['status'] = self::STATUS_LATE;
                
                $startTime = new DateTime($today . ' ' . $schedule['start_time']);
                $result['late_minutes'] = ($timestamp->getTimestamp() - $startTime->getTimestamp()) / 60;
            }
        }
        
        if ($type === self::TYPE_DEPARTURE) {
            // Calculer les heures travaillées
            $arrival = $this->getLastPointage($employeeId, $today, self::TYPE_ARRIVAL);
            
            if ($arrival) {
                $arrivalTime = new DateTime($arrival['timestamp']);
                $workDuration = $timestamp->getTimestamp() - $arrivalTime->getTimestamp();
                
                // Soustraire les pauses
                $breakDuration = $this->calculateBreakDuration($employeeId, $today);
                $workDuration -= $breakDuration;
                
                $result['worked_hours'] = gmdate('H:i:s', max(0, $workDuration));
                $result['break_duration'] = gmdate('H:i:s', $breakDuration);
                
                // Calculer les heures supplémentaires
                if ($schedule) {
                    $expectedDuration = $this->parseTimeToSeconds($schedule['duration']);
                    $overtime = max(0, $workDuration - $expectedDuration);
                    
                    if ($overtime > 0) {
                        $result['overtime_hours'] = gmdate('H:i:s', $overtime);
                        $result['status'] = self::STATUS_OVERTIME;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Récupère les horaires de travail d'un employé
     */
    private function getEmployeeSchedule(int $employeeId, int $dayOfWeek): ?array {
        $sql = "SELECT * FROM employee_schedules 
                WHERE employee_id = ? AND day_of_week = ? AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $dayOfWeek]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère le dernier pointage d'un type donné
     */
    private function getLastPointage(int $employeeId, string $date, string $type): ?array {
        $sql = "SELECT * FROM pointages 
                WHERE employee_id = ? AND DATE(timestamp) = ? AND type = ?
                ORDER BY timestamp DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $date, $type]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Calcule la durée totale des pauses
     */
    private function calculateBreakDuration(int $employeeId, string $date): int {
        $sql = "SELECT 
            SUM(CASE 
                WHEN p1.type = 'break_start' AND p2.type = 'break_end' 
                THEN UNIX_TIMESTAMP(p2.timestamp) - UNIX_TIMESTAMP(p1.timestamp)
                ELSE 0 
            END) as total_break
        FROM pointages p1
        LEFT JOIN pointages p2 ON p2.employee_id = p1.employee_id 
            AND p2.type = 'break_end' 
            AND p2.timestamp > p1.timestamp
            AND DATE(p2.timestamp) = DATE(p1.timestamp)
        WHERE p1.employee_id = ? 
        AND DATE(p1.timestamp) = ? 
        AND p1.type = 'break_start'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $date]);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Met à jour les statistiques de l'employé
     */
    private function updateEmployeeStats(int $employeeId): void {
        $sql = "UPDATE employees SET 
            last_clocking_at = NOW(),
            total_clockings = (
                SELECT COUNT(*) FROM pointages WHERE employee_id = ?
            ),
            updated_at = NOW()
        WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $employeeId]);
    }
    
    /**
     * Récupère l'historique des pointages
     */
    public function getHistory(int $employeeId, array $filters = []): array {
        $where = ["employee_id = ?"];
        $params = [$employeeId];
        
        // Filtres de date
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Filtre par type
        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT 
            p.*,
            bt.type as badge_type,
            CASE 
                WHEN p.is_late = 1 THEN 'Retard'
                WHEN p.overtime_hours IS NOT NULL THEN 'Heures sup.'
                ELSE 'Normal'
            END as status_label
        FROM pointages p
        LEFT JOIN badge_tokens bt ON p.badge_token_id = bt.id
        WHERE $whereClause
        ORDER BY p.timestamp DESC
        LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Génère un rapport de pointage
     */
    public function generateReport(array $filters = []): array {
        $where = ["e.status = 'active'"];
        $params = [];
        
        // Filtres
        if (!empty($filters['employee_id'])) {
            $where[] = "p.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        if (!empty($filters['department'])) {
            $where[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(p.timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(p.timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT 
            e.id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.department,
            COUNT(DISTINCT DATE(p.timestamp)) as working_days,
            COUNT(p.id) as total_clockings,
            SUM(CASE WHEN p.is_late = 1 THEN 1 ELSE 0 END) as late_count,
            AVG(TIME_TO_SEC(p.worked_hours)) as avg_daily_seconds,
            SUM(TIME_TO_SEC(p.worked_hours)) as total_seconds,
            SUM(TIME_TO_SEC(p.overtime_hours)) as overtime_seconds
        FROM employees e
        LEFT JOIN pointages p ON e.id = p.employee_id
        WHERE $whereClause
        GROUP BY e.id
        ORDER BY e.last_name, e.first_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll();
        
        // Formatage des heures
        foreach ($results as &$row) {
            $row['avg_daily_hours'] = gmdate('H:i:s', $row['avg_daily_seconds'] ?? 0);
            $row['total_hours'] = gmdate('H:i:s', $row['total_seconds'] ?? 0);
            $row['overtime_hours'] = gmdate('H:i:s', $row['overtime_seconds'] ?? 0);
        }
        
        return $results;
    }
    
    /**
     * Convertit un temps au format H:i:s en secondes
     */
    private function parseTimeToSeconds(string $time): int {
        $parts = explode(':', $time);
        return ($parts[0] * 3600) + ($parts[1] * 60) + ($parts[2] ?? 0);
    }
}