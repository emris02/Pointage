<?php
require_once '../src/config/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $calendrierController = new CalendrierController($pdo);
    $pointageController = new PointageController($pdo);

    switch ($action) {
        case 'get_events':
            $start = $input['start'] ?? date('Y-m-01');
            $end = $input['end'] ?? date('Y-m-t');
            $employeId = $input['employe_id'] ?? $_SESSION['employe_id'] ?? null;
            
            $events = [];
            
            // Récupérer les événements du calendrier
            if ($employeId) {
                $calEvents = $calendrierController->getEmployeEvents($employeId, $start, $end);
            } else {
                $calEvents = $calendrierController->getAllEvents([
                    'date_range' => [
                        'start' => $start,
                        'end' => $end
                    ]
                ]);
            }
            
            // Formater les événements pour FullCalendar
            foreach ($calEvents as $event) {
                $events[] = [
                    'id' => 'event_' . $event['id'],
                    'title' => $event['titre'],
                    'start' => $event['start_date'],
                    'end' => $event['end_date'],
                    'color' => getEventColor($event['type']),
                    'extendedProps' => [
                        'type' => 'evenement',
                        'event_type' => $event['type'],
                        'description' => $event['description'],
                        'employe' => $event['employe_prenom'] . ' ' . $event['employe_nom']
                    ]
                ];
            }
            
            // Récupérer les jours de pointage si c'est un employé
            if ($employeId) {
                $pointageDays = $calendrierController->getPointageDays($employeId, $start, $end);
                
                foreach ($pointageDays as $day) {
                    $events[] = [
                        'id' => 'pointage_' . $day['pointage_date'],
                        'title' => '🕐 Pointages: ' . $day['nb_pointages'],
                        'start' => $day['pointage_date'],
                        'end' => $day['pointage_date'],
                        'color' => '#28a745',
                        'allDay' => true,
                        'extendedProps' => [
                            'type' => 'pointage',
                            'nb_pointages' => $day['nb_pointages'],
                            'premier' => $day['premier_pointage'],
                            'dernier' => $day['dernier_pointage']
                        ]
                    ];
                }
            }
            
            echo json_encode($events);
            break;

        case 'add_event':
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Non autorisé');
            }
            
            $eventData = [
                'titre' => $input['titre'],
                'type' => $input['type'],
                'description' => $input['description'] ?? '',
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'employe_id' => !empty($input['employe_id']) ? $input['employe_id'] : null,
                'created_by' => $_SESSION['user_id']
            ];
            
            if ($calendrierController->createEvent($eventData)) {
                echo json_encode(['success' => true, 'message' => 'Événement créé avec succès']);
            } else {
                throw new Exception('Erreur lors de la création de l\'événement');
            }
            break;

        case 'update_event':
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Non autorisé');
            }
            
            $eventId = $input['id'];
            $eventData = [
                'titre' => $input['titre'],
                'type' => $input['type'],
                'description' => $input['description'] ?? '',
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'employe_id' => !empty($input['employe_id']) ? $input['employe_id'] : null
            ];
            
            if ($calendrierController->updateEvent($eventId, $eventData)) {
                echo json_encode(['success' => true, 'message' => 'Événement mis à jour avec succès']);
            } else {
                throw new Exception('Erreur lors de la mise à jour de l\'événement');
            }
            break;

        case 'delete_event':
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Non autorisé');
            }
            
            $eventId = $input['id'];
            
            if ($calendrierController->deleteEvent($eventId)) {
                echo json_encode(['success' => true, 'message' => 'Événement supprimé avec succès']);
            } else {
                throw new Exception('Erreur lors de la suppression de l\'événement');
            }
            break;

        case 'get_event':
            $eventId = $input['id'];
            $event = $calendrierController->getEventById($eventId);
            
            if ($event) {
                echo json_encode(['success' => true, 'event' => $event]);
            } else {
                throw new Exception('Événement non trouvé');
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

// Fonction utilitaire pour les couleurs des événements
function getEventColor($type) {
    $colors = [
        'reunion' => '#007bff',
        'congé' => '#dc3545', 
        'formation' => '#ffc107',
        'autre' => '#6c757d'
    ];
    return $colors[$type] ?? '#6c757d';
}
?>