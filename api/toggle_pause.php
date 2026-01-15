<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/AuthService.php';

use Pointage\Services\AuthService;

try {
    AuthService::requireAuth();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$employeId = $_SESSION['employe_id'] ?? null;
if (!$employeId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employé non identifié']);
    exit();
}

$now = date('Y-m-d H:i:s');

// --- 1️⃣ Vérifier si une pause est ouverte ---
$stmt = $pdo->prepare("
    SELECT * FROM pauses 
    WHERE employe_id = :emp 
      AND date_fin IS NULL 
    ORDER BY date_debut DESC 
    LIMIT 1
");
$stmt->execute([':emp' => $employeId]);
$openPause = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 2️⃣ Gestion GET (status check) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($openPause) {
        echo json_encode([
            'success' => true,
            'active' => true,
            'pause_id' => $openPause['id'],
            'started_at' => $openPause['date_debut'],
            'pause_type' => $openPause['type_pause']
        ]);
    } else {
        echo json_encode(['success' => true, 'active' => false]);
    }
    exit();
}

// --- 3️⃣ Gestion POST (toggle pause) ---
$allowed = ['dejeuner','course','fatigue','malaise','commission','autre'];
$pauseType = strtolower(trim($_POST['pause_type'] ?? ''));
$justification = trim($_POST['justification'] ?? '');
$filePath = null;

// --- 3a️⃣ Gérer fichier justificatif ---
if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/justifications/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $fileName = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
    $dest = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
        $filePath = 'uploads/justifications/' . $fileName;
    }
}

if ($openPause) {
    // --- 4️⃣ Fin de la pause ---
    $startTs = strtotime($openPause['date_debut']);
    $endTs = strtotime($now);
    $durationMinutes = max(0, intval(($endTs - $startTs) / 60));

    // Mettre à jour la pause
    $stmtUpd = $pdo->prepare("
        UPDATE pauses 
        SET date_fin = :fin, duree_minutes = :duree, justification = :justif, fichier_justificatif = :file
        WHERE id = :id
    ");
    $stmtUpd->execute([
        ':fin' => $now,
        ':duree' => $durationMinutes,
        ':justif' => $justification,
        ':file' => $filePath,
        ':id' => $openPause['id']
    ]);

    // Créer un pointage de fin de pause (pour affichage dans pointages)
    $stmtIns = $pdo->prepare("
        INSERT INTO pointages (employe_id, type, date_heure, etat, statut)
        VALUES (:emp, 'pause_fin', :now, 'pause', 'présent')
    ");
    $stmtIns->execute([':emp' => $employeId, ':now' => $now]);
    $pointageId = $pdo->lastInsertId();

    // Enregistrer dans retards si justification fournie
    if (!empty($justification) || $filePath) {
        $stmtR = $pdo->prepare("
            INSERT INTO retards (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission, type_pointage, est_visible_in_pointage)
            VALUES (:pid, :emp, 'pause_fin', :details, :file, 'en_attente', NOW(), 'arrivee', 1)
        ");
        $stmtR->execute([
            ':pid' => $pointageId,
            ':emp' => $employeId,
            ':details' => $justification,
            ':file' => $filePath
        ]);
    }

    echo json_encode([
        'success' => true,
        'action' => 'pause_ended',
        'pause_id' => $openPause['id'],
        'duration_minutes' => $durationMinutes,
        'ended_at' => $now
    ]);
    exit();
} else {
    // --- 5️⃣ Début de la pause ---
    if (empty($pauseType) || !in_array($pauseType, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Veuillez indiquer le type de pause valide']);
        exit();
    }

    $stmtIns = $pdo->prepare("
        INSERT INTO pauses (employe_id, type_pause, date_debut, justification, fichier_justificatif)
        VALUES (:emp, :type_pause, :now, :justif, :file)
    ");
    $stmtIns->execute([
        ':emp' => $employeId,
        ':type_pause' => $pauseType,
        ':now' => $now,
        ':justif' => $justification,
        ':file' => $filePath
    ]);
    $pauseId = $pdo->lastInsertId();

    // Créer un pointage de début de pause pour l'affichage
    $stmtPoint = $pdo->prepare("
        INSERT INTO pointages (employe_id, type, date_heure, etat, statut)
        VALUES (:emp, 'pause_debut', :now, 'pause', 'présent')
    ");
    $stmtPoint->execute([':emp' => $employeId, ':now' => $now]);
    $pointageId = $pdo->lastInsertId();

    // Enregistrer dans retards si justification fournie
    if (!empty($justification) || $filePath) {
        $stmtR = $pdo->prepare("
            INSERT INTO retards (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission, type_pointage, est_visible_in_pointage)
            VALUES (:pid, :emp, :type_pause, :details, :file, 'en_attente', NOW(), 'arrivee', 1)
        ");
        $stmtR->execute([
            ':pid' => $pointageId,
            ':emp' => $employeId,
            ':type_pause' => $pauseType,
            ':details' => $justification,
            ':file' => $filePath
        ]);
    }

    echo json_encode([
        'success' => true,
        'action' => 'pause_started',
        'pause_id' => $pauseId,
        'started_at' => $now,
        'pause_type' => $pauseType
    ]);
    exit();
}
?>
