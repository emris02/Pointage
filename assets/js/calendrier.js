// Initialisation du calendrier
function initCalendar(isAdmin = false) {
    const calendarEl = document.getElementById(isAdmin ? 'calendar-admin' : 'calendar-employe');
    if (!calendarEl) return;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        firstDay: 1, // Lundi
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'Aujourd\'hui',
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour',
            list: 'Liste'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            loadCalendarEvents(fetchInfo.start, fetchInfo.end, isAdmin, successCallback, failureCallback);
        },
        eventClick: function(info) {
            // Only show details for admins — employee calendar must be read-only and non-clickable
            if (isAdmin) {
                showEventDetails(info.event, isAdmin);
            } else {
                if (info && info.jsEvent && typeof info.jsEvent.preventDefault === 'function') {
                    info.jsEvent.preventDefault();
                }
            }
        },
        dateClick: function(info) {
            // Allow both admin and employee to create events by clicking a date
            showEventModal(null, info.dateStr);
        },
        eventDrop: function(info) {
            if (isAdmin && info.event.extendedProps.type === 'evenement') {
                updateEventDate(info.event);
            }
        },
        eventResize: function(info) {
            if (isAdmin && info.event.extendedProps.type === 'evenement') {
                updateEventDate(info.event);
            }
        },
        eventDidMount: function(info) {
            // Ajouter des tooltips pour les événements
            if (info.event.extendedProps.type === 'pointage') {
                info.el.setAttribute('title', 
                    `${info.event.extendedProps.nb_pointages} pointage(s) ce jour\n` +
                    `Premier: ${formatTime(info.event.extendedProps.premier)}\n` +
                    `Dernier: ${formatTime(info.event.extendedProps.dernier)}`
                );
            } else {
                info.el.setAttribute('title', 
                    `${info.event.title}\n` +
                    `Type: ${info.event.extendedProps.event_type}\n` +
                    `${info.event.extendedProps.description || 'Aucune description'}`
                );
            }
        }
    });

    calendar.render();
    window.calendarInstance = calendar;

    // Légende du calendrier
    createCalendarLegend(isAdmin);
}

// Chargement des événements
function loadCalendarEvents(start, end, isAdmin, successCallback, failureCallback) {
    const loadingEl = document.getElementById('calendar-loading');
    if (loadingEl) loadingEl.style.display = 'block';

    const startISO = start.toISOString().split('T')[0];
    const endISO = end.toISOString().split('T')[0];

    // Employé: charger les événements de pointage + événements généraux (FullCalendar) puis fusionner
    if (!isAdmin) {
        const p1 = fetch(`api/get_pointage_events.php?start=${encodeURIComponent(startISO)}&end=${encodeURIComponent(endISO)}`).then(r => r.json());
        const p2 = fetch(`api/get_events.php?employe_id=${encodeURIComponent(window.employeId || '')}`).then(r => r.json()).catch(() => []);
        Promise.all([p1, p2])
            .then(([pointages, generalEvents]) => {
                // Harmoniser les événements généraux pour FullCalendar si nécessaire
                const events = []
                    .concat(Array.isArray(pointages) ? pointages : [])
                    .concat(Array.isArray(generalEvents) ? generalEvents.map(ev => ({
                        id: ev.id,
                        title: ev.title || ev.titre || 'Événement',
                        start: ev.start || ev.start_date,
                        end: ev.end || ev.end_date || null,
                        allDay: false,
                        color: ev.color || '#0672e4',
                        extendedProps: Object.assign({ event_type: 'evenement' }, ev.extendedProps || {})
                    })) : []);
                successCallback(events);
            })
            .catch(err => { console.error('Erreur chargement événements:', err); failureCallback(err); })
            .finally(() => { if (loadingEl) loadingEl.style.display = 'none'; });
        return;
    }

    // Admin: charger aussi les pointages (agrégés) puis les événements généraux, et fusionner
    const p1 = fetch(`api/get_pointage_events.php?start=${encodeURIComponent(startISO)}&end=${encodeURIComponent(endISO)}`).then(r => r.json()).catch(() => []);
    const p2 = fetch('api/calendrier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_events',
            start: startISO,
            end: endISO,
            employe_id: null
        })
    }).then(r => r.json()).catch(() => []);

    Promise.all([p1, p2])
        .then(([pointages, generalEvents]) => {
            const events = []
                .concat(Array.isArray(pointages) ? pointages : [])
                .concat(Array.isArray(generalEvents) ? generalEvents.map(ev => ({
                    id: ev.id,
                    title: ev.title || ev.titre || 'Événement',
                    start: ev.start || ev.start_date,
                    end: ev.end || ev.end_date || null,
                    allDay: false,
                    color: ev.color || '#0672e4',
                    extendedProps: Object.assign({ event_type: 'evenement' }, ev.extendedProps || {})
                })) : []);
            successCallback(events);
        })
        .catch(err => { console.error('Erreur chargement événements:', err); failureCallback(err); })
        .finally(() => { if (loadingEl) loadingEl.style.display = 'none'; });
}

