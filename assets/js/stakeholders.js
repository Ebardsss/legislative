/**
 * assets/js/stakeholders.js
 * ------------------------------------------------------------------
 * Powers modules/stakeholders/index.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('stakeholdersTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/modules/stakeholders/ajax_search.php';
  let currentSort = 'created_at';
  let currentDir = 'desc';
  let currentPage = 1;
  let searchTimer = null;
  let selectedIds = new Set();

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const category = document.getElementById('categoryFilter').value;
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (category) params.set('category_id', category);
    params.set('sort', currentSort);
    params.set('dir', currentDir);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load stakeholders right now.'); }
    });
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

    wrap.querySelectorAll('.btn-edit-stakeholder').forEach(btn => {
      btn.addEventListener('click', () => openEditModal(btn.getAttribute('data-id')));
    });

    wrap.querySelectorAll('.btn-set-status').forEach(btn => {
      btn.addEventListener('click', function () {
        const id = btn.getAttribute('data-id');
        const status = btn.getAttribute('data-status');
        appPost(window.APP_URL + '/modules/stakeholders/ajax_status.php', {
          id, status, csrf_token: window.APP_CSRF_TOKEN
        }).then(data => {
          if (data.success) { appToast('success', data.message); loadTable(); }
          else Swal.fire('Error', data.message, 'error');
        });
      });
    });

    // Checkbox selection for bulk invite
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
      checkAll.addEventListener('change', function () {
        wrap.querySelectorAll('.stakeholder-check').forEach(cb => {
          cb.checked = checkAll.checked;
          toggleSelected(cb.value, checkAll.checked);
        });
      });
    }
    wrap.querySelectorAll('.stakeholder-check').forEach(cb => {
      cb.checked = selectedIds.has(cb.value);
      cb.addEventListener('change', () => toggleSelected(cb.value, cb.checked));
    });
  }

  function toggleSelected(id, checked) {
    if (checked) selectedIds.add(id); else selectedIds.delete(id);
    document.getElementById('selectedCount').textContent = selectedIds.size;
    document.getElementById('btnBulkInvite').disabled = selectedIds.size === 0;
  }

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });
  document.getElementById('categoryFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });

  document.getElementById('btnBulkInvite').addEventListener('click', function () {
    sessionStorage.setItem('bulk_invite_ids', JSON.stringify(Array.from(selectedIds)));
    window.location.href = window.APP_URL + '/modules/stakeholders/invitations.php?bulk=1';
  });

  bindRowEvents();

  /* ================= Add / Edit Modal ================= */
  const modalEl = document.getElementById('stakeholderModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('stakeholderForm');

  document.getElementById('btnAddStakeholder').addEventListener('click', function () {
    form.reset();
    document.getElementById('s_id').value = 0;
    document.getElementById('stakeholderModalTitle').innerHTML = '<i class="bi bi-person-plus"></i> Add Stakeholder';
    modal.show();
  });

  function openEditModal(id) {
    appGet(window.APP_URL + '/modules/stakeholders/ajax_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const s = data.stakeholder;
      form.reset();
      document.getElementById('s_id').value = s.id;
      document.getElementById('s_full_name').value = s.full_name || '';
      document.getElementById('s_email').value = s.email || '';
      document.getElementById('s_phone').value = s.phone || '';
      document.getElementById('s_organization').value = s.organization || '';
      document.getElementById('s_category').value = s.category_id || '';
      document.getElementById('s_status').value = s.status || 'Pending';
      document.getElementById('stakeholderModalTitle').innerHTML = '<i class="bi bi-pencil-square"></i> Edit Stakeholder';
      modal.show();
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveStakeholder');
    btn.disabled = true;
    appPost(window.APP_URL + '/modules/stakeholders/ajax_save.php', Object.fromEntries(new FormData(form)))
      .then(data => {
        btn.disabled = false;
        if (data.success) { modal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });

  /* ================= CSV Import Modal ================= */
  const importModalEl = document.getElementById('importModal');
  const importModal = new bootstrap.Modal(importModalEl);
  const importForm = document.getElementById('importForm');

  document.getElementById('btnImportCsv').addEventListener('click', function () {
    importForm.reset();
    document.getElementById('importResult').innerHTML = '';
    importModal.show();
  });

  importForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnDoImport');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';

    appPostForm(window.APP_URL + '/modules/stakeholders/ajax_import_csv.php', importForm)
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> Import';
        if (data.session_expired) return;
        const resultEl = document.getElementById('importResult');
        if (data.success) {
          let html = `<div class="alert alert-success py-2">${data.message}</div>`;
          if (data.skip_reasons && data.skip_reasons.length) {
            html += '<div class="small text-muted">' + data.skip_reasons.join('<br>') + '</div>';
          }
          resultEl.innerHTML = html;
          loadTable();
        } else {
          resultEl.innerHTML = `<div class="alert alert-danger py-2">${data.message}</div>`;
        }
      });
  });
})();
