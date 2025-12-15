<?php
/**
 * Modèle Rapport
 * Gestion des rapports de pointage
 */
class Rapport {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM rapports ORDER BY date_rapport DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM rapports WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function create($data) {
        $stmt = $this->db->prepare("INSERT INTO rapports (titre, contenu, date_rapport) VALUES (?, ?, NOW())");
        $stmt->execute([$data['titre'], $data['contenu']]);
        return $this->db->lastInsertId();
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare("UPDATE rapports SET titre = ?, contenu = ? WHERE id = ?");
        return $stmt->execute([$data['titre'], $data['contenu'], $id]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM rapports WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
