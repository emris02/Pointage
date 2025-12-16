<?php
 //
require_once('db.php');

if (!isset($_SESSION['employe_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$employe_id = $_SESSION['employe_id'];

// Vérifier si le badge actuel est toujours valide
$query = "SELECT COUNT(*) FROM badge_tokens 
          WHERE employe_id = :employe_id 
          AND token_hash = :token_hash
          AND expires_at > NOW()";
$stmt = $pdo->prepare($query);
$stmt->execute([
    ':employe_id' => $employe_id,
    ':token_hash' => $_POST['token'] ?? ''
]);

$is_valid = $stmt->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'status' => $is_valid ? 'valid' : 'expired'
]);
?>