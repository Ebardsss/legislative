/**
 * assets/js/reports.js
 * ------------------------------------------------------------------
 * Powers reports/index.php: keeps the selected date range appended to
 * each "View Report" link, and remembers the last-used range in
 * sessionStorage so it persists across navigation within Reports.
 * ------------------------------------------------------------------
 */

(function () {
  const dateFrom = document.getElementById('date_from');
  const dateTo = document.getElementById('date_to');
  const links = document.querySelectorAll('.report-link');
  if (!dateFrom || !dateTo) return;

  const STORAGE_KEY = 'lph_report_date_range';

  function restore() {
    try {
      const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}');
      if (saved.date_from) dateFrom.value = saved.date_from;
      if (saved.date_to) dateTo.value = saved.date_to;
    } catch (e) { /* ignore */ }
    syncLinks();
  }

  function syncLinks() {
    links.forEach(a => {
      const type = a.getAttribute('data-type');
      const params = new URLSearchParams({ type });
      if (dateFrom.value) params.set('date_from', dateFrom.value);
      if (dateTo.value) params.set('date_to', dateTo.value);
      a.href = 'view.php?' + params.toString();
    });
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ date_from: dateFrom.value, date_to: dateTo.value }));
  }

  dateFrom.addEventListener('change', syncLinks);
  dateTo.addEventListener('change', syncLinks);

  document.getElementById('btnClearDates').addEventListener('click', function () {
    dateFrom.value = '';
    dateTo.value = '';
    syncLinks();
  });

  restore();
})();
