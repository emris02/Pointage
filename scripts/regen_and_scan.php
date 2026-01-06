<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/services/BadgeManager.php';

$empId = 21;
$regen = BadgeManager::regenerateToken($empId, $pdo);
if (empty($regen['token'])) {
    echo "Failed to generate token\n";
    exit(1);
}
$token = $regen['token'];

// Post to API
$url = 'http://localhost/pointage/api/scan_qr.php';
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/json\r\n",
        'content' => json_encode(['badge_data' => $token]),
        'timeout' => 10
    ]
];
$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "TOKEN:\n" . $token . "\n\nRESPONSE:\n" . $result . "\n"; 
