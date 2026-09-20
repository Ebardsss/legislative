<?php
declare(strict_types=1);
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    'use strict';

    function getSidebar() {
        return document.getElementById('sidebar') || document.querySelector('.sidebar') || document.querySelector('.orlms-sidebar');
    }

    function getBackdrop() {
        return document.getElementById('sidebarBackdrop') || document.querySelector('.sidebar-backdrop') || document.querySelector('.orlms-sidebar-backdrop');
    }

    function closeMobile() {
        const sidebar = getSidebar();
        const backdrop = getBackdrop();
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
    }

    function handleToggle(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const sidebar = getSidebar();
        const backdrop = getBackdrop();

        if (window.innerWidth <= 1050) {
            if (sidebar) sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('show');
        } else {
            const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
            try {
                localStorage.setItem('citizen_sidebar_collapsed', isCollapsed ? '1' : '0');
            } catch (err) {}
        }
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#sidebarToggle, #orlmsSidebarToggle, .orlms-menu-button, .menu-button, [data-sidebar-toggle]');
        if (btn) {
            handleToggle(e);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const backdrop = getBackdrop();
        if (backdrop) {
            backdrop.addEventListener('click', closeMobile);
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 1050) {
            closeMobile();
        }
    });
})();
</script>
</body>
</html>
