<?php
// Integration test for processPointageFromArray
require_once __DIR__ . '/../src/models/Pointage.php';
require_once __DIR__ . '/../src/controllers/PointageController.php';

// Setup in-memory SQLite and create table with location columns
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE pointages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employe_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    date_heure DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address TEXT,
    device_info TEXT,
    badge_token_id INTEGER,
    lat DOUBLE NULL,
    lon DOUBLE NULL,
    location_precision DOUBLE NULL,
    location_source VARCHAR(16) NULL,
    location_address VARCHAR(255) NULL
)");

// Simple TestBadge stub class
class TestBadgeStub {
    public function verifyToken($token) {
        // Return consistent token data expected by controller
        return [
            'employe_id' => 7,
            'badge_token_id' => 123,
            'prenom' => 'Test',
            'nom' => 'User'
        ];
    }
}

$controller = new PointageController($pdo);
// Inject stub
$controller->badgeModel = new TestBadgeStub();

// 1) First arrival request should succeed
$payload = ['token' => 'FAKE-TOKEN-1', 'location' => ['lat' => 48.85, 'lon' => 2.35, 'precision' => 5]];
$res1 = $controller->processPointageFromArray($payload);
if (!$res1['success']) {
    echo "Test failed: first arrival should succeed.\n";
    var_export($res1);
    exit(1);
}

// 2) Second arrival same day should be rejected
$res2 = $controller->processPointageFromArray($payload);
if ($res2['success'] !== false || ($res2['code'] ?? '') !== 'already_pointed_today') {
    echo "Test failed: second arrival should be rejected.\n";
    var_export($res2);
    exit(2);
}

echo "Integration tests passed\n";
exit(0);
