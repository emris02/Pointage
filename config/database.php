<?php
/**
 * Configuration de la base de données
 * Système de Pointage Professionnel v2.0
 */

class DatabaseConfig {
    private static $instance = null;
    private $pdo;
    
    // Configuration de la base de données
    private const DB_CONFIG = [
        'host' => 'localhost',
        'dbname' => 'pointage_pro',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ];
    
    // Clés de sécurité
    public const SECRET_KEY = 'XpertPro2025!SecureKey#';
    public const JWT_SECRET = 'JWT_XpertPro_2025_Secret_Key';
    public const ENCRYPTION_KEY = 'AES256_XpertPro_Encryption_Key';
    
    // Configuration des tokens
    public const TOKEN_EXPIRATION = 7200; // 2 heures
    public const REFRESH_TOKEN_EXPIRATION = 604800; // 7 jours
    public const SESSION_LIFETIME = 86400; // 24 heures
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect(): void {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                self::DB_CONFIG['host'],
                self::DB_CONFIG['dbname'],
                self::DB_CONFIG['charset']
            );
            
            $this->pdo = new PDO(
                $dsn,
                self::DB_CONFIG['username'],
                self::DB_CONFIG['password'],
                self::DB_CONFIG['options']
            );
            
        } catch (PDOException $e) {
            error_log("Erreur de connexion DB: " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
    }
    
    public function getConnection(): PDO {
        return $this->pdo;
    }
    
    public function testConnection(): bool {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Paris');

// Configuration des sessions sécurisées
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');