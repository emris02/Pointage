// === Calendrier Admin (FullCalendar) ===
document.addEventListener('DOMContentLoaded', function() {
  // Initialisation du calendrier
  initCalendar();
  
  // Gestion des avatars avec fallback
  initAvatarFallback();
});

// Initialisation du calendrier FullCalendar
function initCalendar() {
  const calendarEl = document.getElementById('calendar-admin');
  if (!calendarEl) {
    console.warn('Élément calendar-admin non trouvé');
    return;
  }

  const loading = document.getElementById('calendar-loading');
  const eventModalEl = document.getElementById('eventModal');
  const form = document.getElementById('eventForm');
  const deleteBtn = document.getElementById('deleteEventBtn');

  if (!eventModalEl) {
    console.error('Modal eventModal non trouvé');
    return;
  }

  const eventModal = new bootstrap.Modal(eventModalEl);

  // Helper pour toggle spinner
  const setLoading = (state) => {
    if (loading) loading.style.display = state ? 'block' : 'none';
  };

  // Ensure FullCalendar library is available
  if (typeof FullCalendar === 'undefined' && typeof window.FullCalendar === 'undefined') {
    console.error('FullCalendar library non chargée');
    if (calendarEl) {
      calendarEl.innerHTML = '<div class="alert alert-warning">Calendrier indisponible : FullCalendar non chargé.</div>';
    }
    return;
  }

  // Initialisation FullCalendar
  const calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    editable: true,
    selectable: true,
    locale: 'fr',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },
    events: async (info, successCallback, failureCallback) => {
      try {
        setLoading(true);
        const params = new URLSearchParams({ 
          start: info.startStr, 
          end: info.endStr 
        });
        const res = await fetch('api/get_events.php?' + params.toString());
        
        if (!res.ok) {
          throw new Error(`Erreur HTTP: ${res.status}`);
        }
        
        const data = await res.json();
        successCallback(data);
      } catch (e) {
        console.error('Erreur lors du chargement des événements:', e);
        failureCallback(e);
        showToast('Erreur lors du chargement du calendrier', 'error');
      } finally {
        setLoading(false);
      }
    },
    dateClick: (info) => {
      openEventModal(null, info.dateStr);
    },
    eventClick: (info) => {
      const e = info.event;
      openEventModal(e);
    },
    eventDrop: async (info) => {
      try {
        const payload = {
          action: 'update',
          id: info.event.id,
          start_date: info.event.startStr,
          end_date: info.event.endStr
        };
        
        const res = await fetch('api/save_event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        
        if (!res.ok) {
          throw new Error(`Erreur HTTP: ${res.status}`);
        }
        
        const result = await res.json();
        if (result.success) {
          showToast('Événement déplacé avec succès', 'success');
        } else {
          throw new Error(result.message || 'Erreur serveur');
        }
      } catch (e) {
        console.error('Erreur lors du déplacement de l\'événement:', e);
        info.revert();
        showToast('Erreur lors du déplacement: ' + e.message, 'error');
      }
    },
    eventResize: async (info) => {
      try {
        const payload = {
          action: 'update',
          id: info.event.id,
          start_date: info.event.startStr,
          end_date: info.event.endStr
        };
        
        const res = await fetch('api/save_event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        
        if (!res.ok) {
          throw new Error(`Erreur HTTP: ${res.status}`);
        }
        
        const result = await res.json();
        if (result.success) {
          showToast('Événement modifié avec succès', 'success');
        } else {
          throw new Error(result.message || 'Erreur serveur');
        }
      } catch (e) {
        console.error('Erreur lors de la modification de l\'événement:', e);
        info.revert();
        showToast('Erreur lors de la modification: ' + e.message, 'error');
      }
    }
  });

  calendar.render();

  // Re-render quand le panel calendrier devient visible
  window.addEventListener('panel:shown', (e) => {
    if (e.detail?.panelId === 'calendrier') {
      setTimeout(() => {
        try {
          calendar.updateSize();
        } catch (err) {
          console.error('Erreur lors du redimensionnement du calendrier:', err);
        }
      }, 100);
    }
  });

  // Ajout via bouton
  const addBtn = document.getElementById('addEventBtn');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      openEventModal(null);
    });
  }

  // Soumission formulaire (create/update)
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      if (!validateEventForm()) {
        return;
      }
      
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      payload.action = payload.id ? 'update' : 'create';

      try {
        setLoading(true);
        
        const res = await fetch('api/save_event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        
        if (!res.ok) {
          throw new Error(`Erreur HTTP: ${res.status}`);
        }
        
        const result = await res.json();
        
        if (result.success) {
          eventModal.hide();
          calendar.refetchEvents();
          showToast(payload.action === 'create' ? 'Événement créé avec succès' : 'Événement modifié avec succès', 'success');
        } else {
          showToast(result.message || 'Erreur lors de l\'opération', 'error');
        }
      } catch (err) {
        console.error('Erreur lors de la soumission:', err);
        showToast('Erreur de connexion au serveur', 'error');
      } finally {
        setLoading(false);
      }
    });
  }

  // Suppression
  if (deleteBtn) {
    deleteBtn.addEventListener('click', async () => {
      const id = document.getElementById('evt-id')?.value;
      if (!id) return;
      
      if (!confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
        return;
      }
      
      try {
        setLoading(true);
        
        const res = await fetch('api/save_event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id })
        });
        
        if (!res.ok) {
          throw new Error(`Erreur HTTP: ${res.status}`);
        }
        
        const result = await res.json();
        
        if (result.success) {
          eventModal.hide();
          calendar.refetchEvents();
          showToast('Événement supprimé avec succès', 'success');
        } else {
          showToast(result.message || 'Erreur lors de la suppression', 'error');
        }
      } catch (err) {
        console.error('Erreur lors de la suppression:', err);
        showToast('Erreur de connexion au serveur', 'error');
      } finally {
        setLoading(false);
      }
    });
  }

  // Fonction pour ouvrir le modal d'événement
  function openEventModal(event = null, dateStr = null) {
    if (!form) return;
    
    form.reset();
    
    const evtIdInput = document.getElementById('evt-id');
    const evtTitleInput = document.getElementById('evt-title');
    const evtTypeInput = document.getElementById('evt-type');
    const evtStartInput = document.getElementById('evt-start');
    const evtEndInput = document.getElementById('evt-end');
    const evtDescInput = document.getElementById('evt-desc');
    const evtEmployeIdInput = document.getElementById('evt-employe-id');
    const modalLabel = document.getElementById('eventModalLabel');
    
    if (event) {
      // Mode édition
      if (evtIdInput) evtIdInput.value = event.id;
      if (evtTitleInput) evtTitleInput.value = event.title;
      if (evtTypeInput) evtTypeInput.value = (event.extendedProps.type || 'autre');
      if (evtStartInput) evtStartInput.value = event.startStr?.replace('Z','') || '';
      if (evtEndInput) evtEndInput.value = event.endStr?.replace('Z','') || '';
      if (evtDescInput) evtDescInput.value = event.extendedProps.description || '';
      if (evtEmployeIdInput) evtEmployeIdInput.value = event.extendedProps.employe_id || '';
      if (deleteBtn) deleteBtn.classList.remove('d-none');
      if (modalLabel) modalLabel.innerText = 'Modifier l\'événement';
    } else {
      // Mode création
      if (evtIdInput) evtIdInput.value = '';
      const now = new Date();
      const defaultStart = dateStr ? `${dateStr}T09:00` : formatDateTimeLocal(now);
      const defaultEnd = dateStr ? `${dateStr}T10:00` : formatDateTimeLocal(new Date(now.getTime() + 60 * 60 * 1000));
      
      if (evtStartInput) evtStartInput.value = defaultStart;
      if (evtEndInput) evtEndInput.value = defaultEnd;
      if (deleteBtn) deleteBtn.classList.add('d-none');
      if (modalLabel) modalLabel.innerText = 'Nouvel événement';
    }
    
    eventModal.show();
  }

  // Validation du formulaire d'événement
  function validateEventForm() {
    const titleInput = document.getElementById('evt-title');
    const startInput = document.getElementById('evt-start');
    const endInput = document.getElementById('evt-end');
    
    if (!titleInput || !startInput || !endInput) {
      showToast('Formulaire incomplet', 'error');
      return false;
    }
    
    const title = titleInput.value.trim();
    const start = startInput.value;
    const end = endInput.value;
    
    if (!title) {
      showToast('Veuillez saisir un titre', 'warning');
      titleInput.focus();
      return false;
    }
    
    if (!start || !end) {
      showToast('Veuillez saisir les dates de début et de fin', 'warning');
      return false;
    }
    
    const startDate = new Date(start);
    const endDate = new Date(end);
    
    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
      showToast('Dates invalides', 'warning');
      return false;
    }
    
    if (endDate <= startDate) {
      showToast('La date de fin doit être après la date de début', 'warning');
      return false;
    }
    
    return true;
  }

  // Formatage de date pour input datetime-local
  function formatDateTimeLocal(date) {
    if (!(date instanceof Date) || isNaN(date.getTime())) {
      date = new Date();
    }
    const pad = (n) => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }
}

