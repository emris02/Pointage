<?php
/**
 * Modèle Pointage
 * Gestion des pointages (arrivée/départ)
 */

class Pointage {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Enregistre un pointage (arrivée ou départ)
     */
    public function create(array $data): int {
        // Structure conforme à la table pointages - toutes les colonnes existantes uniquement
        // Ordre des colonnes conforme à la structure réelle
        // Use the `date_heure` column (consistent with the rest of the codebase)
        $sql = "INSERT INTO pointages (
                    date_heure, employe_id, type, statut, etat, badge_token_id, ip_address, device_info, 
                    latitude, longitude, qr_code_id
                ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['employe_id'],
            $data['type'],
            $data['statut'] ?? 'présent',
            $data['etat'] ?? 'normal',
            $data['badge_token_id'] ?? null,
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            $data['device_info'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['qr_code_id'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Récupère les pointages d'un employé pour une date donnée
     */
    public function getByEmployeAndDate(int $employeId, string $date): array {
        $stmt = $this->db->prepare("
            SELECT * FROM pointages 
            WHERE employe_id = ? AND DATE(date_heure) = ? 
            ORDER BY date_heure ASC
        ");
        $stmt->execute([$employeId, $date]);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupère les pointages d'un employé pour une période donnée
     */
    public function getByEmployeAndPeriod(int $employeId, string $startDate, string $endDate): array {
        $stmt = $this->db->prepare("
            SELECT * FROM pointages 
            WHERE employe_id = ? 
            AND DATE(date_heure) BETWEEN ? AND ? 
            ORDER BY date_heure ASC
        ");
        $stmt->execute([$employeId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupère tous les pointages pour une date donnée
     */
    public function getByDate(string $date): array {
        $stmt = $this->db->prepare("
            SELECT p.*, e.nom, e.prenom, e.poste 
            FROM pointages p 
            JOIN employes e ON p.employe_id = e.id 
            WHERE DATE(p.date_heure) = ? 
            ORDER BY p.date_heure ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupère le dernier pointage d'un employé
     */
    public function getLastByEmploye(int $employeId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM pointages 
            WHERE employe_id = ? 
            ORDER BY date_heure DESC 
            LIMIT 1
        ");
        $stmt->execute([$employeId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Vérifie si un employé peut pointer (évite les doublons)
     */
    public function canPoint(int $employeId, string $type): bool {
        // Vérifier s'il existe déjà un pointage du même type pour cet employé aujourd'hui
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pointages WHERE employe_id = ? AND type = ? AND DATE(date_heure) = ?");
        $stmt->execute([$employeId, $type, $today]);
        $countSameTypeToday = (int)$stmt->fetchColumn();

        if ($countSameTypeToday > 0) {
            // Déjà pointé aujourd'hui pour ce type
            return false;
        }

        // Si aucun pointage précédent, le premier doit être une arrivée
        $lastPointage = $this->getLastByEmploye($employeId);
        if (!$lastPointage) {
            return $type === POINTAGE_ARRIVEE;
        }

        // Interdire le même type de pointage consécutif (historique global)
        return $lastPointage['type'] !== $type;
    }
    
    /**
     * Calcule le temps de travail journalier
     */
    public function calculateWorkHours(int $employeId, string $date): array {
        $pointages = $this->getByEmployeAndDate($employeId, $date);
        
        $totalMinutes = 0;
        $openArrive = null;

        foreach ($pointages as $pointage) {
            $ts = $pointage['date_heure'] ?? null;
            if (!$ts) continue;

            try {
                $dt = new DateTime($ts);
            } catch (Exception $e) {
                continue;
            }

            if ($pointage['type'] === POINTAGE_ARRIVEE) {
                if ($openArrive === null) {
                    $openArrive = $dt;
                } else {
                    // Arrivée consécutive sans départ -> considérer la nouvelle arrivée comme fin
                    $diffSec = $dt->getTimestamp() - $openArrive->getTimestamp();
                    if ($diffSec > 0) {
                        $totalMinutes += intval(round($diffSec / 60));
                    }
                    // Démarrer une nouvelle session à cette arrivée
                    $openArrive = $dt;
                }
            } elseif ($pointage['type'] === POINTAGE_DEPART) {
                if ($openArrive !== null) {
                    $depart = $dt;
                    $diffSec = $depart->getTimestamp() - $openArrive->getTimestamp();
                    if ($diffSec > 0) {
                        $totalMinutes += intval(round($diffSec / 60));
                    }
                    $openArrive = null;
                } else {
                    // Départ sans arrivée connue : on ignore (données incomplètes)
                }
            }
        }

        // Si une arrivée reste ouverte, compter jusqu'à la fin de la journée (ou maintenant si c'est aujourd'hui)
        if ($openArrive !== null) {
            if ($date === date('Y-m-d')) {
                $depart = new DateTime();
            } else {
                $depart = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59');
                if (!$depart) {
                    $depart = new DateTime($date . ' 23:59:59');
                }
            }
            $diffSec = $depart->getTimestamp() - $openArrive->getTimestamp();
            if ($diffSec > 0) {
                $totalMinutes += intval(round($diffSec / 60));
            }
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
     * Récupère les statistiques globales d’une journée
     */
    public function getStats(string $date = null): array {
        $date = $date ?: date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_pointages,
                COUNT(CASE WHEN type = 'arrivee' THEN 1 END) as arrivees,
                COUNT(CASE WHEN type = 'depart' THEN 1 END) as departs,
                COUNT(DISTINCT employe_id) as employes_presents
            FROM pointages 
            WHERE DATE(date_heure) = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetch() ?: [];
    }
    
    /**
     * Supprime un pointage
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM pointages WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Récupère les arrivées en retard
     */
    public function getLateArrivals(string $date = null): array {
        $date = $date ?: date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT p.*, e.nom, e.prenom, e.poste,
                   TIME(p.date_heure) as heure_pointage,
                   TIMESTAMPDIFF(MINUTE, CONCAT(?, ' 08:00:00'), p.date_heure) as retard_minutes
            FROM pointages p 
            JOIN employes e ON p.employe_id = e.id 
            WHERE DATE(p.date_heure) = ? 
            AND p.type = 'arrivee'
            AND TIME(p.date_heure) > '08:00:00'
            ORDER BY p.date_heure ASC
        ");
        $stmt->execute([$date, $date]);
        return $stmt->fetchAll();
    }
}
