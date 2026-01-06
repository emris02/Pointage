<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Employe.php';

$employeModel = new Employe($pdo);

// Insert dummy employee
$nom = 'Test'; $prenom = 'Employe'; $email = 'test.employee+' . rand(1000,9999) . '@example.com';
$createdId = $employeModel->create([
    'nom' => $nom,
    'prenom' => $prenom,
    'email' => $email,
    'password' => 'Password123!'
]);
if (!$createdId) { echo "Failed to create employee\n"; exit(1); }

echo "Created employee id: $createdId\n";

// Insert a badge token active
$token = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token, token_hash, status, expires_at, created_at) VALUES (?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
$stmt->execute([$createdId, $token, hash('sha256', $token)]);
$btId = $pdo->lastInsertId();
if (!$btId) { echo "Failed to insert badge token\n"; exit(1); }

echo "Inserted badge token id: $btId\n";

// Run the same update as deactivate_employe.php
$stmt = $pdo->prepare("UPDATE badge_tokens SET status = 'revoked', expires_at = NOW() WHERE employe_id = ? AND status = 'active'");
$stmt->execute([$createdId]);

$stmt = $pdo->prepare("SELECT status FROM badge_tokens WHERE id = ?");
$stmt->execute([$btId]);
$status = $stmt->fetchColumn();

echo "Badge token status after update: " . ($status ?? 'NULL') . "\n";

if ($status === 'revoked') {
    echo "Success: token status is revoked\n";
} else {
    echo "Failure: token status is not revoked (got: " . var_export($status, true) . ")\n";
}

// cleanup
$employeModel->delete((int)$createdId);
$stmt = $pdo->prepare("DELETE FROM badge_tokens WHERE id = ?"); $stmt->execute([$btId]);

echo "Test finished\n";