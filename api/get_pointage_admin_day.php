<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

// Params: admin_id (int required), date (YYYY-MM-DD required)
$adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : null;
$date = $_GET['date'] ?? null;

if (!$adminId || !$date) {
    echo json_encode(['success' => false, 'message' => 'admin_id et date sont requis']);
    exit();
}

try {
    $sql = "SELECT 
                MIN(COALESCE(p.date_heure, p.date_pointage)) as arrivee_dt,
                MAX(COALESCE(p.date_heure, p.date_pointage)) as depart_dt
            FROM pointages p
            WHERE p.admin_id = :admin_id
              AND DATE(COALESCE(p.date_heure, p.date_pointage)) = :date
              AND p.type IN ('arrivee','depart')";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':date', $date);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $arrivee = $row['arrivee_dt'] ? date('H:i', strtotime($row['arrivee_dt'])) : null;
    $depart = $row['depart_dt'] ? date('H:i', strtotime($row['depart_dt'])) : null;

    echo json_encode([
        'success' => true,
        'date' => $date,
        'admin_id' => $adminId,
        'arrivee' => $arrivee,
        'depart' => $depart
    ]);
    exit();
} catch (Exception $e) {
    error_log('api/get_pointage_admin_day.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    exit();
}
