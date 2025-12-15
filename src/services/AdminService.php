<?php
/**
 * Service centralisé pour la gestion des données admin
 * Centralise toutes les requêtes et logiques métier pour le dashboard admin
 */

class AdminService {
    private $pdo;
    private $adminController;
    private $employeController;
    private $pointageController;
    private $demandeController;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->adminController = new AdminController($pdo);
        $this->employeController = new EmployeController($pdo);
        $this->pointageController = new PointageController($pdo);
        $this->demandeController = new DemandeController($pdo);
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
        $totalEmployes = $this->pdo->query("SELECT COUNT(*) FROM employes")->fetchColumn();
        $presentToday = $this->pdo->query(
            "SELECT COUNT(DISTINCT employe_id) FROM pointages WHERE type='arrivee' AND DATE(date_heure)='$date'"
        )->fetchColumn();
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
        return $this->adminController->getAll();
    }

    /**
     * Liste des demandes et statistiques
     */
    public function getDemandes(): array {
        $demandes = $this->demandeController->getAll(); 

        $stats = [
            'total' => count($demandes),
            'en_attente' => 0,
            'approuve' => 0,
            'rejete' => 0
        ];

        foreach ($demandes as $demande) {
            $statut = $demande['statut'] ?? 'en_attente';
            if ($statut === 'en_attente') $stats['en_attente']++;
            if ($statut === 'approuve') $stats['approuve']++;
            if ($statut === 'rejete') $stats['rejete']++;
        }

        return [
            'demandes' => $demandes,
            'stats' => $stats
        ];
    }

    /**
     * Demandes paginées pour le dashboard
     */
    public function getDemandesPaged(int $page = 1, int $perPage = 10): array {
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

        // Compute statistics in a robust way using controller's grouped counts
        $rawCounts = $this->demandeController->getCountByStatus();

        // Normalizer helper: lowercase and remove common accents, trim
        $normalize = function($s) {
            $s = trim(mb_strtolower((string)$s));
            $trans = [
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'à' => 'a', 'â' => 'a',
                'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c'
            ];
            $s = strtr($s, $trans);
            // replace spaces and hyphens
            $s = str_replace([' ', '-'], '_', $s);
            return $s;
        };

        $stats = [
            'total' => $total,
            'en_attente' => 0,
            'approuve' => 0,
            'rejete' => 0
        ];

        foreach ($rawCounts as $row) {
            $key = $normalize($row['statut'] ?? '');
            $count = (int)($row['count'] ?? 0);
            if ($key === 'en_attente' || $key === 'en_attente') {
                $stats['en_attente'] += $count;
            } elseif ($key === 'approuve' || $key === 'approuve') {
                $stats['approuve'] += $count;
            } elseif ($key === 'rejete' || $key === 'rejete' || $key === 'rejete') {
                $stats['rejete'] += $count;
            } else {
                // unknown statuses - include in total but not mapped
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'stats' => $stats
        ];
    }

    /**
     * Pointages pour une date donnée
     */
    public function getPointages(string $date): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id as employe_id, e.prenom, e.nom, e.departement, e.photo,
                DATE(p.date_heure) as date,
                MIN(CASE WHEN p.type='arrivee' THEN TIME(p.date_heure) END) as arrivee,
                MAX(CASE WHEN p.type='depart' THEN TIME(p.date_heure) END) as depart
            FROM pointages p
            JOIN employes e ON p.employe_id = e.id
            WHERE DATE(p.date_heure)=:date
            GROUP BY e.id, DATE(p.date_heure), e.prenom, e.nom
            ORDER BY e.nom, e.prenom
        ");
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pointages paginés pour une date avec filtre département
     */
    public function getPointagesPaged(string $date, int $page = 1, int $perPage = 10, ?string $departement = null): array {
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(DISTINCT e.id) FROM pointages p JOIN employes e ON p.employe_id = e.id WHERE DATE(p.date_heure)=:date";
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
                   MAX(CASE WHEN p.type='depart' THEN TIME(p.date_heure) END) as depart
            FROM pointages p
            JOIN employes e ON p.employe_id = e.id
            WHERE DATE(p.date_heure)=:date
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
    }

    /**
     * Retards paginés
     */
    public function getRetards(string $date, int $page = 1, int $perPage = 10, ?string $departement = null): array {
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT p.id, e.id as employe_id, e.prenom, e.nom, e.departement, p.date_heure,
                   TIMEDIFF(p.date_heure, CONCAT(DATE(p.date_heure),' 09:00:00')) as retard,
                   p.retard_cause, p.retard_justifie
            FROM pointages p
            JOIN employes e ON p.employe_id = e.id
            WHERE p.type='arrivee' AND TIME(p.date_heure)>'09:00:00' AND DATE(p.date_heure)=:date
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
    }

    /**
     * Liste des employés absents pour une date donnée, paginée
     */
    public function getAbsencesPaged(string $date, int $page = 1, int $perPage = 10, ?string $departement = null): array {
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT e.id, e.nom, e.prenom, e.departement, e.poste, e.date_embauche, e.photo
            FROM employes e
            WHERE e.id NOT IN (
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
    }

    /**
     * Temps totaux travaillés
     */
    public function getTempsTotaux(): array {
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
            WHERE p1.type='arrivee'
            GROUP BY e.id, e.prenom, e.nom, e.email
            HAVING total_travail>0
            ORDER BY e.nom, e.prenom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Temps totaux paginés
     */
    public function getTempsTotauxPaged(int $page = 1, int $perPage = 10): array {
        $offset = ($page - 1) * $perPage;
        $countStmt = $this->pdo->query("SELECT COUNT(DISTINCT e.id) FROM pointages p1 JOIN employes e ON p1.employe_id=e.id WHERE p1.type='arrivee'");
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
            WHERE p1.type='arrivee'
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
    }

    /**
     * Traiter une demande (approuver ou rejeter)
     */
    public function traiterDemande(int $demandeId, string $action): array {
        try {
            return $this->demandeController->update($demandeId, ['statut'=>$action]);
        } catch (Exception $e) {
            return ['success'=>false,'message'=>'Erreur lors du traitement: '.$e->getMessage()];
        }
    }

    /**
     * Supprimer un employé
     */
    public function supprimerEmploye(int $employeId): array {
        try {
            return $this->employeController->delete($employeId);
        } catch (Exception $e) {
            return ['success'=>false,'message'=>'Erreur lors de la suppression: '.$e->getMessage()];
        }
    }

    /**
     * Supprimer un admin
     */
    public function supprimerAdmin(int $adminId): array {
        try {
            return $this->adminController->delete($adminId);
        } catch (Exception $e) {
            return ['success'=>false,'message'=>'Erreur lors de la suppression: '.$e->getMessage()];
        }
    }
}
