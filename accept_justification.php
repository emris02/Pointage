<?php
// Temporary test endpoint to accept justification-like POSTs and log them for local testing
$debugData = [
    'timestamp' => date('c'),
    'post' => $_POST,
    'files' => array_keys($_FILES),
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'cli'
];
try {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logDir . '/badge_system.log', json_encode($debugData) . PHP_EOL, FILE_APPEND | LOCK_EX);
} catch (Throwable $e) {
    error_log('Failed to write test accept_justification log: ' . $e->getMessage());
}
header('Content-Type: application/json');
echo json_encode(['success' => true, 'received' => $debugData]);
