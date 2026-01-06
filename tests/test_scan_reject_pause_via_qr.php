<?php
// Test simple: QR with pause type should be rejected immediately with NO_PAUSE_VIA_QR
$url = 'http://localhost/pointage/api/scan_qr.php';
$data = json_encode(['badge_data' => 'TEST-FAKE', 'type' => 'pause_debut']);

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data,
        'ignore_errors' => true
    ]
];
$context = stream_context_create($opts);
$result = file_get_contents($url, false, $context);
$info = $http_response_header[0] ?? '';
$status = 0;
if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $info, $m)) $status = intval($m[1]);

echo "HTTP STATUS: $status\n";
echo "RESPONSE: $result\n";

$ok = $status === 400 && strpos($result, 'NO_PAUSE_VIA_QR') !== false;
if ($ok) {
    echo "TEST PASSED: pause QR rejected.\n";
    exit(0);
} else {
    echo "TEST FAILED\n";
    exit(2);
}
