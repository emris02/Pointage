<?php
/**
 * Modèle Pointage
 * Système de Pointage Professionnel v2.1
 */

namespace PointagePro\Models;

use PDO;
use DateTime;
use Exception;
use PDOException;
use Throwable;

class Pointage {
    private PDO $db;
    private string $dateColumn = 'date_pointage'; // colonne principale pour date/heure

    // Types de pointage
    public const TYPE_ARRIVAL = 'arrivee';
    public const TYPE_DEPARTURE = 'depart';
    public const TYPE_BREAK_START = 'break_start';
    public const TYPE_BREAK_END = 'break_end';

    // Statuts
    public const STATUS_VALID = 'normal';
    public const STATUS_LATE = 'retard';
    public const STATUS_OVERTIME = 'heures_sup';

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Crée un pointage
     */
    public function create(array $data): int {
        $sql = "INSERT INTO pointages (
                    employe_id, type, date_pointage, ip_address, device_info, badge_token_id, statut, latitude, longitude
                ) VALUES (
                    :employe_id, :type, :date_pointage, :ip_address, :device_info, :badge_token_id, :statut, :latitude, :longitude
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employe_id' => $data['employe_id'],
            ':type' => $data['type'],
            ':date_pointage' => $data['date_pointage'] ?? date('Y-m-d H:i:s'),
            ':ip_address' => $data['ip_address'] ?? null,
            ':device_info' => $data['device_info'] ?? null,
            ':badge_token_id' => $data['badge_token_id'] ?? null,
            ':statut' => $data['statut'] ?? 'présent',
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Vérifie si un pointage peut être effectué (anti doublon)
     */
    public function canPoint(int $employeId, string $type): bool {
        $sql = "SELECT COUNT(*) FROM pointages 
                WHERE employe_id = ? 
                  AND type = ? 
                  AND DATE(date_pointage) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeId, $type]);
        return $stmt->fetchColumn() == 0;
    }

    /**
     * Récupère le dernier pointage d'un employé
     */
    public function getLastByEmploye(int $employeId): ?array {
        $sql = "SELECT * FROM pointages 
                WHERE employe_id = ? 
                ORDER BY date_pointage DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Calcule les heures travaillées pour un employé sur une date
     */
    public function calculateWorkHours(int $employeeId, string $date): ?string {
        $sqlArrivee = "SELECT MIN(date_pointage) as arrivee_time 
                       FROM pointages 
                       WHERE employe_id = ? AND DATE(date_pointage) = ? AND type = 'arrivee'";
        $stmtA = $this->db->prepare($sqlArrivee);
        $stmtA->execute([$employeeId, $date]);
        $arrivee = $stmtA->fetchColumn();

        $sqlDepart = "SELECT MAX(date_pointage) as depart_time 
                      FROM pointages 
                      WHERE employe_id = ? AND DATE(date_pointage) = ? AND type = 'depart'";
        $stmtD = $this->db->prepare($sqlDepart);
        $stmtD->execute([$employeeId, $date]);
        $depart = $stmtD->fetchColumn();

        if (!$arrivee) return null;
        $departTs = $depart ? strtotime($depart) : time();
        $workSeconds = max(0, $departTs - strtotime($arrivee));

        // Soustraire les pauses
        $breakSeconds = 0;
        try {
            $breakSeconds = $this->calculateBreakDuration($employeeId, $date);
        } catch (Throwable $e) {
            $breakSeconds = 0;
        }

        return gmdate('H:i:s', max(0, $workSeconds - (int)$breakSeconds));
    }

    /**
     * Calcule la durée totale des pauses
     */
    public function calculateBreakDuration(int $employeeId, string $date): int {
        $sql = "SELECT 
            SUM(
                CASE WHEN type = 'break_start' THEN
                    (SELECT TIMESTAMPDIFF(SECOND, date_pointage, 
                        (SELECT MIN(date_pointage) FROM pointages 
                         WHERE employe_id = p.employe_id 
                           AND type = 'break_end' 
                           AND date_pointage > p.date_pointage
                           AND DATE(date_pointage) = DATE(p.date_pointage)
                        )
                    )
                ELSE 0 END
            ) as total_break
            FROM pointages p
            WHERE employe_id = ? AND DATE(date_pointage) = ? AND type = 'break_start'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $date]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère les pointages d’un employé sur une période
     */
    public function getByEmployeAndPeriod(int $employeId, string $startDate, string $endDate, int $limit = 100): array {
        $sql = "SELECT * FROM pointages
                WHERE employe_id = :employe_id
                  AND DATE(date_pointage) BETWEEN :start_date AND :end_date
                ORDER BY date_pointage DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':employe_id', $employeId, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime un pointage
     */
    public function delete(int $pointageId): bool {
        $stmt = $this->db->prepare("DELETE FROM pointages WHERE id = ?");
        return $stmt->execute([$pointageId]);
    }

    /**
     * Récupère l'historique des pointages avec filtres
     */
    public function getHistory(int $employeeId, array $filters = []): array {
        $where = ["employe_id = ?"];
        $params = [$employeeId];

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(date_pointage) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(date_pointage) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM pointages WHERE $whereClause ORDER BY date_pointage DESC LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère un rapport de pointage
     */
    public function generateReport(array $filters = []): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['employe_id'])) {
            $where[] = "employe_id = ?";
            $params[] = $filters['employe_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(date_pointage) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(date_pointage) <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM pointages WHERE $whereClause ORDER BY date_pointage DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
