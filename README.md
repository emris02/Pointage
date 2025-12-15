<<<<<<< HEAD
# Pointage Xpert Pro

Système de gestion de pointage moderne et professionnel développé en PHP avec une architecture MVC propre.

## 🏗️ Architecture du Projet

Le projet a été restructuré selon une architecture moderne et évolutive :

```
pointage/
│
├── public/                     # Fichiers accessibles depuis le navigateur
│   ├── index.php              # Page d'accueil
│   ├── login.php              # Page de connexion
│   ├── admin_dashboard_unifie    # Dashboard administrateur
│   ├── employe_dashboard.php  # Dashboard employé
│   ├── logout.php             # Déconnexion
│   └── assets/                # Fichiers statiques
│       ├── css/               # Feuilles de style
│       ├── js/                # Scripts JavaScript
│       └── img/               # Images
│
├── src/                       # Logique applicative
│   ├── config/                # Configuration
│   │   ├── bootstrap.php      # Initialisation de l'app
│   │   ├── constants.php      # Constantes globales
│   │   └── db.php             # Connexion base de données
│   ├── controllers/           # Contrôleurs
│   │   ├── AuthController.php
│   │   ├── PointageController.php
│   │   ├── EmployeController.php
│   │   └── AdminController.php
│   ├── models/                # Modèles de données
│   │   ├── Employe.php
│   │   ├── Pointage.php
│   │   ├── Admin.php
│   │   └── Badge.php
│   └── views/                 # Templates HTML
│       ├── partials/          # Composants réutilisables
│       │   ├── header.php
│       │   ├── footer.php
│       │   ├── sidebar.php
│       │   └── alerts.php
│       ├── login.php
│       ├── admin_dashboard_unifie
│       └── employe_dashboard.php
│
├── logs/                      # Fichiers de logs
├── uploads/                   # Fichiers uploadés
├── .htaccess                  # Configuration Apache
└── README.md                  # Documentation
```

## 🚀 Installation

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Apache avec mod_rewrite activé
- Composer (optionnel)

### Configuration

1. **Cloner le projet**
   ```bash
   git clone [url-du-repo]
   cd pointage
   ```

2. **Configurer la base de données**
   - Créer une base de données MySQL nommée `pointage`
   - Importer le fichier `pointage.sql` dans votre base de données
   - Modifier les paramètres de connexion dans `src/config/db.php`

3. **Configurer Apache**
   - Assurez-vous que le module `mod_rewrite` est activé
   - Le fichier `.htaccess` est déjà configuré pour rediriger vers le dossier `public/`

4. **Permissions**
   ```bash
   chmod 755 logs/
   chmod 755 uploads/
   chmod 644 .htaccess
   ```

## 🔧 Configuration

### Base de données

Modifiez les paramètres dans `src/config/db.php` :

```php
$host     = 'localhost';
$dbname   = 'pointage';
$username = 'root';
$password = '';
```

### Constantes

Les constantes de l'application sont définies dans `src/config/constants.php` :

```php
define('APP_NAME', 'Pointage Xpert Pro');
define('APP_VERSION', '2.0.0');
define('SECRET_KEY', 'GroupeXpert2025!');
```

## 📱 Utilisation

### Connexion

1. Accédez à `http://localhost/pointage/`
2. Cliquez sur "Se connecter"
3. Utilisez vos identifiants administrateur ou employé

### Rôles

- **Super Admin** : Accès complet au système
- **Admin** : Gestion des employés et pointages
- **Employé** : Pointage personnel et consultation

### Pointage

Les employés peuvent pointer via :
- Scanner QR Code
- Interface web mobile
- Badge d'accès

## 🛠️ Développement

### Structure MVC

- **Modèles** (`src/models/`) : Gestion des données et logique métier
- **Contrôleurs** (`src/controllers/`) : Logique de traitement des requêtes
- **Vues** (`src/views/`) : Templates HTML et interface utilisateur

### Ajout de nouvelles fonctionnalités

1. **Créer un modèle** dans `src/models/`
2. **Créer un contrôleur** dans `src/controllers/`
3. **Créer une vue** dans `src/views/`
4. **Ajouter la route** dans le fichier public approprié

### Exemple d'ajout d'une fonctionnalité

