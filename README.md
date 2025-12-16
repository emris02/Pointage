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

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 📞 Support

Pour toute question ou problème :
- Créez une issue sur GitHub
- Contactez l'équipe de développement

---

**Pointage Xpert Pro** - Solution moderne de gestion du pointage