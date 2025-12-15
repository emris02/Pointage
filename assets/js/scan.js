// scan.js - Version améliorée et corrigée
import QrScanner from './qr-scanner.min.js';

class AdvancedBadgeScanner {
    constructor() {
        // Initialisation des éléments DOM
        this.video = document.getElementById('qr-video');
        this.scanStatus = document.getElementById('scan-status');
        this.feedbackMessage = document.getElementById('feedback-message');
        this.historique = document.getElementById('historique');
        this.journal = document.getElementById('journal');
        this.historyCount = document.getElementById('history-count');
        this.restartBtn = document.getElementById('restart-scan');
        this.switchCameraBtn = document.getElementById('switch-camera');
        this.toggleFlashBtn = document.getElementById('toggle-flash');
        this.dateFilter = document.getElementById('date-filter');
        this.refreshHistoryBtn = document.getElementById('refresh-history');
        
        // Variables d'état
        this.scanner = null;
        this.isProcessing = false;
        this.lastScan = '';
        this.currentFacingMode = 'environment';
        this.scanCooldown = 2000;
        this.cameras = [];
        this.currentCameraIndex = 0;
        this.scanAttempts = 0;
        this.maxScanAttempts = 5;
        this.flashEnabled = false;
        
        // Configuration des endpoints API - Version corrigée pour la page publique
        const appBase = window.location.pathname.split('/').slice(0, -1).join('/') || '';
        this.apiEndpoints = {
            scan: appBase + '/api/scan_qr.php', // API publique pour le scan
            getPointagesJour: appBase + '/api/get_pointages.php', // API publique pour les pointages
            // Le système utilise actuellement un handler côté page employe_dashboard.php
            // Nous pointons vers ce fichier pour soumettre la justification via AJAX.
            justifyDelay: appBase + '/employe_dashboard.php'
        };
        
        console.log('Endpoints API configurés:', this.apiEndpoints);
        
        // Configuration du scanner
        this.scannerConfig = {
            highlightScanRegion: true,
            highlightCodeOutline: true,
            maxScansPerSecond: 3,
            preferredCamera: this.currentFacingMode,
            returnDetailedScanResult: true,
            onDecodeError: this.handleDecodeError.bind(this),
            canvas: {
                willReadFrequently: true // Pour éviter le warning Canvas2D
            }
        };
        
        this.init();
    }
    
    async init() {
        try {
            this.showFeedback('Initialisation du système de scan...', 'info');
            
            // Vérifier si nous sommes dans un environnement sécurisé
            if (!this.checkSecurityEnvironment()) {
                // Afficher un avertissement visible et arrêter l'initialisation
                // pour éviter les warnings navigateur et l'accès caméra sur HTTP
                this.showSecurityWarning();
                throw new Error('Accès caméra désactivé: page non sécurisée (HTTPS requis)');
            }
            
            // Initialisations
            await this.initializeScanner();
            await this.loadPointagesJour();
            
            // Bind events et setup
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setupPerformanceMonitoring();
            
            // Démarrer la surveillance réseau
            this.setupNetworkMonitoring();
            
            this.showFeedback('Système de scan prêt', 'success');
            console.log('AdvancedBadgeScanner initialisé avec succès');
            
        } catch (error) {
            console.error('Erreur d\'initialisation:', error);
            this.showFeedback(`Erreur lors du démarrage: ${error.message}`, 'error');
            this.handleFatalError(error);
        }
    }
    
    checkSecurityEnvironment() {
        // Vérifier si on est en HTTPS ou localhost
        const isSecure = window.location.protocol === 'https:' || 
                        window.location.hostname === 'localhost' || 
                        window.location.hostname === '127.0.0.1';
        
        if (!isSecure) {
            console.warn('Page non sécurisée - certaines fonctionnalités caméra peuvent être limitées');
            return false;
        }
        
        return true;
    }
    
    showSecurityWarning() {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning alert-dismissible fade show mt-3';
        warning.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attention :</strong> Pour un meilleur fonctionnement de la caméra, utilisez HTTPS.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.scan-container');
        if (container) {
            container.insertBefore(warning, container.firstChild);
        }
    }
    
    async initializeScanner() {
        this.updateStatus('waiting', 'Initialisation de la caméra...');
        
        try {
            // Vérifications préalables
            await this.validateEnvironment();
            
            // Chargement des caméras disponibles
            await this.loadAvailableCameras();
            
            // Mise à jour de l'interface avec les caméras disponibles
            this.updateCameraUI();
            
            // Configuration adaptative
            this.adaptScannerConfig();
            
            // Initialisation du scanner QR
            this.scanner = new QrScanner(
                this.video,
                (result) => this.handleScan(result),
                this.scannerConfig
            );
            
            // Démarrage du scanner
            await this.scanner.start();
            
            this.updateStatus('ready', 'Scanner activé - Approchez votre badge');
            this.showFeedback('La caméra est prête. Positionnez le QR code dans le cadre.', 'info');
            
            // Activer les boutons de contrôle
            this.enableControls(true);
            
            // Démarrage du monitoring de performance
            this.startPerformanceMonitoring();
            
        } catch (error) {
            this.handleCameraError(error);
            throw error;
        }
    }
    
    async validateEnvironment() {
        // Vérification des APIs nécessaires
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('API caméra non supportée par votre navigateur');
        }
        
        if (!window.fetch) {
            throw new Error('Votre navigateur est trop ancien. Mettez-le à jour.');
        }
        
        // Vérifier la permission de la caméra
        const hasCameraPermission = await this.checkCameraPermission();
        if (!hasCameraPermission) {
            throw new Error('Permission caméra refusée ou non accordée');
        }
        
