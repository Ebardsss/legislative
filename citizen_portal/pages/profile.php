<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

$pdo=db();
$pageTitle='My Profile';
$activeMenu='profile';
$extraCss=[appUrl('assets/css/account.css')];

$q=$pdo->prepare(
    'SELECT
        u.id,u.username,u.full_name,u.email,u.phone,u.created_at,u.last_login_at,
        r.name role_name,
        cp.address,cp.district,cp.barangay,cp.preferred_contact,
        cp.privacy_consent_at,cp.terms_accepted_at,
        cs.password_changed_at,cs.last_profile_update_at
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN citizen_portal_profiles cp ON cp.user_id=u.id
     LEFT JOIN citizen_portal_account_security cs ON cs.user_id=u.id
     WHERE u.id=:user
     LIMIT 1'
);
$q->execute([':user'=>currentUserId()]);
$u=$q->fetch();

$hasProfile=!empty($u['privacy_consent_at'])&&!empty($u['terms_accepted_at']);

include __DIR__.'/../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<span class="eyebrow">MY ACCOUNT</span>
<h1>Citizen Profile</h1>
<p>Update your contact details and manage the information used by the Citizen Portal.</p>
</div>
<a class="btn btn-outline-primary" href="<?= e(appUrl('pages/account_security.php')) ?>"><i class="bi bi-shield-lock"></i> Account Security</a>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card">
<div class="card-header">Edit Profile</div>
<div class="card-body">
<form method="post" action="<?= e(appUrl('auth/update_profile.php')) ?>">
<?= csrfField() ?>

<div class="row g-3">
<div class="col-md-8">
<label class="form-label">Full Name</label>
<input class="form-control" name="full_name" value="<?= e($u['full_name']) ?>" maxlength="150" required>
</div>

<div class="col-md-4">
<label class="form-label">Username</label>
<input class="form-control" name="username" value="<?= e($u['username']) ?>" maxlength="40" required>
</div>

<div class="col-md-8">
<label class="form-label">Email Address</label>
<input type="email" class="form-control" name="email" value="<?= e($u['email']) ?>" maxlength="150" required>
</div>

<div class="col-md-4">
<label class="form-label">Phone</label>
<input class="form-control" name="phone" value="<?= e($u['phone']) ?>" maxlength="50">
</div>

<div class="col-12">
<label class="form-label">Address</label>
<input class="form-control" name="address" value="<?= e($u['address']) ?>" maxlength="255">
</div>

<div class="col-md-4">
<label class="form-label">District</label>
<input class="form-control" name="district" value="<?= e($u['district']) ?>" maxlength="100">
</div>

<div class="col-md-4">
<label class="form-label">Barangay</label>
<input class="form-control" name="barangay" value="<?= e($u['barangay']) ?>" maxlength="150">
</div>

<div class="col-md-4">
<label class="form-label">Preferred Contact</label>
<select class="form-select" name="preferred_contact">
<?php foreach(['Portal','Email','Phone'] as $contact): ?>
<option <?= ($u['preferred_contact']?:'Portal')===$contact?'selected':'' ?>><?= e($contact) ?></option>
<?php endforeach; ?>
</select>
</div>

<?php if(!$hasProfile): ?>
<div class="col-12">
<div class="account-consent">
<div class="form-check">
<input class="form-check-input" type="checkbox" name="privacy_consent" value="1" id="privacyConsent" required>
<label class="form-check-label" for="privacyConsent">I agree that these details will be used to provide Citizen Portal services.</label>
</div>
<div class="form-check mt-2">
<input class="form-check-input" type="checkbox" name="terms_acceptance" value="1" id="termsAcceptance" required>
<label class="form-check-label" for="termsAcceptance">I accept the Citizen Portal terms of use.</label>
</div>
</div>
</div>
<?php endif; ?>

<div class="col-12 d-flex justify-content-end">
<button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Profile</button>
</div>
</div>
</form>
</div>
</div>
</div>

<div class="col-xl-4">
<div class="card portal-card mb-4">
<div class="card-header">Account Information</div>
<div class="card-body">
<div class="public-side-list">
<div><small>Account Role</small><strong><?= e($u['role_name']) ?></strong></div>
<div><small>Registered</small><strong><?= formatDateTime($u['created_at']) ?></strong></div>
<div><small>Last Login</small><strong><?= formatDateTime($u['last_login_at']) ?></strong></div>
<div><small>Last Profile Update</small><strong><?= formatDateTime($u['last_profile_update_at']) ?></strong></div>
<div><small>Password Changed</small><strong><?= formatDateTime($u['password_changed_at']) ?></strong></div>
</div>
</div>
</div>

<div class="safe-note">
<i class="bi bi-info-circle"></i>
<div>
<strong>Shared citizen account</strong>
<span>Updated name, email, phone and address are also synchronized with your linked PHCMS stakeholder profile when one exists. Existing CEPFMS submissions keep their original contact snapshot.</span>
</div>
</div>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
