<?php
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';

use Pointage\Services\AuthService;

header('Content-Type: application/json; charset=utf-8');

AuthService::requireAuth();

session_start();

$current_admin_id = (int)($_SESSION['admin_id'] ?? 0);

$action = $_POST['action'] ?? ($_GET['action'] ?? 'get');
$admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : (isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0);

if (!$admin_id) {
    echo json_encode(['status' => 'error', 'message' => 'admin_id missing']);
    exit();
}

// Only allow managing the timer for the current logged-in admin
if ($admin_id !== $current_admin_id) {
    echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    exit();
}

if (!isset($_SESSION['persistent_timers'])) $_SESSION['persistent_timers'] = [];

$now = time();

switch ($action) {
    case 'start':
        // Start timer (if already running, keep original start)
        if (empty($_SESSION['persistent_timers'][$admin_id]) || $_SESSION['persistent_timers'][$admin_id]['status'] !== 'running') {
            $_SESSION['persistent_timers'][$admin_id] = [
                'status' => 'running',
                'started_at' => $now,
                'elapsed_before' => $_SESSION['persistent_timers'][$admin_id]['elapsed_before'] ?? 0,
                'last_heartbeat' => $now
            ];
        } else {
            // update heartbeat
            $_SESSION['persistent_timers'][$admin_id]['last_heartbeat'] = $now;
        }

        echo json_encode(['status' => 'success', 'data' => ['status' => 'running', 'started_at' => $_SESSION['persistent_timers'][$admin_id]['started_at'], 'elapsed_before' => $_SESSION['persistent_timers'][$admin_id]['elapsed_before']]]);
        exit();
    case 'stop':
        // Stop timer and compute elapsed
        if (isset($_SESSION['persistent_timers'][$admin_id]) && $_SESSION['persistent_timers'][$admin_id]['status'] === 'running') {
            $started = $_SESSION['persistent_timers'][$admin_id]['started_at'];
            $prev = $_SESSION['persistent_timers'][$admin_id]['elapsed_before'] ?? 0;
            $elapsed = ($now - $started) + $prev;
            $_SESSION['persistent_timers'][$admin_id]['status'] = 'stopped';
            $_SESSION['persistent_timers'][$admin_id]['stopped_at'] = $now;
            $_SESSION['persistent_timers'][$admin_id]['elapsed_before'] = $elapsed;
            $_SESSION['persistent_timers'][$admin_id]['last_heartbeat'] = $now;

            echo json_encode(['status' => 'success', 'data' => ['status' => 'stopped', 'elapsed_before' => $elapsed]]);
            exit();
        } else {
            $prev = $_SESSION['persistent_timers'][$admin_id]['elapsed_before'] ?? 0;
            echo json_encode(['status' => 'success', 'data' => ['status' => 'stopped', 'elapsed_before' => $prev]]);
            exit();
        }
    case 'heartbeat':
        if (!isset($_SESSION['persistent_timers'][$admin_id])) {
            $_SESSION['persistent_timers'][$admin_id] = ['status' => 'stopped', 'started_at' => null, 'elapsed_before' => 0, 'last_heartbeat' => $now];
        } else {
            $_SESSION['persistent_timers'][$admin_id]['last_heartbeat'] = $now;
        }
        echo json_encode(['status' => 'success']);
        exit();
    case 'get':
    default:
        $data = $_SESSION['persistent_timers'][$admin_id] ?? null;
        if (!$data) {
            echo json_encode(['status' => 'success', 'data' => ['status' => 'stopped', 'started_at' => null, 'elapsed_before' => 0]]);
            exit();
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit();
}

echo json_encode(['status' => 'error', 'message' => 'invalid_action']);
exit();
