/**
 * assets/js/activity-logs.js
 * ------------------------------------------------------------------
 * Powers pages/activity_logs.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('logsTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/pages/ajax_activity_logs_search.php';
  let currentDir = 'desc';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const userId = document.getElementById('userFilter').value;
    const action = document.getElementById('actionFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    if (search) params.set('search', search);
    if (userId) params.set('user_id', userId);
    if (action) params.set('action', action);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    params.set('dir', currentDir);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load activity logs right now.'); }
    });
  }

  function bindRowEvents() {
    wrap.querySelectorAll('.sort-link').forEach(el => {
      el.style.cursor = 'pointer';
      el.addEventListener('click', function () {
        currentDir = currentDir === 'asc' ? 'desc' : 'asc';
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
  }
  bindRowEvents();

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  ['userFilter', 'actionFilter', 'dateFrom', 'dateTo'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => { currentPage = 1; loadTable(); });
  });
})();
