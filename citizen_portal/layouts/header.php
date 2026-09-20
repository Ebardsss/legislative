<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Citizen Dashboard';
$user = currentUser();
$portalFlash = getFlashMessages();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png')) ?>">
<link rel="shortcut icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Google Fonts matching landing page and subsystems -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css?v=' . time())) ?>">
<link rel="stylesheet" href="<?= e(appUrl('assets/css/orlms-shell.css?v=' . time())) ?>">
<?php if(!empty($extraCss)): foreach($extraCss as $css): ?>
<link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; endif; ?>
<script>
(function(){
    try {
        if (localStorage.getItem('citizen_sidebar_collapsed') === '1' && window.innerWidth > 1050) {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    } catch(e){}
})();
</script>
</head>
<body>
<script>
if (document.documentElement.classList.contains('sidebar-collapsed')) {
    document.body.classList.add('sidebar-collapsed');
}
</script>

<?php if($portalFlash): ?>
<div class="portal-flash-wrap">
    <?php foreach($portalFlash as $m): ?>
        <div class="alert alert-<?= e($m['type']) ?> shadow-sm mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i>
            <div><?= e($m['message']) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Executive Floating Topbar Actions (Matches ORLMS & CEPFMS - No Clunky Header Bar) -->
<header class="orlms-topbar">
    <button class="orlms-menu-button" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
        <i class="bi bi-list"></i>
    </button>

    <div class="orlms-topbar-actions">
        <!-- Quick Search -->
        <a class="orlms-topbar-icon" href="<?= e(appUrl('pages/search.php')) ?>" title="Search Public Records">
            <i class="bi bi-search"></i>
        </a>

        <!-- Notifications -->
        <a class="orlms-topbar-icon" href="<?= e(appUrl('pages/notifications.php')) ?>" title="Notifications">
            <i class="bi bi-bell"></i>
        </a>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="orlms-user-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="orlms-avatar"><i class="bi bi-person-fill"></i></span>
                <div class="orlms-user-copy">
                    <strong><?= e($user['full_name'] ?? 'Citizen User') ?></strong>
                    <small><?= e($user['role_name'] ?? 'Public Citizen') ?></small>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" style="font-size: 0.85rem; z-index: 1060; border-radius: 12px; min-width: 210px;">
                <li><a class="dropdown-item py-2" href="<?= e(appUrl('pages/profile.php')) ?>"><i class="bi bi-person me-2 text-warning"></i>My Profile</a></li>
                <li><a class="dropdown-item py-2" href="<?= e(appUrl('pages/account_security.php')) ?>"><i class="bi bi-shield-lock me-2 text-warning"></i>Account Security</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <form method="post" action="<?= e(appUrl('logout.php')) ?>" class="m-0">
                        <?= csrfField() ?>
                        <button class="dropdown-item py-2 text-danger" type="submit">
                            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
