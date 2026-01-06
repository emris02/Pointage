// ==
// JavaScript principal de l'application
// Pointage Xpert Pro
// ==

const App = {
    // Configuration
    config: {
        alertAutoHideDuration: 5000,
        fadeOutDuration: 1000,
        qrCodeSize: '200x200'
    },
    
    // Initialisation
    init: function() {
        console.log('Application initialisation...');
        this.setupAutoHideAlerts();
        this.setupBadgeGeneration();
        this.setupDynamicPanels();
        this.setupFormValidations();
        this.setupResponsiveElements();
    },
    
    // Configuration des alertes auto-masquables
    setupAutoHideAlerts: function() {
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert.alert-auto-hide');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
                    this.fadeOut(alert, this.config.alertAutoHideDuration);
                }
            });
        }, 1000);
    },
    
    // Génération de badge self-service
    setupBadgeGeneration: function() {
        const generateBtn = document.getElementById('demanderBadgeBtn');
        if (!generateBtn) {
            console.debug('Bouton génération badge non trouvé');
            return;
        }
        
        generateBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Confirmation
            if (!confirm('Êtes-vous sûr de vouloir générer un nouveau badge ? L\'ancien badge sera désactivé.')) {
                return;
            }
            
            const loader = document.getElementById('badge-loader');
            const qrImg = document.querySelector('.badge-qr');
            const badgeStatus = document.querySelector('.badge-status');
            
            try {
                // Afficher le loader
                if (loader) loader.style.display = 'block';
                if (badgeStatus) {
                    badgeStatus.textContent = 'Génération en cours...';
                    badgeStatus.className = 'badge-status text-warning';
                }
                
                // Désactiver le bouton pendant la requête
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
                
                // Appel API
                const response = await fetch('api/regenerate_badge_self.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data && data.success) {
                    // Mettre à jour le QR code
                    if (qrImg && data.data && data.data.token) {
                        const token = encodeURIComponent(data.data.token);
                        const timestamp = new Date().getTime(); // Pour éviter le cache
                        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=${this.config.qrCodeSize}&data=${token}&t=${timestamp}`;
                        
                        // Ajouter un événement pour gérer les erreurs de chargement du QR
                        qrImg.onload = () => {
                            if (badgeStatus) {
                                badgeStatus.textContent = 'Badge généré avec succès';
                                badgeStatus.className = 'badge-status text-success';
                            }
                        };
                        
                        qrImg.onerror = () => {
                            console.error('Erreur de chargement du QR code');
                            if (badgeStatus) {
                                badgeStatus.textContent = 'QR code généré, erreur d\'affichage';
                                badgeStatus.className = 'badge-status text-warning';
                            }
                        };
                    }
                    
                    // Afficher un toast de succès
                    this.showToast('Nouveau badge généré avec succès', 'success');
                    
                } else {
                    throw new Error(data.message || 'Erreur lors de la génération du badge');
                }
                
            } catch (error) {
                console.error('Erreur génération badge:', error);
                this.showToast(`Erreur: ${error.message}`, 'error');
                
                if (badgeStatus) {
                    badgeStatus.textContent = 'Erreur lors de la génération';
                    badgeStatus.className = 'badge-status text-danger';
                }
                
            } finally {
                // Réinitialiser le bouton et le loader
                if (loader) loader.style.display = 'none';
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="fas fa-qrcode"></i> Générer un nouveau badge';
                
                // Recharger la page après 3 secondes pour voir les changements
                setTimeout(() => {
                    location.reload();
                }, 3000);
            }
        });
    },
    
    // Configuration des panneaux dynamiques (onglets)
    setupDynamicPanels: function() {
        const panelButtons = document.querySelectorAll('[data-panel-target]');
        panelButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = button.getAttribute('data-panel-target');
                this.switchPanel(targetId);
            });
        });
    },
    
    // Validation des formulaires
    setupFormValidations: function() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, false);
        });
    },
    
    // Éléments responsives
    setupResponsiveElements: function() {
        // Ajuster la hauteur des textarea
        const autoResizeTextareas = document.querySelectorAll('textarea[data-auto-resize]');
        autoResizeTextareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
        
        // Gestion du menu mobile
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mainNav = document.getElementById('main-nav');
        
        if (mobileMenuToggle && mainNav) {
            mobileMenuToggle.addEventListener('click', () => {
                mainNav.classList.toggle('show');
            });
        }
    },
    
    // Changement de panneau
    switchPanel: function(panelId) {
        // Cacher tous les panneaux
        document.querySelectorAll('.app-panel').forEach(panel => {
            panel.classList.remove('active');
            panel.style.display = 'none';
        });
        
        // Désactiver tous les boutons
        document.querySelectorAll('[data-panel-target]').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Afficher le panneau cible
        const targetPanel = document.getElementById(panelId);
        const targetButton = document.querySelector(`[data-panel-target="${panelId}"]`);
        
        if (targetPanel) {
            targetPanel.style.display = 'block';
            targetPanel.classList.add('active');
            
            // Déclencher un événement personnalisé
            const event = new CustomEvent('panel:shown', {
                detail: { panelId: panelId }
            });
            window.dispatchEvent(event);
        }
        
        if (targetButton) {
            targetButton.classList.add('active');
        }
        
        console.log(`Panneau activé: ${panelId}`);
    },
    
    // Validation de formulaire
    validateForm: function(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            field.classList.remove('is-invalid');
            
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
                
                // Ajouter un message d'erreur
                const errorDiv = field.nextElementSibling;
                if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'invalid-feedback';
                    errorMsg.textContent = field.getAttribute('data-error-message') || 'Ce champ est obligatoire';
                    field.parentNode.appendChild(errorMsg);
                }
            }
        });
        
        return isValid;
    },
    
    // Fonction pour faire disparaître un élément
    fadeOut: function(element, duration = 1000) {
        if (!element) return;
        
        element.style.transition = `opacity ${duration}ms ease-out`;
        element.style.opacity = '0';
        
        setTimeout(() => {
            element.style.display = 'none';
            element.remove();
        }, duration);
    },
    
    // Afficher une notification toast
    showToast: function(message, type = 'info') {
        // Vérifier si Bootstrap Toast est disponible
        if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
            console.log(`${type.toUpperCase()}: ${message}`);
            return;
        }
        
        // Créer un toast dynamique
        const toastId = `toast-${Date.now()}`;
        const icon = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ'
        }[type] || 'ℹ';
        
        const bgClass = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-info text-white'
        }[type] || 'bg-info text-white';
        
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center">
                        <span class="me-2 fs-5">${icon}</span>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        // Créer ou récupérer le container de toasts
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1060';
            document.body.appendChild(container);
        }
        
        container.insertAdjacentHTML('beforeend', toastHtml);
        
        const toastEl = document.getElementById(toastId);
        if (!toastEl) return;
        
        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 3000
        });
        
        toast.show();
        
        // Nettoyer après la disparition
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    },
    
    // Fonction utilitaire pour formater la date
    formatDate: function(date, format = 'fr-FR') {
        if (!date) return '';
        
        const d = new Date(date);
        if (isNaN(d.getTime())) return '';
        
        return d.toLocaleDateString(format, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    // Fonction pour copier du texte dans le presse-papier
    copyToClipboard: function(text, showNotification = true) {
        if (!navigator.clipboard) {
            // Fallback pour les anciens navigateurs
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        } else {
            navigator.clipboard.writeText(text);
        }
        
        if (showNotification) {
            this.showToast('Copié dans le presse-papier', 'success');
        }
    }
};

// ==
// Initialisation au chargement du DOM
// ==
document.addEventListener('DOMContentLoaded', function() {
    try {
        App.init();
        console.log('Application Pointage Xpert Pro initialisée avec succès');
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de l\'application:', error);
    }
});

// ==
// Gestionnaires d'événements globaux
// ==

// Recharger le calendrier quand la fenêtre est redimensionnée
window.addEventListener('resize', function() {
    const event = new CustomEvent('window:resized');
    window.dispatchEvent(event);
});

// Gestion des erreurs non capturées
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript non capturée:', e.error);
    
    // Afficher un message à l'utilisateur en production
    if (window.location.hostname !== 'localhost') {
        App.showToast('Une erreur technique est survenue. Veuillez rafraîchir la page.', 'error');
    }
});

// ==
// Exposer certaines fonctions globalement
// ==
window.App = App;

// Fonction pour charger dynamiquement un panneau
window.loadPanel = function(panelId) {
    App.switchPanel(panelId);
};

// Fonction pour afficher un toast globalement
window.showToast = function(message, type) {
    App.showToast(message, type);
};