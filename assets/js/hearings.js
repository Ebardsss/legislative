(function () {
  'use strict';

  const wrap = document.getElementById('hearingsTableWrap');
  const filterForm = document.getElementById('filterForm');

  if (!wrap || !filterForm) return;

  const AJAX_URL =
    window.APP_URL + '/modules/hearings/ajax_search.php';

  let currentSort = 'hearing_date';
  let currentDir = 'asc';
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const data = new FormData(filterForm);
    const params = new URLSearchParams();

    for (const [key, value] of data.entries()) {
      if (String(value).trim() !== '') {
        params.append(key, value);
      }
    }

    params.set('sort', currentSort);
    params.set('dir', currentDir);
    params.set('page', extra && extra.page ? extra.page : currentPage);

    return params;
  }

  function syncPrintLink() {
    const printLink = document.getElementById('printScheduleLink');

    if (!printLink) return;

    const params = buildParams({ page: 1 });

    params.delete('search');
    params.delete('sort');
    params.delete('dir');
    params.delete('page');

    printLink.href =
      'print.php' + (params.toString() ? '?' + params.toString() : '');
  }

  function loadTable(extra) {
    const params = buildParams(extra || {});

    appGet(AJAX_URL + '?' + params.toString())
      .then(function (data) {
        if (data.success) {
          wrap.innerHTML = data.html;
          bindRowEvents();
          syncPrintLink();
          return;
        }

        if (!data.session_expired) {
          appToast(
            'error',
            data.message || 'Unable to load the hearing schedule.'
          );
        }
      });
  }

  function bindRowEvents() {
    wrap.querySelectorAll('.sort-link').forEach(function (button) {
      button.addEventListener('click', function () {
        const column = button.getAttribute('data-sort');

        if (currentSort === column) {
          currentDir = currentDir === 'asc' ? 'desc' : 'asc';
        } else {
          currentSort = column;
          currentDir = 'asc';
        }

        currentPage = 1;
        loadTable();
      });
    });

    wrap.querySelectorAll('.pagination a.page-link').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();

        const url = new URL(link.href, window.location.href);

        currentPage = parseInt(
          url.searchParams.get('page') || '1',
          10
        );

        loadTable({ page: currentPage });
      });
    });

    wrap.querySelectorAll('.btn-edit-hearing').forEach(function (button) {
      button.addEventListener('click', function () {
        openEditModal(button.getAttribute('data-id'));
      });
    });
  }

  if (window.registerDeleteHandler) {
    window.registerDeleteHandler(function () {
      loadTable();
    });
  }

  filterForm.addEventListener('input', function (event) {
    if (event.target.id !== 'searchInput') {
      return;
    }

    clearTimeout(searchTimer);

    searchTimer = setTimeout(function () {
      currentPage = 1;
      loadTable();
    }, 350);
  });

  filterForm.addEventListener('change', function () {
    currentPage = 1;
    loadTable();
  });

  syncPrintLink();
  bindRowEvents();

  const modalElement = document.getElementById('hearingModal');

  if (!modalElement) {
    return;
  }

  const modal = new bootstrap.Modal(modalElement);
  const form = document.getElementById('hearingForm');
  const addButton = document.getElementById('btnAddHearing');
  const statusInput = document.getElementById('f_status');
  const cancellationWrap = document.getElementById('cancellationReasonWrap');
  const cancellationInput = document.getElementById('f_cancellation_reason');

  function setCancellationState() {
    const cancelled = statusInput.value === 'Cancelled';

    cancellationWrap.hidden = !cancelled;
    cancellationInput.required = cancelled;

    if (!cancelled) {
      cancellationInput.value = '';
    }
  }

  statusInput.addEventListener('change', setCancellationState);

  addButton.addEventListener('click', function () {
    form.reset();

    document.getElementById('hearing_id').value = '0';
    document.getElementById('f_visibility').value = 'Public';
    document.getElementById('f_status').value = 'Upcoming';

    document.getElementById('hearingModalTitle').innerHTML =
      '<i class="bi bi-calendar-plus"></i> Create Hearing';

    setCancellationState();
    modal.show();
  });

  function setValue(id, value) {
    const element = document.getElementById(id);

    if (element) {
      element.value =
        value === null || value === undefined
          ? ''
          : value;
    }
  }

  function openEditModal(id) {
    appGet(
      window.APP_URL
      + '/modules/hearings/ajax_get.php?id='
      + encodeURIComponent(id)
    ).then(function (data) {
      if (!data.success) {
        if (!data.session_expired) {
          appToast(
            'error',
            data.message || 'Unable to load hearing.'
          );
        }

        return;
      }

      const h = data.hearing;

      form.reset();

      setValue('hearing_id', h.id);
      setValue('f_title', h.title);
      setValue('f_legislative_item', h.legislative_item_id);
      setValue('f_type', h.hearing_type_id);
      setValue('f_committee', h.committee_id);
      setValue('f_date', h.hearing_date);
      setValue('f_time', h.hearing_time);
      setValue('f_end_date', h.end_date);
      setValue('f_end_time', h.end_time);
      setValue('f_venue', h.venue);
      setValue('f_meeting_link', h.meeting_link);
      setValue('f_registration_deadline', h.registration_deadline);
      setValue('f_maximum_participants', h.maximum_participants);
      setValue('f_visibility', h.visibility || 'Public');
      setValue('f_status', h.status || 'Upcoming');
      setValue('f_cancellation_reason', h.cancellation_reason);
      setValue('f_description', h.description);

      document.getElementById('hearingModalTitle').innerHTML =
        '<i class="bi bi-pencil-square"></i> Edit Hearing';

      setCancellationState();
      modal.show();
    });
  }

  window.openEditModal = openEditModal;

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    const button = document.getElementById('btnSaveHearing');

    button.disabled = true;
    button.innerHTML =
      '<span class="spinner-border spinner-border-sm"></span> Saving...';

    appPostForm(
      window.APP_URL + '/modules/hearings/ajax_save.php',
      form
    ).then(function (data) {
      button.disabled = false;
      button.innerHTML =
        '<i class="bi bi-check-circle"></i> Save Hearing';

      if (data.success) {
        modal.hide();
        appToast('success', data.message);
        loadTable();
        return;
      }

      if (!data.session_expired) {
        Swal.fire(
          'Unable to Save Hearing',
          data.message || 'Please review the hearing details.',
          'error'
        );
      }
    }).catch(function () {
      button.disabled = false;
      button.innerHTML =
        '<i class="bi bi-check-circle"></i> Save Hearing';
    });
  });
})();
