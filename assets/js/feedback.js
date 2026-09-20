/**
 * assets/js/feedback.js
 * ------------------------------------------------------------------
 * Powers modules/feedback/index.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('feedbackTableWrap');

  /* ================= Manager table (search/filter/sort/pagination) ================= */
  if (wrap) {
    const AJAX_URL = window.APP_URL + '/modules/feedback/ajax_search.php';
    let currentSort = 'submitted_at';
    let currentDir = 'desc';
    let currentPage = 1;
    let searchTimer = null;

    function buildParams(extra) {
      const params = new URLSearchParams();
      const search = document.getElementById('searchInput').value;
      const status = document.getElementById('statusFilter').value;
      const category = document.getElementById('categoryFilter').value;
      const sentiment = document.getElementById('sentimentFilter') ? document.getElementById('sentimentFilter').value : '';
      if (search) params.set('search', search);
      if (status) params.set('status', status);
      if (category) params.set('category_id', category);
      if (sentiment) params.set('sentiment', sentiment);
      params.set('sort', currentSort);
      params.set('dir', currentDir);
      params.set('page', extra && extra.page ? extra.page : currentPage);
      return params;
    }

    function loadTable(extra) {
      appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
        if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
        else if (!data.session_expired) { appToast('error', data.message || 'Unable to load feedback right now.'); }
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
      wrap.querySelectorAll('.btn-view-feedback').forEach(btn => {
        btn.addEventListener('click', () => openViewModal(btn.getAttribute('data-id')));
      });
    }

    if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

    document.getElementById('searchInput').addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
    });
    document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });
    document.getElementById('categoryFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });
    if (document.getElementById('sentimentFilter')) {
      document.getElementById('sentimentFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });
    }

    bindRowEvents();
    window.__feedbackLoadTable = loadTable;
  }

  /* ================= Submit Feedback Modal (all roles) ================= */
  const formModalEl = document.getElementById('feedbackFormModal');
  const formModal = new bootstrap.Modal(formModalEl);
  const form = document.getElementById('feedbackForm');

  document.getElementById('btnNewFeedback').addEventListener('click', function () {
    formModal.show();
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    appPostForm(window.APP_URL + '/modules/feedback/ajax_submit.php', form)
      .then(data => {
        if (btn) btn.disabled = false;
        if (data.success) {
          formModal.hide();
          appToast('success', data.message);
          if (window.__feedbackLoadTable) window.__feedbackLoadTable();
          else setTimeout(() => window.location.reload(), 800);
        } else if (!data.session_expired) {
          Swal.fire('Error', data.message || 'Unable to submit your feedback. Please check the form and try again.', 'error');
        }
      });
  });

  /* ================= View / Reply Modal (managers only) ================= */
  const viewModalEl = document.getElementById('viewFeedbackModal');
  if (!viewModalEl) return;
  const viewModal = new bootstrap.Modal(viewModalEl);
  let currentFeedbackId = null;

  function openViewModal(id) {
    appGet(window.APP_URL + '/modules/feedback/ajax_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const f = data.feedback;
      currentFeedbackId = f.id;
      document.getElementById('vf_from').textContent = f.name + ' (' + f.email + ')';
      document.getElementById('vf_category').textContent = f.category_name || '-';
      document.getElementById('vf_subject').textContent = f.subject || '(no subject)';
      document.getElementById('vf_status').innerHTML = '<span class="badge bg-secondary">' + f.status + '</span>';
      document.getElementById('vf_date').textContent = f.submitted_at;
      document.getElementById('vf_message').textContent = f.message;
      document.getElementById('vf_reply_text').value = '';

      const existingReplyWrap = document.getElementById('vf_existing_reply');
      if (f.reply_text) {
        document.getElementById('vf_existing_reply_text').textContent = f.reply_text;
        document.getElementById('vf_existing_reply_meta').textContent =
          'Replied ' + (f.replied_at || '') + (f.replied_by_name ? ' by ' + f.replied_by_name : '');
        existingReplyWrap.classList.remove('d-none');
      } else {
        existingReplyWrap.classList.add('d-none');
      }

      renderAiPanel(data.ai_analysis);
      viewModal.show();
    });
  }
  window.openViewModal = openViewModal;

  /* ---------------- AI Sentiment Analysis panel ---------------- */
  const sentimentEmoji = { Positive: '🟢', Neutral: '🟡', Negative: '🔴' };
  const sentimentColor = { Positive: 'success', Neutral: 'warning', Negative: 'danger' };

  function renderAiPanel(analysis) {
    const panel = document.getElementById('vf_ai_panel');
    const pending = document.getElementById('vf_ai_pending');
    const unavailable = document.getElementById('vf_ai_unavailable');
    panel.classList.add('d-none');
    pending.classList.add('d-none');
    unavailable.classList.add('d-none');

    if (!analysis) {
      unavailable.classList.remove('d-none');
      return;
    }
    if (analysis.status === 'pending') {
      pending.classList.remove('d-none');
      return;
    }
    if (analysis.status === 'failed') {
      unavailable.classList.remove('d-none');
      document.getElementById('vf_ai_unavailable').innerHTML =
        '<i class="bi bi-exclamation-triangle text-warning"></i> AI analysis failed: ' + (analysis.error_message || 'unknown error') +
        ' <button type="button" class="btn btn-outline-primary btn-sm ms-2" id="btnAnalyzeNow"><i class="bi bi-robot"></i> Try Again</button>';
      document.getElementById('btnAnalyzeNow').addEventListener('click', () => runAnalysis(false));
      return;
    }

    // status === 'completed'
    const emoji = sentimentEmoji[analysis.sentiment] || '⚪';
    const color = sentimentColor[analysis.sentiment] || 'secondary';
    const keywords = Array.isArray(analysis.keywords) ? analysis.keywords : [];

    document.getElementById('vf_ai_body').innerHTML = `
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-${color} fs-6">${emoji} ${analysis.sentiment} (${Math.round(analysis.confidence_score)}%)</span>
        ${analysis.flagged_for_review ? '<span class="badge bg-danger"><i class="bi bi-flag-fill"></i> Flagged for review</span>' : ''}
      </div>
      <div class="small mb-2"><strong>Summary:</strong> ${escapeHtml(analysis.summary || '-')}</div>
      <div class="small mb-2"><strong>Recommended category:</strong> ${escapeHtml(analysis.recommended_category || '-')}</div>
      <div class="small mb-2">
        <strong>Keywords:</strong>
        ${keywords.length ? keywords.map(k => `<span class="badge bg-light text-dark border me-1">${escapeHtml(k)}</span>`).join('') : '<span class="text-muted">none</span>'}
      </div>
      ${analysis.suggested_response ? `
        <div class="small">
          <strong>Suggested response:</strong>
          <div class="border rounded p-2 bg-light mt-1">${escapeHtml(analysis.suggested_response)}</div>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnUseSuggestedResponse"><i class="bi bi-clipboard-check"></i> Use as Reply</button>
        </div>` : ''}
    `;
    panel.classList.remove('d-none');

    const useBtn = document.getElementById('btnUseSuggestedResponse');
    if (useBtn) {
      useBtn.addEventListener('click', () => {
        document.getElementById('vf_reply_text').value = analysis.suggested_response;
        appToast('success', 'Suggested response copied into the reply box — review and edit before sending.');
      });
    }
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function runAnalysis(isReanalyze) {
    if (!currentFeedbackId) return;
    appToast('success', isReanalyze ? 'Re-analyzing with AI...' : 'Running AI analysis...');
    appPost(window.APP_URL + '/modules/feedback/ajax_analyze.php', {
      id: currentFeedbackId, csrf_token: window.APP_CSRF_TOKEN
    }).then(data => {
      if (data.session_expired) return;
      if (data.success) {
        appToast('success', 'AI analysis complete.');
        renderAiPanel(data.ai_analysis);
        if (window.__feedbackLoadTable) window.__feedbackLoadTable();
      } else {
        Swal.fire('AI Analysis Failed', data.message || 'Unable to analyze this feedback.', 'warning');
      }
    });
  }

  document.getElementById('btnReanalyze').addEventListener('click', () => runAnalysis(true));
  document.getElementById('btnReanalyzeInline').addEventListener('click', () => runAnalysis(false));
  document.getElementById('btnAnalyzeNow')?.addEventListener('click', () => runAnalysis(false));

  viewModalEl.querySelectorAll('[data-mark-status]').forEach(btn => {
    btn.addEventListener('click', function () {
      appPost(window.APP_URL + '/modules/feedback/ajax_status.php', {
        id: currentFeedbackId, status: btn.getAttribute('data-mark-status'), csrf_token: window.APP_CSRF_TOKEN
      }).then(data => {
        if (data.success) {
          appToast('success', data.message);
          viewModal.hide();
          if (window.__feedbackLoadTable) window.__feedbackLoadTable();
        } else if (!data.session_expired) {
          Swal.fire('Error', data.message, 'error');
        }
      });
    });
  });

  document.getElementById('btnSendReply').addEventListener('click', function () {
    const replyText = document.getElementById('vf_reply_text').value.trim();
    if (!replyText) {
      Swal.fire('Missing reply', 'Please type a reply message first.', 'warning');
      return;
    }
    appPost(window.APP_URL + '/modules/feedback/ajax_reply.php', {
      id: currentFeedbackId, reply_text: replyText, csrf_token: window.APP_CSRF_TOKEN
    }).then(data => {
      if (data.success) {
        appToast('success', data.message);
        viewModal.hide();
        if (window.__feedbackLoadTable) window.__feedbackLoadTable();
      } else if (!data.session_expired) {
        Swal.fire('Error', data.message, 'error');
      }
    });
  });
})();
