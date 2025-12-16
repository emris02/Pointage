<?php
// Simple test harness to simulate a justification AJAX POST to employe_dashboard.php
// Run this script from the browser or CLI (via PHP built-in server). It performs a POST using cURL.

$target = 'http://localhost/pointage/employe_dashboard.php';

$ch = curl_init($target);

$post = [
    'employe_id' => 1,
    'pointage_id' => 123,
    'date' => date('Y-m-d H:i:s'),
    'raison' => 'transport',
    'details' => 'Test de justification via simulate_justification_post',
    'submit_justification' => '1'
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

$result = curl_exec($ch);
$info = curl_getinfo($ch);
if (curl_errno($ch)) {
    echo 'CURL Error: ' . curl_error($ch);
} else {
    echo "HTTP Status: " . $info['http_code'] . "\n";
    echo "Response:\n" . $result;
}
curl_close($ch);

// Also print out any recent logs containing DEBUG keyword
$logFile = __DIR__ . '/../logs/badge_system.log';
if (file_exists($logFile)) {
    echo "\n--- Last log lines ---\n";
    $lines = array_slice(file($logFile), -50);
    echo implode('', $lines);
} else {
    echo "\nNo log file found at $logFile\n";
}
