<?php
require_once 'src/config/bootstrap.php';
require_once 'src/services/AuthService.php';
// toggle_admin_status.php - Version simplifiée et déboguée

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Définir l'en-tête JSON
header('Content-Type: application/json; charset=utf-8');

// Journaliser pour débogage
error_log("=== toggle_admin_status.php appelé ===");
error_log("Méthode: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Session: " . print_r($_SESSION, true));

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("❌ Méthode non POST");
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérifier la session
if (!isset($_SESSION['user_id'])) {
    error_log("❌ Session non définie");
    echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    error_log("❌ Pas Super Admin");
    echo json_encode(['success' => false, 'message' => 'Accès refusé. Droits insuffisants.']);
    exit;
}

// Récupérer les données
$admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

error_log("Admin ID: $admin_id, Action: $action");

// Validation simple
if ($admin_id <= 0) {
    error_log("❌ Admin ID invalide");
    echo json_encode(['success' => false, 'message' => 'ID administrateur invalide.']);
    exit;
}

if (!in_array($action, ['activate', 'deactivate'])) {
    error_log("❌ Action invalide");
    echo json_encode(['success' => false, 'message' => 'Action invalide.']);
    exit;
}

// Charger le bootstrap de l'application (session, constantes, $pdo, autoloader)
$attempts = [];
$candidates = [
    __DIR__ . '/src/config/bootstrap.php',
    __DIR__ . '/../src/config/bootstrap.php',
    __DIR__ . '/../../src/config/bootstrap.php',
    dirname(__DIR__) . '/src/config/bootstrap.php'
];

$loaded = false;
foreach ($candidates as $path) {
    $attempts[] = $path;
    if (file_exists($path) && is_readable($path)) {
        require_once $path;
        error_log("✅ Chargement bootstrap depuis: $path");
        $loaded = true;
        break;
    } else {
        error_log("ℹ️ Tentative bootstrap path introuvable: $path");
    }
}

if (!$loaded) {
    error_log("❌ Aucun bootstrap trouvé, chemins testés: " . print_r($attempts, true));
    echo json_encode(['success' => false, 'message' => 'Configuration manquante (bootstrap introuvable).']);
    exit;
}

// S'assurer que la DB est disponible
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("❌ PDO non disponible après inclusion du bootstrap");
    echo json_encode(['success' => false, 'message' => 'Configuration manquante (BD non disponible).']);
    exit;
}

try {
    // Vérifier la connexion PDO
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("❌ PDO non disponible");
        throw new Exception('Connexion base de données indisponible');
    }
    
    // Tester la connexion
    $pdo->query("SELECT 1");
    error_log("✅ Connexion PDO OK");
    
    // 1. Récupérer l'admin
    $stmt = $pdo->prepare("SELECT id, prenom, nom, role, statut FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        error_log("❌ Admin #$admin_id non trouvé");
        echo json_encode(['success' => false, 'message' => 'Administrateur non trouvé.']);
        exit;
    }
    
    error_log("Admin trouvé: " . print_r($admin, true));
    
    // 2. Vérifier les permissions
    if ($admin['role'] === 'super_admin') {
        error_log("❌ Tentative de modifier Super Admin");
        echo json_encode(['success' => false, 'message' => 'Impossible de modifier un Super Administrateur.']);
        exit;
    }
    
    // 3. Déterminer le nouveau statut
    $new_status = ($action === 'activate') ? 'actif' : 'inactif';
    error_log("Nouveau statut: $new_status (ancien: " . ($admin['statut'] ?? 'non défini') . ")");
    
    // 4. Vérifier si le statut change réellement
    if (isset($admin['statut']) && $admin['statut'] === $new_status) {
        $status_text = ($new_status === 'actif') ? 'déjà actif' : 'déjà inactif';
        error_log("⚠️ Statut déjà $status_text");
        echo json_encode([
            'success' => false, 
            'message' => "Le compte est $status_text."
        ]);
        exit;
    }
    
    // 5. Mettre à jour le statut
    $sql = "UPDATE admins SET statut = :statut, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':statut' => $new_status,
        ':id' => $admin_id
    ];
    
    error_log("Exécution SQL: $sql avec params: " . print_r($params, true));
    
    $result = $stmt->execute($params);
    $rowCount = $stmt->rowCount();
    
    error_log("Résultat update: " . ($result ? 'OK' : 'ERREUR'));
    error_log("Lignes affectées: $rowCount");
    
    if (!$result || $rowCount === 0) {
        error_log("❌ Échec mise à jour");
        throw new Exception('Aucune ligne mise à jour');
    }
    
    // 6. Préparer la réponse
    $admin_name = $admin['prenom'] . ' ' . $admin['nom'];
    $action_text = ($action === 'activate') ? 'activé' : 'désactivé';
    
    $response = [
        'success' => true,
        'message' => "Le compte de $admin_name a été $action_text avec succès.",
        'data' => [
            'admin_id' => $admin_id,
            'new_status' => $new_status,
            'admin_name' => $admin_name
        ]
    ];
    
    error_log("✅ Réponse succès: " . print_r($response, true));
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("❌ Erreur PDO: " . $e->getMessage());
    error_log("Code erreur: " . $e->getCode());
    error_log("Trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("❌ Erreur générale: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}

error_log("=== Fin toggle_admin_status.php ===");