```php
// 1. Modèle
class NouvelleFonctionnalite {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function getData() {
        // Logique métier
    }
}

// 2. Contrôleur
class NouvelleFonctionnaliteController {
    private $model;
    
    public function __construct(PDO $db) {
        $this->model = new NouvelleFonctionnalite($db);
    }
    
    public function index() {
        return $this->model->getData();
    }
}

// 3. Vue
<?php include '../src/views/partials/header.php'; ?>
<div class="container">
    <!-- Contenu de la page -->
</div>
<?php include '../src/views/partials/footer.php'; ?>
```

## 🎨 Personnalisation

### CSS

Les styles sont organisés dans `public/assets/css/` :
- `main.css` : Styles principaux
- `admin.css` : Interface d'administration
- `employe.css` : Interface employé
- `login.css` : Page de connexion

### JavaScript

Les scripts sont dans `public/assets/js/` :
- `main.js` : Fonctionnalités communes
- `admin.js` : Fonctionnalités admin
- `employe.js` : Fonctionnalités employé

## 🔒 Sécurité

- Protection contre les injections SQL (PDO)
- Validation des données d'entrée
- Gestion des sessions sécurisées
- Headers de sécurité HTTP
- Protection des fichiers sensibles

## 📊 Fonctionnalités

### Pour les Administrateurs
- Dashboard avec statistiques en temps réel
- Gestion des employés (CRUD)
- Consultation des pointages
- Génération de rapports
- Gestion des badges QR
- Notifications système

### Pour les Employés
- Pointage via QR Code
- Consultation de l'historique personnel
- Calcul automatique des heures
- Interface mobile responsive

## 🐛 Dépannage

### Problèmes courants

1. **Erreur 500** : Vérifiez les permissions des dossiers
2. **Base de données** : Vérifiez la connexion dans `src/config/db.php`
3. **URL rewriting** : Assurez-vous que `mod_rewrite` est activé

### Logs

Les logs sont disponibles dans le dossier `logs/` :
- `badge_system.log` : Logs du système de badges
- `pointage_system.log` : Logs des pointages

## 📝 Changelog

### Version 2.0.0
- Restructuration complète de l'architecture
- Séparation backend/frontend
- Amélioration de la sécurité
- Interface utilisateur modernisée
- Code plus maintenable et évolutif

## 🤝 Contribution

1. Fork le projet
2. Créez une branche pour votre fonctionnalité
3. Committez vos changements
4. Poussez vers la branche
5. Ouvrez une Pull Request
=======
# Système de Pointage Professionnel v2.0

## 🎯 Vue d'ensemble

Système de pointage moderne et sécurisé utilisant des badges QR Code dynamiques pour la gestion des heures de travail des employés.

## ✨ Fonctionnalités principales

### 🔐 Sécurité avancée
- **Tokens JWT** pour l'authentification
- **Badges QR dynamiques** avec expiration automatique
- **Signatures HMAC** pour la validation des tokens
- **Chiffrement Argon2ID** pour les mots de passe
- **Validation géographique** optionnelle

### 👥 Gestion des employés
- **Profils complets** avec photo et informations détaillées
- **Départements et hiérarchies**
- **Horaires de travail personnalisés**
- **Contrats multiples** (CDI, CDD, Stage, Freelance)

### ⏰ Pointage intelligent
- **Types multiples** : Arrivée, Départ, Pause
- **Calcul automatique** des heures travaillées
- **Détection des retards** et heures supplémentaires
- **Validation des règles métier**
- **Historique complet** avec statistiques

### 📊 Rapports et analytics
- **Tableaux de bord** personnalisés
- **Statistiques détaillées** par employé/département
- **Exports** CSV/PDF
- **Graphiques** de performance

### 🌐 Interface moderne
- **Design responsive** Bootstrap 5
- **PWA** (Progressive Web App)
- **Mode sombre** automatique
- **Interface multilingue** (FR/EN)

## 🏗️ Architecture technique

### Structure du projet
```
pointage-pro/
├── config/
│   └── database.php          # Configuration BDD
├── src/
│   ├── Core/
│   │   └── Security/         # Gestion sécurité
│   ├── Models/              # Modèles de données
│   ├── Services/            # Logique métier
│   └── Controllers/         # Contrôleurs API
├── public/
│   ├── api/                 # Points d'entrée API
│   ├── dashboard/           # Interfaces utilisateur
│   └── assets/              # Ressources statiques
├── database/
│   └── schema.sql           # Schéma de base de données
└── tests/                   # Tests unitaires
```

