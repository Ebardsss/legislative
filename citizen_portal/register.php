<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
if(isLoggedIn())redirect(appUrl('dashboard.php'));
$flash=getFlashMessages();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Citizen Registration | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Google Fonts matching Executive Landing Page -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --primary-navy: #0F2137;
    --primary-navy-dark: #071426;
    --primary-navy-light: #1A3A5C;
    --gold-primary: #B8860B;
    --gold-light: #D4AF37;
    --gold-accent: #E5C07B;
    --font-serif: 'Cinzel', serif;
    --font-sans: 'Plus Jakarta Sans', sans-serif;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    position: relative;
    min-height: 100vh;
    background-color: #F8FAFC;
    background-image: 
        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(212, 175, 55, 0.08) 0%, transparent 60%),
        radial-gradient(circle at 90% 90%, rgba(15, 33, 55, 0.03) 0%, transparent 50%),
        linear-gradient(180deg, #F8FAFC 0%, #F1F5F9 100%);
    font-family: var(--font-sans);
    color: #1E293B;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    overflow-x: hidden;
}

/* Manila Seal Full Page Watermark Background */
body::before {
    content: '';
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: min(860px, 92vw);
    height: min(860px, 92vw);
    background-image: url('<?= e(appUrl("assets/images/manila.png")) ?>');
    background-repeat: no-repeat;
    background-position: center center;
    background-size: contain;
    opacity: 0.08;
    pointer-events: none;
    z-index: 0;
    filter: drop-shadow(0 15px 40px rgba(0, 0, 0, 0.15));
}

/* Top Quick Navigation */
.reg-top-bar {
    width: 100%;
    max-width: 760px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    position: relative;
    z-index: 2;
}

.reg-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.825rem;
    font-weight: 600;
    color: #64748B;
    text-decoration: none;
    transition: all 0.2s ease;
}

.reg-back-link:hover {
    color: var(--primary-navy);
    transform: translateX(-2px);
}

.reg-back-link i {
    color: var(--gold-primary);
    font-size: 0.95rem;
}

/* Clean Centered Registration Card */
.reg-card {
    width: 100%;
    max-width: 760px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(16px);
    border-radius: 20px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 20px 50px rgba(15, 28, 52, 0.08), 0 2px 6px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    position: relative;
    z-index: 2;
    animation: fadeInCard 0.5s ease-out;
}

/* Subtle Watermark Inside the Card */
.reg-card::before {
    content: '';
    position: absolute;
    right: -60px;
    bottom: -60px;
    width: 320px;
    height: 320px;
    background-image: url('<?= e(appUrl("assets/images/manila.png")) ?>');
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    opacity: 0.04;
    pointer-events: none;
    z-index: 0;
}

@keyframes fadeInCard {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Gold & Navy Header Stripe */
.reg-card-stripe {
    height: 5px;
    background: linear-gradient(90deg, #071426 0%, #D4AF37 50%, #071426 100%);
    position: relative;
    z-index: 1;
}

/* Card Header Section */
.reg-header {
    text-align: center;
    padding: 2.5rem 2rem 1.75rem;
    border-bottom: 1px solid #F1F5F9;
    background: linear-gradient(180deg, #FFFFFF 0%, #FAFBFD 100%);
    position: relative;
    z-index: 1;
}

.reg-seal {
    width: 74px;
    height: 74px;
    margin: 0 auto 1.15rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.reg-seal img {
    width: 74px !important;
    height: 74px !important;
    max-width: 74px !important;
    object-fit: contain;
    filter: drop-shadow(0 6px 14px rgba(0, 0, 0, 0.12));
}

.reg-eyebrow {
    font-family: var(--font-sans);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #B8860B;
    margin-bottom: 0.35rem;
    display: block;
}

.reg-title {
    font-family: var(--font-serif);
    font-size: clamp(1.6rem, 2.5vw, 2rem);
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 0.45rem;
    letter-spacing: 0.3px;
}

.reg-subtitle {
    font-size: 0.88rem;
    color: #64748B;
    max-width: 520px;
    margin: 0 auto;
    line-height: 1.55;
}

/* Card Body Section */
.reg-body {
    padding: 2rem 2.25rem 2.5rem;
    position: relative;
    z-index: 1;
}

/* Section Dividers with Badges */
.form-section-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #0F2137;
    margin: 1.5rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #F1F5F9;
}

.form-section-header:first-of-type {
    margin-top: 0;
}

.form-section-header i {
    color: var(--gold-primary);
    font-size: 0.95rem;
}

/* Form Controls & Inputs */
.form-label-custom {
    font-size: 0.825rem;
    font-weight: 650;
    color: #1E293B;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.form-label-custom .text-req {
    color: #DC2626;
    font-size: 0.85rem;
}

.input-icon-group {
    position: relative;
}

.input-icon-group i.input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 0.95rem;
    pointer-events: none;
    transition: color 0.25s ease;
}

.input-icon-group .form-control-custom,
.input-icon-group .form-select-custom {
    width: 100%;
    height: 46px;
    padding: 0.65rem 1rem 0.65rem 2.65rem;
    font-size: 0.92rem;
    color: #0F172A;
    background-color: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-family: var(--font-sans);
    transition: all 0.25s ease;
}

.input-icon-group .form-control-custom:focus,
.input-icon-group .form-select-custom:focus {
    background-color: #FFFFFF;
    border-color: #0F2137;
    box-shadow: 0 0 0 3.5px rgba(15, 33, 55, 0.1);
    outline: none;
}

.input-icon-group .form-control-custom:focus ~ i.input-icon {
    color: var(--gold-primary);
}

.input-icon-group .btn-pwd-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #94A3B8;
    font-size: 1rem;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
    transition: color 0.2s ease;
}

.input-icon-group .btn-pwd-toggle:hover {
    color: #0F172A;
}

/* Password Hint Box */
.pwd-hint-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    font-size: 0.785rem;
    color: #64748B;
    margin-top: 0.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.pwd-hint-box i {
    color: var(--gold-primary);
    font-size: 0.95rem;
    margin-top: 1px;
}

/* Checkboxes */
.form-check-custom {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin-top: 0.85rem;
}

.form-check-custom input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: #0F2137;
    cursor: pointer;
}

