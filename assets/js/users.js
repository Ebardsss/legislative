/**
 * assets/js/users.js
 * ------------------------------------------------------------------
 * Powers pages/users.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('usersTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/pages/ajax_users_search.php';
  let currentSort = 'full_name';
  let currentDir = 'asc';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    if (search) params.set('search', search);
    if (role) params.set('role_id', role);
    if (status) params.set('status', status);
    params.set('sort', currentSort);
    params.set('dir', currentDir);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load users right now.'); }
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
    wrap.querySelectorAll('.btn-edit-user').forEach(btn => {
      btn.addEventListener('click', () => openEditModal(btn.getAttribute('data-id')));
    });
  }

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  document.getElementById('roleFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });
  document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });

  bindRowEvents();

  /* ================= Add / Edit Modal ================= */
  const modalEl = document.getElementById('userModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('userForm');
  const passwordInput = document.getElementById('u_password');
  const passwordRequiredMark = document.getElementById('u_password_required');
  const passwordHint = document.getElementById('u_password_hint');

  function setPasswordMode(isCreate) {
    if (isCreate) {
      passwordInput.setAttribute('required', 'required');
      passwordRequiredMark.style.display = 'inline';
      passwordHint.textContent = 'Minimum 8 characters.';
    } else {
      passwordInput.removeAttribute('required');
      passwordRequiredMark.style.display = 'none';
      passwordHint.textContent = 'Leave blank to keep the current password.';
    }
  }

  document.getElementById('btnAddUser').addEventListener('click', function () {
    form.reset();
    document.getElementById('u_id').value = 0;
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus"></i> Add User';
    setPasswordMode(true);
    modal.show();
  });

  function openEditModal(id) {
    appGet(window.APP_URL + '/pages/ajax_user_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const u = data.user;
      form.reset();
      document.getElementById('u_id').value = u.id;
      document.getElementById('u_full_name').value = u.full_name || '';
      document.getElementById('u_email').value = u.email || '';
      document.getElementById('u_role').value = u.role_id || '';
      document.getElementById('u_status').value = u.status || 'Active';
      document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil-square"></i> Edit User';
      setPasswordMode(false);
      modal.show();
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    appPost(window.APP_URL + '/pages/ajax_user_save.php', Object.fromEntries(new FormData(form)))
      .then(data => {
        if (data.success) { modal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });
})();
