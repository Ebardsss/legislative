'use strict';
(function(){
  try {
    const isCollapsed = localStorage.getItem('cepfms_sidebar_collapsed') === '1' || localStorage.getItem('orlms_sidebar_collapsed') === '1';
    if (isCollapsed && window.innerWidth > 1050) {
      document.body.classList.add('sidebar-collapsed');
    }
  } catch(e){}
})();

document.addEventListener('DOMContentLoaded', function(){
  function closeMobile(){
    const sidebar = document.getElementById('orlmsSidebar') || document.getElementById('sidebar');
    const backdrop = document.getElementById('orlmsSidebarBackdrop') || document.getElementById('sidebarBackdrop');
    sidebar?.classList.remove('open');
    backdrop?.classList.remove('show');
  }

  function handleToggle(e){
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    const sidebar = document.getElementById('orlmsSidebar') || document.getElementById('sidebar');
    const backdrop = document.getElementById('orlmsSidebarBackdrop') || document.getElementById('sidebarBackdrop');

    if (window.innerWidth <= 1050) {
      sidebar?.classList.toggle('open');
      backdrop?.classList.toggle('show');
    } else {
      document.body.classList.toggle('sidebar-collapsed');
      const active = document.body.classList.contains('sidebar-collapsed');
      try {
        localStorage.setItem('cepfms_sidebar_collapsed', active ? '1' : '0');
        localStorage.setItem('orlms_sidebar_collapsed', active ? '1' : '0');
      } catch(ex){}
    }
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('#orlmsCollapseToggle, #orlmsSidebarToggle, #sidebarToggle, .orlms-collapse-toggle, .orlms-menu-button, .menu-button, .topbar-menu-button');
    if (btn) {
      handleToggle(e);
    }
  });

  const backdrop = document.getElementById('orlmsSidebarBackdrop') || document.getElementById('sidebarBackdrop');
  backdrop?.addEventListener('click', closeMobile);

  window.addEventListener('resize', function(){
    if (window.innerWidth > 1050) {
      closeMobile();
    }
  });
});
