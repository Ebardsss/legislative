<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$modulePermission=$modulePermission??'cepfms.dashboard.view';
requireCefPermission($modulePermission);

$pageTitle = $moduleTitle ?? 'CEPFMS Administration';
$activeMenu = $moduleKey ?? '';

$extraCss = [
    appUrl('assets/css/dashboard.css'),
];

include __DIR__ . '/../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="dashboard-page-header">
            <div>
                <div class="dashboard-eyebrow">
                    <i class="bi <?= e($moduleIcon ?? 'bi-grid') ?>"></i>
                    CEPFMS Administration
                </div>

                <h1>
                    <?= e($moduleTitle ?? 'Administration') ?>
                </h1>

                <p>
                    <?= e(
                        $moduleDescription
                        ?? 'Administrative navigation is ready.'
                    ) ?>
                </p>
            </div>

            <a
                href="<?= e(appUrl('dashboard.php')) ?>"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
        </section>

        <section class="placeholder-module-card">
            <span class="placeholder-module-icon">
                <i class="bi <?= e($moduleIcon ?? 'bi-grid') ?>"></i>
            </span>

            <h2>
                <?= e($moduleTitle ?? 'Administration') ?>
            </h2>

            <p>
                Shared authentication, responsive layout,
                role-based navigation, and subsystem access
                are already connected. Detailed administration
                functions are intentionally postponed.
            </p>

            <div class="placeholder-status-list">
                <span>
                    <i class="bi bi-check-circle"></i>
                    Shared authentication connected
                </span>

                <span>
                    <i class="bi bi-check-circle"></i>
                    Navigation and layout connected
                </span>

                <span>
                    <i class="bi bi-clock"></i>
                    Backend administration postponed
                </span>
            </div>
        </section>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
