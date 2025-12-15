// Initialisation des composants
document.addEventListener('DOMContentLoaded', function() {
  // Gestion du thème
  initThemeSwitcher();
  
  // Gestion de l'avatar
  initAvatarEditor();
  
  // Gestion du formulaire
  initSettingsForm();
  
  // Gestion des accordéons FAQ
  initFAQAccordion();
  
  // Gestion des nouveaux paramètres
  initPasswordStrength();
  initSessionTimeout();
  initTwoFactorAuth();
  initDataExport();
});

/**
 * Initialise le sélecteur de thème
 */
function initThemeSwitcher() {
  const themeRadios = document.querySelectorAll('input[name="theme"]');
  
  themeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      // Apply immediately to body (preferred) and persist via AJAX
      const applied = (this.value === 'auto') ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'sombre' : 'clair') : this.value;
      document.body.setAttribute('data-theme', applied);
      // Send to server to persist in DB + session
      fetch('/pointage/update_theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: this.value })
      }).then(r => r.json()).then(resp => {
        if (resp && resp.success) {
          showToast('Thème appliqué et enregistré');
        } else {
          showToast('Erreur lors de l\'enregistrement du thème', 'error');
        }
      }).catch(err => {
        console.error(err);
        showToast('Erreur réseau lors de l\'enregistrement', 'error');
      });
    });
  });
  
  // Appliquer le thème sauvegardé
  // Initialize theme from server-applied body attribute
  const current = document.body.getAttribute('data-theme') || document.documentElement.getAttribute('data-theme') || 'clair';
  if (current === 'auto') {
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.body.setAttribute('data-theme', prefersDark ? 'sombre' : 'clair');
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        document.body.setAttribute('data-theme', e.matches ? 'sombre' : 'clair');
      });
    }
  }
  const radio = document.querySelector(`input[value="${current}"]`);
  if (radio) radio.checked = true;
}

/**
 * Initialise l'éditeur d'avatar
 */
function initAvatarEditor() {
  const editBtn = document.querySelector('.avatar-edit-btn');
  
  if (editBtn) {
    editBtn.addEventListener('click', function(e) {
      e.preventDefault();
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      
      input.onchange = function() {
        if (this.files && this.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            document.querySelector('.avatar').src = e.target.result;
            showToast('Avatar mis à jour');
          };
          reader.readAsDataURL(this.files[0]);
        }
      };
      
      input.click();
    });
  }
}

/**
 * Initialise le formulaire de paramètres
 */
function initSettingsForm() {
  const form = document.getElementById('settings-form');
  
  if (form) {
    // Soumission via AJAX pour un meilleur UX
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(form);
      const settings = {};
      formData.forEach((value, key) => { settings[key] = value; });

      // Mettre à jour localStorage pour effet instantané
      localStorage.setItem('admin_settings', JSON.stringify(settings));

      // Envoi au serveur
      fetch(location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      }).then(r => r.json())
        .then(data => {
          if (data.success) {
            showToast('Préférences enregistrées avec succès');
          } else {
            showToast('Erreur lors de la sauvegarde', 'error');
          }
        }).catch(err => {
          console.error(err);
          showToast('Erreur réseau lors de la sauvegarde', 'error');
        });
    });
    
    // Charger les préférences existantes
    const savedSettings = JSON.parse(localStorage.getItem('admin_settings') || '{}');
    Object.keys(savedSettings).forEach(key => {
      const element = form.querySelector(`[name="${key}"]`);
      if (element) {
        if (element.type === 'checkbox' || element.type === 'radio') {
          element.checked = savedSettings[key] === 'on' || savedSettings[key] === element.value;
        } else {
          element.value = savedSettings[key];
        }
      }
    });

    // Apply recommended preset (client-side preview then submit)
    const recommendBtn = document.getElementById('apply-recommended');
    if (recommendBtn) {
      recommendBtn.addEventListener('click', function(e) {
        e.preventDefault();
        // Fill form with recommended values (matching server preset)
        const preset = {
          theme: 'clair', notifications: '1', notif_mail: '0', animations: '1', table_compact: '0',
          sidebar_mini: '0', dashboard_graph: '1', dashboard_cards: '1', dashboard_welcome: '1',
          font_size: '100', selected_font: 'system-ui', contrast: '0', session_timeout: '30',
          two_factor: '0', language: 'fr', timezone: 'Europe/Paris', action: 'recommander'
        };
        Object.keys(preset).forEach(k => {
          const el = form.querySelector(`[name="${k}"]`);
          if (!el) return;
          if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = (preset[k] === '1' || el.value === preset[k]);
          } else {
            el.value = preset[k];
          }
        });
        // submit
        form.querySelector('[name="action"]').value = 'recommander';
        form.requestSubmit();
      });
    }
  }
}

/**
 * Initialise l'indicateur de force du mot de passe
 */
