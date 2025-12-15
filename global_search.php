<?php
/**
 * Recherche globale avec redirection intelligente
 * Usage: global_search.php?q=terme
 */
session_start();
require_once __DIR__ . '/src/config/bootstrap.php';

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    header("Location: admin_dashboard_unifie.php");
    exit();
}

$like = '%' . $q . '%';
$redirectUrl = null;

// 1. Recherche par ID exact (priorité maximale)
if (is_numeric($q)) {
    $id = (int)$q;
    
    // Vérifier si c'est un ID d'employé
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()) {
        $redirectUrl = "profil_employe.php?id=$id";
    }
    
    // Vérifier si c'est un ID d'admin
    if (!$redirectUrl) {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            $redirectUrl = "profil_admin.php?id=$id";
        }
    }
    
    // Vérifier si c'est un ID de demande
    if (!$redirectUrl) {
        $stmt = $pdo->prepare("SELECT id FROM demandes WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            $redirectUrl = "demandes.php?id=$id";
        }
    }
}

// 2. Recherche par email exact
if (!$redirectUrl) {
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE email = ? LIMIT 1");
    $stmt->execute([$q]);
    $result = $stmt->fetch();
    if ($result) {
        $redirectUrl = "profil_employe.php?id=" . $result['id'];
    }
    
    if (!$redirectUrl) {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$q]);
        $result = $stmt->fetch();
        if ($result) {
            $redirectUrl = "profil_admin.php?id=" . $result['id'];
        }
    }
}

// 3. Recherche par nom/prénom exact
if (!$redirectUrl) {
    $parts = explode(' ', trim($q));
    if (count($parts) >= 2) {
        $prenom = $parts[0];
        $nom = implode(' ', array_slice($parts, 1));
        
        // Recherche employé
        $stmt = $pdo->prepare("SELECT id FROM employes WHERE prenom = ? AND nom = ? LIMIT 1");
        $stmt->execute([$prenom, $nom]);
        $result = $stmt->fetch();
        if ($result) {
            $redirectUrl = "profil_employe.php?id=" . $result['id'];
        }
        
        // Recherche admin
        if (!$redirectUrl) {
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE prenom = ? AND nom = ? LIMIT 1");
            $stmt->execute([$prenom, $nom]);
            $result = $stmt->fetch();
            if ($result) {
                $redirectUrl = "profil_admin.php?id=" . $result['id'];
            }
        }
    }
}

// 4. Recherche par nom ou prénom seul
if (!$redirectUrl) {
    // Employé par nom
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE nom = ? LIMIT 1");
    $stmt->execute([$q]);
    $result = $stmt->fetch();
    if ($result) {
        $redirectUrl = "profil_employe.php?id=" . $result['id'];
    }
    
    // Employé par prénom
    if (!$redirectUrl) {
        $stmt = $pdo->prepare("SELECT id FROM employes WHERE prenom = ? LIMIT 1");
        $stmt->execute([$q]);
        $result = $stmt->fetch();
        if ($result) {
            $redirectUrl = "profil_employe.php?id=" . $result['id'];
        }
    }
    
    // Admin par nom
    if (!$redirectUrl) {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE nom = ? LIMIT 1");
        $stmt->execute([$q]);
        $result = $stmt->fetch();
        if ($result) {
            $redirectUrl = "profil_admin.php?id=" . $result['id'];
        }
    }
    
    // Admin par prénom
    if (!$redirectUrl) {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE prenom = ? LIMIT 1");
        $stmt->execute([$q]);
        $result = $stmt->fetch();
        if ($result) {
            $redirectUrl = "profil_admin.php?id=" . $result['id'];
        }
    }
}

// 5. Recherche par poste exact
if (!$redirectUrl) {
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE poste = ? LIMIT 1");
    $stmt->execute([$q]);
    $result = $stmt->fetch();
    if ($result) {
        $redirectUrl = "profil_employe.php?id=" . $result['id'];
    }
}

