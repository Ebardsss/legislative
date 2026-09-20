/**
 * assets/js/invitations.js
 * ------------------------------------------------------------------
 * Powers modules/stakeholders/invitations.php.
 * ------------------------------------------------------------------
 */

(function () {
  const wrap = document.getElementById('invitationsTableWrap');
  if (!wrap) return;

  const AJAX_URL = window.APP_URL + '/modules/stakeholders/ajax_search_invitations.php';
  let currentSort = 'created_at';
  let currentDir = 'desc';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const hearing = document.getElementById('hearingFilter').value;
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (hearing) params.set('hearing_id', hearing);
    params.set('sort', currentSort);
    params.set('dir', currentDir);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(AJAX_URL + '?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load invitations right now.'); }
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

    wrap.querySelectorAll('.btn-mark-sent').forEach(btn => {
      btn.addEventListener('click', function () {
        appPost(window.APP_URL + '/modules/stakeholders/ajax_invitation_send.php', {
          id: btn.getAttribute('data-id'), csrf_token: window.APP_CSRF_TOKEN
        }).then(data => {
          if (data.success) { appToast('success', data.message); loadTable(); }
          else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
        });
      });
    });

    wrap.querySelectorAll('.btn-email-template').forEach(btn => {
      btn.addEventListener('click', () => openEmailTemplate(btn.getAttribute('data-id')));
    });
  }

  if (window.registerDeleteHandler) window.registerDeleteHandler(loadTable);

  document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });
  document.getElementById('hearingFilter').addEventListener('change', () => { currentPage = 1; loadTable(); });

  bindRowEvents();

  /* ================= New Invitation Modal ================= */
  const invModalEl = document.getElementById('invitationModal');
  const invModal = new bootstrap.Modal(invModalEl);
  const invForm = document.getElementById('invitationForm');

  document.getElementById('btnAddInvitation').addEventListener('click', function () {
    invForm.reset();
    invModal.show();
  });

  invForm.addEventListener('submit', function (e) {
    e.preventDefault();
    appPost(window.APP_URL + '/modules/stakeholders/ajax_invitation_save.php', Object.fromEntries(new FormData(invForm)))
      .then(data => {
        if (data.success) { invModal.hide(); appToast('success', data.message); loadTable(); }
        else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
      });
  });

  /* ================= Bulk Invite Modal ================= */
  const bulkModalEl = document.getElementById('bulkInviteModal');
  const bulkModal = new bootstrap.Modal(bulkModalEl);
  const bulkForm = document.getElementById('bulkInviteForm');

  function getBulkIds() {
    try { return JSON.parse(sessionStorage.getItem('bulk_invite_ids') || '[]'); }
    catch (e) { return []; }
  }

  function openBulkModal() {
    const ids = getBulkIds();
    document.getElementById('bulkCountLabel').textContent = ids.length;
    document.getElementById('bulkResult').innerHTML = '';
    if (ids.length === 0) {
      appToast('warning', 'No stakeholders selected. Select some from the Stakeholders tab first.');
      return;
    }
    bulkModal.show();
  }

  document.getElementById('btnBulkInviteOpen').addEventListener('click', openBulkModal);

  // Auto-open if arriving from index.php's "Bulk Invite" button (?bulk=1)
  if (new URLSearchParams(window.location.search).get('bulk') === '1') {
    openBulkModal();
  }

  bulkForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const ids = getBulkIds();
    const btn = document.getElementById('btnDoBulkInvite');
    btn.disabled = true;

    appPost(window.APP_URL + '/modules/stakeholders/ajax_bulk_invite.php', {
      hearing_id: document.getElementById('bulkHearingSelect').value,
      stakeholder_ids: ids.join(','),
      csrf_token: window.APP_CSRF_TOKEN
    }).then(data => {
      btn.disabled = false;
      if (data.session_expired) return;
      const resultEl = document.getElementById('bulkResult');
      if (data.success) {
        resultEl.innerHTML = `<div class="alert alert-success py-2 mt-2">${data.message}</div>`;
        sessionStorage.removeItem('bulk_invite_ids');
        loadTable();
      } else {
        resultEl.innerHTML = `<div class="alert alert-danger py-2 mt-2">${data.message}</div>`;
      }
    });
  });

  /* ================= Email Template Preview ================= */
  const emailModalEl = document.getElementById('emailTemplateModal');
  const emailModal = new bootstrap.Modal(emailModalEl);

  function openEmailTemplate(id) {
    appGet(window.APP_URL + '/modules/stakeholders/ajax_invitation_get.php?id=' + id).then(data => {
      if (!data.success) { if (!data.session_expired) appToast('error', data.message); return; }
      const inv = data.invitation;
      const hearingLine = inv.hearing_title
        ? `${inv.hearing_title}, scheduled on ${inv.hearing_date} at ${inv.hearing_time}${inv.venue ? ' (Venue: ' + inv.venue + ')' : ''}.`
        : 'an upcoming public hearing / consultation session.';

      document.getElementById('emailSubject').value =
        `Invitation to Public Hearing${inv.hearing_title ? ': ' + inv.hearing_title : ''}`;

      document.getElementById('emailBody').value =
`Dear ${inv.full_name},

You are cordially invited to participate in ${hearingLine}

Your invitation code is: ${inv.invitation_code}

Please present this code (or your stakeholder QR code) upon registration and check-in. We look forward to your valuable participation.

Sincerely,
${window.APP_NAME || 'Legislative Services Department'}`;

      emailModal.show();
    });
  }

  document.getElementById('btnCopyEmail').addEventListener('click', function () {
    const subject = document.getElementById('emailSubject').value;
    const body = document.getElementById('emailBody').value;
    navigator.clipboard.writeText(`Subject: ${subject}\n\n${body}`)
      .then(() => appToast('success', 'Copied to clipboard.'))
      .catch(() => appToast('error', 'Unable to copy automatically — please select and copy manually.'));
  });
})();
