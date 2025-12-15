/**
 * employe_dashboard.js
 * Gestion complète du dashboard employé :
 * - Badge (création, timer, impression)
 * - Calendrier (FullCalendar, lecture seule, rappels)
 * - Profil (édition inline)
 * - Notifications et alertes
 * Auteur : Moha (Xpert Pro)
 */

document.addEventListener('DOMContentLoaded', () => {
    initBadgeSystem();

    // Attendre que FullCalendar soit chargé et éviter une double initialisation
    (function ensureCalendarReady(){
        // Si une instance existe déjà, ne rien faire
        if (window.employeeCalendar) return;

        if (typeof FullCalendar !== 'undefined' && typeof FullCalendar.Calendar !== 'undefined') {
            try {
                // Priorité à une implémentation globale fournie par la page
                if (typeof window.initEmployeeCalendar === 'function') {
                    window.initEmployeeCalendar();
                } else if (typeof initEmployeeCalendar === 'function') {
                    initEmployeeCalendar();
                }
            } catch (e) {
                console.error('Erreur initialisation calendrier (calendar-employe.js):', e);
            }
        } else {
            setTimeout(ensureCalendarReady, 150);
        }
    })();

    initInlineProfileEdit();
    initBootstrapTooltips();
});

/* ----------------------------------------------------------
   🪪 1. GESTION DU BADGE EMPLOYÉ
----------------------------------------------------------- */
function initBadgeSystem() {
    const btnBadge = document.getElementById('demanderBadgeBtn');
    const timerElement = document.getElementById('badge-timer');
    const loader = document.getElementById('badge-loader');

    // 🧩 Démarrer le timer si badge actif
    if (window.badgeExpiry) {
        startBadgeTimer(window.badgeExpiry, timerElement);
    }

    // 🎫 Génération d’un nouveau badge
    if (btnBadge) {
        btnBadge.addEventListener('click', () => {
            btnBadge.disabled = true;
            loader.style.display = 'block';

            fetch('api/badge.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate',
                    employe_id: window.employeId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Badge généré', 'Votre nouveau badge est prêt.', 'success');
                    location.reload();
                } else {
                    showToast('Erreur', data.message || 'Impossible de générer le badge', 'danger');
                }
            })
            .catch(() => showToast('Erreur', 'Problème de connexion au serveur.', 'danger'))
            .finally(() => {
                btnBadge.disabled = false;
                loader.style.display = 'none';
            });
        });
    }
}

/**
 * Lancer le compte à rebours du badge
 */
function startBadgeTimer(expiryDate, element) {
    const expiry = new Date(expiryDate).getTime();
    const timer = setInterval(() => {
        const now = new Date().getTime();
        const distance = expiry - now;

        if (distance <= 0) {
            clearInterval(timer);
            element.innerHTML = '<span class="text-danger">Badge expiré</span>';
            showToast('Badge expiré', 'Veuillez renouveler votre badge.', 'warning');
            return;
        }

        const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        element.innerHTML = `Expire dans ${h}h ${m}m`;
    }, 1000);
}

/**
 * Imprimer le badge en version physique
 */
function printBadge() {
    const printWindow = window.open('', '_blank');
    const { prenom, nom, poste, departement, token, expires_at } = window.badgeInfo || {};
    printWindow.document.write(`
        <html>
            <head>
                <title>Badge ${prenom} ${nom}</title>
                <style>
                    body { font-family: Arial; text-align: center; padding: 20px; }
                    .badge { border: 2px solid #000; display: inline-block; padding: 20px; }
                    .qr { width: 200px; height: 200px; margin: 10px auto; }
                </style>
            </head>
            <body>
                <div class="badge">
                    <h2>XPERT PRO</h2>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(token)}" class="qr" alt="QR Code">
                    <h3>${prenom} ${nom}</h3>
                    <p>${poste} - ${departement}</p>
                    <p>Valide jusqu’au ${new Date(expires_at).toLocaleString('fr-FR')}</p>
                </div>
            </body>
        </html>
    `);
    printWindow.print();
}

