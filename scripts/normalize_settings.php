<?php
require_once __DIR__ . '/../src/config/bootstrap.php';
if (!class_exists('Settings')) {
    echo "Settings model not found\n";
    exit(0);
}
$sm = new Settings($pdo);
$rows = $sm->getAll();
if (empty($rows)) {
    echo "No settings to normalize\n";
    exit(0);
}

// Mapping des clés connues vers des noms canoniques
$mapping = [
    'font_large' => 'font_size',
    'selected_font' => 'font_family',
    'contrast' => 'high_contrast',
    // ajoutez des mappings supplémentaires ici
];

foreach ($rows as $r) {
    $old = $r['cle'];
    $val = $r['valeur'];
    if (isset($mapping[$old])) {
        $new = $mapping[$old];
        echo "Renaming $old -> $new\n";
        $sm->set($new, $val);
        // Optionnel : supprimer l'ancien
        $pdo->prepare('DELETE FROM settings WHERE cle = ?')->execute([$old]);
    } else {
        echo "No mapping for $old\n";
    }
}

echo "Normalization complete.\n";
