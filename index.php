<<<<<<< HEAD
<?php 
require_once 'src/config/bootstrap.php';

$authController = new AuthController($pdo);

// Redirection si connecté
if ($authController->isLoggedIn()) {
    $authController->redirectByRole();
}

$pageTitle = 'Xpert Pro - Pointage Intelligent par QR Code';
$metaDescription = 'Solution moderne de pointage par QR Code. Automatisez la gestion des présences avec une interface intuitive et sécurisée.';
$bodyClass = 'home-page modern-design';
$additionalCSS = [
    'assets/css/style.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
];

include 'src/views/partials/header.php';
?>

<!-- Design moderne et attrayant -->
<main class="modern-homepage">
    <!-- Section Hero avec animation -->
    <section class="hero-section-modern">
        <div class="hero-background">
            <div class="animated-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
            </div>
        </div>
        
        <div class="container">
            <div class="row align-items-center min-vh-80">
                <div class="col-lg-6 hero-content">
                    <div class="badge-modern">
                        <i class="fas fa-bolt"></i>
                        <span>Solution Innovante</span>
                    </div>
                    
                    <h1 class="hero-title-modern">
                        <span class="gradient-text">Xpert Pro</span>
                        <br>Système de Pointage<br>
                        <span class="highlight-modern">Intelligent</span>
                    </h1>
                    
                    <p class="hero-subtitle-modern">
                        Transformez votre gestion des présences avec notre solution QR Code moderne.
                        Simple, rapide et sécurisé pour les équipes d'aujourd'hui.
                    </p>
                    
                    <div class="hero-stats-modern">
                        <div class="stat-modern">
                            <div class="stat-number">+95%</div>
                            <div class="stat-label">Productivité</div>
                        </div>
                        <div class="stat-modern">
                            <div class="stat-number">-80%</div>
                            <div class="stat-label">Erreurs</div>
                        </div>
                        <div class="stat-modern">
                            <div class="stat-number">100%</div>
                            <div class="stat-label">Sécurité</div>
                        </div>
                    </div>
                    
                    <div class="cta-buttons-modern">
                        <a href="login.php?type=employee" class="btn-modern btn-primary-modern">
                            <i class="fas fa-user-check"></i>
                            <span>Connexion Employé</span>
                            <div class="btn-hover-effect"></div>
                        </a>
                        <a href="login.php?type=admin" class="btn-modern btn-secondary-modern">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard Admin</span>
                            <div class="btn-hover-effect"></div>
                        </a>
                    </div>
                    
                    <div class="trust-badges">
                        <div class="trust-item">
                            <i class="fas fa-shield-check"></i>
                            <span>RGPD Compliant</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-cloud"></i>
                            <span>Cloud Français</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-headset"></i>
                            <span>Support 7j/7</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 hero-visual">
                    <div class="floating-dashboard">
                        <!-- Dashboard flottant avec animation -->
                        <div class="dashboard-preview-modern">
                            <div class="dashboard-header-modern">
                                <div class="dashboard-title">
                                    <i class="fas fa-qrcode"></i>
                                    <h6>Pointage en direct</h6>
                                </div>
                                <div class="status-badge active">
                                    <span class="pulse"></span>
                                    En ligne
                                </div>
                            </div>
                            
                            <div class="qr-scanner-modern">
                                <div class="qr-container-modern">
                                    <div class="qr-code-animated">
                                        <svg viewBox="0 0 100 100" class="qr-svg">
                                            <!-- Pattern animated -->
                                            <path class="qr-pattern" d="M10,10 L30,10 L30,30 L10,30 Z M70,10 L90,10 L90,30 L70,30 Z M10,70 L30,70 L30,90 L10,90 Z" 
                                                  fill="#0672e4" fill-opacity="0.9"/>
                                            <g class="qr-dots">
                                                <!-- Animated dots -->
                                                <circle cx="45" cy="25" r="2" fill="#0672e4"/>
                                                <circle cx="65" cy="25" r="2" fill="#0672e4"/>
                                                <circle cx="25" cy="45" r="2" fill="#0672e4"/>
                                                <circle cx="45" cy="45" r="2" fill="#0672e4"/>
                                                <circle cx="65" cy="45" r="2" fill="#0672e4"/>
                                                <circle cx="85" cy="45" r="2" fill="#0672e4"/>
                                                <circle cx="45" cy="65" r="2" fill="#0672e4"/>
                                                <circle cx="65" cy="65" r="2" fill="#0672e4"/>
                                                <circle cx="25" cy="85" r="2" fill="#0672e4"/>
                                                <circle cx="45" cy="85" r="2" fill="#0672e4"/>
                                                <circle cx="65" cy="85" r="2" fill="#0672e4"/>
                                            </g>
                                        </svg>
                                        
                                        <!-- Animation de scan -->
                                        <div class="scan-beam">
                                            <div class="scan-line"></div>
                                        </div>
                                        
                                        <!-- Effet de brillance -->
                                        <div class="qr-glow"></div>
                                    </div>
                                </div>
                                
                                <div class="scan-info">
                                    <div class="scanning-animation">
                                        <i class="fas fa-mobile-alt"></i>
                                        <div class="scanning-text">
                                            <span class="scanning">Scan en cours...</span>
                                            <span class="success"><i class="fas fa-check"></i> Pointé !</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="live-stats">
                                <div class="stat-card-modern">
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value">42</div>
                                        <div class="stat-label">Présents</div>
                                    </div>
                                </div>
                                <div class="stat-card-modern">
                                    <div class="stat-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value">08:42</div>
                                        <div class="stat-label">Dernier scan</div>
                                    </div>
                                </div>
                                <div class="stat-card-modern">
                                    <div class="stat-icon">
                                        <i class="fas fa-bolt"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value">1.8s</div>
                                        <div class="stat-label">Temps moyen</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Fonctionnalités avec cartes animées -->
    <section class="features-modern-section">
        <div class="container">
            <div class="section-header-modern">
                <h2 class="section-title-modern">Comment ça marche ?</h2>
                <p class="section-subtitle-modern">Un processus simple en 4 étapes</p>
                <div class="title-decoration"></div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card-modern">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon-bg"></div>
                            <i class="fas fa-qrcode feature-icon"></i>
                            <div class="feature-step">1</div>
                        </div>
                        <h3 class="feature-title">Génération QR</h3>
                        <p class="feature-description">Créez des QR codes dynamiques sécurisés pour chaque session de travail</p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check"></i> Codes uniques</li>
                            <li><i class="fas fa-check"></i> Géolocalisation</li>
                            <li><i class="fas fa-check"></i> Expiration auto</li>
                        </ul>
                        <div class="feature-hover-effect"></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card-modern">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon-bg"></div>
                            <i class="fas fa-mobile-alt feature-icon"></i>
                            <div class="feature-step">2</div>
                        </div>
                        <h3 class="feature-title">Scan Mobile</h3>
                        <p class="feature-description">Scan ultra-rapide avec n'importe quel smartphone, aucune app requise</p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check"></i> Aucune installation</li>
                            <li><i class="fas fa-check"></i> Compatibilité totale</li>
                            <li><i class="fas fa-check"></i> Validation instantanée</li>
                        </ul>
                        <div class="feature-hover-effect"></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card-modern">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon-bg"></div>
                            <i class="fas fa-database feature-icon"></i>
                            <div class="feature-step">3</div>
                        </div>
                        <h3 class="feature-title">Enregistrement</h3>
                        <p class="feature-description">Données sécurisées avec horodatage certifié et chiffrement SSL</p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check"></i> Horodatage certifié</li>
                            <li><i class="fas fa-check"></i> Chiffrement AES-256</li>
                            <li><i class="fas fa-check"></i> Backup automatique</li>
                        </ul>
                        <div class="feature-hover-effect"></div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card-modern">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon-bg"></div>
                            <i class="fas fa-chart-bar feature-icon"></i>
                            <div class="feature-step">4</div>
                        </div>
                        <h3 class="feature-title">Analytics</h3>
                        <p class="feature-description">Dashboard complet avec rapports automatiques et analyses temps réel</p>
                        <ul class="feature-list">
                            <li><i class="fas fa-check"></i> Dashboard temps réel</li>
                            <li><i class="fas fa-check"></i> Export PDF/Excel</li>
                            <li><i class="fas fa-check"></i> Alertes intelligentes</li>
                        </ul>
                        <div class="feature-hover-effect"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Dashboard Preview -->
    <section class="dashboard-showcase">
        <div class="container">
            <div class="section-header-modern">
                <h2 class="section-title-modern">Des interfaces intuitives</h2>
                <p class="section-subtitle-modern">Conçues pour simplifier votre quotidien</p>
            </div>
            
            <div class="dashboard-tabs">
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="employee">
                        <i class="fas fa-user-tie"></i> Vue Employé
                    </button>
                    <button class="tab-button" data-tab="admin">
                        <i class="fas fa-chart-pie"></i> Vue Admin
                    </button>
                    <button class="tab-button" data-tab="mobile">
                        <i class="fas fa-mobile-alt"></i> Version Mobile
                    </button>
                </div>
                
                <div class="tab-content">
                    <div class="tab-pane active" id="employee-tab">
                        <div class="dashboard-preview-large">
                            <div class="dashboard-header">
                                <div class="user-info">
                                    <div class="avatar-large">JD</div>
                                    <div>
                                        <h5>Jean Dupont</h5>
                                        <p class="role">Développeur Full-Stack</p>
                                    </div>
                                </div>
                                <div class="today-info">
                                    <div class="date">Aujourd'hui, 15 Mars 2024</div>
                                    <div class="current-time">08:42:15</div>
                                </div>
                            </div>
                            
                            <div class="dashboard-grid">
                                <div class="stat-card-large">
                                    <div class="stat-icon-large">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-content-large">
                                        <div class="stat-value-large">7h 42m</div>
                                        <div class="stat-label-large">Temps travaillé</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card-large">
                                    <div class="stat-icon-large">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="stat-content-large">
                                        <div class="stat-value-large">21</div>
                                        <div class="stat-label-large">Jours présents</div>
                                    </div>
                                </div>
                                
                                <div class="upcoming-events">
                                    <h6>Prochains événements</h6>
                                    <div class="event-list">
                                        <div class="event-item">
                                            <i class="fas fa-briefcase"></i>
                                            <span>Réunion équipe - 10:00</span>
                                        </div>
                                        <div class="event-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Congé - 20 Mars</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Avantages avec design moderne -->
    <section class="benefits-modern-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="benefits-visual">
                        <div class="floating-elements">
                            <div class="floating-element speed">
                                <i class="fas fa-bolt"></i>
                                <span>Rapidité</span>
                            </div>
                            <div class="floating-element security">
                                <i class="fas fa-shield-alt"></i>
                                <span>Sécurité</span>
                            </div>
                            <div class="floating-element automation">
                                <i class="fas fa-robot"></i>
                                <span>Automatisation</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="benefits-content">
                        <h2 class="benefits-title">Pourquoi choisir Xpert Pro ?</h2>
                        
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-stopwatch"></i>
                            </div>
                            <div>
                                <h4>Gain de temps exceptionnel</h4>
                                <p>Réduisez de 80% le temps consacré à la gestion des présences</p>
                            </div>
                        </div>
                        
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h4>Précision maximale</h4>
                                <p>Horodatage certifié et données 100% fiables</p>
                            </div>
                        </div>
                        
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div>
                                <h4>Sécurité renforcée</h4>
                                <p>Système anti-fraude avec géolocalisation et QR codes uniques</p>
                            </div>
                        </div>
                        
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-euro-sign"></i>
                            </div>
                            <div>
                                <h4>Rentabilité immédiate</h4>
                                <p>Réduction des coûts administratifs dès le premier mois</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section CTA Finale -->
    <section class="final-cta">
        <div class="container">
            <div class="cta-card">
                <div class="cta-content">
                    <h2 class="cta-title">Prêt à révolutionner votre pointage ?</h2>
                    <p class="cta-subtitle">Rejoignez les 500+ entreprises qui nous font déjà confiance</p>
                    
                    <div class="cta-stats">
                        <div class="cta-stat">
                            <div class="stat-number">30</div>
                            <div class="stat-label">Jours d'essai gratuit</div>
                        </div>
                        <div class="cta-stat">
                            <div class="stat-number">0€</div>
                            <div class="stat-label">Sans engagement</div>
                        </div>
                        <div class="cta-stat">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support inclus</div>
                        </div>
                    </div>
                    
                    <div class="cta-buttons">
                        <a href="register.php" class="btn-cta-primary">
                            <i class="fas fa-rocket"></i>
                            Démarrer gratuitement
                        </a>
                        <a href="#demo" class="btn-cta-secondary">
                            <i class="fas fa-play-circle"></i>
                            Voir la démo
                        </a>
                    </div>
                    
                    <div class="cta-footer">
                        <i class="fas fa-lock"></i>
                        <span>Données sécurisées • RGPD compliant • Hébergement France</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* =======================================================
   VARIABLES MODERNES
