<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';

$adminModel = new Admin($pdo);

echo "Starting regenerate updates admin test...\n";

$createdId = $adminModel->create(['nom'=>'RegenTest','prenom'=>'Admin','email'=>'regen.admin+'.rand(1,9999).'@example.com','password'=>'Password!','role'=>ROLE_ADMIN,'statut'=>'actif']);
if (!$createdId) { echo "Failed create\n"; exit(1); }
echo "Created admin id: $createdId\n";

$res = BadgeManager::regenerateTokenForAdmin((int)$createdId, $pdo);
if (empty($res) || $res['status'] !== 'success') { echo "Regenerate failed\n"; $adminModel->delete((int)$createdId); exit(1); }

$admin = $adminModel->getById((int)$createdId);
if (!$admin) { echo "Admin not found after regen\n"; exit(1); }

if (!empty($admin['badge_id']) && !empty($admin['badge_token']) && $admin['badge_actif'] == 1) {
    echo "Success: admin badge fields updated: ";
    echo "badge_id={$admin['badge_id']}, badge_token=".substr($admin['badge_token'],0,12)."\n";
} else {
    echo "Failure: admin badge not updated: ".var_export($admin, true)."\n";
    $adminModel->delete((int)$createdId);
    exit(1);
}

// Cleanup
$pdo->prepare('DELETE FROM badge_tokens WHERE admin_id = ?')->execute([$createdId]);
$adminModel->delete((int)$createdId);
echo "Test finished\n";
