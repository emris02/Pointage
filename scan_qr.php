<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scan de Badge - Xpert Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="scan.css">
</head>
<body>
    <div class="scan-container">
        <div class="scan-header">
            <h2><i class="fas fa-qrcode me-2"></i> Scan de Badge</h2>
            <p class="mb-0">Positionnez votre badge devant la caméra</p>
        </div>
        
        <div class="p-4">
            <div class="text-center position-relative">
                <video id="qr-video" class="pulse"></video>
                <div id="scan-status" class="scan-status waiting mt-3">
                    <i class="fas fa-search me-2"></i> En attente de détection...
                </div>
            </div>
            
            <div class="d-flex justify-content-center gap-3 mt-4">
                <button id="restart-scan" class="btn btn-warning">
                    <i class="fas fa-redo me-2"></i> Recommencer
                </button>
                <a href="employe_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home me-2"></i> Tableau de bord
                </a>
            </div>
        </div>
        
        <div class="p-4 bg-light">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="history-card card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Historique des scans</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul id="historique" class="list-group list-group-flush"></ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="log-card card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Journal des événements</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul id="journal" class="list-group list-group-flush"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="scan.js"></script>
    <script>

// === CONFIGURATION GLOBALE ===
const api = {
    scan: 'pointage.php',
    saveJustification: 'save_late_reason.php'
};

const state = {
    isProcessing: false,
    lastScan: '',
    scanner: null,
    cameras: [],
    currentCam: 0
};

// === INITIALISATION DU SCANNER ===
import QrScanner from './js/qr-scanner.min.js';
QrScanner.WORKER_PATH = './js/qr-scanner-worker.min.js';

export async function initScanner(videoElement, onValid, onError) {
    try {
        state.cameras = await QrScanner.listCameras(true);
        if (state.cameras.length === 0) throw new Error('Aucune caméra détectée.');

        state.scanner = new QrScanner(
            videoElement,
            (result) => handleScan(result.data, onValid, onError),
            {
                preferredCamera: state.cameras[state.currentCam]?.id,
                highlightScanRegion: true,
                maxScansPerSecond: 2
            }
        );

        await state.scanner.start();
    } catch (error) {
        onError(`Erreur initialisation caméra : ${error.message}`);
    }
}

export function switchCamera() {
    if (state.cameras.length < 2) return;

    state.currentCam = (state.currentCam + 1) % state.cameras.length;
    state.scanner.setCamera(state.cameras[state.currentCam].id);
}

export function restartScanner() {
    if (state.scanner) {
        state.scanner.start();
    }
}

// === TRAITEMENT D'UN SCAN ===
async function handleScan(token, onValid, onError) {
    if (state.isProcessing || token === state.lastScan) return;
    state.isProcessing = true;
    state.lastScan = token;

    try {
        const res = await fetch(api.scan, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        });

        const data = await res.json();

        if (data.status === 'success') {
            if (typeof onValid === 'function') onValid(data);
        } else {
            if (typeof onError === 'function') onError(data.message || "Échec validation.");
        }

    } catch (error) {
        if (typeof onError === 'function') onError("Erreur de réseau : " + error.message);
    } finally {
        state.isProcessing = false;
        setTimeout(() => {
            state.lastScan = '';
            restartScanner();
        }, 2000);
    }
}

// === ENVOI JUSTIFICATION DE RETARD ===
export async function sendJustification({ employe_id, scan_time, late_time, reason, comment }, onSuccess, onFail) {
    try {
        const response = await fetch(api.saveJustification, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: employe_id,
                scan_time,
                late_time,
                reason,
                comment,
                status: 'pending'
            })
        });

        const result = await response.json();
        result.success ? onSuccess(result) : onFail(result.message);
    } catch (err) {
        onFail("Erreur réseau : " + err.message);
    }
}
// Importation de la bibliothèque QrScanner
import QrScanner from 'js/qr-scanner.min.js';

// Références aux éléments du DOM
const video = document.getElementById('qr-video');
const feedback = document.getElementById('feedback-message');
const historique = document.getElementById('historique');
const journal = document.getElementById('journal');