======================================================= */
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

/* =======================================================
   RESET & BASE MODERNE
======================================================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--text);
    line-height: 1.6;
    overflow-x: hidden;
    background: var(--light);
}

.modern-homepage {
    background: linear-gradient(180deg, #ffff 0%, #f8fafc 100%);
}

/* =======================================================
   HERO SECTION MODERNE
======================================================= */
.hero-section-modern {
    position: relative;
    min-height: 100vh;
    padding: 100px 0;
    background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
    overflow: hidden;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 0;
}

.animated-shapes {
    position: absolute;
    width: 100%;
    height: 100%;
}

.shape {
    position: absolute;
    border-radius: 50%;
    background: var(--primary-light);
    opacity: 0.1;
    animation: float 20s infinite ease-in-out;
}

.shape-1 {
    width: 300px;
    height: 300px;
    top: -150px;
    right: -150px;
    animation-delay: 0s;
}

.shape-2 {
    width: 200px;
    height: 200px;
    bottom: 100px;
    left: -100px;
    background: var(--accent);
    animation-delay: 5s;
}

.shape-3 {
    width: 150px;
    height: 150px;
    top: 50%;
    right: 20%;
    background: var(--secondary);
    animation-delay: 10s;
}

.shape-4 {
    width: 100px;
    height: 100px;
    bottom: 200px;
    right: 30%;
    animation-delay: 15s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    33% { transform: translateY(-20px) rotate(120deg); }
    66% { transform: translateY(20px) rotate(240deg); }
}

