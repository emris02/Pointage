<?php
/**
 * Model Heures - fournit les requêtes liées aux heures/pointages
 */
class Heures {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /** Retourne le total de pointages entre deux dates (inclus) */
    public function getTotalPointagesBetween(string $startDate, string $endDate): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pointages WHERE DATE(date_heure) BETWEEN :start AND :end");
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        return (int)$stmt->fetchColumn();
    }

    /** Retourne le total des arrivées entre deux dates */
    public function getTotalArrivalsBetween(string $startDate, string $endDate): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pointages WHERE type = 'arrivee' AND DATE(date_heure) BETWEEN :start AND :end");
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        return (int)$stmt->fetchColumn();
    }

    /** Récupère les N derniers pointages (activités récentes) */
    public function getRecentPointages(int $limit = 10): array {
        $stmt = $this->db->prepare("SELECT p.*, e.prenom, e.nom, e.departement, TIME(p.date_heure) as heure, DATE(p.date_heure) as date FROM pointages p LEFT JOIN employes e ON p.employe_id = e.id ORDER BY p.date_heure DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Récupère les retards entre deux dates (arrivées après 09:00) */
    public function getRetardsBetween(string $startDate, string $endDate, int $limit = 100): array {
        $stmt = $this->db->prepare(
            "SELECT p.id, e.id as employe_id, e.prenom, e.nom, p.date_heure, TIME(p.date_heure) as heure_pointage, TIMESTAMPDIFF(MINUTE, CONCAT(DATE(p.date_heure), ' 09:00:00'), p.date_heure) as retard_minutes
             FROM pointages p
             JOIN employes e ON p.employe_id = e.id
             WHERE p.type = 'arrivee' AND TIME(p.date_heure) > '09:00:00' AND DATE(p.date_heure) BETWEEN :start AND :end
             ORDER BY p.date_heure DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':start', $startDate);
        $stmt->bindValue(':end', $endDate);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Somme des secondes travaillées entre deux dates (arrivée->départ appariés) */
    public function getTotalWorkedSecondsBetween(string $startDate, string $endDate): int {
        $sql = "SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND,p1.date_heure,p2.date_heure)),0) as seconds
                FROM pointages p1
                JOIN pointages p2 ON p1.employe_id = p2.employe_id
                    AND DATE(p1.date_heure) = DATE(p2.date_heure)
                    AND p1.type = 'arrivee' AND p2.type = 'depart' AND p2.date_heure > p1.date_heure
                WHERE DATE(p1.date_heure) BETWEEN :start AND :end";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        return (int)$stmt->fetchColumn();
    }

    /** Retourne la liste des employés avec leur temps total (format SQL SEC_TO_TIME) */
    public function getEmployeesHoursBetween(string $startDate, string $endDate): array {
        // Use LEFT JOIN on employes to include employees with zero time
        $sql = "SELECT e.id, e.prenom, e.nom, e.departement, e.photo,
                       COALESCE(SEC_TO_TIME(SUM(agg.seconds)), '00:00:00') as total_travail
                FROM employes e
                LEFT JOIN (
                    SELECT p1.employe_id, SUM(TIMESTAMPDIFF(SECOND, p1.date_heure, p2.date_heure)) as seconds
                    FROM pointages p1
                    JOIN pointages p2 ON p1.employe_id = p2.employe_id
                        AND DATE(p1.date_heure) = DATE(p2.date_heure)
                        AND p1.type = 'arrivee' AND p2.type = 'depart' AND p2.date_heure > p1.date_heure
                    WHERE DATE(p1.date_heure) BETWEEN :start AND :end
                    GROUP BY p1.employe_id
                ) as agg ON agg.employe_id = e.id
                GROUP BY e.id, e.prenom, e.nom, e.departement, e.photo
                ORDER BY e.nom, e.prenom";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Présences / absences pour une date */
    public function getPresenceCountsForDate(string $date): array {
        $totalStmt = $this->db->query("SELECT COUNT(*) FROM employes");
        $total = (int)$totalStmt->fetchColumn();

        $presentStmt = $this->db->prepare("SELECT COUNT(DISTINCT employe_id) FROM pointages WHERE type = 'arrivee' AND DATE(date_heure) = :date");
        $presentStmt->execute([':date' => $date]);
        $present = (int)$presentStmt->fetchColumn();

        return ['total' => $total, 'present' => $present, 'absent' => max(0, $total - $present)];
    }
}