        return true;
    }
    
    async checkCameraPermission() {
        try {
            // Tenter d'accéder à la caméra pour vérifier la permission
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            console.warn('Erreur permission caméra:', error);
            return false;
        }
    }
    
    updateCameraUI() {
        // Mettre à jour l'affichage des infos caméra
        const cameraNameElement = document.getElementById('camera-name');
        const cameraStatusElement = document.getElementById('camera-status');
        
        if (cameraNameElement && this.cameras.length > 0) {
            const camera = this.cameras[this.currentCameraIndex];
            cameraNameElement.textContent = this.getCameraLabel(camera);
        }
        
        if (cameraStatusElement) {
            cameraStatusElement.innerHTML = `
                <i class="fas fa-circle fa-xs ${this.isProcessing ? 'text-warning' : 'text-success'}"></i>
                ${this.isProcessing ? 'En traitement' : 'Actif'}
            `;
        }
    }
    
    getCameraLabel(camera) {
        const label = camera.label.toLowerCase();
        if (label.includes('front') || label.includes('face')) return 'Caméra avant';
        if (label.includes('back') || label.includes('rear')) return 'Caméra arrière';
        if (label.includes('external')) return 'Caméra externe';
        if (label.includes('webcam')) return 'Webcam intégrée';
        return 'Caméra principale';
    }
    
    adaptScannerConfig() {
        // Adaptation basée sur le nombre de caméras
        if (this.cameras.length === 1) {
            this.scannerConfig.preferredCamera = this.cameras[0].id;
        }
        
        // Adaptation pour les mobiles
        if (this.isMobileDevice()) {
            this.scannerConfig.maxScansPerSecond = 2;
            this.scanCooldown = 2500;
        }
        
        // Détection des performances
        if (this.isLowPerformanceDevice()) {
            this.scannerConfig.maxScansPerSecond = 1;
            this.scannerConfig.highlightCodeOutline = false;
        }
    }
    
    isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    isLowPerformanceDevice() {
        const memory = navigator.deviceMemory;
        const cores = navigator.hardwareConcurrency;
        return memory < 4 || cores < 4;
    }
    
    async loadAvailableCameras() {
        try {
            this.cameras = await QrScanner.listCameras(true);
            console.log(`${this.cameras.length} caméra(s) détectée(s):`, this.cameras);
            
            if (this.cameras.length === 0) {
                throw new Error('Aucune caméra détectée sur cet appareil');
            }
            
            // Trier les caméras par type (environnement d'abord)
            this.cameras.sort((a, b) => {
                const aLabel = a.label.toLowerCase();
                const bLabel = b.label.toLowerCase();
                if (aLabel.includes('back') || aLabel.includes('rear')) return -1;
                if (bLabel.includes('back') || bLabel.includes('rear')) return 1;
                return 0;
            });
            
        } catch (error) {
            console.warn('Impossible de lister les caméras:', error);
            this.cameras = [];
            throw new Error('Accès aux caméras indisponible');
        }
    }
    
    enableControls(enabled) {
        const controls = ['restart-scan', 'switch-camera', 'toggle-flash'];
        controls.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = !enabled;
                btn.classList.toggle('disabled', !enabled);
            }
        });
    }
    
    async handleScan(result) {
        // Gestion du cooldown et anti-spam
        if (this.isProcessing) {
            console.log('Scan en cours, ignore...');
            return;
        }
        
        if (result.data === this.lastScan) {
            console.log('Même code QR, ignore...');
            return;
        }
        
        this.isProcessing = true;
        this.lastScan = result.data;
        this.scanAttempts++;
        
        this.updateStatus('processing', 'Analyse du badge...');
        this.showFeedback('Badge détecté, traitement en cours...', 'info');
        this.updateCameraUI();
        
        try {
            console.log('QR Code détecté:', result.data);
            
            const rawToken = (result.data || '').trim();
            
            // Parsing des données du badge
            let badgeData = this.parseBadgeData(rawToken);
            
            if (!badgeData) {
                badgeData = { 
                    employe_id: '', 
                    token: '', 
                    nom: 'Employé', 
                    prenom: 'Utilisateur', 
                    departement: '', 
                    raw_token: rawToken 
                };
            } else {
                badgeData.raw_token = badgeData.raw_token || rawToken;
            }
            
            this.updateStatus('success', 'Badge détecté');
            
            // Traitement du badge
            await this.processBadge(badgeData);
            
            // Réinitialisation du compteur après un succès
            this.scanAttempts = 0;
            
        } catch (error) {
            console.error('Erreur traitement badge:', error);
            await this.handleScanError(error);
        } finally {
            // Réactivation progressive
            const cooldown = this.calculateAdaptiveCooldown();
            setTimeout(() => {
                this.isProcessing = false;
                this.updateStatus('ready', 'Prêt pour le scan suivant');
                this.updateCameraUI();
            }, cooldown);
        }
    }
    
    calculateAdaptiveCooldown() {
        const baseCooldown = this.scanCooldown;
        const multiplier = Math.min(this.scanAttempts, 5);
        return baseCooldown * (1 + (multiplier * 0.5));
    }
    
    parseBadgeData(qrData) {
        if (!qrData || typeof qrData !== 'string') {
            throw new Error('Données QR code invalides');
        }
        
        qrData = qrData.trim().replace(/[\r\n\t]/g, '');
        console.log('Parsing des données:', qrData.substring(0, 100) + '...');
        
        try {
            // Essayer différents formats
            
            // Format avec séparateur "|" (le plus courant)
            if (qrData.includes('|')) {
                const parts = qrData.split('|').map(part => part.trim());
                console.log(`Format "|" détecté avec ${parts.length} parties:`, parts);
                
                // Différentes configurations possibles
                if (parts.length >= 5) {
                    // Format: id|token|timestamp|type|hash
                    return {
                        employe_id: parts[0],
                        token: parts[1],
                        timestamp: parts[2],
                        type: parts[3],
                        hash: parts[4],
                        nom: parts.length > 5 ? parts[5] : 'Employé',
                        prenom: parts.length > 6 ? parts[6] : 'Utilisateur',
                        departement: parts.length > 7 ? parts[7] : '',
                        raw_token: qrData
                    };
                } else if (parts.length >= 4) {
                    // Format: id|token|nom|prenom
                    return {
                        employe_id: parts[0],
                        token: parts[1],
                        nom: parts[2],
                        prenom: parts[3],
                        departement: parts.length > 4 ? parts[4] : '',
                        raw_token: qrData
                    };
                } else if (parts.length >= 2) {
                    // Format minimal: id|token
                    return {
                        employe_id: parts[0],
                        token: parts[1],
                        nom: 'Employé',
                        prenom: 'Utilisateur',
                        departement: '',
                        raw_token: qrData
                    };
                }
            }
            
            // Format URL
            if (qrData.includes('?') || qrData.includes('=')) {
                try {
                    const urlObj = new URL(qrData.includes('://') ? qrData : `http://dummy.com?${qrData}`);
                    const params = new URLSearchParams(urlObj.search);
                    
                    const data = {
                        employe_id: params.get('id') || params.get('employe_id') || params.get('employee_id'),
                        token: params.get('token') || params.get('hash') || params.get('code'),
                        nom: params.get('nom') || params.get('name') || params.get('lastname'),
                        prenom: params.get('prenom') || params.get('firstname') || params.get('first_name'),
                        departement: params.get('departement') || params.get('dept') || params.get('department'),
                        raw_token: qrData
                    };
                    
                    if (data.employe_id && data.token) {
                        console.log('Format URL détecté');
                        return data;
                    }
                } catch (e) {
                    // Continuer avec d'autres formats
                }
            }
            
            // Format JSON
            if ((qrData.startsWith('{') && qrData.endsWith('}')) || 
                (qrData.startsWith('[') && qrData.endsWith(']'))) {
                try {
                    const jsonData = JSON.parse(qrData);
                    const data = {
                        employe_id: jsonData.id || jsonData.employe_id || jsonData.employee_id,
                        token: jsonData.token || jsonData.hash || jsonData.code,
                        nom: jsonData.nom || jsonData.name || jsonData.lastname || 'Employé',
                        prenom: jsonData.prenom || jsonData.firstname || jsonData.first_name || 'Utilisateur',
                        departement: jsonData.departement || jsonData.dept || jsonData.department || '',
                        raw_token: qrData
                    };
                    
                    if (data.employe_id && data.token) {
                        console.log('Format JSON détecté');
                        return data;
                    }
                } catch (e) {
                    // Continuer avec d'autres formats
                }
            }
            
            // Dernière tentative : extraction basique
            console.log('Format non reconnu, tentative d\'extraction basique');
            
            // Essayer de trouver un ID numérique au début
            const idMatch = qrData.match(/^(\d+)/);
            if (idMatch) {
                return {
                    employe_id: idMatch[1],
                    token: qrData.substring(idMatch[1].length).trim(),
                    nom: 'Employé',
                    prenom: 'Utilisateur',
                    departement: '',
                    raw_token: qrData
                };
            }
            
            throw new Error('Format de badge non supporté');
            
        } catch (error) {
            console.error('Erreur parsing badge:', error);
            throw new Error(`Format de token invalide: ${error.message}`);
        }
    }
    
    async processBadge(badgeData) {
        console.log('Traitement du badge:', badgeData);
        
        if (!badgeData || !badgeData.employe_id || !badgeData.token) {
            throw new Error('Données de badge incomplètes');
        }
        
        const sessionId = this.generateSessionId();
        const payload = {
            badge_data: badgeData.raw_token,
            scan_time: new Date().toISOString(),
            type: this.determinePointageType(),
            device_info: this.getDeviceInfo()
        };
        
        console.log('Payload envoyé:', payload);
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000); // 15s timeout
            
            const response = await fetch(this.apiEndpoints.scan, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Request-ID': sessionId,
                    'X-Client-Version': '2.0.0'
                },
                body: JSON.stringify(payload),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            // Vérifier le type de contenu
            const contentType = response.headers.get('content-type') || '';
            const text = await response.text();
            let result;
            
            // Debug logging
            console.log('Status:', response.status);
            console.log('Content-Type:', contentType);
            console.log('Response text (first 500 chars):', text.substring(0, 500));
            
            try {
                if (contentType.includes('application/json')) {
                    result = JSON.parse(text);
                } else if (text.trim().startsWith('<')) {
                    // C'est du HTML (erreur PHP probable)
                    console.error('Réponse HTML reçue:', text.substring(0, 500));
                    
                    // Essayer d'extraire un message d'erreur du HTML
                    const errorMatch = text.match(/<b>(.*?)<\/b>/i) || 
                                     text.match(/Parse error.*?line.*?(\d+)/i) ||
                                     text.match(/error.*?<\/font>/i);
                    
                    const errorMsg = errorMatch ? 
                        `Erreur serveur: ${errorMatch[0].substring(0, 100)}` : 
                        'Erreur serveur (format HTML)';
                    
                    throw new Error(errorMsg);
                } else {
                    // Tentative de parsing JSON même sans content-type
                    try {
                        result = JSON.parse(text);
                    } catch (parseError) {
                        throw new Error(`Réponse invalide: ${text.substring(0, 100)}`);
                    }
                }
            } catch (parseError) {
                console.error('Erreur parsing réponse:', parseError);
                throw new Error(`Réponse serveur invalide: ${parseError.message}`);
            }
            
            if (!response.ok) {
                const errorMsg = result?.message || 
                               result?.error || 
                               `Erreur HTTP ${response.status}`;
                throw new Error(errorMsg);
            }
            
            if (!result.success) {
                throw new Error(result.message || 'Échec du pointage');
            }
            
            await this.handleScanSuccess(result, badgeData);
            
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Timeout: La requête a pris trop de temps');
            }
            throw error;
        }
    }
    
    generateSessionId() {
        return `scan_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }
    
    getDeviceInfo() {
        return {
            userAgent: navigator.userAgent.substring(0, 200),
            platform: navigator.platform,
            language: navigator.language,
            screen: `${screen.width}x${screen.height}`,
            viewport: `${window.innerWidth}x${window.innerHeight}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
    }
    
    determinePointageType() {
        const now = new Date();
        const hour = now.getHours();
        
        // Logique simplifiée pour déterminer le type
        if (hour < 12) {
            return 'arrivee';
        } else if (hour >= 17) {
            return 'depart';
        } else {
            return hour < 14 ? 'pause_debut' : 'pause_fin';
        }
    }
    
    async handleScanSuccess(result, badgeData) {
        // Animation et feedback immédiat
        this.animateSuccess();
        
        // Afficher le message de succès
        const successMessage = result.message || 
                             `Pointage réussi pour ${badgeData.prenom} ${badgeData.nom}`;
        
        this.showFeedback(successMessage, 'success');
        
        // Mise à jour de l'interface utilisateur
        this.displayScanResult(result.data || result);
        
        // Ajouter à l'historique local
        this.addToLocalHistory({
            prenom: badgeData.prenom,
            nom: badgeData.nom,
            type: result.data?.type || result.type || 'arrivee',
            heure: new Date().toLocaleTimeString('fr-FR'),
            date: new Date().toLocaleDateString('fr-FR'),
            statut: 'success',
            details: result.details || ''
        });
        
        // Recharger les pointages du jour
        await this.loadPointagesJour();
        
        // Gestion des retards
        if (result.retard === true || result.is_late) {
            setTimeout(() => this.showJustificationModal(badgeData, result, true), 2000);
        }
        
        // Envoyer une notification
        this.sendNotification('Nouveau pointage', successMessage);
    }
    
    displayScanResult(data) {
        // Créer un overlay temporaire pour afficher le résultat
        const resultOverlay = document.createElement('div');
        resultOverlay.className = 'scan-result-overlay';
        resultOverlay.innerHTML = `
            <div class="scan-result-card">
                <div class="result-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="result-content">
                    <h5>Pointage réussi</h5>
                    ${data.nom ? `<p><strong>${data.prenom} ${data.nom}</strong></p>` : ''}
                    ${data.type ? `<p>Type: <span class="badge bg-primary">${data.type}</span></p>` : ''}
                    ${data.heure ? `<p>Heure: ${data.heure}</p>` : ''}
                    ${data.retard_minutes ? `<p class="text-warning">Retard: ${data.retard_minutes} min</p>` : ''}
                </div>
                <button class="btn-close-result">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(resultOverlay);
        
        // Animation d'entrée
        setTimeout(() => {
            resultOverlay.classList.add('show');
        }, 10);
        
        // Fermeture après 5 secondes ou sur clic
        const closeBtn = resultOverlay.querySelector('.btn-close-result');
        closeBtn.addEventListener('click', () => {
            resultOverlay.classList.remove('show');
            setTimeout(() => {
                if (resultOverlay.parentNode) {
                    resultOverlay.parentNode.removeChild(resultOverlay);
                }
            }, 300);
        });
        
        setTimeout(() => {
            if (resultOverlay.parentNode) {
                closeBtn.click();
            }
        }, 5000);
    }
    
    addToLocalHistory(event) {
        const historyList = document.querySelector('#pointages-container .pointage-list');
        if (!historyList) return;
        
        const now = new Date();
        const timeString = now.toLocaleTimeString('fr-FR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const eventHtml = `
            <div class="pointage-item p-3 border-bottom animate__animated animate__fadeIn">
                <div class="d-flex align-items-center">
                    <div class="pointage-icon bg-success me-3">
                        <i class="fas fa-${event.type === 'arrivee' ? 'sign-in-alt' : 'sign-out-alt'}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong class="text-success">${event.type === 'arrivee' ? 'Arrivée' : 'Départ'}</strong>
                                <span class="text-muted ms-2">à ${timeString}</span>
                                <span class="ms-2">• ${event.prenom} ${event.nom}</span>
                            </div>
                            <div class="text-end">
                                <small class="text-success"><i class="fas fa-check-circle"></i> En ligne</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Ajouter en haut de la liste
        historyList.insertAdjacentHTML('afterbegin', eventHtml);
        
        // Mettre à jour le compteur
        this.updateHistoryCount();
        
        // Limiter à 20 éléments
        const items = historyList.querySelectorAll('.pointage-item');
        if (items.length > 20) {
            items[items.length - 1].remove();
        }
    }
    
    updateHistoryCount() {
        const historyList = document.querySelector('#pointages-container .pointage-list');
        if (historyList && this.historyCount) {
            const count = historyList.querySelectorAll('.pointage-item').length;
            this.historyCount.textContent = count;
            this.historyCount.classList.add('animate__bounce');
            setTimeout(() => {
                this.historyCount.classList.remove('animate__bounce');
            }, 1000);
        }
    }
    
    async handleScanError(error) {
        console.error('Erreur de scan:', error);
        
        const errorMessage = error.message || 'Erreur lors du scan';
        
        // Feedback utilisateur
        this.showFeedback(errorMessage, 'error');
        this.animateError();
        
        // Gestion spécifique des erreurs réseau
        if (errorMessage.includes('réseau') || errorMessage.includes('timeout') || errorMessage.includes('Network')) {
            this.showFeedback('Problème de connexion. Vérifiez votre réseau.', 'warning');
            this.enableOfflineMode();
        }
        
        // Limite de tentatives
        if (this.scanAttempts >= this.maxScanAttempts) {
            this.showFeedback('Trop de tentatives échouées. Redémarrage du scanner...', 'warning');
            setTimeout(() => this.restartScanner(), 3000);
        }
        
        // Enregistrement pour debug
        this.recordFailedScan(error);
    }
    
    enableOfflineMode() {
        // Stocker les scans en local pour envoi ultérieur
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            console.log('Mode hors ligne activé - les scans seront synchronisés plus tard');
        }
    }
    
    handleDecodeError(error) {
        console.debug('Erreur décodage QR:', error);
    }
    
    recordFailedScan(error) {
        // Enregistrement local des erreurs
        const errors = JSON.parse(localStorage.getItem('scan_errors') || '[]');
        errors.push({
            timestamp: new Date().toISOString(),
            message: error.message,
            data: this.lastScan,
            userAgent: navigator.userAgent
        });
        
        // Garder seulement les 50 dernières erreurs
        if (errors.length > 50) {
            errors.shift();
        }
        
        localStorage.setItem('scan_errors', JSON.stringify(errors));
    }
    
    handleCameraError(error) {
        console.error('Erreur caméra:', error);
        
        let message = 'Erreur de caméra';
        let suggestion = '';
        
        if (error.name === 'NotAllowedError') {
            message = 'Permission caméra refusée';
            suggestion = 'Veuillez autoriser l\'accès à la caméra dans les paramètres de votre navigateur.';
        } else if (error.name === 'NotFoundError') {
            message = 'Aucune caméra détectée';
            suggestion = 'Vérifiez qu\'une caméra est connectée et fonctionnelle.';
        } else if (error.name === 'NotSupportedError') {
            message = 'Navigateur non supporté';
            suggestion = 'Utilisez Chrome, Firefox ou Safari récents.';
        } else if (error.name === 'NotReadableError') {
            message = 'Caméra indisponible';
            suggestion = 'La caméra est peut-être utilisée par une autre application.';
        } else if (error.name === 'OverconstrainedError') {
            message = 'Configuration caméra non supportée';
            suggestion = 'Changement de caméra automatique...';
            setTimeout(() => this.attemptCameraRecovery(), 1000);
        }
        
        this.updateStatus('error', message);
        this.showFeedback(suggestion ? `${message}. ${suggestion}` : message, 'error');
        
        // Désactiver les contrôles
        this.enableControls(false);
    }
    
    async attemptCameraRecovery() {
        try {
            if (this.cameras.length > 1) {
                this.currentCameraIndex = (this.currentCameraIndex + 1) % this.cameras.length;
                await this.scanner.setCamera(this.cameras[this.currentCameraIndex].id);
                this.showFeedback('Caméra changée avec succès', 'success');
                this.updateCameraUI();
            }
        } catch (error) {
            console.error('Tentative de reprise échouée:', error);
        }
    }
    
    async switchCamera() {
        if (!this.scanner || this.cameras.length < 2) {
            this.showFeedback('Une seule caméra disponible', 'info');
            return;
        }
        
        try {
            this.currentCameraIndex = (this.currentCameraIndex + 1) % this.cameras.length;
            const camera = this.cameras[this.currentCameraIndex];
            
            await this.scanner.setCamera(camera.id);
            
            const cameraType = this.getCameraLabel(camera);
            this.showFeedback(`${cameraType} activée`, 'success');
            this.updateCameraUI();
            
            // Animation de transition
            this.animateCameraSwitch();
            
        } catch (error) {
            console.error('Erreur changement caméra:', error);
            this.showFeedback('Erreur lors du changement de caméra', 'error');
        }
    }
    
    async toggleFlash() {
        if (!this.scanner) return;
        
        try {
            this.flashEnabled = !this.flashEnabled;
            
            if (this.flashEnabled) {
                await this.scanner.turnFlashOn();
                this.toggleFlashBtn.innerHTML = '<i class="fas fa-lightbulb me-2"></i> Flash ON';
                this.toggleFlashBtn.classList.add('btn-warning');
                this.showFeedback('Flash activé', 'info');
            } else {
                await this.scanner.turnFlashOff();
                this.toggleFlashBtn.innerHTML = '<i class="fas fa-lightbulb me-2"></i> Flash';
                this.toggleFlashBtn.classList.remove('btn-warning');
                this.showFeedback('Flash désactivé', 'info');
            }
            
        } catch (error) {
            console.error('Erreur flash:', error);
            this.showFeedback('Flash non disponible', 'warning');
            this.flashEnabled = false;
            this.toggleFlashBtn.disabled = true;
        }
    }
    
    async restartScanner() {
        try {
            this.isProcessing = false;
            this.lastScan = '';
            this.scanAttempts = 0;
            
            this.updateStatus('waiting', 'Redémarrage du scanner...');
            this.showFeedback('Redémarrage en cours...', 'info');
            this.enableControls(false);
            
            if (this.scanner) {
                await this.scanner.stop();
                await this.scanner.destroy();
                this.scanner = null;
            }
            
            // Pause courte pour stabilisation
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await this.initializeScanner();
            this.showFeedback('Scanner redémarré avec succès', 'success');
            
        } catch (error) {
            this.handleCameraError(error);
        }
    }
    
    showJustificationModal(badgeData, scanResult, required = false) {
        const modalEl = document.getElementById('justificationModal');
        const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
        const form = document.getElementById('justificationForm');
        if (form) form.dataset.required = required ? 'true' : 'false';
        
        // Pré-remplir les champs
        document.getElementById('justificationEmployeId').value = badgeData.employe_id;
        document.getElementById('justificationPointageId').value = scanResult?.data?.pointage_id || scanResult.pointage_id || '';
        document.getElementById('justificationDate').value = new Date().toISOString().split('T')[0];
        const fullName = scanResult?.data?.employe?.full_name || `${badgeData.prenom} ${badgeData.nom}`;
        document.getElementById('justificationEmployeName').textContent = fullName;
        document.getElementById('justificationPointageTime').textContent = scanResult?.data?.heure || new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        
        // Réinitialiser le formulaire
        document.getElementById('justificationReason').value = '';
        document.getElementById('justificationDetails').value = '';
        document.getElementById('justificationFile').value = '';

        // Si la justification est obligatoire, masquer le bouton annuler et forcer la soumission normale
        const cancelBtn = document.getElementById('cancelJustificationBtn');
        const headerClose = modalEl.querySelector('.btn-close');
        const submitHidden = document.getElementById('submitJustificationHidden');
        if (required) {
            if (cancelBtn) cancelBtn.classList.add('d-none');
            if (headerClose) headerClose.classList.add('d-none');
            if (submitHidden) submitHidden.value = '1';
        } else {
            if (cancelBtn) cancelBtn.classList.remove('d-none');
            if (headerClose) headerClose.classList.remove('d-none');
            if (submitHidden) submitHidden.value = '';
        }

        modal.show();
        
        // Focus automatique
        setTimeout(() => {
            document.getElementById('justificationReason').focus();
        }, 500);
        // Remplir les informations supplémentaires si disponibles
        const emp = scanResult?.data?.employe || {};
        const addrEl = document.getElementById('justificationAddress');
        const deptEl = document.getElementById('justificationDepartment');
        const arrEl = document.getElementById('justificationArriveeTime');
        const depEl = document.getElementById('justificationDepartTime');
        const statusEl = document.getElementById('justificationStatus');

        if (addrEl) addrEl.textContent = emp.adresse || scanResult?.data?.adresse || badgeData.adresse || '-';
        if (deptEl) deptEl.textContent = emp.departement || scanResult?.data?.departement || badgeData.departement || '-';
        if (arrEl) arrEl.textContent = emp.arrivee_time || '-';
        if (depEl) depEl.textContent = emp.depart_time || '-';
        if (statusEl) {
+            const statusText = emp.status || (scanResult.is_late ? 'En retard' : 'À l\'heure');
+            statusEl.textContent = statusText;
+            statusEl.classList.remove('text-warning','text-success');
+            if ((scanResult.is_late || (emp && emp.status && emp.status.toLowerCase().includes('retard'))) ) {
+                statusEl.classList.add('text-warning');
+            } else {
+                statusEl.classList.add('text-success');
+            }
+        }
    }
    
    async submitJustification() {
        const reason = document.getElementById('justificationReason').value;
        const details = document.getElementById('justificationDetails').value;
        const file = document.getElementById('justificationFile').files[0];
        
        if (!reason) {
            this.showFeedback('Veuillez sélectionner une raison', 'error');
            this.shakeElement(document.getElementById('justificationReason'));
            return;
        }
        
        const formData = new FormData();
        formData.append('employe_id', document.getElementById('justificationEmployeId').value);
        formData.append('pointage_id', document.getElementById('justificationPointageId').value);
        formData.append('date', document.getElementById('justificationDate').value);
        formData.append('reason', reason);
        formData.append('details', details);
        if (file) {
            formData.append('attachment', file);
        }
        // Add submit flag expected by server-side handler
        formData.append('submit_justification', '1');
        
        try {
            this.showFeedback('Envoi de la justification...', 'info');
            
            const response = await fetch(this.apiEndpoints.justifyDelay, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showFeedback('Justification enregistrée avec succès', 'success');
                
                // Fermeture du modal
                bootstrap.Modal.getInstance(document.getElementById('justificationModal')).hide();
                
                // Rafraîchir les données
                await this.loadPointagesJour();
                
            } else {
                throw new Error(result.message || 'Erreur lors de la justification');
            }
            
        } catch (error) {
            this.showFeedback(`Erreur: ${error.message}`, 'error');
        }
    }
    
    updateStatus(type, message) {
        if (!this.scanStatus) return;
        
        const icons = {
            waiting: 'clock',
            ready: 'search',
            processing: 'spinner fa-spin',
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle'
        };
        
        this.scanStatus.className = `scan-status ${type}`;
        this.scanStatus.innerHTML = `
            <i class="fas fa-${icons[type] || 'search'} me-2"></i> 
            ${message}
        `;
        
        // Animation de transition
        this.scanStatus.classList.add('animate__animated', 'animate__fadeIn');
        setTimeout(() => {
            this.scanStatus.classList.remove('animate__fadeIn');
        }, 500);
    }
    
    showFeedback(message, type) {
        if (!this.feedbackMessage) return;
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        
        this.feedbackMessage.innerHTML = `
            <i class="fas fa-${icons[type] || 'info-circle'} me-2"></i>
            ${message}
        `;
        
        this.feedbackMessage.className = `feedback-message feedback-${type} animate__animated animate__fadeIn`;
        
        // Auto-masquage
        const hideDelay = {
            info: 4000,
            success: 5000,
            warning: 6000,
            error: 8000
        }[type] || 5000;
        
        setTimeout(() => {
            if (this.feedbackMessage.innerHTML.includes(message)) {
                this.feedbackMessage.classList.add('animate__fadeOut');
                setTimeout(() => {
                    this.feedbackMessage.innerHTML = '';
                    this.feedbackMessage.className = 'feedback-message';
                }, 500);
            }
        }, hideDelay);
    }
    
    animateSuccess() {
        this.animateElement('.scan-frame', 'animate__pulse');
        this.animateElement('.scan-line', 'animate__flash');
    }
    
    animateError() {
        this.animateElement('.scan-frame', 'animate__shakeX');
    }
    
    animateCameraSwitch() {
        this.animateElement('.video-wrapper', 'animate__fadeIn');
    }
    
    animateElement(selector, animation) {
        const element = document.querySelector(selector);
        if (element) {
            element.classList.add('animate__animated', animation, 'animate__faster');
            setTimeout(() => {
                element.classList.remove(animation);
            }, 1000);
        }
    }
    
    shakeElement(element) {
        if (element) {
            element.classList.add('animate__animated', 'animate__shakeX');
            setTimeout(() => {
                element.classList.remove('animate__shakeX');
            }, 1000);
        }
    }
    
    async loadPointagesJour(date = null) {
        const pointagesContainer = document.getElementById('pointages-container');
        if (!pointagesContainer) return;
        
        const dateFilter = date || (this.dateFilter ? this.dateFilter.value : new Date().toISOString().split('T')[0]);
        
        try {
            pointagesContainer.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Chargement des pointages...
                </div>
            `;
            
            const url = `${this.apiEndpoints.getPointagesJour}?date=${encodeURIComponent(dateFilter)}`;
            console.log('Chargement des pointages depuis:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
                if (data.success) {
                // Prefer summaries (aggregated sentences) when available
                const entries = data.summaries && data.summaries.length ? data.summaries : (data.pointages || []);
                this.renderPointagesJour(entries, data.stats || {}, dateFilter);
                this.updateStats(data.stats || {});
            } else {
                pointagesContainer.innerHTML = `
                    <div class="text-center p-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-3"></i>
                        <p>Aucun pointage trouvé pour cette date</p>
                    </div>
                `;
            }
            
        } catch (error) {
            console.error('Erreur chargement pointages:', error);
            
            // Mode dégradé : afficher un message et des données factices
            pointagesContainer.innerHTML = `
                <div class="text-center p-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Impossible de charger les pointages en temps réel
                    </div>
                    <div class="text-muted">
                        <small>Le système fonctionne en mode hors ligne</small>
                    </div>
                </div>
            `;
            
            this.updateStats({
                arrivees: 0,
                departs: 0,
                retards: 0,
                temps_travail: '00:00',
                total_pointages: 0
            });
        }
    }
    
    renderPointagesJour(pointages, stats, date) {
        const container = document.getElementById('pointages-container');
        if (!container) return;
        
        // Mettre à jour la date affichée
        const dateDisplay = document.getElementById('current-date');
        if (dateDisplay) {
            const dateObj = new Date(date);
            dateDisplay.textContent = dateObj.toLocaleDateString('fr-FR', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        if (!pointages || pointages.length === 0) {
            container.innerHTML = `
                <div class="pointage-list">
                    <div class="text-center p-5 text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <p class="mb-0">Aucun pointage enregistré pour cette date</p>
                    </div>
                </div>
            `;
            return;
        }
        // If entries are summaries (contain 'sentence'), render the sentence layout
        let html = '<div class="pointage-list">';
        pointages.forEach(entry => {
            if (entry.sentence) {
                html += `
                <div class="pointage-item p-3 border-bottom">
                    <div>
                        <strong>${entry.fullname}</strong>
                        <div class="text-muted small">${entry.departement}</div>
                        <div class="mt-2">${entry.sentence}</div>
                    </div>
                </div>
                `;
            } else if (entry.nom || entry.prenom) {
                // Fallback to old format (individual pointage)
                const name = `${entry.prenom || ''} ${entry.nom || ''}`.trim();
                const time = entry.heure || '';
                const typeLabel = entry.type || '';
                html += `
                <div class="pointage-item p-3 border-bottom">
                    <div class="d-flex justify-content-between">
                        <div>${name} • ${entry.departement || ''}</div>
                        <div class="text-muted small">${typeLabel} à ${time}</div>
                    </div>
                </div>
                `;
            }
        });

        html += '</div>';
        container.innerHTML = html;
        
        // Mettre à jour le compteur
        this.updateHistoryCount();
    }
    
    updateStats(stats) {
        const elements = {
            'stat-arrivees': 'arrivees',
            'stat-departs': 'departs', 
            'stat-retards': 'retards',
            'stat-temps': 'temps_travail'
        };
        
        Object.entries(elements).forEach(([id, key]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = stats[key] || (key === 'temps_travail' ? '00:00' : '0');
            }
        });
        
        // Mettre à jour les détails
        const details = {
            'arrivee-details': `Arrivées: ${stats.arrivees || 0}`,
            'depart-details': `Départs: ${stats.departs || 0}`,
            'temps-details': `Temps moyen: ${stats.temps_travail || '00:00'}`,
            'retard-details': `Retards: ${stats.retards || 0}`
        };
        
        Object.entries(details).forEach(([id, text]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = text;
        });
    }
    
    bindEvents() {
        // Boutons principaux
        if (this.restartBtn) {
            this.restartBtn.addEventListener('click', () => this.restartScanner());
        }
        
        if (this.switchCameraBtn) {
            this.switchCameraBtn.addEventListener('click', () => this.switchCamera());
        }
        
        if (this.toggleFlashBtn) {
            this.toggleFlashBtn.addEventListener('click', () => this.toggleFlash());
        }
        
        // Filtre de date
        if (this.dateFilter) {
            this.dateFilter.addEventListener('change', (e) => {
                this.loadPointagesJour(e.target.value);
            });
        }
        
        // Bouton de rafraîchissement
        if (this.refreshHistoryBtn) {
            this.refreshHistoryBtn.addEventListener('click', () => {
                this.loadPointagesJour();
            });
        }
        
        // Note: submit handled via form submit listener below (allows mandatory full-submit)
        
        // Gestion de la visibilité
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseScanner();
            } else {
                this.resumeScanner();
            }
        });
        
        // Gestion du formulaire de justification
        const justificationForm = document.getElementById('justificationForm');
        if (justificationForm) {
            justificationForm.addEventListener('submit', (e) => {
                const isRequired = justificationForm.dataset.required === 'true';
                const submitHidden = document.getElementById('submitJustificationHidden');
                if (isRequired) {
                    // Allow normal form submission (server will redirect to dashboard)
                    if (submitHidden) submitHidden.value = '1';
                    return; // don't prevent default
                }

                // For non-mandatory flows, submit via AJAX to keep on the current page
                e.preventDefault();
                this.submitJustification();
            });
        }
        
        // Compteur de caractères pour les justifications
        const detailsTextarea = document.getElementById('justificationDetails');
        if (detailsTextarea) {
            detailsTextarea.addEventListener('input', () => {
                const charCount = document.getElementById('char-count');
                if (charCount) {
                    charCount.textContent = `${detailsTextarea.value.length}/500`;
                }
            });
        }
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ignorer si dans un champ de saisie
            if (e.target.tagName === 'INPUT' || 
                e.target.tagName === 'TEXTAREA' || 
                e.target.isContentEditable) {
                return;
            }
            
            // Raccourcis clavier
            switch(e.key.toLowerCase()) {
                case 'r':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        this.restartScanner();
                    }
                    break;
                case 'c':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        this.switchCamera();
                    }
                    break;
                case 'f':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        this.toggleFlash();
                    }
                    break;
                case 'f5':
                    e.preventDefault();
                    this.loadPointagesJour();
                    break;
                case 'escape':
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    }
                    break;
            }
        });
    }
    
    setupNetworkMonitoring() {
        window.addEventListener('online', () => {
            this.showFeedback('Connexion rétablie', 'success');
            this.loadPointagesJour();
        });
        
        window.addEventListener('offline', () => {
            this.showFeedback('Connexion perdue - Mode hors ligne', 'warning');
        });
        
        // Vérifier l'état initial
        if (!navigator.onLine) {
            this.showFeedback('Mode hors ligne - certaines fonctionnalités sont limitées', 'warning');
        }
    }
    
    setupPerformanceMonitoring() {
        // rendre idempotent : arrêter l'ancien intervalle s'il existe
        if (this.performanceInterval) {
            clearInterval(this.performanceInterval);
            this.performanceInterval = null;
        }

        this.performanceMetrics = {
            startTime: Date.now(),
            scanCount: 0,
            errorCount: 0
        };

        this.performanceInterval = setInterval(() => {
            this.monitorPerformance();
        }, 30000);
    }
    
    monitorPerformance() {
        const now = Date.now();
        const uptime = now - this.performanceMetrics.startTime;
        
        if (this.performanceMetrics.errorCount > 10) {
            console.warn('Nombre d\'erreurs élevé:', this.performanceMetrics.errorCount);
        }
        
        // Réinitialisation périodique
        if (uptime > 300000) {
            this.performanceMetrics.scanCount = 0;
            this.performanceMetrics.errorCount = 0;
            this.performanceMetrics.startTime = now;
        }
    }

    // Démarre le monitoring de performance si non démarré
    startPerformanceMonitoring() {
        if (this.performanceInterval) {
            // déjà démarré
            return;
        }

        if (!this.performanceMetrics) {
            this.performanceMetrics = {
                startTime: Date.now(),
                scanCount: 0,
                errorCount: 0
            };
        }

        this.performanceInterval = setInterval(() => this.monitorPerformance(), 30000);
        console.log('Performance monitoring démarré');
    }

    // Arrête le monitoring de performance
    stopPerformanceMonitoring() {
        if (this.performanceInterval) {
            clearInterval(this.performanceInterval);
            this.performanceInterval = null;
            console.log('Performance monitoring arrêté');
        }
    }
    
    async pauseScanner() {
        if (this.scanner && !this.isProcessing) {
            await this.scanner.stop();
            this.showFeedback('Scanner en pause', 'info');
        }
    }
    
    async resumeScanner() {
        if (this.scanner && !this.isProcessing) {
            await this.scanner.start();
            this.showFeedback('Scanner réactivé', 'success');
        }
    }
    
    handleFatalError(error) {
        console.error('Erreur fatale:', error);
        
        this.showFeedback(`
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-2 text-danger"></i>
                <div>Erreur critique du scanner</div>
                <small class="text-muted">${error.message}</small>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-redo me-2"></i>Recharger la page
                    </button>
                </div>
            </div>
        `, 'error');
        
        this.isProcessing = true;
        if (this.scanner) {
            try {
                const stopResult = this.scanner.stop();
                // stopResult peut être une Promise ou undefined selon l'implémentation
                if (stopResult && typeof stopResult.then === 'function') {
                    stopResult.catch(console.error);
                }
            } catch (e) {
                console.error('Erreur lors de l\'arrêt du scanner:', e);
            }
        }
        
        this.enableControls(false);
    }
    
    sendNotification(title, message) {
        // Vérifier si les notifications sont supportées
        if (!("Notification" in window)) {
            return;
        }
        
        // Demander la permission si nécessaire
        if (Notification.permission === "granted") {
            new Notification(title, { body: message });
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    new Notification(title, { body: message });
                }
            });
        }
    }
    
    async destroy() {
        // Arrêt des intervalles
        if (this.performanceInterval) {
            clearInterval(this.performanceInterval);
        }
        
        // Arrêt du scanner
        if (this.scanner) {
            await this.scanner.destroy();
            this.scanner = null;
        }
        
        // Nettoyage des états
        this.isProcessing = false;
        this.scanAttempts = 0;
        
        console.log('AdvancedBadgeScanner détruit');
    }
}

// Gestionnaire d'erreurs global amélioré
window.addEventListener('error', (event) => {
    console.error('Erreur globale:', event.error);
    
    // Envoyer les erreurs critiques au serveur si possible
    if (event.error) {
        const errorData = {
            type: 'global_error',
            message: event.error.message,
            stack: event.error.stack,
            url: window.location.href,
            timestamp: new Date().toISOString()
        };
        
        // Envoi asynchrone sans bloquer (défensif)
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/api/log_error.php', JSON.stringify(errorData));
            }
        } catch (e) {
            console.warn('sendBeacon échoué:', e);
        }
    }
});

// Initialisation sécurisée
document.addEventListener('DOMContentLoaded', () => {
    // Vérifier que nous sommes sur la page de scan
    if (document.getElementById('qr-video')) {
        try {
            // Vérifier les prérequis
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                document.getElementById('scan-status').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Votre navigateur ne supporte pas la caméra. Utilisez Chrome, Firefox ou Edge récent.
                    </div>
                `;
                return;
            }
            
            // Initialiser le scanner
            window.badgeScanner = new AdvancedBadgeScanner();
            
            // Exporter pour le débogage
            window._badgeScannerInstance = window.badgeScanner;
            
        } catch (error) {
            console.error('Échec de l\'initialisation du scanner:', error);
            document.getElementById('scan-status').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Impossible de démarrer le scanner: ${error.message}
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary" onclick="window.location.reload()">
                            <i class="fas fa-redo me-2"></i>Recharger
                        </button>
                    </div>
                </div>
            `;
        }
    }
});

// CSS supplémentaire pour les animations et overlays
const style = document.createElement('style');
style.textContent = `
    .scan-result-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .scan-result-overlay.show {
        opacity: 1;
    }
    
    .scan-result-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        position: relative;
        animation: slideInUp 0.5s;
    }
    
    .result-icon {
        font-size: 4rem;
        color: #28a745;
        margin-bottom: 20px;
    }
    
    .btn-close-result {
        position: absolute;
        top: 10px;
        right: 10px;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }
    
    .btn-close-result:hover {
        color: #333;
    }
    
    @keyframes slideInUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .animate__faster {
        animation-duration: 0.5s !important;
    }
    
    .pointage-item {
        transition: all 0.3s;
    }
    
    .pointage-item:hover {
        background-color: #f8f9fa;
    }
`;
document.head.appendChild(style);

// Exporter pour les tests
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdvancedBadgeScanner;
}