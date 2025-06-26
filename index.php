<?php
require_once 'db.php'; // Assurez-vous que ce fichier est inclus pour la connexion à la base de données
require_once 'BadgeManager.php'; // Assurez-vous que BadgeManager est également inclus
session_start();

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

if (!isset($_SESSION['employe_id'])) {
    header("Location: login.php");
    exit();
}

/**
 * Gestion complète des badges avec sécurité renforcée
 */

// Initialisation
$employe_id = $_SESSION['employe_id'];

try {
    // Récupérer les informations de l'employé et du badge
    $stmt = $pdo->prepare("
        SELECT e.*, 
               b.token AS token, 
               b.expires_at AS badge_expiry
        FROM employes e
        LEFT JOIN badge_tokens b ON e.id = b.employe_id
        WHERE e.id = ?
        AND b.status = 'active'
        AND b.expires_at > NOW()
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$employe_id]);
    $employe = $stmt->fetch();

    // Vérifiez si l'employé a un badge actif
    $badge_actif = !empty($employe['token']) && strtotime($employe['expires_at']) > time();
    if (!$employe || empty($employe['token'])) {
        echo "Aucun badge actif trouvé.";
        exit();
    }

    // Vérifier si nous avons besoin d'un nouveau badge
    $needsNewBadge = false;
    if (strtotime($employe['badge_expiry']) < time()) {
        $needsNewBadge = true;
    }

    // Déterminer le prochain type de pointage
    $next_check_type = BadgeManager::getNextCheckinType($employe['last_check_type']);

    // Générer un nouveau badge si nécessaire
    if ($needsNewBadge) {
        // Utiliser la méthode de BadgeManager pour générer un nouveau badge
        $new_badge = BadgeManager::generateBadgeToken($employe_id, $pdo, $next_check_type);
        $employe['token'] = $new_badge['token_hash'];
        $employe['badge_expiry'] = $new_badge['expires_at'];
    }

    // Préparation des données pour la vue
    $departement = ucfirst(str_replace('depart_', '', $employe['departement']));
    $date_embauche = $employe['date_embauche'] ? date('d/m/Y', strtotime($employe['date_embauche'])) : 'Non disponible';
    $badge_created = $employe['badge_created'] ? date('d/m H:i', strtotime($employe['badge_created'])) : 'Nouveau';
    $employee_id_display = "XPERT+" . strtoupper(substr($employe['departement'], 0, 3)) . $employe['id'];

    // Formater la date d'expiration pour l'affichage
    $expiration_display = "Non disponible";
    if (!empty($employe['badge_expiry'])) {
        $expiration_display = date('d/m/Y à H:i', strtotime($employe['badge_expiry']));
    }

    // Récupérer le chemin de la photo de profil
    $profile_photo = 'assets/default-profile.jpg';
    if (!empty($employe['photo'])) {
        $profile_photo = htmlspecialchars($employe['photo']);
    }

} catch (Exception $e) {
    // Gestion des erreurs
    die("Erreur système : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge - <?= htmlspecialchars($employe['prenom'].' '.$employe['nom']) ?> | Xpert+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="badge_acces.css">
    <link rel="manifest" href="/manifest.json">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.register('/service-worker.js')
            .then(function(registration) {
              console.log('ServiceWorker enregistré avec succès:', registration.scope);
            }, function(err) {
              console.log('Erreur ServiceWorker:', err);
            });
        });
      }
    </script>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-id-badge"></i>
                <span>Xpert+</span>
            </div>
            <div class="user-info">
                <img src="<?= $profile_photo ?>" class="user-photo" alt="Photo de profil">
                <span><?= htmlspecialchars($employe['prenom']) ?></span>
            </div>
        </div>
    </header>
    
    <main class="badge-container">
        <div class="badge-card">
            <!-- En-tête du badge -->
            <div class="badge-header">
                <div class="employee-id"><?= htmlspecialchars($employee_id_display) ?></div>
                
                <div class="photo-container">
                    <img src="<?= $profile_photo ?>" class="employee-photo" alt="Photo de profil">
                </div>
                
                <h1 class="employee-name"><?= htmlspecialchars($employe['prenom'].' '.$employe['nom']) ?></h1>
                <p class="employee-position"><?= htmlspecialchars($employe['poste']) ?></p>
            </div>
            
            <!-- Informations employé -->
            <div class="badge-content">
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Département</div>
                        <div class="info-value"><?= htmlspecialchars($departement) ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Matricule</div>
                        <div class="info-value"><?= htmlspecialchars($employee_id_display) ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Embauché le</div>
                        <div class="info-value"><?= htmlspecialchars($date_embauche) ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Badge créé</div>
                        <div class="info-value"><?= htmlspecialchars($badge_created) ?></div>
                    </div>
                </div>
                
                <!-- Section QR Code -->
                <div class="qr-section">
                    <h3 class="qr-title">
                        <i class="fas fa-qrcode"></i>
                        Code de pointage
                    </h3>
                    
                    <div class="qr-container">
                        <div id="qrCode"></div>
                    </div>
                    
                    <div class="checkin-status <?= $next_check_type === 'arrivee' ? 'status-arrivee' : 'status-depart' ?>">
                        <i class="fas fa-<?= $next_check_type === 'arrivee' ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                        Prochain pointage : <?= $next_check_type === 'arrivee' ? 'ARRIVÉE' : 'DÉPART' ?>
                    </div>
                    
                    <div class="expiration-info">
                        <i class="fas fa-clock"></i>
                        Valide jusqu'au <strong id="expirationDate"><?= $expiration_display ?></strong>
                    </div>
                    
                    <div class="countdown">
                        <i class="fas fa-hourglass-half"></i>
                        Temps restant : <span id="timeRemaining"></span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="action-buttons">
                    <button onclick="window.print()" class="btn-badge btn-primary">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <a href="employe_dashboard.php" class="btn-badge btn-secondary">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                    <a href="scan_qr.php" class="btn-badge btn-info">
                        <i class="fas fa-camera"></i> Zone de pointage
                    </a>
                    <a href="logout.php" class="btn-badge btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <p>Système de gestion des badges - Xpert+ &copy; <?= date('Y') ?></p>
        <p>Votre session est sécurisée - Dernière connexion : <?= date('d/m/Y H:i') ?></p>
    </footer>
    
    <!-- Alerte de sécurité -->
    <div class="security-alert" id="securityAlert">
        <div class="security-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div>
            <strong>Sécurité</strong>
            <p>Votre badge est personnel. Ne le partagez pas.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        // Génération du QR Code
        document.addEventListener('DOMContentLoaded', function() {
            // QR Code
            new QRCode(document.getElementById("qrCode"), {
                text: "<?= htmlspecialchars($employe['token']) ?>",
                width: 220,
                height: 220,
                colorDark: "#2c3e50",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            // Compte à rebours
            function updateTimer() {
                const expiryTime = new Date("<?= $employe['badge_expiry'] ?>").getTime();
                const now = new Date().getTime();
                const distance = expiryTime - now;
                
                const timeElement = document.getElementById("timeRemaining");
                
                if (distance < 0) {
                    timeElement.innerHTML = "EXPIRÉ";
                    timeElement.className = "expiration-danger";
                    return;
                }
                
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Appliquer des classes en fonction du temps restant
                if (hours < 1) {
                    timeElement.className = "expiration-warning";
                } else {
                    timeElement.className = "";
                }
                
                timeElement.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
            }
            
            // Initialiser le timer
            updateTimer();
            setInterval(updateTimer, 1000);
            
            // Masquer l'alerte de sécurité après 8 secondes
            setTimeout(() => {
                document.getElementById("securityAlert").style.display = "none";
            }, 8000);
        });
    </script>
</body>
</html>