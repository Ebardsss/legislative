/**
 * assets/js/survey-responses.js
 * ------------------------------------------------------------------
 * Powers modules/feedback/survey_responses.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('responsesTableWrap');
  if (!wrap) return;

  const surveyId = window.SURVEY_ID || 0;
  const AJAX_URL = window.APP_URL + '/modules/feedback/ajax_survey_responses_search.php';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    params.set('id', surveyId);
    const search = document.getElementById('searchInput').value;
    if (search) params.set('search', search);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load responses right now.'); }
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

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
})();
