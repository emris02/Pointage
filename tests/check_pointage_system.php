<?php
require_once __DIR__ . '/../src/config/bootstrap.php';

header('Content-Type: text/plain');

echo "Running quick diagnostics...\n";

try {
    // 1) Monthly aggregates (last 6 months)
    $startDate = date('Y-m-01', strtotime('-5 months'));
    $monthlySql = "SELECT DATE_FORMAT(COALESCE(date_heure,date_pointage),'%Y-%m') as ym, SUM(type='arrivee') as arrivals, SUM(type='depart') as departures FROM pointages WHERE DATE(COALESCE(date_heure,date_pointage)) >= :start GROUP BY ym";
    $stmt = $pdo->prepare($monthlySql);
    $stmt->execute(['start' => $startDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Monthly aggregates since $startDate:\n";
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // 2) Today's etat counts
    $today = date('Y-m-d');
    $etatSql = "SELECT 
        SUM(CASE WHEN type='arrivee' AND etat = 'normal' THEN 1 ELSE 0 END) as normal_arrives,
        SUM(CASE WHEN type='arrivee' AND etat = 'justifie' THEN 1 ELSE 0 END) as justifie_arrives,
        SUM(CASE WHEN type='arrivee' AND TIME(COALESCE(date_heure,date_pointage)) > '09:00:00' THEN 1 ELSE 0 END) as late_arrives
    FROM pointages
    WHERE DATE(COALESCE(date_heure,date_pointage)) = :today";
    $stmt = $pdo->prepare($etatSql);
    $stmt->execute(['today' => $today]);
    $etatRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['normal_arrives'=>0,'justifie_arrives'=>0,'late_arrives'=>0];
    echo "Today's etat counts ({$today}):\n" . json_encode($etatRow) . "\n\n";

    // 3) Insert a temporary notification and read it back (to validate notification table write/read)
    $title = 'TEST_NOTIFICATION_AUTOTEST_' . time();
    $stmtIns = $pdo->prepare("INSERT INTO notifications (employe_id, titre, contenu, message, type, lien, lue, date_creation, date) VALUES (0, ?, ?, ?, 'test', '', 0, NOW(), NOW())");
    $stmtIns->execute([$title, 'Contenu test', 'Message test']);
    $insertedId = $pdo->lastInsertId();
    echo "Inserted test notification id: $insertedId\n";

    $stmtSel = $pdo->prepare("SELECT id, titre, contenu, type, lue, date_creation FROM notifications WHERE id = ?");
    $stmtSel->execute([$insertedId]);
    $notif = $stmtSel->fetch(PDO::FETCH_ASSOC);
    echo "Notification readback:\n" . json_encode($notif) . "\n\n";

    // Clean up the test notification
    $stmtDel = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmtDel->execute([$insertedId]);
    echo "Test notification deleted.\n\n";

    // 4) Test the work time calculation (private method via reflection)
    require_once __DIR__ . '/../api/pointage.php';
    $ps = new PointageSystem($pdo);
    $class = new ReflectionClass($ps);
    $method = $class->getMethod('calculerTempsTravail');
    $method->setAccessible(true);

    $start = date('Y-m-d H:i:s', strtotime('today 09:00:00'));
    $end = date('Y-m-d H:i:s', strtotime('today 17:30:00'));
    $times = $method->invokeArgs($ps, [$start, $end]);
    echo "Work time calculation for $start -> $end:\n" . json_encode($times) . "\n\n";

    echo "Diagnostics completed successfully.\n";
    exit(0);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}