/**
 * Badge Scanner - Système de pointage par QR Code
 * Version refactorisée avec architecture améliorée
 */

class BadgeScanner {
    constructor(options = {}) {
        // Configuration par défaut
        this.config = {
            apiEndpoint: options.apiEndpoint || '/api/scan.php',
            maxScanAttempts: options.maxScanAttempts || 5,
            scanDelay: options.scanDelay || 2000,
            autoRestart: options.autoRestart !== false,
            debug: options.debug || false,
            ...options
        };
        
        // État du scanner
        this.state = {
            isScanning: false,
            scanAttempts: 0,
            currentStream: null,
            activeCameraIndex: 0,
            availableCameras: [],
            lastScanTime: 0
        };
        
        // Éléments DOM
        this.elements = {};
        
        // Scanner QR
        this.codeReader = null;
        
        // Initialisation
        this.init();
    }
    
    /**
     * Initialisation du scanner
     */
    async init() {
        try {
            this.log('Initialisation du Badge Scanner...');
            
            // Vérification de la compatibilité
            await this.checkBrowserCompatibility();
            
            // Initialisation des éléments DOM
            this.initDOMElements();
            
            // Initialisation du scanner QR
            this.initQRScanner();
            
            // Ajout des event listeners
            this.attachEventListeners();
            
            // Affichage du prompt de démarrage
            this.showCameraStartPrompt();
            
            this.log('Scanner initialisé avec succès');
            
        } catch (error) {
            this.handleError('Erreur d\'initialisation', error);
        }
    }
    
    /**
     * Vérification de la compatibilité du navigateur
     */
    async checkBrowserCompatibility() {
        const checks = {
            mediaDevices: !!navigator.mediaDevices,
            getUserMedia: !!navigator.mediaDevices?.getUserMedia,
            videoElement: 'video' in document.createElement('video'),
            isSecure: this.isSecureContext(),
            webGL: this.hasWebGLSupport()
        };
        
        this.log('Vérifications de compatibilité:', checks);
        
        const failedChecks = Object.entries(checks)
            .filter(([key, value]) => !value)
            .map(([key]) => key);
        
        if (failedChecks.length > 0) {
            const message = this.getCompatibilityErrorMessage(failedChecks);
            throw new Error(`Navigateur incompatible: ${message}`);
        }
        
        return true;
    }
    
    /**
     * Vérifie si le contexte est sécurisé
     */
    isSecureContext() {
        return window.location.protocol === 'https:' || 
               window.location.hostname === 'localhost' ||
               window.location.hostname === '127.0.0.1' ||
               window.location.hostname.endsWith('.local');
    }
    
    /**
     * Vérifie le support WebGL
     */
    hasWebGLSupport() {
        try {
            const canvas = document.createElement('canvas');
            return !!(canvas.getContext('webgl', { willReadFrequently: true }) || canvas.getContext('experimental-webgl', { willReadFrequently: true }));
        } catch (e) {
            return false;
        }
    }
    
    /**
     * Initialisation des éléments DOM
     */
    initDOMElements() {
        const elementIds = [
            'video-element', 'status-message', 'history-list', 'status-list',
            'restart-btn', 'switch-camera-btn', 'camera-error', 'error-message',
            'retry-camera', 'late-minutes', 'late-employee-id', 'late-timestamp',
            'late-reason', 'other-reason-container', 'other-reason',
            'late-comment', 'submit-late'
        ];
        
        elementIds.forEach(id => {
            this.elements[this.toCamelCase(id)] = document.getElementById(id);
        });
        
        // Éléments par classe
        this.elements.scanContainer = document.querySelector('.scan-container');
        this.elements.cameraView = document.querySelector('.camera-view');
        
        // Modals Bootstrap
        if (typeof bootstrap !== 'undefined') {
            this.elements.lateModal = new bootstrap.Modal('#lateModal');
        }
        
        this.validateDOMElements();
    }
    
    /**
     * Validation que tous les éléments DOM nécessaires sont présents
     */
    validateDOMElements() {
        const required = ['videoElement', 'statusMessage', 'cameraView'];
        const missing = required.filter(key => !this.elements[key]);
        
        if (missing.length > 0) {
            throw new Error(`Éléments DOM manquants: ${missing.join(', ')}`);
        }
    }
    
