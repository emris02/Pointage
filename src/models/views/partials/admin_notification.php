<?php
// admin_notification.php : gestion des notifications de justification de retard pour l'admin
 //
require_once 'db.php';

// Vérifier que l'utilisateur est bien un admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    // Liste des notifications de justification de retard non traitées
    $stmt = $pdo->prepare("SELECT n.id, n.employe_id, e.nom, e.prenom, n.date, n.message, n.statut, n.lu
        FROM notifications n
        JOIN employes e ON n.employe_id = e.id
        WHERE n.type = 'justif_retard'
        ORDER BY n.date DESC LIMIT 50");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'read' && isset($_POST['id'])) {
    // Marquer comme lu
    $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'set_statut' && isset($_POST['id']) && isset($_POST['statut'])) {
    // Autoriser ou refuser la justification
    $stmt = $pdo->prepare("UPDATE notifications SET statut = ? WHERE id = ?");
    $stmt->execute([$_POST['statut'], $_POST['id']]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action inconnue']);
exit;
