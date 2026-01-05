<?php
require __DIR__ . '/../src/config/bootstrap.php';

// Use current date as reference
$date = date('Y-m-d');

function callApi($params) {
    $_GET = $params;
    ob_start();
    include __DIR__ . '/../api/get_pointages.php';
    $out = ob_get_clean();
    return $out;
}

echo "Testing day range...\n";
$out = callApi(['date' => $date, 'range' => 'day']);
$res = json_decode($out, true);
if (!$res || !isset($res['success'])) {
    echo "FAIL: invalid response for day\n";
    echo $out . "\n";
    exit(1);
}
echo "Day result: success=" . ($res['success'] ? 'true' : 'false') . " — pointages=" . count($res['pointages']) . "\n";

echo "Testing week range...\n";
$out = callApi(['date' => $date, 'range' => 'week']);
$res = json_decode($out, true);
if (!$res || !isset($res['success'])) {
    echo "FAIL: invalid response for week\n";
    echo $out . "\n";
    exit(1);
}
echo "Week result: success=" . ($res['success'] ? 'true' : 'false') . " — pointages=" . count($res['pointages']) . "\n";

echo "Testing month range...\n";
$out = callApi(['date' => $date, 'range' => 'month']);
$res = json_decode($out, true);
if (!$res || !isset($res['success'])) {
    echo "FAIL: invalid response for month\n";
    echo $out . "\n";
    exit(1);
}
echo "Month result: success=" . ($res['success'] ? 'true' : 'false') . " — pointages=" . count($res['pointages']) . "\n";

exit(0);
