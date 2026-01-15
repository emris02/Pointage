import QrScanner from './js/qr-scanner.min.js';

// Éléments DOM
const video = document.getElementById('qr-video');
const statusElement = document.getElementById('scan-status');
const historique = document.getElementById('historique');
const journal = document.getElementById('journal');
const restartBtn = document.getElementById('restart-scan');

// Configuration du scanner
const scannerConfig = {
    highlightScanRegion: true,
    highlightCodeOutline: true,
    maxScansPerSecond: 5,
    returnDetailedScanResult: true
};

// États de l'application
const appState = {
    isScanning: false,
    lastScanToken: null
};

// Fonctions utilitaires
const utils = {
    showNotification: (message, type = 'info', duration = 5000) => {
        const iconMap = {
            success: 'fa-check-circle',
            danger: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification animate__animated animate__fadeInUp`;
        notification.innerHTML = `
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <strong><i class="fas ${iconMap[type] || 'fa-info-circle'} me-2"></i>
            ${type === 'success' ? 'Succès' : 
              type === 'danger' ? 'Erreur' : 
              type === 'warning' ? 'Avertissement' : 'Information'}</strong>
            <p class="mb-0">${message}</p>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('animate__fadeOut');
            setTimeout(() => notification.remove(), 500);
        }, duration);
    },

    addHistoryItem: (token, status = 'success') => {
        const now = new Date();
        const item = document.createElement('li');
        item.className = `badge-item list-group-item d-flex justify-content-between align-items-center animate__animated animate__fadeIn`;
        item.innerHTML = `
            <span>${now.toLocaleTimeString()}</span>
            <div>
                <span class="badge bg-primary me-2">${token.substring(0, 12)}...</span>
                <span class="badge bg-${status === 'success' ? 'success' : 'danger'}">
                    <i class="fas ${status === 'success' ? 'fa-check' : 'fa-times'}"></i>
                </span>
            </div>
        `;
        historique.prepend(item);
    },

    addLogEntry: (message, status = 'success') => {
        const now = new Date();
        const entry = document.createElement('li');
        entry.className = `list-group-item list-group-item-${status === 'success' ? 'success' : 'danger'} animate__animated animate__fadeIn`;
        entry.innerHTML = `
            <i class="fas ${status === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} me-2"></i>
            <strong>${now.toLocaleTimeString()}:</strong> ${message}
        `;
        journal.prepend(entry);
    },

    updateStatus: (message, status = 'waiting') => {
        const iconMap = {
            waiting: 'fa-search',
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            scanning: 'fa-qrcode'
        };

        statusElement.className = `scan-status ${status} animate__animated animate__pulse`;
        statusElement.innerHTML = `<i class="fas ${iconMap[status]} me-2"></i> ${message}`;
    }
};

// Gestionnaire de scan QR
const handleScan = async (result) => {
    if (appState.isScanning) return;
    appState.isScanning = true;
    
    const qrToken = result.data;
    appState.lastScanToken = qrToken;

    utils.updateStatus('Traitement en cours...', 'scanning');
    utils.addHistoryItem(qrToken, 'pending');

    try {
        // Remplacer l'ancienne validation
        const result = await BadgeManager.validateForCheckin(qrToken, pdo);
    if (result.valid) {
    // Enregistrer le pointage...
        }
        // Validation et enregistrement du pointage
        const response = await fetch('pointage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ badge_token: qrToken })
        });

        // Gérer conflit 409 -> demander validation
        if (response.status === 409) {
            const body = await response.json().catch(() => null);
            const msg = (body && (body.message || body.code)) ? (body.message || body.code) : 'Pointage en conflit';
            // show confirmation modal
            await showConflictModal(msg, body);
            return;
        }

        if (!response.ok) {
            throw new Error('Erreur réseau lors de la requête');
        }

        const pointageResult = await response.json();
        
        if (pointageResult.status !== 'success') {
            throw new Error(pointageResult.message || "Erreur lors du pointage");
        }

        // Succès
        utils.updateStatus('Pointage enregistré', 'success');
        utils.addHistoryItem(qrToken, 'success');
        utils.addLogEntry(pointageResult.message, 'success');
        utils.showNotification(pointageResult.message, 'success');

        // Redirection après délai
        setTimeout(() => {
            window.location.href = 'employe_dashboard.php';
        }, 2000);

    } catch (error) {
        console.error('Erreur:', error);
        utils.updateStatus(error.message, 'error');
        utils.addHistoryItem(qrToken, 'error');
        utils.addLogEntry(error.message, 'error');
        utils.showNotification(error.message, 'danger');
    } finally {
        appState.isScanning = false;
        scanner.start();
    }
};

