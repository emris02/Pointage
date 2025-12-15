<?php
class BadgeManager {
    const TOKEN_PREFIX = 'XPERT-';
    const TOKEN_VALIDITY = 7200; // 2 heures en secondes
    const TOKEN_HASH_ALGO = 'sha256'; // Changé pour compatibilité
    const TOKEN_FORMAT_VERSION = 3; // Version du format
    
    // Méthode unifiée de génération de token
    public static function generateToken(int $employe_id): array {
        $random = bin2hex(random_bytes(16));
        $timestamp = time();
        $version = self::TOKEN_FORMAT_VERSION;
        $data = "$employe_id|$random|$timestamp|$version";
        $signature = hash_hmac(self::TOKEN_HASH_ALGO, $data, SECRET_KEY);

        // Calcul de l'heure d'expiration dynamique
        $now = new DateTime();
        $jour = (int)$now->format('N'); // 1 = lundi, 6 = samedi
        if ($jour >= 1 && $jour <= 5) {
            // Lundi à vendredi : descente à 18h, badge valide jusqu'à 19h
            $descente = clone $now;
            $descente->setTime(18, 0, 0);
            $expiration = clone $descente;
            $expiration->modify('+1 hour');
        } elseif ($jour === 6) {
            // Samedi : descente à 14h, badge valide jusqu'à 15h
            $descente = clone $now;
            $descente->setTime(14, 0, 0);
            $expiration = clone $descente;
            $expiration->modify('+1 hour');
        } else {
            // Dimanche : badge expire dans 2h (par défaut)
            $expiration = clone $now;
            $expiration->modify('+2 hours');
        }
        // Si on est déjà après l'heure de descente, badge expire dans 1h
        if ($now > $descente) {
            $expiration = clone $now;
            $expiration->modify('+1 hour');
        }

        return [
            'token' => "$data|$signature",
            'expires_at' => $expiration->format('Y-m-d H:i:s'),
            'token_hash' => hash('sha256', "$data|$signature")
        ];
    }

    // Méthode unifiée de vérification
    public static function verifyToken(string $token, PDO $pdo): array {
        $parts = explode('|', $token);
        
        // Validation basique de la structure
        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Format de token invalide");
        }
        
        // Extraction des composants
        list($employe_id, $random, $timestamp, $version, $signature) = $parts;
        $data = "$employe_id|$random|$timestamp|$version";
        
        // Vérification de la signature
        $expectedSignature = hash_hmac(self::TOKEN_HASH_ALGO, $data, SECRET_KEY);
        if (!hash_equals($signature, $expectedSignature)) {
            throw new RuntimeException("Signature invalide");
        }
        
        // Vérification en base de données
        $token_hash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT bt.*, e.* 
                             FROM badge_tokens bt
                             JOIN employes e ON bt.employe_id = e.id
                             WHERE bt.token_hash = ?
                             AND bt.expires_at > NOW()
                             AND bt.status = 'active'");
        $stmt->execute([$token_hash]);
        
        if (!$tokenData = $stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException("Token invalide ou expiré");
        }
        
        // Ajout des métadonnées de validation
        $tokenData['validation'] = [
            'signature_valid' => true,
            'format_version' => (int)$version,
            'generated_at' => date('Y-m-d H:i:s', (int)$timestamp),
            'checked_at' => date('Y-m-d H:i:s')
        ];
        
