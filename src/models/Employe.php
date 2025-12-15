<?php
/**
 * Modèle Employe
 * Gestion des employés et de leurs données
 */

class Employe {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Récupère un employé par son ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM employes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère un employé par son email
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM employes WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère tous les employés
     */
    public function getAll(): array {
        $stmt = $this->db->prepare("SELECT * FROM employes ORDER BY nom, prenom");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Récupère les employés actifs
     */
    public function getActive(): array {
        $stmt = $this->db->prepare("SELECT * FROM employes WHERE statut = 'actif' ORDER BY nom, prenom");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Crée un nouvel employé
     */
    public function create(array $data): int {
        $sql = "INSERT INTO employes (nom, prenom, email, password, role, statut, date_embauche, poste, telephone, photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'] ?? ROLE_EMPLOYE,
            $data['statut'] ?? 'actif',
            $data['date_embauche'] ?? date('Y-m-d'),
            $data['poste'] ?? '',
            $data['telephone'] ?? '',
            $data['photo'] ?? ''
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Met à jour un employé
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'password' && !empty($value)) {
                $fields[] = "$key = ?";
                $values[] = password_hash($value, PASSWORD_DEFAULT);
            } elseif (in_array($key, ['nom','prenom','poste','email','email_pro','telephone','photo','infos_sup'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE employes SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Supprime un employé
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM employes WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Vérifie les identifiants de connexion
     */
    public function authenticate(string $email, string $password): ?array {
        $employe = $this->getByEmail($email);
        
        if ($employe && password_verify($password, $employe['password'])) {
            return $employe;
        }
        
        return null;
    }
    
    /**
     * Met à jour le mot de passe
     */
    public function updatePassword(int $id, string $newPassword): bool {
        $stmt = $this->db->prepare("UPDATE employes SET password = ? WHERE id = ?");
        return $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }
    
    /**
     * Récupère les statistiques d'un employé
     */
    public function getStats(int $id): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_pointages,
                COUNT(CASE WHEN type = 'arrivee' THEN 1 END) as arrivees,
                COUNT(CASE WHEN type = 'depart' THEN 1 END) as departs
            FROM pointages 
            WHERE employe_id = ? 
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }
}