.hero-content {
    position: relative;
    z-index: 2;
}

.badge-modern {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 24px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(67, 97, 238, 0.2);
}

.hero-title-modern {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 24px;
    color: var(--dark);
}

.gradient-text {
    background: var(--gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    display: inline-block;
}

.highlight-modern {
    position: relative;
    display: inline-block;
}

.highlight-modern::after {
    content: '';
    position: absolute;
    bottom: 5px;
    left: 0;
    width: 100%;
    height: 8px;
    background: linear-gradient(90deg, var(--accent), transparent);
    opacity: 0.4;
    z-index: -1;
}

.hero-subtitle-modern {
    font-size: 1.25rem;
    color: var(--text-light);
    margin-bottom: 32px;
    max-width: 600px;
    line-height: 1.6;
}

.hero-stats-modern {
    display: flex;
    gap: 32px;
    margin-bottom: 40px;
}

.stat-modern {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    background: var(--gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-light);
    margin-top: 4px;
}

.cta-buttons-modern {
    display: flex;
    gap: 16px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.btn-modern {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 16px 32px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    overflow: hidden;
    transition: var(--transition);
    z-index: 1;
}

.btn-primary-modern {
    background: var(--gradient);
    color: var(--white);
    box-shadow: var(--shadow);
}

.btn-secondary-modern {
    background: var(--white);
    color: var(--primary);
    border: 2px solid var(--primary);
    box-shadow: var(--shadow);
}

.btn-hover-effect {
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
    z-index: -1;
}

.btn-modern:hover .btn-hover-effect {
    left: 100%;
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.btn-secondary-modern:hover {
    background: var(--primary);
    color: var(--white);
    transform: translateY(-2px);
}

.trust-badges {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}

.trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-light);
    font-size: 0.875rem;
}

.trust-item i {
    color: var(--success);
}

/* =======================================================
   VISUEL HERO (Dashboard animé)
======================================================= */
.hero-visual {
    position: relative;
    z-index: 2;
}

.floating-dashboard {
    animation: float-dashboard 3s ease-in-out infinite alternate;
}

@keyframes float-dashboard {
    0% { transform: translateY(0); }
    100% { transform: translateY(-10px); }
}

.dashboard-preview-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.dashboard-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.dashboard-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dashboard-title i {
    color: var(--primary);
    font-size: 1.5rem;
}

.dashboard-title h6 {
    margin: 0;
    font-weight: 600;
    color: var(--dark);
}

.status-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--success);
    color: white;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 500;
}