/* ----------------------------------------------------------
   📅 2. CALENDRIER EMPLOYÉ (FullCalendar)
----------------------------------------------------------- */
function initEmployeeCalendar() {
    const calendarEl = document.getElementById('calendar-employe');
    if (!calendarEl) return;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        nowIndicator: true,
        editable: false,
        selectable: false,
        dayMaxEvents: 3,
        eventDisplay: 'block',
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        events: fetchEmployeeEvents,
        eventDidMount: (info) => {
            // Build a safe Bootstrap tooltip (plain text) to avoid rendering raw HTML/JS
            try {
                const ev = info.event;
                function escapeHtml(str) {
                    if (str === null || str === undefined) return '';
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                const title = escapeHtml(ev.title || '');
                const type = escapeHtml(ev.extendedProps.type || '');
                const desc = escapeHtml(ev.extendedProps.description || 'Aucune description');
                const start = ev.start ? new Date(ev.start).toLocaleString('fr-FR') : '';

                // Use plain text tooltip to avoid injecting HTML into the DOM
                const tooltipText = `${title} ${type ? '(' + type + ')' : ''}\n${desc}\n${start}`;
                info.el.setAttribute('data-bs-toggle', 'tooltip');
                info.el.setAttribute('data-bs-html', 'false');
                info.el.setAttribute('title', tooltipText);
                new bootstrap.Tooltip(info.el);
            } catch (e) {
                console.debug('eventDidMount tooltip error', e);
            }
        },
        // For employee calendar events must NOT be clickable — prevent default click behavior
        eventClick: (info) => {
            if (info && info.jsEvent && typeof info.jsEvent.preventDefault === 'function') {
                info.jsEvent.preventDefault();
            }
            // No action for employees on event click
        }
    });

    calendar.render();
    setInterval(() => checkUpcomingEvents(calendar), 30000);
}

/**
 * Charger les événements depuis le serveur
 */
function fetchEmployeeEvents(fetchInfo, success, fail) {
    // Pass the requested window to the server for efficient querying
    const params = new URLSearchParams({ start: fetchInfo.startStr, end: fetchInfo.endStr });
    fetch('get_evenements_calendrier.php?' + params.toString(), { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(events => {
            console.debug('Calendrier (employé) — événements reçus:', events);
            // Keep events where visible is not explicitly false/0 (backwards compatible)
            const filtered = events.filter(e => e.visible !== 0 && e.visible !== false);
            success(filtered);
        })
        .catch(err => {
            console.error('Erreur fetchEmployeeEvents:', err);
            fail(err);
        });
}

/**
 * Notification automatique 5 min avant un événement
 */
function checkUpcomingEvents(calendar) {
    const now = new Date();
    calendar.getEvents().forEach(ev => {
        if (!ev.start) return;
        const diff = (ev.start.getTime() - now.getTime()) / 60000;
        if (diff > 4.5 && diff < 5.5 && !ev.extendedProps.notified) {
            ev.setExtendedProp('notified', true);
            showToast('Rappel', `L’événement <b>${ev.title}</b> commence dans 5 min.`, 'info');
        }
    });
}

/**
 * Afficher les détails d’un événement (modal)
 */
function showEventDetails(event) {
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const desc = escapeHtml(event.extendedProps.description || 'Aucune description.');
    const start = event.start ? escapeHtml(formatDateTime(event.start)) : '—';
    const end = event.end ? escapeHtml(formatDateTime(event.end)) : '—';
    const title = escapeHtml(event.title || 'Détails');

    Swal.fire({
        title: title,
        html: `
            <div class="text-start">
                <p><strong>Début :</strong> ${start}</p>
                <p><strong>Fin :</strong> ${end}</p>
                <p><strong>Détails :</strong> ${desc}</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Fermer'
    });
}

/* ----------------------------------------------------------
   👤 3. MODIFICATION INLINE DU PROFIL
----------------------------------------------------------- */
function initInlineProfileEdit() {
    document.querySelectorAll('.edit-inline').forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.closest('.detail-item');
            const field = container.dataset.field;
            const valueElement = container.querySelector('.detail-value');
            const oldValue = valueElement.textContent.trim();

            const input = document.createElement('input');
            input.className = 'form-control form-control-sm mt-2';
            input.value = oldValue;
            valueElement.replaceWith(input);

            btn.innerHTML = '✔️';
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');

            btn.addEventListener('click', () => saveProfileChange(btn, input, field), { once: true });
        });
    });
}

/**
 * Enregistrer la modification d’un champ
 */
function saveProfileChange(btn, input, field) {
    fetch('employe_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field: field, value: input.value })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const newValue = document.createElement('div');
            newValue.className = 'detail-value';
            newValue.textContent = input.value;
            input.replaceWith(newValue);
            btn.innerHTML = '<i class="fas fa-edit"></i>';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
            showToast('Profil mis à jour', `${field} modifié avec succès.`, 'success');
        } else {
            showToast('Erreur', data.message || 'Échec de la mise à jour.', 'danger');
        }
    })
    .catch(() => showToast('Erreur', 'Problème de connexion au serveur.', 'danger'));
}

/* ----------------------------------------------------------
   🔔 4. UTILITAIRES ET NOTIFICATIONS
----------------------------------------------------------- */
function showToast(title, message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 show position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = 9999;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function formatDateTime(date) {
    return new Date(date).toLocaleString('fr-FR', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short', year: 'numeric' });
}

function initBootstrapTooltips() {
    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(el => new bootstrap.Tooltip(el));
}
