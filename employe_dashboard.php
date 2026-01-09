<?php
/**
 * Dashboard employé avec logique de justification des retards
 */
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';

// Inclure les contrôleurs nécessaires
require_once 'src/controllers/EmployeController.php';
require_once 'src/controllers/PointageController.php';
require_once 'src/controllers/BadgeController.php';
require_once 'src/controllers/EventController.php';

// ✅ INCLURE AVEC VÉRIFICATION
$notificationControllerPath = 'src/controllers/NotificationController.php';
if (file_exists($notificationControllerPath)) {
    require_once $notificationControllerPath;
} else {
    error_log("Fichier NotificationController non trouvé: " . $notificationControllerPath);
}

use Pointage\Services\AuthService;

AuthService::requireAuth();

$authController = new AuthController($pdo);
if (!$authController->isLoggedIn() || $authController->isAdmin()) {
    header("Location: login.php");
    exit();
}

$isAdmin = false;
$pageTitle = 'Mon Dashboard - Xpert Pro';
$pageHeader = 'Mon Dashboard';
$pageDescription = 'Votre espace personnel de pointage';

$employe_id = $_SESSION['employe_id'];

// ✅ INITIALISATION SÉCURISÉE DES CONTRÔLEURS
try {
    $employeController = new EmployeController($pdo);
    $pointageController = new PointageController($pdo);
    $badgeController = new BadgeController($pdo);
    $eventController = new EventController($pdo);
    
    // ✅ INITIALISATION CONDITIONNELLE DE NotificationController
    $notificationController = null;
    if (class_exists('NotificationController')) {
        $notificationController = new NotificationController($pdo);
    } elseif (class_exists('Pointage\Controllers\NotificationController')) {
        // Si la classe utilise un namespace
        $notificationController = new Pointage\Controllers\NotificationController($pdo);
    } else {
        error_log("Classe NotificationController non trouvée");
    }
    
} catch (Exception $e) {
    error_log("Erreur initialisation contrôleurs: " . $e->getMessage());
    die("Erreur lors du chargement du dashboard. Veuillez contacter l'administrateur.");
}

// ==================== LOGIQUE DE JUSTIFICATION DES RETARDS ====================

$showJustificationModal = false;
$retardData = null;

// 1. Vérifier si un retard vient d'être détecté via scan_qr.php
if (isset($_SESSION['pending_retard_justification'])) {
    $retardData = $_SESSION['pending_retard_justification'];
    $showJustificationModal = true;
    
    // Vérifier que les données sont valides
    if (!isset($retardData['pointage_id']) || !isset($retardData['date_heure'])) {
        $showJustificationModal = false;
        unset($_SESSION['pending_retard_justification']);
    }
}

// 2. Vérifier les retards existants non justifiés
if (!$showJustificationModal) {
    $today = date('Y-m-d');
    $heureLimite = '09:00:00';
    
    // CORRECTION : Le paramètre :heure_limite est utilisé 2 fois dans la requête
    // Il faut le binder 2 fois ou utiliser un paramètre différent
    $stmt = $pdo->prepare("
        SELECT 
            p.id as pointage_id,
            p.date_heure,
            p.type,
            TIMESTAMPDIFF(MINUTE, :heure_limite, TIME(p.date_heure)) as retard_minutes
        FROM pointages p
        LEFT JOIN retards r ON p.id = r.pointage_id
        WHERE p.employe_id = :employe_id
        AND DATE(p.date_heure) = :date_aujourdhui
        AND p.type = 'arrivee'
        AND TIME(p.date_heure) > :heure_limite_compare
        AND (r.id IS NULL OR r.statut = 'en_attente')
        ORDER BY p.date_heure DESC
        LIMIT 1
    ");
    
    // CORRECTION : Binder les deux paramètres
    $stmt->execute([
        'employe_id' => $employe_id,
        'date_aujourdhui' => $today,
        'heure_limite' => $heureLimite,          // Pour TIMESTAMPDIFF
        'heure_limite_compare' => $heureLimite   // Pour TIME(p.date_heure) > ...
    ]);
    
    $retardNonJustifie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($retardNonJustifie) {
        $showJustificationModal = true;
        $retardData = [
            'pointage_id' => $retardNonJustifie['pointage_id'],
            'date_heure' => $retardNonJustifie['date_heure'],
            'retard_minutes' => $retardNonJustifie['retard_minutes'],
            'heure_limite' => $heureLimite,
            'from_existing' => true
        ];
        
        // Stocker dans la session pour persister après rafraîchissement
        $_SESSION['pending_retard_justification'] = $retardData;
    }
}

// 3. Traitement de la soumission de justification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_justification'])) {
    $pointageId = (int)($_POST['pointage_id'] ?? 0);
    $raison = trim($_POST['raison'] ?? '');
    $details = trim($_POST['details'] ?? '');
    
    if ($pointageId > 0 && !empty($raison)) {
        try {
            // Vérifier que le pointage appartient à l'employé
            $stmt = $pdo->prepare("
                SELECT id FROM pointages 
                WHERE id = ? AND employe_id = ? AND type = 'arrivee'
            ");
            $stmt->execute([$pointageId, $employe_id]);
            
            if ($stmt->fetch()) {
                // Gestion du fichier uploadé (optionnel)
                $fichierPath = null;
                if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/justifications/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
                    $fichierPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $fichierPath)) {
                        // Fichier uploadé avec succès
                    }
                }
                
                // Enregistrer la justification
                // CORRECTION : Ajout de la colonne employe_id qui manquait
                $stmt = $pdo->prepare("
                    INSERT INTO retards 
                    (pointage_id, employe_id, raison, details, fichier_justificatif, statut, date_soumission) 
                    VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())
                ");
                
                $stmt->execute([
                    $pointageId,
                    $employe_id,  // CORRECTION : Ajout de l'ID employé
                    $raison,
                    $details,
                    $fichierPath
                ]);
                
                // Supprimer de la session et cacher le modal
                unset($_SESSION['pending_retard_justification']);
                $showJustificationModal = false;
                $retardData = null;
                
                // Ajouter un message de succès
                $_SESSION['justification_success'] = true;
                
                // Rediriger pour éviter la re-soumission du formulaire
                header("Location: employe_dashboard.php?justification=success");
                exit();
            }
        } catch (Exception $e) {
            error_log("Erreur enregistrement justification: " . $e->getMessage());
            $justificationError = "Erreur lors de l'enregistrement de la justification.";
        }
    } else {
        $justificationError = "Veuillez sélectionner une raison pour votre retard.";
    }
}

