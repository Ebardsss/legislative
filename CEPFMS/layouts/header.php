<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$extraCss = $extraCss ?? [];
$extraJs = $extraJs ?? [];

$portalBaseUrl = rtrim(dirname(defined('APP_URL') ? APP_URL : 'http://localhost/legislative/CEPFMS'), '/');
$subsystems = [
    ['label' => 'ORLMS', 'short' => 'ORLMS', 'name' => 'Ordinance & Resolution Life Cycle', 'url' => $portalBaseUrl . '/ORLMS/', 'icon' => 'bi-file-earmark-text', 'active' => false],
    ['label' => 'SLMMS', 'short' => 'SLMMS', 'name' => 'Session & Legislative Meeting', 'url' => $portalBaseUrl . '/subsystem_info.php?code=slmms', 'icon' => 'bi-calendar-event', 'active' => false],
    ['label' => 'LACMS', 'short' => 'LACMS', 'name' => 'Legislative Agenda & Calendar', 'url' => $portalBaseUrl . '/LACMS/', 'icon' => 'bi-calendar3', 'active' => false],
    ['label' => 'CMAS', 'short' => 'CMAS', 'name' => 'Committee Management & Assignment', 'url' => $portalBaseUrl . '/subsystem_info.php?code=cmas', 'icon' => 'bi-diagram-3', 'active' => false],
    ['label' => 'VQDSS', 'short' => 'VQDSS', 'name' => 'Voting, Quorum & Decisions', 'url' => $portalBaseUrl . '/vqdss/', 'icon' => 'bi-check2-square', 'active' => false],
    ['label' => 'LRDMS', 'short' => 'LRDMS', 'name' => 'Records & Document Management', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lrdms', 'icon' => 'bi-folder-check', 'active' => false],
    ['label' => 'LPH', 'short' => 'LPH', 'name' => 'Public Hearing & Consultation', 'url' => $portalBaseUrl . '/lph/', 'icon' => 'bi-people', 'active' => false],
    ['label' => 'LAHRS', 'short' => 'LAHRS', 'name' => 'Archives & Historical Repository', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lahrs', 'icon' => 'bi-archive', 'active' => false],
    ['label' => 'LRPAIES', 'short' => 'LRPAIES', 'name' => 'Research, Policy & Impact Evaluation', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lrpaies', 'icon' => 'bi-graph-up-arrow', 'active' => false],
    ['label' => 'CEPFMS', 'short' => 'CEPFMS', 'name' => 'Citizen Engagement & Feedback', 'url' => $portalBaseUrl . '/CEPFMS/', 'icon' => 'bi-chat-square-heart', 'active' => true],
    ['label' => 'PORTAL', 'short' => 'PORTAL', 'name' => 'Legislative Citizen Portal', 'url' => $portalBaseUrl . '/citizen_portal/', 'icon' => 'bi-person-badge', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= e($pageTitle) ?> | <?= e(APP_SHORT_NAME) ?>
    </title>

    <link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
    <link rel="apple-touch-icon" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">

    <link
        rel="stylesheet"
        href="<?= e(vendorAsset(
            'bootstrap/bootstrap.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
        )) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(vendorAsset(
            'bootstrap-icons/bootstrap-icons.css',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
        )) ?>"
    >

    <!-- Google Fonts matching landing page and Subsystem 7 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?= e(appUrl('assets/css/layout.css?v=' . time())) ?>"
    >
    <link
        rel="stylesheet"
        href="<?= e(appUrl('assets/css/cepfms-shell.css?v=' . time())) ?>"
    >

    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css . (str_contains($css, '?') ? '&' : '?') . 'v=' . time()) ?>">
    <?php endforeach; ?>

    <style>
        /* ============================================================
           PRO ENTERPRISE MODAL DESIGN SYSTEM (CEPFMS)
           Ensures modals look sleek, compact, centered, and never overwhelm the screen.
           ============================================================ */
        .modal-dialog {
            max-width: 620px !important;
            margin: 1.75rem auto !important;
        }
        .modal-dialog.modal-sm {
            max-width: 440px !important;
        }
        .modal-dialog.modal-lg,
        .modal-dialog.modal-xl,
        .modal-lg,
        .modal-xl {
            max-width: 680px !important;
        }
        .modal-dialog-centered {
            display: flex !important;
            align-items: center !important;
            min-height: calc(100% - 3.5rem) !important;
        }
        .modal-content {
            border-radius: 14px !important;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.04) !important;
            overflow: hidden !important;
            background: #ffffff !important;
        }
        .modal-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
            color: #ffffff !important;
            padding: 0.75rem 1.25rem !important;
            border-bottom: 2px solid #3b82f6 !important;
        }
        .modal-header .modal-title {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            letter-spacing: 0.2px;
            margin: 0 !important;
        }
        .modal-header small, .modal-header .text-muted, .modal-header p {
            color: rgba(255, 255, 255, 0.7) !important;
            font-size: 0.72rem !important;
            margin: 0 !important;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) !important;
            opacity: 0.8 !important;
            padding: 0.5rem !important;
        }
        .modal-header .btn-close:hover {
            opacity: 1 !important;
        }
        .modal-body {
            padding: 1.15rem 1.25rem !important;
            max-height: calc(85vh - 110px) !important;
            overflow-y: auto !important;
            background: #ffffff !important;
        }
        .modal-footer {
            padding: 0.65rem 1.25rem !important;
            background-color: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
        }
        .modal .form-label {
            font-size: 0.76rem !important;
            font-weight: 600 !important;
            color: #334155 !important;
            margin-bottom: 0.25rem !important;
        }
        .modal .form-control,
        .modal .form-select {
            font-size: 0.82rem !important;
            padding: 0.35rem 0.65rem !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
        }
        .modal .form-control:focus,
        .modal .form-select:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }
        .modal textarea.form-control {
            min-height: 70px !important;
            max-height: 160px !important;
        }
        @media (max-width: 768px) {
            .modal-dialog,
            .modal-dialog.modal-lg,
            .modal-dialog.modal-xl,
            .modal-lg,
            .modal-xl {
                max-width: 95% !important;
                margin: 0.75rem auto !important;
            }
        }
    </style>
