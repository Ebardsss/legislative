(function () {
  'use strict';

  const grid = document.getElementById('calendarGrid');
  const label = document.getElementById('calMonthLabel');

  if (!grid || !label) return;

  const monthNames = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December'
  ];

  const statusClass = {
    Upcoming: 'upcoming',
    Ongoing: 'ongoing',
    Completed: 'completed',
    Cancelled: 'cancelled'
  };

  const today = new Date();
  let viewYear = today.getFullYear();
  let viewMonth = today.getMonth() + 1;

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function load() {
    label.textContent =
      monthNames[viewMonth - 1] + ' ' + viewYear;

    appGet(
      window.APP_URL
      + '/modules/hearings/ajax_calendar_events.php?year='
      + viewYear
      + '&month='
      + viewMonth
    ).then(function (data) {
      render(data.success ? data.events : []);
    });
  }

  function render(events) {
    const first = new Date(viewYear, viewMonth - 1, 1);
    const offset = first.getDay();
    const days = new Date(viewYear, viewMonth, 0).getDate();

    const eventsByDay = {};

    events.forEach(function (event) {
      const startDate = new Date(event.hearing_date + 'T00:00:00');
      const endDate = new Date((event.end_date || event.hearing_date) + 'T00:00:00');

      for (
        let current = new Date(startDate);
        current <= endDate;
        current.setDate(current.getDate() + 1)
      ) {
        if (
          current.getFullYear() !== viewYear
          || current.getMonth() + 1 !== viewMonth
        ) {
          continue;
        }

        const day = current.getDate();

        if (!eventsByDay[day]) {
          eventsByDay[day] = [];
        }

        eventsByDay[day].push(event);
      }
    });

    let html = '';

    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function (day) {
      html += '<div class="hearing-cal-head">' + day + '</div>';
    });

    for (let i = 0; i < offset; i++) {
      html += '<div class="hearing-cal-day empty"></div>';
    }

    const currentMonth =
      viewYear === today.getFullYear()
      && viewMonth === today.getMonth() + 1;

    for (let day = 1; day <= days; day++) {
      const isToday = currentMonth && day === today.getDate();

      html +=
        '<div class="hearing-cal-day'
        + (isToday ? ' today' : '')
        + '">';

      html +=
        '<div class="hearing-cal-day-number">'
        + day
        + '</div>';

      (eventsByDay[day] || []).forEach(function (event) {
        const status = statusClass[event.status] || 'default';
        const title = escapeHtml(event.title);
        const reference = escapeHtml(event.reference_number || '');
        const time = String(event.hearing_time || '').substring(0, 5);

        html +=
          '<button type="button" class="hearing-cal-event '
          + status
          + '" data-id="'
          + Number(event.id)
          + '" title="'
          + reference
          + ' '
          + title
          + '">'
          + '<small>'
          + escapeHtml(time)
          + '</small>'
          + '<span>'
          + title
          + '</span>'
          + '</button>';
      });

      html += '</div>';
    }

    grid.innerHTML = html;

    grid.querySelectorAll('.hearing-cal-event').forEach(function (button) {
      button.addEventListener('click', function () {
        window.location.href =
          window.APP_URL
          + '/modules/hearings/view.php?id='
          + button.getAttribute('data-id');
      });
    });
  }

  document.getElementById('prevMonth').addEventListener('click', function () {
    viewMonth--;

    if (viewMonth < 1) {
      viewMonth = 12;
      viewYear--;
    }

    load();
  });

  document.getElementById('nextMonth').addEventListener('click', function () {
    viewMonth++;

    if (viewMonth > 12) {
      viewMonth = 1;
      viewYear++;
    }

    load();
  });

  load();
})();
