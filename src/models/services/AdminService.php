<?php
/**
 * Service centralisé pour la gestion des données admin
 * Centralise toutes les requêtes et logiques métier pour le dashboard admin
 */

class AdminService {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // Initialiser les contrôleurs si nécessaire, sinon utiliser directement PDO
    }

    /**
     * Récupère toutes les données nécessaires pour le dashboard admin
     */
    public function getDashboardData(): array {
        $today = date('Y-m-d');

        return [
            'stats' => $this->getStats($today),
            'employes' => $this->getEmployes(),
            'admins' => $this->getAdmins(),
            'demandes' => $this->getDemandes(),
            'pointages' => $this->getPointages($today),
            'retards' => $this->getRetards($today),
            'absents' => $this->getAbsencesPaged($today, 1, 100), // absences du jour
            'temps_totaux' => $this->getTempsTotaux()
        ];
    }

    /**
     * Statistiques globales pour le dashboard
     */
    public function getStats(string $date): array {
        // Compter les employés actifs uniquement
        $totalEmployes = $this->pdo->query("SELECT COUNT(*) FROM employes WHERE statut='actif'")->fetchColumn();
        
        // Compter les présents du jour (arrivées)
        $presentToday = $this->pdo->query(
            "SELECT COUNT(DISTINCT employe_id) FROM pointages WHERE type='arrivee' AND DATE(date_heure)='$date'"
        )->fetchColumn();
        
        // Compter les retards (arrivées après 9h)
        $retardsToday = $this->pdo->query(
            "SELECT COUNT(*) FROM pointages WHERE type='arrivee' AND TIME(date_heure) > '09:00:00' AND DATE(date_heure)='$date'"
        )->fetchColumn();
        
        $absentsToday = $totalEmployes - $presentToday;

        return [
            'total_employes' => (int)$totalEmployes,
            'present_today' => (int)$presentToday,
            'absents_today' => (int)$absentsToday,
            'retards_today' => (int)$retardsToday
        ];
    }

    /**
     * Liste des employés paginée
     */
    public function getEmployes(int $page = 1, int $perPage = 10): array {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, email, telephone, poste, departement, photo, date_embauche
            FROM employes 
            WHERE statut = 'actif'
            ORDER BY nom, prenom
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste des administrateurs
     */
    public function getAdmins(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT id, nom, prenom, email, role, statut, created_at
                FROM admins 
                WHERE statut = 'actif'
                ORDER BY nom, prenom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getAdmins: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Liste des demandes et statistiques
     */
    public function getDemandes(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT d.*, e.prenom, e.nom, e.poste, e.departement 
                FROM demandes d 
                LEFT JOIN employes e ON d.employe_id = e.id 
                ORDER BY d.date_demande DESC
                LIMIT 50
            ");
            $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer les statistiques
            $statsStmt = $this->pdo->query("
                SELECT statut, COUNT(*) as count 
                FROM demandes 
                GROUP BY statut
            ");
            $statsData = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

            $stats = [
                'total' => count($demandes),
                'en_attente' => 0,
                'approuve' => 0,
                'rejete' => 0
            ];

            foreach ($statsData as $row) {
                $statut = strtolower($row['statut'] ?? '');
                if ($statut === 'en_attente') {
                    $stats['en_attente'] = (int)$row['count'];
                } elseif ($statut === 'approuve') {
                    $stats['approuve'] = (int)$row['count'];
                } elseif ($statut === 'rejete') {
                    $stats['rejete'] = (int)$row['count'];
                }
            }

            return [
                'demandes' => $demandes,
                'stats' => $stats
            ];
        } catch (Exception $e) {
            error_log("Erreur getDemandes: " . $e->getMessage());
            return [
                'demandes' => [],
                'stats' => ['total' => 0, 'en_attente' => 0, 'approuve' => 0, 'rejete' => 0]
            ];
        }
    }

    /**
     * Demandes paginées pour le dashboard
     */
    public function getDemandesPaged(int $page = 1, int $perPage = 10): array {
        try {
            $offset = ($page - 1) * $perPage;
            $total = (int)$this->pdo->query('SELECT COUNT(*) FROM demandes')->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT d.*, e.prenom, e.nom, e.poste, e.departement, e.photo 
                FROM demandes d 
                LEFT JOIN employes e ON d.employe_id = e.id 
                ORDER BY d.date_demande DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer les statistiques directement
            $statsStmt = $this->pdo->query("
                SELECT statut, COUNT(*) as count 
                FROM demandes 
                GROUP BY statut
            ");
            $statsData = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

            $stats = [
                'total' => $total,
                'en_attente' => 0,
                'approuve' => 0,
                'rejete' => 0
            ];

            foreach ($statsData as $row) {
                $statut = strtolower($row['statut'] ?? '');
                if ($statut === 'en_attente') {
                    $stats['en_attente'] = (int)$row['count'];
                } elseif ($statut === 'approuve') {
                    $stats['approuve'] = (int)$row['count'];
                } elseif ($statut === 'rejete') {
                    $stats['rejete'] = (int)$row['count'];
                }
            }

            return [
                'items' => $items,
                'total' => $total,
                'stats' => $stats
            ];
        } catch (Exception $e) {
            error_log("Erreur getDemandesPaged: " . $e->getMessage());
            return [
                'items' => [],
                'total' => 0,
                'stats' => ['total' => 0, 'en_attente' => 0, 'approuve' => 0, 'rejete' => 0]
            ];
        }
    }

    /**
     * Pointages pour une date donnée
     */
    public function getPointages(string $date): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id as employe_id, e.prenom, e.nom, e.departement, e.photo,
                    DATE(p.date_heure) as date,
                    MIN(CASE WHEN p.type='arrivee' THEN TIME(p.date_heure) END) as arrivee,
                    MAX(CASE WHEN p.type='depart' THEN TIME(p.date_heure) END) as depart,
                    p.retard_minutes
                FROM pointages p
                JOIN employes e ON p.employe_id = e.id
                WHERE DATE(p.date_heure)=:date AND e.statut = 'actif'
                GROUP BY e.id, DATE(p.date_heure), e.prenom, e.nom
                ORDER BY e.nom, e.prenom
            ");
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getPointages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Pointages paginés pour une date avec filtre département
     */
    public function getPointagesPaged(string $date, int $page = 1, int $perPage = 10, ?string $departement = null): array {
        try {
            $offset = ($page - 1) * $perPage;

            $countSql = "SELECT COUNT(DISTINCT e.id) FROM pointages p JOIN employes e ON p.employe_id = e.id WHERE DATE(p.date_heure)=:date AND e.statut='actif'";
            if ($departement) $countSql .= " AND e.departement=:dep";

            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->bindParam(':date', $date);
            if ($departement) $countStmt->bindValue(':dep', $departement);
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();

            $sql = "
                SELECT e.id as employe_id, e.prenom, e.nom, e.departement, e.photo,
                       DATE(p.date_heure) as date,
                       MIN(CASE WHEN p.type='arrivee' THEN TIME(p.date_heure) END) as arrivee,
                       MAX(CASE WHEN p.type='depart' THEN TIME(p.date_heure) END) as depart,
                       p.retard_minutes
                FROM pointages p
                JOIN employes e ON p.employe_id = e.id
                WHERE DATE(p.date_heure)=:date AND e.statut='actif'
            ";
            if ($departement) $sql .= " AND e.departement=:dep";
            $sql .= " GROUP BY e.id, DATE(p.date_heure), e.prenom, e.nom
                      ORDER BY e.nom, e.prenom
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':date', $date);
            if ($departement) $stmt->bindValue(':dep', $departement);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log("Erreur getPointagesPaged: " . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Retards paginés
     */
    public function getRetards(string $date, int $page = 1, int $perPage = 10, ?string $departement = null): array {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "
                SELECT p.id, e.id as employe_id, e.prenom, e.nom, e.departement, p.date_heure,
                       p.retard_minutes, p.est_justifie,
                       -- latest retard info (if any)
                       (SELECT r.statut FROM retards r WHERE r.pointage_id = p.id ORDER BY r.date_soumission DESC LIMIT 1) as statut,
                       (SELECT r.details FROM retards r WHERE r.pointage_id = p.id ORDER BY r.date_soumission DESC LIMIT 1) as details,
                       (SELECT r.raison FROM retards r WHERE r.pointage_id = p.id ORDER BY r.date_soumission DESC LIMIT 1) as retard_raison
                FROM pointages p
                JOIN employes e ON p.employe_id = e.id
                WHERE p.type='arrivee' AND p.retard_minutes > 0 AND DATE(p.date_heure)=:date
            ";
            if ($departement) $sql .= " AND e.departement=:dep";
            $sql .= " ORDER BY p.date_heure DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':date', $date);
            if ($departement) $stmt->bindValue(':dep', $departement);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getRetards: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Liste des employés absents pour une date donnée, paginée
     */
    public function getAbsencesPaged(string $date, int $page = 1, int $perPage = 10, ?string $departement = null): array {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "
                SELECT e.id, e.nom, e.prenom, e.departement, e.poste, e.date_embauche, e.photo
                FROM employes e
                WHERE e.statut='actif' AND e.id NOT IN (
                    SELECT DISTINCT p.employe_id
                    FROM pointages p
                    WHERE DATE(p.date_heure)=:date AND p.type='arrivee'
                )
            ";
            if ($departement) $sql .= " AND e.departement=:dep";

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM ($sql) AS subquery");
            $countStmt->bindParam(':date', $date);
            if ($departement) $countStmt->bindValue(':dep', $departement);
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();

            $sql .= " ORDER BY e.nom, e.prenom LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':date', $date);
            if ($departement) $stmt->bindValue(':dep', $departement);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log("Erreur getAbsencesPaged: " . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Temps totaux travaillés
     */
    public function getTempsTotaux(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT e.id as employe_id, e.prenom, e.nom, e.email,
                       SEC_TO_TIME(SUM(
                           CASE WHEN p2.date_heure IS NOT NULL THEN TIMESTAMPDIFF(SECOND, p1.date_heure, p2.date_heure) ELSE 0 END
                       )) as total_travail
                FROM pointages p1
                JOIN employes e ON p1.employe_id = e.id
                LEFT JOIN pointages p2 ON p1.employe_id = p2.employe_id
                    AND DATE(p1.date_heure) = DATE(p2.date_heure)
                    AND p1.type='arrivee'
                    AND p2.type='depart'
                    AND p2.date_heure > p1.date_heure
                WHERE p1.type='arrivee' AND e.statut='actif'
                GROUP BY e.id, e.prenom, e.nom, e.email
                HAVING total_travail>0
                ORDER BY e.nom, e.prenom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getTempsTotaux: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Temps totaux paginés
     */
    public function getTempsTotauxPaged(int $page = 1, int $perPage = 10): array {
        try {
            $offset = ($page - 1) * $perPage;
            $countStmt = $this->pdo->query("SELECT COUNT(DISTINCT e.id) FROM pointages p1 JOIN employes e ON p1.employe_id=e.id WHERE p1.type='arrivee' AND e.statut='actif'");
            $total = (int)$countStmt->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT e.id as employe_id, e.prenom, e.nom, e.email,
                       SEC_TO_TIME(SUM(
                           CASE WHEN p2.date_heure IS NOT NULL THEN TIMESTAMPDIFF(SECOND, p1.date_heure, p2.date_heure) ELSE 0 END
                   )) as total_travail
                FROM pointages p1
                JOIN employes e ON p1.employe_id=e.id
                LEFT JOIN pointages p2 ON p1.employe_id=p2.employe_id
                    AND DATE(p1.date_heure)=DATE(p2.date_heure)
                    AND p1.type='arrivee'
                    AND p2.type='depart'
                    AND p2.date_heure>p1.date_heure
                WHERE p1.type='arrivee' AND e.statut='actif'
                GROUP BY e.id, e.prenom, e.nom, e.email
                HAVING total_travail>0
                ORDER BY e.nom, e.prenom
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log("Erreur getTempsTotauxPaged: " . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Traiter une demande (approuver ou rejeter)
     */
    public function traiterDemande(int $demandeId, string $action): array {
        try {
            // Vérifier que l'action est valide
            $validActions = ['approuve', 'rejete'];
            if (!in_array($action, $validActions)) {
                return ['success' => false, 'message' => 'Action invalide'];
            }

            // Mettre à jour la demande
            $stmt = $this->pdo->prepare("
                UPDATE demandes 
                SET statut = :statut, date_traitement = NOW() 
                WHERE id = :id
            ");
            $stmt->bindParam(':statut', $action);
            $stmt->bindParam(':id', $demandeId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Demande traitée avec succès'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors du traitement: ' . $e->getMessage()];
        }
    }

    /**
     * Supprimer un employé (désactiver)
     */
    public function supprimerEmploye(int $employeId): array {
        try {
            $stmt = $this->pdo->prepare("UPDATE employes SET statut='inactif' WHERE id = :id");
            $stmt->bindParam(':id', $employeId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Employé désactivé avec succès'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de la suppression'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }

    /**
     * Supprimer un admin (désactiver)
     */
    public function supprimerAdmin(int $adminId): array {
        try {
            $stmt = $this->pdo->prepare("UPDATE admins SET statut='inactif' WHERE id = :id");
            $stmt->bindParam(':id', $adminId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Administrateur désactivé avec succès'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de la suppression'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }
}