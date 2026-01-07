    </main>
    
    <!-- Footer moderne -->
    <footer class="site-footer">
        <div class="footer-wave">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" 
                      opacity=".25" fill="currentColor"></path>
                <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" 
                      opacity=".5" fill="currentColor"></path>
                <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" 
                      fill="currentColor"></path>
            </svg>
        </div>
        
        <div class="container">
            <div class="footer-content">
                <!-- Section principale -->
                <div class="footer-main">
                    <div class="footer-brand">
                        <div class="footer-logo">
                            <i class="fas fa-clock"></i>
                            <span class="brand-text">Xpert Pro</span>
                        </div>
                        <p class="footer-description">
                            Solution innovante de pointage par QR Code pour les entreprises modernes.
                            Simplifiez la gestion des présences avec notre système intelligent.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" aria-label="LinkedIn">
                                <i class="fab fa-linkedin"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Facebook">
                                <i class="fab fa-facebook"></i>
                            </a>
                            <a href="#" class="social-link" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Liens rapides -->
                    <div class="footer-links">
                        <h5 class="footer-title">Liens rapides</h5>
                        <ul>
                            <li><a href="index.php">Accueil</a></li>
                            <li><a href="index.php#features">Fonctionnalités</a></li>
                            <li><a href="index.php#avantages">Avantages</a></li>
                            <li><a href="index.php#about">À propos</a></li>
                            <li><a href="pricing.php">Tarifs</a></li>
                        </ul>
                    </div>
                    
                    <!-- Ressources -->
                    <div class="footer-links">
                        <h5 class="footer-title">Ressources</h5>
                        <ul>
                            <li><a href="documentation.php">Documentation</a></li>
                            <li><a href="faq.php">FAQ</a></li>
                            <li><a href="tutoriels.php">Tutoriels</a></li>
                            <li><a href="blog.php">Blog</a></li>
                            <li><a href="changelog.php">Mises à jour</a></li>
                        </ul>
                    </div>
                    
                    <!-- Contact & Légal -->
                    <div class="footer-links">
                        <h5 class="footer-title">Légal & Contact</h5>
                        <ul>
                            <li><a href="mentions-legales.php">Mentions légales</a></li>
                            <li><a href="confidentialite.php">Politique de confidentialité</a></li>
                            <li><a href="cgu.php">CGU</a></li>
                            <li><a href="cookies.php">Cookies</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Newsletter -->
                <div class="newsletter-section">
                    <h5 class="newsletter-title">Restez informé</h5>
                    <p class="newsletter-text">
                        Inscrivez-vous pour recevoir nos dernières actualités et conseils.
                    </p>
                    <form class="newsletter-form">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Votre email" required>
                            <button class="btn-newsletter" type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <small class="newsletter-disclaimer">
                            En vous inscrivant, vous acceptez notre politique de confidentialité.
                        </small>
                    </form>
                </div>
                
                <!-- Copyright et info -->
                <div class="footer-bottom">
                    <div class="copyright">
                        <p>&copy; <?= date('Y') ?> Xpert Pro. Tous droits réservés.</p>
                        <p class="tech-info">
                            <i class="fas fa-server"></i> 
                            Hébergé en France • 
                            <i class="fas fa-shield-alt"></i> 
                            RGPD compliant • 
                            <i class="fas fa-heart text-danger"></i> 
                            Made with love
                        </p>
                    </div>
                    
                    <div class="footer-payments">
                        <span class="payment-icon">💳</span>
                        <span class="payment-icon">🔒</span>
                        <span class="payment-icon">📱</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bouton retour en haut -->
    <button class="back-to-top" aria-label="Retour en haut">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Modal de cookie -->
    <div class="cookie-consent" id="cookieConsent">
        <div class="cookie-content">
            <p>
                🍪 Nous utilisons des cookies pour améliorer votre expérience. 
                En continuant, vous acceptez notre 
                <a href="cookies.php">politique de cookies</a>.
            </p>
            <div class="cookie-buttons">
                <button class="btn-cookie accept">Accepter</button>
                <button class="btn-cookie decline">Refuser</button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- FontAwesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?= htmlspecialchars($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Inline JS -->
    <?php if (isset($inlineJS)): ?>
        <script>
            <?= $inlineJS ?>
        </script>
    <?php endif; ?>

    <!-- Footer scripts -->
    <script>
    // Back to top button
    const backToTopBtn = document.querySelector('.back-to-top');
    
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTopBtn.classList.add('visible');
        } else {
            backToTopBtn.classList.remove('visible');
        }
    });
    
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Cookie consent
    const cookieConsent = document.getElementById('cookieConsent');
    const acceptCookies = document.querySelector('.btn-cookie.accept');
    const declineCookies = document.querySelector('.btn-cookie.decline');
    
    if (!localStorage.getItem('cookiesAccepted')) {
        setTimeout(() => {
            cookieConsent.classList.add('visible');
        }, 1000);
    }
    
    acceptCookies.addEventListener('click', () => {
        localStorage.setItem('cookiesAccepted', 'true');
        cookieConsent.classList.remove('visible');
    });
    
    declineCookies.addEventListener('click', () => {
        localStorage.setItem('cookiesAccepted', 'false');
        cookieConsent.classList.remove('visible');
    });

    // Newsletter form
    const newsletterForm = document.querySelector('.newsletter-form');
    newsletterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = newsletterForm.querySelector('input[type="email"]').value;
        // Ici, vous ajouterez la logique d'envoi
        alert(`Merci pour votre inscription avec l'email : ${email}`);
        newsletterForm.reset();
    });
    </script>
