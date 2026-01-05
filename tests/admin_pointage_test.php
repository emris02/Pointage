<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';
require_once __DIR__ . '/../validate_badge.php';

echo "Starting admin pointage test...\n";
$adminModel = new Admin($pdo);
$adminId = $adminModel->create([
    'nom' => 'Pointage',
    'prenom' => 'Admin',
    'email' => 'pointage.admin+' . rand(1000,9999) . '@example.com',
    'password' => 'Password123!',
    'role' => 'admin',
    'statut' => 'actif'
]);
if (!$adminId) { echo "Failed to create admin\n"; exit(1); }

echo "Created admin id: $adminId\n";

$tokenData = BadgeManager::regenerateTokenForAdmin($adminId, $pdo);
$token = $tokenData['token'];

echo "Token generated: " . substr($token,0,80) . "...\n";
try {
    $verified = BadgeManager::verifyToken($token, $pdo);
    echo "Verified token owner: ";
    if (isset($verified['admin_id'])) echo "admin_id={$verified['admin_id']}\n"; else echo "employe_id={$verified['employe_id']}\n";
} catch (Exception $e) {
    echo "verifyToken FAIL: " . $e->getMessage() . "\n";
}

$svc = new PointageService($pdo);
try {
    $res = $svc->traiterPointage($token);
    print_r($res);
} catch (Exception $e) {
    echo "traiterPointage threw: " . $e->getMessage() . "\n";
}

// Verify pointage row exists for admin
$stmt = $pdo->prepare('SELECT * FROM pointages WHERE admin_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([$adminId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "No pointage recorded for admin\n"; exit(1); }

echo "Pointage recorded: type={$row['type']} date_heure={$row['date_heure']}\n";

// Cleanup
$pdo->prepare('DELETE FROM pointages WHERE admin_id = ?')->execute([$adminId]);
$pdo->prepare('DELETE FROM badge_tokens WHERE admin_id = ?')->execute([$adminId]);
$adminModel->delete($adminId);

echo "Cleanup done\n";
