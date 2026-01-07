document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleSidebar');
    const burgerBtn = document.getElementById('burger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const content = document.querySelector('.main-content');
    const storageKey = 'sidebarCollapsed';

    // Initialize from localStorage
    try {
        const collapsed = localStorage.getItem(storageKey) === 'true';
        if (collapsed && sidebar && content) {
            sidebar.classList.add('collapsed');
            content.classList.add('sidebar-collapsed');
        }
    } catch (e) {
        // ignore storage errors
    }

    // Toggle handler (shared for desktop/mobile)
    function handleToggle() {
        if (!sidebar) return;
        const isMobile = window.innerWidth < 992;

        if (isMobile) {
            sidebar.classList.toggle('show');
            overlay?.classList.toggle('active');
        } else {
            const nowCollapsed = sidebar.classList.toggle('collapsed');
            content?.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem(storageKey, String(nowCollapsed)); } catch (e) {}
        }
    }

    toggleBtn?.addEventListener('click', handleToggle);
    burgerBtn?.addEventListener('click', () => {
        // burger (small screens) opens sidebar
        sidebar?.classList.toggle('show');
        overlay?.classList.toggle('active');
    });

    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('show');
        overlay?.classList.remove('active');
    });

    // Ensure mobile/desktop state sync on resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            overlay?.classList.remove('active');
            sidebar?.classList.remove('show');
        }
    });
});