// 4. Vérifier si l'utilisateur a ignoré le modal
if (isset($_GET['ignore_retard']) && isset($_SESSION['pending_retard_justification'])) {
    // Marquer comme "vu" sans justification
    $pointageId = $_SESSION['pending_retard_justification']['pointage_id'] ?? 0;
    
    if ($pointageId > 0) {
        // Optionnel : enregistrer un marqueur d'ignorance
        // CORRECTION : Ajout de la colonne employe_id
        $stmt = $pdo->prepare("
            INSERT INTO retards 
            (pointage_id, employe_id, raison, statut, date_soumission) 
            VALUES (?, ?, 'ignore', 'ignore', NOW())
            ON DUPLICATE KEY UPDATE raison = 'ignore'
        ");
        $stmt->execute([$pointageId, $employe_id]); // CORRECTION : Ajout de l'ID employé
    }
    
    unset($_SESSION['pending_retard_justification']);
    $showJustificationModal = false;
    $retardData = null;
}

// ==================== FIN LOGIQUE JUSTIFICATION ====================

// Traitement des requêtes AJAX pour la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    str_contains($_SERVER["CONTENT_TYPE"] ?? '', 'application/json')) {

   header("Content-Type: application/json");

    $input = json_decode(file_get_contents('php://input'), true);
    $field = $input['field'] ?? null;
    $value = trim($input['value'] ?? '');

    $allowedFields = ['nom', 'prenom', 'telephone', 'adresse', 'mot_de_passe'];

    if (!$field || !in_array($field, $allowedFields) || empty($value)) {
        echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
        exit;
    }

    try {
        if ($field === 'mot_de_passe') {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare("UPDATE employes SET `$field` = :value WHERE id = :id");
        $stmt->execute([
            ':value' => $value,
            ':id' => $employe_id
        ]);

        echo json_encode(['success' => true, 'field' => $field, 'value' => $value]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
    }

    exit;
}

// Récupération des données de l'employé avec le contrôleur
$employe = $employeController->show($employe_id);
if (!$employe) {
    header("Location: login.php");
    exit();
}
$prochainConge = $prochainConge ?? null;

// Préparer la source de la photo (proxy si nécessaire) et les initiales
$photoSrc = '';
if (!empty($employe['photo'])) {
    // Only use the uploaded photo if the file actually exists on disk
    $photoRaw = $employe['photo'];
    $photoSrcCandidate = htmlspecialchars($photoRaw);
    if (strpos($photoRaw, 'uploads/') !== false) {
        $uploadsPath = __DIR__ . '/uploads/employes/' . basename($photoRaw);
        if (file_exists($uploadsPath)) {
            $photoSrc = dirname($_SERVER['SCRIPT_NAME']) . '/image.php?f=' . urlencode(basename($photoRaw));
        } else {
            // file missing, fallback to generic profile image
            error_log('Employe photo missing: ' . $uploadsPath);
            $photoSrc = '';
        }
    } else {
        // If not an uploads path, keep the value (external URL) but it's sanitized
        $photoSrc = $photoSrcCandidate;
    }
}
$initiale = strtoupper(substr($employe['prenom'] ?? '', 0, 1)) . strtoupper(substr($employe['nom'] ?? '', 0, 1));

// Fallbacks sécurisés pour tous les tokens possibles
$token = $token ?? '';                     // si tu utilises $token
$badgeTokenValue = $badgeToken['token'] ?? ''; // si tu utilises $badgeToken['token']

// Toujours forcer en string (prévient les deprecated warnings)
$token = (string) $token;
$badgeTokenValue = (string) $badgeTokenValue;

// Récupération ou génération du badge
$stmt = $pdo->prepare("SELECT * FROM badge_tokens WHERE employe_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$employe_id]);
$badgeToken = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$badgeToken) {
    $result = BadgeManager::regenerateToken($employe_id, $pdo);
    if (isset($result['status']) && $result['status'] === 'success') {
        $badgeToken = [
            'token' => $result['token'],
            'token_hash' => $result['token_hash'],
            'expires_at' => $result['expires_at'],
            'message' => isset($result['message']) ? $result['message'] : '',
            'status' => 'active'
        ];
    }
} else {
    $validite = BadgeManager::generateToken($employe_id);
    $badgeToken['message'] = isset($validite['message']) ? $validite['message'] : '';
}

// Ensure token variables are set for the view (used for QR generation)
$token = $badgeToken['token'] ?? '';
$badgeTokenValue = $badgeToken['token'] ?? '';
$expiresAt = $badgeToken['expires_at'] ?? null;

$qrUrlSmall = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($token);
$qrUrlModal = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($badgeTokenValue);

// Configuration des données pour l'affichage
$departement = ucfirst(str_replace('depart_', '', $employe['departement']));
$initiale = strtoupper(substr($employe['prenom'], 0, 1)) . strtoupper(substr($employe['nom'], 0, 1));
$badge_actif = !empty($badgeToken);
$badge_type = $employe['type'] ?? 'inconnu';

$departementColors = [
    'depart_formation' => 'bg-info',
    'depart_communication' => 'bg-warning',
    'depart_informatique' => 'bg-primary',
    'depart_grh' => 'bg-success',
    'administration' => 'bg-secondary'
];
$departementClass = $departementColors[$employe['departement']] ?? 'bg-dark';

// Gestion du mois sélectionné pour le calendrier
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$selected_month = max(1, min(12, $selected_month));
$selected_year = max(2020, min(2030, $selected_year));

// Calcul des mois précédent/suivant
$prev_month = $selected_month - 1;
$prev_year = $selected_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $selected_month + 1;
$next_year = $selected_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$current_month = date('m');
$current_year = date('Y');
$allow_next = !($selected_month == $current_month && $selected_year == $current_year);

// Récupération des statistiques via les contrôleurs
$employeStats = $employeController->getStats($employe_id);
$todayPointages = $pointageController->getEmployeHistory($employe_id, date('Y-m-d'), date('Y-m-d'));

// Normaliser et extraire arrivée/départ d'aujourd'hui
$todayArrivee = null;
$todayDepart = null;
foreach ($todayPointages as $tp) {
    $dt = $tp['date_heure'] ?? $tp['created_at'] ?? null;
    if (!$dt) continue;
    $time = date('H:i', strtotime($dt));
    if (($tp['type'] ?? $tp['event'] ?? '') === 'arrivee') {
        // garder l'arrivée la plus ancienne
        if ($todayArrivee === null || strtotime($time) < strtotime($todayArrivee)) {
            $todayArrivee = $time;
        }
    } elseif (($tp['type'] ?? $tp['event'] ?? '') === 'depart') {
        // garder le départ le plus récent
        if ($todayDepart === null || strtotime($time) > strtotime($todayDepart)) {
            $todayDepart = $time;
        }
    }
}
$monthlyStats = $eventController->getMonthlyStats($employe_id, $selected_year);

// Calcul des jours ouvrables du mois
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
$workingDays = [];

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
    $dayOfWeek = date('N', strtotime($date));
    if ($dayOfWeek < 6) { // Lundi à Vendredi
        $workingDays[] = $date;
    }
}

// Récupération des pointages du mois
$pointages = $pointageController->getEmployeHistory($employe_id, 
    "$selected_year-$selected_month-01", 
    "$selected_year-$selected_month-$daysInMonth"
);

// Calcul des dates pointées
$pointedDates = [];
foreach ($pointages as $pointage) {
    $date = date('Y-m-d', strtotime($pointage['date_heure'] ?? $pointage['created_at'] ?? 'now'));
    if (!in_array($date, $pointedDates)) {
        $pointedDates[] = $date;
    }
}

// Calcul des absences
$absentDays = array_diff($workingDays, $pointedDates);
$absencesAutorisees = 0;
$absencesNonAutorisees = 0;

foreach ($absentDays as $absentDate) {
    // Vérifier si l'absence est autorisée (AbsenceController)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM absences
        WHERE employe_id = :employe_id
          AND date_absence = :date
          AND statut = 'autorisé'
    ");
    $stmt->execute([
        'employe_id' => $employe_id,
        'date'       => $absentDate
    ]);

    $nbAbsence = (int) $stmt->fetchColumn();

    if ($nbAbsence > 0) {
        $absencesAutorisees++;
    } else {
        $absencesNonAutorisees++;
    }
}

// Calcul du temps de travail mensuel
$temps_mensuel = $pdo->prepare("
    SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(temps_total))) AS total
    FROM pointages
    WHERE employe_id = ?
      AND type = 'depart'
      AND MONTH(created_at) = ?
      AND YEAR(created_at) = ?
");
$temps_mensuel->execute([$employe_id, $selected_month, $selected_year]);
$temps = $temps_mensuel->fetch();
$temps_travail_mois = $temps['total'] ?? '00:00:00';