// 6. Recherche par département exact
if (!$redirectUrl) {
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE departement = ? LIMIT 1");
    $stmt->execute([$q]);
    $result = $stmt->fetch();
    if ($result) {
        $redirectUrl = "profil_employe.php?id=" . $result['id'];
    }
}

// 7. Recherche par type de demande exact
if (!$redirectUrl) {
    $stmt = $pdo->prepare("SELECT id FROM demandes WHERE type = ? LIMIT 1");
    $stmt->execute([$q]);
    $result = $stmt->fetch();
    if ($result) {
        $redirectUrl = "demandes.php?id=" . $result['id'];
    }
}

// 8. Recherche par date (format français ou international)
if (!$redirectUrl) {
    $dateFormats = [
        'd/m/Y', 'd-m-Y', 'd.m.Y',
        'Y-m-d', 'Y/m/d', 'Y.m.d',
        'm/d/Y', 'm-d-Y', 'm.d.Y'
    ];
    
    foreach ($dateFormats as $format) {
        $date = DateTime::createFromFormat($format, $q);
        if ($date !== false) {
            $dateStr = $date->format('Y-m-d');
            
            // Chercher un pointage de cette date
            $stmt = $pdo->prepare("
                SELECT p.employe_id 
                FROM pointages p 
                WHERE DATE(p.date_heure) = ? 
                LIMIT 1
            ");
            $stmt->execute([$dateStr]);
            $result = $stmt->fetch();
            if ($result) {
                $redirectUrl = "profil_employe.php?id=" . $result['employe_id'];
                break;
            }
            
            // Chercher une demande de cette date
            $stmt = $pdo->prepare("
                SELECT d.id 
                FROM demandes d 
                WHERE DATE(d.date_demande) = ? 
                LIMIT 1
            ");
            $stmt->execute([$dateStr]);
            $result = $stmt->fetch();
            if ($result) {
                $redirectUrl = "demandes.php?id=" . $result['id'];
                break;
            }
        }
    }
}

// 9. Recherche par heure
if (!$redirectUrl) {
    if (preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $q)) {
        $stmt = $pdo->prepare("
            SELECT p.employe_id 
            FROM pointages p 
            WHERE TIME(p.date_heure) LIKE ? 
            LIMIT 1
        ");
        $stmt->execute(["%$q%"]);
        $result = $stmt->fetch();
        if ($result) {
            $redirectUrl = "profil_employe.php?id=" . $result['employe_id'];
        }
    }
}

// 10. Si aucune correspondance exacte, recherche floue et redirection vers dashboard
if (!$redirectUrl) {
    // Chercher la première correspondance floue
    $stmt = $pdo->prepare("
        SELECT 'employe' as type, id FROM employes 
        WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR poste LIKE ? OR departement LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$like, $like, $like, $like, $like]);
    $result = $stmt->fetch();
    
    if ($result) {
        $redirectUrl = "profil_employe.php?id=" . $result['id'];
    } else {
        $stmt = $pdo->prepare("
            SELECT 'admin' as type, id FROM admins 
            WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR role LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$like, $like, $like, $like]);
        $result = $stmt->fetch();
        
        if ($result) {
            $redirectUrl = "profil_admin.php?id=" . $result['id'];
        } else {
            $stmt = $pdo->prepare("
                SELECT 'demande' as type, d.id FROM demandes d
                JOIN employes e ON e.id = d.employe_id
                WHERE d.type LIKE ? OR d.motif LIKE ? OR d.statut LIKE ? OR e.nom LIKE ? OR e.prenom LIKE ?
                LIMIT 1
            ");
            $stmt->execute([$like, $like, $like, $like, $like]);
            $result = $stmt->fetch();
            
            if ($result) {
                $redirectUrl = "demandes.php?id=" . $result['id'];
            }
        }
    }
}

// 11. Dernier recours : redirection vers dashboard avec le terme en paramètre
if (!$redirectUrl) {
    $redirectUrl = "admin_dashboard_unifie.php?search=" . urlencode($q);
}

// Redirection finale
header("Location: $redirectUrl");
exit();
?>