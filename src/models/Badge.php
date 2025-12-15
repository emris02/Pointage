<?php
/**
 * Modèle Badge
 * Gestion des badges et tokens QR
 */

class Badge {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Génère un nouveau token pour un employé
     */
    public function generateToken(int $employeId): array {
        $random = bin2hex(random_bytes(16));
        $timestamp = time();
        $version = 3;
        $data = "$employeId|$random|$timestamp|$version";
        $signature = hash_hmac('sha256', $data, SECRET_KEY);
        
        // Calcul dynamique de l'expiration selon le jour
        $now = new DateTime();
        $jour = (int)$now->format('N'); // 1 = lundi, 7 = dimanche
        
        if ($jour >= 1 && $jour <= 5) {
            $descente = clone $now;
            $descente->setTime(18, 0, 0);
            $expiration = clone $descente;
            $expiration->modify('+1 hour');
        } elseif ($jour === 6) {
            $descente = clone $now;
            $descente->setTime(14, 0, 0);
            $expiration = clone $descente;
            $expiration->modify('+1 hour');
        } else {
            $expiration = clone $now;
            $expiration->modify('+2 hours');
        }
        
        // Si déjà après l'heure de descente, badge = 1h seulement
        if (isset($descente) && $now > $descente) {
            $expiration = clone $now;
            $expiration->modify('+1 hour');
        }
        
        $token = "$data|$signature";
        $tokenHash = hash('sha256', $token);
        
        return [
            'token' => $token,
            'expires_at' => $expiration->format('Y-m-d H:i:s'),
            'token_hash' => $tokenHash
        ];
    }
    
    /**
     * Régénère un token pour un employé
     */
    public function regenerateToken(int $employeId): array {
        try {
            $this->db->beginTransaction();
            
            // Génération du nouveau token
            $newToken = $this->generateToken($employeId);
            
            // Suppression des anciens badges
            $stmt = $this->db->prepare("DELETE FROM badge_tokens WHERE employe_id = ?");
            $stmt->execute([$employeId]);
            
            // Insertion du nouveau token
            $stmt = $this->db->prepare("
                INSERT INTO badge_tokens 
                (employe_id, token, token_hash, created_at, expires_at, ip_address, device_info, status, created_by) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                $employeId,
                $newToken['token'],
                $newToken['token_hash'],
                $newToken['expires_at'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $employeId
            ]);
            
            $this->db->commit();
            $this->logAction($employeId, 'regeneration', $newToken['token_hash']);
            
            return [
                'status' => 'success',
                'token' => $newToken['token'],
                'token_hash' => $newToken['token_hash'],
                'expires_at' => $newToken['expires_at'],
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logAction($employeId, 'error', $e->getMessage());
            throw new RuntimeException("Échec de la régénération: " . $e->getMessage());
        }
    }
    
    /**
     * Vérifie la validité d'un token
     */
    public function verifyToken(string $token): array {
        $parts = explode('|', $token);
        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Format de token invalide");
        }
        
        list($employeId, $random, $timestamp, $version, $signature) = $parts;
        $data = "$employeId|$random|$timestamp|$version";
        $expectedSignature = hash_hmac('sha256', $data, SECRET_KEY);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new RuntimeException("Signature invalide");
        }
        
        $tokenHash = hash('sha256', $token);
        
        $stmt = $this->db->prepare("
            SELECT bt.id AS badge_token_id, bt.*, e.* 
            FROM badge_tokens bt
            JOIN employes e ON bt.employe_id = e.id
            WHERE bt.token_hash = ?
            AND bt.expires_at > NOW()
            AND bt.status = 'active'
        ");
        $stmt->execute([$tokenHash]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            throw new RuntimeException("Token invalide ou expiré");
        }
        
        $tokenData['validation'] = [
            'signature_valid' => true,
            'format_version' => (int)$version,
            'generated_at' => date('Y-m-d H:i:s', (int)$timestamp),
            'checked_at' => date('Y-m-d H:i:s')
        ];
        
        return $tokenData;
    }
    
    /**
     * Récupère les tokens actifs d'un employé
     */
    public function getActiveTokens(int $employeId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM badge_tokens 
            WHERE employe_id = ? 
            AND expires_at > NOW() 
            AND status = 'active'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$employeId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Désactive un token
     */
    public function deactivateToken(string $tokenHash): bool {
        $stmt = $this->db->prepare("
            UPDATE badge_tokens 
            SET status = 'inactive' 
            WHERE token_hash = ?
        ");
        return $stmt->execute([$tokenHash]);
    }
    
    /**
     * Nettoie les tokens expirés
     */
    public function cleanExpiredTokens(): int {
        $stmt = $this->db->prepare("
            DELETE FROM badge_tokens 
            WHERE expires_at < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Logging des actions
     */
    private function logAction(int $employeId, string $action, string $details): void {
        $logDir = ROOT_PATH . '/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'badge_system.log';
        $log = sprintf(
            "[%s] %s - Employé: %d | Détails: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($action),
            $employeId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}
