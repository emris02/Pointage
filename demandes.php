<?php
require_once __DIR__ . '/src/config/bootstrap.php';
require_once __DIR__ . '/src/services/AuthService.php';
require_once __DIR__ . '/src/controllers/DemandeController.php';
require_once __DIR__ . '/src/controllers/AdminController.php';
require_once __DIR__ . '/src/controllers/EmployeController.php';

use Pointage\Services\AuthService;
AuthService::requireAuth();

// Vérification des permissions
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','super_admin','rh','manager'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$demande = null;
$historique = [];
$documents = [];
$notifications = [];
$employe = null;
$demandes_similaires = [];

// Initialisation des contrôleurs
$demandeController = new DemandeController($pdo);
$adminController = new AdminController($pdo);
$employeController = new EmployeController($pdo);

if ($id > 0) {
    $demande = $demandeController->show($id);
    
    if ($demande) {
        // Normaliser les clés utilisées dans la vue pour éviter les notices
        $defaults = [
            'id' => 0,
            'statut' => 'en_attente',
            'type' => 'autre',
            'priorite' => null,
            'date_demande' => null,
            'motif' => '',
            'description' => '',
            'lieu' => '',
            'commentaire_interne' => '',
            'date_debut' => null,
            'date_fin' => null,
            'duree_jours' => 0,
            'heure_debut' => null,
            'heure_fin' => null,
            'assignee_id' => null,
            'date_maj' => null,
            'email' => null,
            'prenom' => '',
            'nom' => ''
        ];
        $demande = array_merge($defaults, $demande);

        // Récupération des données associées
        $historique = method_exists($demandeController, 'getHistorique') ? $demandeController->getHistorique($id) : [];
        $documents = method_exists($demandeController, 'getDocuments') ? $demandeController->getDocuments($id) : [];

        // Récupération des informations employé
        if (!empty($demande['employe_id'])) {
            $employe = $employeController->getEmployeById($demande['employe_id']);
        }

        // Récupération des demandes similaires
        $demandes_similaires = $demandeController->getDemandesSimilaires(
            $demande['employe_id'], 
            $demande['type'], 
            $id
        );

        // Récupération des notifications (si le service existe)
        if (class_exists('NotificationService')) {
            $notificationService = new NotificationService($pdo);
            $notifications = $notificationService->getNotificationsDemande($id) ?? [];
        } else {
            $notifications = [];
        }
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $demande_id = (int)($_POST['id'] ?? 0);
    $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'update_status':
                $nouveau_statut = $_POST['statut'] ?? '';
                $commentaire = trim($_POST['commentaire'] ?? '');
                $motif_rejet = trim($_POST['motif_rejet'] ?? '');
                $assignee_id = isset($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null;
                
                $updateData = [
                    'statut' => $nouveau_statut,
                    'commentaire_interne' => $commentaire
                ];
                
                if ($assignee_id) {
                    $updateData['assignee_id'] = $assignee_id;
                }
                
                $ok = $demandeController->update($demande_id, $updateData);
                
                if ($ok) {
                    // Création d'une entrée dans l'historique
                    $demandeController->addHistorique($demande_id, [
                        'type' => 'statut',
                        'titre' => 'Changement de statut',
                        'description' => "Statut changé de '{$demande['statut']}' à '{$nouveau_statut}'" . 
                                       ($commentaire ? "\nCommentaire: {$commentaire}" : ""),
                        'ancien_statut' => $demande['statut'],
                        'nouveau_statut' => $nouveau_statut,
                        'auteur_id' => $adminId
                    ]);
                    
                    // Envoi de notification
                    if (class_exists('NotificationService')) {
                        $notificationService->createNotification([
                            'type' => 'demande_statut',
                            'titre' => "Votre demande a été mise à jour",
                            'message' => "Le statut de votre demande #{$demande_id} est maintenant '{$nouveau_statut}'",
                            'destinataire_id' => $demande['employe_id'],
                            'destinataire_type' => 'employe',
                            'lien' => "mes_demandes.php?id={$demande_id}",
                            'demande_id' => $demande_id
                        ]);
                    }
                    
                    // Envoi d'email
                    envoyerEmailStatut($demande, $nouveau_statut, $commentaire, $motif_rejet);
                    
                    $_SESSION['success_message'] = "Statut mis à jour avec succès.";
                    header("Location: demandes.php?id=" . $demande_id);
                    exit();
                }
                break;
                
            case 'add_comment':
                $commentaire = trim($_POST['commentaire'] ?? '');
                $type_commentaire = $_POST['type_commentaire'] ?? 'interne';
                
                if ($commentaire !== '') {
                    $demandeController->addCommentaire($demande_id, $commentaire, $adminId, $type_commentaire);
                    
                    // Notification pour les commentaires visibles par l'employé
                    if ($type_commentaire === 'public' && class_exists('NotificationService')) {
                        $notificationService->createNotification([
                            'type' => 'demande_commentaire',
                            'titre' => "Nouveau commentaire sur votre demande",
                            'message' => "Un administrateur a ajouté un commentaire à votre demande #{$demande_id}",
                            'destinataire_id' => $demande['employe_id'],
                            'destinataire_type' => 'employe',
                            'lien' => "mes_demandes.php?id={$demande_id}",
                            'demande_id' => $demande_id
                        ]);
                    }
                    
                    $_SESSION['success_message'] = "Commentaire ajouté avec succès.";
                    header("Location: demandes.php?id=" . $demande_id);
                    exit();
                }
                break;
                
            case 'upload_document':
                if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    $description = trim($_POST['description_document'] ?? '');
                    $demandeController->uploadDocument($demande_id, $_FILES['document'], $description, $adminId);
                    
                    $_SESSION['success_message'] = "Document téléchargé avec succès.";
                    header("Location: demandes.php?id=" . $demande_id);
                    exit();
                }
                break;
                
                case 'quick_approve':
                    traiterDecisionRapide($demande_id, 'approuve', $adminId);
                    break;

                case 'quick_reject':
                    $motif = trim($_POST['motif_rejet'] ?? '');
                    traiterDecisionRapide($demande_id, 'rejete', $adminId, $motif);
                    break;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
}

// Fonction pour envoyer l'email de statut
function envoyerEmailStatut($demande, $nouveau_statut, $commentaire = '', $motif_rejet = '') {
    if (!class_exists('EmailService')) return;
    
    $destEmail = $demande['email'] ?? null;
    if (!$destEmail) return;
    
    $sujet = "Mise à jour de votre demande #" . $demande['id'];
    
    $template = new EmailTemplate();
    $template->setSubject($sujet);
    $template->setRecipient($destEmail, $demande['prenom'] . ' ' . $demande['nom']);
    
    if ($nouveau_statut === 'approuve') {
        $template->loadTemplate('demande_approuvee', [
            'numero_demande' => $demande['id'],
            'type_demande' => ucfirst($demande['type']),
            'date_debut' => $demande['date_debut'],
            'date_fin' => $demande['date_fin'],
            'commentaire' => $commentaire,
            'lien_details' => 'https://votre-domaine.com/mes_demandes.php?id=' . $demande['id']
        ]);
    } elseif ($nouveau_statut === 'rejete') {
        $template->loadTemplate('demande_rejetee', [
            'numero_demande' => $demande['id'],
            'type_demande' => ucfirst($demande['type']),
            'motif_rejet' => $motif_rejet ?: $commentaire,
            'lien_details' => 'https://votre-domaine.com/mes_demandes.php?id=' . $demande['id'],
            'lien_nouvelle_demande' => 'https://votre-domaine.com/nouvelle_demande.php'
        ]);
    } else {
        $template->loadTemplate('demande_mise_a_jour', [
            'numero_demande' => $demande['id'],
            'type_demande' => ucfirst($demande['type']),
            'nouveau_statut' => ucfirst($nouveau_statut),
            'commentaire' => $commentaire,
            'lien_details' => 'https://votre-domaine.com/mes_demandes.php?id=' . $demande['id']
        ]);
    }
    
    EmailService::send($template);
}

// Fonction pour traiter les décisions rapides
function traiterDecisionRapide($demande_id, $decision, $adminId, $motif = '') {
    global $demandeController;
    
    $demande = $demandeController->show($demande_id);
    $demandeController->update($demande_id, ['statut' => $decision]);
    
    // Historique
    $demandeController->addHistorique($demande_id, [
        'type' => 'decision_rapide',
        'titre' => 'Décision rapide',
        'description' => "Demande {$decision}" . ($motif ? " - Motif: {$motif}" : ""),
        'auteur_id' => $adminId
    ]);
    
    // Notification
    if (class_exists('NotificationService')) {
        $notificationService = new NotificationService($pdo);
        $notificationService->createNotification([
            'type' => 'demande_decision_rapide',
            'titre' => "Décision prise sur votre demande",
            'message' => "Votre demande #{$demande_id} a été {$decision}",
            'destinataire_id' => $demande['employe_id'],
            'destinataire_type' => 'employe',
            'lien' => "mes_demandes.php?id={$demande_id}"
        ]);
    }
    
    // Email
    envoyerEmailStatut($demande, $decision, '', $motif);
    
    $_SESSION['success_message'] = "Décision enregistrée avec succès.";
    header("Location: demandes.php?id=" . $demande_id);
    exit();
}

$pageHeader = 'Gestion des demandes';
$pageDescription = $demande ? ('Demande #' . $demande['id'] . ' - ' . ucfirst($demande['type'])) : 'Détail de la demande';

include __DIR__ . '/src/views/partials/header.php';
include __DIR__ . '/src/views/partials/sidebar_canonique.php';
?>

<div class="main-content">
    <div class="container-fluid mt-4">
        <!-- Messages de notification -->
        <?php include __DIR__ . '/src/views/partials/alerts.php'; ?>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard_unifie.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li class="breadcrumb-item"><a href="admin_dashboard_unifie.php#demandes">Demandes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Demande #<?= $id ?></li>
            </ol>
        </nav>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-lg-8">
                <?php if (!$demande): ?>
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h4>Demande introuvable</h4>
                            <p class="text-muted">La demande que vous recherchez n'existe pas ou vous n'avez pas les permissions nécessaires.</p>
                            <a href="admin_dashboard_unifie.php#demandes" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Retour aux demandes
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- En-tête de la demande -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <h2 class="h4 mb-0 me-3">Demande #<?= $demande['id'] ?></h2>
                                        <span class="badge rounded-pill bg-<?= getStatusColor($demande['statut']) ?> fs-6 px-3 py-2">
                                            <i class="fas <?= getStatusIcon($demande['statut']) ?> me-1"></i>
                                            <?= ucfirst($demande['statut']) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div>
                                            <i class="fas <?= getDemandeIcon($demande['type']) ?> text-muted me-1"></i>
                                            <span class="text-muted"><?= ucfirst($demande['type']) ?></span>
                                        </div>
                                        <div>
                                            <i class="fas fa-calendar text-muted me-1"></i>
                                            <span class="text-muted">Créée le <?= date('d/m/Y à H:i', strtotime($demande['date_demande'])) ?></span>
                                        </div>
                                        <?php if ($demande['priorite']): ?>
                                        <div>
                                            <span class="badge bg-<?= getPriorityColor($demande['priorite']) ?>">
                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                <?= ucfirst($demande['priorite']) ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="demande_pdf.php?id=<?= $id ?>" target="_blank">
                                            <i class="fas fa-print me-2"></i>Générer PDF
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#emailModal">
                                            <i class="fas fa-envelope me-2"></i>Envoyer un email
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-warning" href="modifier_demande.php?id=<?= $id ?>">
                                            <i class="fas fa-edit me-2"></i>Modifier
                                        </a></li>
                                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash me-2"></i>Supprimer
                                        </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Informations employé -->
                        <div class="col-md-6">
                            <div class="card shadow-sm mb-4 h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Informations employé</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($employe): ?>
                                        <div class="d-flex align-items-center mb-4">
                                            <?php if ($employe['photo']): ?>
                                                <img src="<?= htmlspecialchars($employe['photo']) ?>" 
                                                     alt="Photo employé" 
                                                     class="rounded-circle me-3"
                                                     style="width:80px;height:80px;object-fit:cover;border:3px solid var(--bs-primary)">
                                            <?php else: ?>
                                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                                     style="width:80px;height:80px;border:3px solid var(--bs-primary)">
                                                    <i class="fas fa-user text-primary fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1"><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></h5>
                                                <p class="text-muted mb-1"><?= htmlspecialchars($employe['poste'] ?? '') ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?= htmlspecialchars($employe['departement'] ?? 'Non spécifié') ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="small text-muted mb-1">Email professionnel</label>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-envelope text-muted me-2"></i>
                                                    <a href="mailto:<?= htmlspecialchars($employe['email']) ?>" 
                                                       class="text-decoration-none">
                                                        <?= htmlspecialchars($employe['email']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="small text-muted mb-1">Téléphone</label>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-phone text-muted me-2"></i>
                                                    <span><?= htmlspecialchars($employe['telephone'] ?? 'Non renseigné') ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="small text-muted mb-1">Date d'embauche</label>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                    <span><?= $employe['date_embauche'] ? date('d/m/Y', strtotime($employe['date_embauche'])) : 'Non spécifiée' ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-12">
                                                <label class="small text-muted mb-1">Solde de congés</label>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-umbrella-beach text-muted me-2"></i>
                                                    <span><?= $employe['solde_conges'] ?? 0 ?> jours restants</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <a href="profil_employe.php?id=<?= (int)$employe['id'] ?>" 
                                               class="btn btn-outline-primary w-100">
                                                <i class="fas fa-id-card me-2"></i>Voir le profil complet
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-user-slash fa-2x mb-3"></i>
                                            <p>Informations employé non disponibles</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Détails de la demande -->
                        <div class="col-md-6">
                            <div class="card shadow-sm mb-4 h-100">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Détails de la demande</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="small text-muted mb-1">Type de demande</label>
                                            <div class="d-flex align-items-center">
                                                <i class="fas <?= getDemandeIcon($demande['type']) ?> text-info me-2 fa-lg"></i>
                                                <span class="fw-semibold"><?= htmlspecialchars(ucfirst($demande['type'])) ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="small text-muted mb-1">Période</label>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-calendar-day text-info me-2"></i>
                                                <span>
                                                    <?= $demande['date_debut'] ? date('d/m/Y', strtotime($demande['date_debut'])) : 'Immédiate' ?>
                                                    <?php if ($demande['date_fin']): ?>
                                                        <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                        <?= date('d/m/Y', strtotime($demande['date_fin'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($demande['duree_jours']): ?>
                                        <div class="col-12">
                                            <label class="small text-muted mb-1">Durée</label>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-clock text-info me-2"></i>
                                                <span><?= $demande['duree_jours'] ?> jour(s)</span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($demande['heure_debut']): ?>
                                        <div class="col-12">
                                            <label class="small text-muted mb-1">Heure</label>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-clock text-info me-2"></i>
                                                <span>
                                                    <?= date('H:i', strtotime($demande['heure_debut'])) ?>
                                                    <?php if ($demande['heure_fin']): ?>
                                                        - <?= date('H:i', strtotime($demande['heure_fin'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="col-12">
                                            <label class="small text-muted mb-1">Assigné à</label>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-check text-info me-2"></i>
                                                <?php if ($demande['assignee_id']): ?>
                                                    <?php 
                                                    $assignee = $adminController->getAdminById($demande['assignee_id']);
                                                    echo $assignee ? htmlspecialchars($assignee['prenom'] . ' ' . $assignee['nom']) : 'Non assigné';
                                                    ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non assigné</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="small text-muted mb-1">Dernière mise à jour</label>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-history text-info me-2"></i>
                                                <span><?= $demande['date_maj'] ? date('d/m/Y H:i', strtotime($demande['date_maj'])) : date('d/m/Y H:i', strtotime($demande['date_demande'])) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Décision rapide -->
                                    <?php if (in_array($demande['statut'], ['en_attente', 'en_cours'])): ?>
                                    <div class="mt-4 pt-3 border-top">
                                        <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Décision rapide</h6>
                                        <div class="d-grid gap-2">
                                            <form method="post" action="demandes.php?id=<?= $id ?>" class="d-grid gap-2">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="quick_approve">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check-circle me-2"></i>Approuver la demande
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal">
                                                <i class="fas fa-times-circle me-2"></i>Rejeter la demande
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Motif et description -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-align-left me-2"></i>Contenu de la demande</h6>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-clock me-1"></i>
                                <?= calculateDelai($demande['date_demande']) ?> jour(s) ouvert(s)
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Motif principal</label>
                                <div class="p-3 bg-light bg-opacity-25 rounded border">
                                    <?= nl2br(htmlspecialchars($demande['motif'] ?? 'Aucun motif spécifié')) ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($demande['description'])): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Description détaillée</label>
                                <div class="p-3 bg-light bg-opacity-25 rounded border">
                                    <?= nl2br(htmlspecialchars($demande['description'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($demande['lieu'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Lieu</label>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                    <span><?= htmlspecialchars($demande['lieu']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($demande['commentaire_interne'])): ?>
                            <div class="mt-4 p-3 bg-warning bg-opacity-10 rounded border-start border-warning border-3">
                                <label class="form-label fw-semibold text-warning">
                                    <i class="fas fa-exclamation-circle me-2"></i>Commentaire interne
                                </label>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($demande['commentaire_interne'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Gestion complète de la demande -->
                    <?php if (in_array($_SESSION['role'], ['admin','super_admin','rh'])): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Gestion avancée</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="demandes.php?id=<?= $id ?>" id="manageForm">
                                <input type="hidden" name="id" value="<?= (int)$demande['id'] ?>">
                                <input type="hidden" name="action" value="update_status">
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Statut</label>
                                        <select name="statut" class="form-select" required>
                                            <option value="en_attente" <?= $demande['statut']=='en_attente'?'selected':'' ?>>🟡 En attente</option>
                                            <option value="en_cours" <?= $demande['statut']=='en_cours'?'selected':'' ?>>🔵 En cours de traitement</option>
                                            <option value="approuve" <?= $demande['statut']=='approuve'?'selected':'' ?>>🟢 Approuvée</option>
                                            <option value="rejete" <?= $demande['statut']=='rejete'?'selected':'' ?>>🔴 Rejetée</option>
                                            <option value="annule" <?= $demande['statut']=='annule'?'selected':'' ?>>⚫ Annulée</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Priorité</label>
                                        <select name="priorite" class="form-select">
                                            <option value="low" <?= ($demande['priorite']??'')=='low'?'selected':'' ?>>Basse</option>
                                            <option value="medium" <?= ($demande['priorite']??'medium')=='medium'?'selected':'' ?>>Moyenne</option>
                                            <option value="high" <?= ($demande['priorite']??'')=='high'?'selected':'' ?>>Haute</option>
                                            <option value="critical" <?= ($demande['priorite']??'')=='critical'?'selected':'' ?>>Critique</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Assigner à</label>
                                        <select name="assignee_id" class="form-select">
                                            <option value="">Non assigné</option>
                                            <?php
                                            $admins = $adminController->getAllAdmins();
                                            foreach ($admins as $admin):
                                            ?>
                                                <option value="<?= $admin['id'] ?>" <?= ($demande['assignee_id'] ?? '') == $admin['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?>
                                                    <?= $admin['role'] ? ' (' . $admin['role'] . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Commentaire administratif</label>
                                        <textarea name="commentaire" class="form-control" rows="3" 
                                                  placeholder="Commentaire interne visible uniquement par les administrateurs..."></textarea>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Message à l'employé</label>
                                        <textarea name="message_employe" class="form-control" rows="3" 
                                                  placeholder="Message qui sera envoyé à l'employé par email..."></textarea>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-info" 
                                                    data-bs-toggle="modal" data-bs-target="#notificationModal">
                                                <i class="fas fa-bell me-2"></i>Envoyer une notification
                                            </button>
                                            
                                            <a href="dupliquer_demande.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-copy me-2"></i>Dupliquer
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Demandes similaires -->
                    <?php if (!empty($demandes_similaires)): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Demandes similaires</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Statut</th>
                                            <th>Période</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($demandes_similaires as $similaire): ?>
                                        <tr>
                                            <td><?= $similaire['id'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($similaire['date_demande'])) ?></td>
                                            <td>
                                                <i class="fas <?= getDemandeIcon($similaire['type']) ?> me-1"></i>
                                                <?= ucfirst($similaire['type']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getStatusColor($similaire['statut']) ?>">
                                                    <?= ucfirst($similaire['statut']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $similaire['date_debut'] ? date('d/m/Y', strtotime($similaire['date_debut'])) : '' ?>
                                                <?php if ($similaire['date_fin']): ?>
                                                    <br><small>au <?= date('d/m/Y', strtotime($similaire['date_fin'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="demandes.php?id=<?= $similaire['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Historique des modifications -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-history me-2"></i>Historique</h6>
                            <span class="badge bg-light text-dark"><?= count($historique) ?> événement(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($historique)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-history fa-3x mb-3"></i>
                                    <p>Aucun historique disponible</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline-vertical px-3 py-3">
                                    <?php foreach ($historique as $event): ?>
                                        <div class="timeline-item mb-4 position-relative">
                                            <div class="timeline-indicator bg-<?= getStatusColor($event['nouveau_statut'] ?? $event['ancien_statut'] ?? 'secondary') ?>">
                                                <i class="fas fa-<?= getHistoryIcon($event['type']) ?>"></i>
                                            </div>
                                            <div class="timeline-content ms-5 ps-3">
                                                <div class="card border-0 shadow-sm">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between mb-2">
                                                            <h6 class="mb-0"><?= htmlspecialchars($event['titre']) ?></h6>
                                                            <small class="text-muted">
                                                                <?= date('d/m/Y H:i', strtotime($event['date_creation'])) ?>
                                                            </small>
                                                        </div>
                                                        <p class="mb-2"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <i class="fas fa-user-circle me-1"></i>
                                                                <?= htmlspecialchars($event['auteur_nom'] . ' ' . $event['auteur_prenom']) ?>
                                                            </small>
                                                            <?php if ($event['type'] === 'statut'): ?>
                                                                <div class="d-flex align-items-center">
                                                                    <span class="badge bg-light text-dark me-2">
                                                                        <?= ucfirst($event['ancien_statut'] ?? 'N/A') ?>
                                                                    </span>
                                                                    <i class="fas fa-arrow-right text-muted me-2"></i>
                                                                    <span class="badge bg-<?= getStatusColor($event['nouveau_statut']) ?>">
                                                                        <?= ucfirst($event['nouveau_statut']) ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar droite -->
            <div class="col-lg-4">
                <!-- Documents associés -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-paperclip me-2"></i>Documents</h6>
                        <span class="badge bg-dark"><?= count($documents) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-3"></i>
                                <p>Aucun document</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="list-group-item border-0 px-0 py-2">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="document-icon bg-light rounded p-2">
                                                    <i class="fas fa-file-<?= getFileIcon($doc['type_fichier']) ?> text-primary fa-lg"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1"><?= htmlspecialchars($doc['nom_fichier']) ?></h6>
                                                <small class="text-muted">
                                                    Ajouté le <?= date('d/m/Y', strtotime($doc['date_upload'])) ?>
                                                    <?php if ($doc['description']): ?>
                                                        <br><?= htmlspecialchars($doc['description']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="dropdown flex-shrink-0">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="<?= htmlspecialchars($doc['chemin_fichier']) ?>" 
                                                           target="_blank">
                                                            <i class="fas fa-eye me-2"></i>Visualiser
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="<?= htmlspecialchars($doc['chemin_fichier']) ?>" 
                                                           download>
                                                            <i class="fas fa-download me-2"></i>Télécharger
                                                        </a>
                                                    </li>
                                                    <?php if (in_array($_SESSION['role'], ['admin','super_admin','rh'])): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" 
                                                           href="#" 
                                                           onclick="deleteDocument(<?= $doc['id'] ?>)">
                                                            <i class="fas fa-trash me-2"></i>Supprimer
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($_SESSION['role'], ['admin','super_admin','rh'])): ?>
                        <div class="mt-4">
                            <form method="post" action="demandes.php?id=<?= $id ?>" 
                                  enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="id" value="<?= (int)$demande['id'] ?>">
                                <input type="hidden" name="action" value="upload_document">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Ajouter un document</label>
                                    <div class="file-upload-area border rounded p-3 text-center">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                                        <p class="small text-muted mb-2">Glissez-déposez ou cliquez pour sélectionner</p>
                                        <input type="file" name="document" 
                                               class="form-control" 
                                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description (optionnel)</label>
                                    <textarea name="description_document" 
                                              class="form-control" 
                                              rows="2"
                                              placeholder="Description du document..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-upload me-2"></i>Télécharger le document
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Commentaires -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-comments me-2"></i>Commentaires</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="demandes.php?id=<?= $id ?>" class="mb-4">
                            <input type="hidden" name="id" value="<?= (int)$demande['id'] ?>">
                            <input type="hidden" name="action" value="add_comment">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nouveau commentaire</label>
                                <textarea name="commentaire" 
                                          class="form-control" 
                                          rows="3" 
                                          placeholder="Ajouter un commentaire..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Type de commentaire</label>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="type_commentaire" 
                                           value="interne" 
                                           id="interne" 
                                           checked>
                                    <label class="form-check-label" for="interne">
                                        Interne (visible uniquement par les administrateurs)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="type_commentaire" 
                                           value="public" 
                                           id="public">
                                    <label class="form-check-label" for="public">
                                        Public (visible par l'employé)
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-info w-100">
                                <i class="fas fa-paper-plane me-2"></i>Publier le commentaire
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div id="commentairesContainer">
                            <!-- Les commentaires seront chargés ici via AJAX -->
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Métriques et statistiques -->
                <?php if ($demande): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-6">
                                <div class="metric-card p-3 bg-primary bg-opacity-10 rounded">
                                    <div class="metric-value text-primary fs-3 fw-bold">
                                        <?= calculateDelai($demande['date_demande']) ?>
                                    </div>
                                    <div class="metric-label small text-muted">Jours ouverts</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card p-3 bg-warning bg-opacity-10 rounded">
                                    <div class="metric-value text-warning fs-3 fw-bold">
                                        <?= count($historique) ?>
                                    </div>
                                    <div class="metric-label small text-muted">Événements</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card p-3 bg-info bg-opacity-10 rounded">
                                    <div class="metric-value text-info fs-3 fw-bold">
                                        <?= count($documents) ?>
                                    </div>
                                    <div class="metric-label small text-muted">Documents</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-card p-3 bg-success bg-opacity-10 rounded">
                                    <div class="metric-value text-success fs-3 fw-bold">
                                        <?= count($notifications) ?>
                                    </div>
                                    <div class="metric-label small text-muted">Notifications</div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($demande['type'] === 'conge'): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-semibold mb-3">Impact sur les congés</h6>
                            <div class="alert alert-info">
                                <div class="d-flex">
                                    <i class="fas fa-info-circle fa-lg me-3 mt-1"></i>
                                    <div>
                                        <p class="mb-1">Cette demande de congé consommera 
                                            <strong><?= $demande['duree_jours'] ?? 1 ?> jour(s)</strong>
                                            du solde de l'employé.
                                        </p>
                                        <small class="text-muted">
                                            Solde actuel: <?= $employe['solde_conges'] ?? 0 ?> jours
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions rapides -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="creer_demande.php?employe_id=<?= $demande['employe_id'] ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-plus-circle me-2"></i>Nouvelle demande pour cet employé
                            </a>
                            
                            <a href="calendrier.php?highlight=<?= $id ?>" 
                               class="btn btn-outline-success">
                                <i class="fas fa-calendar-alt me-2"></i>Voir dans le calendrier
                            </a>
                            
                            <a href="rapport_demande.php?id=<?= $id ?>" 
                               class="btn btn-outline-info">
                                <i class="fas fa-chart-line me-2"></i>Générer un rapport
                            </a>
                            
                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                            <button type="button" class="btn btn-outline-danger" 
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash-alt me-2"></i>Supprimer la demande
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php if ($demande): ?>
<!-- Modal Rejet -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="demandes.php?id=<?= $id ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="action" value="quick_reject">
                
                <div class="modal-header">
                    <h5 class="modal-title">Rejeter la demande</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motif du rejet</label>
                        <textarea name="motif_rejet" class="form-control" rows="4" 
                                  placeholder="Expliquez les raisons du rejet..." required></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify_employee" checked>
                        <label class="form-check-label" for="notify_employee">
                            Notifier l'employé par email
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer le rejet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Notification -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Envoyer une notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="notificationForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type de notification</label>
                        <select class="form-select">
                            <option value="info">Information</option>
                            <option value="warning">Avertissement</option>
                            <option value="urgent">Urgent</option>
                            <option value="update">Mise à jour</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" rows="4" 
                                  placeholder="Contenu de la notification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Supprimer la demande
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <h6 class="alert-heading">Attention !</h6>
                    <p class="mb-2">Vous êtes sur le point de supprimer définitivement la demande #<?= $demande['id'] ?>.</p>
                    <p class="mb-0">Cette action supprimera également :</p>
                    <ul class="mb-0">
                        <li>Tous les documents associés</li>
                        <li>L'historique des modifications</li>
                        <li>Les commentaires et notifications</li>
                    </ul>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        Je comprends que cette action est irréversible
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notifyEmployee">
                    <label class="form-check-label" for="notifyEmployee">
                        Informer l'employé de la suppression
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                    Supprimer définitivement
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Styles CSS personnalisés -->
<style>
.timeline-vertical {
    position: relative;
}

.timeline-vertical::before {
    content: '';
    position: absolute;
    left: 24px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--bs-border-color);
}

.timeline-item {
    position: relative;
}

.timeline-indicator {
    position: absolute;
    left: 16px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    z-index: 1;
}

.timeline-content {
    margin-left: 40px;
}

.document-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.metric-card {
    transition: transform 0.2s;
}

.metric-card:hover {
    transform: translateY(-2px);
}

.file-upload-area {
    border: 2px dashed #dee2e6;
    transition: all 0.3s;
}

.file-upload-area:hover {
    border-color: var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}

/* Statut badges */
.bg-en_attente { background-color: #ffc107 !important; }
.bg-en_cours { background-color: #0dcaf0 !important; }
.bg-approuve { background-color: #198754 !important; }
.bg-rejete { background-color: #dc3545 !important; }
.bg-annule { background-color: #6c757d !important; }

/* Priorité badges */
.bg-low { background-color: #198754 !important; }
.bg-medium { background-color: #ffc107 !important; }
.bg-high { background-color: #fd7e14 !important; }
.bg-critical { background-color: #dc3545 !important; }

/* Statut text */
.text-en_attente { color: #ffc107; }
.text-en_cours { color: #0dcaf0; }
.text-approuve { color: #198754; }
.text-rejete { color: #dc3545; }
.text-annule { color: #6c757d; }
</style>

<!-- Scripts JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Charger les commentaires via AJAX
    loadComments();
    
    // Confirmation de suppression
    const confirmCheckbox = document.getElementById('confirmDelete');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    
    if (confirmCheckbox && deleteBtn) {
        confirmCheckbox.addEventListener('change', function() {
            deleteBtn.disabled = !this.checked;
        });
        
        deleteBtn.addEventListener('click', function() {
            deleteDemande();
        });
    }
    
    // Formulaire d'upload avec preview
    const uploadInput = document.querySelector('input[name="document"]');
    const uploadArea = document.querySelector('.file-upload-area');
    
    if (uploadInput && uploadArea) {
        uploadInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                uploadArea.innerHTML = `
                    <i class="fas fa-file text-success fa-2x mb-2"></i>
                    <p class="mb-1 fw-semibold">${fileName}</p>
                    <small class="text-muted">Prêt à être téléchargé</small>
                    <input type="file" name="document" class="d-none">
                `;
            }
        });
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--bs-primary)';
            this.style.backgroundColor = 'rgba(var(--bs-primary-rgb), 0.1)';
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.backgroundColor = '';
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.backgroundColor = '';
            
            if (e.dataTransfer.files.length) {
                uploadInput.files = e.dataTransfer.files;
                const event = new Event('change', { bubbles: true });
                uploadInput.dispatchEvent(event);
            }
        });
    }
});

function loadComments() {
    const container = document.getElementById('commentairesContainer');
    if (!container) return;
    
    fetch(`api/get_comments.php?demande_id=<?= $id ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.comments.length > 0) {
                container.innerHTML = data.comments.map(comment => `
                    <div class="comment-item mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <span class="fw-semibold">${comment.auteur_nom}</span>
                                <span class="badge bg-${comment.type === 'public' ? 'info' : 'secondary'} ms-2">
                                    ${comment.type === 'public' ? 'Public' : 'Interne'}
                                </span>
                            </div>
                            <small class="text-muted">${comment.date}</small>
                        </div>
                        <p class="mb-2">${comment.contenu}</p>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-comment-slash fa-2x mb-3"></i>
                        <p>Aucun commentaire</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Impossible de charger les commentaires
                </div>
            `;
        });
}

function deleteDocument(docId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce document ?')) return;
    
    fetch('api/delete_document.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            document_id: docId,
            demande_id: <?= $id ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Document supprimé avec succès', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('Erreur lors de la suppression', 'error');
        }
    })
    .catch(error => {
        showAlert('Erreur réseau', 'error');
    });
}

function deleteDemande() {
    const notifyEmployee = document.getElementById('notifyEmployee').checked;
    
    fetch('api/delete_demande.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            demande_id: <?= $id ?>,
            notify_employee: notifyEmployee
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Demande supprimée avec succès', 'success');
            setTimeout(() => {
                window.location.href = 'admin_dashboard_unifie.php#demandes';
            }, 1500);
        } else {
            showAlert(data.message || 'Erreur lors de la suppression', 'error');
        }
    })
    .catch(error => {
        showAlert('Erreur réseau', 'error');
    });
}

function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Fonctions utilitaires PHP
<?php
function getStatusColor($status) {
    $colors = [
        'en_attente' => 'warning',
        'en_cours' => 'info',
        'approuve' => 'success',
        'rejete' => 'danger',
        'annule' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'en_attente' => 'clock',
        'en_cours' => 'sync-alt',
        'approuve' => 'check-circle',
        'rejete' => 'times-circle',
        'annule' => 'ban'
    ];
    return $icons[$status] ?? 'file-alt';
}

function getPriorityColor($priority) {
    $colors = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'orange',
        'critical' => 'danger'
    ];
    return $colors[$priority] ?? 'secondary';
}

function getDemandeIcon($type) {
    $icons = [
        'conge' => 'umbrella-beach',
        'absence' => 'user-clock',
        'retard' => 'clock',
        'formation' => 'graduation-cap',
        'equipement' => 'laptop',
        'materiel' => 'desktop',
        'autre' => 'file-alt',
        'mission' => 'plane',
        'teletravail' => 'home',
        'arret' => 'file-medical',
        'recrutement' => 'user-plus'
    ];
    return $icons[$type] ?? 'file-alt';
}

function getHistoryIcon($type) {
    $icons = [
        'statut' => 'sync',
        'commentaire' => 'comment',
        'document' => 'paperclip',
        'assignation' => 'user-plus',
        'decision_rapide' => 'bolt',
        'creation' => 'plus-circle'
    ];
    return $icons[$type] ?? 'info-circle';
}

function getFileIcon($fileType) {
    if (strpos($fileType, 'image') !== false) return 'image';
    if (strpos($fileType, 'pdf') !== false) return 'pdf';
    if (strpos($fileType, 'word') !== false) return 'word';
    if (strpos($fileType, 'excel') !== false) return 'excel';
    if (strpos($fileType, 'text') !== false) return 'alt';
    return 'file';
}

function calculateDelai($dateDebut) {
    try {
        $start = new DateTime($dateDebut);
        $now = new DateTime();
        $interval = $start->diff($now);
        return $interval->days;
    } catch (Exception $e) {
        return 0;
    }
}
?>
</script>

<?php include __DIR__ . '/src/views/partials/footer.php'; ?>