### Technologies utilisées
- **Backend** : PHP 8.1+, PDO, JWT
- **Frontend** : HTML5, CSS3, JavaScript ES6+, Bootstrap 5
- **Base de données** : MySQL 8.0+
- **Sécurité** : HTTPS, CSRF, XSS Protection
- **APIs** : RESTful, JSON

## 🚀 Installation

### Prérequis
- PHP 8.1 ou supérieur
- MySQL 8.0 ou supérieur
- Serveur web (Apache/Nginx)
- Composer (optionnel)

### Étapes d'installation

1. **Cloner le projet**
```bash
git clone https://github.com/votre-repo/pointage-pro.git
cd pointage-pro
```

2. **Configuration de la base de données**
```bash
# Créer la base de données
mysql -u root -p -e "CREATE DATABASE pointage_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importer le schéma
mysql -u root -p pointage_pro < database/schema.sql
```

3. **Configuration**
```php
// config/database.php
private const DB_CONFIG = [
    'host' => 'localhost',
    'dbname' => 'pointage_pro',
    'username' => 'votre_utilisateur',
    'password' => 'votre_mot_de_passe'
];
```

4. **Permissions**
```bash
chmod -R 755 public/
chmod -R 777 public/uploads/
```

5. **Accès**
- Interface employé : `http://votre-domaine/public/dashboard/employee.php`
- Interface admin : `http://votre-domaine/public/dashboard/admin.php`
- API : `http://votre-domaine/public/api/`

## 📱 Utilisation

### Pour les employés
1. **Connexion** avec email/mot de passe
2. **Génération automatique** du badge QR
3. **Scan du badge** pour pointer
4. **Consultation** de l'historique et statistiques

### Pour les administrateurs
1. **Gestion complète** des employés
2. **Configuration** des horaires et départements
3. **Validation** des pointages et justificatifs
4. **Génération** de rapports détaillés

## 🔧 Configuration avancée

### Sécurité
```php
// Personnaliser les clés de sécurité
public const SECRET_KEY = 'VotreCleSecrete2025!';
public const JWT_SECRET = 'VotreCleJWT2025!';
```

### Horaires de travail
```sql
-- Exemple d'horaires personnalisés
INSERT INTO employee_schedules (employee_id, day_of_week, start_time, end_time) 
VALUES (1, 1, '09:00:00', '17:00:00'); -- Lundi
```

### Zones géographiques
```sql
-- Définir une zone autorisée
INSERT INTO authorized_locations (name, latitude, longitude, radius) 
VALUES ('Bureau Principal', 48.8566, 2.3522, 100);
```

## 🧪 Tests

```bash
# Tests unitaires
php vendor/bin/phpunit tests/

# Tests d'intégration
php tests/integration/run-tests.php
```

## 📈 Performance

### Optimisations incluses
- **Index de base de données** optimisés
- **Cache** des requêtes fréquentes
- **Compression** des assets
- **CDN** pour les ressources statiques

### Métriques
- **Temps de réponse** < 200ms
- **Disponibilité** 99.9%
- **Sécurité** Grade A+ SSL Labs

## 🔒 Sécurité

### Mesures implémentées
- ✅ Validation des entrées
- ✅ Protection CSRF
- ✅ Prévention XSS
- ✅ Injection SQL impossible
- ✅ Chiffrement des données sensibles
- ✅ Logs de sécurité
- ✅ Rate limiting
- ✅ Headers de sécurité

## 📞 Support

### Documentation
- **API** : `/docs/api.md`
- **Base de données** : `/docs/database.md`
- **Déploiement** : `/docs/deployment.md`

### Contact
- **Email** : support@xpertpro.com
- **Issues** : GitHub Issues
- **Wiki** : Documentation complète
>>>>>>> 2fc47109b0d43eb3be3464bd2a12f9f4e8f82762

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

<<<<<<< HEAD
## 📞 Support

Pour toute question ou problème :
- Créez une issue sur GitHub
- Contactez l'équipe de développement

---

**Pointage Xpert Pro** - Solution moderne de gestion du pointage
=======
## 🤝 Contribution

Les contributions sont les bienvenues ! Voir `CONTRIBUTING.md` pour les guidelines.

---

**Développé avec ❤️ par l'équipe XpertPro**
>>>>>>> 2fc47109b0d43eb3be3464bd2a12f9f4e8f82762
