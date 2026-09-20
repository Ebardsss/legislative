/**
 * assets/js/surveys.js
 * ------------------------------------------------------------------
 * Powers modules/feedback/surveys.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('surveysTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/modules/feedback/ajax_surveys_search.php';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load surveys right now.'); }
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
    wrap.querySelectorAll('.btn-edit-survey').forEach(btn => {
      btn.addEventListener('click', () => openEditModal(btn.getAttribute('data-id')));
    });
  }

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });

  bindRowEvents();

  const modalEl = document.getElementById('surveyModal');
  if (!modalEl) return;

  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('surveyForm');

  document.getElementById('btnAddSurvey').addEventListener('click', function () {
    form.reset();
    document.getElementById('sv_id').value = 0;
    document.getElementById('surveyModalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> New Survey';
    modal.show();
  });

  function openEditModal(id) {
    appGet(window.APP_URL + '/modules/feedback/ajax_survey_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const s = data.survey;
      form.reset();
      document.getElementById('sv_id').value = s.id;
      document.getElementById('sv_title').value = s.title || '';
      document.getElementById('sv_description').value = s.description || '';
      document.getElementById('sv_status').value = s.status || 'Active';
      document.getElementById('surveyModalTitle').innerHTML = '<i class="bi bi-pencil-square"></i> Edit Survey';
      modal.show();
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    appPost(window.APP_URL + '/modules/feedback/ajax_survey_save.php', Object.fromEntries(new FormData(form)))
      .then(data => {
        if (data.success) { modal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });
})();
