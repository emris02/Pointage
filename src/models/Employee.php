<?php
/**
 * Modèle Employé
 * Système de Pointage Professionnel v2.0
 */

namespace PointagePro\Models;

use PDO;
use Exception;

class Employee {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Crée un nouvel employé
     */
    public function create(array $data): int {
        $sql = "INSERT INTO employees (
            first_name, last_name, email, phone, position, 
            department, address, hire_date, contract_type, 
            password_hash, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['position'],
            $data['department'],
            $data['address'] ?? null,
            $data['hire_date'],
            $data['contract_type'],
            password_hash($data['password'], PASSWORD_ARGON2ID)
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Récupère un employé par ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT 
            e.*,
            d.name as department_name,
            d.manager_id,
            COUNT(DISTINCT p.id) as total_clockings,
            0 as avg_daily_hours
        FROM employees e
        LEFT JOIN departments d ON e.department = d.code
        LEFT JOIN pointages p ON e.id = p.employe_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE e.id = ? AND e.status = 'active'
        GROUP BY e.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Récupère un employé par email
     */
    public function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM employees WHERE email = ? AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Met à jour un employé
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'first_name', 'last_name', 'email', 'phone', 
            'position', 'department', 'address', 'contract_type'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        $values[] = $id;
        
        $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    /**
     * Supprime un employé (soft delete)
     */
    public function delete(int $id): bool {
        $sql = "UPDATE employees SET status = 'deleted', updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }
    
    /**
     * Liste les employés avec filtres
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        $where = ["e.status = 'active'"];
        $params = [];
        
        // Filtres
        if (!empty($filters['department'])) {
            $where[] = "e.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Requête principale
        $sql = "SELECT 
            e.*,
            d.name as department_name,
            COUNT(DISTINCT p.id) as total_clockings,
            MAX(p.created_at) as last_clocking
        FROM employees e
        LEFT JOIN departments d ON e.department = d.code
        LEFT JOIN pointages p ON e.id = p.employe_id
        WHERE $whereClause
        GROUP BY e.id
        ORDER BY e.last_name, e.first_name
        LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $employees = $stmt->fetchAll();
        
        // Compter le total
        $countSql = "SELECT COUNT(DISTINCT e.id) FROM employees e WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute(array_slice($params, 0, -2));
        $total = $countStmt->fetchColumn();
        
        return [
            'data' => $employees,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Vérifie le mot de passe
     */
    public function verifyPassword(int $id, string $password): bool {
        $sql = "SELECT password_hash FROM employees WHERE id = ? AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $hash = $stmt->fetchColumn();
        return $hash && password_verify($password, $hash);
    }
    
    /**
     * Met à jour le mot de passe
     */
    public function updatePassword(int $id, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        $sql = "UPDATE employees SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$hash, $id]);
    }
    
    /**
     * Récupère les statistiques d'un employé
     */
    public function getStatistics(int $id, string $period = '30days'): array {
        $dateCondition = match($period) {
            '7days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
            'year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        };
        
        $sql = "SELECT 
            COUNT(DISTINCT DATE(created_at)) as working_days,
            COUNT(*) as total_clockings,
            0 as avg_daily_seconds,
            0 as total_seconds,
            SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_arrivals,
            SUM(CASE WHEN overtime_hours > 0 THEN TIME_TO_SEC(overtime_hours) ELSE 0 END) as overtime_seconds
        FROM pointages 
        WHERE employe_id = ? 
        AND created_at >= $dateCondition
        AND type = 'departure'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $stats = $stmt->fetch();
        
        // Conversion des secondes en heures
        $stats['avg_daily_hours'] = gmdate('H:i:s', $stats['avg_daily_seconds'] ?? 0);
        $stats['total_hours'] = gmdate('H:i:s', $stats['total_seconds'] ?? 0);
        $stats['overtime_hours'] = gmdate('H:i:s', $stats['overtime_seconds'] ?? 0);
        
        return $stats;
    }
}