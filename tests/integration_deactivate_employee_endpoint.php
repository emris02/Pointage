<?php
// Integration test: login as admin via HTTP and call deactivate_employe.php as AJAX
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/models/Admin.php';
require_once __DIR__ . '/../src/models/Employe.php';

$adminModel = new Admin($pdo);
$employeModel = new Employe($pdo);

// Create admin user
$email = 'integration.admin+' . rand(1000,9999) . '@example.com';
$adminId = $adminModel->create([
    'nom' => 'Integration', 'prenom' => 'Admin', 'email' => $email, 'password' => 'Password123!', 'role' => ROLE_ADMIN, 'statut' => 'actif'
]);
if (!$adminId) { echo "Failed to create admin\n"; exit(1); }

// Create employee
$empId = $employeModel->create(['nom' => 'ToDeactivate', 'prenom' => 'Emp', 'email' => 'integration.emp+' . rand(1000,9999) . '@example.com', 'password' => 'Password123!']);
if (!$empId) { echo "Failed to create employee\n"; exit(1); }

// Prepare cookie jar
$cookieJar = sys_get_temp_dir() . '/test_cookies_' . uniqid() . '.txt';

// 1) Login via POST to login.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/pointage/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $email, 'password' => 'Password123!']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
// store cookies
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 400) {
    echo "Login failed (HTTP $httpCode)\n"; exit(1);
}

echo "Login finished, HTTP $httpCode\n";

// 2) Call deactivate_employe.php as AJAX
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/pointage/deactivate_employe.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['employe_id' => $empId]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// send ajax header and cookies
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Deactivate response (HTTP $httpCode): $response\n";

// Basic assert: response should be JSON with success=true
$json = json_decode($response, true);
if (!is_array($json)) {
    echo "Response is not JSON\n";
} else {
    if (!empty($json['success'])) {
        echo "Success: deactivate endpoint returned success=true\n";
    } else {
        echo "Endpoint returned error: " . ($json['message'] ?? 'no message') . "\n";
    }
}

// 3) Reactivate via endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/pointage/activate_employe.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['employe_id' => $empId]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// send ajax header and cookies
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Activate response (HTTP $httpCode): $response\n";
$json = json_decode($response, true);
if (!is_array($json)) {
    echo "Activate response not JSON\n";
} else {
    if (!empty($json['success'])) {
        echo "Success: activate endpoint returned success=true\n";
    } else {
        echo "Activate endpoint returned error: " . ($json['message'] ?? 'no message') . "\n";
    }
}

// Cleanup
$adminModel->delete((int)$adminId);
$employeModel->delete((int)$empId);
@unlink($cookieJar);

echo "Integration test finished\n";