.pulse {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.qr-scanner-modern {
    position: relative;
    margin-bottom: 24px;
}

.qr-container-modern {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
}

.qr-code-animated {
    position: relative;
    width: 180px;
    height: 180px;
    background: white;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.qr-svg {
    width: 100%;
    height: 100%;
}

.qr-dots circle {
    animation: dot-pulse 2s infinite;
}

.qr-dots circle:nth-child(2n) {
    animation-delay: 0.5s;
}

.qr-dots circle:nth-child(3n) {
    animation-delay: 1s;
}

@keyframes dot-pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

.scan-beam {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 12px;
}

.scan-line {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent), transparent);
    animation: scan 2s infinite linear;
    box-shadow: 0 0 8px var(--accent);
}

@keyframes scan {
    0% { transform: translateY(0); }
    100% { transform: translateY(180px); }
}

.qr-glow {
    position: absolute;
    top: -10px;
    left: -10px;
    right: -10px;
    bottom: -10px;
    background: radial-gradient(circle, rgba(76, 201, 240, 0.2) 0%, transparent 70%);
    border-radius: 16px;
    animation: glow 3s infinite alternate;
}

@keyframes glow {
    0% { opacity: 0.3; }
    100% { opacity: 0.6; }
}

.scan-info {
    text-align: center;
}

