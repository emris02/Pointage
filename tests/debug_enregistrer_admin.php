<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';
require_once __DIR__ . '/../validate_badge.php';

$adminModel = new Admin($pdo);
$adminId = $adminModel->create(['nom'=>'Debug','prenom'=>'Admin','email'=>'debug.admin+'.rand(1,9999).'@example.com','password'=>'Password!','role'=>'admin','statut'=>'actif']);
$tokenData = BadgeManager::regenerateTokenForAdmin($adminId, $pdo);
$svc = new PointageService($pdo);
try {
    $res = $svc->enregistrerPointageAdmin($adminId, (int)$tokenData['token_hash']);
    print_r($res);
} catch (Exception $e) {
    echo 'ERR: '.$e->getMessage()."\n";
}

// cleanup
$pdo->prepare('DELETE FROM badge_tokens WHERE admin_id = ?')->execute([$adminId]);
$adminModel->delete($adminId);
