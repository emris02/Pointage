<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';

echo "Starting admin badge token verification test...\n\n";
$adminModel = new Admin($pdo);
$adminId = $adminModel->create([
    'nom' => 'Token',
    'prenom' => 'Admin',
    'email' => 'token.admin+' . rand(10000,99999) . '@example.com',
    'password' => 'Password123!',
    'role' => 'admin',
    'statut' => 'actif'
]);

if (!$adminId) { echo "Failed to create test admin\n"; exit(1); }

echo "Created admin id: $adminId\n";

$tokenData = BadgeManager::regenerateTokenForAdmin($adminId, $pdo);
if (empty($tokenData['token'])) { echo "Failed to generate token for admin\n"; exit(1); }

echo "Generated token hash: {$tokenData['token_hash']}\n";

// Verify token
$result = BadgeManager::verifyToken($tokenData['token'], $pdo);
if ($result['validation']['signature_valid'] !== true) { echo "Signature invalid\n"; exit(1); }

if ((int)$result['admin_id'] !== (int)$adminId) { echo "Token does not belong to admin\n"; exit(1); }

echo "Admin token verified and belongs to admin id {$result['admin_id']}\n";

// Cleanup
$adminModel->delete($adminId);
$pdo->prepare('DELETE FROM badge_tokens WHERE admin_id = ?')->execute([$adminId]);

echo "Cleanup done\n";
