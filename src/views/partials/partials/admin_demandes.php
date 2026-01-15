<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le bootstrap du projet
require_once __DIR__ . '/../../config/bootstrap.php';

// Vérification de session sécurisée
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: login.php");
    exit();
}

// Initialisation sécurisée des variables
$_SESSION['admin_id'] = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
$_SESSION['last_activity'] = time();

// Traitement AJAX sécurisé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $demande_id = filter_input(INPUT_POST, 'id_demande', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $commentaire = isset($_POST['commentaire']) ? htmlspecialchars(trim($_POST['commentaire']), ENT_QUOTES, 'UTF-8') : '';

        if (!$demande_id || !in_array($action, ['approuve', 'rejete'])) {
            throw new Exception('Requête invalide');
        }

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE demandes 
                              SET statut = ?, 
                                  commentaire = ?,
                                  date_traitement = NOW(),
                                  traite_par = ?
                              WHERE id = ?");
        $stmt->execute([$action, $commentaire, $_SESSION['admin_id'], $demande_id]);
        
        // Journalisation
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)")
           ->execute([$_SESSION['admin_id'], 'demande_'.$action, "Demande ID: $demande_id"]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => "Demande ".($action === 'approuve' ? 'approuvée' : 'rejetée')]);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Configuration de la pagination
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres sécurisés
$filtre_statut = isset($_GET['statut']) ? htmlspecialchars($_GET['statut']) : 'tous';
$filtre_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'tous';
$filtre_date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '';
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

// Construction de la requête sécurisée
$where = [];
$params = [];

$valid_statuts = ['en_attente', 'approuve', 'rejete'];
if ($filtre_statut !== 'tous' && in_array($filtre_statut, $valid_statuts)) {
    $where[] = "d.statut = ?";
    $params[] = $filtre_statut;
}

$valid_types = ['conge', 'retard', 'absence'];
if ($filtre_type !== 'tous' && in_array($filtre_type, $valid_types)) {
    $where[] = "d.type = ?";
    $params[] = $filtre_type;
}

if (!empty($filtre_date) && DateTime::createFromFormat('Y-m-d', $filtre_date)) {
    $where[] = "DATE(d.date_demande) = ?";
    $params[] = $filtre_date;
}

if (!empty($search_query)) {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ? OR d.motif LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Comptage
$count_sql = "SELECT COUNT(*) FROM demandes d 
              JOIN employes e ON d.employe_id = e.id 
              $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_demandes = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_demandes / $limit));

