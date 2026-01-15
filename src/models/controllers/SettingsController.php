<?php
/**
 * Contrôleur des paramètres
 */
class SettingsController {
    private $settingsModel;
    public function __construct(PDO $db) { $this->settingsModel = new Settings($db); }
    public function index() { return $this->settingsModel->getAll(); }
    public function get($key) { return $this->settingsModel->get($key); }
    public function set($key, $value) { return $this->settingsModel->set($key, $value); }
}
