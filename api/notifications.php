<?php
require_once '../src/config/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['employe_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$employe_id = $_SESSION['employe_id'];
$notificationController = new NotificationController($pdo);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? $input['action'] ?? '';

    switch ($action) {
        case 'get_unread_count':
            $count = $notificationController->countUnread($employe_id);
            echo json_encode(['success' => true, 'count' => $count]);
            break;

        case 'mark_as_read':
            $notification_id = $input['notification_id'] ?? null;
            if (!$notification_id) {
                throw new Exception('ID de notification manquant');
            }
            
            if ($notificationController->markAsRead($notification_id, $employe_id)) {
                echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
            } else {
                throw new Exception('Erreur lors du marquage de la notification');
            }
            break;

        case 'mark_all_as_read':
            if ($notificationController->markAllAsRead($employe_id)) {
                echo json_encode(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
            } else {
                throw new Exception('Erreur lors du marquage des notifications');
            }
            break;

        case 'get_notifications':
            $limit = $input['limit'] ?? 10;
            $unread_only = $input['unread_only'] ?? false;
            
            $notifications = $notificationController->getByEmploye(
                $employe_id, 
                $limit, 
                $unread_only
            );
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;

        case 'delete':
            $notification_id = $input['notification_id'] ?? null;
            if (!$notification_id) {
                throw new Exception('ID de notification manquant');
            }
            
            if ($notificationController->delete($notification_id, $employe_id)) {
                echo json_encode(['success' => true, 'message' => 'Notification supprimée']);
            } else {
                throw new Exception('Erreur lors de la suppression de la notification');
            }
            break;

        default:
            throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>