// Modal helper for conflicts
async function showConflictModal(message, payload){
    return new Promise(async (resolve) => {
        // create modal element
        const modal = document.createElement('div');
        modal.className = 'conflict-modal';
        modal.style.position = 'fixed'; modal.style.left = 0; modal.style.top = 0; modal.style.right = 0; modal.style.bottom = 0; modal.style.background = 'rgba(0,0,0,0.4)'; modal.style.display = 'flex'; modal.style.alignItems = 'center'; modal.style.justifyContent = 'center'; modal.style.zIndex = 2000;
        modal.innerHTML = `
            <div style="background:#fff;padding:20px;border-radius:8px;max-width:420px;width:90%">
                <h5>Action détectée</h5>
                <p id="conflictMsg">${message}</p>
                <div style="display:flex;gap:8px;margin-top:12px">
                    <button id="conflictPause" class="btn btn-secondary">⏸️ Prendre une pause</button>
                    <button id="conflictDepart" class="btn btn-warning">🚪 Départ anticipé</button>
                    <button id="conflictCancel" class="btn btn-outline-secondary">❌ Annuler</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('conflictCancel').addEventListener('click', ()=>{ modal.remove(); resolve({action:'cancel'}); });
        document.getElementById('conflictPause').addEventListener('click', async ()=>{
            // open prompt for minutes
            const mins = prompt('Durée de la pause (minutes)', '30'); if(!mins){ return; }
            // call api/do_pause_confirm.php
            const id = payload?.data?.employe_id || payload?.data?.admin_id || payload?.last?.id || null;
            const type = payload?.data?.user_type || (payload && payload.last && payload.last.admin_id ? 'admin' : 'employe');
            if(!id){ utils.showNotification('ID introuvable pour l\'action', 'danger'); return; }
            try{
                const res = await fetch('api/do_pause_confirm.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type, id, minutes: mins})});
                const j = await res.json();
                if(res.ok && j.status === 'success'){ utils.showNotification(j.message,'success'); // try to refresh UI
                    const refreshBtn = document.getElementById('refresh-history'); if(refreshBtn) refreshBtn.click(); modal.remove(); resolve({action:'pause',result:j}); }
                else { utils.showNotification(j.message||'Erreur', 'danger'); }
            }catch(e){ utils.showNotification('Erreur: '+e.message,'danger'); }
        });
        document.getElementById('conflictDepart').addEventListener('click', async ()=>{
            const reason = prompt('Motif du départ anticipé (obligatoire)'); if(!reason) return alert('Motif requis');
            const id = payload?.data?.employe_id || payload?.data?.admin_id || payload?.last?.id || null;
            const type = payload?.data?.user_type || (payload && payload.last && payload.last.admin_id ? 'admin' : 'employe');
            if(!id){ utils.showNotification('ID introuvable pour l\'action', 'danger'); return; }
            try{
                const res = await fetch('api/do_depart_confirm.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type, id, reason})});
                const j = await res.json();
                if(res.ok && j.status === 'success'){ utils.showNotification(j.message,'success'); const refreshBtn = document.getElementById('refresh-history'); if(refreshBtn) refreshBtn.click(); modal.remove(); resolve({action:'depart',result:j}); }
                else { utils.showNotification(j.message||'Erreur', 'danger'); }
            }catch(e){ utils.showNotification('Erreur: '+e.message,'danger'); }
        });
    });
}

// Initialisation du scanner
const scanner = new QrScanner(video, handleScan, scannerConfig);

// Gestion des événements
restartBtn.addEventListener('click', () => {
    scanner.start().then(() => {
        utils.updateStatus('Prêt à scanner', 'waiting');
    });
});

// Démarrer l'application
const initScanner = async () => {
    try {
        await scanner.start();
        utils.updateStatus('Prêt à scanner', 'waiting');
    } catch (error) {
        console.error('Erreur initialisation scanner:', error);
        utils.updateStatus('Erreur caméra', 'error');
        utils.showNotification('Veuillez autoriser l\'accès à la caméra', 'danger');
    }
};

// Lancement
initScanner();