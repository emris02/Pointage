// Calendar Admin JavaScript
class CalendarManager {
    constructor() {
        this.calendar = null;
        this.calendarFull = null;
        this.events = [];
        this.init();
    }

    init() {
        this.loadEvents();
        this.initCalendars();
        this.bindEvents();
    }

    loadEvents() {
        // Charger les événements depuis la base de données
        fetch('get_events.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.events = data.events;
                    this.renderEvents();
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des événements:', error);
            });
    }

    initCalendars() {
        const calendarEl = document.getElementById('calendar');
        const calendarFullEl = document.getElementById('calendar-full');

        if (calendarEl) {
            this.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
                height: 'auto',
                aspectRatio: 1.35,
                dayMaxEvents: 3,
                moreLinkClick: 'popover',
                eventDisplay: 'block',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventDidMount: (info) => {
                    this.addEventTooltip(info);
                },
                eventClick: (info) => {
                    this.showEventDetails(info.event);
                },
                dateClick: (info) => {
                    this.showAddEventModal(info.dateStr);
                },
                eventDrop: (info) => {
                    this.updateEventDate(info.event);
                },
                eventResize: (info) => {
                    this.updateEventDuration(info.event);
                }
            });
            this.calendar.render();
        }

        if (calendarFullEl) {
            this.calendarFull = new FullCalendar.Calendar(calendarFullEl, {
                initialView: 'dayGridMonth',
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                height: 'auto',
                aspectRatio: 1.8,
                dayMaxEvents: 5,
                moreLinkClick: 'popover',
                eventDisplay: 'block',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventDidMount: (info) => {
                    this.addEventTooltip(info);
                },
                eventClick: (info) => {
                    this.showEventDetails(info.event);
                },
                dateClick: (info) => {
                    this.showAddEventModal(info.dateStr);
                },
                eventDrop: (info) => {
                    this.updateEventDate(info.event);
                },
                eventResize: (info) => {
                    this.updateEventDuration(info.event);
                }
            });
            this.calendarFull.render();
        }
    }

    renderEvents() {
        const events = this.events.map(event => ({
            id: event.id,
            title: event.titre,
            start: event.date_evenement,
            className: `event-${event.type.toLowerCase()}`,
            extendedProps: {
                type: event.type,
                description: event.description,
                created_at: event.created_at
            }
        }));

        if (this.calendar) {
            this.calendar.removeAllEvents();
            this.calendar.addEventSource(events);
        }

        if (this.calendarFull) {
            this.calendarFull.removeAllEvents();
            this.calendarFull.addEventSource(events);
        }
    }

    addEventTooltip(info) {
        const event = info.event;
        const tooltip = document.createElement('div');
        tooltip.className = 'event-tooltip';
        tooltip.innerHTML = `
            <div class="tooltip-header">
                <strong>${event.title}</strong>
                <span class="badge event-${event.extendedProps.type.toLowerCase()}">${event.extendedProps.type}</span>
            </div>
            <div class="tooltip-body">
                ${event.extendedProps.description || 'Aucune description'}
            </div>
            <div class="tooltip-footer">
                <small>${this.formatDate(event.start)}</small>
            </div>
        `;

        // Ajouter le tooltip à l'événement
        info.el.setAttribute('data-bs-toggle', 'tooltip');
        info.el.setAttribute('data-bs-html', 'true');
        info.el.setAttribute('data-bs-title', tooltip.outerHTML);
        
        // Initialiser le tooltip Bootstrap
        new bootstrap.Tooltip(info.el);
    }

    showEventDetails(event) {
        const modalEl = document.getElementById('eventDetailsModal');
        const modalBody = document.getElementById('eventDetailsBody');
        if (!modalEl || typeof bootstrap === 'undefined') {
            console.warn('eventDetailsModal not present or bootstrap missing — falling back to alert');
            Swal.fire({ title: event.title || 'Détails', html: event.extendedProps?.description || 'Aucune description', icon: 'info' });
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        
        modalBody.innerHTML = `
            <div class="event-details">
                <div class="event-header mb-3">
                    <h5 class="mb-2">${event.title}</h5>
                    <span class="badge event-${event.extendedProps.type.toLowerCase()}">${event.extendedProps.type}</span>
                </div>
                <div class="event-info">
                    <p><strong>Date:</strong> ${this.formatDate(event.start)}</p>
                    <p><strong>Description:</strong> ${event.extendedProps.description || 'Aucune description'}</p>
                    <p><strong>Créé le:</strong> ${this.formatDate(event.extendedProps.created_at)}</p>
                </div>
                <div class="event-actions mt-3">
                    <button class="btn btn-primary btn-sm" onclick="calendarManager.editEvent(${event.id})">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="calendarManager.deleteEvent(${event.id})">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
            </div>
        `;
        
        modal.show();
    }

    showAddEventModal(dateStr = null) {
        const addEl = document.getElementById('addEventModal');
        if (!addEl || typeof bootstrap === 'undefined') {
            console.warn('addEventModal not present or bootstrap missing — cannot open modal');
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(addEl);
        if (dateStr) {
            const dateInput = document.getElementById('eventDate');
            if (dateInput) dateInput.value = dateStr;
        }
        try { modal.show(); } catch (e) { console.warn('Unable to show addEventModal', e); }
    }

    updateEventDate(event) {
        const newDate = event.start.toISOString().slice(0, 19).replace('T', ' ');
        
        fetch('update_event.php', {
                    method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: event.id,
                date_evenement: newDate
            })
        })
        .then(response => response.json())
                .then(data => {
            if (data.success) {
                this.showNotification('Événement mis à jour avec succès', 'success');
            } else {
                this.showNotification('Erreur lors de la mise à jour', 'error');
                this.loadEvents(); // Recharger pour annuler les changements
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.showNotification('Erreur lors de la mise à jour', 'error');
            this.loadEvents();
        });
    }

    updateEventDuration(event) {
        const startDate = event.start.toISOString().slice(0, 19).replace('T', ' ');
        const endDate = event.end ? event.end.toISOString().slice(0, 19).replace('T', ' ') : null;
        
        fetch('update_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: event.id,
                date_evenement: startDate,
                date_fin: endDate
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Événement mis à jour avec succès', 'success');
            } else {
                this.showNotification('Erreur lors de la mise à jour', 'error');
                this.loadEvents();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.showNotification('Erreur lors de la mise à jour', 'error');
            this.loadEvents();
        });
    }

    editEvent(eventId) {
        // Implémenter l'édition d'événement
        console.log('Éditer événement:', eventId);
    }

    deleteEvent(eventId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
            fetch('delete_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('Événement supprimé avec succès', 'success');
                    this.loadEvents();
                } else {
                    this.showNotification('Erreur lors de la suppression', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                this.showNotification('Erreur lors de la suppression', 'error');
            });
        }
    }

    bindEvents() {
        // Gestion du formulaire d'ajout d'événement
        const addEventForm = document.getElementById('addEventForm');
        if (addEventForm) {
            addEventForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitEventForm(addEventForm);
            });
        }

        // Gestion du changement de type d'événement
        const eventTypeSelect = document.getElementById('eventType');
        if (eventTypeSelect) {
            eventTypeSelect.addEventListener('change', (e) => {
                this.updateEventTypePreview(e.target.value);
            });
        }
    }

    submitEventForm(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Afficher l'état de chargement
        submitBtn.innerHTML = '<span class="loading"></span> Ajout en cours...';
        submitBtn.disabled = true;

        fetch('add_event.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Événement ajouté avec succès', 'success');
                form.reset();
                const addEl = document.getElementById('addEventModal');
                if (addEl && typeof bootstrap !== 'undefined') {
                    const inst = bootstrap.Modal.getInstance(addEl);
                    if (inst && typeof inst.hide === 'function') {
                        try { inst.hide(); } catch (e) { console.warn('Unable to hide addEventModal', e); }
                    }
                }
                this.loadEvents();
            } else {
                this.showNotification('Erreur: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            this.showNotification('Erreur lors de l\'ajout de l\'événement', 'error');
        })
        .finally(() => {
            // Restaurer le bouton
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    updateEventTypePreview(type) {
        const preview = document.getElementById('eventTypePreview');
        if (preview) {
            const colors = {
                'réunion': '#1976d2',
                'congé': '#7b1fa2',
                'formation': '#388e3c',
                'autre': '#f57c00'
            };
            
            preview.style.backgroundColor = colors[type] || '#666';
            preview.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        }
    }

    formatDate(date) {
        if (!date) return '';
        
        const d = new Date(date);
        return d.toLocaleDateString('fr-FR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    showNotification(message, type = 'info') {
        // Créer une notification toast
        const toastContainer = document.getElementById('toastContainer') || this.createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Supprimer le toast après qu'il soit caché
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }
}

// Initialiser le gestionnaire de calendrier
let calendarManager;

document.addEventListener('DOMContentLoaded', function() {
    calendarManager = new CalendarManager();
    
    // Ajouter des styles CSS pour les tooltips personnalisés
    const style = document.createElement('style');
    style.textContent = `
        .event-tooltip {
            background: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 250px;
        }
        
        .tooltip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .tooltip-body {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        
        .tooltip-footer {
            font-size: 0.8rem;
            color: #999;
        }
        
        .fc-event {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .fc-event:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
});