        return $tokenData;
    }

    // Méthode complète de régénération
    public static function regenerateToken(int $employe_id, PDO $pdo): array {
        try {
            $pdo->beginTransaction();
            
            // Générer le nouveau token
            $newToken = self::generateToken($employe_id);
            
            // Invalider les anciens tokens
            $stmt = $pdo->prepare("UPDATE badge_tokens 
                                 SET status = 'revoked', 
                                     revoked_at = NOW() 
                                 WHERE employe_id = ? 
                                 AND status = 'active'");
            $stmt->execute([$employe_id]);
            
            // Insérer le nouveau token
            $stmt = $pdo->prepare("INSERT INTO badge_tokens 
                                 (employe_id, token, token_hash, created_at, expires_at, ip_address, device_info, status, created_by) 
                                 VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $employe_id,
                $newToken['token'],
                $newToken['token_hash'],
                $newToken['expires_at'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                self::TOKEN_FORMAT_VERSION
            ]);
            
            $pdo->commit();
            
            // Journalisation
            self::logAction($employe_id, 'regeneration', $newToken['token_hash']);
            
            return [
                'status' => 'success',
                'token' => $newToken['token'],
                'token_hash' => $newToken['token_hash'],
                'expires_at' => $newToken['expires_at'],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            self::logAction($employe_id, 'error', $e->getMessage());
            throw new RuntimeException("Échec de la régénération: " . $e->getMessage());
        }
    }

    // Méthode de validation pour pointage
    public static function validateForCheckin(string $token, PDO $pdo): array {
        $tokenData = self::verifyToken($token, $pdo);
        
        try {
            $pdo->beginTransaction();
            
            // Marquer le token comme utilisé
            $stmt = $pdo->prepare("UPDATE badge_tokens 
                                 SET last_used = NOW(), 
                                     use_count = use_count + 1 
                                 WHERE token_hash = ?");
            $stmt->execute([$tokenData['token_hash']]);
            
            // Enregistrer le scan
            $stmt = $pdo->prepare("INSERT INTO badge_scans 
                                 (token_hash, scan_time, scan_type, device_info)
                                 VALUES (?, NOW(), 'checkin', ?)");
            $stmt->execute([
                $tokenData['token_hash'],
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            $pdo->commit();
            
            return [
                'valid' => true,
                'employe_id' => $tokenData['employe_id'],
                'checkin_time' => date('Y-m-d H:i:s'),
                'token_info' => $tokenData
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'valid' => false,
                'error' => "Erreur d'enregistrement: " . $e->getMessage()
            ];
        }
    }

    // Gestion des logs
    private static function logAction(int $employe_id, string $action, string $details): void {
        $log = sprintf(
            "[%s] %s - Employé: %d | Détails: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($action),
            $employe_id,
            $details,
            $_SERVER['REMOTE_ADDR']
        );

        file_put_contents(__DIR__.'badge_system.log', $log, FILE_APPEND);
    }

    public static function getEmployeeData(int $employe_id, PDO $pdo): array {
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   b.token_hash AS token, 
                   b.expires_at AS badge_expiry,
                   b.created_at AS badge_created,
                   p.type AS last_check_type,
                   p.date_heure AS last_check_time
            FROM employes e
            LEFT JOIN badge_tokens b ON (
                b.employe_id = e.id AND 
                b.expires_at > NOW() AND 
                b.status = 'active'
            )
            LEFT JOIN pointages p ON (
                p.employe_id = e.id
            )
            WHERE e.id = ?
            ORDER BY p.date_heure DESC
            LIMIT 1
        ");
        $stmt->execute([$employe_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getNextCheckinType(?string $last_check_type): string {
        if ($last_check_type === 'arrivee') {
            return 'depart';
        }
        return 'arrivee';
    }

    public static function generateBadgeToken(int $employe_id, PDO $pdo): array {
        try {
            $pdo->beginTransaction();
            
            // Générer un token unique
            $token_data = [
                'employe_id' => $employe_id,
                'generation_time' => time(),
                'random' => bin2hex(random_bytes(16))
            ];
            
            $token_hash = hash('sha256', json_encode($token_data));
            $expires_at = date('Y-m-d H:i:s', time() + self::TOKEN_VALIDITY);
            
            // Insérer le nouveau token dans la base de données
            $stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token_hash, created_at, expires_at, status) VALUES (?, ?, NOW(), ?, 'active')");
            $stmt->execute([$employe_id, $token_hash, $expires_at]);
            
            $pdo->commit();
            
            return [
                'token_hash' => $token_hash,
                'expires_at' => $expires_at
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new RuntimeException("Erreur lors de la génération du token: " . $e->getMessage());
        }
    }
}