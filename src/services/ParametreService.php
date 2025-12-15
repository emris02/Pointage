<?php
/**
 * Service pour gérer les paramètres utilisateur
 * Table recommandée SQL (MySQL):
 *
 * CREATE TABLE `parametres_utilisateur` (
 *   `id` INT AUTO_INCREMENT PRIMARY KEY,
 *   `user_id` INT NOT NULL,
 *   `user_type` VARCHAR(32) DEFAULT 'admin',
 *   `cle` VARCHAR(255) NOT NULL,
 *   `valeur` TEXT,
 *   `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
 *   `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *   UNIQUE KEY `user_key` (`user_id`,`cle`)
 * );
 */

class ParametreService {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getUserParam(int $userId, string $key, string $default = null) {
        $stmt = $this->db->prepare("SELECT valeur FROM parametres_utilisateur WHERE user_id = :uid AND cle = :cle LIMIT 1");
        $stmt->execute([':uid' => $userId, ':cle' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $default;
        return $row['valeur'];
    }

    public function getAllUserParams(int $userId): array {
        $stmt = $this->db->prepare("SELECT cle, valeur FROM parametres_utilisateur WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[$r['cle']] = $r['valeur'];
        return $out;
    }

    public function setUserParam(int $userId, string $key, $value, string $userType = 'admin'): bool {
        // REPLACE INTO style upsert
        $sql = "INSERT INTO parametres_utilisateur (user_id, user_type, cle, valeur) VALUES (:uid, :utype, :cle, :valeur)
                ON DUPLICATE KEY UPDATE valeur = :valeur2, updated_at = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':uid' => $userId,
            ':utype' => $userType,
            ':cle' => $key,
            ':valeur' => is_scalar($value) ? (string)$value : json_encode($value),
            ':valeur2' => is_scalar($value) ? (string)$value : json_encode($value)
        ]);
    }

    public function setMultiple(int $userId, array $data, string $userType = 'admin') {
        foreach ($data as $k => $v) {
            $this->setUserParam($userId, $k, $v, $userType);
        }
    }
}
