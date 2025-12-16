<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
$stmt = $pdo->query('SELECT id, nom, prenom FROM employes LIMIT 10');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "EMPLOYEE: id={$r['id']} name={$r['prenom']} {$r['nom']}\n";
}