// Affichage des détails d'un événement
function showEventDetails(event, isAdmin) {
    const props = event.extendedProps;

    // Helper: escape HTML to display raw description safely
    function escapeHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    if (props.type === 'pointage') {
        // Détails des pointages
        Swal.fire({
            title: 'Pointages du jour',
            html: `
                <div class="text-start">
                    <p><strong>Date:</strong> ${event.start.toLocaleDateString('fr-FR')}</p>
                    <p><strong>Nombre de pointages:</strong> ${props.nb_pointages}</p>
                    <p><strong>Premier pointage:</strong> ${formatTime(props.premier)}</p>
                    <p><strong>Dernier pointage:</strong> ${formatTime(props.dernier)}</p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Fermer'
        });
    } else {
        // Détails d'un événement calendrier
        let html = `
            <div class="text-start">
                <p><strong>Titre:</strong> ${event.title}</p>
                <p><strong>Type:</strong> ${props.event_type}</p>
                <p><strong>Début:</strong> ${formatDateTime(event.start)}</p>
                <p><strong>Fin:</strong> ${formatDateTime(event.end)}</p>
        `;
        
        if (props.description) {
            html += `<p><strong>Description:</strong> ${escapeHtml(props.description)}</p>`;
        }
        
        if (props.employe) {
            html += `<p><strong>Employé:</strong> ${props.employe}</p>`;
        }
        
        html += `</div>`;
        
        const buttons = {
            confirm: {
                text: 'Fermer',
                className: 'btn btn-secondary'
            }
        };
        
        if (isAdmin) {
            buttons.edit = {
                text: 'Modifier',
                className: 'btn btn-primary',
                action: function() {
                    showEventModal(event.id.replace('event_', ''), null, true);
                }
            };
            buttons.delete = {
                text: 'Supprimer',
                className: 'btn btn-danger',
                action: function() {
                    deleteEvent(event.id.replace('event_', ''));
                }
            };
        }
        
        Swal.fire({
            title: 'Détails de l\'événement',
            html: html,
            icon: 'info',
            showCloseButton: true,
            showCancelButton: isAdmin,
            showDenyButton: isAdmin,
            confirmButtonText: buttons.confirm.text,
            confirmButtonClass: buttons.confirm.className,
            cancelButtonText: buttons.edit.text,
            cancelButtonClass: buttons.edit.className,
            denyButtonText: buttons.delete.text,
            denyButtonClass: buttons.delete.className
        });
    }
}

// Modal d'ajout/édition d'événement
function showEventModal(eventId = null, defaultDate = null, isEdit = false) {
    const modalEl = document.getElementById('eventModal');
    if (!modalEl) {
        console.warn('showEventModal: #eventModal not found in DOM, skipping bootstrap modal display.');
        // Fallback: if no modal exists, show a simple alert for read-only users
        if (eventId) {
            // Attempt to fetch and display event details
            fetch('api/calendrier.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_event', id: eventId })
            }).then(r=>r.json()).then(data=>{
                    if (data && data.success && data.event) {
                    const ev = data.event;
                    const desc = ev.description ? escapeHtml(ev.description) : '';
                    const start = ev.start_date || '';
                    Swal.fire({ title: escapeHtml(ev.titre || 'Événement'), html: `<p>${desc}</p><p>${escapeHtml(start)}</p>`, icon: 'info' });
                } else {
                    Swal.fire('Information', 'Détails indisponibles (modal manquant).', 'info');
                }
            }).catch(()=>Swal.fire('Erreur', 'Impossible de charger l\'événement.', 'error'));
        } else {
            Swal.fire('Modal manquant', 'L\'édition/ajout d\'événement n\'est pas disponible (modal introuvable).', 'warning');
        }
        return;
    }
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('eventForm');
    const title = document.getElementById('eventModalLabel');
    const deleteBtn = document.getElementById('deleteEventBtn');
    
    // Réinitialiser le formulaire
    form.reset();
    
    if (eventId) {
        // Mode édition
        title.textContent = 'Modifier l\'événement';
        deleteBtn.classList.remove('d-none');
        
        // Charger les données de l'événement
        fetch('api/calendrier.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_event',
                id: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.event;
                document.getElementById('evt-id').value = event.id;
                document.getElementById('evt-title').value = event.titre;
                document.getElementById('evt-type').value = event.type;
                document.getElementById('evt-desc').value = event.description || '';
                document.getElementById('evt-start').value = event.start_date.replace(' ', 'T');
                document.getElementById('evt-end').value = event.end_date.replace(' ', 'T');
                document.getElementById('evt-employe-id').value = event.employe_id || '';
            }
        });
        
    } else {
        // Mode création
        title.textContent = 'Nouvel événement';
        deleteBtn.classList.add('d-none');

        if (defaultDate) {
            const start = defaultDate + 'T09:00';
            const end = defaultDate + 'T10:00';
            const startEl = document.getElementById('evt-start');
            const endEl = document.getElementById('evt-end');
            if (startEl) startEl.value = start;
            if (endEl) endEl.value = end;
        }

        // If the current user is not admin, prefill the employee ID so events are linked to the user
        try {
            var isAdminGlobal = window.isAdmin === true || window.isAdmin === '1' || window.isAdmin === 1;
            if (!isAdminGlobal && window.employeId) {
                const eid = document.getElementById('evt-employe-id');
                if (eid) eid.value = window.employeId;
            }
        } catch (e) {
            // ignore
        }
    }
    
    modal.show();
}

// Soumission du formulaire d'événement
document.getElementById('eventForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const eventId = formData.get('id');
    const action = eventId ? 'update_event' : 'add_event';
    
    const eventData = {
        action: action,
        titre: formData.get('titre'),
        type: formData.get('type'),
        description: formData.get('description'),
        start_date: formData.get('start_date').replace('T', ' '),
        end_date: formData.get('end_date').replace('T', ' '),
        employe_id: formData.get('employe_id') || null
    };
    
    if (eventId) {
        eventData.id = eventId;
    }
    
    fetch('api/calendrier.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(eventData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const evModalEl = document.getElementById('eventModal');
            if (evModalEl && typeof bootstrap !== 'undefined') {
                const evInst = bootstrap.Modal.getInstance(evModalEl);
                if (evInst && typeof evInst.hide === 'function') {
                    try { evInst.hide(); } catch (e) { console.warn('Unable to hide eventModal', e); }
                }
            }
            showNotification(data.message, 'success');
            window.calendarInstance.refetchEvents();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'enregistrement', 'error');
    });
});

// Suppression d'un événement
function deleteEvent(eventId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, supprimer !',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/calendrier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_event',
                    id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    window.calendarInstance.refetchEvents();
                    const el = document.getElementById('eventModal');
                    if (el && typeof bootstrap !== 'undefined') {
                        const inst = bootstrap.Modal.getInstance(el);
                        if (inst && typeof inst.hide === 'function') {
                            try { inst.hide(); } catch (e) { console.warn('Unable to hide eventModal', e); }
                        }
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de la suppression', 'error');
            });
        }
    });
}

// Mise à jour de la date d'un événement (drag & drop)
function updateEventDate(event) {
    const eventData = {
        action: 'update_event',
        id: event.id.replace('event_', ''),
        titre: event.title,
        type: event.extendedProps.event_type,
        description: event.extendedProps.description,
        start_date: event.start.toISOString().replace('T', ' ').substring(0, 19),
        end_date: event.end ? event.end.toISOString().replace('T', ' ').substring(0, 19) : event.start.toISOString().replace('T', ' ').substring(0, 19),
        employe_id: event.extendedProps.employe_id || null
    };
    
    fetch('api/calendrier.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(eventData)
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            showNotification('Erreur lors de la mise à jour', 'error');
            window.calendarInstance.refetchEvents(); // Recharger pour annuler le changement
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la mise à jour', 'error');
        window.calendarInstance.refetchEvents();
    });
}

// Création de la légende du calendrier
function createCalendarLegend(isAdmin) {
    const legendContainer = document.querySelector('.calendar-legend');
    if (!legendContainer) return;
    
    const legends = [
        { color: '#28a745', label: 'Jours de pointage' },
        { color: '#007bff', label: 'Réunions' },
        { color: '#dc3545', label: 'Congés' },
        { color: '#ffc107', label: 'Formations' },
        { color: '#6c757d', label: 'Autres événements' }
    ];
    
    if (!isAdmin) {
        legends.splice(1, 0, { color: '#17a2b8', label: 'Événements personnels' });
    }
    
    const legendHtml = legends.map(legend => `
        <div class="legend-item d-flex align-items-center me-3 mb-2">
            <div class="legend-color me-2" style="width: 15px; height: 15px; background-color: ${legend.color}; border-radius: 3px;"></div>
            <small class="text-muted">${legend.label}</small>
        </div>
    `).join('');
    
    legendContainer.innerHTML = `
        <div class="d-flex flex-wrap align-items-center">
            ${legendHtml}
        </div>
    `;
}

// Fonctions utilitaires
function formatDateTime(date) {
    return date.toLocaleString('fr-FR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatTime(dateTimeStr) {
    return new Date(dateTimeStr).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showNotification(message, type = 'info') {
    // Implémentation de votre système de notification
    console.log(`${type.toUpperCase()}: ${message}`);
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Pour l'admin
    if (document.getElementById('calendar-admin')) {
        initCalendar(true);
    }
    
    // Pour l'employé
    if (document.getElementById('calendar-employe')) {
        initCalendar(false);
    }
    
    // Bouton d'ajout d'événement
    document.getElementById('addEventBtn')?.addEventListener('click', function() {
        showEventModal();
    });
    
    // Bouton de suppression dans le modal
    document.getElementById('deleteEventBtn')?.addEventListener('click', function() {
        const eventId = document.getElementById('evt-id').value;
        if (eventId) {
            deleteEvent(eventId);
        }
    });
});