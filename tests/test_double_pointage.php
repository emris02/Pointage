<?php
// Test minimal pour vérifier la logique "déjà pointé aujourd'hui" dans Pointage::canPoint
require_once __DIR__ . '/../src/models/Pointage.php';

// Créer une base SQLite en mémoire
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Créer table pointages minimale (structure conforme à MySQL)
$pdo->exec("CREATE TABLE pointages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date_pointage DATETIME,
    date_heure DATETIME DEFAULT CURRENT_TIMESTAMP,
    employe_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    statut TEXT DEFAULT 'présent',
    ip_address TEXT,
    device_info TEXT,
    badge_token_id INTEGER,
    latitude REAL,
    longitude REAL,
    qr_code_id INTEGER,
    etat TEXT DEFAULT 'normal',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Définir constantes utilisées par le modèle
if (!defined('POINTAGE_ARRIVEE')) define('POINTAGE_ARRIVEE', 'arrivee');
if (!defined('POINTAGE_DEPART')) define('POINTAGE_DEPART', 'depart');

$pointageModel = new Pointage($pdo);
$employeId = 42;

// 1) Aucun pointage existant -> canPoint(arrivee) === true
assert($pointageModel->canPoint($employeId, POINTAGE_ARRIVEE) === true, 'Should allow first arrival');

// Insérer une arrivée aujourd'hui
$stmt = $pdo->prepare("INSERT INTO pointages (date_pointage, employe_id, type, statut, date_heure) VALUES (datetime('now'), ?, ?, 'présent', datetime('now'))");
$stmt->execute([$employeId, POINTAGE_ARRIVEE]);

// 2) Nouvelle tentative d'arrivée aujourd'hui -> doit être refusée
if ($pointageModel->canPoint($employeId, POINTAGE_ARRIVEE) !== false) {
    echo "Test failed: double arrival allowed\n";
    exit(2);
}

// 3) Tentative de départ -> doit être autorisée
if ($pointageModel->canPoint($employeId, POINTAGE_DEPART) !== true) {
    echo "Test failed: departure after arrival should be allowed\n";
    exit(3);
}

// Insérer un départ aujourd'hui
$stmt = $pdo->prepare("INSERT INTO pointages (date_pointage, employe_id, type, statut, date_heure) VALUES (datetime('now'), ?, ?, 'présent', datetime('now'))");
$stmt->execute([$employeId, POINTAGE_DEPART]);

// 4) Nouvelle tentative de départ aujourd'hui -> doit être refusée
if ($pointageModel->canPoint($employeId, POINTAGE_DEPART) !== false) {
    echo "Test failed: double departure allowed\n";
    exit(4);
}

echo "All tests passed\n";
exit(0);
