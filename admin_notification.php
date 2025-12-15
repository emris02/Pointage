<?php
// Compatibility shim: forward legacy requests to the API endpoint
// This prevents 404s from old code or cached clients requesting `admin_notification.php`
require_once __DIR__ . '/api/admin_notifications.php';
