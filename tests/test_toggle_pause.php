<?php
// Simple local test for pause start/end logic using the DB directly.
// Run with: php tests/test_toggle_pause.php
require_once __DIR__ . '/../src/config/bootstrap.php';

function ok($msg){ echo "[OK] $msg\n"; }
function fail($msg){ echo "[FAIL] $msg\n"; exit(1); }

// Create a test employee if not exists
$testMat = 'TEST-PAUSE';
$stmt = $pdo->prepare('SELECT id FROM employes WHERE matricule = ? LIMIT 1');
$stmt->execute([$testMat]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    $stmt = $pdo->prepare('INSERT INTO employes (nom, prenom, matricule, email) VALUES (?, ?, ?, ?)');
    $stmt->execute(['Test', 'Pause', $testMat, 'pause@test.local']);
    $empId = $pdo->lastInsertId();
    ok('Créé employé test id=' . $empId);
} else {
    $empId = $emp['id'];
    ok('Employé test exist id=' . $empId);
}

// Clean up any existing test pointages today
$today = date('Y-m-d');
$pdo->prepare("DELETE FROM pointages WHERE employe_id = ? AND DATE(COALESCE(date_heure, date_pointage)) = ? AND type IN ('pause_debut','pause_fin')")->execute([$empId, $today]);

// Start a pause
$now = date('Y-m-d H:i:s', time() - 360); // simulate 6 minutes ago
$pdo->prepare("INSERT INTO pointages (employe_id, type, date_heure, etat, statut, commentaire, pause_debut) VALUES (?, 'pause_debut', ?, 'pause', 'présent', ?, ?)")->execute([$empId, $now, 'test start', $now]);
$startId = $pdo->lastInsertId();
if (!$startId) fail('Échec création pause_debut');
ok('pause_debut created id=' . $startId);

// Detect open pause using same query as API
$stmt = $pdo->prepare("SELECT p1.id, COALESCE(p1.date_heure, p1.date_pointage) as date_heure
        FROM pointages p1
        WHERE p1.employe_id = :emp
          AND p1.type = 'pause_debut'
          AND DATE(COALESCE(p1.date_heure, p1.date_pointage)) = :date
          AND NOT EXISTS (
              SELECT 1 FROM pointages p2
              WHERE p2.employe_id = p1.employe_id
                AND p2.type = 'pause_fin'
                AND DATE(COALESCE(p2.date_heure, p2.date_pointage)) = DATE(COALESCE(p1.date_heure, p1.date_pointage))
                AND COALESCE(p2.date_heure, p2.date_pointage) > COALESCE(p1.date_heure, p1.date_pointage)
          )
        ORDER BY COALESCE(p1.date_heure, p1.date_pointage) DESC
        LIMIT 1");
$stmt->execute([':emp' => $empId, ':date' => $today]);
$open = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$open) fail('Open pause not detected');
ok('Open pause detected id=' . $open['id']);

// End the pause
$endNow = date('Y-m-d H:i:s');
$pdo->prepare("INSERT INTO pointages (employe_id, type, date_heure, etat, statut) VALUES (?, 'pause_fin', ?, 'pause', 'présent')")->execute([$empId, $endNow]);
$endId = $pdo->lastInsertId();
if (!$endId) fail('Échec création pause_fin');
ok('pause_fin created id=' . $endId);

// Calculate duration
$startTs = strtotime($open['date_heure']);
$endTs = strtotime($endNow);
$duration = max(0, intval(($endTs - $startTs) / 60));
ok('Duration minutes computed = ' . $duration);

// Clean up test rows (optional)
//$pdo->prepare('DELETE FROM pointages WHERE id IN (?, ?)')->execute([$startId, $endId]);

ok('Test toggle pause flow terminé');

?>