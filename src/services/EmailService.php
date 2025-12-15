<?php
/**
 * Service d'envoi d'emails pour le système de pointage
 */
class EmailService {
    
    /**
     * Configuration SMTP (à adapter selon votre serveur)
     */
    private static $config = [
        'smtp_host' => 'localhost', // Ou votre serveur SMTP
        'smtp_port' => 587,
        'smtp_username' => 'noreply@xpertpro.com', // À configurer
        'smtp_password' => '', // À configurer
        'from_email' => 'noreply@xpertpro.com',
        'from_name' => 'Xpert Pro - Système de Pointage'
    ];
    
    /**
     * Envoie les identifiants de connexion à un nouvel employé
     */
    public static function sendEmployeeCredentials($email, $nom, $prenom, $password, $loginUrl = 'http://localhost/pointage/login.php') {
        $subject = "Vos identifiants de connexion - Xpert Pro";
        
        $htmlBody = self::getEmployeeEmailTemplate($nom, $prenom, $password, $loginUrl);
        $textBody = self::getEmployeeTextTemplate($nom, $prenom, $password, $loginUrl);
        
        return self::sendEmail($email, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Envoie les identifiants de connexion à un nouvel admin
     */
    public static function sendAdminCredentials($email, $nom, $prenom, $password, $role, $loginUrl = 'http://localhost/pointage/login.php') {
        $subject = "Vos identifiants administrateur - Xpert Pro";
        
        $htmlBody = self::getAdminEmailTemplate($nom, $prenom, $password, $role, $loginUrl);
        $textBody = self::getAdminTextTemplate($nom, $prenom, $password, $role, $loginUrl);
        
        return self::sendEmail($email, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Envoie un email générique
     */
    private static function sendEmail($to, $subject, $htmlBody, $textBody) {
        try {
            // Pour l'instant, on utilise la fonction mail() de PHP
            // En production, utilisez PHPMailer ou SwiftMailer
            $headers = [
                'From: ' . self::$config['from_name'] . ' <' . self::$config['from_email'] . '>',
                'Reply-To: ' . self::$config['from_email'],
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="boundary123"',
                'X-Mailer: Xpert Pro System'
            ];
            
            $message = "--boundary123\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $message .= $textBody . "\r\n\r\n";
            $message .= "--boundary123\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            $message .= "--boundary123--";
            
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            
            // Log de l'envoi
            self::logEmail($to, $subject, $result ? 'success' : 'failed');
            
            return $result;
            
        } catch (Exception $e) {
            self::logEmail($to, $subject, 'error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Template HTML pour les employés
     */
    private static function getEmployeeEmailTemplate($nom, $prenom, $password, $loginUrl) {
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Vos identifiants de connexion</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0672e4, #3f37c9); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .credentials { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0672e4; }
                .btn { display: inline-block; background: #0672e4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Bienvenue chez Xpert Pro !</h1>
                    <p>Votre compte employé a été créé avec succès</p>
                </div>
                
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) . ',</h2>
                    
                    <p>Votre compte employé dans le système de pointage Xpert Pro a été créé avec succès.</p>
                    
                    <div class="credentials">
                        <h3>🔐 Vos identifiants de connexion :</h3>
                        <p><strong>Email :</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Mot de passe temporaire :</strong> <code style="background: #f1f3f4; padding: 4px 8px; border-radius: 4px; font-family: monospace;">' . htmlspecialchars($password) . '</code></p>
                    </div>
                    
                    <div class="warning">
                        <strong>⚠️ Important :</strong> Pour votre sécurité, veuillez changer ce mot de passe lors de votre première connexion.
                    </div>
                    
                    <p>Vous pouvez maintenant vous connecter à votre espace employé :</p>
                    <a href="' . $loginUrl . '" class="btn">Se connecter maintenant</a>
                    
                    <p>Si vous avez des questions, n\'hésitez pas à contacter votre responsable ou l\'équipe RH.</p>
                    
                    <p>Cordialement,<br>
                    <strong>L\'équipe Xpert Pro</strong></p>
                </div>
                
                <div class="footer">
                    <p>Cet email a été envoyé automatiquement par le système Xpert Pro.</p>
                    <p>© ' . date('Y') . ' Xpert Pro. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Template texte pour les employés
     */
    private static function getEmployeeTextTemplate($nom, $prenom, $password, $loginUrl) {
        return "Bonjour $prenom $nom,

Votre compte employé dans le système de pointage Xpert Pro a été créé avec succès.

Vos identifiants de connexion :
- Email : $email
- Mot de passe temporaire : $password

IMPORTANT : Pour votre sécurité, veuillez changer ce mot de passe lors de votre première connexion.

Vous pouvez vous connecter à : $loginUrl

Si vous avez des questions, n'hésitez pas à contacter votre responsable.

Cordialement,
L'équipe Xpert Pro

---
Cet email a été envoyé automatiquement par le système Xpert Pro.
© " . date('Y') . " Xpert Pro. Tous droits réservés.";
    }
    
    /**
     * Template HTML pour les admins
     */
    private static function getAdminEmailTemplate($nom, $prenom, $password, $role, $loginUrl) {
        $roleText = ($role === 'super_admin') ? 'Super Administrateur' : 'Administrateur';
        
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Vos identifiants administrateur</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .credentials { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545; }
                .btn { display: inline-block; background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .warning { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Accès Administrateur</h1>
                    <p>Votre compte ' . $roleText . ' a été créé</p>
                </div>
                
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) . ',</h2>
                    
                    <p>Votre compte ' . strtolower($roleText) . ' dans le système de pointage Xpert Pro a été créé avec succès.</p>
                    
                    <div class="credentials">
                        <h3>🔑 Vos identifiants de connexion :</h3>
                        <p><strong>Email :</strong> ' . htmlspecialchars($email) . '</p>
                        <p><strong>Mot de passe temporaire :</strong> <code style="background: #f1f3f4; padding: 4px 8px; border-radius: 4px; font-family: monospace;">' . htmlspecialchars($password) . '</code></p>
                        <p><strong>Rôle :</strong> <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">' . $roleText . '</span></p>
                    </div>
                    
                    <div class="warning">
                        <strong>⚠️ Sécurité :</strong> Ce compte a des privilèges élevés. Veuillez changer ce mot de passe immédiatement après votre première connexion.
                    </div>
                    
                    <p>Vous pouvez maintenant accéder au panneau d\'administration :</p>
                    <a href="' . $loginUrl . '" class="btn">Accéder au panneau admin</a>
                    
                    <p>En cas de problème, contactez le super administrateur.</p>
                    
                    <p>Cordialement,<br>
                    <strong>L\'équipe Xpert Pro</strong></p>
                </div>
                
                <div class="footer">
                    <p>Cet email a été envoyé automatiquement par le système Xpert Pro.</p>
                    <p>© ' . date('Y') . ' Xpert Pro. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Template texte pour les admins
     */
    private static function getAdminTextTemplate($nom, $prenom, $password, $role, $loginUrl) {
        $roleText = ($role === 'super_admin') ? 'Super Administrateur' : 'Administrateur';
        
        return "Bonjour $prenom $nom,

Votre compte $roleText dans le système de pointage Xpert Pro a été créé avec succès.

Vos identifiants de connexion :
- Email : $email
- Mot de passe temporaire : $password
- Rôle : $roleText

SÉCURITÉ : Ce compte a des privilèges élevés. Veuillez changer ce mot de passe immédiatement après votre première connexion.

Vous pouvez accéder au panneau d'administration : $loginUrl

En cas de problème, contactez le super administrateur.

Cordialement,
L'équipe Xpert Pro

---
Cet email a été envoyé automatiquement par le système Xpert Pro.
© " . date('Y') . " Xpert Pro. Tous droits réservés.";
    }
    
    /**
     * Log des emails envoyés
     */
    private static function logEmail($to, $subject, $status) {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'email_system.log';
        $log = sprintf(
            "[%s] Email to: %s | Subject: %s | Status: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $status
        );
        
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}