function initPasswordStrength() {
  const passwordInput = document.getElementById('password');
  const strengthBar = document.getElementById('password-strength');
  
  if (passwordInput && strengthBar) {
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      
      if (password.length > 5) strength += 25;
      if (password.length > 8) strength += 25;
      if (/[A-Z]/.test(password)) strength += 25;
      if (/[0-9]/.test(password)) strength += 25;
      if (/[^A-Za-z0-9]/.test(password)) strength += 25;
      
      // Mettre à jour la barre de force
      strengthBar.className = 'password-strength';
      
      if (strength < 50) {
        strengthBar.classList.add('strength-weak');
      } else if (strength < 75) {
        strengthBar.classList.add('strength-medium');
      } else if (strength < 100) {
        strengthBar.classList.add('strength-good');
      } else {
        strengthBar.classList.add('strength-strong');
      }
    });
  }
}

/**
 * Initialise le slider de délai de session
 */
function initSessionTimeout() {
  const slider = document.getElementById('session-timeout');
  const valueDisplay = document.getElementById('session-timeout-value');
  
  if (slider && valueDisplay) {
    // Mettre à jour l'affichage de la valeur
    slider.addEventListener('input', function() {
      valueDisplay.textContent = this.value + ' minutes';
    });
    
    // Initialiser avec la valeur sauvegardée
    const savedSettings = JSON.parse(localStorage.getItem('admin_settings') || '{}');
    if (savedSettings.session_timeout) {
      slider.value = savedSettings.session_timeout;
      valueDisplay.textContent = savedSettings.session_timeout + ' minutes';
    }
  }
}

/**
 * Initialise l'authentification à deux facteurs
 */
function initTwoFactorAuth() {
  const toggle2FA = document.getElementById('two-factor-toggle');
  const qrCodeContainer = document.getElementById('2fa-qrcode');
  
  if (toggle2FA) {
    toggle2FA.addEventListener('change', function() {
      if (this.checked) {
        // Simuler la génération d'un QR Code
        qrCodeContainer.innerHTML = '<div class="text-center"><p>Scanner avec votre application d\'authentification</p><div class="bg-light p-3 d-inline-block">[QR Code Simulation]</div></div>';
        showToast('Authentification à deux facteurs activée');
      } else {
        qrCodeContainer.innerHTML = '';
        showToast('Authentification à deux facteurs désactivée');
      }
    });
  }
}

/**
 * Initialise l'export des données
 */
function initDataExport() {
  const exportBtn = document.getElementById('export-data-btn');
  
  if (exportBtn) {
    exportBtn.addEventListener('click', function() {
      // Simuler l'export de données
      showToast('Export des données en cours...');
      
      setTimeout(() => {
        showToast('Export terminé. Votre fichier est prêt.');
        
        // Simuler le téléchargement
        const link = document.createElement('a');
        link.href = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify({
          message: "Ceci est un export simulé de vos paramètres"
        }));
        link.download = 'parametres-export-' + new Date().toISOString().slice(0, 10) + '.json';
        link.click();
      }, 1500);
    });
  }
}

/**
 * Initialise les accordéons FAQ
 */
function initFAQAccordion() {
  const accordions = document.querySelectorAll('.accordion-button');
  
  accordions.forEach(btn => {
    btn.addEventListener('click', function() {
      this.classList.toggle('active');
    });
  });
}

/**
 * Affiche un toast de notification
 * @param {string} message 
 */
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = 'toast-notification';
  
  // Déterminer la couleur en fonction du type
  const bgColor = type === 'success' ? '#4CAF50' : 
                 type === 'error' ? '#F44336' : 
                 type === 'warning' ? '#FF9800' : '#2196F3';
  
  toast.innerHTML = `
    <div class="toast-content" style="background: ${bgColor}">
      <i class="fas ${type === 'success' ? 'fa-check-circle' : 
                  type === 'error' ? 'fa-exclamation-circle' : 
                  type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'} me-2"></i>
      ${message}
    </div>
  `;
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.classList.add('show');
  }, 10);
  
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => {
      document.body.removeChild(toast);
    }, 300);
  }, 3000);
}

// Ajout dynamique des styles pour les toasts
const toastStyles = document.createElement('style');
toastStyles.textContent = `
.toast-notification {
  position: fixed;
  bottom: 20px;
  right: 20px;
  color: white;
  padding: 0;
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-md);
  transform: translateY(100px);
  opacity: 0;
  transition: all 0.4s ease;
  z-index: 1000;
  max-width: 350px;
}

.toast-notification.show {
  transform: translateY(0);
  opacity: 1;
}

.toast-content {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  border-radius: var(--radius-sm);
}
`;

