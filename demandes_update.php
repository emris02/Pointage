<?php
 //
require_once __DIR__ . '/src/config/bootstrap.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard_unifie.php#demandes');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$statut = $_POST['statut'] ?? 'en_attente';

if ($id <= 0 || !in_array($statut, ['en_attente','approuve','rejete'])) {
    header('Location: admin_dashboard_unifie.php#demandes');
    exit();
}

$controller = new DemandeController($pdo);
$controller->update($id, ['type'=>'', 'motif'=>'', 'statut'=>$statut]);

header('Location: demandes.php?id=' . $id);
exit();
?>


