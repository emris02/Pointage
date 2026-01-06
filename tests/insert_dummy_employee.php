<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

// This script attempts to insert a dummy employee using the same constraints as the form.
// Run locally: php tests/insert_dummy_employee.php

try {
    $prenom = 'Test' . rand(100,999);
    $nom = 'User' . rand(100,999);
    $email = 'test.' . time() . '@example.local';
    $departement = 'test_dept';

    // Generate matricule
    $base = strtoupper(preg_replace('/[^A-Z]/', '', substr($prenom, 0, 3) . substr($nom, 0, 3)));
    $deptCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($departement, 0, 2)));
    $mat = $base . $deptCode . str_pad((string)random_int(0,9999), 4, '0', STR_PAD_LEFT);

    $plainPassword = bin2hex(random_bytes(4));
    $pwdHash = password_hash($plainPassword, PASSWORD_BCRYPT);

    // Build a safe insert depending on columns
    $hasMot = (bool)$pdo->query("SHOW COLUMNS FROM employes LIKE 'mot_de_passe'")->fetch();

    $sql = "INSERT INTO employes (prenom, nom, email, telephone, adresse, departement, poste, date_creation, photo, statut, matricule" . ($hasMot ? ", mot_de_passe" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . ($hasMot ? ", ?" : "") . ")";

    $params = [$prenom, $nom, $email, '', '', $departement, 'Testeur', date('Y-m-d H:i:s'), null, 'actif', $mat];
    if ($hasMot) $params[] = $pwdHash;

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);

    if ($ok) {
        echo "OK: employe inserted with matricule=$mat and temp password=$plainPassword\n";
    } else {
        echo "ERROR: insert failed: " . json_encode($stmt->errorInfo()) . "\n";
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
