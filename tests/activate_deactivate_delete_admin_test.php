<?php
// Simple integration test for activate / deactivate / delete flows
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';

$adminModel = new Admin($pdo);

echo "Starting admin activate/deactivate/delete test...\n";

// Create a dummy admin
$createdId = $adminModel->create([
    'nom' => 'Test', 'prenom' => 'Admin', 'email' => 'test.admin+' . rand(1,9999) . '@example.com', 'password' => 'Password123!', 'role' => ROLE_ADMIN, 'statut' => 'actif'
]);
if (!$createdId) { echo "Failed to create admin\n"; exit(1); }
echo "Created admin id: $createdId\n";

// Deactivate via model
$updated = $adminModel->update((int)$createdId, ['statut' => 'inactif']);
if (!$updated) { echo "Failed to update admin statut\n"; exit(1); }
$ref = $adminModel->getById((int)$createdId);
if ($ref && $ref['statut'] === 'inactif') {
    echo "Success: admin statut is inactif\n";
} else {
    echo "Unexpected statut: " . var_export($ref, true) . "\n";
    exit(1);
}

// Activate back
$updated = $adminModel->update((int)$createdId, ['statut' => 'actif']);
$ref = $adminModel->getById((int)$createdId);
if ($ref && $ref['statut'] === 'actif') {
    echo "Success: admin statut back to actif\n";
} else {
    echo "Activation failed\n"; exit(1);
}

// Delete permanently via model
$deleted = $adminModel->delete((int)$createdId);
if ($deleted) {
    echo "Success: admin deleted\n";
} else {
    echo "Delete failed\n"; exit(1);
}

echo "Test finished\n";