.scanning-animation {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: var(--text);
}

.scanning-animation i {
    color: var(--primary);
    font-size: 1.25rem;
}

.scanning-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
}

.scanning {
    font-weight: 500;
    animation: scanning 2s infinite;
}

.success {
    color: var(--success);
    font-weight: 500;
    display: none;
}

@keyframes scanning {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

.live-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.stat-card-modern {
    background: var(--light);
    border-radius: var(--radius);
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: var(--transition);
}

.stat-card-modern:hover {
    transform: translateY(-2px);
    background: var(--white);
    box-shadow: var(--shadow);
}

.stat-icon {
    width: 40px;
    height: 40px;
    background: var(--gradient-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-light);
}

/* =======================================================
   SECTION FONCTIONNALITÉS MODERNE
======================================================= */
.features-modern-section {
    padding: 100px 0;
    background: var(--white);
}

.section-header-modern {
    text-align: center;
    margin-bottom: 60px;
}

.section-title-modern {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 16px;
}

.section-subtitle-modern {
    font-size: 1.125rem;
    color: var(--text-light);
    margin-bottom: 24px;
}

.title-decoration {
    width: 60px;
    height: 4px;
    background: var(--gradient);
    margin: 0 auto;
    border-radius: 2px;
}

.feature-card-modern {
    position: relative;
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 32px 24px;
    height: 100%;
    border: 1px solid #e2e8f0;
    transition: var(--transition);
    overflow: hidden;
}

.feature-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
    border-color: transparent;
}

.feature-icon-wrapper {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
}

.feature-icon-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--gradient-light);
    border-radius: 20px;
    opacity: 0.1;
    transition: var(--transition);
}

.feature-card-modern:hover .feature-icon-bg {
    opacity: 0.2;
    transform: scale(1.1);
}

.feature-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    color: var(--primary);
    z-index: 2;
}

.feature-step {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 32px;
    height: 32px;
    background: var(--gradient);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
}

.feature-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 12px;
    text-align: center;
}

.feature-description {
    color: var(--text-light);
    margin-bottom: 20px;
    text-align: center;
    font-size: 0.95rem;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.feature-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    color: var(--text);
    font-size: 0.9rem;
}

.feature-list i {
    color: var(--success);
    font-size: 0.875rem;
}

.feature-hover-effect {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.feature-card-modern:hover .feature-hover-effect {
    transform: scaleX(1);
}

/* =======================================================
   SECTION DASHBOARD PREVIEW
======================================================= */
.dashboard-showcase {
    padding: 100px 0;
    background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
}

.dashboard-tabs {
    background: var(--white);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.tab-buttons {
    display: flex;
    background: var(--light);
    padding: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.tab-button {
    flex: 1;
    padding: 16px;
    background: none;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    color: var(--text-light);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab-button.active {
    background: var(--white);
    color: var(--primary);
    box-shadow: var(--shadow);
}

.tab-button:hover:not(.active) {
    color: var(--primary);
}

.tab-content {
    padding: 32px;
}

.dashboard-preview-large {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    background: var(--gradient);
    color: white;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.avatar-large {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.25rem;
}

.today-info {
    text-align: right;
}

.date {
    font-size: 0.875rem;
    opacity: 0.9;
}

.current-time {
    font-size: 1.5rem;
    font-weight: 700;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    padding: 24px;
}

.stat-card-large {
    background: var(--light);
    border-radius: var(--radius);
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: var(--transition);
}

.stat-card-large:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.stat-icon-large {
    width: 60px;
    height: 60px;
    background: var(--gradient);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-value-large {
    font-size: 2rem;
    font-weight: 800;
    color: var(--dark);
}

.stat-label-large {
    color: var(--text-light);
    font-size: 0.875rem;
}

.upcoming-events {
    grid-column: 1 / -1;
    background: var(--light);
    border-radius: var(--radius);
    padding: 24px;
}

.upcoming-events h6 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 16px;
}

.event-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.event-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: white;
    border-radius: var(--radius);
    transition: var(--transition);
}

.event-item:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow);
}

.event-item i {
    color: var(--primary);
}

/* =======================================================
   SECTION AVANTAGES MODERNE
======================================================= */
.benefits-modern-section {
    padding: 100px 0;
    background: var(--white);
}

.benefits-visual {
    position: relative;
    height: 400px;
}

.floating-elements {
    position: relative;
    width: 100%;
    height: 100%;
}

.floating-element {
    position: absolute;
    width: 120px;
    height: 120px;
    background: var(--white);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    animation: float-element 3s infinite ease-in-out;
}

.floating-element i {
    font-size: 2rem;
}

.floating-element.speed {
    top: 20%;
    left: 10%;
    color: var(--accent);
    animation-delay: 0s;
}

.floating-element.security {
    top: 40%;
    right: 15%;
    color: var(--success);
    animation-delay: 1s;
}

.floating-element.automation {
    bottom: 20%;
    left: 30%;
    color: var(--primary);
    animation-delay: 2s;
}

@keyframes float-element {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.benefits-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 32px;
}

.benefit-item {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
    padding: 20px;
    background: var(--light);
    border-radius: var(--radius);
    transition: var(--transition);
}

.benefit-item:hover {
    transform: translateX(8px);
    background: var(--white);
    box-shadow: var(--shadow);
}

.benefit-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.benefit-item h4 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 8px;
}

.benefit-item p {
    color: var(--text-light);
    margin: 0;
}

/* =======================================================
   CTA FINALE
======================================================= */
.final-cta {
    padding: 100px 0;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    position: relative;
    overflow: hidden;
}

.cta-card {
    position: relative;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: var(--radius-lg);
    padding: 60px;
    text-align: center;
    box-shadow: var(--shadow-hover);
    max-width: 800px;
    margin: 0 auto;
}

.cta-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 16px;
}

