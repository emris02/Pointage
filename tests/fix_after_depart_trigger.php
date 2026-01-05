<?php
require __DIR__ . '/../src/config/bootstrap.php';

try {
    $sqlDrop = "DROP TRIGGER IF EXISTS after_depart_pointage";
    $pdo->exec($sqlDrop);

    $sqlCreate = <<<SQL
CREATE TRIGGER after_depart_pointage
AFTER INSERT ON pointages
FOR EACH ROW
BEGIN
    IF NEW.type = 'depart' AND NEW.employe_id IS NOT NULL THEN
        UPDATE badge_tokens 
        SET expires_at = NOW() 
        WHERE employe_id = NEW.employe_id AND expires_at > NOW();
        
        INSERT INTO badge_logs (employe_id, action, details)
        VALUES (NEW.employe_id, 'invalidation', 'Badge invalidé après pointage de départ');
    END IF;
END;
SQL;

    $pdo->exec($sqlCreate);
    echo "TRIGGER_FIXED";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
    exit(1);
}
