<?php
require __DIR__ . '/../src/config/bootstrap.php';
require __DIR__ . '/../src/services/BadgeManager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query('SELECT id, nom, prenom FROM admins LIMIT 1');
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        echo json_encode(['error' => 'NO_ADMIN']);
        exit(2);
    }

    $res = BadgeManager::regenerateTokenForAdmin((int)$admin['id'], $pdo);
    $token = $res['token'];

    // Forcer suppression des pointages admin du jour pour permettre un nouveau test (environnement de test uniquement)
    try {
        $stmtDel = $pdo->prepare("DELETE FROM pointages WHERE admin_id = ? AND DATE(date_heure) = DATE(NOW())");
        $stmtDel->execute([(int)$admin['id']]);
    } catch (Throwable $e) {
        // ignore
    }

    $url = 'http://localhost/pointage/api/scan_qr.php';

    // 1) Envoi d'une arrivée admin
    $payloadArr = ['badge_data' => $token, 'type' => 'arrivee', 'device_info' => ['test' => true]];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadArr));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resArr = curl_exec($ch);
    $codeArr = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // 2) Envoi d'un départ admin
    $payloadDep = ['badge_data' => $token, 'type' => 'depart', 'device_info' => ['test' => true]];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadDep));

    $resDep = curl_exec($ch);
    $codeDep = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    echo json_encode([
        'admin' => $admin,
        'token' => $token,
        'arrivee' => ['http_code' => $codeArr, 'body' => json_decode($resArr, true)],
        'depart' => ['http_code' => $codeDep, 'body' => json_decode($resDep, true)],
    ]);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
