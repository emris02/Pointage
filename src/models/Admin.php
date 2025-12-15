<?php
/**
 * Modèle Admin
 * Gestion des administrateurs
 */

class Admin {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Récupère un admin par son ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère un admin par son email
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère tous les admins
     */
    public function getAll(): array {
        $stmt = $this->db->prepare("SELECT * FROM admins ORDER BY nom, prenom");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Crée un nouvel admin
     */
    public function create(array $data): int {
        $sql = "INSERT INTO admins (nom, prenom, email, password, role, statut, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'] ?? ROLE_ADMIN,
            $data['statut'] ?? 'actif'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Met à jour un admin
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'password' && !empty($value)) {
                $fields[] = "$key = ?";
                $values[] = password_hash($value, PASSWORD_DEFAULT);
            } elseif ($key !== 'password') {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Supprime un admin
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM admins WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Vérifie les identifiants de connexion
     */
    public function authenticate(string $email, string $password): ?array {
        $admin = $this->getByEmail($email);
        
        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        
        return null;
    }
    
    /**
     * Met à jour le mot de passe
     */
    public function updatePassword(int $id, string $newPassword): bool {
        $stmt = $this->db->prepare("UPDATE admins SET password = ? WHERE id = ?");
        return $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }
    
    /**
     * Vérifie si un admin a les droits super_admin
     */
    public function isSuperAdmin(int $id): bool {
        $admin = $this->getById($id);
        return $admin && $admin['role'] === ROLE_SUPER_ADMIN;
    }
}
