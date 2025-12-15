<?php
/**
 * Configuration de la base de données
 * Connexion PDO centralisée
 */

$host     = 'localhost';
$dbname   = 'pointage';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    die('Erreur de connexion à la base de données.');
}
