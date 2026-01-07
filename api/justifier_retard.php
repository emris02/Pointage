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
    // Vérifier que le pointage existe
    $stmt = $pdo->prepare('SELECT id, employe_id, type FROM pointages WHERE id = ?');
    $stmt->execute([$pointageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pointage not found']);
        exit();
    }

    // On ne justifie que les arrivées
    if (strtolower($row['type']) !== 'arrivee') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Justification only allowed for arrival pointages']);
        exit();
    }

    $employeId = (int)$row['employe_id'];

    // Gestion du fichier uploadé si présent
    $fichierPath = null;
    if (!empty($_FILES['piece_jointe']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../uploads/justificatifs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = basename($_FILES['piece_jointe']['name']);
        $targetPath = $uploadDir . time() . '_' . $filename;

        if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $targetPath)) {
            $fichierPath = $targetPath;
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            exit();
        }
    }

    // Insérer la justification
    $stmt = $pdo->prepare('INSERT INTO retards_justifies (pointage_id, employe_id, raison, details, piece_jointe, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$pointageId, $employeId, $raison, $details, $fichierPath]);

    $retardId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'retard_id' => $retardId,
        'message' => 'Justification saved'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
