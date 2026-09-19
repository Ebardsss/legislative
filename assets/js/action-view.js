/**
 * assets/js/action-view.js
 * ------------------------------------------------------------------
 * Powers modules/actions/view.php: document upload, assign office,
 * and add progress update forms.
 * ------------------------------------------------------------------
 */

(function () {
  const uploadForm = document.getElementById('uploadForm');
  const assignForm = document.getElementById('assignForm');
  const updateForm = document.getElementById('updateForm');

  if (uploadForm) {
    uploadForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = uploadForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      appPostForm(window.APP_URL + '/modules/actions/ajax_upload_document.php', uploadForm)
        .then(data => {
          btn.disabled = false;
          if (data.success) { appToast('success', data.message); setTimeout(() => window.location.reload(), 700); }
          else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
        });
    });
  }

  if (assignForm) {
    assignForm.addEventListener('submit', function (e) {
      e.preventDefault();
      appPost(window.APP_URL + '/modules/actions/ajax_assign.php', Object.fromEntries(new FormData(assignForm)))
        .then(data => {
          if (data.success) { appToast('success', data.message); setTimeout(() => window.location.reload(), 700); }
          else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
        });
    });
  }

  if (updateForm) {
    updateForm.addEventListener('submit', function (e) {
      e.preventDefault();
      appPost(window.APP_URL + '/modules/actions/ajax_add_update.php', Object.fromEntries(new FormData(updateForm)))
        .then(data => {
          if (data.success) { appToast('success', data.message); setTimeout(() => window.location.reload(), 700); }
          else if (!data.session_expired) { Swal.fire('Error', data.message, 'error'); }
        });
    });
  }
})();
