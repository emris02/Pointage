<?php
require_once 'config.php';

/* ======================================================
   SÉCURITÉ : accès réservé admin / super_admin
====================================================== */

if (
    !isset($_SESSION['admin_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'super_admin'], true)
) {
    header('Location: login.php');
    exit();
}

/* ======================================================
   FONCTIONS UTILITAIRES
====================================================== */

/**
 * Génère un token de badge sécurisé
 */
function generateBadgeToken(int $employe_id): array
{
    $random    = bin2hex(random_bytes(16));
    $timestamp = time();
    $data      = "$employe_id|$random|$timestamp";
    $signature = hash_hmac('sha256', $data, SECRET_KEY);

    return [
        'token'      => base64_encode("$data|$signature"),
        'expires_at' => date('Y-m-d H:i:s', $timestamp + 7200) // 2 heures
    ];
}

/**
 * Notification email (log simplifié)
 */
function sendBadgeApprovalEmail(int $employe_id, string $token): void
{
    error_log("Badge approuvé | Employé ID: $employe_id | Token: $token");
}

/* ======================================================
   TRAITEMENT DES ACTIONS (POST)
====================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demande'])) {

    $demande_id  = (int) ($_POST['id_demande'] ?? 0);
    $action      = $_POST['action_demande'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');

    if (!in_array($action, ['approuve', 'rejete'], true)) {
        $_SESSION['error_message'] = "Action invalide.";
        header('Location: gestion_demandes_badge.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT * FROM demandes_badge WHERE id = ? FOR UPDATE"
        );
        $stmt->execute([$demande_id]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$demande || $demande['statut'] !== 'en_attente') {
            throw new Exception("Demande inexistante ou déjà traitée.");
        }

        if ($action === 'approuve') {
            $tokenData = generateBadgeToken((int)$demande['employe_id']);

            $stmt = $pdo->prepare(
                "INSERT INTO badge_tokens (employe_id, token_hash, created_at, expires_at)
                 VALUES (?, ?, NOW(), ?)"
            );
            $stmt->execute([
                $demande['employe_id'],
                password_hash($tokenData['token'], PASSWORD_BCRYPT),
                $tokenData['expires_at']
            ]);

            sendBadgeApprovalEmail(
                (int)$demande['employe_id'],
                $tokenData['token']
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE demandes_badge
             SET statut = ?, raison = ?, traite_par = ?, date_traitement = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $action,
            $commentaire,
            $_SESSION['admin_id'],
            $demande_id
        ]);

        $pdo->commit();

        $_SESSION['success_message'] =
            $action === 'approuve'
            ? "Demande approuvée et badge généré."
            : "Demande rejetée.";

    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
    }

    header('Location: gestion_demandes_badge.php');
    exit();
}

/* ======================================================
   PAGINATION & FILTRES
====================================================== */

$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$statut      = $_GET['statut'] ?? 'tous';
$departement = $_GET['departement'] ?? 'tous';
$recherche   = trim($_GET['recherche'] ?? '');

$where  = [];
$params = [];

if ($statut !== 'tous') {
    $where[]  = "d.statut = ?";
    $params[] = $statut;
}

if ($departement !== 'tous') {
    $where[]  = "e.departement = ?";
    $params[] = $departement;
}

if ($recherche !== '') {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ? OR e.matricule LIKE ?)";
    array_push(
        $params,
        "%$recherche%",
        "%$recherche%",
        "%$recherche%",
        "%$recherche%"
    );
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ======================================================
   DONNÉES PRINCIPALES
====================================================== */

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM demandes_badge d
     JOIN employes e ON d.employe_id = e.id
     $whereClause"
);
$countStmt->execute($params);
$total_demandes = (int) $countStmt->fetchColumn();
$total_pages    = max(1, (int) ceil($total_demandes / $limit));

$query = "
    SELECT
        d.*,
        e.nom, e.prenom, e.email, e.poste, e.departement, e.photo, e.matricule,
        a.nom   AS admin_nom,
        a.prenom AS admin_prenom
    FROM demandes_badge d
    JOIN employes e ON d.employe_id = e.id
    LEFT JOIN admins a ON d.traite_par = a.id
    $whereClause
    ORDER BY
        CASE WHEN d.statut = 'en_attente' THEN 0 ELSE 1 END,
        d.date_demande DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   STATISTIQUES & OPTIONS
====================================================== */

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(statut = 'en_attente') AS en_attente,
        SUM(statut = 'approuve')   AS approuve,
        SUM(statut = 'rejete')     AS rejete
     FROM demandes_badge"
)->fetch(PDO::FETCH_ASSOC);

$departements = $pdo->query(
    "SELECT DISTINCT departement
     FROM employes
     WHERE departement IS NOT NULL
     ORDER BY departement"
)->fetchAll(PDO::FETCH_COLUMN);