// Initialisation du scanner avec détection du QR code
const scanner = new QrScanner(video, result => {
    // Récupère le texte (compatible ancienne et nouvelle version)
    const qrText = result.data ?? result;

    // Affiche dans le feedback
    feedback.textContent = `📷 QR détecté : ${qrText}`;
    feedback.classList.add('text-success', 'message-feedback');

    // Ajoute une ligne au journal de l'historique
    historique.innerHTML += `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            ${new Date().toLocaleTimeString()}
            <span class="badge bg-success">${qrText}</span>
        </li>`;

    // Arrête le scanner temporairement
    scanner.stop();

    // Envoie le QR code au backend pour traitement (validate_badge.php)
    fetch('pointage.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `type=${encodeURIComponent(qrText)}`
    })    
        .then(res => res.json())
        .then(data => {
            feedback.textContent = data.message;
            feedback.className = `message-feedback ${data.status === 'success' ? 'text-success' : 'text-danger'}`;

            journal.innerHTML += `
                <li class="list-group-item ${data.status === 'success' ? 'text-success' : 'text-danger'}">
                    ${new Date().toLocaleTimeString()} - ${data.message}
                </li>`;
        })
        .catch(err => {
            feedback.textContent = "⛔ Erreur lors du pointage.";
            feedback.className = 'message-feedback text-danger';
            journal.innerHTML += `<li class="list-group-item text-danger">Erreur : ${err}</li>`;
        });

}, {
    highlightScanRegion: true,
    highlightCodeOutline: true
});

// Démarre le scanner (attention à HTTPS !)
scanner.start();

/**
 * Permet de simuler un pointage manuel
 * @param {string} type - "arrivee" ou "depart"
 */
function envoyerPointage(type) {
    if (!['arrivee', 'depart'].includes(type)) {
        afficherNotification("⛔ Type de pointage invalide.", 'error');
        return;
    }

    fetch('pointage.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `type=${encodeURIComponent(type)}`
    })
    .then(response => response.json())
    .then(data => {
        afficherNotification(data.message, data.status === 'success' ? 'success' : 'error');
    })
    .catch(error => {
        console.error("Erreur:", error);
        afficherNotification("⛔ Une erreur est survenue. Veuillez réessayer.", 'error');
    });
}

/**
 * Affiche une notification visuelle temporaire
 */
function afficherNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 4000);
}

  // Initialisation des tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (element) {
            return new bootstrap.Tooltip(element);
        });

        function updateTimer() {
            const now = Math.floor(Date.now() / 1000);
            const diff = expirationTimestamp - now;

            if (diff <= 0) {
                timerElement.innerText = "⛔ Badge expiré";
                timerElement.classList.remove("text-success");
                timerElement.classList.add("text-danger");
                clearInterval(timerInterval);
                return;
            }

            const hours = Math.floor(diff / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            const seconds = diff % 60;

            timerElement.innerText = `Temps restant : ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

            if (diff < 3600) { // Moins d'une heure
                timerElement.classList.remove("text-success");
                timerElement.classList.add("text-warning");
            }
        }
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
        // Fonction pour afficher les notifications
        function showToast(title, message, type) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            const alertsContainer = document.getElementById('alertsContainer') || document.body;
            alertsContainer.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        // Vérification périodique de l'expiration
        function checkBadgeExpiry() {
            const expiryElement = document.querySelector('.badge-expiry');
            if (expiryElement) {
                const expiryText = expiryElement.innerText;
                if (expiryText.includes("Badge actif") && expirationTimestamp <= Math.floor(Date.now() / 1000)) {
                    showToast('Badge Expire', 'Votre badge d\'accès a expiré. Veuillez le renouveler.', 'danger');
                    expiryElement.innerText = "Badge expiré";
                    expiryElement.classList.remove("text-success");
                    expiryElement.classList.add("text-danger");
                    clearInterval(timerInterval);
                }
            }
        }
        setInterval(checkBadgeExpiry, 60000); // Vérifier toutes les minutes
        


    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>