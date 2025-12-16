<?php
/**
 * API: justifier_retard.php
 * Reçoit une requête POST pour justifier un retard.
 * Authentification via header 'X-Terminal-Key' ou param POST 'api_key' égal à TERMINAL_API_KEY.
 * Retourne JSON. Ne dépend pas de la session.
 */
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/config/constants.php';

header('Content-Type: application/json; charset=utf-8');

// Auth: check header or POST key
$providedKey = $_SERVER['HTTP_X_TERMINAL_KEY'] ?? $_POST['api_key'] ?? null;
if (empty($providedKey) || $providedKey !== TERMINAL_API_KEY) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: invalid API key']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed, use POST']);
    exit();
}

$pointageId = isset($_POST['pointage_id']) ? (int)$_POST['pointage_id'] : 0;
$raison = trim($_POST['raison'] ?? '');
$details = trim($_POST['details'] ?? '');

if ($pointageId <= 0 || empty($raison)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters: pointage_id and raison']);
    exit();
}

try {
    // Verify that pointage exists and get employe_id
    $stmt = $pdo->prepare('SELECT id, employe_id, type FROM pointages WHERE id = ?');
    $stmt->execute([$pointageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pointage not found']);
        exit();
    }

    // Ensure we justify only an 'arrivee'
    if (strtolower($row['type']) !== 'arrivee') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Justification only allowed for arrival pointages']);
        exit();
    }

    $employeId = (int)$row['employe_id'];

    // Handle file upload if present ('piece_jointe')
    $fichierPath = null;
    if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/justifications/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
        $dest = $uploadDir . $fileName;
        if (!move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
            // Move failed but we continue; log
            error_log('justifier_retard: failed to move uploaded file');
        } else {
            $fichierPath = 'uploads/justifications/' . $fileName;
        }
    }

    // Insert into retards
    $stmt = $pdo->prepare("INSERT INTO retards (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())");
    $stmt->execute([$pointageId, $employeId, $raison, $details, $fichierPath]);
    $insertedId = $pdo->lastInsertId();

    // Log and respond
    error_log('[API] Retard justifié via API: pointage=' . $pointageId . ' employe=' . $employeId . ' raison=' . $raison);

    echo json_encode(['success' => true, 'message' => 'Justification saved', 'retard_id' => $insertedId]);
    exit();

} catch (Exception $e) {
    error_log('justifier_retard error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit();
}
