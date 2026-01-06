<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';

$adminModel = new Admin($pdo);

echo "Starting deactivate admin test...\n";

// Create dummy admin
$dummy = [
    'nom' => 'Test',
    'prenom' => 'Deact',
    'email' => 'test.deact+' . time() . '@example.com',
    'password' => 'Password123!',
    'role' => ROLE_ADMIN,
    'statut' => 'actif'
];

$createdId = $adminModel->create($dummy);
if (!$createdId) {
    echo "Failed to create dummy admin\n";
    exit(1);
}

echo "Created admin id: $createdId\n";

// Deactivate
$updated = $adminModel->update((int)$createdId, ['statut' => 'inactif']);
if (!$updated) {
    echo "Failed to update admin statut\n";
    // Cleanup
    $adminModel->delete((int)$createdId);
    exit(1);
}

$ref = $adminModel->getById((int)$createdId);
if ($ref && $ref['statut'] === 'inactif') {
    echo "Success: admin statut is inactif\n";
    // Cleanup: delete the dummy
    $adminModel->delete((int)$createdId);
    exit(0);
} else {
    echo "Unexpected statut: " . var_export($ref, true) . "\n";
    // Cleanup
    $adminModel->delete((int)$createdId);
    exit(1);
}
