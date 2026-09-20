/**
 * assets/js/issue-view.js
 * ------------------------------------------------------------------
 * Powers modules/issues/view.php: the Assign and Add Note forms.
 * ------------------------------------------------------------------
 */

(function () {
  const assignForm = document.getElementById('assignForm');
  const noteForm = document.getElementById('noteForm');

  if (assignForm) {
    assignForm.addEventListener('submit', function (e) {
      e.preventDefault();
      appPost(window.APP_URL + '/modules/issues/ajax_assign.php', Object.fromEntries(new FormData(assignForm)))
        .then(data => {
          if (data.success) {
            appToast('success', data.message);
            setTimeout(() => window.location.reload(), 700);
          } else if (!data.session_expired) {
            Swal.fire('Error', data.message, 'error');
          }
        });
    });
  }

  if (noteForm) {
    noteForm.addEventListener('submit', function (e) {
      e.preventDefault();
      appPost(window.APP_URL + '/modules/issues/ajax_add_note.php', Object.fromEntries(new FormData(noteForm)))
        .then(data => {
          if (data.success) {
            appToast('success', data.message);
            setTimeout(() => window.location.reload(), 700);
          } else if (!data.session_expired) {
            Swal.fire('Error', data.message, 'error');
          }
        });
    });
  }
})();
