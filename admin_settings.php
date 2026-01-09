<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/src/config/bootstrap.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF token: generate on GET (or if missing); validate on POST
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Vérification CSRF (le token attendu a été créé lors du GET)
  if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Erreur de sécurité CSRF');
    }
    
    // Traitement des données du formulaire
    $settings = [
        'theme' => $_POST['theme'] ?? 'clair',
        'notifications' => isset($_POST['notifications']) ? 1 : 0,
        'notif_mail' => isset($_POST['notif_mail']) ? 1 : 0,
        'animations' => isset($_POST['animations']) ? 1 : 0,
        'table_compact' => isset($_POST['table_compact']) ? 1 : 0,
        'sidebar_mini' => isset($_POST['sidebar_mini']) ? 1 : 0,
        'dashboard_graph' => isset($_POST['dashboard_graph']) ? 1 : 0,
        'dashboard_cards' => isset($_POST['dashboard_cards']) ? 1 : 0,
        'dashboard_welcome' => isset($_POST['dashboard_welcome']) ? 1 : 0,
        'font_large' => isset($_POST['font_large']) ? 1 : 0,
        'contrast' => isset($_POST['contrast']) ? 1 : 0,
        'session_timeout' => $_POST['session_timeout'] ?? 30,
        'password' => $_POST['password'] ?? '',
        'two_factor' => isset($_POST['two_factor']) ? 1 : 0,
        'language' => $_POST['language'] ?? 'fr',
        'timezone' => $_POST['timezone'] ?? 'Europe/Paris'
    ];
    
  // Si action == recommander, appliquer un preset recommandé
  if (isset($_POST['action']) && $_POST['action'] === 'recommander') {
    $settings = [
      'theme' => 'clair',
      'notifications' => 1,
      'notif_mail' => 0,
      'animations' => 1,
      'table_compact' => 0,
      'sidebar_mini' => 0,
      'dashboard_graph' => 1,
      'dashboard_cards' => 1,
      'dashboard_welcome' => 1,
      'font_size' => 100,
      'selected_font' => 'system-ui',
      'contrast' => 0,
      'session_timeout' => 30,
      'two_factor' => 0,
      'language' => 'fr',
      'timezone' => 'Europe/Paris'
    ];
  }

  // Sauvegarde réelle en base via le modèle Settings
  try {
    if (class_exists('Settings')) {
      $settingsModel = new Settings($pdo);
      foreach ($settings as $key => $value) {
        // Normaliser les valeurs (checkbox => 1/0)
        if ($value === true) $value = 1;
        if ($value === false) $value = 0;
        // Sauvegarde via REPLACE INTO (Settings::set)
        $settingsModel->set($key, is_scalar($value) ? $value : json_encode($value));
      }
    $success = true;
    }
  } catch (Throwable $e) {
    $success = false;
  }

  // Préparer la réponse AJAX si nécessaire
  $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => (bool)$success, 'settings' => $settings]);
    exit();
  }
    
  // Régénérer le token CSRF après traitement (prévenir la réutilisation)
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $csrfToken = $_SESSION['csrf_token'];
} else {
  // Sur GET, s'assurer qu'un token est présent
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrfToken = $_SESSION['csrf_token'];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres Admin | Xpert Pro</title>
    
    <!-- Framework Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Icônes Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS personnalisé -->
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body class="d-flex flex-column min-vh-100">
  <?php include 'src/views/partials/sidebar_canonique.php'; ?>
  <?php 
    $pageHeader = 'Paramètres';
    $pageDescription = "Configuration de l'interface et des préférences";
    include 'src/views/partials/header.php';
  ?>

  <!-- Contenu principal -->
  <main class="container my-auto py-5 content-with-sidebar">
    <div class="settings-card bg-white p-4 p-md-5 rounded-4 shadow-sm mx-auto">
      <!-- En-tête de la carte -->
      <div class="text-center mb-5">
        <div class="avatar-container position-relative d-inline-block">
          <img src="assets/img/xpertpro.png" alt="Logo Xpert Pro" class="avatar shadow" width="80" height="80">
          <button class="btn btn-sm btn-outline-primary avatar-edit-btn" aria-label="Modifier l'avatar">
            <i class="fas fa-pencil-alt"></i>
          </button>
        </div>
        <h1 class="h3 mb-1 mt-3 fw-bold">Paramètres de l'interface</h1>
        <p class="text-muted mb-0">Personnalisez votre expérience d'administration</p>
      </div>

      <!-- Message de succès -->
      <?php if ($success): ?>
      <div class="alert alert-success d-flex align-items-center" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <div>Vos préférences ont été enregistrées avec succès</div>
      </div>
      <?php endif; ?>

      <!-- Formulaire de paramètres -->
      <form method="post" id="settings-form" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="d-flex justify-content-end gap-2 mb-3">
          <button type="submit" name="action" value="recommander" class="btn btn-outline-secondary" id="apply-recommended">Appliquer les recommandations</button>
          <button type="submit" name="action" value="save" class="btn btn-primary">Sauvegarder</button>
        </div>

        <!-- Section Thème -->
        <fieldset class="mb-4">
          <legend class="h5 fw-semibold mb-3">Thème de l'interface</legend>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check card-theme-option">
              <input class="form-check-input" type="radio" name="theme" id="theme-clair" value="clair" checked>
              <label class="form-check-label d-flex align-items-center" for="theme-clair">
                <i class="fas fa-sun me-2 text-warning"></i>Clair
              </label>
            </div>
            <div class="form-check card-theme-option">
              <input class="form-check-input" type="radio" name="theme" id="theme-sombre" value="sombre">
              <label class="form-check-label d-flex align-items-center" for="theme-sombre">
                <i class="fas fa-moon me-2 text-primary"></i>Sombre
              </label>
            </div>
            <div class="form-check card-theme-option">
              <input class="form-check-input" type="radio" name="theme" id="theme-auto" value="auto">
              <label class="form-check-label d-flex align-items-center" for="theme-auto">
                <i class="fas fa-adjust me-2 text-secondary"></i>Automatique
              </label>
            </div>
          </div>
        </fieldset>

        <!-- Section Notifications -->
        <fieldset class="mb-4">
          <legend class="h5 fw-semibold mb-3">Préférences de notifications</legend>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="notif-switch" name="notifications" checked>
            <label class="form-check-label" for="notif-switch">Notifications système</label>
            <div class="form-text">Recevez des alertes importantes dans l'interface</div>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="notif-mail" name="notif_mail">
            <label class="form-check-label" for="notif-mail">Notifications par email</label>
            <div class="form-text">Activez pour recevoir des copies par email</div>
          </div>
        </fieldset>

        <!-- Section Affichage -->
        <fieldset class="mb-4">
          <legend class="h5 fw-semibold mb-3">Affichage & Expérience</legend>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="anim-switch" name="animations" checked>
                <label class="form-check-label" for="anim-switch">Animations</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="table-switch" name="table_compact">
                <label class="form-check-label" for="table-switch">Tableaux compacts</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sidebar-mini" name="sidebar_mini">
                <label class="form-check-label" for="sidebar-mini">Menu réduit</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="dashboard-graph" name="dashboard_graph" checked>
                <label class="form-check-label" for="dashboard-graph">Graphiques</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="dashboard-cards" name="dashboard_cards" checked>
                <label class="form-check-label" for="dashboard-cards">Cartes animées</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="dashboard-welcome" name="dashboard_welcome">
                <label class="form-check-label" for="dashboard-welcome">Message d'accueil</label>
              </div>
            </div>
          </div>
        </fieldset>

<!-- Section Accessibilité -->
<fieldset class="mb-4">
  <legend class="h5 fw-semibold mb-3">Accessibilité</legend>
  
  <!-- Taille de la police -->
  <div class="mb-4">
    <label class="form-label">Taille de la police</label>
    <div class="d-flex align-items-center gap-3 mb-2">
      <i class="fas fa-font text-muted"></i>
      <input type="range" class="form-range" id="font-size-slider" min="80" max="150" step="5" value="100">
      <span id="font-size-value" class="badge bg-primary">100%</span>
    </div>
    <div class="form-text">Ajustez la taille du texte pour un meilleur confort de lecture</div>
    
    <!-- Aperçu de la taille -->
    <div class="preview-box mt-3 p-3 border rounded">
      <p id="font-size-preview" class="mb-0">Ceci est un exemple de texte qui changera de taille en temps réel.</p>
    </div>
  </div>
  
  <!-- Choix de police -->
  <div class="mb-4">
    <label class="form-label">Police d'écriture</label>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="card font-preview-card" data-font="system-ui">
          <div class="card-body">
            <h6 class="card-title">Par défaut</h6>
            <p class="mb-0" style="font-family: system-ui">Lorem ipsum dolor sit amet</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card font-preview-card" data-font="Arial, sans-serif">
          <div class="card-body">
            <h6 class="card-title">Arial</h6>
            <p class="mb-0" style="font-family: Arial">Lorem ipsum dolor sit amet</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card font-preview-card" data-font="'Open Sans', sans-serif">
          <div class="card-body">
            <h6 class="card-title">Open Sans</h6>
            <p class="mb-0" style="font-family: 'Open Sans'">Lorem ipsum dolor sit amet</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card font-preview-card" data-font="'Comic Sans MS', cursive">
          <div class="card-body">
            <h6 class="card-title">Comic Sans</h6>
            <p class="mb-0" style="font-family: 'Comic Sans MS'">Lorem ipsum dolor sit amet</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card font-preview-card" data-font="'Times New Roman', serif">
          <div class="card-body">
            <h6 class="card-title">Times New Roman</h6>
            <p class="mb-0" style="font-family: 'Times New Roman'">Lorem ipsum dolor sit amet</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card font-preview-card" data-font="'Courier New', monospace">
          <div class="card-body">
            <h6 class="card-title">Courier New</h6>
            <p class="mb-0" style="font-family: 'Courier New'">Lorem ipsum dolor sit amet</p>
          </div>
        </div>
      </div>
    </div>
    <input type="hidden" name="selected_font" id="selected-font" value="system-ui">
  </div>
  
  <!-- Options d'accessibilité -->
  <div class="row">
    <div class="col-md-6">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="contrast" name="contrast">
        <label class="form-check-label" for="contrast">Mode contraste élevé</label>
        <div class="form-text">Augmente le contraste des couleurs</div>
      </div>
      
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="reduce-motion" name="reduce_motion">
        <label class="form-check-label" for="reduce-motion">Réduire les animations</label>
        <div class="form-text">Désactive les effets de mouvement</div>
      </div>
    </div>
    
    <div class="col-md-6">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="dark-mode" name="dark_mode">
        <label class="form-check-label" for="dark-mode">Mode sombre forcé</label>
        <div class="form-text">Active le mode sombre en permanence</div>
      </div>
      
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="cursor-size" name="cursor_size">
        <label class="form-check-label" for="cursor-size">Curseur agrandi</label>
        <div class="form-text">Augmente la taille du curseur</div>
      </div>
    </div>
  </div>
  
  <!-- Bouton de réinitialisation -->
  <div class="mt-3">
    <button type="button" id="reset-accessibility" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-redo me-1"></i>Réinitialiser les paramètres d'accessibilité
    </button>
  </div>
</fieldset>

        <!-- NOUVEAUX PARAMÈTRES ESSENTIELS -->
        
        <!-- Section Sécurité -->
        <fieldset class="mb-4">
          <legend class="h5 fw-semibold mb-3">Sécurité</legend>
          
          <div class="form-group">
            <label for="password" class="form-label">Modifier le mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Nouveau mot de passe">
            <div class="password-strength" id="password-strength"></div>
          </div>
          
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="two-factor-toggle" name="two_factor">
            <label class="form-check-label" for="two-factor-toggle">Authentification à deux facteurs</label>
            <div class="form-text">Ajoutez une couche de sécurité supplémentaire à votre compte</div>
            <div id="2fa-qrcode" class="mt-2"></div>
          </div>
          
          <div class="form-group">
            <label class="form-label">Délai d'expiration de session</label>
            <div class="d-flex align-items-center gap-3">
              <span id="session-timeout-value">30 minutes</span>
              <input type="range" class="session-timeout-slider" id="session-timeout" name="session_timeout" min="5" max="120" step="5" value="30">
            </div>
            <div class="form-text">Détermine après combien de temps d'inactivité vous serez déconnecté</div>
          </div>
        </fieldset>
        
        <!-- Section Langue et Région -->
        <fieldset class="mb-4">
          <legend class="h5 fw-semibold mb-3">Langue & Région</legend>
          
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="language" class="form-label">Langue</label>
                <select class="form-select" id="language" name="language">
                  <option value="fr" selected>Français</option>
                  <option value="en">English</option>
                  <option value="es">Español</option>
                  <option value="de">Deutsch</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="timezone" class="form-label">Fuseau horaire</label>
                <select class="form-select" id="timezone" name="timezone">
                  <option value="Europe/Paris" selected>Europe/Paris</option>
                  <option value="Europe/London">Europe/London</option>
                  <option value="America/New_York">America/New York</option>
                  <option value="Asia/Tokyo">Asia/Tokyo</option>
                </select>
              </div>
            </div>
          </div>
        </fieldset>
        
        <!-- Section Confidentialité -->
        <fieldset class="mb-4">
          <legend class="h5 fw-semibold mb-3">Confidentialité</legend>
          
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="data-sharing" name="data_sharing">
            <label class="form-check-label" for="data-sharing">Partager les données d'utilisation</label>
            <div class="form-text">Aidez-nous à améliorer le produit en partageant des données anonymes</div>
          </div>
          
          <div class="form-group">
            <button type="button" class="btn btn-outline-primary" id="export-data-btn">
              <i class="fas fa-download me-2"></i>Exporter mes données
            </button>
            <div class="form-text">Téléchargez une copie de toutes vos données personnelles</div>
          </div>
        </fieldset>

        <!-- Boutons d'action -->
        <div class="d-grid gap-3 d-md-flex justify-content-md-end mt-5">
          <button type="reset" class="btn btn-outline-secondary">
            <i class="fas fa-undo me-2"></i>Réinitialiser
          </button>
          <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save me-2"></i>Enregistrer
          </button>
        </div>
      </form>
    </div>
  </main>

  <!-- Pied de page -->
  <footer class="mt-auto py-4 bg-light border-top">
    <div class="container text-center">
      <div class="d-flex flex-wrap justify-content-center gap-3 mb-3">
        <a href="mailto:support@xpertpro.com" class="text-decoration-none">
          <i class="fas fa-life-ring me-1"></i> Support technique
        </a>
        <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#faqModal">
          <i class="fas fa-question-circle me-1"></i> FAQ
        </a>
        <a href="privacy.php" class="text-decoration-none">
          <i class="fas fa-shield-alt me-1"></i> Confidentialité
        </a>
      </div>
      <p class="small text-muted mb-0">Version 2.1.0 &copy; <?= date('Y') ?> Xpert Pro</p>
    </div>
  </footer>

  <!-- Modal FAQ -->
  <div class="modal fade" id="faqModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="faqModalLabel">
            <i class="fas fa-question-circle me-2"></i>Centre d'aide
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="accordion" id="faqAccordion">
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                  Comment changer mon mot de passe ?
                </button>
              </h3>
              <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Accédez à <strong>Mon compte > Sécurité</strong> pour modifier votre mot de passe.
                </div>
              </div>
            </div>
            <!-- Autres questions... -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <!-- bootstrap loaded from footer -->
  <script src="js/settings.js"></script>
</body>
</html>