    /**
     * Initialisation du scanner QR
     */
    initQRScanner() {
        if (typeof ZXing === 'undefined') {
            throw new Error('Bibliothèque ZXing non chargée');
        }
        
        this.codeReader = new ZXing.BrowserQRCodeReader();
        this.log('Scanner QR initialisé');
    }
    
    /**
     * Attache les event listeners
     */
    attachEventListeners() {
        // Boutons de contrôle
        this.elements.restartBtn?.addEventListener('click', () => this.restartScanner());
        this.elements.switchCameraBtn?.addEventListener('click', () => this.switchCamera());
        this.elements.retryCamera?.addEventListener('click', () => this.initCamera());
        
        // Modal de retard
        this.elements.lateReason?.addEventListener('change', () => this.toggleOtherReason());
        this.elements.submitLate?.addEventListener('click', () => this.submitLateReason());
        
        // Feedback visuel sur la zone de scan
        this.elements.cameraView?.addEventListener('click', () => {
            if (this.state.isScanning) {
                this.showScanFeedback();
            }
        });
        
        // Gestion des erreurs globales
        window.addEventListener('error', (event) => {
            this.log('Erreur globale capturée:', event.error);
        });
        
        // Cleanup lors du déchargement
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }
    
    /**
     * Affichage du prompt de démarrage de caméra
     */
    showCameraStartPrompt() {
        const promptDiv = document.createElement('div');
        promptDiv.className = 'camera-prompt text-center mb-4';
        promptDiv.innerHTML = `
            <div class="prompt-icon mb-3">
                <i class="fas fa-camera fa-4x text-primary"></i>
            </div>
            <h4 class="mb-3">Activation de la caméra</h4>
            <p class="text-muted mb-4">Pour scanner votre badge, nous avons besoin d'accéder à votre caméra.</p>
            <button id="start-camera-btn" class="btn btn-primary btn-lg">
                <i class="fas fa-camera me-2"></i> Activer la caméra
            </button>
            <div class="mt-3">
                <small class="text-muted">Vous pourrez désactiver l'accès ensuite dans les paramètres de votre navigateur.</small>
            </div>
        `;
        
        this.elements.scanContainer.insertBefore(promptDiv, this.elements.cameraView);
        
        document.getElementById('start-camera-btn').addEventListener('click', () => {
            promptDiv.remove();
            this.initCamera();
        });
    }
    
    /**
     * Initialisation de la caméra
     */
    async initCamera() {
        try {
            this.showStatus('Initialisation de la caméra...', 'processing');
            this.hideCameraError();
            
            // Arrêt du flux précédent
            this.stopCamera();
            
            // Détection des caméras disponibles
            await this.detectAvailableCameras();
            
            // Configuration des contraintes
            const constraints = this.getCameraConstraints();
            this.log('Contraintes caméra:', constraints);
            
            // Demande d'accès à la caméra
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.state.currentStream = stream;
            this.elements.videoElement.srcObject = stream;
            
            // Attente du chargement de la vidéo
            await this.waitForVideoLoad();
            
            // Démarrage du scan
            this.startScanning();
            
        } catch (error) {
            this.handleCameraError(error);
        }
    }
    
    /**
     * Détection des caméras disponibles
     */
    async detectAvailableCameras() {
        try {
            this.state.availableCameras = await this.codeReader.listVideoInputDevices();
            this.log(`${this.state.availableCameras.length} caméra(s) détectée(s)`);
            
            // Activation du bouton de changement de caméra
            if (this.elements.switchCameraBtn) {
                this.elements.switchCameraBtn.disabled = this.state.availableCameras.length < 2;
            }
        } catch (error) {
            this.log('Impossible de lister les caméras:', error);
            this.state.availableCameras = [];
        }
    }
    
    /**
     * Configuration des contraintes de caméra
     */
    getCameraConstraints() {
        const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
        const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        
        let constraints = {
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'environment'
            }
        };
        
        // Adaptations pour mobile
        if (isMobile) {
            constraints.video.width = { ideal: 1920 };
            constraints.video.height = { ideal: 1080 };
        }
        
        // Adaptations pour Safari
        if (isSafari) {
            constraints.video.frameRate = { ideal: 30 };
        }
        
        // Caméra spécifique si disponible
        if (this.state.availableCameras.length > 0) {
            const deviceId = this.state.availableCameras[this.state.activeCameraIndex].deviceId;
            constraints.video.deviceId = { exact: deviceId };
        }
        
