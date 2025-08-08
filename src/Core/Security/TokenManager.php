<?php
/**
 * Gestionnaire de tokens sécurisé
 * Système de Pointage Professionnel v2.0
 */

namespace PointagePro\Core\Security;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenManager {
    private const ALGORITHM = 'HS256';
    private const TOKEN_PREFIX = 'XPERT_';
    private const TOKEN_VERSION = '2.0';
    
    private string $secretKey;
    private string $jwtSecret;
    
    public function __construct(string $secretKey, string $jwtSecret) {
        $this->secretKey = $secretKey;
        $this->jwtSecret = $jwtSecret;
    }
    
    /**
     * Génère un token de badge sécurisé
     */
    public function generateBadgeToken(int $employeId, string $type = 'standard'): array {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $version = self::TOKEN_VERSION;
        
        // Données du token
        $tokenData = [
            'employe_id' => $employeId,
            'type' => $type,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'version' => $version,
            'expires_at' => $timestamp + DatabaseConfig::TOKEN_EXPIRATION
        ];
        
        // Signature HMAC
        $dataString = implode('|', $tokenData);
        $signature = hash_hmac('sha256', $dataString, $this->secretKey);
        
        // Token final
        $token = self::TOKEN_PREFIX . base64_encode($dataString . '|' . $signature);
        $tokenHash = hash('sha256', $token);
        
        return [
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => date('Y-m-d H:i:s', $tokenData['expires_at']),
            'type' => $type,
            'metadata' => [
                'generated_at' => date('Y-m-d H:i:s', $timestamp),
                'version' => $version,
                'algorithm' => 'HMAC-SHA256'
            ]
        ];
    }
    
    /**
     * Valide un token de badge
     */
    public function validateBadgeToken(string $token): array {
        if (!str_starts_with($token, self::TOKEN_PREFIX)) {
            throw new Exception("Format de token invalide");
        }
        
        // Décoder le token
        $encodedData = substr($token, strlen(self::TOKEN_PREFIX));
        $decodedData = base64_decode($encodedData);
        
        if ($decodedData === false) {
            throw new Exception("Token corrompu");
        }
        
        $parts = explode('|', $decodedData);
        if (count($parts) !== 6) {
            throw new Exception("Structure de token invalide");
        }
        
        [$employeId, $type, $timestamp, $nonce, $version, $signature] = $parts;
        
        // Vérifier la signature
        $dataString = implode('|', array_slice($parts, 0, 5));
        $expectedSignature = hash_hmac('sha256', $dataString, $this->secretKey);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception("Signature invalide");
        }
        
        // Vérifier l'expiration
        $expiresAt = (int)$timestamp + DatabaseConfig::TOKEN_EXPIRATION;
        if (time() > $expiresAt) {
            throw new Exception("Token expiré");
        }
        
        return [
            'employe_id' => (int)$employeId,
            'type' => $type,
            'timestamp' => (int)$timestamp,
            'nonce' => $nonce,
            'version' => $version,
            'expires_at' => $expiresAt,
            'valid' => true
        ];
    }
    
    /**
     * Génère un JWT pour l'authentification
     */
    public function generateJWT(int $userId, string $role, array $permissions = []): string {
        $payload = [
            'iss' => 'PointagePro',
            'aud' => 'PointagePro-Users',
            'iat' => time(),
            'exp' => time() + DatabaseConfig::SESSION_LIFETIME,
            'user_id' => $userId,
            'role' => $role,
            'permissions' => $permissions,
            'jti' => bin2hex(random_bytes(16))
        ];
        
        return JWT::encode($payload, $this->jwtSecret, self::ALGORITHM);
    }
    
    /**
     * Valide un JWT
     */
    public function validateJWT(string $jwt): array {
        try {
            $decoded = JWT::decode($jwt, new Key($this->jwtSecret, self::ALGORITHM));
            return (array) $decoded;
        } catch (Exception $e) {
            throw new Exception("JWT invalide: " . $e->getMessage());
        }
    }
    
    /**
     * Génère un token de rafraîchissement
     */
    public function generateRefreshToken(int $userId): array {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = time() + DatabaseConfig::REFRESH_TOKEN_EXPIRATION;
        
        return [
            'token' => $token,
            'hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'user_id' => $userId
        ];
    }
}