.cta-subtitle {
    font-size: 1.125rem;
    color: var(--text-light);
    margin-bottom: 40px;
}

.cta-stats {
    display: flex;
    justify-content: center;
    gap: 48px;
    margin-bottom: 40px;
}

.cta-stat {
    text-align: center;
}

.cta-stat .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    background: var(--gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    line-height: 1;
}

.cta-stat .stat-label {
    font-size: 0.875rem;
    color: var(--text-light);
    margin-top: 8px;
}

.cta-buttons {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-bottom: 32px;
    flex-wrap: wrap;
}

.btn-cta-primary, .btn-cta-secondary {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 18px 36px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 1.125rem;
    text-decoration: none;
    transition: var(--transition);
    border: none;
    cursor: pointer;
}

.btn-cta-primary {
    background: var(--gradient);
    color: var(--white);
    box-shadow: var(--shadow);
}

.btn-cta-primary:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: var(--shadow-hover);
}

.btn-cta-secondary {
    background: var(--white);
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-cta-secondary:hover {
    background: var(--primary);
    color: var(--white);
    transform: translateY(-2px);
}

.cta-footer {
    color: var(--text-light);
    font-size: 0.875rem;
}

.cta-footer i {
    color: var(--success);
    margin-right: 8px;
}

/* =======================================================
   RESPONSIVE DESIGN AVANCÉ
======================================================= */
@media (max-width: 1200px) {
    .hero-title-modern {
        font-size: 3rem;
    }
    
    .section-title-modern, .benefits-title, .cta-title {
        font-size: 2.25rem;
    }
}

@media (max-width: 992px) {
    .hero-section-modern {
        padding: 60px 0;
        min-height: auto;
    }
    
    .hero-title-modern {
        font-size: 2.5rem;
    }
    
    .hero-visual {
        margin-top: 60px;
    }
    
    .floating-element {
        width: 100px;
        height: 100px;
        font-size: 0.875rem;
    }
    
    .floating-element i {
        font-size: 1.5rem;
    }
    
    .cta-card {
        padding: 40px 24px;
    }
}

@media (max-width: 768px) {
    .hero-title-modern {
        font-size: 2rem;
    }
    
    .hero-subtitle-modern {
        font-size: 1.125rem;
    }
    
    .hero-stats-modern {
        gap: 16px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .cta-buttons-modern, .cta-buttons {
        flex-direction: column;
    }
    
    .btn-modern, .btn-cta-primary, .btn-cta-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .dashboard-header {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .today-info {
        text-align: center;
    }
    
    .floating-element {
        width: 80px;
        height: 80px;
        font-size: 0.75rem;
    }
    
    .floating-element i {
        font-size: 1.25rem;
    }
    
    .benefit-item {
        flex-direction: column;
        text-align: center;
    }
    
    .benefit-icon {
        margin: 0 auto;
    }
}

@media (max-width: 576px) {
    .hero-title-modern {
        font-size: 1.75rem;
    }
    
    .section-title-modern, .benefits-title, .cta-title {
        font-size: 1.75rem;
    }
    
    .trust-badges {
        justify-content: center;
    }
    
    .tab-buttons {
        flex-direction: column;
    }
    
    .cta-stats {
        flex-direction: column;
        gap: 24px;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .live-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Animation d'entrée */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-content, .feature-card-modern, .benefit-item, .cta-card {
    animation: fadeInUp 0.6s ease-out;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}
</style>

<script>
// Animation interactive pour la page
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    // Observer les éléments à animer
    document.querySelectorAll('.feature-card-modern, .benefit-item, .stat-card-modern').forEach(el => {
        observer.observe(el);
    });

    // Animation du QR Code
    const qrDots = document.querySelectorAll('.qr-dots circle');
    qrDots.forEach((dot, index) => {
        dot.style.animationDelay = `${index * 0.1}s`;
    });

    // Simulation de scan réussi
    setInterval(() => {
        const scanning = document.querySelector('.scanning');
        const success = document.querySelector('.success');
        
        scanning.style.display = 'none';
        success.style.display = 'block';
        
        setTimeout(() => {
            scanning.style.display = 'block';
            success.style.display = 'none';
        }, 2000);
    }, 5000);

    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
        });
    });

    // Effet de parallaxe sur les formes
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const shapes = document.querySelectorAll('.shape');
        
        shapes.forEach((shape, index) => {
            const speed = 0.2 + (index * 0.1);
            const yPos = -(scrolled * speed);
            shape.style.transform = `translateY(${yPos}px) rotate(${scrolled * 0.1}deg)`;
        });
    });
});
</script>

