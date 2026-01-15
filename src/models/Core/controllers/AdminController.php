<?php
/**
 * Contrôleur des administrateurs
 * Gère les opérations CRUD sur les administrateurs
 */

class AdminController {
    private $adminModel;

    public function __construct(PDO $db) {
        $this->adminModel = new Admin($db);
    }

    /**
     * Récupère tous les administrateurs (remplace getAll pour compatibilité AdminService)
     */
    public function getAll(): array {
        return $this->adminModel->getAll();
    }

    /**
     * Récupère la liste des administrateurs (alias de getAll)
     */
    public function index(): array {
        return $this->getAll();
    }

    /**
     * Récupère un admin par son ID
     */
    public function show(int $id): ?array {
        return $this->adminModel->getById($id);
    }

    /**
     * Crée un nouvel administrateur
     */
    public function create(array $data): array {
        try {
            $validation = $this->validateAdminData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            if ($this->adminModel->getByEmail($data['email'])) {
                return ['success' => false, 'message' => 'Un administrateur avec cet email existe déjà'];
            }

            $adminId = $this->adminModel->create($data);
            return ['success' => true, 'message' => 'Administrateur créé avec succès', 'data' => ['id' => $adminId]];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la création: ' . $e->getMessage()];
        }
    }

    /**
     * Met à jour un administrateur
     */
    public function update(int $id, array $data): array {
        try {
            if (!$this->adminModel->getById($id)) {
                return ['success' => false, 'message' => 'Administrateur non trouvé'];
            }

            $validation = $this->validateAdminData($data, $id);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            $success = $this->adminModel->update($id, $data);
            return $success
                ? ['success' => true, 'message' => 'Administrateur mis à jour avec succès']
                : ['success' => false, 'message' => 'Aucune modification effectuée'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
        }
    }

    /**
     * Supprime un administrateur
     */
    public function delete(int $id): array {
        try {
            $admin = $this->adminModel->getById($id);
            if (!$admin) return ['success' => false, 'message' => 'Administrateur non trouvé'];
            if ($admin['role'] === ROLE_SUPER_ADMIN) return ['success' => false, 'message' => 'Impossible de supprimer un super administrateur'];

            $success = $this->adminModel->delete($id);
            return $success
                ? ['success' => true, 'message' => 'Administrateur supprimé avec succès']
                : ['success' => false, 'message' => 'Erreur lors de la suppression'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }

    /**
     * Récupère les statistiques des administrateurs
     */
    public function getStats(): array {
        $admins = $this->getAll();

        $stats = ['total' => count($admins), 'super_admins' => 0, 'admins' => 0, 'actifs' => 0, 'inactifs' => 0];

        foreach ($admins as $admin) {
            $stats[$admin['role'] === ROLE_SUPER_ADMIN ? 'super_admins' : 'admins']++;
            $stats[$admin['statut'] === 'actif' ? 'actifs' : 'inactifs']++;
        }

        return $stats;
    }

    /**
     * Met à jour le mot de passe d'un administrateur
     */
    public function updatePassword(int $id, string $currentPassword, string $newPassword): array {
        try {
            $admin = $this->adminModel->getById($id);
            if (!$admin) return ['success' => false, 'message' => 'Administrateur non trouvé'];
            if (!password_verify($currentPassword, $admin['password'])) return ['success' => false, 'message' => 'Mot de passe actuel incorrect'];
            if (strlen($newPassword) < 6) return ['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'];

            $success = $this->adminModel->updatePassword($id, $newPassword);
            return $success
                ? ['success' => true, 'message' => 'Mot de passe mis à jour avec succès']
                : ['success' => false, 'message' => 'Erreur lors de la mise à jour du mot de passe'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
        }
    }

    /**
     * Valide les données d'un administrateur
     */
    private function validateAdminData(array $data, int $excludeId = null): array {
        $required = ['nom', 'prenom', 'email'];
        foreach ($required as $field) if (empty($data[$field])) return ['valid' => false, 'message' => "Le champ $field est obligatoire"];

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) return ['valid' => false, 'message' => 'Format d\'email invalide'];

        $existingAdmin = $this->adminModel->getByEmail($data['email']);
        if ($existingAdmin && (!$excludeId || $existingAdmin['id'] != $excludeId)) return ['valid' => false, 'message' => 'Un administrateur avec cet email existe déjà'];

        if (isset($data['password']) && strlen($data['password']) < 6) return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères'];
        if (isset($data['role']) && !in_array($data['role'], [ROLE_ADMIN, ROLE_SUPER_ADMIN])) return ['valid' => false, 'message' => 'Rôle invalide'];

        return ['valid' => true];
    }
}
