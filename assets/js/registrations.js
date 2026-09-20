/**
 * assets/js/registrations.js
 * ------------------------------------------------------------------
 * Powers modules/stakeholders/registrations.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('registrationsTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/modules/stakeholders/ajax_search_registrations.php';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const hearing = document.getElementById('hearingFilter').value;
    if (search) params.set('search', search);
    if (hearing) params.set('hearing_id', hearing);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load registrations right now.'); }
    });
  }

  function bindRowEvents() {
    wrap.querySelectorAll('.pagination a.page-link').forEach(a => {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        currentPage = parseInt(new URL(a.href).searchParams.get('page') || '1', 10);
        loadTable({ page: currentPage });
      });
    });
  }

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  document.getElementById('hearingFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });

  bindRowEvents();

  /* ================= Add Registration Modal ================= */
  const modalEl = document.getElementById('registrationModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('registrationForm');

  document.getElementById('btnAddRegistration').addEventListener('click', function () {
    form.reset();
    modal.show();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    appPost(window.APP_URL + '/modules/stakeholders/ajax_registration_save.php', Object.fromEntries(new FormData(form)))
      .then(data => {
        if (data.success) { modal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });
})();
