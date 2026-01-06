<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
require_once __DIR__ . '/../src/controllers/NotificationController.php';

$nc = new NotificationController($pdo);

$testEmploye = 1; // ensure this exist in your dev DB

echo "Count unread for employe {$testEmploye}: ";
var_dump($nc->countUnread($testEmploye));

echo "Creating test notification...\n";
$ok = $nc->create([
    'employe_id' => $testEmploye,
    'titre' => 'Test Notification',
    'message' => 'This is a test from unit test',
    'type' => 'info',
    'lien' => 'notifications.php'
]);
var_dump($ok);

if ($ok) {
    $items = $nc->getByEmploye($testEmploye, 5);
    echo "Last items:\n";
    print_r($items);
    $id = $items[0]['id'] ?? null;
    if ($id) {
        echo "Marking as read id={$id}...\n";
        var_dump($nc->markAsRead($id, $testEmploye));
        echo "Deleting id={$id}...\n";
        var_dump($nc->delete($id, $testEmploye));
    }
}
