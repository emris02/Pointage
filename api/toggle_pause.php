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

// Support GET => status check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT p1.id, COALESCE(p1.date_heure, p1.date_pointage) as date_heure, p1.type_justification
            FROM pointages p1
            WHERE p1.employe_id = :emp
              AND p1.type = 'pause_debut'
              AND DATE(COALESCE(p1.date_heure, p1.date_pointage)) = DATE(NOW())
              AND NOT EXISTS (
                  SELECT 1 FROM pointages p2
                  WHERE p2.employe_id = p1.employe_id
                    AND p2.type = 'pause_fin'
                    AND DATE(COALESCE(p2.date_heure, p2.date_pointage)) = DATE(COALESCE(p1.date_heure, p1.date_pointage))
                    AND COALESCE(p2.date_heure, p2.date_pointage) > COALESCE(p1.date_heure, p1.date_pointage)
              )
            ORDER BY COALESCE(p1.date_heure, p1.date_pointage) DESC
            LIMIT 1");
        $stmt->execute([':emp' => $employeId]);
        $openPause = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($openPause) {
            echo json_encode(['success' => true, 'active' => true, 'pause_id' => $openPause['id'], 'started_at' => $openPause['date_heure'], 'pause_type' => $openPause['type_justification'] ?? null]);
            exit();
        }
        echo json_encode(['success' => true, 'active' => false]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
        exit();
    }
}

$date = date('Y-m-d');
try {
    // Chercher un 'pause_debut' aujourd'hui sans 'pause_fin' associé (dernier non clôturé)
    $stmt = $pdo->prepare("SELECT p1.id, COALESCE(p1.date_heure, p1.date_pointage) as date_heure
        FROM pointages p1
        WHERE p1.employe_id = :emp
          AND p1.type = 'pause_debut'
          AND DATE(COALESCE(p1.date_heure, p1.date_pointage)) = :date
          AND NOT EXISTS (
              SELECT 1 FROM pointages p2
              WHERE p2.employe_id = p1.employe_id
                AND p2.type = 'pause_fin'
                AND DATE(COALESCE(p2.date_heure, p2.date_pointage)) = DATE(COALESCE(p1.date_heure, p1.date_pointage))
                AND COALESCE(p2.date_heure, p2.date_pointage) > COALESCE(p1.date_heure, p1.date_pointage)
          )
        ORDER BY COALESCE(p1.date_heure, p1.date_pointage) DESC
        LIMIT 1");
    $stmt->execute([':emp' => $employeId, ':date' => $date]);
    $openPause = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = date('Y-m-d H:i:s');
    if ($openPause) {
        // Close pause
        // Allow optional justification (reason text / file) when ending pause
        $justification = trim($_POST['justification'] ?? '');
        $filePath = null;
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/justifications/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
            $dest = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
                $filePath = 'uploads/justifications/' . $fileName;
            }
        }

        $stmtIns = $pdo->prepare("INSERT INTO pointages (employe_id, type, date_heure, etat, statut) VALUES (:emp, 'pause_fin', :now, 'pause', 'présent')");
        $stmtIns->execute([':emp' => $employeId, ':now' => $now]);
        $pauseId = $pdo->lastInsertId();

        // calculer durée
        $startTs = strtotime($openPause['date_heure']);
        $endTs = strtotime($now);
        $durationMinutes = max(0, intval(($endTs - $startTs) / 60));

        // If justification provided, insert into retards table (reusing schema for justifications)
        if (!empty($justification) || $filePath) {
            $stmtR = $pdo->prepare("INSERT INTO retards (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())");
            $stmtR->execute([$openPause['id'], $employeId, 'pause_fin', $justification, $filePath]);
            // mark pointage as having submitted justification
            $stmtUp = $pdo->prepare("UPDATE pointages SET est_justifie = 0, type_justification = 'pause' WHERE id = ?");
            $stmtUp->execute([$openPause['id']]);
        }

        echo json_encode([
            'success' => true,
            'action' => 'pause_ended',
            'pause_id' => $pauseId,
            'duration_minutes' => $durationMinutes,
            'message' => 'Pause terminée',
            'ended_at' => $now
        ]);
        exit();
    } else {
        // Start pause
        // Accept pause_type (déjeuner, course, fatigue, malaise, commission, autre)
        $allowed = ['dejeuner','course','fatigue','malaise','commission','autre'];
        $pauseType = strtolower(trim($_POST['pause_type'] ?? ''));
        if (empty($pauseType) || !in_array($pauseType, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Veuillez indiquer le type de pause valide (ex: dejeuner, course, fatigue, malaise, commission, autre)']);
            exit();
        }

        $justification = trim($_POST['justification'] ?? '');
        $filePath = null;
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/justifications/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
            $dest = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
                $filePath = 'uploads/justifications/' . $fileName;
            }
        }

        $stmtIns = $pdo->prepare("INSERT INTO pointages (employe_id, type, date_heure, etat, statut, type_justification, commentaire) VALUES (:emp, 'pause_debut', :now, 'pause', 'présent', :type_justification, :commentaire)");
        $stmtIns->execute([':emp' => $employeId, ':now' => $now, ':type_justification' => $pauseType, ':commentaire' => $justification]);
        $pauseId = $pdo->lastInsertId();

        // If justification provided, record it in retards table for review
        if (!empty($justification) || $filePath) {
            $stmtR = $pdo->prepare("INSERT INTO retards (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())");
            $stmtR->execute([$pauseId, $employeId, $pauseType, $justification, $filePath]);
            // Mark the pointage as having a pending justification
            $stmtUp = $pdo->prepare("UPDATE pointages SET est_justifie = 0 WHERE id = ?");
            $stmtUp->execute([$pauseId]);
        }

        echo json_encode([
            'success' => true,
            'action' => 'pause_started',
            'pause_id' => $pauseId,
            'message' => 'Pause commencée',
            'started_at' => $now,
            'pause_type' => $pauseType
        ]);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    exit();
}