// Gestion de l'accessibilité
function initAccessibilitySettings() {
  // Taille de la police
  const fontSizeSlider = document.getElementById('font-size-slider');
  const fontSizeValue = document.getElementById('font-size-value');
  const fontSizePreview = document.getElementById('font-size-preview');
  
  if (fontSizeSlider) {
    fontSizeSlider.addEventListener('input', function() {
      const size = this.value;
      fontSizeValue.textContent = `${size}%`;
      fontSizePreview.style.fontSize = `${size}%`;
      document.documentElement.style.fontSize = `${size}%`;
      localStorage.setItem('font_size', size);
    });
    
    // Charger la taille sauvegardée
    const savedSize = localStorage.getItem('font_size') || 100;
    fontSizeSlider.value = savedSize;
    fontSizeValue.textContent = `${savedSize}%`;
    fontSizePreview.style.fontSize = `${savedSize}%`;
    document.documentElement.style.fontSize = `${savedSize}%`;
  }
  
  // Sélection de police
  const fontCards = document.querySelectorAll('.font-preview-card');
  const selectedFontInput = document.getElementById('selected-font');
  
  fontCards.forEach(card => {
    card.addEventListener('click', function() {
      const font = this.dataset.font;
      
      // Désélectionner toutes les cartes
      fontCards.forEach(c => c.classList.remove('selected'));
      
      // Sélectionner la carte actuelle
      this.classList.add('selected');
      
      // Appliquer la police
      document.documentElement.style.fontFamily = font;
      selectedFontInput.value = font;
      localStorage.setItem('selected_font', font);
      
      showToast(`Police appliquée: ${this.querySelector('.card-title').textContent}`);
    });
    
    // Sélectionner la police sauvegardée
    const savedFont = localStorage.getItem('selected_font');
    if (savedFont && card.dataset.font === savedFont) {
      card.classList.add('selected');
      document.documentElement.style.fontFamily = savedFont;
      selectedFontInput.value = savedFont;
    }
  });
  
  // Mode contraste élevé
  const contrastToggle = document.getElementById('contrast');
  if (contrastToggle) {
    contrastToggle.addEventListener('change', function() {
      if (this.checked) {
        document.documentElement.classList.add('high-contrast');
      } else {
        document.documentElement.classList.remove('high-contrast');
      }
      localStorage.setItem('high_contrast', this.checked);
    });
    
    // Charger l'état sauvegardé
    const savedContrast = localStorage.getItem('high_contrast') === 'true';
    contrastToggle.checked = savedContrast;
    if (savedContrast) document.documentElement.classList.add('high-contrast');
  }
  
  // Réduction des animations
  const reduceMotion = document.getElementById('reduce-motion');
  if (reduceMotion) {
    reduceMotion.addEventListener('change', function() {
      if (this.checked) {
        document.documentElement.classList.add('reduce-motion');
      } else {
        document.documentElement.classList.remove('reduce-motion');
      }
      localStorage.setItem('reduce_motion', this.checked);
    });
    
    // Charger l'état sauvegardé
    const savedMotion = localStorage.getItem('reduce_motion') === 'true';
    reduceMotion.checked = savedMotion;
    if (savedMotion) document.documentElement.classList.add('reduce-motion');
  }
  
  // Curseur agrandi
  const cursorSize = document.getElementById('cursor-size');
  if (cursorSize) {
    cursorSize.addEventListener('change', function() {
      if (this.checked) {
        document.documentElement.classList.add('large-cursor');
      } else {
        document.documentElement.classList.remove('large-cursor');
      }
      localStorage.setItem('large_cursor', this.checked);
    });
    
    // Charger l'état sauvegardé
    const savedCursor = localStorage.getItem('large_cursor') === 'true';
    cursorSize.checked = savedCursor;
    if (savedCursor) document.documentElement.classList.add('large-cursor');
  }
  
  // Réinitialisation des paramètres
  const resetBtn = document.getElementById('reset-accessibility');
  if (resetBtn) {
    resetBtn.addEventListener('click', function() {
      // Réinitialiser les valeurs
      if (fontSizeSlider) {
        fontSizeSlider.value = 100;
        fontSizeValue.textContent = '100%';
        fontSizePreview.style.fontSize = '100%';
        document.documentElement.style.fontSize = '';
        localStorage.removeItem('font_size');
      }
      
      if (fontCards.length > 0) {
        fontCards.forEach(c => c.classList.remove('selected'));
        document.documentElement.style.fontFamily = '';
        selectedFontInput.value = '';
        localStorage.removeItem('selected_font');
      }
      
      const toggles = [contrastToggle, reduceMotion, cursorSize];
      toggles.forEach(toggle => {
        if (toggle) {
          toggle.checked = false;
          document.documentElement.classList.remove(
            'high-contrast', 'reduce-motion', 'large-cursor'
          );
          localStorage.removeItem(toggle.id);
        }
      });
      
      showToast('Paramètres d\'accessibilité réinitialisés');
    });
  }
}

// Ajouter cette fonction à l'initialisation
document.addEventListener('DOMContentLoaded', function() {
  // ... autres initialisations
  initAccessibilitySettings();
});
document.head.appendChild(toastStyles);