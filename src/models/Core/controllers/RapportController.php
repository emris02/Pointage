<?php
/**
 * Contrôleur des rapports
 */
class RapportController {
    private $rapportModel;
    public function __construct(PDO $db) { $this->rapportModel = new Rapport($db); }
    public function index() { return $this->rapportModel->getAll(); }
    public function show($id) { return $this->rapportModel->getById($id); }
    public function create($data) { return $this->rapportModel->create($data); }
    public function update($id, $data) { return $this->rapportModel->update($id, $data); }
    public function delete($id) { return $this->rapportModel->delete($id); }
}
