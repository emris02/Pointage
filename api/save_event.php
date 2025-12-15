<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO evenements (titre, description, start_date, end_date, type, employe_id)
                               VALUES (:titre, :description, :start_date, :end_date, :type, :employe_id)");
        $stmt->execute([
            ':titre' => $input['titre'] ?? '',
            ':description' => $input['description'] ?? '',
            ':start_date' => $input['start_date'],
            ':end_date' => $input['end_date'],
            ':type' => $input['type'] ?? 'autre',
            ':employe_id' => !empty($input['employe_id']) ? (int)$input['employe_id'] : null
        ]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit();
    }

    if ($action === 'update') {
        // update souple: si titre existe, on met à jour tout; sinon on ne touche qu'aux dates
        if (!empty($input['titre'])) {
            $stmt = $pdo->prepare("UPDATE evenements SET titre=:titre, description=:description, start_date=:start_date, end_date=:end_date, type=:type, employe_id=:employe_id WHERE id=:id");
            $stmt->execute([
                ':id' => (int)$input['id'],
                ':titre' => $input['titre'],
                ':description' => $input['description'] ?? '',
                ':start_date' => $input['start_date'],
                ':end_date' => $input['end_date'],
                ':type' => $input['type'] ?? 'autre',
                ':employe_id' => !empty($input['employe_id']) ? (int)$input['employe_id'] : null
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE evenements SET start_date=:start_date, end_date=:end_date WHERE id=:id");
            $stmt->execute([
                ':id' => (int)$input['id'],
                ':start_date' => $input['start_date'],
                ':end_date' => $input['end_date']
            ]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM evenements WHERE id = :id");
        $stmt->execute([':id' => (int)$input['id']]);
        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Action invalide']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


