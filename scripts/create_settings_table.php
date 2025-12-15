<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cle VARCHAR(191) NOT NULL UNIQUE,
        valeur TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "Table 'settings' created or already exists.\n";
} catch (PDOException $e) {
    echo "Error creating settings table: " . $e->getMessage() . "\n";
    exit(1);
}
