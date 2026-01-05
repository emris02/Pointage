<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

try {
    $nom = 'AdminTest' . rand(100,999);
    $prenom = 'Super' . rand(100,999);
    $email = 'adm.' . time() . '@example.local';
    $telephone = '77' . rand(1000000,9999999);
    $adresse = 'Test Address';
    $poste = 'Ops';
    $departement = 'administration';
    $password = 'TestPass' . rand(100,999);

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO admins (nom, prenom, adresse, email, telephone, password, role, poste, departement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([$nom, $prenom, $adresse, $email, $telephone, $passwordHash, 'admin', $poste, $departement]);

    if ($ok) {
        echo "OK: admin inserted with email=$email and temp password=$password\n";
    } else {
        echo "ERROR: insert failed: " . json_encode($stmt->errorInfo()) . "\n";
    }
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