        return constraints;
    }
    
    /**
     * Attente du chargement de la vidéo
     */
    waitForVideoLoad() {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                reject(new Error('Timeout lors du chargement de la vidéo'));
            }, 10000);
            
            this.elements.videoElement.onloadedmetadata = () => {
                clearTimeout(timeout);
                resolve();
            };
            
            this.elements.videoElement.play().catch(reject);
        });
    }
    
    /**
     * Démarrage du scan
     */
    async startScanning() {
        try {
            this.state.isScanning = true;
            this.state.scanAttempts = 0;
            
            this.showStatus('Prêt à scanner - présentez votre badge', 'waiting');
            this.elements.cameraView.classList.add('scan-active');
            
            this.codeReader.decodeFromVideoElement(
                this.elements.videoElement,
                (result, error) => this.handleScanResult(result, error)
            );
            
        } catch (error) {
            this.handleError('Erreur lors du démarrage du scan', error);
        }
    }
    
    /**
     * Gestion des résultats de scan
     */
    handleScanResult(result, error) {
        if (result && this.state.isScanning) {
            const now = Date.now();
            if (now - this.state.lastScanTime < 1000) {
                return; // Éviter les scans trop rapprochés
            }
            
            this.state.lastScanTime = now;
            this.handleSuccessfulScan(result.text);
        } else if (error && !error.message.includes('NotFoundException')) {
            this.state.scanAttempts++;
            
            if (this.state.scanAttempts >= this.config.maxScanAttempts) {
                this.showStatus('Difficultés de scan - rapprochez le badge', 'warning');
            }
        }
    }
    
    /**
     * Traitement d'un scan réussi
     */
    async handleSuccessfulScan(badgeData) {
        if (!this.state.isScanning) return;
        
        this.state.isScanning = false;
        this.elements.cameraView.classList.remove('scan-active');
        
        // Feedback visuel immédiat
        this.showScanFeedback();
        this.showStatus('Traitement du badge...', 'processing');
        
        // Ajout à l'historique
        this.addHistoryEntry(badgeData);
        
        try {
            // Envoi vers l'API
            const response = await this.sendBadgeToAPI(badgeData);
            
            if (response.status === 'success') {
                this.handleScanSuccess(response);
            } else {
                throw new Error(response.message || 'Erreur inconnue');
            }
            
        } catch (error) {
            this.handleScanError(error, badgeData);
        }
    }
    
    /**
     * Envoi des données vers l'API
     */
    async sendBadgeToAPI(badgeData) {
        const response = await fetch(this.config.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ badge_data: badgeData })
        });
        
        if (!response.ok) {
            throw new Error(`Erreur HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }
    
    /**
     * Gestion d'un scan réussi
     */
    handleScanSuccess(data) {
        this.showScanResult(data);
        this.addStatusEntry({
            status: 'success',
            type: data.type,
            message: data.message,
            timestamp: data.timestamp
        });
        
        // Gestion du retard
        if (data.is_late && this.elements.lateModal) {
            this.showLateModal(data);
        } else if (this.config.autoRestart) {
            setTimeout(() => this.restartScanner(), this.config.scanDelay);
        }
    }
    
    /**
     * Utilitaires
     */
    
    toCamelCase(str) {
        return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
    }
    
    log(...args) {
        if (this.config.debug) {
            console.log('[BadgeScanner]', ...args);
        }
    }
    
    showStatus(message, type = 'info') {
        if (this.elements.statusMessage) {
            this.elements.statusMessage.innerHTML = message;
            this.elements.statusMessage.className = `status-message status-${type}`;
        }
    }
    
    hideCameraError() {
        this.elements.cameraError?.classList.add('d-none');
    }
    
    stopCamera() {
        if (this.state.currentStream) {
            this.state.currentStream.getTracks().forEach(track => track.stop());
            this.state.currentStream = null;
        }
    }
    
    cleanup() {
        this.stopCamera();
        if (this.codeReader) {
            this.codeReader.reset();
        }
    }
    
    // ... Autres méthodes (handleError, showScanFeedback, etc.)
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    if (navigator.mediaDevices && window.MediaStreamTrack) {
        window.badgeScanner = new BadgeScanner({
            debug: localStorage.getItem('scanner-debug') === 'true'
        });
    } else {
        console.error('Scanner non supporté par ce navigateur');
    }
});

