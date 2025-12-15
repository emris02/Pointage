<?php
/**
 * API: Création d'une demande pour un employé
 * POST: employe_id, type, motif, description (opt), priorite (opt), date_debut (opt), date_fin (opt), commentaire_interne (opt)
 * Réponse JSON par défaut; si redirect est fourni, redirige vers l'URL donnée
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';
require_once __DIR__ . '/../src/controllers/DemandeController.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

// Autorisation: admin ou super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    if (!empty($_POST['redirect'])) {
        header('Location: ' . $_POST['redirect']);
        exit();
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit();
}

// Récupération inputs
$employeId = isset($_POST['employe_id']) ? (int)$_POST['employe_id'] : 0;
$type = trim($_POST['type'] ?? '');
$motif = trim($_POST['motif'] ?? '');
$description = trim($_POST['description'] ?? '');
$priorite = trim($_POST['priorite'] ?? 'medium');
$dateDebut = $_POST['date_debut'] ?? null;
$dateFin = $_POST['date_fin'] ?? null;
$commentaireInterne = trim($_POST['commentaire_interne'] ?? '');
$redirect = $_POST['redirect'] ?? '';

$errors = [];
if ($employeId <= 0) $errors[] = 'Employé invalide';
if ($type === '') $errors[] = 'Type requis';
if ($motif === '') $errors[] = 'Motif requis';

if (!empty($errors)) {
    if ($redirect) {
        $_SESSION['error_message'] = implode(' | ', $errors);
        header('Location: ' . $redirect);
        exit();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
    exit();
}

try {
    $controller = new DemandeController($pdo);
    $demandeId = $controller->create([
        'employe_id' => $employeId,
        'type' => $type,
        'motif' => $motif,
        'description' => $description,
        'priorite' => $priorite,
        'date_debut' => $dateDebut ?: null,
        'date_fin' => $dateFin ?: null,
        'commentaire_interne' => $commentaireInterne ?: null,
    ]);

    if ($redirect) {
        if ($demandeId) {
            $_SESSION['success_message'] = 'Demande créée avec succès (#' . $demandeId . ').';
        } else {
            $_SESSION['error_message'] = 'Erreur lors de la création de la demande.';
        }
        header('Location: ' . $redirect);
        exit();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => (bool)$demandeId, 'id' => $demandeId]);
} catch (Throwable $e) {
    if ($redirect) {
        $_SESSION['error_message'] = 'Erreur: ' . $e->getMessage();
        header('Location: ' . $redirect);
        exit();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>


