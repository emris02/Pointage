-- Schéma de base de données optimisé
-- Système de Pointage Professionnel v2.0

SET FOREIGN_KEY_CHECKS = 0;

-- Table des départements
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    manager_id INT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des employés (optimisée)
CREATE TABLE IF NOT EXISTS employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    position VARCHAR(100),
    department VARCHAR(20),
    address TEXT,
    hire_date DATE,
    contract_type ENUM('CDI', 'CDD', 'Stage', 'Freelance') DEFAULT 'CDI',
    password_hash VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255),
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    last_clocking_at TIMESTAMP NULL,
    total_clockings INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (department) REFERENCES departments(code) ON UPDATE CASCADE,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_department (department),
    INDEX idx_employee_number (employee_number),
    FULLTEXT idx_search (first_name, last_name, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS administrators (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'super_admin', 'hr_manager') DEFAULT 'admin',
    permissions JSON,
    last_login_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tokens de badge (optimisée)
CREATE TABLE IF NOT EXISTS badge_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    token_hash VARCHAR(128) UNIQUE NOT NULL,
    type ENUM('standard', 'temporary', 'emergency') DEFAULT 'standard',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    status ENUM('active', 'used', 'expired', 'revoked') DEFAULT 'active',
    device_info JSON,
    ip_address VARCHAR(45),
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_employee_status (employee_id, status),
    INDEX idx_expires (expires_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des pointages (optimisée)
CREATE TABLE IF NOT EXISTS pointages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    type ENUM('arrival', 'departure', 'break_start', 'break_end') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    device_info JSON,
    ip_address VARCHAR(45),
    badge_token_id INT,
    
    -- Données calculées
    status ENUM('valid', 'late', 'early', 'overtime') DEFAULT 'valid',
    worked_hours TIME,
    break_duration TIME,
    overtime_hours TIME,
    is_late BOOLEAN DEFAULT FALSE,
    late_minutes INT DEFAULT 0,
    
    -- Métadonnées
    notes TEXT,
    validated_by INT,
    validated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_token_id) REFERENCES badge_tokens(id) ON DELETE SET NULL,
    FOREIGN KEY (validated_by) REFERENCES administrators(id) ON DELETE SET NULL,
    
    INDEX idx_employee_date (employee_id, timestamp),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_date (timestamp),
    INDEX idx_late (is_late)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des horaires de travail
CREATE TABLE IF NOT EXISTS employee_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    day_of_week TINYINT NOT NULL, -- 1=Lundi, 7=Dimanche
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration TIME GENERATED ALWAYS AS (TIMEDIFF(end_time, start_time)) STORED,
    break_duration TIME DEFAULT '01:00:00',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_day (employee_id, day_of_week),
    INDEX idx_day (day_of_week),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des zones géographiques autorisées
CREATE TABLE IF NOT EXISTS authorized_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    radius INT DEFAULT 100, -- Rayon en mètres
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_coordinates (latitude, longitude),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison employés-zones
CREATE TABLE IF NOT EXISTS employee_locations (
    employee_id INT NOT NULL,
    location_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (employee_id, location_id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES authorized_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des demandes de badge
CREATE TABLE IF NOT EXISTS badge_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    request_type ENUM('new', 'replacement', 'temporary') NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT,
    admin_notes TEXT,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES administrators(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_employee (employee_id),
    INDEX idx_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des justificatifs
CREATE TABLE IF NOT EXISTS justifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pointage_id INT NOT NULL,
    type ENUM('late_arrival', 'early_departure', 'absence', 'overtime') NOT NULL,
    reason TEXT NOT NULL,
    supporting_document VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT,
    admin_notes TEXT,
    
    FOREIGN KEY (pointage_id) REFERENCES pointages(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES administrators(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs système
CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
    message TEXT NOT NULL,
    context JSON,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_level (level),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des sessions
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('employee', 'admin') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    data JSON,
    
    INDEX idx_user (user_id, user_type),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vues pour les rapports
CREATE OR REPLACE VIEW v_employee_stats AS
SELECT 
    e.id,
    e.employee_number,
    CONCAT(e.first_name, ' ', e.last_name) as full_name,
    e.department,
    d.name as department_name,
    COUNT(DISTINCT DATE(p.timestamp)) as working_days_current_month,
    COUNT(p.id) as total_clockings_current_month,
    SUM(CASE WHEN p.is_late = 1 THEN 1 ELSE 0 END) as late_arrivals_current_month,
    AVG(TIME_TO_SEC(p.worked_hours)) as avg_daily_seconds,
    SUM(TIME_TO_SEC(p.worked_hours)) as total_worked_seconds,
    SUM(TIME_TO_SEC(p.overtime_hours)) as total_overtime_seconds,
    e.last_clocking_at,
    e.total_clockings
FROM employees e
LEFT JOIN departments d ON e.department = d.code
LEFT JOIN pointages p ON e.id = p.employee_id 
    AND p.timestamp >= DATE_FORMAT(NOW(), '%Y-%m-01')
    AND p.type = 'departure'
WHERE e.status = 'active'
GROUP BY e.id;

-- Triggers pour maintenir les statistiques
DELIMITER //

CREATE TRIGGER tr_pointage_after_insert
AFTER INSERT ON pointages
FOR EACH ROW
BEGIN
    UPDATE employees 
    SET 
        last_clocking_at = NEW.timestamp,
        total_clockings = total_clockings + 1
    WHERE id = NEW.employee_id;
END//

CREATE TRIGGER tr_badge_token_before_insert
BEFORE INSERT ON badge_tokens
FOR EACH ROW
BEGIN
    -- Révoquer les anciens tokens actifs
    UPDATE badge_tokens 
    SET status = 'revoked' 
    WHERE employee_id = NEW.employee_id 
    AND status = 'active' 
    AND expires_at > NOW();
END//

DELIMITER ;

-- Données de test
INSERT INTO departments (code, name, description) VALUES
('IT', 'Informatique', 'Département des technologies de l\'information'),
('HR', 'Ressources Humaines', 'Gestion du personnel et recrutement'),
('SALES', 'Commercial', 'Équipe commerciale et ventes'),
('ADMIN', 'Administration', 'Administration générale');

INSERT INTO administrators (username, email, password_hash, first_name, last_name, role) VALUES
('admin', 'admin@xpertpro.com', '$argon2id$v=19$m=65536,t=4,p=3$example', 'Admin', 'System', 'super_admin');

-- Index de performance supplémentaires
CREATE INDEX idx_pointages_employee_month ON pointages(employee_id, timestamp);
CREATE INDEX idx_badge_tokens_employee_active ON badge_tokens(employee_id, status, expires_at);

SET FOREIGN_KEY_CHECKS = 1;