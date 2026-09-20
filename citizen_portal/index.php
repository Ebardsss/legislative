<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$logged=isLoggedIn();

$subsystems = [
    [
        'code'  => 'ORLMS',
        'icon'  => 'bi-file-earmark-text',
        'title' => 'Ordinances & Resolutions',
        'sub'   => 'Ordinance & Resolution Life Cycle',
        'desc'  => 'Published public legislative measures, enacted city ordinances, and approved resolutions from ORLMS.'
    ],
    [
        'code'  => 'SLMMS',
        'icon'  => 'bi-calendar-event',
        'title' => 'Sessions & Meetings',
        'sub'   => 'Session & Meeting Management',
        'desc'  => 'Regular and special session schedules, plenary agenda notices, and confirmed proceedings from SLMMS.'
    ],
    [
        'code'  => 'LACMS',
        'icon'  => 'bi-calendar3',
        'title' => 'Agenda & Calendar',
        'sub'   => 'Legislative Calendar Management',
        'desc'  => 'Finalized session agendas, confirmed legislative calendar schedules, and official deadlines from LACMS.'
    ],
    [
        'code'  => 'CMAS',
        'icon'  => 'bi-diagram-3',
        'title' => 'Committees & Assignments',
        'sub'   => 'Standing Committee Operations',
        'desc'  => 'Standing legislative committees, membership directory, jurisdiction scopes, and committee reports from CMAS.'
    ],
    [
        'code'  => 'VQDSS',
        'icon'  => 'bi-bar-chart-steps',
        'title' => 'Voting Results',
        'sub'   => 'Voting, Quorum & Decisions',
        'desc'  => 'Published official voting decisions, roll-call voting outcomes, and quorum verification from VQDSS.'
    ],
    [
        'code'  => 'LRDMS',
        'icon'  => 'bi-folder-check',
        'title' => 'Records & Documents',
        'sub'   => 'Document & Records Management',
        'desc'  => 'Official digital repository of enacted measures, certified records, and document search from LRDMS.'
    ],
    [
        'code'  => 'PHCMS',
        'icon'  => 'bi-people',
        'title' => 'Hearings & Consultations',
        'sub'   => 'Public Consultation System',
        'desc'  => 'Public hearing schedules, citizen consultation notices, attendee info, and consultation records from PHCMS.'
    ],
    [
        'code'  => 'LAHRS',
        'icon'  => 'bi-archive',
        'title' => 'Archives & History',
        'sub'   => 'Historical Legislative Archives',
        'desc'  => 'Digital historical repository, city legislative archives, and landmark municipal records from LAHRS.'
    ],
    [
        'code'  => 'LRPAIES',
        'icon'  => 'bi-graph-up-arrow',
        'title' => 'Policy Research & Impact',
        'sub'   => 'Research & Impact Evaluation',
        'desc'  => 'Legislative policy research findings, municipal studies, and public impact assessments from LRPAIES.'
    ],
    [
        'code'  => 'CEPFMS',
        'icon'  => 'bi-chat-square-heart',
        'title' => 'Citizen Engagement',
        'sub'   => 'Public Feedback Management',
        'desc'  => 'Submit feedback, track community issues, and stay engaged with City Council initiatives through CEPFMS.'
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(APP_NAME) ?> | City of Manila</title>
<link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png')) ?>">
<link rel="shortcut icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Premium Google Fonts matching landing page -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css?v=' . time())) ?>">
<link rel="stylesheet" href="<?= e(appUrl('assets/css/public.css?v=' . time())) ?>">
</head>
<body class="landing-body">

<section class="hero">
<div class="hero-overlay"></div>
<div class="container hero-content">
<div class="row align-items-center g-4 g-xl-5">
<div class="col-lg-7 col-xl-7">
  <span class="hero-eyebrow"><i class="bi bi-bank2"></i> City of Manila · Official Public Gateway</span>
  <h1 class="hero-heading">Legislative <span class="highlight-gold">Citizen Portal</span></h1>
  <p class="hero-subtext">A unified, transparent digital gateway providing citizen access to official city ordinances, session calendars, voting decisions, public consultation records, and civic feedback across all 10 municipal legislative subsystems.</p>

  <div class="hero-highlights-strip">
    <div class="hero-badge"><i class="bi bi-shield-check"></i> Verified Public Records</div>
    <div class="hero-badge"><i class="bi bi-lightning-charge"></i> Real-Time Proceedings</div>
    <div class="hero-badge"><i class="bi bi-diagram-3"></i> 10 Connected Subsystems</div>
  </div>
</div>
<div class="col-lg-5 col-xl-5 text-center text-lg-end">
  <div class="hero-logo-wrapper">
    <img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City Hall of Manila Seal" class="hero-seal-img" width="420" height="420">
  </div>
</div>
</div>
</div>
</section>

<section class="public-services">
<div class="container">
<div class="section-header-center">
  <span class="eyebrow">INTEGRATED LEGISLATIVE PLATFORM</span>
  <h2>What Citizens Can Access</h2>
  <p>The citizen portal provides transparent public access to records, proceedings, and civic services from all 10 integrated municipal subsystems.</p>
</div>

<div class="subsystems-10-grid">
<?php foreach($subsystems as $idx => $item): 
    $slideDir = ($idx < 5) ? 'slide-from-right' : 'slide-from-left';
?>
  <div class="service-card <?= $slideDir ?>">
    <div class="card-hover-line"></div>
    <div class="service-icon-box">
      <i class="bi <?= e($item['icon']) ?>"></i>
    </div>
    <h3 class="service-title"><?= e($item['title']) ?></h3>
    <div class="service-sub"><?= e($item['sub']) ?></div>
    <p class="service-desc"><?= e($item['desc']) ?></p>
  </div>
<?php endforeach; ?>
</div>

</div>
</section>

<section class="privacy-strip">
<div class="container d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
  <div>
    <h4 class="privacy-title"><i class="bi bi-shield-check me-2"></i>Official Public Records Only</h4>
    <p class="mb-0 privacy-text">Internal executive drafts, confidential committee notes, restricted records, staff activity logs, and private personal citizen data are strictly protected and never displayed publicly.</p>
  </div>
  <a href="<?= e(appUrl($logged?'dashboard.php':'login.php')) ?>" class="btn-gold-action text-nowrap">
    <i class="bi bi-arrow-right-circle"></i> Continue to Portal
  </a>
</div>
</section>

</body>
</html>
