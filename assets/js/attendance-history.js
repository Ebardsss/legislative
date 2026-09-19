/**
 * assets/js/attendance-history.js
 * ------------------------------------------------------------------
 * Powers modules/attendance/history.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('historyTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/modules/attendance/ajax_search_history.php';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const hearing = document.getElementById('hearingFilter').value;
    const action = document.getElementById('actionFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    if (search) params.set('search', search);
    if (hearing) params.set('hearing_id', hearing);
    if (action) params.set('action', action);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load attendance history right now.'); }
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
  bindRowEvents();

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  ['hearingFilter', 'actionFilter', 'dateFrom', 'dateTo'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => { currentPage = 1; loadTable(); });
  });
})();
