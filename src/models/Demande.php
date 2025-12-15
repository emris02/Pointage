<?php
/**
 * Modèle Demande
 * Gestion des demandes (congés, absences, etc.)
 */
class Demande {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function getAll() {
        $stmt = $this->db->prepare("SELECT 
                d.id, d.employe_id, d.type, d.motif, d.date_demande, d.statut,
                e.prenom, e.nom, e.poste, e.departement, e.email, e.photo
            FROM demandes d
            JOIN employes e ON d.employe_id = e.id
            ORDER BY d.date_demande DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT 
                d.id, d.employe_id, d.type, d.motif, d.date_demande, d.statut,
                e.prenom, e.nom, e.poste, e.departement, e.email, e.photo
            FROM demandes d
            JOIN employes e ON d.employe_id = e.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function create($data) {
        $stmt = $this->db->prepare("INSERT INTO demandes (employe_id, type, motif, date_demande, statut) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$data['employe_id'], $data['type'], $data['motif'], $data['statut'] ?? 'en attente']);
        return $this->db->lastInsertId();
    }
    public function update($id, $data) {
        $stmt = $this->db->prepare("UPDATE demandes SET type = ?, motif = ?, statut = ? WHERE id = ?");
        return $stmt->execute([$data['type'], $data['motif'], $data['statut'], $id]);
    }
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM demandes WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
