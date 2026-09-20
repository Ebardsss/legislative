This folder holds local copies of third-party libraries (Bootstrap, jQuery,
DataTables, SweetAlert2, Chart.js, QR code libraries) so the app can run
100% offline once they're downloaded.

## Quick start (enables full offline operation)

While your machine has internet access (even briefly), visit:

    http://localhost/lph/tools/download-vendor-assets.php

or run it from the command line:

    php tools/download-vendor-assets.php

That's it. Every page already checks this folder first before falling back
to a CDN (see includes/functions.php::vendorAsset()), so:

- **Before** you run the downloader: the app works exactly as it does now,
  loading libraries from CDNs (requires internet).
- **After** you run the downloader: the app automatically switches to these
  local files — no code changes, no configuration, nothing else to do.

## Why this folder starts empty

Vendoring pre-built, minified third-party libraries safely requires an
actual, complete, byte-for-byte download — not something that should be
faked or partially reconstructed, since a single corrupted byte in a
minified JS file can silently break every page that loads it. The
downloader script above does a normal, complete HTTP download using your
own connection, which is the reliable way to do this.

## Manifest (what gets downloaded where)

| Local path                                          | Source                                             |
|-------------------------------------------------------|-----------------------------------------------------|
| bootstrap/bootstrap.min.css                          | Bootstrap 5.3.3 CSS                                  |
| bootstrap/bootstrap.bundle.min.js                    | Bootstrap 5.3.3 JS bundle (incl. Popper)             |
| bootstrap-icons/bootstrap-icons.css + fonts/*         | Bootstrap Icons 1.11.3                               |
| jquery/jquery-3.7.1.min.js                           | jQuery 3.7.1                                         |
| datatables/*                                          | DataTables 1.13.8 (+ Bootstrap 5 styling)            |
| sweetalert2/sweetalert2.min.js                       | SweetAlert2 11.10.7                                  |
| chartjs/chart.umd.min.js                             | Chart.js 4.4.4                                       |
| qrcodejs/qrcode.min.js                               | davidshimjs/qrcodejs 1.0.0 (stakeholder QR codes)    |
| html5-qrcode/html5-qrcode.min.js                     | html5-qrcode 2.3.8 (attendance camera scanner)       |

## Security note

`tools/download-vendor-assets.php` fetches external URLs — fine for local
setup, but not something you want reachable on a public production server.
Delete it (or the whole `tools/` folder) once you've run it.
