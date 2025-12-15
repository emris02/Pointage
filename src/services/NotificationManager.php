<?php
// Fonctions de gestion des notifications

function creer_notification($employe_id, $titre, $contenu, $type = 'info', $admin_only = 0) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (employe_id, titre, contenu, type, admin_only) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$employe_id, $titre, $contenu, $type, $admin_only]);
}

function get_notifications($employe_id, $admin = false) {
    global $pdo;
    if ($admin) {
        $stmt = $pdo->query("SELECT * FROM notifications WHERE admin_only = 1 OR employe_id IS NULL ORDER BY date DESC");
        return $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE (employe_id = ? OR employe_id IS NULL) AND admin_only = 0 ORDER BY date DESC");
        $stmt->execute([$employe_id]);
        return $stmt->fetchAll();
    }
}

function marquer_notification_lue($notif_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ?");
    return $stmt->execute([$notif_id]);
}

function get_nb_notifications_non_lues($employe_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (employe_id = ? OR employe_id IS NULL) AND lu = 0 AND admin_only = 0");
    $stmt->execute([$employe_id]);
    return $stmt->fetchColumn();
}
