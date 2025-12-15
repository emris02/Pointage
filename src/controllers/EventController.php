<?php
class EventController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les événements du calendrier
     */
    public function getAllEvents($filters = []) {
        try {
            $sql = "
                SELECT ce.*, 
                       e.prenom as employe_prenom,
                       e.nom as employe_nom,
                       e.poste as employe_poste
                FROM calendrier_events ce
                LEFT JOIN employes e ON ce.employe_id = e.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Filtre par type d'événement
            if (!empty($filters['type'])) {
                $sql .= " AND ce.type = ?";
                $params[] = $filters['type'];
            }
            
            // Filtre par employé
            if (!empty($filters['employe_id'])) {
                $sql .= " AND ce.employe_id = ?";
                $params[] = $filters['employe_id'];
            }
            
            // Filtre par date de début
            if (!empty($filters['start_date'])) {
                $sql .= " AND ce.start_date >= ?";
                $params[] = $filters['start_date'];
            }
            
            // Filtre par date de fin
            if (!empty($filters['end_date'])) {
                $sql .= " AND ce.end_date <= ?";
                $params[] = $filters['end_date'];
            }
            
            // Filtre pour une période
            if (!empty($filters['date_range'])) {
                $sql .= " AND (
                    (ce.start_date BETWEEN ? AND ?) OR 
                    (ce.end_date BETWEEN ? AND ?) OR
                    (ce.start_date <= ? AND ce.end_date >= ?)
                )";
                $params[] = $filters['date_range']['start'];
                $params[] = $filters['date_range']['end'];
                $params[] = $filters['date_range']['start'];
                $params[] = $filters['date_range']['end'];
                $params[] = $filters['date_range']['start'];
                $params[] = $filters['date_range']['end'];
            }
            
            $sql .= " ORDER BY ce.start_date ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur EventController::getAllEvents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les événements pour un employé spécifique
     */
    public function getEmployeEvents($employeId, $startDate = null, $endDate = null) {
        try {
            $sql = "
                SELECT ce.* 
                FROM calendrier_events ce
                WHERE (ce.employe_id = ? OR ce.employe_id IS NULL)
            ";
            
            $params = [$employeId];
            
            if ($startDate && $endDate) {
                $sql .= " AND (
                    (ce.start_date BETWEEN ? AND ?) OR 
                    (ce.end_date BETWEEN ? AND ?) OR
                    (ce.start_date <= ? AND ce.end_date >= ?)
                )";
                array_push($params, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
            }
            
            $sql .= " ORDER BY ce.start_date ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur EventController::getEmployeEvents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crée un nouvel événement
     */
    public function createEvent($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO calendrier_events 
                (titre, type, description, start_date, end_date, employe_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['titre'],
                $data['type'],
                $data['description'] ?? '',
                $data['start_date'],
                $data['end_date'],
                !empty($data['employe_id']) ? $data['employe_id'] : null,
                $data['created_by'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("Erreur EventController::createEvent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour un événement
     */
    public function updateEvent($id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE calendrier_events 
                SET titre = ?, type = ?, description = ?, start_date = ?, end_date = ?, employe_id = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['titre'],
                $data['type'],
                $data['description'] ?? '',
                $data['start_date'],
                $data['end_date'],
                !empty($data['employe_id']) ? $data['employe_id'] : null,
                $id
            ]);
            
        } catch (Exception $e) {
            error_log("Erreur EventController::updateEvent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un événement
     */
    public function deleteEvent($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM calendrier_events WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Erreur EventController::deleteEvent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère un événement par son ID
     */
    public function getEventById($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ce.*, 
                       e.prenom as employe_prenom,
                       e.nom as employe_nom
                FROM calendrier_events ce
                LEFT JOIN employes e ON ce.employe_id = e.id
                WHERE ce.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur EventController::getEventById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les jours de pointage d'un employé pour une période
     */
    public function getPointageDays($employeId, $startDate, $endDate) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT DATE(date_heure) as pointage_date,
                       COUNT(*) as nb_pointages,
                       MIN(date_heure) as premier_pointage,
                       MAX(date_heure) as dernier_pointage
                FROM pointages 
                WHERE employe_id = ? 
                AND DATE(date_heure) BETWEEN ? AND ?
                GROUP BY DATE(date_heure)
                ORDER BY pointage_date
            ");
            
            $stmt->execute([$employeId, $startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur EventController::getPointageDays: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les statistiques de pointage mensuelles
     */
    public function getMonthlyStats($employeId, $year = null) {
        try {
            $year = $year ?: date('Y');
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    MONTH(date_heure) as mois,
                    YEAR(date_heure) as annee,
                    COUNT(DISTINCT DATE(date_heure)) as jours_pointes,
                    SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(
                        (SELECT date_heure FROM pointages p2 
                         WHERE p2.employe_id = p.employe_id 
                         AND DATE(p2.date_heure) = DATE(p.date_heure) 
                         AND p2.type = 'depart' 
                         ORDER BY date_heure DESC LIMIT 1),
                        (SELECT date_heure FROM pointages p3 
                         WHERE p3.employe_id = p.employe_id 
                         AND DATE(p3.date_heure) = DATE(p.date_heure) 
                         AND p3.type = 'arrivee' 
                         ORDER BY date_heure ASC LIMIT 1)
                    )))) as duree_moyenne
                FROM pointages p
                WHERE employe_id = ? 
                AND YEAR(date_heure) = ?
                GROUP BY YEAR(date_heure), MONTH(date_heure)
                ORDER BY annee, mois
            ");
            
            $stmt->execute([$employeId, $year]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur EventController::getMonthlyStats: " . $e->getMessage());
            return [];
        }
    }
}
?>