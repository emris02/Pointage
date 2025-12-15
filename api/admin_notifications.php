<?php
/**
 * API de gestion des notifications
 * Version améliorée avec meilleure sécurité, performances et gestion d'erreurs
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once __DIR__ . '/../src/config/bootstrap.php';

// Gestion des sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Vérification d'authentification et d'autorisation
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Accès non autorisé. Veuillez vous connecter.'
    ]);
    exit;
}

// Fonctions utilitaires
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function logNotificationError($message) {
    error_log(date('Y-m-d H:i:s') . " - Notification API Error: " . $message);
}

// Variables
$response = ['success' => false, 'message' => ''];
$adminId = (int)$_SESSION['admin_id'];

try {
    // Déterminer la méthode HTTP et l'action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = '';
    $inputData = [];

    switch ($method) {
        case 'GET':
            $action = sanitizeInput($_GET['action'] ?? 'list');
            break;
            
        case 'POST':
        case 'PUT':
        case 'DELETE':
            // Récupérer les données selon le Content-Type
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $jsonInput = file_get_contents('php://input');
                $inputData = json_decode($jsonInput, true) ?? [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Données JSON invalides');
                }
            } else {
                $inputData = $_POST;
            }
            
            $action = sanitizeInput($inputData['action'] ?? ($_POST['action'] ?? 'list'));
            break;
            
        default:
            throw new Exception('Méthode HTTP non supportée');
    }

    // Validation de l'action
    $allowedActions = ['list', 'read', 'mark_all_read', 'get_stats', 'set_status'];
    if (!in_array($action, $allowedActions)) {
        throw new Exception('Action non autorisée');
    }

    // Traitement selon l'action
    switch ($action) {
        case 'list':
            $limit = min((int)($_GET['limit'] ?? 20), 50); // Limite de sécurité
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            
            // Récupérer les notifications non lues récentes
            $notifications = [];
            
            // 1. Pointages récents non lus
            $stmt = $pdo->prepare("
                SELECT 
                    p.id, 
                    p.type, 
                    p.date_heure, 
                    e.nom, 
                    e.prenom, 
                    e.photo, 
                    TIMESTAMPDIFF(MINUTE, p.date_heure, NOW()) as minutes_ago,
                    'pointage' as notification_type
                FROM pointages p 
                JOIN employes e ON p.employe_id = e.id 
                WHERE COALESCE(p.is_read, 0) = 0 
                AND (p.admin_id = :admin_id OR :admin_id = 0 OR p.admin_id IS NULL)
                ORDER BY p.date_heure DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pointages as $p) {
                $notifications[] = [
                    'type' => 'pointage',
                    'id' => (int)$p['id'],
                    'prenom' => $p['prenom'],
                    'nom' => $p['nom'],
                    'date' => $p['date_heure'],
                    'message' => "{$p['prenom']} {$p['nom']} a pointé (" . ucfirst($p['type']) . ")",
                    'photo' => !empty($p['photo']) ? $p['photo'] : null,
                    'time_ago' => (int)$p['minutes_ago'],
                    'notification_type' => $p['notification_type'],
                    'timestamp' => strtotime($p['date_heure'])
                ];
            }
            
            // 2. Demandes en attente
            $stmt = $pdo->prepare("
                SELECT 
                    d.id, 
                    d.type, 
                    d.date_demande, 
                    e.nom, 
                    e.prenom, 
                    e.photo, 
                    TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) as heures_attente,
                    'demande' as notification_type
                FROM demandes_badge d 
                JOIN employes e ON d.employe_id = e.id 
                WHERE d.statut = 'en_attente' 
                AND COALESCE(d.is_read, 0) = 0 
                ORDER BY d.date_demande DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($demandes as $d) {
                $notifications[] = [
                    'type' => 'demande',
                    'id' => (int)$d['id'],
                    'prenom' => $d['prenom'],
                    'nom' => $d['nom'],
                    'date' => $d['date_demande'],
                    'message' => "{$d['prenom']} {$d['nom']} - Demande de " . ucfirst($d['type']),
                    'photo' => !empty($d['photo']) ? $d['photo'] : null,
                    'time_ago' => (int)$d['heures_attente'],
                    'notification_type' => $d['notification_type'],
                    'timestamp' => strtotime($d['date_demande'])
                ];
            }
            
            // 3. Tri par date (plus récent d'abord)
            usort($notifications, function($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });
            
            // Statistiques
            $totalUnread = 0;
            foreach ($notifications as $notif) {
                if (isset($notif['time_ago']) && $notif['time_ago'] < 1440) { // Moins de 24h
                    $totalUnread++;
                }
            }
            
            $response = [
                'success' => true,
                'data' => array_slice($notifications, 0, $limit),
                'stats' => [
                    'total' => count($notifications),
                    'unread' => $totalUnread,
                    'pointages' => count($pointages),
                    'demandes' => count($demandes)
                ],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => count($notifications) >= $limit
                ]
            ];
            break;
            
        case 'read':
            // Marquer une notification comme lue
            if ($method !== 'POST' && $method !== 'PUT') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            $id = (int)($inputData['id'] ?? 0);
            $notificationType = sanitizeInput($inputData['notification_type'] ?? 'pointage');
            
            if ($id <= 0) {
                throw new Exception('ID de notification invalide');
            }
            
            $success = false;
            
            if ($notificationType === 'pointage') {
                $stmt = $pdo->prepare('UPDATE pointages SET is_read = 1 WHERE id = ?');
                $stmt->execute([$id]);
                $success = $stmt->rowCount() > 0;
            } elseif ($notificationType === 'demande') {
                $stmt = $pdo->prepare('UPDATE demandes_badge SET is_read = 1 WHERE id = ?');
                $stmt->execute([$id]);
                $success = $stmt->rowCount() > 0;
            } else {
                // Essayer les deux tables
                $stmt = $pdo->prepare('UPDATE pointages SET is_read = 1 WHERE id = ?');
                $stmt->execute([$id]);
                $success = $stmt->rowCount() > 0;
                
                if (!$success) {
                    $stmt = $pdo->prepare('UPDATE demandes_badge SET is_read = 1 WHERE id = ?');
                    $stmt->execute([$id]);
                    $success = $stmt->rowCount() > 0;
                }
            }
            
            $response = [
                'success' => $success,
                'message' => $success ? 'Notification marquée comme lue' : 'Notification non trouvée'
            ];
            break;
            
        case 'mark_all_read':
            // Marquer toutes les notifications comme lues
            if ($method !== 'POST') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            $notificationType = sanitizeInput($inputData['notification_type'] ?? 'all');
            $affectedRows = 0;
            
            if ($notificationType === 'all' || $notificationType === 'pointage') {
                $stmt = $pdo->prepare('UPDATE pointages SET is_read = 1 WHERE COALESCE(is_read, 0) = 0');
                $stmt->execute();
                $affectedRows += $stmt->rowCount();
            }
            
            if ($notificationType === 'all' || $notificationType === 'demande') {
                $stmt = $pdo->prepare("UPDATE demandes_badge SET is_read = 1 WHERE COALESCE(is_read, 0) = 0 AND statut = 'en_attente'");
                $stmt->execute();
                $affectedRows += $stmt->rowCount();
            }
            
            $response = [
                'success' => true,
                'message' => "{$affectedRows} notification(s) marquée(s) comme lue(s)"
            ];
            break;
            
        case 'get_stats':
            // Récupérer les statistiques des notifications
            $stats = [];
            
            // Nombre de pointages non lus
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM pointages WHERE COALESCE(is_read, 0) = 0');
            $stmt->execute();
            $stats['pointages_unread'] = (int)$stmt->fetchColumn();
            
            // Nombre de demandes en attente
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM demandes_badge WHERE statut = 'en_attente' AND COALESCE(is_read, 0) = 0");
            $stmt->execute();
            $stats['demandes_pending'] = (int)$stmt->fetchColumn();
            
            // Total
            $stats['total_unread'] = $stats['pointages_unread'] + $stats['demandes_pending'];
            
            // Pointages aujourd'hui
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pointages WHERE DATE(date_heure) = CURDATE()");
            $stmt->execute();
            $stats['pointages_today'] = (int)$stmt->fetchColumn();
            
            $response = [
                'success' => true,
                'stats' => $stats
            ];
            break;
            
        case 'set_status':
            // Changer le statut d'une demande
            if ($method !== 'POST' && $method !== 'PUT') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            $id = (int)($inputData['id'] ?? 0);
            $statut = sanitizeInput($inputData['statut'] ?? '');
            $comment = sanitizeInput($inputData['comment'] ?? '');
            
            if ($id <= 0 || empty($statut)) {
                throw new Exception('ID ou statut manquant');
            }
            
            $allowedStatuses = ['en_attente', 'approuve', 'refuse', 'annule'];
            if (!in_array($statut, $allowedStatuses)) {
                throw new Exception('Statut non autorisé');
            }
            
            // Essayer demandes_badge d'abord
            $stmt = $pdo->prepare('UPDATE demandes_badge SET statut = ?, commentaire_admin = ?, date_traitement = NOW() WHERE id = ?');
            $stmt->execute([$statut, $comment, $id]);
            
            if ($stmt->rowCount() === 0) {
                // Essayer la table demandes
                $stmt = $pdo->prepare('UPDATE demandes SET statut = ?, commentaire_admin = ?, date_traitement = NOW() WHERE id = ?');
                $stmt->execute([$statut, $comment, $id]);
            }
            
            $response = [
                'success' => $stmt->rowCount() > 0,
                'message' => $stmt->rowCount() > 0 ? 'Statut mis à jour' : 'Demande non trouvée'
            ];
            break;
            
        default:
            throw new Exception('Action non implémentée');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    logNotificationError("PDO Error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Erreur de base de données',
        'debug' => (ENVIRONMENT === 'development') ? $e->getMessage() : null
    ];
} catch (Exception $e) {
    http_response_code(400);
    logNotificationError("General Error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Retourner la réponse JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;