// Requête principale
$sql = "
    SELECT d.*, 
        e.nom, e.prenom, e.email, e.poste, e.photo, e.departement,
        a.nom AS admin_nom, a.prenom AS admin_prenom,
        TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) AS heures_ecoulees
    FROM demandes d
    JOIN employes e ON d.employe_id = e.id
    LEFT JOIN admins a ON d.traite_par = a.id
    $where_clause
    ORDER BY 
        CASE WHEN d.statut = 'en_attente' THEN 0 ELSE 1 END,
        d.date_demande DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(statut = 'en_attente') AS en_attente,
        SUM(statut = 'approuve') AS approuve,
        SUM(statut = 'rejete') AS rejete,
        SUM(type = 'conge') AS conges,
        SUM(type = 'retard') AS retards,
        SUM(type = 'absence') AS absences,
        SUM(type = 'maladie') AS maladies
    FROM demandes
    WHERE DATE(date_demande) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Demandes | Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
        --primary: #0672e4;
        --primary-dark: #3a56d4;
        --primary-light: #6b8aff;
        --secondary: #0672e4;
        --accent: #4cc9f0;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --light: #f8fafc;
        --dark: #1e293b;
        --text: #ffff;
        --text-light: #64748b;
        --white: #ffff;
        --gradient: linear-gradient(135deg, #2078e9 0%, #0672e4 100%);
        --gradient-light: linear-gradient(135deg, #0672e4 0%, #0672e4 100%);
        
        --radius: 16px;
        --radius-lg: 24px;
        --shadow: 0 8px 32px rgba(67, 97, 238, 0.15);
        --shadow-lg: 0 20px 40px rgba(67, 97, 238, 0.25);
        --shadow-hover: 0 25px 50px rgba(67, 97, 238, 0.3);
        --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.mobile-show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            overflow: hidden;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .stat-card.total::before { background-color: var(--primary-color); }
        .stat-card.en_attente::before { background-color: var(--warning-color); }
        .stat-card.approuve::before { background-color: var(--success-color); }
        .stat-card.rejete::before { background-color: var(--danger-color); }
        
        /* Table Styling */
        .data-table {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .data-table .table {
            margin-bottom: 0;
        }
        
        .data-table .table thead {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .data-table .table th {
            border: none;
            padding: 16px 12px;
            font-weight: 600;
        }
        
        .data-table .table td {
            padding: 14px 12px;
            vertical-align: middle;
            border-color: #f1f3f9;
        }
        
        .data-table .table tbody tr {
            transition: var(--transition);
        }
        
        .data-table .table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85em;
        }
        
        .badge-en_attente {
            background: rgba(247, 37, 133, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(247, 37, 133, 0.2);
        }
        
        .badge-approuve {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }
        
        .badge-rejete {
            background: rgba(114, 9, 183, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(114, 9, 183, 0.2);
        }
        
        .badge-type {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(67, 97, 238, 0.2);
        }
        
        /* Buttons */
        .btn-action {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: var(--transition);
            border: none;
        }
        
        .btn-view {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .btn-view:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-approve {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success-color);
        }
        
        .btn-approve:hover {
            background: var(--success-color);
            color: white;
        }
        
        .btn-reject {
            background: rgba(114, 9, 183, 0.1);
            color: var(--danger-color);
        }
        
        .btn-reject:hover {
            background: var(--danger-color);
            color: white;
        }
        
        /* Employee Avatar */
        .employee-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .avatar-initials {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        /* Search Bar */
        .search-container {
            position: relative;
        }
        
        .search-container .form-control {
            padding-left: 45px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            height: 45px;
        }
        
        .search-container .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        /* Modal */
        .modal-xl {
            max-width: 900px;
        }
        
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }
        
        @media (max-width: 992px) {
            .mobile-toggle {
                display: flex;
            }
        }
        
        /* Responsive Table */
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: var(--border-radius);
            }
            
            .data-table .table th,
            .data-table .table td {
                padding: 10px 8px;
                font-size: 0.9em;
            }
            
            .btn-action {
                padding: 6px 12px;
                font-size: 0.85em;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(67, 97, 238, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-4">
            <!-- Logo -->
            <div class="d-flex align-items-center mb-5">
                <div class="bg-white p-2 rounded me-3">
                    <i class="fas fa-shield-alt text-primary fs-4"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold">Admin Pro</h4>
                    <small class="text-white-50">Gestion RH</small>
                </div>
            </div>
            
            <!-- Navigation -->
            <ul class="nav nav-pills flex-column">
                <li class="nav-item mb-2">
                    <a href="admin_dashboard_unifie.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_demandes.php" class="nav-link active">
                        <i class="fas fa-tasks"></i> Demandes
                        <?php if ($stats['en_attente'] > 0): ?>
                        <span class="badge bg-warning float-end"><?= $stats['en_attente'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_demandes.php?type=conge" class="nav-link">
                        <i class="fas fa-umbrella-beach"></i> Congés
                        <span class="badge bg-success float-end"><?= $stats['conges'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_demandes.php?type=absence" class="nav-link">
                        <i class="fas fa-user-slash"></i> Absences
                        <span class="badge bg-danger float-end"><?= $stats['absences'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_demandes.php?type=retard" class="nav-link">
                        <i class="fas fa-clock"></i> Retards
                        <span class="badge bg-warning float-end"><?= $stats['retards'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_demandes.php?type=maladie" class="nav-link">
                        <i class="fas fa-head-side-cough"></i> Maladies
                        <span class="badge bg-info float-end"><?= $stats['maladies'] ?? 0 ?></span>
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_employes.php" class="nav-link">
                        <i class="fas fa-users"></i> Employés
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i> Rapports
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a href="admin_settings.php" class="nav-link">
                        <i class="fas fa-cog"></i> Paramètres
                    </a>
                </li>
            </ul>
            
            <!-- User Profile -->
            <div class="mt-auto pt-4 border-top border-white-10">
                <div class="d-flex align-items-center">
                    <div class="avatar-initials me-3">
                        <?= strtoupper(substr(($_SESSION['admin_prenom'] ?? 'A'),0,1) . substr(($_SESSION['admin_nom'] ?? 'D'),0,1)) ?>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?= htmlspecialchars(($_SESSION['admin_prenom'] ?? '') . ' ' . ($_SESSION['admin_nom'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></h6>
                        <small class="text-white-50"><?= ucfirst($_SESSION['role'] ?? 'admin') ?></small>
                    </div>
                    <a href="logout.php" class="text-white-50" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-5">
            <div class="mb-3 mb-md-0">
                <h1 class="h3 fw-bold mb-2">Gestion des demandes</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="admin_dashboard_unifie.php" class="text-decoration-none">Tableau de bord</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Demandes</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Quick Stats -->
            <div class="d-flex gap-3">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-2 rounded me-2">
                        <i class="fas fa-clock text-primary"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">En attente</small>
                        <span class="fw-bold"><?= $stats['en_attente'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 p-2 rounded me-2">
                        <i class="fas fa-check text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Approuvées</small>
                        <span class="fw-bold"><?= $stats['approuve'] ?? 0 ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card total h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total</h6>
                                <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                                <small class="text-muted">30 derniers jours</small>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-list-alt text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card en_attente h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">En attente</h6>
                                <h3 class="mb-0"><?= $stats['en_attente'] ?? 0 ?></h3>
                                <small class="text-muted">À traiter</small>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-clock text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card approuve h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Approuvées</h6>
                                <h3 class="mb-0"><?= $stats['approuve'] ?? 0 ?></h3>
                                <small class="text-muted">Ce mois</small>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card rejete h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Rejetées</h6>
                                <h3 class="mb-0"><?= $stats['rejete'] ?? 0 ?></h3>
                                <small class="text-muted">Ce mois</small>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="fas fa-times-circle text-danger fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-medium">Statut</label>
                                <select name="statut" class="form-select">
                                    <option value="tous">Tous les statuts</option>
                                    <option value="en_attente" <?= $filtre_statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="approuve" <?= $filtre_statut === 'approuve' ? 'selected' : '' ?>>Approuvé</option>
                                    <option value="rejete" <?= $filtre_statut === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-medium">Type</label>
                                <select name="type" class="form-select">
                                    <option value="tous">Tous les types</option>
                                    <option value="conge" <?= $filtre_type === 'conge' ? 'selected' : '' ?>>Congé</option>
                                    <option value="retard" <?= $filtre_type === 'retard' ? 'selected' : '' ?>>Retard</option>
                                    <option value="absence" <?= $filtre_type === 'absence' ? 'selected' : '' ?>>Absence</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-medium">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filtre_date, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-4">
                        <div class="d-flex gap-3 h-100">
                            <div class="search-container flex-grow-1">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <button type="submit" form="filterForm" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrer
                                </button>
                                <a href="admin_demandes.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sync-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demandes Table -->
        <div class="data-table">
            <div class="card-header bg-white border-0 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-list me-2"></i> Liste des demandes
                    </h5>
                    <span class="badge bg-primary">
                        <?= $total_demandes ?> demande(s)
                    </span>
                </div>
            </div>
            
            <?php if (empty($demandes)): ?>
            <div class="card-body">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="mt-3 mb-2">Aucune demande trouvée</h5>
                    <p class="text-muted mb-4">Aucune demande ne correspond à vos critères de recherche</p>
                    <a href="admin_demandes.php" class="btn btn-primary">
                        <i class="fas fa-sync-alt me-1"></i> Réinitialiser les filtres
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="demandesTable">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Type</th>
                            <th>Date demande</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $demande): ?>
                        <?php
                            $nomComplet = trim(($demande['prenom'] ?? '') . ' ' . ($demande['nom'] ?? ''));
                            $poste = $demande['poste'] ?? '';
                            $departement = $demande['departement'] ?? '';
                            $type = $demande['type'] ?? '';
                            $dateDemande = !empty($demande['date_demande']) ? date('d/m/Y H:i', strtotime($demande['date_demande'])) : '';
                            $statut = $demande['statut'] ?? '';
                            $heuresEcoulees = $demande['heures_ecoulees'] ?? 0;
                            $isUrgent = ($heuresEcoulees < 24 && $statut === 'en_attente');
                            $badgeClass = "badge-" . $statut;
                            $initiales = strtoupper(substr($demande['prenom'] ?? '',0,1) . substr($demande['nom'] ?? '',0,1));
                        ?>
                        <tr class="<?= $isUrgent ? 'table-warning' : '' ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($demande['photo'])): ?>
                                    <img src="<?= htmlspecialchars($demande['photo']) ?>" 
                                         class="employee-avatar me-3"
                                         alt="<?= htmlspecialchars($nomComplet) ?>">
                                    <?php else: ?>
                                    <div class="avatar-initials me-3">
                                        <?= $initiales ?>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="mb-0 fw-medium"><?= htmlspecialchars($nomComplet) ?></h6>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($poste) ?> • <?= htmlspecialchars($departement) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-type">
                                    <i class="fas fa-<?= $type === 'conge' ? 'umbrella-beach' : ($type === 'retard' ? 'clock' : 'user-slash') ?> me-1"></i>
                                    <?= htmlspecialchars(ucfirst($type)) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-medium"><?= $dateDemande ?></span>
                                    <?php if ($isUrgent): ?>
                                    <small class="text-danger fw-medium">
                                        <i class="fas fa-exclamation-circle me-1"></i> Urgent
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <i class="fas fa-<?= $statut === 'approuve' ? 'check' : ($statut === 'rejete' ? 'times' : 'clock') ?> me-1"></i>
                                    <?= htmlspecialchars(ucfirst($statut)) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-sm btn-action btn-view view-details" 
                                            data-id="<?= (int)$demande['id'] ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#detailsModal">
                                        <i class="fas fa-eye me-1"></i> Détails
                                    </button>
                                    
                                    <?php if ($demande['statut'] === 'en_attente'): ?>
                                    <button class="btn btn-sm btn-action btn-approve" 
                                            onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'approuve')">
                                        <i class="fas fa-check me-1"></i> Accorder
                                    </button>
                                    <button class="btn btn-sm btn-action btn-reject" 
                                            onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'rejete')">
                                        <i class="fas fa-times me-1"></i> Rejeter
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $queryString = $queryParams ? '&' . http_build_query($queryParams) : '';
            ?>
            <div class="card-footer bg-white border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Affichage de <strong><?= min($offset + 1, $total_demandes) ?>-<?= min($offset + $limit, $total_demandes) ?></strong> sur <strong><?= $total_demandes ?></strong> demandes
                    </div>
                    <nav aria-label="Pagination">
                        <ul class="pagination mb-0">
                            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= max(1, $page-1) . $queryString ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                if ($start > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1' . $queryString . '">1</a></li>';
                                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $start; $i <= $end; $i++) {
                                    $active = $i == $page ? ' active' : '';
                                    echo '<li class="page-item' . $active . '">';
                                    if ($active) {
                                        echo '<span class="page-link">' . $i . '</span>';
                                    } else {
                                        echo '<a class="page-link" href="?page=' . $i . $queryString . '">' . $i . '</a>';
                                    }
                                    echo '</li>';
                                }
                                
                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $queryString . '">' . $total_pages . '</a></li>';
                                }
                            ?>
                            
                            <li class="page-item<?= $page >= $total_pages ? ' disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= min($total_pages, $page+1) . $queryString ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <footer class="mt-5 pt-4 border-top">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        &copy; <?= date('Y') ?> Admin Pro. Tous droits réservés.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        Version 1.0.0 • Dernière mise à jour : <?= date('d/m/Y') ?>
                    </p>
                </div>
            </div>
        </footer>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-alt me-2"></i> Détails de la demande
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="modalDetailsContent">
                    <!-- Content loaded via AJAX -->
                    <div class="text-center py-5">
                        <div class="loading mx-auto mb-3"></div>
                        <p>Chargement des détails...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- bootstrap loaded from footer -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        // Mobile sidebar toggle
        $('#sidebarToggle').click(function() {
            $('#sidebar').toggleClass('mobile-show');
            $(this).find('i').toggleClass('fa-bars fa-times');
        });
        
        // Initialize DataTable
        $('#demandesTable').DataTable({
            responsive: true,
            searching: false,
            paging: false,
            info: false,
            ordering: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            columnDefs: [
                { orderable: false, targets: [4] }
            ]
        });
        
        // Real-time search
        let searchTimeout;
        $('#searchInput').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchValue = $(this).val();
                const currentUrl = new URL(window.location.href);
                
                if (searchValue) {
                    currentUrl.searchParams.set('search', searchValue);
                } else {
                    currentUrl.searchParams.delete('search');
                }
                currentUrl.searchParams.delete('page');
                
                window.location.href = currentUrl.toString();
            }, 500);
        });
        
        // Load modal content
        $('.view-details').click(function() {
            const demandeId = $(this).data('id');
            const modalContent = $('#modalDetailsContent');
            
            $.ajax({
                url: 'get_demande_details.php',
                type: 'GET',
                data: { id: demandeId },
                beforeSend: function() {
                    modalContent.html(`
                        <div class="text-center py-5">
                            <div class="loading mx-auto mb-3"></div>
                            <p>Chargement des détails...</p>
                        </div>
                    `);
                },
                success: function(response) {
                    modalContent.html(response);
                },
                error: function() {
                    modalContent.html(`
                        <div class="alert alert-danger m-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Impossible de charger les détails de la demande.
                        </div>
                    `);
                }
            });
        });
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            $.get('check_updates.php', function(data) {
                if (data.new_demandes > 0) {
                    // Show notification
                    if (Notification.permission === "granted") {
                        new Notification("Nouvelles demandes", {
                            body: `${data.new_demandes} nouvelle(s) demande(s) en attente`,
                            icon: "/assets/img/notification.png"
                        });
                    }
                    
                    // Update badge
                    $('.badge.bg-warning').text(data.new_demandes);
                    
                    // Optional: Reload page
                    if (data.reload_suggested) {
                        Swal.fire({
                            title: 'Nouvelles demandes',
                            text: `${data.new_demandes} nouvelle(s) demande(s) disponible(s. Voulez-vous rafraîchir la page ?`,
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Rafraîchir',
                            cancelButtonText: 'Plus tard'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                location.reload();
                            }
                        });
                    }
                }
            });
        }, 60000);
        
        // Request notification permission
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
    });
    
    function traiterDemande(id, action) {
        const actionText = action === 'approuve' ? 'approuver' : 'rejeter';
        
        Swal.fire({
            title: 'Confirmer l\'action',
            text: `Voulez-vous vraiment ${actionText} cette demande ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: action === 'approuve' ? '#4cc9f0' : '#7209b7',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('admin_demandes.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        ajax_action: 1,
                        id_demande: id,
                        action: action
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erreur lors du traitement');
                    }
                    return data;
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Succès !',
                    text: `La demande a été ${actionText}e avec succès.`,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            }
        }).catch(error => {
            Swal.fire({
                title: 'Erreur',
                text: error.message || 'Une erreur est survenue',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+F for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            $('#searchInput').focus();
        }
        
        // Escape to clear search
        if (e.key === 'Escape' && $('#searchInput').is(':focus')) {
            $('#searchInput').val('').trigger('input');
        }
    });
    </script>
</body>
</html>