<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

$pdo=db();
$pageTitle='Account Security';
$activeMenu='profile';
$extraCss=[appUrl('assets/css/account.css')];

$security=portalSecurityState(currentUserId());

include __DIR__.'/../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('pages/profile.php')) ?>"><i class="bi bi-arrow-left"></i> My Profile</a>
<h1>Account Security</h1>
<p>Change your password or deactivate only your Citizen Portal access.</p>
</div>
<span class="public-status good">Protected</span>
</div>

<div class="row g-4">
<div class="col-xl-7">
<div class="card portal-card">
<div class="card-header">Change Password</div>
<div class="card-body">
<form method="post" action="<?= e(appUrl('auth/change_password.php')) ?>">
<?= csrfField() ?>

<div class="mb-3">
<label class="form-label">Current Password</label>
<input type="password" class="form-control" name="current_password" required autocomplete="current-password">
</div>

<div class="row g-3">
<div class="col-md-6">
<label class="form-label">New Password</label>
<input type="password" class="form-control" name="new_password" required minlength="10" autocomplete="new-password">
</div>
<div class="col-md-6">
<label class="form-label">Confirm New Password</label>
<input type="password" class="form-control" name="confirm_password" required minlength="10" autocomplete="new-password">
</div>
</div>

<div class="form-text mt-2">Use at least 10 characters with uppercase, lowercase and a number.</div>

<button class="btn btn-primary mt-3"><i class="bi bi-key"></i> Change Password</button>
</form>
</div>
</div>
</div>

<div class="col-xl-5">
<div class="card portal-card mb-4">
<div class="card-header">Security Status</div>
<div class="card-body">
<div class="public-side-list">
<div><small>Session Security Version</small><strong><?= (int)($security['session_version']??1) ?></strong></div>
<div><small>Last Password Change</small><strong><?= formatDateTime($security['password_changed_at']??null) ?></strong></div>
</div>
<div class="safe-note mt-3">
<i class="bi bi-shield-check"></i>
<div><strong>Session invalidation</strong><span>Changing your password signs out other Citizen Portal sessions using the old security version.</span></div>
</div>
</div>
</div>

<div class="card account-danger-card">
<div class="card-header">Deactivate Citizen Portal Access</div>
<div class="card-body">
<p class="small text-muted">This does not delete your shared legislative account or records. It only disables access to this Citizen Portal until an administrator reactivates it.</p>
<form method="post" action="<?= e(appUrl('auth/deactivate_portal.php')) ?>">
<?= csrfField() ?>
<label class="form-label">Current Password</label>
<input type="password" class="form-control mb-2" name="current_password" required autocomplete="current-password">

<label class="form-label">Type DEACTIVATE to confirm</label>
<input class="form-control mb-3" name="confirmation" required autocomplete="off">

<button class="btn btn-outline-danger"><i class="bi bi-person-x"></i> Deactivate Portal Access</button>
</form>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
