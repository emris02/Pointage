<?php
class DemandeController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function getAll($filters = []) {
        try {
            $sql = "
                SELECT d.*, 
                       e.prenom, 
                       e.nom, 
                       e.email, 
                       e.poste, 
                       e.departement,
                       e.photo,
                       e.telephone,
                       e.date_embauche,
                       a.prenom as assignee_prenom,
                       a.nom as assignee_nom,
                       b.token,
                       b.expires_at
                FROM demandes d
                JOIN employes e ON d.employe_id = e.id
                LEFT JOIN admins a ON d.assignee_id = a.id
                LEFT JOIN badge_tokens b ON e.id = b.employe_id AND b.status = 'active' AND b.expires_at > NOW()
                WHERE 1=1
            ";
            
            $params = [];
            
            // Filtre par statut
            if (!empty($filters['statut'])) {
                if (is_array($filters['statut'])) {
                    $placeholders = str_repeat('?,', count($filters['statut']) - 1) . '?';
                    $sql .= " AND d.statut IN ($placeholders)";
                    $params = array_merge($params, $filters['statut']);
                } else {
                    $sql .= " AND d.statut = ?";
                    $params[] = $filters['statut'];
                }
            }
            
            // Filtre par type
            if (!empty($filters['type'])) {
                if (is_array($filters['type'])) {
                    $placeholders = str_repeat('?,', count($filters['type']) - 1) . '?';
                    $sql .= " AND d.type IN ($placeholders)";
                    $params = array_merge($params, $filters['type']);
                } else {
                    $sql .= " AND d.type = ?";
                    $params[] = $filters['type'];
                }
            }
            
            // Filtre par département
            if (!empty($filters['departement'])) {
                $sql .= " AND e.departement = ?";
                $params[] = $filters['departement'];
            }
            
            // Filtre par employé
            if (!empty($filters['employe_id'])) {
                $sql .= " AND d.employe_id = ?";
                $params[] = $filters['employe_id'];
            }
            
            // Filtre par assignation
            if (!empty($filters['assignee_id'])) {
                $sql .= " AND d.assignee_id = ?";
                $params[] = $filters['assignee_id'];
            }
            
            // Filtre par priorité
            if (!empty($filters['priorite'])) {
                $sql .= " AND d.priorite = ?";
                $params[] = $filters['priorite'];
            }
            
            // Filtre par demande urgente
            if (isset($filters['est_urgent'])) {
                $sql .= " AND d.est_urgent = ?";
                $params[] = $filters['est_urgent'] ? 1 : 0;
            }
            
            // Filtre par demande importante
            if (isset($filters['est_important'])) {
                $sql .= " AND d.est_important = ?";
                $params[] = $filters['est_important'] ? 1 : 0;
            }
            
            // Filtre par date de début
            if (!empty($filters['date_debut'])) {
                $sql .= " AND DATE(d.date_demande) >= ?";
                $params[] = $filters['date_debut'];
            }
            
            // Filtre par date de fin
            if (!empty($filters['date_fin'])) {
                $sql .= " AND DATE(d.date_demande) <= ?";
                $params[] = $filters['date_fin'];
            }
            
            // Recherche texte
            if (!empty($filters['search'])) {
                $search = "%{$filters['search']}%";
                $sql .= " AND (d.motif LIKE ? OR d.description LIKE ? OR e.prenom LIKE ? OR e.nom LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
            
            // Ordre de tri
            $orderBy = $filters['order_by'] ?? 'd.date_demande';
            $orderDir = $filters['order_dir'] ?? 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
            
            // Limite pour les résultats
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
            }
            
            // Pagination
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur DemandeController::getAll: " . $e->getMessage());
            return [];
        }
    }

    // MÉTHODE EXISTANTE
    public function getCountByStatus() {
        try {
            $sql = "
                SELECT statut, COUNT(*) as count 
                FROM demandes 
                GROUP BY statut 
                ORDER BY count DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur DemandeController::getCountByStatus: " . $e->getMessage());
            return [];
        }
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function getStats($periode = 'month') {
        try {
            $dateCondition = "";
            switch ($periode) {
                case 'today':
                    $dateCondition = " AND DATE(d.date_demande) = CURDATE()";
                    break;
                case 'week':
                    $dateCondition = " AND d.date_demande >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $dateCondition = " AND d.date_demande >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
                case 'year':
                    $dateCondition = " AND d.date_demande >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                    break;
                default:
                    $dateCondition = " AND d.date_demande >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            }
            
            $sql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
                    SUM(CASE WHEN statut = 'approuve' THEN 1 ELSE 0 END) as approuve,
                    SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) as rejete,
                    SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annule,
                    SUM(CASE WHEN est_urgent = 1 THEN 1 ELSE 0 END) as urgentes,
                    SUM(CASE WHEN est_important = 1 THEN 1 ELSE 0 END) as importantes,
                    COUNT(DISTINCT employe_id) as employes_uniques,
                    AVG(TIMESTAMPDIFF(DAY, date_demande, COALESCE(date_maj, NOW()))) as delai_moyen
                FROM demandes 
                WHERE 1=1 {$dateCondition}
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur DemandeController::getStats: " . $e->getMessage());
            return [
                'total' => 0,
                'en_attente' => 0,
                'en_cours' => 0,
                'approuve' => 0,
                'rejete' => 0,
                'annule' => 0,
                'urgentes' => 0,
                'importantes' => 0,
                'employes_uniques' => 0,
                'delai_moyen' => 0
            ];
        }
    }

    // MÉTHODE EXISTANTE
    public function getRecent($limit = 5) {
        return $this->getAll(['limit' => $limit, 'order_by' => 'd.date_demande', 'order_dir' => 'DESC']);
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function getUrgentes($limit = null) {
        $filters = [
            'est_urgent' => true, 
            'statut' => ['en_attente', 'en_cours'],
            'order_by' => 'd.date_demande', 
            'order_dir' => 'ASC'
        ];
        
        if ($limit) {
            $filters['limit'] = $limit;
        }
        
        return $this->getAll($filters);
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function show($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.*, 
                       e.prenom, 
                       e.nom, 
                       e.email, 
                       e.poste, 
                       e.departement, 
                       e.photo, 
                       e.telephone,
                       e.date_embauche,
                       a.prenom as assignee_prenom,
                       a.nom as assignee_nom
                FROM demandes d
                JOIN employes e ON d.employe_id = e.id
                LEFT JOIN admins a ON d.assignee_id = a.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur DemandeController::show: " . $e->getMessage());
            return null;
        }
    }

    // MÉTHODE EXISTANTE
    public function getHistorique($demande_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT dh.*, 
                       u.prenom as auteur_prenom, 
                       u.nom as auteur_nom
                FROM demande_historique dh
                LEFT JOIN admins u ON dh.auteur_id = u.id
                WHERE dh.demande_id = ?
                ORDER BY dh.date_creation DESC
            ");
            $stmt->execute([$demande_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur DemandeController::getHistorique: " . $e->getMessage());
            return [];
        }
    }

    // MÉTHODE EXISTANTE
    public function getDocuments($demande_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM demande_documents 
                WHERE demande_id = ? 
                ORDER BY date_upload DESC
            ");
            $stmt->execute([$demande_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur DemandeController::getDocuments: " . $e->getMessage());
            return [];
        }
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function updateStatut($demande_id, $nouveau_statut, $commentaire, $auteur_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Récupérer l'ancien statut
            $stmt = $this->pdo->prepare("SELECT statut FROM demandes WHERE id = ?");
            $stmt->execute([$demande_id]);
            $ancien_statut = $stmt->fetchColumn();

            // Mettre à jour la demande
            $stmt = $this->pdo->prepare("
                UPDATE demandes 
                SET statut = ?, date_maj = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$nouveau_statut, $demande_id]);

            // Ajouter à l'historique
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_historique 
                (demande_id, ancien_statut, nouveau_statut, commentaire, auteur_id, type, titre, description)
                VALUES (?, ?, ?, ?, ?, 'statut', 'Changement de statut', ?)
            ");
            
            $description = "Statut modifié de '{$ancien_statut}' à '{$nouveau_statut}'";
            if (!empty($commentaire)) {
                $description .= ". Commentaire: {$commentaire}";
            }
            
            $stmt->execute([
                $demande_id, 
                $ancien_statut, 
                $nouveau_statut, 
                $commentaire, 
                $auteur_id,
                $description
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::updateStatut: " . $e->getMessage());
            return false;
        }
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function addCommentaire($demande_id, $commentaire, $auteur_id, $is_interne = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_commentaires 
                (demande_id, contenu, auteur_id, is_interne, date_creation)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([$demande_id, $commentaire, $auteur_id, $is_interne]);
            
            // Ajouter également à l'historique
            if ($result) {
                $titre = $is_interne ? 'Note interne ajoutée' : 'Commentaire ajouté';
                $stmt = $this->pdo->prepare("
                    INSERT INTO demande_historique 
                    (demande_id, commentaire, auteur_id, type, titre, description)
                    VALUES (?, ?, ?, 'commentaire', ?, ?)
                ");
                $stmt->execute([$demande_id, $commentaire, $auteur_id, $titre, $commentaire]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Erreur DemandeController::addCommentaire: " . $e->getMessage());
            return false;
        }
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function uploadDocument($demande_id, $file) {
        try {
            // Vérifier la taille du fichier (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception("Le fichier est trop volumineux (max 10MB)");
            }
            
            // Vérifier le type de fichier
            $allowedTypes = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain'
            ];
            
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception("Type de fichier non autorisé");
            }
            
            $uploadDir = __DIR__ . '/../uploads/demandes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO demande_documents 
                    (demande_id, nom_fichier, chemin_fichier, type_fichier, taille_fichier, date_upload)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $result = $stmt->execute([
                    $demande_id,
                    $file['name'],
                    'uploads/demandes/' . $fileName,
                    $file['type'],
                    $file['size']
                ]);
                
                // Ajouter à l'historique
                if ($result) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO demande_historique 
                        (demande_id, auteur_id, type, titre, description)
                        VALUES (?, 1, 'document', 'Document uploadé', ?)
                    ");
                    $stmt->execute([$demande_id, "Fichier: {$file['name']}"]);
                }
                
                return $result;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur DemandeController::uploadDocument: " . $e->getMessage());
            throw $e;
        }
    }

    // MÉTHODE EXISTANTE - AMÉLIORÉE
    public function create($data) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO demandes 
                (employe_id, type, motif, description, statut, priorite, date_debut, date_fin, commentaire_interne)
                VALUES (?, ?, ?, ?, 'en_attente', ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['employe_id'],
                $data['type'],
                $data['motif'],
                $data['description'] ?? '',
                $data['priorite'] ?? 'medium',
                $data['date_debut'] ?? null,
                $data['date_fin'] ?? null,
                $data['commentaire_interne'] ?? null
            ]);
            
            if ($result) {
                $demande_id = $this->pdo->lastInsertId();
                
                // Ajouter à l'historique
                $stmt = $this->pdo->prepare("
                    INSERT INTO demande_historique 
                    (demande_id, auteur_id, type, titre, description)
                    VALUES (?, ?, 'creation', 'Demande créée', ?)
                ");
                $stmt->execute([$demande_id, $data['employe_id'], "Nouvelle demande de {$data['type']}"]);
            }
            
            $this->pdo->commit();
            return $result ? $demande_id : false;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::create: " . $e->getMessage());
            return false;
        }
    }

    // NOUVELLES MÉTHODES AJOUTÉES

    /**
     * Récupère les commentaires d'une demande
     */
    public function getCommentaires($demande_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT dc.*, 
                       a.prenom as auteur_prenom, 
                       a.nom as auteur_nom
                FROM demande_commentaires dc
                LEFT JOIN admins a ON dc.auteur_id = a.id
                WHERE dc.demande_id = ?
                ORDER BY dc.date_creation DESC
            ");
            $stmt->execute([$demande_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur DemandeController::getCommentaires: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marque une demande comme vue par un admin
     */
    public function marquerCommeVue($demande_id, $admin_id) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_vues (demande_id, admin_id, date_vue)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE date_vue = NOW()
            ");
            return $stmt->execute([$demande_id, $admin_id]);
        } catch (Exception $e) {
            error_log("Erreur DemandeController::marquerCommeVue: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marque une demande comme urgente
     */
    public function marquerUrgent($demande_id, $admin_id) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE demandes 
                SET est_urgent = 1, date_maj = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$demande_id]);
            
            // Ajouter à l'historique
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_historique 
                (demande_id, auteur_id, type, titre, description)
                VALUES (?, ?, 'marquage', 'Marquage urgent', 'Demande marquée comme urgente')
            ");
            $stmt->execute([$demande_id, $admin_id]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::marquerUrgent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marque une demande comme importante
     */
    public function marquerImportant($demande_id, $admin_id) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE demandes 
                SET est_important = 1, date_maj = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$demande_id]);
            
            // Ajouter à l'historique
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_historique 
                (demande_id, auteur_id, type, titre, description)
                VALUES (?, ?, 'marquage', 'Marquage important', 'Demande marquée comme importante')
            ");
            $stmt->execute([$demande_id, $admin_id]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::marquerImportant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime une demande (super_admin seulement)
     */
    public function delete($demande_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Supprimer les documents physiques
            $documents = $this->getDocuments($demande_id);
            foreach ($documents as $doc) {
                $filePath = __DIR__ . '/../' . $doc['chemin_fichier'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Supprimer les enregistrements liés
            $tables = ['demande_documents', 'demande_commentaires', 'demande_historique', 'demande_vues'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE demande_id = ?");
                $stmt->execute([$demande_id]);
            }
            
            // Supprimer la demande
            $stmt = $this->pdo->prepare("DELETE FROM demandes WHERE id = ?");
            $result = $stmt->execute([$demande_id]);
            
            $this->pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::delete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Duplique une demande existante
     */
    public function duplicate($demande_id, $admin_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Récupérer la demande originale
            $original = $this->show($demande_id);
            if (!$original) {
                throw new Exception("Demande originale non trouvée");
            }
            
            // Créer la nouvelle demande
            $stmt = $this->pdo->prepare("
                INSERT INTO demandes 
                (employe_id, type, motif, description, statut, priorite, date_debut, date_fin, commentaire_interne)
                VALUES (?, ?, ?, ?, 'en_attente', ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $original['employe_id'],
                $original['type'],
                $original['motif'] . " (Copie)",
                $original['description'],
                $original['priorite'],
                $original['date_debut'],
                $original['date_fin'],
                $original['commentaire_interne'] . " - Dupliquée de #{$demande_id}"
            ]);
            
            if (!$result) {
                throw new Exception("Erreur lors de la duplication");
            }
            
            $new_demande_id = $this->pdo->lastInsertId();
            
            // Ajouter à l'historique
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_historique 
                (demande_id, auteur_id, type, titre, description)
                VALUES (?, ?, 'duplication', 'Demande dupliquée', ?)
            ");
            $stmt->execute([$new_demande_id, $admin_id, "Dupliquée de la demande #{$demande_id}"]);
            
            $this->pdo->commit();
            return $new_demande_id;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::duplicate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour la priorité d'une demande
     */
    public function updatePriorite($demande_id, $priorite, $admin_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Récupérer l'ancienne priorité
            $stmt = $this->pdo->prepare("SELECT priorite FROM demandes WHERE id = ?");
            $stmt->execute([$demande_id]);
            $ancienne_priorite = $stmt->fetchColumn();

            // Mettre à jour la demande
            $stmt = $this->pdo->prepare("
                UPDATE demandes 
                SET priorite = ?, date_maj = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$priorite, $demande_id]);
            
            // Ajouter à l'historique
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_historique 
                (demande_id, auteur_id, type, titre, description)
                VALUES (?, ?, 'priorite', 'Changement de priorité', ?)
            ");
            $description = "Priorité modifiée de '{$ancienne_priorite}' à '{$priorite}'";
            $stmt->execute([$demande_id, $admin_id, $description]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::updatePriorite: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assigne une demande à un administrateur
     */
    public function assignerDemande($demande_id, $assignee_id, $admin_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Récupérer l'ancien assigné
            $stmt = $this->pdo->prepare("SELECT assignee_id FROM demandes WHERE id = ?");
            $stmt->execute([$demande_id]);
            $ancien_assignee = $stmt->fetchColumn();

            // Mettre à jour la demande
            $stmt = $this->pdo->prepare("
                UPDATE demandes 
                SET assignee_id = ?, date_maj = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$assignee_id, $demande_id]);
            
            // Ajouter à l'historique
            $stmt = $this->pdo->prepare("
                INSERT INTO demande_historique 
                (demande_id, auteur_id, type, titre, description)
                VALUES (?, ?, 'assignation', 'Assignation modifiée', ?)
            ");
            
            $description = "Demande assignée à l'administrateur ID: {$assignee_id}";
            if ($ancien_assignee) {
                $description = "Assignation modifiée de {$ancien_assignee} à {$assignee_id}";
            }
            
            $stmt->execute([$demande_id, $admin_id, $description]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur DemandeController::assignerDemande: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les demandes assignées à un admin
     */
    public function getDemandesAssignees($admin_id) {
        return $this->getAll(['assignee_id' => $admin_id]);
    }

    /**
     * Récupère les demandes importantes
     */
    public function getImportantes($limit = null) {
        $filters = [
            'est_important' => true, 
            'statut' => ['en_attente', 'en_cours'],
            'order_by' => 'd.date_demande', 
            'order_dir' => 'ASC'
        ];
        
        if ($limit) {
            $filters['limit'] = $limit;
        }
        
        return $this->getAll($filters);
    }

    /**
     * Compte le nombre total de demandes
     */
    public function count($filters = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM demandes d JOIN employes e ON d.employe_id = e.id WHERE 1=1";
            $params = [];
            
            // Appliquer les mêmes filtres que getAll()
            if (!empty($filters['statut'])) {
                $sql .= " AND d.statut = ?";
                $params[] = $filters['statut'];
            }
            
            if (!empty($filters['type'])) {
                $sql .= " AND d.type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['departement'])) {
                $sql .= " AND e.departement = ?";
                $params[] = $filters['departement'];
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Erreur DemandeController::count: " . $e->getMessage());
            return 0;
        }
    }
}
?>