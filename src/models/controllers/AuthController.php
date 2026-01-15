<?php
/**
 * Contrôleur d'authentification
 * Gère la connexion et déconnexion des utilisateurs
 */

class AuthController {
    private $employeModel;
    private $adminModel;
    private $paramService;
    
    public function __construct(PDO $db) {
        $this->employeModel = new Employe($db);
        $this->adminModel = new Admin($db);
        // ParametreService optionnel (table peut ne pas exister)
        if (class_exists('ParametreService')) {
            try {
                $this->paramService = new ParametreService($db);
            } catch (Exception $e) {
                $this->paramService = null;
            }
        } else {
            $this->paramService = null;
        }
    }
    
    /**
     * Traite la connexion d'un utilisateur
     */
    public function login(): array {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Méthode non autorisée'];
        }
        
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        
        // Validation
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Veuillez remplir tous les champs.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format d\'email invalide.'];
        }
        
        try {
            // Tentative de connexion ADMIN
            $admin = $this->adminModel->authenticate($email, $password);
            if ($admin) {
                $this->setAdminSession($admin);
                return [
                    'success' => true, 
                    'message' => 'Connexion admin réussie',
                    'redirect' => 'admin_dashboard_unifie'
                ];
            }
            
            // Tentative de connexion EMPLOYÉ
            $employe = $this->employeModel->authenticate($email, $password);
            if ($employe) {
                $this->setEmployeSession($employe);
                return [
                    'success' => true, 
                    'message' => 'Connexion employé réussie',
                    'redirect' => 'employe_dashboard.php'
                ];
            }
            
            return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
            
        } catch (Exception $e) {
            error_log("Erreur de connexion: " . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur technique est survenue.'];
        }
    }
    
    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) || isset($_SESSION['employe_id']);
    }
    
    /**
     * Vérifie le rôle de l'utilisateur
     */
    public function hasRole(string $role): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool {
        return $this->hasRole(ROLE_ADMIN) || $this->hasRole(ROLE_SUPER_ADMIN);
    }
    
    /**
     * Vérifie si l'utilisateur est super admin
     */
    public function isSuperAdmin(): bool {
        return $this->hasRole(ROLE_SUPER_ADMIN);
    }
    
    /**
     * Redirige selon le rôle de l'utilisateur
     */
    public function redirectByRole(): void {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
        
        $role = $_SESSION['role'] ?? '';
        
        if ($role === ROLE_EMPLOYE) {
            header("Location: employe_dashboard.php");
        } elseif (in_array($role, [ROLE_ADMIN, ROLE_SUPER_ADMIN])) {
            header("Location: admin_dashboard_unifie.php");
        } else {
            header("Location: login.php");
        }
        exit();
    }
    
    /**
     * Définit la session pour un admin
     */
    private function setAdminSession(array $admin): void {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['role'] = $admin['role'];
        $_SESSION['nom'] = $admin['nom'];
        $_SESSION['prenom'] = $admin['prenom'];
        $_SESSION['admin_id'] = $admin['id'];
        // Charger paramètres utilisateur en session (ex: theme)
        if ($this->paramService) {
            $theme = $this->paramService->getUserParam($admin['id'], 'theme', null);
            if ($theme !== null) {
                $_SESSION['theme'] = $theme;
            }
        }
    }
    
    /**
     * Définit la session pour un employé
     */
    private function setEmployeSession(array $employe): void {
        $_SESSION['employe_id'] = $employe['id'];
        $_SESSION['role'] = $employe['role'];
        $_SESSION['nom'] = $employe['nom'];
        $_SESSION['prenom'] = $employe['prenom'];
        // Charger paramètres utilisateur en session (ex: theme)
        if ($this->paramService) {
            $theme = $this->paramService->getUserParam($employe['id'], 'theme', null);
            if ($theme !== null) {
                $_SESSION['theme'] = $theme;
            }
        }
    }
    
    /**
     * Génère un token de réinitialisation de mot de passe
     */
    public function generatePasswordResetToken(string $email): array {
        // Vérifier si c'est un admin ou un employé
        $admin = $this->adminModel->getByEmail($email);
        $employe = $this->employeModel->getByEmail($email);
        
        if (!$admin && !$employe) {
            return ['success' => false, 'message' => 'Email non trouvé'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 heure
        
        // Stocker le token en base (vous devrez créer une table password_reset_tokens)
        // Pour l'instant, on simule
        return [
            'success' => true, 
            'message' => 'Token généré',
            'token' => $token,
            'expires_at' => $expires
        ];
    }
}
