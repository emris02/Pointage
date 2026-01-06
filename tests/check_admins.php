<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

try {
    $stmt = $pdo->query("SELECT id, nom, prenom, email, role, last_activity FROM admins ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "ADMIN COUNT: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "id={$r['id']} name={$r['prenom']} {$r['nom']} email={$r['email']} role={$r['role']} last_activity={$r['last_activity']}\n";
    }
} catch (Throwable $e) {
    echo "ERROR checking admins: " . $e->getMessage() . "\n";
}
