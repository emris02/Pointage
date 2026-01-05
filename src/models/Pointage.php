<?php
/**
 * Modèle Pointage
 * Système de Pointage Professionnel v2.0
 */

namespace PointagePro\Models;

use PDO;
use DateTime;
use Exception;

if (!class_exists(__NAMESPACE__ . '\\Pointage', false)) {
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
                employe_id, type, date_heure, location_lat, location_lng,
                device_info, ip_address, badge_token_id, status,
                worked_hours, break_duration, overtime_hours, is_late,
                late_minutes, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['employe_id'],
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
            $this->updateEmployeeStats($data['employe_id']);
            
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
        if (empty($data['employe_id'])) {
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
        $stmt->execute([$data['employe_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Employé non trouvé ou inactif");
        }
    }
    
    /**
     * Calcule les données automatiques du pointage
     */
    private function calculatePointageData(array $data): array {
        $employeeId = $data['employe_id'];
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
                WHERE employe_id = ? AND day_of_week = ? AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $dayOfWeek]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère le dernier pointage d'un type donné
     */
    private function getLastPointage(int $employeeId, string $date, string $type): ?array {
        $sql = "SELECT *, COALESCE(date_heure, date_pointage, created_at) AS timestamp FROM pointages 
                WHERE employe_id = ? AND DATE(COALESCE(date_heure, date_pointage, created_at)) = ? AND type = ?
                ORDER BY COALESCE(date_heure, date_pointage, created_at) DESC LIMIT 1";
        
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
                WHEN p1.type IN ('break_start','pause_debut','pause') AND p2.type IN ('break_end','pause_fin','pause') 
                THEN UNIX_TIMESTAMP(COALESCE(p2.date_heure, p2.date_pointage, p2.created_at)) - UNIX_TIMESTAMP(COALESCE(p1.date_heure, p1.date_pointage, p1.created_at))
                ELSE 0 
            END) as total_break
        FROM pointages p1
        LEFT JOIN pointages p2 ON p2.employe_id = p1.employe_id 
            AND p2.type IN ('break_end','pause_fin','pause') 
            AND COALESCE(p2.date_heure, p2.date_pointage, p2.created_at) > COALESCE(p1.date_heure, p1.date_pointage, p1.created_at)
            AND DATE(COALESCE(p2.date_heure, p2.date_pointage, p2.created_at)) = DATE(COALESCE(p1.date_heure, p1.date_pointage, p1.created_at))
        WHERE p1.employe_id = ? 
        AND DATE(COALESCE(p1.date_heure, p1.date_pointage, p1.created_at)) = ? 
        AND p1.type = 'break_start'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $date]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Calcule la durée totale des pauses entre deux timestamps (inclusif)
     */
    private function calculateBreakDurationBetween(int $employeeId, string $from, string $to): int {
        $sql = "SELECT 
            SUM(CASE 
                WHEN p1.type IN ('break_start','pause_debut','pause') AND p2.type IN ('break_end','pause_fin','pause') 
                THEN UNIX_TIMESTAMP(COALESCE(p2.date_heure, p2.date_pointage, p2.created_at)) - UNIX_TIMESTAMP(COALESCE(p1.date_heure, p1.date_pointage, p1.created_at))
                ELSE 0 
            END) as total_break
        FROM pointages p1
        LEFT JOIN pointages p2 ON p2.employe_id = p1.employe_id 
            AND p2.type IN ('break_end','pause_fin','pause') 
            AND COALESCE(p2.date_heure, p2.date_pointage, p2.created_at) > COALESCE(p1.date_heure, p1.date_pointage, p1.created_at)
            AND COALESCE(p2.date_heure, p2.date_pointage, p2.created_at) <= ?
        WHERE p1.employe_id = ? 
        AND COALESCE(p1.date_heure, p1.date_pointage, p1.created_at) >= ? 
        AND COALESCE(p1.date_heure, p1.date_pointage, p1.created_at) <= ? 
        AND p1.type IN ('break_start','pause_debut','pause')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$to, $employeeId, $from, $to]);

        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Met à jour les statistiques de l'employé
     */
    private function updateEmployeeStats(int $employeeId): void {
        $sql = "UPDATE employees SET 
            last_clocking_at = NOW(),
            total_clockings = (
                SELECT COUNT(*) FROM pointages WHERE employe_id = ?
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
        $where = ["employe_id = ?"];
        $params = [$employeeId];
        
        // Filtres de date
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(COALESCE(date_heure,date_pointage,created_at)) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(COALESCE(date_heure,date_pointage,created_at)) <= ?";
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
        ORDER BY COALESCE(p.date_heure,p.date_pointage,p.created_at) DESC
        LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Récupère les pointages d'un employé pour une période donnée
     */
    public function getByEmployeAndPeriod(int $employeeId, string $startDate, string $endDate): array {
        $sql = "SELECT p.*, COALESCE(p.date_heure,p.date_pointage,p.created_at) as timestamp
                FROM pointages p
                WHERE p.employe_id = ? AND DATE(COALESCE(p.date_heure,p.date_pointage,p.created_at)) BETWEEN ? AND ?
                ORDER BY COALESCE(p.date_heure,p.date_pointage,p.created_at) ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Calcule le total (en secondes) des heures travaillées pour un employé sur une date donnée.
     */
    public function calculateWorkSecondsForDate(int $employeeId, string $date): int {
        $sql = "SELECT *, COALESCE(date_heure, date_pointage, created_at) AS timestamp FROM pointages
                WHERE employe_id = ? AND DATE(COALESCE(date_heure, date_pointage, created_at)) = ? AND type IN ('arrival','arrivee','departure','depart','break_start','break_end','pause_debut','pause_fin')
                ORDER BY COALESCE(date_heure, date_pointage, created_at) ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $date]);
        $rows = $stmt->fetchAll();

        $totalSeconds = 0;
        $lastArrival = null;

        foreach ($rows as $row) {
            $type = $row['type'];
            $ts = strtotime($row['timestamp']);

            $isArrival = in_array($type, ['arrival','arrivee']);
            $isDeparture = in_array($type, ['departure','depart']);

            if ($isArrival) {
                $lastArrival = $ts;
            } elseif ($isDeparture) {
                if ($lastArrival !== null) {
                    $duration = max(0, $ts - $lastArrival);
                    $breaks = $this->calculateBreakDurationBetween($employeeId, date('Y-m-d H:i:s', $lastArrival), date('Y-m-d H:i:s', $ts));
                    $totalSeconds += max(0, $duration - $breaks);
                    $lastArrival = null;
                }
            }
        }

        if ($lastArrival !== null) {
            $nowTs = (new DateTime())->getTimestamp();
            $ongoing = max(0, $nowTs - $lastArrival);
            $breaks = $this->calculateBreakDurationBetween($employeeId, date('Y-m-d H:i:s', $lastArrival), (new DateTime())->format('Y-m-d H:i:s'));
            $totalSeconds += max(0, $ongoing - $breaks);
        }

        return (int)$totalSeconds;
    }

    /**
     * Retourne la représentation H:i:s pour les heures travaillées d'une date
     */
    public function getBreakDurationBetween(int $employeeId, string $from, string $to): int {
        return $this->calculateBreakDurationBetween($employeeId, $from, $to);
    }
    public function calculateWorkHours(int $employeeId, string $date): string {
        $seconds = $this->calculateWorkSecondsForDate($employeeId, $date);
        return gmdate('H:i:s', $seconds);
    }

    /**
     * Total des secondes travaillées pour une période (inclusif)
     */
    public function getTotalWorkSecondsForPeriod(int $employeeId, string $startDate, string $endDate): int {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $endInclusive = clone $end;
        $total = 0;

        for ($dt = $start; $dt <= $endInclusive; $dt->modify('+1 day')) {
            $d = $dt->format('Y-m-d');
            $total += $this->calculateWorkSecondsForDate($employeeId, $d);
        }

        return (int)$total;
    }

    /**
     * Résumé mensuel pour un employé (par défaut mois courant si year/month non fournis)
     */
    public function getEmployeeMonthlySummary(int $employeeId, ?int $month = null, ?int $year = null): array {
        $month = $month ?? (int)date('m');
        $year = $year ?? (int)date('Y');

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int)date('t', strtotime($startDate));
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Récupérer tous les pointages du mois
        $sql = "SELECT *, COALESCE(date_heure,date_pointage,created_at) as ts_date FROM pointages
                WHERE employe_id = ? AND DATE(COALESCE(date_heure,date_pointage,created_at)) BETWEEN ? AND ?
                ORDER BY COALESCE(date_heure,date_pointage,created_at) ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $startDate, $endDate]);
        $rows = $stmt->fetchAll();

        // Grouper par date
        $byDate = [];
        foreach ($rows as $r) {
            $d = date('Y-m-d', strtotime($r['ts_date']));
            $byDate[$d][] = $r;
        }

        $presentDays = 0;
        $lateCount = 0;
        $totalPointages = count($rows);

        foreach ($byDate as $d => $entries) {
            $hasArrival = false;
            $hasDeparture = false;
            foreach ($entries as $e) {
                $t = $e['type'];
                if (in_array($t, ['arrivee','arrival'])) $hasArrival = true;
                if (in_array($t, ['depart','departure'])) $hasDeparture = true;
                if (!empty($e['is_late'])) {
                    // compter les retards (arrivées en retard)
                    if (in_array($t, ['arrivee','arrival'])) $lateCount++;
                }
            }
            if ($hasArrival && $hasDeparture) $presentDays++;
        }

        $totalSeconds = $this->getTotalWorkSecondsForPeriod($employeeId, $startDate, $endDate);
        $avgDailySeconds = $presentDays > 0 ? intval(round($totalSeconds / $presentDays)) : 0;

        // Calcul des jours ouvrables (lundi-vendredi)
        $workingDays = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dow = date('N', strtotime($date));
            if ($dow < 6) $workingDays++;
        }

        $presenceRate = $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 1) : 0.0;

        // Progression vs mois précédent
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $prevTotalSeconds = $this->getTotalWorkSecondsForPeriod($employeeId, sprintf('%04d-%02d-01', $prevYear, $prevMonth), sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, (int)date('t', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevMonth)))));
        $progression = $prevTotalSeconds > 0 ? round((($totalSeconds - $prevTotalSeconds) / max(1, $prevTotalSeconds)) * 100, 1) : null;

        return [
            'month' => $month,
            'year' => $year,
            'present_days' => $presentDays,
            'total_pointages' => $totalPointages,
            'late_count' => $lateCount,
            'total_seconds' => $totalSeconds,
            'avg_daily_seconds' => $avgDailySeconds,
            'presence_rate' => $presenceRate,
            'progression_percent' => $progression
        ];
    }
    
    /**
     * Génère un rapport de pointage
     */
    public function generateReport(array $filters = []): array {
        $where = ["e.status = 'active'"];
        $params = [];
        
        // Filtres
        if (!empty($filters['employe_id'])) {
            $where[] = "p.employe_id = ?";
            $params[] = $filters['employe_id'];
        }
        
        if (!empty($filters['department'])) {
            $where[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(COALESCE(p.date_heure,p.date_pointage,p.created_at)) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(COALESCE(p.date_heure,p.date_pointage,p.created_at)) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT 
            e.id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.department,
            COUNT(DISTINCT DATE(COALESCE(p.date_heure,p.date_pointage,p.created_at))) as working_days,
            COUNT(p.id) as total_clockings,
            SUM(CASE WHEN p.is_late = 1 THEN 1 ELSE 0 END) as late_count,
            0 as avg_daily_seconds,
            0 as total_seconds,
            0 as overtime_seconds
        FROM employees e
        LEFT JOIN pointages p ON e.id = p.employe_id
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
} // end guard for namespaced class

// Backwards compatibility: if other parts of the app expect a global "Pointage" class
// we provide a lightweight alias that extends the namespaced model.
if (!class_exists('Pointage', false) && class_exists('PointagePro\\Models\\Pointage', false)) {
    class_alias('PointagePro\\Models\\Pointage', 'Pointage', false);
}