// Organisation des pointages par jour
$pointages_par_jour = [];
foreach ($pointages as $pointage) {
    $date = date('Y-m-d', strtotime($pointage['date_heure'] ?? $pointage['created_at'] ?? 'now'));
    if (!isset($pointages_par_jour[$date])) {
        $pointages_par_jour[$date] = ['arrivee' => null, 'depart' => null];
    }
    
    $heurePointage = $pointage['date_heure'] ?? $pointage['created_at'] ?? null;
    if ($pointage['type'] === 'arrivee') {
        $pointages_par_jour[$date]['arrivee'] = $heurePointage ? date('H:i', strtotime($heurePointage)) : null;
    } elseif ($pointage['type'] === 'depart') {
        $pointages_par_jour[$date]['depart'] = $heurePointage ? date('H:i', strtotime($heurePointage)) : null;
    }
}

// Calcul des statistiques
$stats = [
    'jours_presents' => 0,
    'jours_retards' => 0,
    'jours_absents' => 0,
    'jours_weekend' => 0,
    'temps_total' => 0
];

foreach ($pointages_par_jour as $date => $pointageDuJour) {
    $dayOfWeek = date('N', strtotime($date));

    if ($dayOfWeek >= 6) { // Samedi et Dimanche
        $stats['jours_weekend']++;
        continue;
    }

    if (isset($pointageDuJour['arrivee']) && !empty($pointageDuJour['arrivee'])) {
        $stats['jours_presents']++;

        // ✅ CORRECTION : Vérifier que 'arrivee' n'est pas null avant d'appeler strtotime()
        $arriveeTime = $pointageDuJour['arrivee'];
        if ($arriveeTime !== null) {
            $timestampArrivee = strtotime($arriveeTime);
            if ($timestampArrivee !== false && $timestampArrivee > strtotime('09:00:00')) {
                $stats['jours_retards']++;
            }
        }
    } else {
        // Vérifier si c'est un jour ouvrable sans pointage
        if (in_array($date, $workingDays) && !in_array($date, $pointedDates)) {
            $stats['jours_absents']++;
        }
    }
}

// Calcul des retards justifiés
$retardsJustifies = 0;
$retardsNonJustifies = $stats['jours_retards'];

// Compter les retards justifiés pour ce mois
if ($stats['jours_retards'] > 0) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(p.date_heure)) as retards_justifies
        FROM pointages p
        INNER JOIN retards r ON p.id = r.pointage_id
        WHERE p.employe_id = :employe_id
        AND MONTH(p.date_heure) = :month
        AND YEAR(p.date_heure) = :year
        AND p.type = 'arrivee'
        AND TIME(p.date_heure) > '09:00:00'
        AND r.statut IN ('en_attente', 'approuve')
    ");
    
    $stmt->execute([
        'employe_id' => $employe_id,
        'month' => $selected_month,
        'year' => $selected_year
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $retardsJustifies = $result['retards_justifies'] ?? 0;
    $retardsNonJustifies = max(0, $stats['jours_retards'] - $retardsJustifies);
}

// Derniers pointages
$raw_derniers = $pointageController->getEmployeHistory($employe_id, null, null, 10);

// Normaliser les champs pour l'affichage
$derniers_pointages = [];
foreach ($raw_derniers as $p) {
    $dt = $p['date_heure'] ?? $p['created_at'] ?? null;
    $jour = $dt ? date('Y-m-d', strtotime($dt)) : ($p['jour'] ?? null);
    $heure = $dt ? date('H:i', strtotime($dt)) : ($p['heure'] ?? null);
    $derniers_pointages[] = array_merge($p, [
        'jour' => $jour,
        'heure' => $heure,
        'type' => $p['type'] ?? ($p['event'] ?? 'inconnu')
    ]);
}

$derniers_pointages = array_slice($derniers_pointages, 0, 8);

// Données pour l'affichage
$date_actuelle = date('d F Y');
$mois_annee = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

// Vérification si le badge doit être régénéré
$dernier_depart = $pdo->prepare("
    SELECT created_at 
    FROM pointages 
    WHERE employe_id = ? 
    AND type = 'depart' 
    ORDER BY created_at DESC 
    LIMIT 1
");
$dernier_depart->execute([$employe_id]);
$depart = $dernier_depart->fetch();
$doit_regenerer = false;

if ($depart && isset($depart['created_at']) && $depart['created_at'] !== null) {
    $timestampDepart = strtotime($depart['created_at']);
    if ($timestampDepart !== false && $timestampDepart < time() - 3600) {
        $doit_regenerer = true;
    }
}

// ✅ CORRECTION : Vérifications des pointages manquants
$today = date('Y-m-d');
$now = date('H:i');

if ($now < '09:00') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM pointages 
        WHERE employe_id = ? 
        AND DATE(created_at) = ? 
        AND type = 'arrivee'
    ");
    $stmt->execute([$employe_id, $today]);
    
    if ($stmt->fetchColumn() == 0) {
        if ($notificationController && 
            method_exists($notificationController, 'notificationExists') &&
            method_exists($notificationController, 'createArriveeManquante')) {
            
            if (!$notificationController->notificationExists($employe_id, 'arrivee_manquante', $today)) {
                $notificationController->createArriveeManquante($employe_id);
            }
        }
    }
}

if ($now > '18:00') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM pointages 
        WHERE employe_id = ? 
        AND DATE(created_at) = ? 
        AND type = 'depart'
    ");
    $stmt->execute([$employe_id, $today]);
    
    if ($stmt->fetchColumn() == 0) {
        if ($notificationController && 
            method_exists($notificationController, 'notificationExists') &&
            method_exists($notificationController, 'createDepartManquant')) {
            
            if (!$notificationController->notificationExists($employe_id, 'depart_manquant', $today)) {
                $notificationController->createDepartManquant($employe_id);
            }
        }
    }
}

// ✅ CORRECTION : Récupération sécurisée des notifications
try {
    $notifications = [];
    if ($notificationController && method_exists($notificationController, 'getByEmploye')) {
        $notifications = $notificationController->getByEmploye($employe_id, 5);
    }
} catch (Exception $e) {
    error_log("Erreur récupération notifications: " . $e->getMessage());
    $notifications = [];
}

// CSS et JS supplémentaires
$additionalCSS = [
    'assets/css/employe_dash.css',
    'assets/css/fullcalendar.css'
];

$additionalJS = [
    'assets/js/employe.js',
    /* use the included local QR scanner (qr-scanner.min.js) */
    'assets/js/qr-scanner.min.js',
    /* Load FullCalendar from CDN for stability */
    'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js',
    'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js',
    // SweetAlert2 for modals used by calendrier.js
    'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js',
    'assets/js/calendrier.js',
    'assets/js/calendar-employe.js'
];

// Inline JS to run after scripts are loaded (tooltip init, badge toasts)
$inlineJS = "const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=\'tooltip\']'));\n";
$inlineJS .= "tooltipTriggerList.map(function (element) { return new bootstrap.Tooltip(element); });\n";

if (isset($_GET['new_badge'])) {
    $expiresAtToast = $badgeToken['expires_at'] ?? null;
    if ($expiresAtToast !== null) {
        $timestampExpiresToast = strtotime($expiresAtToast);
        if ($timestampExpiresToast !== false) {
            $expiresText = "Valide jusqu'au " . date('d/m/Y H:i', $timestampExpiresToast);
        } else {
            $expiresText = "Date d'expiration invalide";
        }
    } else {
        $expiresText = "Date d'expiration non définie";
    }

    $inlineJS .= "setTimeout(() => { const toast = document.createElement('div'); toast.className = 'position-fixed bottom-0 end-0 p-3'; toast.style.zIndex = '11'; toast.innerHTML = `";
    $inlineJS .= "<div class=\"toast show\" role=\"alert\">\n<div class=\"toast-header bg-success text-white\">\n<strong class=\"me-auto\">Nouveau badge généré</strong>\n<button type=\"button\" class=\"btn-close btn-close-white\" data-bs-dismiss=\"toast\"></button>\n</div>\n<div class=\"toast-body\">Votre badge a été régénéré avec succès. {$expiresText}</div>\n</div>`; document.body.appendChild(toast); setTimeout(()=>{ toast.remove(); }, 5000); }, 1000);";
}

