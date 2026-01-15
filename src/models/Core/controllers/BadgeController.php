<?php
class BadgeController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Génère un nouveau badge pour un employé
     */
    public function generateBadge($employeId, $validityHours = 24) {
        try {
            // Désactiver les anciens badges
            $this->deactivateOldBadges($employeId);

            // Générer un token unique
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityHours} hours"));

            $stmt = $this->pdo->prepare("
                INSERT INTO badge_tokens (employe_id, token, expires_at, status) 
                VALUES (?, ?, ?, 'active')
            ");

            $result = $stmt->execute([$employeId, $token, $expiresAt]);

            if ($result) {
                return [
                    'id' => $this->pdo->lastInsertId(),
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'status' => 'active'
                ];
            }

            return false;

        } catch (Exception $e) {
            error_log("Erreur BadgeController::generateBadge: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère le badge actif d'un employé
     */
    public function getActiveBadge($employeId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM badge_tokens 
                WHERE employe_id = ? 
                AND status = 'active' 
                AND expires_at > NOW() 
                ORDER BY created_at DESC 
                LIMIT 1
            ");

            $stmt->execute([$employeId]);
            $badge = $stmt->fetch(PDO::FETCH_ASSOC);

            return $badge ?: false;

        } catch (Exception $e) {
            error_log("Erreur BadgeController::getActiveBadge: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Désactive les anciens badges d'un employé
     */
    private function deactivateOldBadges($employeId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE badge_tokens 
                SET status = 'inactive' 
                WHERE employe_id = ? 
                AND status = 'active'
            ");

            return $stmt->execute([$employeId]);

        } catch (Exception $e) {
            error_log("Erreur BadgeController::deactivateOldBadges: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valide un token de badge
     */
    public function validateToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT bt.*, e.prenom, e.nom, e.poste, e.departement
                FROM badge_tokens bt
                JOIN employes e ON bt.employe_id = e.id
                WHERE bt.token = ? 
                AND bt.status = 'active' 
                AND bt.expires_at > NOW()
            ");

            $stmt->execute([$token]);
            $badge = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($badge) {
                // Marquer le badge comme utilisé (optionnel)
                $this->markTokenUsed($token);
                return $badge;
            }

            return false;

        } catch (Exception $e) {
            error_log("Erreur BadgeController::validateToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marque un token comme utilisé
     */
    private function markTokenUsed($token) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE badge_tokens 
                SET last_used = NOW(), use_count = use_count + 1 
                WHERE token = ?
            ");

            return $stmt->execute([$token]);

        } catch (Exception $e) {
            error_log("Erreur BadgeController::markTokenUsed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère l'historique des badges d'un employé
     */
    public function getBadgeHistory($employeId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM badge_tokens 
                WHERE employe_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");

            $stmt->execute([$employeId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Erreur BadgeController::getBadgeHistory: " . $e->getMessage());
            return [];
        }
    }
}
?>