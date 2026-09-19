/**
 * assets/js/issues.js
 * ------------------------------------------------------------------
 * Powers modules/issues/index.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('issuesTableWrap');
  const filterForm = document.getElementById('filterForm');
  if (!wrap || !filterForm) return;

  const AJAX_URL = window.APP_URL + '/modules/issues/ajax_search.php';
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
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load issues right now.'); }
    });
    syncPrintLink();
  }

  function syncPrintLink() {
    const printLink = document.getElementById('printIssuesLink');
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
    wrap.querySelectorAll('.btn-edit-issue').forEach(btn => {
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
  const modalEl = document.getElementById('issueModal');
  if (!modalEl) return;

  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('issueForm');

  document.getElementById('btnAddIssue').addEventListener('click', function () {
    form.reset();
    document.getElementById('is_id').value = 0;
    document.getElementById('issueModalTitle').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Log Issue';
    modal.show();
  });

  function openEditModal(id) {
    appGet(window.APP_URL + '/modules/issues/ajax_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const iss = data.issue;
      form.reset();
      document.getElementById('is_id').value = iss.id;
      document.getElementById('is_title').value = iss.title || '';
      document.getElementById('is_description').value = iss.description || '';
      document.getElementById('is_category').value = iss.category_id || '';
      document.getElementById('is_hearing').value = iss.hearing_id || '';
      document.getElementById('is_priority').value = iss.priority || 'Medium';
      document.getElementById('is_status').value = iss.status || 'Open';
      document.getElementById('is_due_at').value = iss.due_at ? String(iss.due_at).replace(' ', 'T').slice(0, 16) : '';
      document.getElementById('is_office').value = iss.assigned_office_id || '';
      document.getElementById('issueModalTitle').innerHTML = '<i class="bi bi-pencil-square"></i> Edit Issue';
      modal.show();
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    appPost(window.APP_URL + '/modules/issues/ajax_save.php', Object.fromEntries(new FormData(form)))
      .then(data => {
        if (data.success) { modal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });
})();
