<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

// Reset admin password to known value for testing
$plain = 'Secret123!';
$hash = password_hash($plain, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE admins SET password = ? WHERE id = 1')->execute([$hash]);

// Emulate POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email'] = 'ouologuemoussa@gmail.com';
$_POST['password'] = $plain;

$auth = new AuthController($pdo);
$res = $auth->login();
echo json_encode($res, JSON_PRETTY_PRINT);
