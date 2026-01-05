<?php
// Centralized list of canonical departments used across the app
// Keys match the values used in `ajouter_admin.php` -- keep them stable.
function get_departments()
{
    return [
        '__NULL__' => 'Non défini',
        'depart_formation' => 'Formation',
        'depart_communication' => 'Communication',
        'depart_informatique' => 'Informatique',
        'depart_consulting' => 'Consulting',
        'depart_marketing&vente' => 'Marketing & Vente',
        'depart_grh' => 'GRH',
        'depart_rh' => 'Ressources Humaines',
        'depart_finance' => 'Finance',
        'administration' => 'Administration',
    ];
}

