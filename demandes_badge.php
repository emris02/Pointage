<?php
 //
require_once 'src/config/bootstrap.php';

// Vérification des droits employé
if (!isset($_SESSION['employe_id'])) {
    header('Location: login.php');
    exit();
}

$employe_id = $_SESSION['employe_id'];

// Vérifier si l'employé a déjà un badge actif
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM badge_tokens 
    WHERE employe_id = ? AND expires_at > NOW()
");
$stmt->execute([$employe_id]);
$has_active_badge = $stmt->fetchColumn();

// Vérifier si une demande est déjà en attente
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM demandes_badge 
    WHERE employe_id = ? AND statut = 'en_attente'
");
$stmt->execute([$employe_id]);
$has_pending_request = $stmt->fetchColumn();

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demander_badge'])) {
    if (!$has_active_badge && !$has_pending_request) {
        $raison = $_POST['raison'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO demandes_badge 
            (employe_id, date_demande, raison, statut) 
            VALUES (?, NOW(), ?, 'en_attente')
        ");
        $stmt->execute([$employe_id, $raison]);
        
        $_SESSION['success_message'] = "Votre demande de badge a été envoyée à l'administrateur.";
        header("Location: employe_dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demander un badge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #28a745, #218838);
            border: none;
            color: white;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            border: none;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-id-card me-2"></i>Demande de badge d'accès</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($has_active_badge): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Vous avez déjà un badge actif.
                            </div>
                            <a href="employe_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
                            </a>
                        <?php elseif ($has_pending_request): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Vous avez déjà une demande en attente de traitement par l'administrateur.
                            </div>
                            <a href="employe_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i> Retour au tableau de bord
                            </a>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="raison" class="form-label">Raison de la demande</label>
                                    <textarea class="form-control" id="raison" name="raison" rows="3" 
                                              placeholder="Pourquoi avez-vous besoin d'un nouveau badge?" required></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="demander_badge" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> Envoyer la demande
                                    </button>
                                    <a href="employe_dashboard.php" class="btn btn-outline-secondary">
                                        Annuler
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>