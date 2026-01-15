[1mdiff --git a/scan.js b/scan.js[m
[1mindex 85f0784..4234019 100644[m
[1m--- a/scan.js[m
[1m+++ b/scan.js[m
[36m@@ -20,6 +20,8 @@[m [mconst appState = {[m
     isScanning: false,[m
     lastScanToken: null[m
 };[m
[32m+[m[32m// Tokens récemment traités pour éviter double-scan rapide[m
[32m+[m[32mappState.processedTokens = {};[m
 [m
 // Fonctions utilitaires[m
 const utils = {[m
[36m@@ -30,15 +32,16 @@[m [mconst utils = {[m
             warning: 'fa-exclamation-triangle',[m
             info: 'fa-info-circle'[m
         };[m
[32m+[m[32m        // Afficher warnings en rouge aussi[m
[32m+[m[32m        if (type === 'warning') type = 'danger';[m
 [m
         const notification = document.createElement('div');[m
[31m-        notification.className = `alert alert-${type} notification animate__animated animate__fadeInUp`;[m
[32m+[m[32m        const cssType = (type === 'error' || type === 'danger') ? 'danger' : type;[m
[32m+[m[32m        notification.className = `alert alert-${cssType} notification animate__animated animate__fadeInUp`;[m
         notification.innerHTML = `[m
             <button type="button" class="btn-close" data-bs-dismiss="alert"></button>[m
[31m-            <strong><i class="fas ${iconMap[type] || 'fa-info-circle'} me-2"></i>[m
[31m-            ${type === 'success' ? 'Succès' : [m
[31m-              type === 'danger' ? 'Erreur' : [m
[31m-              type === 'warning' ? 'Avertissement' : 'Information'}</strong>[m
[32m+[m[32m            <strong><i class="fas ${iconMap[cssType] || 'fa-info-circle'} me-2"></i>[m
[32m+[m[32m            ${cssType === 'success' ? 'Succès' : 'Erreur'}</strong>[m
             <p class="mb-0">${message}</p>[m
         `;[m
         [m
[36m@@ -69,7 +72,8 @@[m [mconst utils = {[m
     addLogEntry: (message, status = 'success') => {[m
         const now = new Date();[m
         const entry = document.createElement('li');[m
[31m-        entry.className = `list-group-item list-group-item-${status === 'success' ? 'success' : 'danger'} animate__animated animate__fadeIn`;[m
[32m+[m[32m        const cssStatus = (status === 'success') ? 'success' : 'danger';[m
[32m+[m[32m        entry.className = `list-group-item list-group-item-${cssStatus} animate__animated animate__fadeIn`;[m
         entry.innerHTML = `[m
             <i class="fas ${status === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} me-2"></i>[m
             <strong>${now.toLocaleTimeString()}:</strong> ${message}[m
[36m@@ -92,22 +96,31 @@[m [mconst utils = {[m
 [m
 // Gestionnaire de scan QR[m
 const handleScan = async (result) => {[m
[32m+[m[32m    const qrToken = result.data;[m
[32m+[m
[32m+[m[32m    // Empêcher le double traitement accidentel : confirmation si déjà traité récemment[m
[32m+[m[32m    const nowTs = Date.now();[m
[32m+[m[32m    const lastTs = appState.processedTokens[qrToken] || 0;[m
[32m+[m[32m    const cooldownMs = 20 * 1000; // 20 secondes[m
[32m+[m[32m    if (lastTs && (nowTs - lastTs) < cooldownMs) {[m
[32m+[m[32m        const confirmed = window.confirm('Ce badge a été scanné récemment. Confirmer pour le re-scanner ?');[m
[32m+[m[32m        if (!confirmed) {[m
[32m+[m[32m            utils.updateStatus('Scan annulé (double détection)', 'waiting');[m
[32m+[m[32m            utils.addHistoryItem(qrToken, 'error');[m
[32m+[m[32m            utils.addLogEntry('Double scan annulé par l\'utilisateur', 'error');[m
[32m+[m[32m            return;[m
[32m+[m[32m        }[m
[32m+[m[32m    }[m
[32m+[m
     if (appState.isScanning) return;[m
     appState.isScanning = true;[m
[31m-    [m
[31m-    const qrToken = result.data;[m
     appState.lastScanToken = qrToken;[m
 [m
     utils.updateStatus('Traitement en cours...', 'scanning');[m
     utils.addHistoryItem(qrToken, 'pending');[m
 [m
     try {[m
[31m-        // Remplacer l'ancienne validation[m
[31m-        const result = await BadgeManager.validateForCheckin(qrToken, pdo);[m
[31m-    if (result.valid) {[m
[31m-    // Enregistrer le pointage...[m
[31m-        }[m
[31m-        // Validation et enregistrement du pointage[m
[32m+[m[32m        // Envoi au serveur pour enregistrement[m
         const response = await fetch('pointage.php', {[m
             method: 'POST',[m
             headers: {[m
[36m@@ -115,15 +128,27 @@[m [mconst handleScan = async (result) => {[m
             },[m
             body: JSON.stringify({ badge_token: qrToken })[m
         });[m
[31m-        [m
[32m+[m
[32m+[m[32m        // Tenter de parser la réponse même si response.ok est false, pour récupérer message d'erreur[m
[32m+[m[32m        const text = await response.text();[m
[32m+[m[32m        let pointageResult = null;[m
[32m+[m[32m        try {[m
[32m+[m[32m            pointageResult = text ? JSON.parse(text) : null;[m
[32m+[m[32m        } catch (e) {[m
[32m+[m[32m            throw new Error('Réponse serveur non-JSON: ' + text.slice(0, 200));[m
[32m+[m[32m        }[m
[32m+[m
         if (!response.ok) {[m
[31m-            throw new Error('Erreur réseau lors de la requête');[m
[32m+[m[32m            const msg = (pointageResult && (pointageResult.message || pointageResult.error)) || ('Erreur réseau: ' + response.status);[m
[32m+[m[32m            // marquer comme traité pour éviter relances rapides[m
[32m+[m[32m            appState.processedTokens[qrToken] = Date.now();[m
[32m+[m[32m            throw new Error(msg);[m
         }[m
[31m-        [m
[31m-        const pointageResult = await response.json();[m
[31m-        [m
[31m-        if (pointageResult.status !== 'success') {[m
[31m-            throw new Error(pointageResult.message || "Erreur lors du pointage");[m
[32m+[m
[32m+[m[32m        if (!pointageResult || (pointageResult.status !== 'success' && pointageResult.success !== true)) {[m
[32m+[m[32m            const msg = (pointageResult && (pointageResult.message || pointageResult.error)) || 'Erreur lors du pointage';[m
[32m+[m[32m            appState.processedTokens[qrToken] = Date.now();[m
[32m+[m[32m            throw new Error(msg);[m
         }[m
 [m
         // Succès[m
[36m@@ -132,6 +157,9 @@[m [mconst handleScan = async (result) => {[m
         utils.addLogEntry(pointageResult.message, 'success');[m
         utils.showNotification(pointageResult.message, 'success');[m
 [m
[32m+[m[32m        // marquer token traité[m
[32m+[m[32m        appState.processedTokens[qrToken] = Date.now();[m
[32m+[m
         // Redirection après délai[m
         setTimeout(() => {[m
             window.location.href = 'employe_dashboard.php';[m
