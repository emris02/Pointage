<?php
// Partial: notification bell + dropdown
// Expects session to be available
?>
<div class="dropdown me-3 d-inline-block">
    <style>
        /* Notification bell small improvements */
        .notification-dropdown { min-width: 320px; max-width: 420px; }
        .notif-badge-pulse { animation: pulse 1s ease-in-out 2; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.12); } 100% { transform: scale(1); } }
        .notif-item-icon { width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center }
        .notif-item .small { display:block }
    </style>

    <button class="btn btn-outline-secondary rounded-circle position-relative p-2" id="notifToggle" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications">
        <i class="fas fa-bell fa-lg"></i>
        <span id="notifCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end shadow-lg notification-dropdown" aria-labelledby="notifToggle">
        <li class="dropdown-header px-3 d-flex justify-content-between align-items-center">
            <div><i class="fas fa-bell me-2"></i>Notifications</div>
            <small class="text-muted" id="notifLastFetch">--</small>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li id="notifItems" class="px-0">
            <div class="p-3 text-center text-muted">Chargement...</div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li class="px-3 py-2">
            <div class="d-flex gap-2">
                <button id="notifMarkAll" class="btn btn-sm btn-outline-primary w-100">Marquer toutes lues</button>
                <a href="notifications.php" class="btn btn-sm btn-primary w-100">Voir tout</a>
            </div>
        </li>
    </ul>
</div>

<script>
(function(){
    // Choose API based on server-side session role
    const isAdmin = <?= (!empty($_SESSION['admin_id']) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','super_admin']))) ? 'true' : 'false' ?>;
    const url = isAdmin ? 'api/admin_notifications.php' : 'api/notifications.php';
    let lastItems = [];

    let lastCount = 0;
    async function fetchNotifs() {
        try {
            const r = await fetch(url + (isAdmin ? '?action=list&limit=20' : ''));
            const data = await r.json();
            if (!data.success) return;

            // Normalize response for admin vs employee
            let items = [];
            let count = 0;
            if (isAdmin) {
                items = data.data || [];
                count = (data.stats && data.stats.unread) ? data.stats.unread : (data.stats && data.stats.total ? data.stats.total : 0);
            } else {
                items = data.items || [];
                count = data.unread || 0;
            }

            const badge = document.getElementById('notifCountBadge');

            if (count > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = count;
                // Add pulse when count increases
                if (count > lastCount) {
                    badge.classList.add('notif-badge-pulse');
                    setTimeout(() => badge.classList.remove('notif-badge-pulse'), 1200);
                }
            } else {
                badge.style.display = 'none';
            }

            const itemsEl = document.getElementById('notifItems');
            itemsEl.innerHTML = '';

            if (!items || items.length === 0) {
                itemsEl.innerHTML = '<div class="p-3 text-center text-muted">Aucune notification</div>';
            } else {
                // For admin API items may use 'message' and 'notification_type'
                items.slice(0,6).forEach(notif => {
                    const a = document.createElement('a');
                    a.href = notif.lien || notif.link || '#';
                    a.className = 'dropdown-item d-flex align-items-start gap-2 notif-item';

                    const title = notif.titre || notif.title || notif.message || (notif.prenom ? `${notif.prenom} ${notif.nom}` : 'Notification');
                    const content = notif.contenu || notif.contenu || notif.message || '';
                    const dateFormatted = notif.date ? new Date(notif.date).toLocaleString() : (notif.date_formatted || '');
                    const typeIndicator = notif.notification_type || notif.type || 'pointage';

                    a.innerHTML = `
                        <div class="notif-item-icon me-2 bg-${typeIndicator === 'retard' ? 'warning' : (typeIndicator === 'demande' ? 'info' : 'primary')} text-white"><i class="fas ${typeIndicator === 'retard' ? 'fa-clock' : (typeIndicator === 'demande' ? 'fa-file-alt' : 'fa-bell')}"></i></div>
                        <div class="flex-grow-1">
                            <div class="small fw-semibold mb-1" title="${title}">${title}</div>
                            <div class="small text-muted text-truncate">${content}</div>
                            <div class="small text-muted mt-1">${dateFormatted}</div>
                        </div>
                    `;

                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        // mark single read via API (fire-and-forget) - use correct action names
                        if (isAdmin) {
                            fetch(url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'read', id: notif.id || notif.id, notification_type: notif.notification_type || notif.type }))
                                .finally(() => fetchNotifs());
                        } else {
                            fetch(url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'mark_as_read', notification_id: notif.id || notif.id })})
                                .finally(() => fetchNotifs());
                        }

                        // follow link if present
                        const link = notif.lien || notif.link || '#';
                        if (link && link !== '#') window.location.href = link;
                    });
                    itemsEl.appendChild(a);
                });
            }

            // Desktop notification for new item(s)
            if (window.Notification && Notification.permission === 'granted') {
                const newOnes = items.filter(i => !lastItems.find(li => li.id === i.id));
                newOnes.slice(0,3).forEach(i => {
                    const title = i.titre || i.title || i.message || (i.prenom ? `${i.prenom} ${i.nom}` : 'Notification');
                    const content = i.contenu || i.message || '';
                    new Notification(title, { body: content, icon: '/assets/img/notification.png' });
                });
            }

            lastItems = items;
            lastCount = count;

            // Update last fetch time
            const lastEl = document.getElementById('notifLastFetch');
            lastEl.textContent = new Date().toLocaleTimeString();

        } catch (e) {
            console.error('Erreur notifications:', e);
        }
    }

    document.getElementById('notifMarkAll').addEventListener('click', async function(){
        if (isAdmin) {
            await fetch(url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'mark_all_read', notification_type: 'all' }) });
        } else {
            await fetch(url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'mark_all_as_read' }) });
        }
        fetchNotifs();
    });

    // Request permission for desktop notifications on first use
    if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Initial fetch and polling
    fetchNotifs();
    setInterval(fetchNotifs, 15000); // every 15s
})();
</script>