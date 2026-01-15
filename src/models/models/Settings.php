<?php
/**
 * Modèle Settings
 * Gestion des paramètres d'administration
 */
class Settings {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM settings");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function get($key) {
        $stmt = $this->db->prepare("SELECT * FROM settings WHERE cle = ?");
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }
    public function set($key, $value) {
        $stmt = $this->db->prepare("REPLACE INTO settings (cle, valeur) VALUES (?, ?)");
        return $stmt->execute([$key, $value]);
    }
}