</body>
</html>

<style>
/* =======================================================
   FOOTER MODERNE
======================================================= */
.site-footer {
    background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
    color: #fff;
    position: relative;
    margin-top: 5rem;
    padding: 4rem 0 2rem;
}

.footer-wave {
    position: absolute;
    top: -60px;
    left: 0;
    width: 100%;
    height: 60px;
    color: #1a252f;
    transform: rotate(180deg);
}

.footer-wave svg {
    width: 100%;
    height: 100%;
    display: block;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.footer-content {
    position: relative;
    z-index: 1;
}

.footer-main {
    display: grid;
    grid-template-columns: 1fr;
    gap: 3rem;
    margin-bottom: 3rem;
}

@media (min-width: 768px) {
    .footer-main {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 992px) {
    .footer-main {
        grid-template-columns: 2fr 1fr 1fr 1fr;
    }
}

.footer-brand {
    max-width: 400px;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
}

.footer-logo i {
    color: #0672e4;
    font-size: 1.8rem;
}

.brand-text {
    background: linear-gradient(142deg, #0672e4, #e6bb18);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.footer-description {
    color: #b0b7c3;
    line-height: 1.6;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.social-links {
    display: flex;
    gap: 1rem;
}

.social-link {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: #0672e4;
    transform: translateY(-3px);
    color: white;
}

.footer-links h5 {
    color: #fff;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 0.5rem;
}

.footer-links h5::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 2px;
    background: #0672e4;
}

.footer-links ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 0.75rem;
}

.footer-links a {
    color: #b0b7c3;
    text-decoration: none;
    transition: color 0.3s ease;
    font-size: 0.95rem;
    display: inline-block;
}

.footer-links a:hover {
    color: #0672e4;
    transform: translateX(5px);
}

.newsletter-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    padding: 0.5rem;
    margin: 2rem 0 3rem;
}

.newsletter-title {
    color: #fff;
    margin-bottom: 1rem;
}

.newsletter-text {
    color: #b0b7c3;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.newsletter-form {
    max-width: 500px;
}

.input-group {
    display: flex;
    margin-bottom: 0.5rem;
}

.form-control {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 5px 0 0 5px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-size: 1rem;
}

.form-control::placeholder {
    color: #b0b7c3;
}

.form-control:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.15);
}

.btn-newsletter {
    background: #0672e4;
    color: white;
    border: none;
    padding: 0 1.5rem;
    border-radius: 0 5px 5px 0;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-newsletter:hover {
    background: #3a56d4;
}

.newsletter-disclaimer {
    color: #8a94a6;
    font-size: 0.85rem;
    display: block;
    margin-top: 0.5rem;
}

.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    text-align: center;
}

@media (min-width: 768px) {
    .footer-bottom {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        text-align: left;
    }
}

.copyright p {
    color: #b0b7c3;
    margin: 0.25rem 0;
    font-size: 0.9rem;
}

.tech-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 0.5rem;
}

.tech-info i {
    margin-right: 0.5rem;
}

.footer-payments {
    display: flex;
    gap: 1rem;
    font-size: 1.5rem;
}

.payment-icon {
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.payment-icon:hover {
    opacity: 1;
}

/* =======================================================
   BOUTON RETOUR EN HAUT
======================================================= */
.back-to-top {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #0672e4;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
}

.back-to-top.visible {
    opacity: 1;
    transform: translateY(0);
}

.back-to-top:hover {
    background: #3a56d4;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
}

/* =======================================================
   COOKIE CONSENT
======================================================= */
.cookie-consent {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: white;
    border-radius: 10px;
    padding: 0.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    max-width: 500px;
    width: 90%;
    z-index: 9999;
    transition: transform 0.5s ease;
}

.cookie-consent.visible {
    transform: translateX(-50%) translateY(0);
}

.cookie-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.cookie-content p {
    color: #333;
    margin: 0;
    font-size: 0.95rem;
}

.cookie-content a {
    color: #0672e4;
    text-decoration: none;
}

.cookie-content a:hover {
    text-decoration: underline;
}

.cookie-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-cookie {
    padding: 0.5rem 1.5rem;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-cookie.accept {
    background: #0672e4;
    color: white;
}

.btn-cookie.accept:hover {
    background: #3a56d4;
}

.btn-cookie.decline {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #dee2e6;
}

.btn-cookie.decline:hover {
    background: #e9ecef;
}

/* =======================================================
   RESPONSIVE
======================================================= */
@media (max-width: 768px) {
    .site-footer {
        padding: 3rem 0 1.5rem;
    }
    
    .footer-main {
        gap: 2rem;
    }
    
    .footer-logo {
        font-size: 1.3rem;
    }
    
    .newsletter-section {
        padding: 0.5rem;
    }
    
    .cookie-consent {
        width: 95%;
        left: 2.5%;
        transform: translateY(100px);
    }
    
    .cookie-consent.visible {
        transform: translateY(0);
    }
    
    .cookie-buttons {
        flex-direction: column;
    }
    
    .btn-cookie {
        width: 100%;
    }
    
    .back-to-top {
        bottom: 1rem;
        right: 1rem;
        width: 45px;
        height: 45px;
    }
}

@media (max-width: 480px) {
    .footer-main {
        grid-template-columns: 1fr;
    }
    
    .tech-info {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }
}
</style>