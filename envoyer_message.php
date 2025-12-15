<?php
session_start();
require 'db.php';

// Vérification admin seulement
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $destinataires = $_POST['destinataires'] ?? [];
    $sujet = trim($_POST['sujet']);
    $contenu = trim($_POST['contenu']);

    if (empty($destinataires) || empty($sujet) || empty($contenu)) {
        $_SESSION['erreur'] = "Tous les champs sont obligatoires";
        header("Location: messagerie.php");
        exit;
    }

    // Enregistrement du message
    $pdo->beginTransaction();
    try {
        // Insertion du message
        $stmt = $pdo->prepare("INSERT INTO messages (expediteur_id, sujet, contenu) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $sujet, $contenu]);
        $message_id = $pdo->lastInsertId();

        // Ajout des destinataires
        $stmt = $pdo->prepare("INSERT INTO message_destinataires (message_id, destinataire_id) VALUES (?, ?)");
        foreach ($destinataires as $dest_id) {
            $stmt->execute([$message_id, $dest_id]);
        }

        $pdo->commit();
        $_SESSION['succes'] = "Message envoyé avec succès";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['erreur'] = "Erreur lors de l'envoi du message";
    }

    header("Location: messagerie.php");
    exit;
}