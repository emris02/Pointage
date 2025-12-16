<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
// Insert a test pointage (arrivee) for employe_id 1 and print its id
$employe_id = isset($_GET['employe_id']) ? (int)$_GET['employe_id'] : 1; // pass ?employe_id=21
try {
    $stmt = $pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type) VALUES (NOW(), ?, 'arrivee')");
    $stmt->execute([$employe_id]);
    $id = $pdo->lastInsertId();
    echo "TEST_POINTAGE_ID=" . $id . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
