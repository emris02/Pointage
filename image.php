<?php
// Simple image proxy to serve files from uploads safely
if (!isset($_GET['f'])) {
    http_response_code(400);
    exit('Missing file');
}

$file = basename(urldecode($_GET['f']));

// Candidate directories to search (in order)
$candidates = [
    __DIR__ . '/assets/img/uploads/employes/',
    __DIR__ . '/assets/img/uploads/',
    __DIR__ . '/uploads/employes/',
    // __DIR__ . '/uploads/',
    __DIR__ . '/assets/img/',
];

$path = null;
foreach ($candidates as $d) {
    $p = rtrim($d, '/\\') . DIRECTORY_SEPARATOR . $file;
    if (file_exists($p) && is_readable($p)) {
        $path = $p;
        break;
    }
}

if (!$path) {
    http_response_code(404);
    exit('Not found');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
