<?php
/**
 * Contrôleur des employés
 * Gère les opérations CRUD sur les employés
 */

class EmployeController {
    private $employeModel;
    private $pointageModel;
    
    public function __construct(PDO $db) {
        $this->employeModel = new Employe($db);
        $this->pointageModel = new Pointage($db);
    }
    
    /**
     * Récupère la liste des employés
     */
    public function index(): array {
        return $this->employeModel->getAll();
    }
    
    /**
     * Récupère un employé par son ID
     */
    public function show(int $id): ?array {
        return $this->employeModel->getById($id);
    }
    
    /**
     * Crée un nouvel employé
     */
    public function create(array $data): array {
        try {
            // Validation des données
            $validation = $this->validateEmployeData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Vérifier si l'email existe déjà
            if ($this->employeModel->getByEmail($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Un employé avec cet email existe déjà'
                ];
            }
            
            $employeId = $this->employeModel->create($data);
            
            return [
                'success' => true,
                'message' => 'Employé créé avec succès',
                'data' => ['id' => $employeId]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour un employé
     */
    public function update(int $id, array $data): array {
        try {
            // Vérifier que l'employé existe
            if (!$this->employeModel->getById($id)) {
                return [
                    'success' => false,
                    'message' => 'Employé non trouvé'
                ];
            }
            
            // Validation des données
            $validation = $this->validateEmployeData($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            $success = $this->employeModel->update($id, $data);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Employé mis à jour avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune modification effectuée'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un employé
     */
    public function delete(int $id): array {
        try {
            // Vérifier que l'employé existe
            if (!$this->employeModel->getById($id)) {
                return [
                    'success' => false,
                    'message' => 'Employé non trouvé'
                ];
            }
            
            $success = $this->employeModel->delete($id);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Employé supprimé avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les statistiques d'un employé
     */
    public function getStats(int $id): array {
        $employe = $this->employeModel->getById($id);
        if (!$employe) {
            return ['error' => 'Employé non trouvé'];
        }
        
        $stats = $this->employeModel->getStats($id);
        $workHours = $this->pointageModel->calculateWorkHours($id, date('Y-m-d'));
        
        return [
            'employe' => $employe,
            'pointages' => $stats,
            'work_hours_today' => $workHours
        ];
    }
    
    /**
     * Valide les données d'un employé
     */
    private function validateEmployeData(array $data, int $excludeId = null): array {
        $required = ['nom', 'prenom', 'email'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => "Le champ $field est obligatoire"
                ];
            }
        }
        
        // Validation de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Format d\'email invalide'
            ];
        }
        
        // Vérifier l'unicité de l'email
        $existingEmploye = $this->employeModel->getByEmail($data['email']);
        if ($existingEmploye && (!$excludeId || $existingEmploye['id'] != $excludeId)) {
            return [
                'valid' => false,
                'message' => 'Un employé avec cet email existe déjà'
            ];
        }
        
        // Validation du mot de passe si fourni
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                return [
                    'valid' => false,
                    'message' => 'Le mot de passe doit contenir au moins 6 caractères'
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Recherche des employés
     */
    public function search(string $query): array {
        $stmt = $this->employeModel->db->prepare("
            SELECT * FROM employes 
            WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR poste LIKE ?
            ORDER BY nom, prenom
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll();
    }
    
    /**
     * Exporte la liste des employés
     */
    public function export(string $format = 'csv'): array {
        $employes = $this->employeModel->getAll();
        
        if ($format === 'csv') {
            return $this->exportToCsv($employes);
        } elseif ($format === 'json') {
            return $this->exportToJson($employes);
        }
        
        return ['error' => 'Format non supporté'];
    }
    
    /**
     * Export CSV
     */
    private function exportToCsv(array $employes): array {
        $csv = "ID,Nom,Prénom,Email,Poste,Statut,Date d'embauche\n";
        
        foreach ($employes as $employe) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s\n",
                $employe['id'],
                $employe['nom'],
                $employe['prenom'],
                $employe['email'],
                $employe['poste'] ?? '',
                $employe['statut'] ?? '',
                $employe['date_embauche'] ?? ''
            );
        }
        
        return [
            'content' => $csv,
            'filename' => 'employes_' . date('Y-m-d') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }
    
    /**
     * Export JSON
     */
    private function exportToJson(array $employes): array {
        return [
            'content' => json_encode($employes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename' => 'employes_' . date('Y-m-d') . '.json',
            'mime_type' => 'application/json'
        ];
    }
}
