<?php
session_start();
require 'db.php';

// Vérifier les droits admin
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['success' => false, 'message' => 'Accès non autorisé']));
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'all';
$admin_id = (int)($data['admin_id'] ?? 0);

try {
    if ($type === 'all' || $type === 'badge') {
        // Marquer les demandes de badge comme lues
        $stmt = $pdo->prepare("
            UPDATE demandes_badge 
            SET is_read = 1 
            WHERE statut = 'en_attente' 
            AND is_read = 0
        ");
        $stmt->execute();
    }
    
    if ($type === 'all' || $type === 'message') {
        // Marquer les messages comme lus
        $stmt = $pdo->prepare("
            UPDATE message_destinataires 
            SET lu = 1 
            WHERE destinataire_id = :admin_id 
            AND lu = 0
        ");
        $stmt->execute([':admin_id' => $admin_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}