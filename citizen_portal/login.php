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
<title>Citizen Login | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Premium Google Fonts matching Landing Page (#subsystems) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-blue: #0f2137;
        --primary-blue-dark: #071426;
        --primary-blue-light: #1a3a5c;
        --primary-yellow: #b8860b;
        --primary-yellow-light: #d97706;
        --primary-white: #FFFFFF;
        --primary-gray: #F3F4F6;
        --gold-primary: #D4AF37;
        --gold-light: #E5C07B;
        --font-serif: 'Cinzel', serif;
        --font-sans: 'Plus Jakarta Sans', sans-serif;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; overflow: hidden; font-family: var(--font-sans); line-height: 1.6; }
    .login-wrapper { display: flex; height: 100vh; width: 100%; background: var(--primary-white); }
    .brand-side { flex: 1.1; background: linear-gradient(135deg, #071426 0%, #0f2137 55%, #1a3a5c 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 3rem; position: relative; overflow: hidden; min-height: 100vh; }
    .brand-side::before { content: ''; position: absolute; width: 500px; height: 500px; background: radial-gradient(circle, rgba(184, 134, 11, 0.18) 0%, transparent 70%); top: -150px; right: -150px; border-radius: 50%; }
    .brand-side::after { content: ''; position: absolute; width: 400px; height: 400px; background: radial-gradient(circle, rgba(184, 134, 11, 0.12) 0%, transparent 70%); bottom: -100px; left: -100px; border-radius: 50%; }
    .circle-decoration { position: absolute; border-radius: 50%; border: 2px solid rgba(184, 134, 11, 0.15); pointer-events: none; }
    .circle-1 { width: 300px; height: 300px; top: 10%; right: 5%; opacity: 0.4; }
    .circle-2 { width: 200px; height: 200px; bottom: 15%; left: 10%; opacity: 0.4; }
    .circle-3 { width: 150px; height: 150px; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.3; }
    .brand-content { position: relative; z-index: 2; text-align: center; max-width: 520px; color: var(--primary-white); }
    .brand-logo { width: 220px; height: 220px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; }
    .brand-logo img { width: 220px !important; height: 220px !important; max-width: 220px !important; max-height: 220px !important; object-fit: contain; filter: drop-shadow(0 12px 30px rgba(0, 0, 0, 0.55)); }
    .brand-eyebrow { font-family: var(--font-sans); font-size: 0.75rem; font-weight: 800; letter-spacing: 2.5px; text-transform: uppercase; color: var(--gold-light); margin-bottom: 0.5rem; }
    .brand-title { font-family: var(--font-serif); font-size: clamp(1.85rem, 2.3vw, 2.35rem); font-weight: 700; margin-bottom: 1.75rem; letter-spacing: 0.5px; line-height: 1.25; color: var(--primary-white); text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2); }
    .brand-description { font-family: var(--font-sans); font-size: 0.95rem; opacity: 0.92; line-height: 1.6; margin-bottom: 2rem; color: rgba(255, 255, 255, 0.9); }
    .brand-features { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; text-align: left; margin-top: 1.5rem; }
    .feature-item { background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.12); transition: all 0.3s ease; display: flex; align-items: center; gap: 0.75rem; }
    .feature-item:hover { background: rgba(255, 255, 255, 0.18); transform: translateY(-2px); }
    .feature-item i { color: var(--gold-light); font-size: 1.25rem; }
    .feature-item span { font-family: var(--font-sans); font-size: 0.825rem; font-weight: 600; letter-spacing: 0.2px; color: var(--primary-white); }
    .login-side { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; background: var(--primary-white); position: relative; min-height: 100vh; }
    .login-side::before { content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 100%; background: linear-gradient(180deg, var(--primary-yellow), var(--primary-yellow-light)); box-shadow: 0 0 30px rgba(184, 134, 11, 0.4); }
    .login-container { width: 100%; max-width: 440px; padding: 0.5rem; position: relative; z-index: 1; animation: slideInRight 0.6s ease-out; }
    @keyframes slideInRight { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
    .login-header { margin-bottom: 2rem; }
    .login-eyebrow { font-family: var(--font-sans); font-size: 0.72rem; font-weight: 800; letter-spacing: 2.5px; text-transform: uppercase; color: #B89350; margin-bottom: 0.35rem; }
    .login-greeting { font-family: var(--font-serif); font-size: 1.85rem; font-weight: 700; color: #0F172A; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.65rem; line-height: 1.25; }
    .login-greeting i { color: var(--primary-yellow); font-size: 1.6rem; }
    .login-subtitle { font-family: var(--font-sans); color: #64748B; font-size: 0.9rem; }
    .alert-custom { font-family: var(--font-sans); border: none; border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 500; font-size: 0.875rem; border-left: 4px solid; }
    .form-group { margin-bottom: 1.25rem; }
    .form-label { font-family: var(--font-sans); font-weight: 650; color: #071426; font-size: 0.825rem; letter-spacing: 0.2px; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
    .input-group-modern { position: relative; }
    .input-group-modern .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; z-index: 10; font-size: 1rem; transition: color 0.3s ease; pointer-events: none; }
    .input-group-modern .form-control { font-family: var(--font-sans); padding: 0.75rem 1rem 0.75rem 3rem; border-radius: 12px; border: 2px solid #E5E7EB; background: #FAFAFA; height: 3.25rem; font-size: 0.95rem; transition: all 0.3s ease; color: #1F2937; }
    .input-group-modern .form-control::placeholder { font-family: var(--font-sans); color: #9CA3AF; font-weight: 400; }
    .input-group-modern .form-control:focus { border-color: #071426; background: #FFFFFF; box-shadow: 0 0 0 4px rgba(7, 20, 38, 0.12); outline: none; }
    .input-group-modern .form-control:focus ~ .input-icon { color: var(--primary-yellow); }
    .btn-login { font-family: var(--font-sans); background: linear-gradient(135deg, #071426 0%, #1a3a5c 100%); border: none; border-radius: 12px; padding: 0.85rem; font-weight: 700; font-size: 0.875rem; letter-spacing: 1.5px; text-transform: uppercase; color: white; height: 3.25rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden; box-shadow: 0 4px 15px rgba(7, 20, 38, 0.35); }
    .btn-login:hover { background: linear-gradient(135deg, #0f2137 0%, #b8860b 100%); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(184, 134, 11, 0.4); color: white; }
    .login-footer { font-family: var(--font-sans); text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #F3F4F6; color: #9CA3AF; font-size: 0.775rem; letter-spacing: 0.2px; }
    .divider { font-family: var(--font-sans); display: flex; align-items: center; margin: 1.5rem 0; gap: 1rem; color: #9CA3AF; font-size: 0.725rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #E5E7EB; }
    @media (max-width: 992px) {
        .login-wrapper { flex-direction: column; height: auto; }
        .brand-side { min-height: 45vh; padding: 2rem; }
        .brand-logo img { width: 100px !important; height: 100px !important; }
        .brand-title { font-size: 1.65rem; margin-bottom: 1.25rem; }
        .login-side { min-height: 55vh; padding: 2rem 1.5rem; }
        .login-side::before { top: 0; left: 0; width: 100%; height: 6px; }
    }
    @media (max-width: 576px) {
        .brand-title { font-size: 1.4rem; margin-bottom: 1rem; }
    }
</style>
</head>
<body>
<div class="login-wrapper">
    <div class="brand-side">
        <div class="circle-decoration circle-1"></div>
        <div class="circle-decoration circle-2"></div>
        <div class="circle-decoration circle-3"></div>
        
        <div class="brand-content">
            <div class="brand-logo">
                <img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="Citizen Portal Logo">
            </div>
            
            <div class="brand-eyebrow">CITY OF MANILA &bull; CITIZEN SERVICES</div>
            <h1 class="brand-title">
                City of Manila Legislative Citizen Portal
            </h1>

            <div class="brand-features">
                <div class="feature-item">
                    <i class="bi bi-journal-check"></i>
                    <span>Published Measures</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-calendar-event"></i>
                    <span>Hearing Schedules</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-check2-square"></i>
                    <span>Voting Results</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-chat-square-heart"></i>
                    <span>Feedback & Tracking</span>
                </div>
            </div>
        </div>
    </div>

    <div class="login-side">
        <div class="login-container">
            <div class="login-header">
                <div class="login-eyebrow">CITIZEN ACCESS PORTAL</div>
                <h2 class="login-greeting">
                    <i class="bi bi-person-circle"></i>
                    Citizen Sign In
                </h2>
                <p class="login-subtitle">Access public legislative information and civic services</p>
            </div>

            <?php foreach($flash as $m): ?>
                <div class="alert-custom alert-<?= e($m['type']) ?>">
                    <i class="bi bi-info-circle"></i>
                    <span><?= e($m['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <form method="post" action="<?= e(appUrl('auth/process_login.php')) ?>">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="bi bi-envelope"></i>
                        Email Address
                    </label>
                    <div class="input-group-modern">
                        <input type="email" id="email" name="email" class="form-control" required autocomplete="email" placeholder="name@example.com">
                        <i class="bi bi-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="bi bi-lock"></i>
                        Password
                    </label>
                    <div class="input-group-modern">
                        <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" placeholder="Enter your password">
                        <i class="bi bi-lock input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login mt-4">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login
                </button>

                <div class="text-center mt-3 small text-muted">
                    No citizen account yet? <a href="<?= e(appUrl('register.php')) ?>" class="fw-bold text-dark text-decoration-underline">Register here</a>
                </div>
            </form>

            <div class="divider">
                <span>City of Manila Portal</span>
            </div>

            <div class="login-footer">
                <i class="bi bi-shield-lock"></i>
                &nbsp;City Council of Manila &bull; Citizen Portal &copy; <?= date('Y') ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>

