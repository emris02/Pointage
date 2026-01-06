<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/controllers/NotificationController.php';

// This test creates a message and checks notifications are created for recipients.
// Ensure DB has an employe with id=2 (or adjust below)

$senderAdminId = 1; // admin id
$recipientId = 2;  // recipient employe id (adjust as needed)

$pdo->beginTransaction();
try {
    // Create a message
    $sujet = 'Test sujet ' . rand(1000,9999);
    $contenu = 'Contenu de test ' . uniqid();

    $stmt = $pdo->prepare("INSERT INTO messages (expediteur_id, sujet, contenu) VALUES (?, ?, ?)");
    $stmt->execute([$senderAdminId, $sujet, $contenu]);
    $message_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO message_destinataires (message_id, destinataire_id) VALUES (?, ?)");
    $stmt->execute([$message_id, $recipientId]);

    // Simulate notification creation (same logic as envoyer_message.php)
    $nc = new NotificationController($pdo);
    $created = $nc->create([
        'employe_id' => $recipientId,
        'titre' => 'Nouveau message: ' . $sujet,
        'message' => substr($contenu, 0, 200),
        'type' => 'info',
        'lien' => 'message_detail.php?id=' . $message_id
    ]);

    if ($created) {
        echo "Notification created OK for recipient {$recipientId}\n";
        $count = $nc->countUnread($recipientId);
        echo "Unread count for recipient: {$count}\n";
        $items = $nc->getByEmploye($recipientId, 5);
        print_r($items[0] ?? []);

        // cleanup: delete notification + message + message_dest
        $notifId = $items[0]['id'] ?? null;
        if ($notifId) $nc->delete($notifId, $recipientId);
    } else {
        echo "Failed to create notification\n";
    }

    // cleanup message
    $pdo->prepare("DELETE FROM message_destinataires WHERE message_id = ?")->execute([$message_id]);
    $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$message_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