</head>

<body>

<header class="topbar orlms-topbar">
    <button
        type="button"
        class="topbar-menu-button orlms-menu-button"
        id="sidebarToggle"
        aria-label="Toggle navigation"
        title="Toggle Sidebar"
    >
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-actions orlms-topbar-actions">
        <div class="dropdown">
            <button
                type="button"
                class="topbar-system-button orlms-system-switcher dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span>Subsystems</span>
            </button>

            <div class="dropdown-menu dropdown-menu-end subsystem-menu orlms-subsystem-menu shadow-lg" style="max-height: 85vh; overflow-y: auto; z-index: 1060;">
                <a href="<?= e($portalBaseUrl . '/index.php') ?>" class="subsystem-menu-item orlms-subsystem-item orlms-subsystem-item-portal">
                    <div><strong>PORTAL</strong><small>Main Landing Page</small></div>
                </a>

                <?php foreach ($subsystems as $system): ?>
                    <a
                        href="<?= e($system['url']) ?>"
                        class="subsystem-menu-item orlms-subsystem-item <?= $system['label'] === APP_SHORT_NAME ? 'active' : '' ?>"
                    >
                        <div>
                            <strong><?= e($system['label']) ?></strong>
                            <small><?= e($system['name']) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dropdown">
            <button
                class="topbar-user-button orlms-user-button dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="topbar-avatar orlms-avatar">
                    <?= e(strtoupper(substr(
                        currentUserName(),
                        0,
                        1
                    ))) ?>
                </span>

                <span class="topbar-user-copy orlms-user-copy">
                    <strong><?= e(currentUserName()) ?></strong>
                    <small><?= e(currentRoleLabel()) ?></small>
                </span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" style="font-size: 0.85rem; z-index: 1060; border-radius: 12px; min-width: 210px;">
                <?php if ($currentUserEmail !== ''): ?>
                <li>
                    <span class="dropdown-item-text small text-muted">
                        <?= e($currentUserEmail) ?>
                    </span>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <?php endif; ?>

                <li>
                    <a
                        class="dropdown-item py-2"
                        href="<?= e(appUrl('dashboard.php')) ?>"
                    >
                        <i class="bi bi-speedometer2 me-2 text-warning"></i>
                        Dashboard
                    </a>
                </li>

                <li>
                    <a
                        class="dropdown-item py-2"
                        href="<?= e(citizenPortalUrl('index.php')) ?>"
                        target="_blank"
                    >
                        <i class="bi bi-globe2 me-2 text-warning"></i>
                        Citizen Portal
                    </a>
                </li>

                <li><hr class="dropdown-divider my-1"></li>

                <li>
                    <a
                        class="dropdown-item py-2 text-danger"
                        href="<?= e(appUrl('logout.php')) ?>"
                    >
                        <i class="bi bi-box-arrow-right me-2"></i>
                        Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