.form-check-custom label {
    font-size: 0.84rem;
    color: #475569;
    line-height: 1.5;
    cursor: pointer;
}

/* Submit Button */
.btn-register-action {
    width: 100%;
    height: 50px;
    background: linear-gradient(135deg, #071426 0%, #0F2137 60%, #1A3A5C 100%);
    border: 1px solid #071426;
    border-radius: 12px;
    color: #FFFFFF;
    font-family: var(--font-sans);
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    cursor: pointer;
    margin-top: 1.5rem;
    box-shadow: 0 4px 15px rgba(7, 20, 38, 0.25);
    transition: all 0.3s ease;
}

.btn-register-action:hover {
    background: linear-gradient(135deg, #0F2137 0%, #B8860B 100%);
    border-color: #B8860B;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(184, 134, 11, 0.35);
}

/* Form Footer */
.reg-card-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #F1F5F9;
    font-size: 0.85rem;
    color: #64748B;
}

.reg-card-footer a {
    color: #0F2137;
    font-weight: 700;
    text-decoration: underline;
    transition: color 0.2s ease;
}

.reg-card-footer a:hover {
    color: var(--gold-primary);
}

/* Page Footer Signature */
.reg-page-footer {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.775rem;
    color: #94A3B8;
    letter-spacing: 0.3px;
}

@media (max-width: 768px) {
    body {
        padding: 1.5rem 0.85rem;
    }
    .reg-header {
        padding: 2rem 1.25rem 1.5rem;
    }
    .reg-body {
        padding: 1.5rem 1.25rem 2rem;
    }
}
</style>
</head>
<body>

<!-- Top Navigation -->
<div class="reg-top-bar">
    <a href="<?= e(appUrl('index.php')) ?>" class="reg-back-link">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Citizen Portal</span>
    </a>
    <a href="<?= e(appUrl('login.php')) ?>" class="reg-back-link">
        <span>Already have an account? <strong>Sign In</strong></span>
        <i class="bi bi-box-arrow-in-right"></i>
    </a>
</div>

<!-- Main Clean Registration Card -->
<div class="reg-card">
    <div class="reg-card-stripe"></div>

    <div class="reg-header">
        <div class="reg-seal">
            <img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City of Manila Seal">
        </div>
        <span class="reg-eyebrow">CITY OF MANILA &bull; CITIZEN REGISTRATION</span>
        <h1 class="reg-title">Create Citizen Account</h1>
        <p class="reg-subtitle">Register a public citizen account for transparent access to city ordinances, hearing schedules, voting decisions, and civic feedback.</p>
    </div>

    <div class="reg-body">
        <?php foreach($flash as $m): ?>
            <div class="alert alert-<?= e($m['type']) ?> d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><?= e($m['message']) ?></div>
            </div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(appUrl('auth/process_register.php')) ?>" novalidate>
            <?= csrfField() ?>

            <!-- Section 1: Account Profile -->
            <div class="form-section-header">
                <i class="bi bi-person-badge"></i>
                <span>Personal & Account Information</span>
            </div>

            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label-custom" for="full_name">
                        Full Legal Name <span class="text-req">*</span>
                    </label>
                    <div class="input-icon-group">
                        <input type="text" id="full_name" name="full_name" class="form-control-custom" placeholder="e.g., Juan Dela Cruz" required maxlength="150" autocomplete="name">
                        <i class="bi bi-person input-icon"></i>
                    </div>
                </div>

                <div class="col-md-5">
                    <label class="form-label-custom" for="username">
                        Username <span class="text-req">*</span>
                    </label>
                    <div class="input-icon-group">
                        <input type="text" id="username" name="username" class="form-control-custom" placeholder="e.g., juandelacruz" required maxlength="40" autocomplete="username">
                        <i class="bi bi-at input-icon"></i>
                    </div>
                </div>

                <div class="col-md-7">
                    <label class="form-label-custom" for="email">
                        Email Address <span class="text-req">*</span>
                    </label>
                    <div class="input-icon-group">
                        <input type="email" id="email" name="email" class="form-control-custom" placeholder="juan@example.com" required maxlength="150" autocomplete="email">
                        <i class="bi bi-envelope input-icon"></i>
                    </div>
                </div>

                <div class="col-md-5">
                    <label class="form-label-custom" for="phone">
                        Phone Number
                    </label>
                    <div class="input-icon-group">
                        <input type="tel" id="phone" name="phone" class="form-control-custom" placeholder="0917 123 4567" maxlength="50" autocomplete="tel">
                        <i class="bi bi-telephone input-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Section 2: Address & Residence -->
            <div class="form-section-header mt-4">
                <i class="bi bi-geo-alt"></i>
                <span>Address & Residence (City of Manila)</span>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label-custom" for="address">
                        Street Address / Building / House No.
                    </label>
                    <div class="input-icon-group">
                        <input type="text" id="address" name="address" class="form-control-custom" placeholder="e.g., 123 Rizal Avenue" maxlength="255" autocomplete="street-address">
                        <i class="bi bi-pin-map input-icon"></i>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom" for="district">
                        District
                    </label>
                    <div class="input-icon-group">
                        <input type="text" id="district" name="district" class="form-control-custom" placeholder="e.g., District 1" maxlength="100">
                        <i class="bi bi-compass input-icon"></i>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom" for="barangay">
                        Barangay
                    </label>
                    <div class="input-icon-group">
                        <input type="text" id="barangay" name="barangay" class="form-control-custom" placeholder="e.g., Barangay 128" maxlength="150">
                        <i class="bi bi-buildings input-icon"></i>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom" for="preferred_contact">
                        Preferred Contact
                    </label>
                    <div class="input-icon-group">
                        <select id="preferred_contact" name="preferred_contact" class="form-select-custom">
                            <option value="Portal" selected>Portal Notifications</option>
                            <option value="Email">Email</option>
                            <option value="Phone">Phone / SMS</option>
                        </select>
                        <i class="bi bi-bell input-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Section 3: Security & Credentials -->
            <div class="form-section-header mt-4">
                <i class="bi bi-shield-lock"></i>
                <span>Account Security</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-custom" for="password">
                        Password <span class="text-req">*</span>
                    </label>
                    <div class="input-icon-group">
                        <input type="password" id="password" name="password" class="form-control-custom" required minlength="10" autocomplete="new-password" placeholder="Create password">
                        <i class="bi bi-lock input-icon"></i>
                        <button type="button" class="btn-pwd-toggle" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom" for="confirm_password">
                        Confirm Password <span class="text-req">*</span>
                    </label>
                    <div class="input-icon-group">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control-custom" required minlength="10" autocomplete="new-password" placeholder="Confirm password">
                        <i class="bi bi-shield-check input-icon"></i>
                        <button type="button" class="btn-pwd-toggle" onclick="togglePassword('confirm_password', this)" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="pwd-hint-box">
                <i class="bi bi-info-circle-fill"></i>
                <span>Password must be at least <strong>10 characters</strong> and contain at least one uppercase letter (A-Z), one lowercase letter (a-z), and one number (0-9).</span>
            </div>

            <!-- Section 4: Privacy & Consent -->
            <div class="form-check-custom mt-4">
                <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required>
                <label for="privacy_consent">
                    I agree that my account information will be processed securely to provide legislative services, notifications, and civic verification in accordance with the City of Manila Data Privacy Charter. <span class="text-req">*</span>
                </label>
            </div>

            <div class="form-check-custom">
                <input type="checkbox" id="terms_acceptance" name="terms_acceptance" value="1" required>
                <label for="terms_acceptance">
                    I accept the <a href="#" class="text-dark fw-bold text-decoration-underline" onclick="event.preventDefault(); alert('Legislative Citizen Portal Terms of Use:\n\n1. Public Access: Verified citizens may inspect public ordinances, schedules, and official voting decisions.\n2. Responsible Engagement: Citizen inquiries and feedback must adhere to municipal civic conduct.\n3. Protection: Personal information is strictly safeguarded under Philippine Data Privacy laws.');">Terms of Use</a> for the Legislative Citizen Portal. <span class="text-req">*</span>
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-register-action">
                <i class="bi bi-check2-circle"></i>
                <span>Create Citizen Account</span>
            </button>

            <div class="reg-card-footer">
                Already registered? <a href="<?= e(appUrl('login.php')) ?>">Sign in to your Citizen Account</a>
            </div>
        </form>
    </div>
</div>

<!-- Footer Signature -->
<div class="reg-page-footer">
    <i class="bi bi-shield-check"></i> City Council of Manila &bull; Legislative Citizen Portal &copy; <?= date('Y') ?>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>

</body>
</html>
