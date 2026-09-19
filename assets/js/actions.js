/**
 * assets/js/actions.js
 * ------------------------------------------------------------------
 * Powers modules/actions/index.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('actionsTableWrap');
  const filterForm = document.getElementById('filterForm');
  if (!wrap || !filterForm) return;

  const AJAX_URL = window.APP_URL + '/modules/actions/ajax_search.php';
  let currentSort = 'created_at';
  let currentDir = 'desc';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const data = new FormData(filterForm);
    const params = new URLSearchParams();
    for (const [key, val] of data.entries()) { if (val !== '') params.append(key, val); }
    params.set('sort', currentSort);
    params.set('dir', currentDir);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load actions right now.'); }
    });
    syncPrintLink();
  }

  function syncPrintLink() {
    const printLink = document.getElementById('printActionsLink');
    if (!printLink) return;
    const data = new FormData(filterForm);
    const params = new URLSearchParams();
    for (const [key, val] of data.entries()) { if (val !== '' && key !== 'search') params.append(key, val); }
    printLink.href = 'print.php' + (params.toString() ? '?' + params.toString() : '');
  }

  function bindRowEvents() {
    wrap.querySelectorAll('.sort-link').forEach(el => {
      el.style.cursor = 'pointer';
      el.addEventListener('click', function () {
        const col = el.getAttribute('data-sort');
        currentDir = (currentSort === col && currentDir === 'asc') ? 'desc' : 'asc';
        currentSort = col;
        currentPage = 1;
        loadTable();
      });
    });
    wrap.querySelectorAll('.pagination a.page-link').forEach(a => {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        currentPage = parseInt(new URL(a.href).searchParams.get('page') || '1', 10);
        loadTable({ page: currentPage });
      });
    });
    wrap.querySelectorAll('.btn-edit-action').forEach(btn => {
      btn.addEventListener('click', () => openEditModal(btn.getAttribute('data-id')));
    });
  }

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  filterForm.addEventListener('input', function (e) {
    if (e.target.id === 'searchInput') {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
    }
  });
  filterForm.addEventListener('change', function (e) {
    if (e.target.id !== 'searchInput') { currentPage = 1; loadTable(); }
  });

  bindRowEvents();

  /* ================= Create / Edit Modal ================= */
  const modalEl = document.getElementById('actionModal');
  if (!modalEl) return;

  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('actionForm');

  document.getElementById('btnAddAction').addEventListener('click', function () {
    form.reset();
    document.getElementById('ac_id').value = 0;
    document.getElementById('actionModalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Create Action';
    modal.show();
  });

  function openEditModal(id) {
    appGet(window.APP_URL + '/modules/actions/ajax_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const a = data.action;
      form.reset();
      document.getElementById('ac_id').value = a.id;
      document.getElementById('ac_title').value = a.title || '';
      document.getElementById('ac_description').value = a.description || '';
      document.getElementById('ac_issue').value = a.issue_id || '';
      document.getElementById('ac_deadline').value = a.deadline || '';
      document.getElementById('ac_status').value = a.status || 'Pending';
      document.getElementById('ac_office').value = a.assigned_office || a.assigned_user || '';
      document.getElementById('actionModalTitle').innerHTML = '<i class="bi bi-pencil-square"></i> Edit Action';
      modal.show();
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    appPostForm(window.APP_URL + '/modules/actions/ajax_save.php', form)
      .then(data => {
        if (data.success) { modal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });
})();
