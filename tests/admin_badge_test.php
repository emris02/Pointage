<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';

echo "Starting admin badge assignment test...\n\n";
$adminModel = new Admin($pdo);

// Create test admin
$adminData = [
    'nom' => 'Badge',
    'prenom' => 'Admin',
    'email' => 'badge.admin+' . rand(1000,9999) . '@example.com',
    'password' => 'Password123!',
    'role' => 'admin',
    'statut' => 'actif'
];
$adminId = $adminModel->create($adminData);
if (!$adminId) {
    echo "Failed to create admin for test\n"; exit(1);
}

echo "Created admin id: $adminId\n";

// Assign badge
$badgeInfo = [
    'badge_id' => 'ADM-' . uniqid(),
    'badge_token' => bin2hex(random_bytes(16)),
    'badge_actif' => 1,
    'badge_created' => date('Y-m-d H:i:s'),
    'badge_expires' => date('Y-m-d H:i:s', strtotime('+1 year'))
];

$assigned = $adminModel->assignBadge($adminId, $badgeInfo);
if (!$assigned) {
    echo "Failed to assign badge\n"; exit(1);
}

echo "Badge assigned successfully\n";

$retrieved = $adminModel->getBadge($adminId);
if (!$retrieved) {
    echo "Failed to retrieve badge\n"; exit(1);
}

echo "Retrieved badge:", PHP_EOL;
print_r($retrieved);

// Assertions
if ($retrieved['badge_id'] !== $badgeInfo['badge_id']) {
    echo "Badge ID mismatch\n"; exit(1);
}

if ((int)$retrieved['badge_actif'] !== 1) {
    echo "Badge not active\n"; exit(1);
}

echo "Success: admin badge stored and retrievable\n";

// Clean up: delete test admin
$adminModel->delete($adminId);
echo "Cleanup done\n";