if (isset($_GET['badge_updated'])) {
    $inlineJS .= "showToast('Badge mis à jour', 'Un nouveau badge a été généré automatiquement', 'success');\n";
}

// Ajouter JS pour la modal de justification si nécessaire
if ($showJustificationModal) {
    $additionalJS[] = 'assets/js/justification-modal.js';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/employe_dash.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    
    <style>
        /* Variables CSS modernes */
        :root {
            --primary: #0672e4;
            --primary-dark: #3a56d4;
            --primary-light: #6b8aff;
            --secondary: #7209b7;
            --accent: #4cc9f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --border: #e2e8f0;
            
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .dashboard-body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
        /* Loading Screen amélioré */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        
        .loader-content {
            text-align: center;
            color: white;
        }
        
        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loader-text {
            font-size: 1.1rem;
            opacity: 0.9;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.9; }
            50% { opacity: 0.7; }
        }
        
        /* Layout principal */
        .employe-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        @media (min-width: 992px) {
            .employe-layout {
                grid-template-columns: 1fr 350px;
            }
        }
        
        /* Header amélioré */
        .employe-header {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: var(--shadow-lg);
        }
        
        .btn-glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            transition: var(--transition);
        }
        
        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-lg);
        }
        
        /* Animations */
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .slide-in-left {
            animation: slideInLeft 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Section Profil améliorée */
        .profile-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        
        .profile-section:hover {
            box-shadow: var(--shadow-xl);
        }
        
        .profile-avatar-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-avatar {
            width: 140px;
            height: 140px;
            border: 4px solid white;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .online-status {
            width: 16px;
            height: 16px;
            border: 2px solid white;
        }
        
        .profile-name {
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .profile-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .detail-item {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .detail-item:hover {
            border-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .detail-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        /* Section Statistiques améliorée */
        .stats-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .employe-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        /* Grille de contenu principale */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .content-grid {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        /* Section Calendrier améliorée */
        .calendar-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .calendar-container {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1rem;
            min-height: 400px;
        }
        
        /* Section Pointages améliorée */
        .pointages-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border);
        }
        
        .pointages-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .pointage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        
        .pointage-item:hover {
            background: var(--light);
            border-radius: var(--radius-sm);
        }
        
        .pointage-type {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .pointage-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .type-arrivee {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
        }
        
        .type-depart {
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
        }
        
        /* Sidebar améliorée */
        .sidebar-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        @media (min-width: 992px) {
            .sidebar-content {
                grid-template-rows: auto auto;
            }
        }
        
        /* Section Badge améliorée */
        .badge-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        
        .badge-section:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .badge-qr-container {
            position: relative;
            margin: 1.5rem auto;
            max-width: 200px;
        }
        
        .badge-qr {
            width: 100%;
            height: auto;
            border-radius: var(--radius);
            transition: var(--transition);
        }
        
        .badge-qr:hover {
            transform: scale(1.05);
        }
        
        .qr-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(67, 97, 238, 0.9);
            opacity: 0;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .badge-qr-container:hover .qr-overlay {
            opacity: 1;
        }
        
        /* Section Informations améliorée */
        .info-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border);
        }
        
        .info-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1.25rem;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .info-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        /* Badges de couleur */
        .bg-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .bg-indigo { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
        .bg-orange { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .bg-teal { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); }
        .bg-pink { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        
        /* Modal amélioré */
        .modal-content.glass-effect {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-xl);
        }
        
        /* Animations et effets */
        .hover-lift {
            transition: var(--transition);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .employe-layout {
                padding: 1rem;
                gap: 1rem;
            }
            
            .profile-section,
            .stats-section,
            .calendar-section,
            .pointages-section,
            .badge-section,
            .info-section {
                padding: 1.5rem;
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .employe-header .d-flex {
                flex-direction: column;
                gap: 1rem;
            }
            
            .profile-details-grid,
            .employe-stats,
            .info-list {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-name {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .employe-layout {
                padding: 0.75rem;
            }
            
            .profile-section,
            .stats-section,
            .calendar-section,
            .pointages-section,
            .badge-section,
            .info-section {
                padding: 1.25rem;
            }
            
            .stat-item,
            .detail-item,
            .info-item {
                padding: 1rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-name {
                font-size: 1.3rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
        }
        
        /* Scrollbar personnalisée */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        
        /* States et utilitaires */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
            color: white;
        }
        
        .status-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            color: white;
        }
        
        .status-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
        }
        
        .status-info {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }
    </style>
        /* Overlay utilisé par les dropdowns mobiles */
        <style>
            #dropdownOverlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                z-index: 1040;
                display: none;
            }
            #dropdownOverlay.active {
                display: block;
            }

            /* Forcer la lisibilité du libellé du mois */
            #currentMonth {
                color: var(--dark) !important;
                background: transparent !important;
                font-weight: 600;
                text-shadow: none !important;
            }
            /* Cas où un style hérité colore le texte en blanc ou bleu : appliquer partout dans l'élément */
            #currentMonth, #currentMonth * {
                color: var(--dark) !important;
                background: transparent !important;
            }
        </style>
</head>
<body class="dashboard-body">
    <!-- Overlay global pour dropdowns mobiles -->
    <div id="dropdownOverlay" aria-hidden="true"></div>
    <!-- Loading Screen -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <p class="loader-text">Chargement de votre espace...</p>
        </div>
    </div>
    
    <div class="employe-layout">
        <!-- Header Principal -->
        <header class="employe-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8 col-lg-9">
                        <div class="d-flex align-items-center gap-3">
                            <!-- Logo/Brand -->
                            <div class="brand-logo d-none d-md-flex align-items-center">
                                <div class="logo-icon bg-white rounded-circle p-2 me-2">
                                    <i class="fas fa-user-clock text-primary"></i>
                                </div>
                                <span class="text-white fw-bold fs-5">Xpert Employee</span>
                            </div>
                            
                            <!-- Titre + Breadcrumb -->
                            <div class="d-flex flex-column">
                                <h1 class="h5 text-white mb-1">
                                    <i class="fas fa-user-circle me-2"></i>Mon Espace Personnel
                                </h1>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="#" class="text-white text-decoration-none">
                                                <i class="fas fa-home me-1"></i>Accueil
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item active text-white" aria-current="page">
                                            Tableau de bord
                                        </li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 col-lg-3">
                        <!-- Actions Header -->
                        <div class="d-flex align-items-center justify-content-end gap-3">
                            <!-- Notifications -->
                            <div class="dropdown">
                                <button class="btn btn-glass position-relative rounded-circle p-2" 
                                        id="notificationDropdown" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false" 
                                        aria-label="Notifications">
                                    <i class="fas fa-bell"></i>
                                    <?php if (!empty($notifications)): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger pulse" style="font-size: 0.6rem; padding: 0.25rem 0.4rem;">
                                            <?= count($notifications) ?>
                                        </span>
                                    <?php endif; ?>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end shadow-lg glass-effect border-0" aria-labelledby="notificationDropdown" style="width: 320px;">
                                    <li class="dropdown-header p-3">
                                        <h6 class="mb-1 fw-bold">
                                            <i class="fas fa-bell me-2 text-primary"></i>Notifications
                                        </h6>
                                        <small class="text-muted"><?= count($notifications) ?> notification(s)</small>
                                    </li>
                                    <li class="px-2">
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php if (!empty($notifications)): ?>
                                                <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                                                    <a href="notifications.php?id=<?= $notification['id'] ?>" 
                                                       class="dropdown-item d-flex align-items-start p-3 mb-2 rounded hover-lift text-decoration-none">
                                                        <div class="flex-shrink-0 me-3">
                                                            <div class="rounded-circle p-2 bg-<?= $notification['type'] === 'urgence' ? 'danger' : 'primary' ?> text-white">
                                                                <i class="fas fa-<?= $notification['type'] === 'urgence' ? 'exclamation-triangle' : 'bell' ?>"></i>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <strong class="small"><?= htmlspecialchars($notification['titre'] ?? 'Notification') ?></strong>
                                                                <span class="small text-muted"><?= date('H:i', strtotime($notification['date'] ?? 'now')) ?></span>
                                                            </div>
                                                            <p class="small text-muted mb-0"><?= htmlspecialchars($notification['contenu'] ?? 'Contenu indisponible') ?></p>
                                                        </div>
                                                    </a>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center py-4 text-muted">
                                                    <i class="fas fa-bell-slash fa-2x mb-3"></i>
                                                    <p class="mb-1 fw-medium">Aucune notification</p>
                                                    <small>Vous serez alerté des nouveaux événements</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <?php if (!empty($notifications)): ?>
                                        <li class="p-3 border-top">
                                            <a href="notifications.php" class="btn btn-outline-primary btn-sm w-100">
                                                <i class="fas fa-list me-1"></i> Voir toutes
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <!-- Menu Utilisateur -->
                            <div class="dropdown">
                                <button id="userDropdownBtnEmp" class="btn btn-glass rounded-pill d-flex align-items-center px-3 py-2" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false" 
                                        aria-label="Menu utilisateur">
                                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;font-weight:700;">
                                        <?= htmlspecialchars($initiale) ?>
                                    </div>
                                    <span class="fw-medium text-white d-none d-md-inline"><?= htmlspecialchars($employe['prenom']) ?></span>
                                    <i class="fas fa-chevron-down ms-2 small text-white dropdown-arrow"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end glass-effect shadow-lg border-0 p-2">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded" href="profil_employe.php">
                                            <i class="fas fa-user text-primary me-3"></i>
                                            <span>Mon profil</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded" href="#">
                                            <i class="fas fa-cog text-primary me-3"></i>
                                            <span>Paramètres</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded" href="historique_pointages.php">
                                            <i class="fas fa-history text-primary me-3"></i>
                                            <span>Historique</span>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider my-2"></li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded text-danger" href="logout.php">
                                            <i class="fas fa-sign-out-alt me-3"></i>
                                            <span>Déconnexion</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Contenu Principal -->
        <main class="main-content">
            <!-- Section Profil -->
            <section class="profile-section fade-in-up">
                <div class="row align-items-center">
                    <div class="col-lg-4 col-xl-3 text-center text-lg-start mb-4 mb-lg-0">
                        <div class="profile-avatar-container">
                               <img src="<?= !empty($photoSrc) ? $photoSrc : 'assets/img/profil.jpg' ?>" 
                                   alt="Photo de <?= htmlspecialchars($employe['prenom']) ?>" 
                                   class="profile-avatar"
                                   onerror="this.src='assets/img/profil.jpg';">
                            
                            <button class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded-circle p-2" 
                                    data-bs-toggle="modal" data-bs-target="#editPhotoModal"
                                    title="Modifier la photo">
                                <i class="fas fa-camera"></i>
                            </button>
                            
                            <span class="online-status position-absolute top-0 start-100 translate-middle" 
                                  title="<?= $employe['statut'] === 'actif' ? 'En ligne' : 'Hors ligne' ?>"></span>
                        </div>
                    </div>
                    
                    <div class="col-lg-8 col-xl-9">
                        <div class="profile-info">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <h2 class="profile-name mb-0">
                                    <?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?>
                                </h2>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="status-badge status-info"><?= htmlspecialchars($employe['poste'] ?? 'Poste') ?></span>
                                    <span class="status-badge status-primary"><?= htmlspecialchars($departement ?? 'Département') ?></span>
                                    <span class="status-badge <?= $employe['statut'] === 'actif' ? 'status-success' : ($employe['statut'] === 'congé' ? 'status-warning' : 'status-danger') ?>">
                                        <i class="fas fa-circle me-1 small"></i><?= ucfirst($employe['statut'] ?? 'inconnu') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="text-muted mb-4">
                                <i class="fas fa-building me-2"></i>
                                <?= htmlspecialchars($employe['entreprise'] ?? 'Xpert Consulting') ?>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-2">
                                <a href="modifier_employe.php?id=<?= (int)$employe['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-user-edit me-1"></i>Modifier le profil
                                </a>
                                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editPasswordModal">
                                    <i class="fas fa-lock me-1"></i>Mot de passe
                                </button>
                                <a href="scan_qr.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-qrcode me-1"></i>Pointer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grille d'informations -->
                <div class="profile-details-grid mt-4">
                    <?php
                    $details = [
                        [
                            'icon' => 'fa-id-card',
                            'label' => 'Matricule',
                            'value' => 'XPERT-' . strtoupper(substr($departement ?? 'XXX', 0, 3)) . ($employe['id'] ?? '00'),
                            'subtext' => 'Identifiant unique'
                        ],
                        [
                            'icon' => 'fa-envelope',
                            'label' => 'Email',
                            'value' => $employe['email'] ?? '--',
                            'subtext' => 'Contact professionnel',
                            'link' => 'mailto:' . ($employe['email'] ?? '')
                        ],
                        [
                            'icon' => 'fa-calendar-day',
                            'label' => 'Embauché le',
                            'value' => !empty($employe['date_creation']) ? date('d/m/Y', strtotime($employe['date_creation'])) : '--/--',
                            'subtext' => !empty($employe['date_creation']) ? 
                                (new DateTime($employe['date_creation']))->diff(new DateTime())->format('%y an(s), %m mois') . ' d\'ancienneté' : 
                                '--'
                        ],
                        [
                            'icon' => 'fa-phone',
                            'label' => 'Téléphone',
                            'value' => $employe['telephone'] ?? '--',
                            'subtext' => 'Contact urgent',
                            'link' => 'tel:' . ($employe['telephone'] ?? '')
                        ],
                        [
                            'icon' => 'fa-map-marker-alt',
                            'label' => 'Adresse',
                            'value' => $employe['adresse'] ?? '--',
                            'subtext' => 'Adresse de correspondance'
                        ],
                        [
                            'icon' => 'fa-clock',
                            'label' => 'Dernier pointage',
                            'value' => !empty($derniers_pointages) ? 
                                ($derniers_pointages[0]['heure'] ?? '--:--') : 
                                '--:--',
                            'subtext' => !empty($derniers_pointages) ? 
                                ucfirst($derniers_pointages[0]['type'] ?? 'inconnu') . ' • ' . date('d/m', strtotime($derniers_pointages[0]['jour'] ?? '')) : 
                                'Aujourd\'hui'
                        ]
                    ];
                    
                    foreach ($details as $detail): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas <?= $detail['icon'] ?>"></i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label fw-semibold text-muted mb-1"><?= $detail['label'] ?></div>
                                <div class="detail-value fw-bold fs-5 mb-1">
                                    <?php if (isset($detail['link'])): ?>
                                        <a href="<?= $detail['link'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($detail['value']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($detail['value']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="detail-subtext small text-muted">
                                    <?= $detail['subtext'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Section Statistiques -->
            <section class="stats-section fade-in-up">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <h3 class="section-title">
                        <i class="fas fa-chart-line me-2 text-primary"></i>Statistiques du mois
                    </h3>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark border"><?= date('F Y') ?></span>
                        <button class="btn btn-outline-secondary btn-sm" id="refreshStats" title="Actualiser">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <div class="employe-stats">
                    <?php
                    // Statistiques calculées
                    $stats = [
                        [
                            'icon' => 'fa-calendar-check',
                            'color' => 'bg-success',
                            'value' => $joursPresents ?? 0,
                            'label' => 'Jours présents',
                            'trend' => ($evolutionJours ?? 0) >= 0 ? 'up' : 'down',
                            'trendValue' => abs($evolutionJours ?? 0) . '% vs mois dernier',
                            'trendClass' => ($evolutionJours ?? 0) >= 0 ? 'text-success' : 'text-danger'
                        ],
                        [
                            'icon' => 'fa-clock',
                            'color' => 'bg-primary',
                            'value' => $totalPointages ?? 0,
                            'label' => 'Pointages',
                            'trend' => 'up',
                            'trendValue' => round(($totalPointages ?? 0) / max($joursPresents ?? 1, 1) * 100) . '% régularité',
                            'trendClass' => 'text-success'
                        ],
                        [
                            'icon' => 'fa-exclamation-triangle',
                            'color' => 'bg-warning',
                            'value' => $joursRetards ?? 0,
                            'label' => 'Retards',
                            'trend' => ($joursRetards ?? 0) > 0 ? 'warning' : 'check',
                            'trendValue' => ($joursRetards ?? 0) > 0 ? 'À améliorer' : 'Parfait',
                            'trendClass' => ($joursRetards ?? 0) > 0 ? 'text-danger' : 'text-success'
                        ],
                        [
                            'icon' => 'fa-user-clock',
                            'color' => 'bg-info',
                            'value' => substr($tempsTravaille ?? '00:00', 0, 5) . 'h',
                            'label' => 'Temps travaillé',
                            'trend' => 'chart',
                            'trendValue' => ($joursPresents ?? 0) * 8 . 'h attendues',
                            'trendClass' => 'text-primary'
                        ]
                    ];
                    
                    foreach ($stats as $stat): ?>
                        <div class="stat-item">
                            <div class="stat-icon <?= $stat['color'] ?> rounded-circle">
                                <i class="fas <?= $stat['icon'] ?>"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?= $stat['value'] ?></div>
                                <div class="stat-label text-muted"><?= $stat['label'] ?></div>
                                <div class="stat-trend <?= $stat['trendClass'] ?> small mt-1">
                                    <i class="fas fa-<?= $stat['trend'] === 'up' ? 'arrow-up' : ($stat['trend'] === 'down' ? 'arrow-down' : ($stat['trend'] === 'warning' ? 'exclamation-triangle' : ($stat['trend'] === 'check' ? 'check-circle' : 'chart-line'))) ?> me-1"></i>
                                    <?= $stat['trendValue'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Résumé mensuel -->
                <div class="stats-summary mt-4 p-3 bg-light rounded border">
                    <div class="row text-center g-3">
                        <div class="col-md-4">
                            <div class="small text-muted mb-1">Taux de présence</div>
                            <div class="fw-bold fs-5 text-success">
                                <?= ($joursPresents ?? 0) > 0 ? round((($joursPresents ?? 0) / date('t')) * 100) : 0 ?>%
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted mb-1">Moyenne quotidienne</div>
                            <div class="fw-bold fs-5 text-primary">
                                <?= ($joursPresents ?? 0) > 0 ? substr($tempsTravaille ?? '00:00', 0, 5) : '00:00' ?>h
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted mb-1">Progression</div>
                            <div class="fw-bold fs-5 <?= ($evolutionJours ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($evolutionJours ?? 0) >= 0 ? '+' : '' ?><?= $evolutionJours ?? 0 ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section Double Colonne -->
            <div class="content-grid">
                <!-- Calendrier -->
                <section class="calendar-section fade-in-up">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>Calendrier de présence
                        </h3>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-secondary" id="prevMonth">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="fw-medium mx-2" id="currentMonth"><?= date('F Y') ?></span>
                            <button class="btn btn-sm btn-outline-secondary" id="nextMonth">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div class="calendar-legend mb-3">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success me-2" style="width: 12px; height: 12px;"></div>
                                <span class="small">Présent</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-warning me-2" style="width: 12px; height: 12px;"></div>
                                <span class="small">Retard</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-danger me-2" style="width: 12px; height: 12px;"></div>
                                <span class="small">Absent</span>
                            </div>
                        </div>
                    </div>

                    <div id="calendar-employe" class="calendar-container">
                        <!-- Le calendrier sera chargé ici -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="text-muted">Chargement du calendrier...</p>
                        </div>
                    </div>

                    <div class="calendar-summary mt-3 p-3 bg-light rounded border">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="fw-bold text-success fs-4"><?= $joursPresents ?? 0 ?></div>
                                <div class="small text-muted">Présents</div>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-warning fs-4"><?= $joursRetards ?? 0 ?></div>
                                <div class="small text-muted">Retards</div>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-danger fs-4"><?= date('t') - ($joursPresents ?? 0) ?></div>
                                <div class="small text-muted">Absents</div>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold text-primary fs-4"><?= date('t') ?></div>
                                <div class="small text-muted">Total</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Pointages Récents -->
                <section class="pointages-section fade-in-up">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-history me-2 text-primary"></i>Activité récente
                        </h3>
                        <a href="historique_pointages.php" class="btn btn-sm btn-outline-primary">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="pointages-list">
                        <?php if (!empty($derniers_pointages)): ?>
                            <?php foreach (array_slice($derniers_pointages, 0, 8) as $pointage): ?>
                                <div class="pointage-item">
                                    <div class="pointage-type">
                                        <div class="pointage-icon <?= ($pointage['type'] ?? 'inconnu') === 'arrivee' ? 'type-arrivee' : 'type-depart' ?>">
                                            <i class="fas fa-<?= ($pointage['type'] ?? 'inconnu') === 'arrivee' ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                                        </div>
                                        <div class="pointage-info">
                                            <div class="pointage-date fw-medium">
                                                <?= !empty($pointage['jour']) ? date('d/m/Y', strtotime($pointage['jour'])) : '--/--/----' ?>
                                                <?php if (!empty($pointage['jour']) && date('Y-m-d', strtotime($pointage['jour'])) === date('Y-m-d')): ?>
                                                    <span class="badge bg-primary ms-2">Aujourd'hui</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="pointage-label text-muted small">
                                                <?= ucfirst($pointage['type'] ?? 'inconnu') ?>
                                                <?php if (($pointage['type'] ?? '') === 'arrivee' && !empty($pointage['heure']) && strtotime($pointage['heure']) > strtotime('09:00:00')): ?>
                                                    <span class="badge bg-warning text-dark ms-2">
                                                        <i class="fas fa-clock me-1"></i>Retard
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pointage-time fw-bold">
                                        <?= $pointage['heure'] ?? '--:--' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-3">Aucun pointage enregistré</p>
                                <a href="scan_qr.php" class="btn btn-primary">
                                    <i class="fas fa-qrcode me-2"></i>Commencer à pointer
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($todayArrivee) || !empty($todayDepart)): ?>
                        <div class="today-summary mt-4 p-3 bg-primary text-white rounded">
                            <h6 class="mb-3">
                                <i class="fas fa-sun me-2"></i>Aujourd'hui
                            </h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="fw-bold fs-4"><?= $todayArrivee ?? '--:--' ?></div>
                                    <div class="small opacity-75">Arrivée</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold fs-4"><?= $todayDepart ?? '--:--' ?></div>
                                    <div class="small opacity-75">Départ</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>

        <!-- Sidebar -->
        <aside class="sidebar-content">
            <!-- Badge d'Accès -->
            <section class="badge-section slide-in-left">
                <div class="badge-header text-center mb-4">
                    <h3 class="section-title mb-2">
                        <i class="fas fa-id-card me-2 text-primary"></i>Badge d'Accès
                    </h3>
                    <div class="text-muted small">
                        XPERT-<?= strtoupper(substr($departement ?? 'XXX', 0, 3)) ?><?= $employe['id'] ?? '00' ?>
                    </div>
                </div>

                <?php if ($badge_actif): ?>
                    <div class="badge-content text-center">
                        <!-- QR Code -->
                        <div class="badge-qr-container">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($token) ?>" 
                                 class="badge-qr" 
                                 alt="QR Code du badge">
                            <div class="qr-overlay rounded d-flex align-items-center justify-content-center">
                                <i class="fas fa-expand text-white fa-lg"></i>
                            </div>
                        </div>

                        <!-- Statut -->
                        <div class="badge-status mb-3">
                            <span class="status-badge status-success mb-2">
                                <i class="fas fa-check-circle me-1"></i>Badge Actif
                            </span>
                            <?php if (!empty($expiresAt)): ?>
                                <div class="small <?= (strtotime($expiresAt) - time() < 3600) ? 'text-danger fw-bold pulse' : 'text-muted' ?>">
                                    <i class="fas fa-clock me-1"></i>
                                    Valide jusqu'à <?= date('H:i', strtotime($expiresAt)) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="badge-actions d-grid gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#badgeModal">
                                <i class="fas fa-expand me-2"></i>Voir en grand
                            </button>
                            <button class="btn btn-outline-secondary" onclick="printBadge()">
                                <i class="fas fa-print me-2"></i>Imprimer
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-badge text-center py-4">
                        <i class="fas fa-id-card fa-4x text-muted mb-3"></i>
                        <h4 class="text-dark mb-2">Aucun badge actif</h4>
                        <p class="text-muted mb-4">Générez un nouveau badge pour pointer</p>
                        
                        <button id="demanderBadgeBtn" class="btn btn-primary w-100 mb-3" onclick="generateBadge()">
                            <i class="fas fa-sync-alt me-2"></i>Demander un badge
                        </button>
                        
                        <div id="badge-loader" class="text-center mt-3" style="display:none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <div class="mt-2">Génération en cours...</div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Informations Complémentaires -->
            <section class="info-section slide-in-left">
                <div class="section-header mb-4">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle me-2 text-primary"></i>Informations
                    </h3>
                </div>

                <div class="info-list">
                    <?php
                    $infos = [
                        [
                            'icon' => 'fa-medal',
                            'color' => 'bg-primary',
                            'label' => 'Ancienneté',
                            'value' => $anciennete ?? '--'
                        ],
                        [
                            'icon' => 'fa-calendar-check',
                            'color' => 'bg-success',
                            'label' => 'Congés restants',
                            'value' => ($joursConges ?? 25) . ' jours'
                        ],
                        [
                            'icon' => 'fa-users',
                            'color' => 'bg-info',
                            'label' => 'Équipe',
                            'value' => ($nbCollegues ?? 0) . ' collègue' . (($nbCollegues ?? 0) > 1 ? 's' : '')
                        ],
                        [
                            'icon' => 'fa-plane',
                            'color' => 'bg-purple',
                            'label' => 'Prochain congé',
                            'value' => $prochainConge ? 
                                date('d/m', strtotime($prochainConge['date_debut'])) . '-' . date('d/m', strtotime($prochainConge['date_fin'])) : 
                                'Aucun'
                        ],
                        [
                            'icon' => 'fa-chart-line',
                            'color' => 'bg-indigo',
                            'label' => 'Performance',
                            'value' => ($taux ?? 0) . '% présence'
                        ]
                    ];
                    
                    foreach ($infos as $info): ?>
                        <div class="info-item">
                            <div class="info-icon <?= $info['color'] ?>">
                                <i class="fas <?= $info['icon'] ?>"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label fw-medium text-muted small"><?= $info['label'] ?></div>
                                <div class="info-value fw-bold"><?= $info['value'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </div>

    <?php
// Sécurisation du badgeToken
$modalToken      = $badgeToken['token']       ?? '';
$modalExpiresAt  = $badgeToken['expires_at']  ?? null;

// URL QR sécurisé
$modalQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($modalToken);
?>

    <!-- Modal Badge -->
    <div class="modal fade" id="badgeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content glass-effect">

                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-id-card me-2 text-primary"></i>Badge d'Accès
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>

                <div class="modal-body text-center py-4">

                    <?php if ($badge_actif): ?>
                        
                        <!-- QR Code -->
                        <img src="<?= $modalQrUrl ?>" 
                            class="img-fluid rounded mb-3" 
                            alt="QR Code du badge">

                        <!-- Nom employé -->
                        <h6 class="fw-bold mb-1">
                            <?= htmlspecialchars(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? '')) ?>
                        </h6>

                        <!-- Poste -->
                        <p class="text-muted mb-2">
                            <?= htmlspecialchars($employe['poste'] ?? '---') ?>
                        </p>

                        <!-- Expiration -->
                        <p class="small text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Valide jusqu'à 
                            <?= $modalExpiresAt ? date('H:i', strtotime($modalExpiresAt)) : '--:--' ?>
                        </p>

                    <?php else: ?>

                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Aucun badge actif
                        </div>

                    <?php endif; ?>

                </div>

                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>

                    <?php if ($badge_actif): ?>
                        <button type="button" class="btn btn-primary" onclick="printBadge()">
                            <i class="fas fa-print me-1"></i> Imprimer
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- ... votre contenu HTML existant ... -->

<!-- Justification modal is handled on the scanner page (scan_qr.php) via JS; removed server-side auto-modal here to avoid duplicate UI -->

<?php 
// Inclure le pied de page
include 'partials/footer.php'; 
?>

    <!-- Scripts -->
    <script>
        // Masquer le loader et initialiser les interactions de la page de façon robuste
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const loader = document.getElementById('page-loader');
                if (loader) {
                    loader.style.opacity = '0';
                    setTimeout(() => { loader.style.display = 'none'; }, 500);
                }
            }, 500);

            // Initialiser le calendrier uniquement quand FullCalendar est disponible
            (function ensureCalendarReady(){
                if (typeof FullCalendar !== 'undefined' && typeof FullCalendar.Calendar !== 'undefined') {
                    try { initEmployeeCalendar(); } catch(e) { console.error('Erreur initialisation calendrier:', e); }
                } else {
                    setTimeout(ensureCalendarReady, 150);
                }
            })();

            // Tooltips Bootstrap - safe init
            try {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (el) {
                    try { new bootstrap.Tooltip(el); } catch (e) {}
                });
            } catch (e) {}

            // Rafraîchir les stats (avec garde sur l'élément)
            const refreshBtn = document.getElementById('refreshStats');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const btn = this;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                        fetch('refresh_stats.php')
                            .then(response => response.json())
                            .then(data => { console.log('Stats rafraîchies:', data); })
                            .catch(() => {});
                    }, 1000);
                });
            }

            // Navigation calendrier - garde sur éléments
            const prevBtn = document.getElementById('prevMonth');
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    try {
                        if (window.employeeCalendar && typeof window.employeeCalendar.prev === 'function') {
                            window.employeeCalendar.prev();
                        }
                    } catch (e) { console.error(e); }

                    // fermer overlays/dropdowns si besoin
                    const overlay = document.getElementById('dropdownOverlay');
                    if (overlay) overlay.classList.remove('active');
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => menu.classList.remove('show'));
                });
            }
            const nextBtn = document.getElementById('nextMonth');
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    try {
                        if (window.employeeCalendar && typeof window.employeeCalendar.next === 'function') {
                            window.employeeCalendar.next();
                        }
                    } catch (e) { console.error(e); }

                    const overlay = document.getElementById('dropdownOverlay');
                    if (overlay) overlay.classList.remove('active');
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => menu.classList.remove('show'));
                });
            }
        });

        // Calendar initialization is provided by `assets/js/calendar-employe.js` (module)
        
        function generateBadge() {
            const btn = document.getElementById('demanderBadgeBtn');
            const loader = document.getElementById('badge-loader');
            
            btn.style.display = 'none';
            loader.style.display = 'block';
            
            setTimeout(() => {
                // AJAX pour générer le badge
                fetch('generate_badge.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            }, 1500);
        }
        
        function printBadge() {
            window.print();
        }
    </script>
    
    <?php if (file_exists('partials/modals_employe.php')): ?>
        <?php include 'partials/modals_employe.php'; ?>
    <?php endif; ?>

    <!-- Note: badge expiry check is scheduled in client script (`assets/js/script.js`) -->
</script>
<script>
// Ensure dropdown overlay and open menus are cleared when navigating months
document.addEventListener('DOMContentLoaded', function() {
    // remove any lingering overlays on load
    const overlay = document.getElementById('dropdownOverlay');
    if (overlay) overlay.classList.remove('active');

    document.querySelectorAll('.calendar-nav a').forEach(function(link) {
        link.addEventListener('click', function() {
            // hide overlay and any open dropdowns
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu){
                menu.classList.remove('show');
            });
            document.querySelectorAll('.dropdown-arrow').forEach(function(arrow){
                arrow.style.transform = 'rotate(0deg)';
            });
        });
    });
});
</script>
<script>
// Correction affichage du type de badge et de l'ID même si badge inconnu
document.addEventListener('DOMContentLoaded', function() {
    // Si le type est "inconnu" mais l'employé existe, afficher l'ID
    const badgeType = document.getElementById('badge-type-label');
    const badgeId = document.getElementById('badge-employee-id');
    if (badgeType && badgeType.textContent.trim() === 'inconnu' && badgeId) {
        badgeId.style.opacity = 1;
    }
});
</script>
<script>
// Génération immédiate du badge via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('demanderBadgeBtn');
    if (btn) {
        btn.addEventListener('click', function() {
            btn.disabled = true;
            document.getElementById('badge-success-message').innerHTML = '';
            document.getElementById('badge-loader').style.display = 'block';
            fetch('profil_employe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'demander_badge=1&ajax=1'
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('badge-loader').style.display = 'none';
                if (data.success && data.token && data.expires_at) {
                    document.getElementById('badge-success-message').innerHTML = '<div class="alert alert-success mt-2">Badge généré avec succès !</div>';
                    var infoMsg = document.getElementById('badge-info-message');
                    if (infoMsg) infoMsg.style.display = 'none';
                    // Remplacer la zone no-badge par le badge actif
                    var noBadge = document.querySelector('.no-badge');
                    if (noBadge) {
                        noBadge.innerHTML = `
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(data.token)}" 
                                 class="badge-qr mb-1" 
                                 alt="Badge d'accès"
                                 data-bs-toggle="modal" 
                                 data-bs-target="#badgeModal">
                            <div class="badge-label small fw-bold">Badge actif</div>
                            <div class="badge-expiry">
                                Valide jusqu'au ${data.expires_at_fr || data.expires_at}
                            </div>
                            <div id="badge-timer" class="small fw-bold mt-1"></div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#badgeModal">
                                    <i class="fas fa-expand me-1"></i> Voir en grand
                                </button>
                                <button class="btn btn-sm btn-outline-success flex-grow-1">
                                    <i class="fas fa-print me-1"></i> Imprimer
                                </button>
                            </div>
                        `;
                    }
                } else {
                    document.getElementById('badge-success-message').innerHTML = '<div class="alert alert-danger mt-2">' + (data.message || 'Erreur lors de la génération du badge') + '</div>';
                    btn.disabled = false;
                }
            })
            .catch(() => {
                document.getElementById('badge-loader').style.display = 'none';
                document.getElementById('badge-success-message').innerHTML = '<div class="alert alert-danger mt-2">Erreur réseau</div>';
                btn.disabled = false;
            });
        });
    }
});
// Gestion du dropdown
document.addEventListener('DOMContentLoaded', function() {
    const dropdownBtn = document.getElementById('userDropdownBtnEmp');
    if (dropdownBtn) {
        const dropdown = dropdownBtn.closest('.dropdown');
        const dropdownMenu = dropdown.querySelector('.dropdown-menu');
        const dropdownArrow = dropdown.querySelector('.dropdown-arrow');
        const overlay = document.getElementById('dropdownOverlay');
    
    // Vérifier que Bootstrap est chargé
    if (typeof bootstrap !== 'undefined') {
        // Initialiser le dropdown Bootstrap
        const bsDropdown = new bootstrap.Dropdown(dropdownBtn);
        
        // Animation de la flèche
        dropdownBtn.addEventListener('show.bs.dropdown', function() {
            dropdownArrow.style.transform = 'rotate(180deg)';
            dropdownArrow.style.transition = 'transform 0.3s ease';
            
            // Afficher l'overlay sur mobile
            if (window.innerWidth < 992) {
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
        
        dropdownBtn.addEventListener('hide.bs.dropdown', function() {
            dropdownArrow.style.transform = 'rotate(0deg)';
            
            // Cacher l'overlay sur mobile
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Fermer le dropdown en cliquant sur l'overlay (mobile)
        overlay.addEventListener('click', function() {
            bsDropdown.hide();
        });
        
        // Fermer le dropdown en cliquant en dehors (desktop)
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target) && dropdownMenu.classList.contains('show')) {
                bsDropdown.hide();
            }
        });
        
        // Empêcher la fermeture quand on clique dans le dropdown
        dropdownMenu.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        } else {
            console.error('Bootstrap non chargé');

            // Fallback si Bootstrap n'est pas chargé
            dropdownBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const isOpen = dropdownMenu.classList.contains('show');

                // Fermer tous les autres dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    if (menu !== dropdownMenu) {
                        menu.classList.remove('show');
                        const prev = menu.previousElementSibling && menu.previousElementSibling.querySelector('.dropdown-arrow');
                        if (prev) prev.style.transform = 'rotate(0deg)';
                    }
                });

                // Toggle ce dropdown
                dropdownMenu.classList.toggle('show');
                dropdownArrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';

                // Gérer overlay mobile
                if (window.innerWidth < 992) {
                    if (!isOpen) {
                        overlay && overlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        overlay && overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });

            // Fermer au clic extérieur
            document.addEventListener('click', function(event) {
                if (!dropdown.contains(event.target) && dropdownMenu.classList.contains('show')) {
                    dropdownMenu.classList.remove('show');
                    dropdownArrow.style.transform = 'rotate(0deg)';
                    overlay && overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // Overlay mobile
            overlay && overlay.addEventListener('click', function() {
                dropdownMenu.classList.remove('show');
                dropdownArrow.style.transform = 'rotate(0deg)';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Gérer le redimensionnement
        window.addEventListener('resize', function() {
            const overlayEl = document.getElementById('dropdownOverlay');
            if (window.innerWidth >= 992) {
                overlayEl && overlayEl.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    } // end if dropdownBtn
});
</script>

</body>
</html>