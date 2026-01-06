<?php
// Simple HTTP POST to the login page to test session persistence
$url = 'http://localhost/pointage/login.php';
$data = http_build_query([
    'email' => 'ouologuemoussa@gmail.com',
    'password' => 'Secret123!'
]);
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 5
    ],
];
$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);
$meta = isset($http_response_header) ? $http_response_header : [];

echo "HTTP headers:\n" . implode("\n", $meta) . "\n\n";
if ($response === false) {
    echo "No response or error fetching page.\n";
} else {
    echo "Response length: " . strlen($response) . " bytes\n";
    // show a snippet
    echo substr($response, 0, 800);
}