// Gestion des avatars avec fallback
function initAvatarFallback() {
  function replaceBrokenAvatar(img) {
    try {
      const alt = img.getAttribute('alt') || '';
      const name = alt.trim();
      let initials = 'N/A';
      
      if (name) {
        const parts = name.split(/\s+/).filter(Boolean);
        initials = (parts[0] ? parts[0][0] : '') + (parts[1] ? parts[1][0] : '');
        initials = initials.toUpperCase();
      }
      
      const div = document.createElement('div');
      div.className = img.className || '';
      div.style.width = img.width ? img.width + 'px' : '40px';
      div.style.height = img.height ? img.height + 'px' : '40px';
      div.style.display = 'inline-flex';
      div.style.alignItems = 'center';
      div.style.justifyContent = 'center';
      div.style.borderRadius = '50%';
      div.style.background = '#6c757d';
      div.style.color = '#fff';
      div.style.fontWeight = '700';
      div.textContent = initials;
      img.replaceWith(div);
    } catch (e) {
      console.debug('Erreur lors du remplacement de l\'avatar:', e);
    }
  }

  // Appliquer le fallback aux images d'avatar
  document.querySelectorAll('img.avatar, img.notification-avatar, img.rounded-circle, img[class*="avatar"]').forEach(img => {
    img.addEventListener('error', () => replaceBrokenAvatar(img));
    
    // Vérifier si l'image est déjà cassée
    if (img.complete && img.naturalWidth === 0) {
      replaceBrokenAvatar(img);
    }
  });
}

// Fonction utilitaire pour afficher des notifications toast
function showToast(message, type = 'info') {
  // Vérifier si Bootstrap est disponible
  if (typeof bootstrap === 'undefined') {
    console.log(`${type.toUpperCase()}: ${message}`);
    return;
  }

  // Créer un toast dynamique
  const toastId = `toast-${Date.now()}`;
  const bgClass = {
    success: 'bg-success text-white',
    error: 'bg-danger text-white',
    warning: 'bg-warning text-dark',
    info: 'bg-info text-white'
  }[type] || 'bg-info text-white';

  const toastHtml = `
    <div id="${toastId}" class="toast align-items-center ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
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
    container.style.zIndex = '1055';
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
}