<?php include 'src/views/partials/footer.php'; ?>
=======
<?php
require_once 'db.php'; // Assurez-vous que ce fichier est inclus pour la connexion à la base de données
require_once 'BadgeManager.php'; // Assurez-vous que BadgeManager est également inclus
session_start();

// Définir le fuseau horaire
date_default_timezone_set('Europe/Paris');

if (!isset($_SESSION['employe_id'])) {
    header("Location: login.php");
    exit();
}

/**
 * Gestion complète des badges avec sécurité renforcée
 */

// Initialisation
$employe_id = $_SESSION['employe_id'];

try {
    // Récupérer les informations de l'employé et du badge
    $stmt = $pdo->prepare("
        SELECT e.*, 
               b.token AS token, 
               b.expires_at AS badge_expiry
        FROM employes e
        LEFT JOIN badge_tokens b ON e.id = b.employe_id
        WHERE e.id = ?
        AND b.status = 'active'
        AND b.expires_at > NOW()
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$employe_id]);
    $employe = $stmt->fetch();

    // Vérifiez si l'employé a un badge actif
    $badge_actif = !empty($employe['token']) && strtotime($employe['expires_at']) > time();
    if (!$employe || empty($employe['token'])) {
        echo "Aucun badge actif trouvé.";
        exit();
    }

    // Vérifier si nous avons besoin d'un nouveau badge
    $needsNewBadge = false;
    if (strtotime($employe['badge_expiry']) < time()) {
        $needsNewBadge = true;
    }

    // Déterminer le prochain type de pointage
    $next_check_type = BadgeManager::getNextCheckinType($employe['last_check_type']);

    // Générer un nouveau badge si nécessaire
    if ($needsNewBadge) {
        // Utiliser la méthode de BadgeManager pour générer un nouveau badge
        $new_badge = BadgeManager::generateBadgeToken($employe_id, $pdo, $next_check_type);
        $employe['token'] = $new_badge['token_hash'];
        $employe['badge_expiry'] = $new_badge['expires_at'];
    }

    // Préparation des données pour la vue
    $departement = ucfirst(str_replace('depart_', '', $employe['departement']));
    $date_embauche = $employe['date_embauche'] ? date('d/m/Y', strtotime($employe['date_embauche'])) : 'Non disponible';
    $badge_created = $employe['badge_created'] ? date('d/m H:i', strtotime($employe['badge_created'])) : 'Nouveau';
    $employee_id_display = "XPERT+" . strtoupper(substr($employe['departement'], 0, 3)) . $employe['id'];

    // Formater la date d'expiration pour l'affichage
    $expiration_display = "Non disponible";
    if (!empty($employe['badge_expiry'])) {
        $expiration_display = date('d/m/Y à H:i', strtotime($employe['badge_expiry']));
    }

    // Récupérer le chemin de la photo de profil
    $profile_photo = 'assets/default-profile.jpg';
    if (!empty($employe['photo'])) {
        $profile_photo = htmlspecialchars($employe['photo']);
    }

} catch (Exception $e) {
    // Gestion des erreurs
    die("Erreur système : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge - <?= htmlspecialchars($employe['prenom'].' '.$employe['nom']) ?> | Xpert+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="badge_acces.css">
    <link rel="manifest" href="/manifest.json">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
          navigator.serviceWorker.register('/service-worker.js')
            .then(function(registration) {
              console.log('ServiceWorker enregistré avec succès:', registration.scope);
            }, function(err) {
              console.log('Erreur ServiceWorker:', err);
            });
        });
      }
    </script>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-id-badge"></i>
                <span>Xpert+</span>
            </div>
            <div class="user-info">
                <img src="<?= $profile_photo ?>" class="user-photo" alt="Photo de profil">
                <span><?= htmlspecialchars($employe['prenom']) ?></span>
            </div>
        </div>
    </header>
    
    <main class="badge-container">
        <div class="badge-card">
            <!-- En-tête du badge -->
            <div class="badge-header">
                <div class="employee-id"><?= htmlspecialchars($employee_id_display) ?></div>
                
                <div class="photo-container">
                    <img src="<?= $profile_photo ?>" class="employee-photo" alt="Photo de profil">
                </div>
                
                <h1 class="employee-name"><?= htmlspecialchars($employe['prenom'].' '.$employe['nom']) ?></h1>
                <p class="employee-position"><?= htmlspecialchars($employe['poste']) ?></p>
            </div>
            
            <!-- Informations employé -->
            <div class="badge-content">
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Département</div>
                        <div class="info-value"><?= htmlspecialchars($departement) ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Matricule</div>
                        <div class="info-value"><?= htmlspecialchars($employee_id_display) ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Embauché le</div>
                        <div class="info-value"><?= htmlspecialchars($date_embauche) ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Badge créé</div>
                        <div class="info-value"><?= htmlspecialchars($badge_created) ?></div>
                    </div>
                </div>
                
                <!-- Section QR Code -->
                <div class="qr-section">
                    <h3 class="qr-title">
                        <i class="fas fa-qrcode"></i>
                        Code de pointage
                    </h3>
                    
                    <div class="qr-container">
                        <div id="qrCode"></div>
                    </div>
                    
                    <div class="checkin-status <?= $next_check_type === 'arrivee' ? 'status-arrivee' : 'status-depart' ?>">
                        <i class="fas fa-<?= $next_check_type === 'arrivee' ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                        Prochain pointage : <?= $next_check_type === 'arrivee' ? 'ARRIVÉE' : 'DÉPART' ?>
                    </div>
                    
                    <div class="expiration-info">
                        <i class="fas fa-clock"></i>
                        Valide jusqu'au <strong id="expirationDate"><?= $expiration_display ?></strong>
                    </div>
                    
                    <div class="countdown">
                        <i class="fas fa-hourglass-half"></i>
                        Temps restant : <span id="timeRemaining"></span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="action-buttons">
                    <button onclick="window.print()" class="btn-badge btn-primary">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <a href="employe_dashboard.php" class="btn-badge btn-secondary">
                        <i class="fas fa-tachometer-alt"></i> Tableau de bord
                    </a>
                    <a href="scan_qr.php" class="btn-badge btn-info">
                        <i class="fas fa-camera"></i> Zone de pointage
                    </a>
                    <a href="logout.php" class="btn-badge btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <p>Système de gestion des badges - Xpert+ &copy; <?= date('Y') ?></p>
        <p>Votre session est sécurisée - Dernière connexion : <?= date('d/m/Y H:i') ?></p>
    </footer>
    
    <!-- Alerte de sécurité -->
    <div class="security-alert" id="securityAlert">
        <div class="security-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div>
            <strong>Sécurité</strong>
            <p>Votre badge est personnel. Ne le partagez pas.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        // Génération du QR Code
        document.addEventListener('DOMContentLoaded', function() {
            // QR Code
            new QRCode(document.getElementById("qrCode"), {
                text: "<?= htmlspecialchars($employe['token']) ?>",
                width: 220,
                height: 220,
                colorDark: "#2c3e50",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            // Compte à rebours
            function updateTimer() {
                const expiryTime = new Date("<?= $employe['badge_expiry'] ?>").getTime();
                const now = new Date().getTime();
                const distance = expiryTime - now;
                
                const timeElement = document.getElementById("timeRemaining");
                
                if (distance < 0) {
                    timeElement.innerHTML = "EXPIRÉ";
                    timeElement.className = "expiration-danger";
                    return;
                }
                
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Appliquer des classes en fonction du temps restant
                if (hours < 1) {
                    timeElement.className = "expiration-warning";
                } else {
                    timeElement.className = "";
                }
                
                timeElement.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
            }
            
            // Initialiser le timer
            updateTimer();
            setInterval(updateTimer, 1000);
            
            // Masquer l'alerte de sécurité après 8 secondes
            setTimeout(() => {
                document.getElementById("securityAlert").style.display = "none";
            }, 8000);
        });
    </script>
</body>
</html>
>>>>>>> 2fc47109b0d43eb3be3464bd2a12f9f4e8f82762
