<?php
// Forcer la timezone ici aussi pour éviter tout décalage
if (function_exists('date_default_timezone_set')) {
}
// Assurez-vous que SECRET_KEY est défini dans votre config/db.php ou un fichier de configuration central.
// Exemple : define('SECRET_KEY', 'votre_cle_ultra_secrete_et_longue');
if (!defined('SECRET_KEY') || !is_string(SECRET_KEY) || trim(SECRET_KEY) === '') {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Clé secrète non définie dans la configuration.',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

class BadgeManager {
    const TOKEN_PREFIX = 'XPERT-';
    const TOKEN_VALIDITY = 7200; // 2 heures en secondes
    const TOKEN_HASH_ALGO = 'sha256';
    const TOKEN_FORMAT_VERSION = 3;

    /**
     * Génère un nouveau badge / QR code pour un employé
     */
    public static function generateToken(int $employe_id): array {
        $random = bin2hex(random_bytes(16));
        $timestamp = time();
        $version = self::TOKEN_FORMAT_VERSION;
        $data = "$employe_id|$random|$timestamp|$version";
        $signature = hash_hmac(self::TOKEN_HASH_ALGO, $data, SECRET_KEY);

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
        $token_hash = hash('sha256', $token);

        return [
            'token' => $token,
            'expires_at' => $expiration->format('Y-m-d H:i:s'),
            'token_hash' => $token_hash
        ];
    }

    /**
     * Régénère un badge/QR pour un employé (révoque l'ancien, insère le nouveau)
     */
    public static function regenerateToken(int $employe_id, PDO $pdo): array {
        try {
            $pdo->beginTransaction();

            // Génération du nouveau token
            $newToken = self::generateToken($employe_id);

            // SUPPRESSION DÉFINITIVE des anciens badges (avant insertion du nouveau)
            $stmt = $pdo->prepare("DELETE FROM badge_tokens WHERE employe_id = ?");
            $stmt->execute([$employe_id]);

            // Insérer le nouveau token comme actif
            $stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token, token_hash, created_at, expires_at, ip_address, device_info, status, created_by) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $employe_id,
                $newToken['token'],
                $newToken['token_hash'],
                $newToken['expires_at'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                self::TOKEN_FORMAT_VERSION
            ]);

            $pdo->commit();
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

    /**
     * Régénère un badge/QR pour un administrateur
     */
    public static function regenerateTokenForAdmin(int $admin_id, PDO $pdo): array {
        try {
            $pdo->beginTransaction();

            // Génération du nouveau token
            $newToken = self::generateToken($admin_id);

            // SUPPRESSION DÉFINITIVE des anciens badges (avant insertion du nouveau)
            $stmt = $pdo->prepare("DELETE FROM badge_tokens WHERE admin_id = ?");
            $stmt->execute([$admin_id]);

            // Insérer le nouveau token comme actif (colonne admin_id)
            $stmt = $pdo->prepare("INSERT INTO badge_tokens (admin_id, token, token_hash, created_at, expires_at, ip_address, device_info, status, created_by) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $admin_id,
                $newToken['token'],
                $newToken['token_hash'],
                $newToken['expires_at'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                self::TOKEN_FORMAT_VERSION
            ]);

            $pdo->commit();
            // Log action (using generic logger)
            self::logAction($admin_id, 'regeneration_admin', $newToken['token_hash']);

            return [
                'status' => 'success',
                'token' => $newToken['token'],
                'token_hash' => $newToken['token_hash'],
                'expires_at' => $newToken['expires_at'],
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            self::logAction($admin_id, 'error_admin_regen', $e->getMessage());
            throw new RuntimeException("Échec de la régénération admin: " . $e->getMessage());
        }
    }

    /**
     * Vérifie la validité d'un token (signature, structure, DB)
     */
    public static function verifyToken(string $token, PDO $pdo): array {
        // DEBUG LOG
        $debugFile = __DIR__ . '/logs/badge_verify_debug.log';
        file_put_contents($debugFile, "[".date('Y-m-d H:i:s')."]\nTOKEN REÇU: [$token]\n", FILE_APPEND);
        
        // Parse le token pour extraire les informations
        $parts = explode('|', $token);
        file_put_contents($debugFile, "PARTS COUNT: " . count($parts) . "\n", FILE_APPEND);
        file_put_contents($debugFile, "PARTS: " . json_encode($parts) . "\n", FILE_APPEND);
        
        if (count($parts) < 2) {
            throw new InvalidArgumentException("Format de token invalide - pas assez de parties");
        }
        
        $declaredId = (int)$parts[0];
        
        // Calculer le hash du token pour la recherche en base
        $token_hash = hash('sha256', $token);
        file_put_contents($debugFile, "TOKEN_HASH: [$token_hash]\n", FILE_APPEND);
        file_put_contents($debugFile, "DECLARED_ID: [$declaredId]\n", FILE_APPEND);

        // Recherche en base avec le hash du token complet (sans présumer du type d'utilisateur)
        $stmt = $pdo->prepare("SELECT bt.id AS badge_token_id, bt.* FROM badge_tokens bt WHERE bt.token_hash = ? AND bt.status = 'active'");
        $stmt->execute([$token_hash]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        file_put_contents($debugFile, "Résultat SQL: ".($tokenData ? 'TROUVÉ' : 'AUCUN')."\n", FILE_APPEND);
        if ($tokenData) {
            file_put_contents($debugFile, "TOKEN_DATA: " . json_encode($tokenData) . "\n", FILE_APPEND);
        }

        if (!$tokenData) {
            // Vérifier si le token existe mais est expiré
            $stmtExpired = $pdo->prepare("SELECT bt.* FROM badge_tokens bt WHERE bt.token_hash = ? AND bt.status = 'active'");
            $stmtExpired->execute([$token_hash]);
            $expiredToken = $stmtExpired->fetch(PDO::FETCH_ASSOC);
            
            if ($expiredToken) {
                file_put_contents($debugFile, "TOKEN EXPIRÉ trouvé\n", FILE_APPEND);
                throw new RuntimeException("Badge expiré - Veuillez régénérer votre badge");
            } else {
                file_put_contents($debugFile, "TOKEN INEXISTANT\n", FILE_APPEND);
                throw new RuntimeException("Badge invalide - Ce badge n'existe pas");
            }
        }

        // Déterminer si le badge appartient à un employé ou un admin
        $user = null;
        if (!empty($tokenData['employe_id'])) {
            $stmt = $pdo->prepare('SELECT id, nom, prenom, matricule, departement, adresse, "employe" AS role FROM employes WHERE id = ?');
            $stmt->execute([(int)$tokenData['employe_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $tokenData['user_type'] = 'employe';
            $tokenData['user_id'] = (int)$tokenData['employe_id'];
        } elseif (!empty($tokenData['admin_id'])) {
            // Support pour les admins
            $stmt = $pdo->prepare('SELECT id, nom, prenom, email, role FROM admins WHERE id = ?');
            $stmt->execute([(int)$tokenData['admin_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $tokenData['user_type'] = 'admin';
            $tokenData['user_id'] = (int)$tokenData['admin_id'];
        }

        if (!$user) {
            file_put_contents($debugFile, "ERREUR: Utilisateur du token introuvable\n", FILE_APPEND);
            throw new RuntimeException("Badge invalide - Utilisateur introuvable");
        }

        // Vérifier la correspondance entre l'ID déclaré dans le token et l'utilisateur trouvé
        if ($declaredId && $declaredId !== (int)$tokenData['user_id']) {
            file_put_contents($debugFile, "ERREUR: ID déclaré dans le token ne correspond pas à l'utilisateur trouvé\n", FILE_APPEND);
            throw new RuntimeException("Badge invalide - Le token ne correspond pas à l'utilisateur") ;
        }

        // Vérifications supplémentaires (expiration)
        if (!empty($tokenData['expires_at']) && strtotime($tokenData['expires_at']) <= time()) {
            file_put_contents($debugFile, "TOKEN EXPIRÉ (vérif expire_at)\n", FILE_APPEND);
            throw new RuntimeException('Badge expiré - Veuillez régénérer votre badge');
        }

        $tokenData['validation'] = [
            'signature_valid' => true,
            'format_version' => count($parts),
            'generated_at' => $tokenData['created_at'] ?? null,
            'checked_at' => date('Y-m-d H:i:s')
        ];
        
        // Joindre les informations utilisateur
        $tokenData['user'] = $user;

        // Compatibilité ascendante : exposer admin_id ou employe_id selon le cas
        if ($tokenData['user_type'] === 'admin') {
            $tokenData['admin_id'] = (int)$tokenData['user_id'];
        } else {
            $tokenData['employe_id'] = (int)$tokenData['user_id'];
        }

        file_put_contents($debugFile, "VALIDATION RÉUSSIE\n\n", FILE_APPEND);
        return $tokenData;
    }

    /**
     * Logging sécurisé dans logs/badge_system.log
     */
    private static function logAction(int $employe_id, string $action, string $details): void {
        $logDir = __DIR__ . '/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'badge_system.log';
        $log = sprintf(
            "[%s] %s - Employé: %d | Détails: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($action),
            $employe_id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}