<?php
class NotificationService {
    private $pdo;
    private $controller;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->controller = new NotificationController($pdo);
    }

    public function createNotification(array $data): bool {
        // normalize keys used across the codebase
        $employe_id = $data['destinataire_id'] ?? $data['employe_id'] ?? null;
        $titre = $data['titre'] ?? $data['title'] ?? ($data['titre'] ?? 'Notification');
        $message = $data['message'] ?? $data['contenu'] ?? '';
        $type = $data['type'] ?? 'info';
        $lien = $data['lien'] ?? null;
        $pointage_id = $data['pointage_id'] ?? null;

        if (!$employe_id) {
            error_log('NotificationService::createNotification missing destinataire_id/employe_id');
            return false;
        }

        return $this->controller->create([
            'employe_id' => $employe_id,
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'lien' => $lien,
            'pointage_id' => $pointage_id
        ]);
    }

    public function getNotificationsDemande(int $demande_id): array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE lien LIKE ? ORDER BY date DESC LIMIT 50");
            $like = "%mes_demandes.php?id={$demande_id}%";
            $stmt->execute([$like]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('NotificationService::getNotificationsDemande error: ' . $e->getMessage());
            return [];
        }
    }
}
