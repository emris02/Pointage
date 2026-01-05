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
    'assets/css/styles.css',
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