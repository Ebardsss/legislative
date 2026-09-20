<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$modulePermission=$modulePermission??'cepfms.dashboard.view';
requireCefPermission($modulePermission);

$pageTitle = $moduleTitle ?? 'CEPFMS Module';
$activeMenu = $moduleKey ?? '';

$extraCss = [
    appUrl('assets/css/module-navigation.css'),
];

$moduleFeatures = $moduleFeatures ?? [];
$workflowSteps = $workflowSteps ?? [];
$quickActions = $quickActions ?? [];
$nextModule = $nextModule ?? null;
$previousModule = $previousModule ?? null;
$moduleTable = $moduleTable ?? '';
$problemAddressed = $problemAddressed ?? '';
$transparencyFocus = $transparencyFocus ?? '';


$recordCount = $moduleTable !== ''
    ? countTableRows($moduleTable)
    : 0;

include __DIR__ . '/../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="module-page-header">
            <div>
                <div class="module-eyebrow">
                    <i class="bi <?= e($moduleIcon ?? 'bi-grid') ?>"></i>
                    Citizen Engagement Navigation
                </div>

                <h1><?= e($moduleTitle ?? 'CEPFMS Module') ?></h1>

                <p>
                    <?= e(
                        $moduleDescription
                        ?? 'Client-ready module navigation and workflow preview.'
                    ) ?>
                </p>
            </div>

            <div class="module-header-actions">
                <a
                    href="<?= e(citizenPortalUrl('index.php')) ?>"
                    class="btn btn-outline-secondary"
                    target="_blank"
                >
                    <i class="bi bi-globe2"></i>
                    Citizen Portal
                </a>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-navigation-demo
                >
                    <i class="bi bi-plus-circle"></i>
                    <?= e($primaryAction ?? 'New Record') ?>
                </button>
            </div>
        </section>

        <?php if ($problemAddressed !== ''): ?>
            <section class="module-problem-banner">
                <span><i class="bi bi-bullseye"></i></span>
                <div>
                    <small>Problem Addressed</small>
                    <strong><?= e($problemAddressed) ?></strong>
                    <?php if ($transparencyFocus !== ''): ?>
                        <p><?= e($transparencyFocus) ?></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="module-context-strip">
            <div>
                <i class="bi bi-inboxes"></i>
                <span><small>Centralized</small><strong>One Civic Feedback Platform</strong></span>
            </div>
            <div>
                <i class="bi bi-eye"></i>
                <span><small>Transparent</small><strong>Status & Progress Visibility</strong></span>
            </div>
            <div>
                <i class="bi bi-arrow-repeat"></i>
                <span><small>Coordinated</small><strong>Structured Routing & Response</strong></span>
            </div>
            <div>
                <i class="bi bi-cloud-check"></i>
                <span><small>Deployment</small><strong>Web-Based / Cloud-Ready</strong></span>
            </div>
        </section>

        <section class="row g-3 mb-4">
            <?php
            $summaryCards = [
                [
                    'value' => $recordCount,
                    'label' => 'Centralized Records',
                    'icon' => $moduleIcon ?? 'bi-grid',
                    'class' => '',
                ],
                [
                    'value' => 0,
                    'label' => 'Pending Review',
                    'icon' => 'bi-hourglass-split',
                    'class' => 'pending',
                ],
                [
                    'value' => 0,
                    'label' => 'Assigned',
                    'icon' => 'bi-person-check',
                    'class' => 'assigned',
                ],
                [
                    'value' => 0,
                    'label' => 'Completed',
                    'icon' => 'bi-check-circle',
                    'class' => 'completed',
                ],
            ];
            ?>

            <?php foreach ($summaryCards as $card): ?>
                <div class="col-sm-6 col-xl-3">
                    <div
                        class="module-summary-card
                        <?= e($card['class']) ?>"
                    >
                        <span class="summary-icon">
                            <i class="bi <?= e($card['icon']) ?>"></i>
                        </span>

                        <strong><?= (int)$card['value'] ?></strong>
                        <span><?= e($card['label']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <section class="module-panel h-100">
                    <div class="module-panel-heading">
                        <div>
                            <h2>
                                <i class="bi bi-grid-1x2"></i>
                                Module Capabilities
                            </h2>

                            <p>
                                Main pages and functions planned
                                for this module.
                            </p>
                        </div>
                    </div>

                    <div class="module-feature-grid">
                        <?php foreach ($moduleFeatures as $feature): ?>
                            <button
                                type="button"
                                class="module-feature-card"
                                data-navigation-demo
                            >
                                <span>
                                    <i class="bi <?= e($feature['icon']) ?>"></i>
                                </span>

                                <div>
                                    <strong><?= e($feature['title']) ?></strong>
                                    <small><?= e($feature['description']) ?></small>
                                </div>

                                <i class="bi bi-arrow-right"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="module-panel h-100">
                    <div class="module-panel-heading">
                        <div>
                            <h2>
                                <i class="bi bi-lightning-charge"></i>
                                Quick Actions
                            </h2>

                            <p>
                                Navigation shortcuts for the client demo.
                            </p>
                        </div>
                    </div>

                    <div class="module-quick-actions">
                        <?php foreach ($quickActions as $action): ?>
                            <button
                                type="button"
                                data-navigation-demo
                            >
                                <i class="bi <?= e($action['icon']) ?>"></i>

                                <span>
                                    <strong><?= e($action['title']) ?></strong>
                                    <small><?= e($action['description']) ?></small>
                                </span>
                            </button>
                        <?php endforeach; ?>

                        <?php if (!$quickActions): ?>
                            <div class="module-empty compact">
                                <i class="bi bi-lightning-charge"></i>
                                <strong>Quick actions ready</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="module-panel mb-4">
            <div class="module-panel-heading">
                <div>
                    <h2>
                        <i class="bi bi-diagram-3"></i>
                        Module Workflow
                    </h2>

                    <p>
                        Suggested record flow from intake to completion.
                    </p>
                </div>
            </div>

            <div class="module-workflow">
                <?php foreach ($workflowSteps as $index => $step): ?>
                    <div class="module-workflow-step">
                        <span><?= str_pad(
                            (string)($index + 1),
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) ?></span>

                        <div>
                            <strong><?= e($step['title']) ?></strong>
                            <small><?= e($step['description']) ?></small>
                        </div>

                        <?php if (
                            $index <
                            count($workflowSteps) - 1
                        ): ?>
                            <i class="bi bi-arrow-right"></i>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="module-panel">
            <div class="module-panel-heading">
                <div>
                    <h2>
                        <i class="bi bi-table"></i>
                        Record Workspace
                    </h2>

                    <p>
                        The searchable table and AJAX actions
                        will be connected during the backend phase.
                    </p>
                </div>
            </div>

            <div class="module-empty">
                <i class="bi <?= e($moduleIcon ?? 'bi-grid') ?>"></i>

                <strong>
                    <?= e($moduleTitle ?? 'Module') ?>
                    navigation is complete
                </strong>

                <span>
                    The page structure, module workflow,
                    quick actions, and shared navigation are ready.
                    Database CRUD is intentionally postponed.
                </span>
            </div>
        </section>

        <div class="module-page-navigation">
            <?php if ($previousModule): ?>
                <a href="<?= e(appUrl($previousModule['href'])) ?>">
                    <i class="bi bi-arrow-left"></i>

                    <span>
                        <small>Previous Module</small>
                        <strong><?= e($previousModule['label']) ?></strong>
                    </span>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>

            <?php if ($nextModule): ?>
                <a
                    href="<?= e(appUrl($nextModule['href'])) ?>"
                    class="next"
                >
                    <span>
                        <small>Next Module</small>
                        <strong><?= e($nextModule['label']) ?></strong>
                    </span>

                    <i class="bi bi-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-navigation-demo]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Navigation Phase Complete',
                        text:
                            'This action will be connected during the backend and AJAX development phase.'
                    });
                }
            });
        });
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
