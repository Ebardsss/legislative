/**
 * assets/js/attendance.js
 * ------------------------------------------------------------------
 * Powers modules/attendance/index.php: hearing switching, QR scanner
 * (html5-qrcode), manual attendance with stakeholder autocomplete,
 * live stat cards, and the attendance records table (AJAX search/
 * filter/pagination).
 * ------------------------------------------------------------------
 */

(function () {
  const hearingSelect = document.getElementById('hearingSelect');
  if (hearingSelect) {
    hearingSelect.addEventListener('change', function () {
      window.location.href = 'index.php?hearing_id=' + hearingSelect.value;
    });
  }

  const hearingId = window.SELECTED_HEARING_ID || 0;
  if (!hearingId) return;

  /* ================= Stat cards ================= */
  function refreshStats() {
    appGet(window.APP_URL + '/modules/attendance/ajax_stats.php?hearing_id=' + hearingId).then(data => {
      if (!data.success) return;
      document.getElementById('statRegistered').textContent = data.registered;
      document.getElementById('statPresent').textContent = data.present;
      document.getElementById('statLate').textContent = data.late;
      document.getElementById('statAbsent').textContent = data.absent;
    });
  }
  refreshStats();

  /* ================= Attendance table (AJAX search/filter/pagination) ================= */
  const wrap = document.getElementById('attendanceTableWrap');
  let currentPage = 1;
  let searchTimer = null;

  function buildParams(extra) {
    const params = new URLSearchParams();
    params.set('hearing_id', hearingId);
    const search = document.getElementById('searchInput')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    params.set('page', extra && extra.page ? extra.page : currentPage);
    return params;
  }

  function loadTable(extra) {
    appGet(window.APP_URL + '/modules/attendance/ajax_search.php?' + buildParams(extra || {}).toString()).then(data => {
      if (data.success) { wrap.innerHTML = data.html; bindRowEvents(); }
      else if (!data.session_expired) { appToast('error', data.message || 'Unable to load attendance records right now.'); }
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

  if (window.registerDeleteHandler) {
    window.registerDeleteHandler(function () { loadTable(); refreshStats(); });
  }

  document.getElementById('searchInput')?.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; loadTable(); }, 400);
  });
  document.getElementById('statusFilter')?.addEventListener('change', () => { currentPage = 1; loadTable(); });

  /* ================= QR Scanner (html5-qrcode) ================= */
  const btnToggle = document.getElementById('btnToggleScanner');
  const scanResult = document.getElementById('scanResult');
  let scanner = null;
  let scanning = false;
  let scriptLoaded = false;
  let lastScannedCode = null;
  let lastScanTime = 0;

  function loadScannerScript(cb) {
    if (scriptLoaded) { cb(); return; }
    const localSrc = window.APP_URL + '/assets/vendor/html5-qrcode/html5-qrcode.min.js';
    const cdnSrc = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';

    const s = document.createElement('script');
    s.src = localSrc;
    s.onload = function () { scriptLoaded = true; cb(); };
    s.onerror = function () {
      // Local vendor copy not present yet — fall back to CDN automatically.
      const cdnScript = document.createElement('script');
      cdnScript.src = cdnSrc;
      cdnScript.onload = function () { scriptLoaded = true; cb(); };
      cdnScript.onerror = function () {
        appToast('error', 'Unable to load the QR scanner library. Run tools/download-vendor-assets.php once with internet access to enable this offline, or check your connection.');
      };
      document.head.appendChild(cdnScript);
    };
    document.head.appendChild(s);
  }

  function handleScan(decodedText) {
    const now = Date.now();
    // Debounce repeated reads of the same code within 3 seconds.
    if (decodedText === lastScannedCode && (now - lastScanTime) < 3000) return;
    lastScannedCode = decodedText;
    lastScanTime = now;

    appPost(window.APP_URL + '/modules/attendance/ajax_checkin.php', {
      code: decodedText, hearing_id: hearingId, csrf_token: window.APP_CSRF_TOKEN
    }).then(data => {
      if (data.session_expired) return;
      const cls = data.success ? 'alert-success' : 'alert-warning';
      scanResult.innerHTML = `<div class="alert ${cls} py-2 mb-0">${data.message}</div>`;
      if (data.success) { appToast('success', data.message); loadTable(); refreshStats(); }
    });
  }

  /** True only on HTTPS or localhost — browsers refuse camera access anywhere else. */
  function isSecureContextForCamera() {
    return window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  }

  function pickBestCamera(cameras) {
    // Prefer a camera whose label suggests it's rear/back-facing (typical on phones);
    // otherwise just use the first available camera (typical on desktops/laptops).
    const rear = cameras.find(c => /back|rear|environment/i.test(c.label));
    return rear || cameras[0];
  }

  function populateCameraSelect(cameras, selectedId) {
    const wrap = document.getElementById('cameraSelectWrap');
    const select = document.getElementById('cameraSelect');
    if (cameras.length <= 1) { wrap.classList.add('d-none'); return; }
    select.innerHTML = cameras.map(c =>
      `<option value="${c.id}" ${c.id === selectedId ? 'selected' : ''}>${c.label || 'Camera ' + c.id}</option>`
    ).join('');
    wrap.classList.remove('d-none');
  }

  function startScannerWithCamera(cameraId) {
    scanner.start(
      cameraId,
      { fps: 10, qrbox: 220 },
      handleScan,
      () => {} // ignore per-frame "no QR code found in this frame" noise
    ).then(() => {
      scanning = true;
      btnToggle.textContent = 'Stop Scanner';
      btnToggle.disabled = false;
      scanResult.innerHTML = '';
    }).catch(err => {
      btnToggle.disabled = false;
      console.error('QR scanner start() failed:', err);
      scanResult.innerHTML = '<div class="alert alert-danger py-2 mb-0">Unable to start the camera. It may be in use by another application, or your browser blocked access. Use the manual code entry below instead.</div>';
    });
  }

  if (btnToggle) {
    btnToggle.addEventListener('click', function () {
      if (scanning) {
        if (scanner) scanner.stop().catch(() => {});
        scanning = false;
        btnToggle.textContent = 'Start Scanner';
        return;
      }

      if (!isSecureContextForCamera()) {
        scanResult.innerHTML = '<div class="alert alert-warning py-2 mb-0">' +
          'Camera access requires HTTPS or <code>localhost</code>. You opened this page at <code>' + location.origin + '</code>, ' +
          'which most browsers treat as insecure and will silently block the camera for. ' +
          'Use the manual code entry below, or access this app via <code>https://</code> or from the server itself at <code>http://localhost/...</code>.</div>';
        return;
      }
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        scanResult.innerHTML = '<div class="alert alert-warning py-2 mb-0">This browser does not support camera access. Use the manual code entry below instead.</div>';
        return;
      }

      btnToggle.disabled = true;
      loadScannerScript(function () {
        scanner = new Html5Qrcode('qrReader');

        Html5Qrcode.getCameras().then(cameras => {
          if (!cameras || cameras.length === 0) {
            btnToggle.disabled = false;
            scanResult.innerHTML = '<div class="alert alert-warning py-2 mb-0">No camera was detected on this device. Use the manual code entry below instead.</div>';
            return;
          }
          const chosen = pickBestCamera(cameras);
          populateCameraSelect(cameras, chosen.id);
          startScannerWithCamera(chosen.id);

          document.getElementById('cameraSelect').addEventListener('change', function () {
            if (scanning && scanner) {
              scanner.stop().then(() => startScannerWithCamera(this.value)).catch(() => startScannerWithCamera(this.value));
            }
          });
        }).catch(err => {
          btnToggle.disabled = false;
          console.error('getCameras() failed:', err);
          const denied = err && /permission|denied|NotAllowed/i.test(String(err));
          scanResult.innerHTML = '<div class="alert alert-danger py-2 mb-0">' +
            (denied
              ? 'Camera permission was denied. Check your browser\'s site settings and allow camera access for this page, then try again.'
              : 'Unable to access the camera (' + (err && err.message ? err.message : 'unknown error') + '). Use the manual code entry below instead.') +
            '</div>';
        });
      });
    });
  }

  /* ================= Manual QR code entry (camera fallback) ================= */
  const manualCodeForm = document.getElementById('manualCodeForm');
  if (manualCodeForm) {
    manualCodeForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const input = document.getElementById('manualCodeInput');
      const code = input.value.trim();
      if (!code) return;
      appPost(window.APP_URL + '/modules/attendance/ajax_checkin.php', {
        code: code, hearing_id: hearingId, csrf_token: window.APP_CSRF_TOKEN
      }).then(data => {
        if (data.session_expired) return;
        const cls = data.success ? 'alert-success' : 'alert-warning';
        scanResult.innerHTML = `<div class="alert ${cls} py-2 mb-0">${data.message}</div>`;
        if (data.success) {
          appToast('success', data.message);
          input.value = '';
          loadTable(); refreshStats();
        }
      });
    });
  }

  /* ================= Manual attendance: stakeholder autocomplete ================= */
  const stakeholderSearch = document.getElementById('stakeholderSearch');
  const stakeholderResults = document.getElementById('stakeholderResults');
  const stakeholderIdField = document.getElementById('manualStakeholderId');
  let searchDebounce = null;

  stakeholderSearch.addEventListener('input', function () {
    stakeholderIdField.value = '';
    clearTimeout(searchDebounce);
    const q = stakeholderSearch.value.trim();
    if (q.length < 2) { stakeholderResults.innerHTML = ''; return; }
    searchDebounce = setTimeout(() => {
      appGet(window.APP_URL + '/modules/attendance/ajax_stakeholder_search.php?q=' + encodeURIComponent(q))
        .then(data => {
          const list = data.stakeholders || [];
          stakeholderResults.innerHTML = list.map(s =>
            `<button type="button" class="list-group-item list-group-item-action small stakeholder-option"
                     data-id="${s.id}" data-name="${s.full_name.replace(/"/g,'&quot;')}">
               ${s.full_name} <span class="text-muted">(${s.email})</span>
             </button>`
          ).join('');
          stakeholderResults.querySelectorAll('.stakeholder-option').forEach(btn => {
            btn.addEventListener('click', function () {
              stakeholderIdField.value = btn.getAttribute('data-id');
              stakeholderSearch.value = btn.getAttribute('data-name');
              stakeholderResults.innerHTML = '';
            });
          });
        });
    }, 300);
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#stakeholderSearch') && !e.target.closest('#stakeholderResults')) {
      stakeholderResults.innerHTML = '';
    }
  });

  document.getElementById('manualForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!stakeholderIdField.value) {
      Swal.fire('Missing stakeholder', 'Please search and select a stakeholder first.', 'warning');
      return;
    }
    appPost(window.APP_URL + '/modules/attendance/ajax_manual.php', Object.fromEntries(new FormData(this)))
      .then(data => {
        if (data.success) {
          appToast('success', data.message);
          stakeholderSearch.value = '';
          stakeholderIdField.value = '';
          loadTable(); refreshStats();
        } else if (!data.session_expired) {
          Swal.fire('Error', data.message, 'error');
        }
      });
  });
})();
