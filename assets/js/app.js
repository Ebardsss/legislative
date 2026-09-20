/**
 * assets/js/app.js
 * ------------------------------------------------------------------
 * Global, site-wide JavaScript: sidebar toggle, delete confirmations,
 * generic AJAX helper, and DataTables defaults. Module-specific logic
 * lives in assets/js/<module>.js and is loaded on top of this file.
 * ------------------------------------------------------------------
 */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Sidebar toggle (desktop collapse / mobile slide-in) ---------- */
  const toggleBtns = document.querySelectorAll('#sidebarToggleBtn, #sidebarToggleDesktop, #sidebarToggle, .orlms-collapse-toggle');
  toggleBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-collapsed');
    });
  });
  const toggleMobile  = document.getElementById('sidebarToggleMobile');
  if (toggleMobile) {
    toggleMobile.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-mobile-open');
    });
  }

  /* ---------- Generic "confirm delete" wiring ----------
     Any element with [data-confirm-delete] and [data-delete-url]
     will show a SweetAlert2 confirmation, then POST to the URL
     (with CSRF token) via fetch and reload/redirect on success.

     Uses event delegation on document so it keeps working for rows
     injected later by AJAX (e.g. module live-search table refreshes)
     without needing to be re-bound, and without double-binding.
     Modules that want a custom post-delete action (e.g. reload just
     the table instead of the whole page) can call
     window.registerDeleteHandler(fn) to override the default reload.
  ------------------------------------------------------------- */
  let onDeleteSuccess = function () { window.location.reload(); };
  window.registerDeleteHandler = function (fn) { onDeleteSuccess = fn; };

  document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-confirm-delete]');
    if (!el) return;
    e.preventDefault();

    const url = el.getAttribute('data-delete-url');
    const label = el.getAttribute('data-confirm-delete') || 'this record';
    const csrfToken = window.APP_CSRF_TOKEN || '';

    Swal.fire({
      title: 'Are you sure?',
      text: 'This will permanently delete ' + label + '. This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#a4302a',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it'
    }).then(function (result) {
      if (!result.isConfirmed) return;

      appFetchJson(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
      }).then(function (data) {
        if (data.success) {
          appToast('success', data.message || 'Deleted successfully.');
          onDeleteSuccess();
        } else if (!data.session_expired) {
          Swal.fire('Error', data.message || 'Unable to delete this record.', 'error');
        }
      });
    });
  });

  /* ---------- Default DataTables initialization for tables marked .data-table ---------- */
  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('.data-table').each(function () {
      if (!jQuery.fn.DataTable.isDataTable(this)) {
        jQuery(this).DataTable({
          pageLength: 10,
          responsive: true,
          language: { search: '', searchPlaceholder: 'Search...' }
        });
      }
    });
  }
});

/**
 * ------------------------------------------------------------------
 * Robust AJAX helpers (network-error root-cause fixes, client side)
 * ------------------------------------------------------------------
 * Every helper below ALWAYS resolves (never rejects) with a normalized
 * {success, message, ...} object. This means every existing call site
 * across the app that does `.then(data => { if (data.success) ... else
 * Swal.fire('Error', data.message...) })` automatically gets accurate,
 * specific error messages instead of a generic "network error" — without
 * needing to change each module file individually.
 *
 * The three failure cases are now distinguished instead of collapsed into
 * one generic message:
 *   1. Request never reached the server (offline, server down, wrong URL)
 *      -> "Could not reach the server..." (a genuine network problem)
 *   2. Server responded but not with valid JSON (a PHP fatal error/warning
 *      leaked HTML into the response — should no longer happen after the
 *      server-side fixes, but if it ever does, this says so explicitly)
 *   3. Server responded with valid JSON but success:false (validation
 *      error, permission denied, etc.) -> shows the server's real message
 *
 * If the server reports the session expired, the user is notified and
 * redirected to the login page automatically instead of the form just
 * silently failing.
 * ------------------------------------------------------------------ */

function appFetchJson(url, options) {
  return fetch(url, options)
    .then(function (response) {
      return response.text().then(function (text) {
        let data;
        try {
          data = text ? JSON.parse(text) : {};
        } catch (parseErr) {
          // The server responded, but the body wasn't valid JSON — most
          // likely an HTML error page. Surface that clearly rather than
          // pretending it was a network failure.
          console.error('appFetchJson: non-JSON response from', url, text.substring(0, 500));
          return {
            success: false,
            message: response.ok
              ? 'The server sent back an unexpected response. Please refresh the page and try again.'
              : 'Server error (HTTP ' + response.status + '). Please try again or contact your administrator.',
          };
        }
        if (!response.ok && data.message === undefined) {
          data.message = 'Server error (HTTP ' + response.status + ').';
        }
        return data;
      });
    })
    .then(function (data) {
      if (data && data.session_expired) {
        Swal.fire({
          icon: 'warning',
          title: 'Session Expired',
          text: data.message || 'Your session has expired. Please log in again.',
          confirmButtonText: 'Go to Login'
        }).then(function () {
          window.location.href = window.APP_URL + '/login.php';
        });
      }
      return data;
    })
    .catch(function (err) {
      // A true network-level failure: the request never got a response at
      // all (server unreachable, DNS failure, connection refused, etc.)
      console.error('appFetchJson: network failure for', url, err);
      return {
        success: false,
        message: 'Could not reach the server. Please check that your local server (XAMPP/Apache/MySQL) is running, then try again.',
      };
    });
}

/**
 * POST a plain object as application/x-www-form-urlencoded.
 * Always resolves; never rejects (see appFetchJson above).
 * @param {string} url
 * @param {Object} data
 * @returns {Promise<Object>}
 */
function appPost(url, data) {
  const params = new URLSearchParams(data);
  return appFetchJson(url, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params.toString()
  });
}

/**
 * POST a <form> element as multipart/form-data (required whenever the form
 * includes a file input, e.g. document/CSV uploads). Always resolves;
 * never rejects.
 * @param {string} url
 * @param {HTMLFormElement} formElement
 * @returns {Promise<Object>}
 */
function appPostForm(url, formElement) {
  return appFetchJson(url, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: new FormData(formElement)
  });
}

/**
 * GET a URL and parse JSON, with the same robust error handling as
 * appPost(). Used by every module's live-search/table-refresh calls.
 * @param {string} url
 * @returns {Promise<Object>}
 */
function appGet(url) {
  return appFetchJson(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
}

/** Small toast helper reusable by module scripts. */
function appToast(icon, message) {
  Swal.fire({ icon, title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
}
