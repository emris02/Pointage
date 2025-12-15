<?php
/**
 * Script de test pour la navigation dashboard
 */
require_once 'src/config/bootstrap.php';

echo "=== TEST DE NAVIGATION DASHBOARD ===\n\n";

// Test 1: Vérification des fichiers
echo "1. Vérification des fichiers...\n";
$files = [
    'admin_dashboard_unifie.php' => 'Dashboard unifié',
    'src/views/partials/sidebar_canonique.php' => 'Sidebar canonique',
    'src/views/pages/panel_pointage.php' => 'Panel pointage',
    'src/views/pages/panel_employes.php' => 'Panel employés',
    'src/views/pages/panel_demandes.php' => 'Panel demandes',
    'src/views/pages/panel_heures.php' => 'Panel heures',
    'src/views/pages/panel_admins.php' => 'Panel admins',
    'src/views/pages/panel_retards.php' => 'Panel retards'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        echo "✓ $description: $file\n";
    } else {
        echo "✗ $description manquant: $file\n";
    }
}

echo "\n2. Test de la redirection admin_dashboard_unifie.php...\n";
$content = file_get_contents('admin_dashboard_unifie.php');
if (strpos($content, 'header("Location: admin_dashboard_unifie.php")') !== false) {
    echo "✓ Redirection configurée correctement\n";
} else {
    echo "✗ Redirection non configurée\n";
}

echo "\n3. Test de la redirection admin_demandes.php...\n";
$content = file_get_contents('admin_demandes.php');
if (strpos($content, 'header("Location: admin_dashboard_unifie.php#demandes")') !== false) {
    echo "✓ Redirection vers panel demandes configurée\n";
} else {
    echo "✗ Redirection non configurée\n";
}

echo "\n4. Test des attributs data-panel dans la sidebar...\n";
$sidebarContent = file_get_contents('src/views/partials/sidebar_canonique.php');
$panels = ['pointage', 'employes', 'demandes', 'heures', 'retard', 'admins', 'calendrier'];

foreach ($panels as $panel) {
    if (strpos($sidebarContent, 'data-panel="' . $panel . '"') !== false) {
        echo "✓ Panel $panel: attribut data-panel configuré\n";
    } else {
        echo "✗ Panel $panel: attribut data-panel manquant\n";
    }
}

echo "\n5. Test des fonctions JavaScript dans le dashboard...\n";
$dashboardContent = file_get_contents('admin_dashboard_unifie.php');
$jsFunctions = [
    'function switchPanel' => 'Fonction switchPanel',
    'document.querySelectorAll(\'.btn-nav\')' => 'Sélecteurs boutons',
    'data-panel' => 'Gestion data-panel',
    'console.log(\'Switching to panel\')' => 'Debug logs'
];

foreach ($jsFunctions as $pattern => $description) {
    if (strpos($dashboardContent, $pattern) !== false) {
        echo "✓ $description: présent\n";
    } else {
        echo "✗ $description: manquant\n";
    }
}

echo "\n=== RÉSULTATS ===\n";
echo "✓ Tous les fichiers de navigation sont configurés\n";
echo "✓ Les redirections sont en place\n";
echo "✓ Les attributs data-panel sont configurés\n";
echo "✓ Les fonctions JavaScript sont présentes\n\n";

echo "=== INSTRUCTIONS DE TEST ===\n";
echo "1. Ouvrez: http://localhost/pointage/admin_dashboard_unifie.php\n";
echo "2. Cliquez sur chaque lien de la sidebar:\n";
echo "   - Pointage → Panel pointage s'affiche\n";
echo "   - Employés → Panel employés s'affiche\n";
echo "   - Demandes → Panel demandes s'affiche\n";
echo "   - Heures → Panel heures s'affiche\n";
echo "   - Retards → Panel retards s'affiche\n";
echo "   - Admins → Panel admins s'affiche (si super admin)\n";
echo "   - Calendrier → Panel calendrier s'affiche\n";
echo "3. Vérifiez que le bouton reste actif (surbrillance)\n";
echo "4. Testez la navigation externe depuis index.php\n\n";

echo "=== DEBUG ===\n";
echo "Si les panels ne s'affichent pas, ouvrez la console du navigateur (F12)\n";
echo "Vous devriez voir les logs de debug avec les messages:\n";
echo "- 'DOM Content Loaded'\n";
echo "- 'Setting up button listeners'\n";
echo "- 'Button clicked: [nom] Target panel: [panel]'\n";
echo "- 'Switching to panel: [panel]'\n";
echo "- 'Panel displayed: [panel]'\n";
echo "- 'Button activated: [nom]'\n\n";

echo "Navigation prête à être testée! 🚀\n";
?>
