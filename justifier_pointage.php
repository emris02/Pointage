<?php
session_start();
require 'db.php';

// Vérification des autorisations admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profil_employe.php?id=' . $_POST['employe_id']);
    exit();
}

$employe_id = $_POST['employe_id'];
$date = $_POST['date'];
$type = $_POST['type'];
$est_justifie = isset($_POST['est_justifie']) ? 1 : 0;
$commentaire = $_POST['commentaire'] ?? '';

if ($type === 'retard') {
    // Justifier un retard
    $stmt = $pdo->prepare("UPDATE pointages 
                          SET est_justifie = ?, commentaire = ?, justifie_par = ?, date_justification = NOW()
                          WHERE employe_id = ? AND DATE(date_heure) = ? AND type = 'arrivee'");
    $stmt->execute([$est_justifie, $commentaire, $_SESSION['user_id'], $employe_id, $date]);
} else {
    // Justifier une absence
    // Vérifier d'abord si une entrée existe déjà
    $stmt = $pdo->prepare("SELECT id FROM absences WHERE employe_id = ? AND date_absence = ?");
    $stmt->execute([$employe_id, $date]);
    
    if ($stmt->fetch()) {
        // Mettre à jour
        $stmt = $pdo->prepare("UPDATE absences 
                              SET est_justifie = ?, commentaire = ?, justifie_par = ?, date_justification = NOW()
                              WHERE employe_id = ? AND date_absence = ?");
    } else {
        // Créer
        $stmt = $pdo->prepare("INSERT INTO absences 
                              (employe_id, date_absence, est_justifie, commentaire, justifie_par, date_justification)
                              VALUES (?, ?, ?, ?, ?, NOW())");
    }
    $stmt->execute([$employe_id, $date, $est_justifie, $commentaire, $_SESSION['user_id']]);
}

header('Location: profile.php?id=' . $employe_id . '&justified=1');
exit();