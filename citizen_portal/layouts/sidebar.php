<?php
declare(strict_types=1);
$activeMenu = $activeMenu ?? 'dashboard';
$user = currentUser();

$navGroups = [
    'Overview' => [
        ['dashboard', 'Dashboard', 'bi-speedometer2', 'dashboard.php'],
        ['search', 'Search Records', 'bi-search', 'pages/search.php'],
    ],
    'Legislative Services' => [
        ['ordinances', 'Ordinances & Resolutions', 'bi-journal-text', 'modules/ordinances/index.php'],
        ['calendar', 'Agenda & Calendar', 'bi-calendar-event', 'modules/calendar/index.php'],
        ['voting', 'Voting Results', 'bi-check2-square', 'modules/voting/index.php'],
        ['hearings', 'Hearings & Consultations', 'bi-people', 'modules/hearings/index.php'],
    ],
    'Civic Engagement' => [
        ['engagement', 'Feedback & Tracking', 'bi-chat-square-heart', 'modules/engagement/index.php'],
        ['surveys', 'Consultation Surveys', 'bi-ui-checks-grid', 'modules/surveys/index.php'],
    ],
    'My Account' => [
        ['notifications', 'Notifications', 'bi-bell', 'pages/notifications.php'],
        ['profile', 'My Profile', 'bi-person', 'pages/profile.php'],
        ['security', 'Account Security', 'bi-shield-lock', 'pages/account_security.php'],
    ],
];
?>
<aside class="sidebar orlms-sidebar" id="sidebar">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City of Manila Seal" class="sidebar-logo">
        <div class="sidebar-brand-text">
            <strong>City of <b>MANILA</b></strong>
            <small>Legislative Citizen Portal</small>
        </div>
    </div>

    <!-- Navigation Groups -->
    <nav class="sidebar-nav">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
            <div class="sidebar-section-heading"><?= e($groupLabel) ?></div>
            <?php foreach ($items as [$key, $label, $icon, $url]): ?>
                <a class="sidebar-link <?= $activeMenu === $key ? 'active' : '' ?>" href="<?= e(appUrl($url)) ?>" title="<?= e($label) ?>">
                    <i class="bi <?= e($icon) ?>"></i>
                    <span><?= e($label) ?></span>
                    <?php if ($activeMenu === $key): ?><b class="active-indicator"></b><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar User Footer -->
    <div class="sidebar-user-footer">
        <div class="user-avatar-circle">
            <i class="bi bi-person-fill"></i>
        </div>
        <div class="user-info-text">
            <strong><?= e($user['full_name'] ?? 'Citizen User') ?></strong>
            <small><?= e($user['role_name'] ?? 'Public Citizen') ?></small>
        </div>
        <form method="post" action="<?= e(appUrl('logout.php')) ?>" class="m-0 ms-auto">
            <?= csrfField() ?>
            <button type="submit" class="btn-sidebar-logout" title="Sign Out">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
