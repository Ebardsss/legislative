<?php
/**
 * index.php
 * ------------------------------------------------------------------
 * Main landing page for the Integrated Legislative Management System.
 *
 * Provides access to the ten integrated subsystem websites:
 *
 * #1  Ordinance and Resolution Life Cycle Management System (ORLMS)
 * #2  Session and Legislative Meeting Management System (SLMMS)
 * #3  Legislative Agenda and Calendar Management System (LACMS)
 * #4  Committee Management and Assignment System (CMAS)
 * #5  Voting, Quorum, and Decision Support System (VQDSS)
 * #6  Legislative Records and Document Management System (LRDMS)
 * #7  Public Hearing and Consultation Management System (LPH)
 * #8  Legislative Archives and Historical Repository System (LAHRS)
 * #9  Legislative Research, Policy Analysis, and Impact Evaluation System (LRPAIES)
 * #10 Citizen Engagement and Public Feedback Management System (CEPFMS)
 * ------------------------------------------------------------------
 */

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function vendorAsset(string $relativePath, string $cdnFallback): string {
    $localFsPath = __DIR__ . '/assets/vendor/' . $relativePath;
    if (is_file($localFsPath) && filesize($localFsPath) > 0) {
        return 'assets/vendor/' . $relativePath;
    }
    return $cdnFallback;
}

$loggedIn = false;

// Dynamically compute base portal URL
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$scheme = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$portalPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/legislative')), '/');
$portalUrl = $scheme . $host . ($portalPath === '' || $portalPath === '/' ? '' : $portalPath);

$subsystems = [
    [
        'number'      => '#1',
        'code'        => 'ordinance_resolution',
        'folder'      => 'ORLMS',
        'title'       => 'Ordinance and Resolution Life Cycle Management System',
        'short_title' => 'Ordinance and Resolution',
        'category'    => 'LEGISLATIVE DRAFTING',
        'location'    => 'Council Secretariat • Active Subsystem',
        'description' => 'Manage the complete life cycle of ordinances and resolutions, from drafting and committee review to enactment, publication, implementation, and amendment tracking.',
        'icon'        => 'bi-file-earmark-text',
        'image'       => 'assets/images/subsystem_ordinance.jpg',
        'url'         => $portalUrl . '/ORLMS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Ordinance Drafting and Encoding Module',
            'Resolution Drafting and Submission Module',
            'Review and Committee Endorsement Module',
            'Approval and Enactment Module',
            'Publication and Dissemination Module',
            'Implementation Monitoring Module',
            'Amendment and Revision Tracking Module',
        ],
    ],
    [
        'number'      => '#2',
        'code'        => 'session_meeting',
        'folder'      => 'SLMMS',
        'title'       => 'Session and Legislative Meeting Management System',
        'short_title' => 'Session & Meeting',
        'category'    => 'SESSION MANAGEMENT',
        'location'    => 'Plenary Hall • Active Subsystem',
        'description' => 'Coordinate session scheduling, agenda preparation, attendance and quorum monitoring, session proceedings documentation, minutes generation, and real-time session tracking.',
        'icon'        => 'bi-calendar-event',
        'image'       => 'assets/images/hero_legislative_hall.jpg',
        'url'         => $portalUrl . '/SLMMS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Session Scheduling Module',
            'Agenda Preparation Module',
            'Attendance and Quorum Monitoring Module',
            'Session Proceedings Documentation Module',
            'Minutes Generation Module',
            'Real-Time Session Tracking Module',
        ],
    ],
    [
        'number'      => '#3',
        'code'        => 'agenda',
        'folder'      => 'LACMS',
        'title'       => 'Legislative Agenda and Calendar Management System',
        'short_title' => 'Agenda and Calendar',
        'category'    => 'SESSION SCHEDULING',
        'location'    => 'Executive & Legislative Office • Active Subsystem',
        'description' => 'Manage legislative priorities, schedules, committee meetings, deadlines, and coordination between the executive and legislative offices.',
        'icon'        => 'bi-calendar3',
        'image'       => 'assets/images/subsystem_agenda.jpg',
        'url'         => $portalUrl . '/LACMS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Legislative Priority Setting Module',
            'Calendar Scheduling Module',
            'Meeting Coordination Module',
            'Deadline Tracking Module',
            'Executive-Legislative Synchronization Module',
        ],
    ],
    [
        'number'      => '#4',
        'code'        => 'committee',
        'folder'      => 'CMAS',
        'title'       => 'Committee Management and Assignment System',
        'short_title' => 'Committee Management',
        'category'    => 'COMMITTEE WORK',
        'location'    => 'Committee Secretariat • Active Subsystem',
        'description' => 'Streamline committee formation, member assignment, jurisdiction and scope definition, workload distribution, committee performance monitoring, and committee reporting.',
        'icon'        => 'bi-diagram-3',
        'image'       => 'assets/images/subsystem_agenda.jpg',
        'url'         => $portalUrl . '/CMAS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Committee Formation Module',
            'Member Assignment Module',
            'Jurisdiction and Scope Definition Module',
            'Workload Distribution Module',
            'Committee Performance Monitoring Module',
            'Committee Reporting Module',
        ],
    ],
    [
        'number'      => '#5',
        'code'        => 'voting',
        'folder'      => 'vqdss',
        'title'       => 'Voting, Quorum, and Decision Support System',
        'short_title' => 'Voting and Quorum',
        'category'    => 'SESSION DECISIONS',
        'location'    => 'Plenary Hall • Active Subsystem',
        'description' => 'Support quorum verification, legislative voting, vote tallying, decision recording, validation, and official reporting.',
        'icon'        => 'bi-check2-square',
        'image'       => 'assets/images/hero_legislative_hall.jpg',
        'url'         => $portalUrl . '/vqdss/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Quorum Verification Module',
            'Voting Management Module (Manual/Electronic)',
            'Vote Tallying Module',
            'Decision Recording Module',
            'Result Validation and Reporting Module',
        ],
    ],
    [
        'number'      => '#6',
        'code'        => 'records_doc',
        'folder'      => 'LRDMS',
        'title'       => 'Legislative Records and Document Management System',
        'short_title' => 'Records & Documents',
        'category'    => 'DOCUMENT CONTROL',
        'location'    => 'Records Office • Active Subsystem',
        'description' => 'Manage document encoding and submission, legislative repository, version control, document retrieval and search, access control, security, and audit trail management.',
        'icon'        => 'bi-folder-check',
        'image'       => 'assets/images/subsystem_ordinance.jpg',
        'url'         => $portalUrl . '/LRDMS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Document Encoding and Submission Module',
            'Legislative Repository Module',
            'Version Control Module',
            'Document Retrieval and Search Module',
            'Access Control and Security Module',
            'Audit Trail Management Module',
        ],
    ],
    [
        'number'      => '#7',
        'code'        => 'hearing',
        'folder'      => 'lph',
        'title'       => 'Public Hearing and Consultation Management System',
        'short_title' => 'Public Hearing',
        'category'    => 'CIVIC CONSULTATION',
        'location'    => 'Public Hearing Office • Active Subsystem',
        'description' => 'Coordinate public hearings, stakeholder invitations, registration, attendance, feedback, issue logging, responses, and action tracking.',
        'icon'        => 'bi-people',
        'image'       => 'assets/images/subsystem_hearing.jpg',
        'url'         => $portalUrl . '/lph/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Hearing Scheduling Module',
            'Stakeholder Invitation and Registration Module',
            'Attendance Tracking Module',
            'Public Feedback Collection Module',
            'Issue Logging Module',
            'Response and Action Tracking Module',
        ],
    ],
    [
        'number'      => '#8',
        'code'        => 'archives',
        'folder'      => 'LAHRS',
        'title'       => 'Legislative Archives and Historical Repository System',
        'short_title' => 'Archives & Repository',
        'category'    => 'HISTORICAL REPOSITORY',
        'location'    => 'City Archives • Active Subsystem',
        'description' => 'Digital archiving, historical records digitization, search and retrieval, record classification and indexing, and compliance and retention management.',
        'icon'        => 'bi-archive',
        'image'       => 'assets/images/manila_city_hall.jpg',
        'url'         => $portalUrl . '/LAHRS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Digital Archiving Module',
            'Historical Records Digitization Module',
            'Search and Retrieval Module',
            'Record Classification and Indexing Module',
            'Compliance and Retention Management Module',
        ],
    ],
    [
        'number'      => '#9',
        'code'        => 'research_policy',
        'folder'      => 'LRPAIES',
        'title'       => 'Legislative Research, Policy Analysis, and Impact Evaluation System',
        'short_title' => 'Research & Policy Analysis',
        'category'    => 'POLICY RESEARCH',
        'location'    => 'Policy Research Bureau • Active Subsystem',
        'description' => 'Policy research, data collection and integration, impact assessment, benchmarking and comparative analysis, report generation, and data visualization.',
        'icon'        => 'bi-graph-up-arrow',
        'image'       => 'assets/images/subsystem_ordinance.jpg',
        'url'         => $portalUrl . '/LRPAIES/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Policy Research Module',
            'Data Collection and Integration Module',
            'Impact Assessment Module',
            'Benchmarking and Comparative Analysis Module',
            'Report Generation Module',
            'Data Visualization Module',
        ],
    ],
    [
        'number'      => '#10',
        'code'        => 'citizen',
        'folder'      => 'CEPFMS',
        'title'       => 'Citizen Engagement and Public Feedback Management System',
        'short_title' => 'Citizen Engagement',
        'category'    => 'PUBLIC FEEDBACK',
        'location'    => 'Public Portal • Active Subsystem',
        'description' => 'Manage public feedback, citizen proposals, complaints, moderation, official responses, and engagement analytics.',
        'icon'        => 'bi-chat-square-heart',
        'image'       => 'assets/images/subsystem_hearing.jpg',
        'url'         => $portalUrl . '/CEPFMS/',
        'enabled'     => true,
        'status'      => 'Active',
        'modules'     => [
            'Public Feedback Submission Module',
            'Proposal and Suggestion Management Module',
            'Complaint and Issue Tracking Module',
            'Moderation and Validation Module',
            'Response Management Module',
            'Citizen Engagement Analytics Module',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrated Legislative Management System | Official Governance Portal</title>

    <!-- Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/logo.png">

    <link href="<?= e(vendorAsset('bootstrap/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(vendorAsset('bootstrap-icons/bootstrap-icons.css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css')) ?>" rel="stylesheet">

    <!-- Premium Google Fonts matching reference design -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        :root {
            --bg-navy-dark: #070E1B;
            --bg-navy-main: #0F1C34;
            --bg-navy-card: #152648;
            --bg-navy-light: #1A2F56;
            
            --gold-primary: #D4AF37;
            --gold-light: #E5C07B;
            --gold-muted: #C5A880;
            --gold-dark: #A8862A;
            --gold-glow: rgba(212, 175, 55, 0.25);
            --gold-border: rgba(212, 175, 55, 0.22);
            
            --text-heading: #FFFFFF;
            --text-body: #CBD5E1;
            --text-muted: #94A3B8;
            --text-gold: #E5C07B;

            --font-serif: 'Cinzel', serif;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-navy-dark);
            color: var(--text-body);
            font-family: var(--font-sans);
            overflow-x: hidden;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
            transition: all 0.3s ease;
        }

        /* NAVBAR - Executive Luxury Styling */
        .luxury-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(7, 14, 27, 0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--gold-border);
            padding: 0.85rem 0;
        }

        .navbar-brand-custom {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-logo-ring {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 0 15px var(--gold-glow);
            padding: 3px;
            background: var(--bg-navy-card);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-logo-ring img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .brand-text-block {
            display: flex;
            flex-direction: column;
        }

        .brand-title-main {
            font-family: var(--font-serif);
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--text-heading);
            line-height: 1.1;
        }

        .brand-subtitle-sub {
            font-size: 0.7rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold-muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .nav-link-custom {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-body) !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
        }

        .nav-link-custom:hover {
            color: var(--gold-light) !important;
        }

        .btn-gold-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: 0.65rem 1.4rem;
            background: linear-gradient(135deg, #C5A880 0%, #D4AF37 50%, #B89350 100%);
            border: none;
            border-radius: 6px;
            color: #070E1B;
            font-weight: 700;
            font-size: 0.825rem;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.25);
            transition: all 0.3s ease;
        }

        .btn-gold-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.4);
            color: #070E1B;
            background: linear-gradient(135deg, #D4AF37 0%, #E5C07B 50%, #C5A880 100%);
        }

        /* HERO SECTION - Inspired by "Building Better Spaces. Stronger Futures." */
        .hero-luxury {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 150px 0 100px;
            background: linear-gradient(to right, #070E1B 0%, rgba(7, 14, 27, 0.85) 50%, rgba(7, 14, 27, 0.4) 100%), 
                        url('assets/images/manila_city_hall.jpg') center/cover no-repeat;
            overflow: hidden;
            border-bottom: 1px solid var(--gold-border);
        }

        .hero-eyebrow {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold-muted);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .hero-eyebrow::before {
            content: '';
            width: 30px;
            height: 2px;
            background: var(--gold-primary);
        }

        .hero-heading {
            font-family: var(--font-serif);
            font-size: clamp(2.75rem, 5.5vw, 4.5rem);
            font-weight: 700;
            line-height: 1.1;
            color: var(--text-heading);
            margin-bottom: 1.5rem;
            letter-spacing: -0.5px;
        }

        .hero-heading .highlight-gold {
            color: var(--gold-primary);
            background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold-primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtext {
            font-size: 1.15rem;
            color: var(--text-body);
            max-width: 620px;
            margin-bottom: 2.5rem;
            font-weight: 300;
            line-height: 1.7;
        }

        .btn-gold-lg {
            padding: 0.9rem 2rem;
            font-size: 0.9rem;
            border-radius: 6px;
        }

        .btn-outline-gold {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            background: rgba(21, 38, 72, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid var(--gold-primary);
            color: var(--gold-light);
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-outline-gold:hover {
            background: var(--gold-primary);
            color: var(--bg-navy-dark);
            box-shadow: 0 0 25px var(--gold-glow);
        }

        /* Hero Right Floating Card Showcase */
        .hero-card-showcase {
            background: rgba(15, 28, 52, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid var(--gold-border);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
        }

        .hero-card-title {
            font-family: var(--font-serif);
            font-size: 1.25rem;
            color: var(--text-heading);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gold-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .status-dot-active {
            width: 10px;
            height: 10px;
            background-color: #10B981;
            border-radius: 50%;
            box-shadow: 0 0 10px #10B981;
            display: inline-block;
            margin-right: 6px;
        }

        /* SECTION ABOUT - Inspired by "We don't just build structures. We build possibilities." */
        .section-about {
            padding: 100px 0;
            background: #F8FAFC;
            position: relative;
            border-top: 1px solid #E2E8F0;
        }

        .about-white-card {
            background: #FFFFFF;
            color: #1E293B;
            border-radius: 16px;
            padding: 3.5rem;
            border: 1px solid #E2E8F0;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.06);
            height: 100%;
        }

        .about-white-card .eyebrow {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #B89350;
            margin-bottom: 1rem;
        }

        .about-white-card h2 {
            font-family: var(--font-serif);
            font-size: 2.4rem;
            font-weight: 700;
            line-height: 1.25;
            color: #0F172A;
            margin-bottom: 1.5rem;
        }

        .about-white-card p {
            color: #475569;
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .about-image-card {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #E2E8F0;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.06);
            height: 100%;
            min-height: 440px;
            position: relative;
            background: #0B132B;
        }

        .about-image-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .about-image-card:hover img {
            transform: scale(1.03);
        }

        /* INSTITUTIONAL CHARTER PANEL - Clean Executive Background Design (No Hover) */
        .about-navy-panel {
            position: relative;
            background-color: #070E1B;
            background-image: 
                radial-gradient(ellipse 95% 60% at 85% 12%, rgba(212, 175, 55, 0.18) 0%, transparent 65%),
                radial-gradient(circle at 10% 88%, rgba(30, 58, 138, 0.3) 0%, transparent 55%),
                linear-gradient(160deg, #0d1a33 0%, #091326 50%, #050b14 100%);
            border: 1px solid rgba(212, 175, 55, 0.28);
            border-radius: 16px;
            padding: 2.75rem 2.25rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08);
            overflow: hidden;
            z-index: 1;
        }

        /* Top Gold Accent Line */
        .about-navy-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.4), #FDE68A, #D4AF37, transparent);
            pointer-events: none;
            z-index: 2;
        }

        /* Authentic Seal Watermark in Background */
        .about-navy-panel::after {
            content: '';
            position: absolute;
            right: -25px;
            bottom: -25px;
            width: 230px;
            height: 230px;
            background: url('assets/images/logo.png') center/contain no-repeat;
            opacity: 0.05;
            filter: grayscale(100%) brightness(250%);
            pointer-events: none;
            z-index: 0;
        }

        .vision-item {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .vision-item:last-child {
            margin-bottom: 0;
        }

        .vision-icon-ring {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 50%;
            border: 1.5px solid #D4AF37;
            background: radial-gradient(circle at 30% 30%, rgba(229, 192, 123, 0.2), rgba(15, 28, 52, 0.8));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #E5C07B;
            font-size: 1.35rem;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.15);
        }

        .vision-info {
            flex-grow: 1;
        }

        .vision-info h4 {
            font-family: var(--font-serif);
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #E5C07B;
            margin-bottom: 0.35rem;
        }

        .vision-info p {
            font-size: 0.875rem;
            color: var(--text-body);
            margin: 0;
            line-height: 1.6;
        }

        .image-caption-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 3rem 1.75rem 1.5rem;
            background: linear-gradient(to top, rgba(7, 14, 27, 0.95) 0%, rgba(7, 14, 27, 0.5) 65%, transparent 100%);
            color: #FFFFFF;
            pointer-events: none;
            z-index: 2;
        }

        .image-caption-overlay .caption-badge {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: var(--gold-light);
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }

        .image-caption-overlay h5 {
            font-family: var(--font-serif);
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: #FFFFFF;
            letter-spacing: 0.5px;
        }

        /* SECTION MVV (MISSION, VISION, VALUES) - Compact Luxury Executive Charter */
        .section-mvv {
            padding: 50px 0 55px;
            background-color: #070E1B;
            background-image: 
                radial-gradient(ellipse 80% 50% at 50% 10%, rgba(212, 175, 55, 0.1) 0%, transparent 65%),
                radial-gradient(circle at 10% 85%, rgba(26, 58, 110, 0.25) 0%, transparent 55%),
                radial-gradient(circle at 90% 85%, rgba(184, 134, 11, 0.12) 0%, transparent 55%),
                linear-gradient(180deg, #070E1B 0%, #0A162B 50%, #070E1B 100%);
            border-top: 1px solid rgba(212, 175, 55, 0.3);
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            position: relative;
            overflow: hidden;
        }

        .section-mvv::before {
            content: '';
            position: absolute;
            top: 0;
            left: 20%;
            right: 20%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.5), #FDE68A, rgba(212, 175, 55, 0.5), transparent);
            pointer-events: none;
            z-index: 2;
        }

        .section-mvv .container {
            position: relative;
            z-index: 1;
            max-width: 1180px;
        }

        .mvv-header-block {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 2.25rem;
        }

        .mvv-header-eyebrow {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #E5C07B;
            margin-bottom: 0.4rem;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
        }

        .mvv-header-eyebrow::before,
        .mvv-header-eyebrow::after {
            content: '';
            width: 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, #D4AF37);
        }

        .mvv-header-eyebrow::after {
            background: linear-gradient(90deg, #D4AF37, transparent);
        }

        .mvv-header-title {
            font-family: var(--font-serif);
            font-size: clamp(1.75rem, 3.2vw, 2.35rem);
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: -0.3px;
            line-height: 1.2;
            margin-bottom: 0.45rem;
        }

        .mvv-header-desc {
            color: #CBD5E1;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 auto;
            font-weight: 300;
        }

        /* Compact Open Charter Row & Pillars */
        .mvv-charter-row {
            position: relative;
        }

        .mvv-pillar {
            position: relative;
            padding: 0.75rem 1.6rem;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all 0.35s ease;
        }

        /* Subtle Vertical Divider Rules Between Columns (Desktop Only) */
        @media (min-width: 992px) {
            .mvv-pillar-divider {
                position: relative;
            }

            .mvv-pillar-divider::after {
                content: '';
                position: absolute;
                top: 10%;
                right: 0;
                width: 1px;
                height: 80%;
                background: linear-gradient(180deg, 
                    transparent 0%, 
                    rgba(212, 175, 55, 0.22) 20%, 
                    rgba(253, 230, 138, 0.45) 50%, 
                    rgba(212, 175, 55, 0.22) 80%, 
                    transparent 100%);
                pointer-events: none;
            }
        }

        /* Compact Illuminated Crest / Emblem Ring */
        .mvv-crest-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 1.5px solid #D4AF37;
            background: radial-gradient(circle at 35% 35%, rgba(229, 192, 123, 0.25), rgba(15, 28, 52, 0.95));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FDE68A;
            font-size: 1.35rem;
            margin-bottom: 1rem;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.18);
            position: relative;
            transition: all 0.35s ease;
        }

        .mvv-crest-badge::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            border: 1px dashed rgba(212, 175, 55, 0.35);
            pointer-events: none;
            transition: transform 0.6s ease;
        }

        .mvv-pillar:hover .mvv-crest-badge {
            background: #D4AF37;
            color: #070E1B;
            transform: scale(1.08);
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.5);
        }

        .mvv-pillar:hover .mvv-crest-badge::before {
            transform: rotate(90deg);
            border-color: rgba(253, 230, 138, 0.8);
        }

        .mvv-heading {
            font-family: var(--font-serif);
            font-size: 1.45rem;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 0.5rem;
            line-height: 1.25;
            display: inline-block;
            transition: color 0.3s ease;
        }

        .mvv-pillar:hover .mvv-heading {
            color: #FDE68A;
        }

        /* Expanding Gold Line Under Heading */
        .mvv-title-line {
            width: 36px;
            height: 2px;
            background: linear-gradient(90deg, #D4AF37, #FDE68A);
            margin-bottom: 0.9rem;
            transition: width 0.35s ease;
            border-radius: 2px;
        }

        .mvv-pillar:hover .mvv-title-line {
            width: 65px;
        }

        .mvv-description {
            color: #CBD5E1;
            font-size: 0.915rem;
            line-height: 1.68;
            margin-bottom: 0;
            font-weight: 300;
            transition: color 0.3s ease;
        }

        .mvv-pillar:hover .mvv-description {
            color: #E2E8F0;
        }

        /* Staggered Slide Animations for the 3 Pillars */
        .mvv-pillar-anim {
            opacity: 0;
            will-change: transform, opacity;
        }

        .mvv-pillar-left.in-view {
            animation: slideInFromLeft 1.4s cubic-bezier(0.22, 1, 0.36, 1) 0.1s forwards;
        }

        .mvv-pillar-center.in-view {
            animation: mvvBloomCenter 1.4s cubic-bezier(0.22, 1, 0.36, 1) 0.2s forwards;
        }

        .mvv-pillar-right.in-view {
            animation: slideInFromRight 1.4s cubic-bezier(0.22, 1, 0.36, 1) 0.3s forwards;
        }

        @keyframes mvvBloomCenter {
            0% {
                opacity: 0;
                transform: translateY(25px) scale(0.97);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 991.98px) {
            .section-mvv {
                padding: 40px 0 45px;
            }

            .mvv-pillar {
                padding: 1.25rem 0.75rem;
                border-bottom: 1px solid rgba(212, 175, 55, 0.12);
            }

            .mvv-pillar:last-child {
                border-bottom: none;
            }

            .mvv-pillar-left.in-view,
            .mvv-pillar-center.in-view,
            .mvv-pillar-right.in-view {
                animation: mvvBloomCenter 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            }
        }

        /* FEATURED DEVELOPMENTS / SUBSYSTEMS CARDS - Inspired by "FEATURED DEVELOPMENTS" (Light Background) */
        .section-subsystems {
            padding: 100px 0;
            background: #FFFFFF;
            border-top: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
        }

        .section-header-center {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 4rem;
        }

        .section-header-center .eyebrow {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #B89350;
            margin-bottom: 0.75rem;
        }

        .section-header-center h2 {
            font-family: var(--font-serif);
            font-size: 2.5rem;
            color: #0F172A;
            margin-bottom: 1rem;
        }

        .section-header-center p {
            color: #64748B;
        }

        .development-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            overflow: hidden;
            transition: all 0.35s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            position: relative;
        }

        .development-card:hover {
            transform: translateY(-8px) !important;
            border-color: #D4AF37;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        /* 5-Column Subsystems Grid & Slide Entrance Animations (2s Slow Slide Effect) */
        .subsystems-5col-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
        }

        @keyframes slideInFromLeft {
            0% {
                opacity: 0;
                transform: translateX(-160px);
            }
            60% {
                opacity: 0.85;
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInFromRight {
            0% {
                opacity: 0;
                transform: translateX(160px);
            }
            60% {
                opacity: 0.85;
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .anim-from-left,
        .anim-from-right {
            opacity: 0;
            will-change: transform, opacity;
        }

        .anim-from-left.in-view {
            animation: slideInFromLeft 2s cubic-bezier(0.22, 1, 0.36, 1) var(--anim-delay, 0s) forwards;
        }

        .anim-from-right.in-view {
            animation: slideInFromRight 2s cubic-bezier(0.22, 1, 0.36, 1) var(--anim-delay, 0s) forwards;
        }

        /* Fullscreen Card Zoom-In Transition (Pure White Background with Big Manila Logo) */
        .card-zoom-backdrop {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            background: #FFFFFF !important;
            z-index: 999998;
            opacity: 0;
            pointer-events: all;
            transition: opacity 1s ease;
        }

        .card-zoom-backdrop.active {
            opacity: 1;
        }

        .card-fullscreen-zoom {
            position: fixed !important;
            z-index: 999999 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            background: #FFFFFF !important;
            border: 2px solid #D4AF37 !important;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.15) !important;
            transition: 
                top 3s cubic-bezier(0.2, 0.8, 0.2, 1),
                left 3s cubic-bezier(0.2, 0.8, 0.2, 1),
                width 3s cubic-bezier(0.2, 0.8, 0.2, 1),
                height 3s cubic-bezier(0.2, 0.8, 0.2, 1),
                border-radius 3s cubic-bezier(0.2, 0.8, 0.2, 1) !important;
            display: flex !important;
            flex-direction: column !important;
            color: #0F172A !important;
            pointer-events: all !important;
        }

        .card-fullscreen-zoom.zoomed-in {
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            border-radius: 0 !important;
            border-width: 0 !important;
        }

        /* Interior content morph when card zooms in */
        .zoom-portal-content {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            z-index: 2;
            opacity: 0;
            transition: opacity 1s ease 0.4s;
        }

        .card-fullscreen-zoom.zoomed-in .zoom-portal-content {
            opacity: 1;
        }

        .card-fullscreen-zoom .card-body-content {
            transition: opacity 0.8s ease;
        }

        .card-fullscreen-zoom.zoomed-in .card-body-content {
            opacity: 0;
            display: none;
        }

        /* Big Manila Logo in Center */
        .zoom-manila-logo-large {
            width: 145px;
            height: 145px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 3px solid #D4AF37;
            padding: 8px;
            margin-bottom: 1.25rem;
            box-shadow: 0 12px 35px rgba(212, 175, 55, 0.35), 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulseManilaLogo 2.2s infinite alternate;
        }

        .zoom-manila-logo-large img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        @keyframes pulseManilaLogo {
            0% { transform: scale(1); box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3); }
            100% { transform: scale(1.05); box-shadow: 0 16px 45px rgba(212, 175, 55, 0.5); }
        }

        .zoom-city-label {
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            color: #854D0E;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .zoom-subsystem-title {
            font-family: var(--font-serif);
            font-size: clamp(1.8rem, 3.4vw, 2.75rem);
            color: #0F172A;
            margin-bottom: 0.65rem;
            max-width: 820px;
            line-height: 1.25;
            font-weight: 700;
        }

        .zoom-subsystem-location {
            font-size: 0.95rem;
            color: #475569;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .zoom-progress-container {
            width: 100%;
            max-width: 460px;
            height: 7px;
            background: #E2E8F0;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
            margin-bottom: 1rem;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.12);
        }

        .zoom-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #D4AF37 0%, #B45309 50%, #0F172A 100%);
            border-radius: 999px;
            box-shadow: 0 0 12px rgba(212, 175, 55, 0.6);
            animation: zoomBarFill 3s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        @keyframes zoomBarFill {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        .zoom-status-text {
            font-size: 0.85rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 700;
            color: #854D0E;
        }

        .card-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 1px solid #D4AF37;
            background: rgba(212, 175, 55, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #B89350;
            font-size: 1.35rem;
            transition: all 0.3s ease;
        }

        .development-card:hover .card-icon-box {
            background: #0F172A;
            border-color: #0F172A;
            color: #E5C07B;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.3);
        }

        .badge-category-static {
            background: #0F172A;
            border: 1px solid rgba(212, 175, 55, 0.4);
            color: #E5C07B;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
        }

        .card-body-content {
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .card-title-serif {
            font-family: var(--font-serif);
            font-size: 1.2rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.35rem;
            line-height: 1.3;
        }

        .card-location {
            font-size: 0.775rem;
            color: #B89350;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .card-desc-text {
            font-size: 0.875rem;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .module-check-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem 0;
        }

        .module-check-list li {
            font-size: 0.8rem;
            color: #64748B;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .module-check-list li i {
            color: #B89350;
        }

        .card-link-gold {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #B89350;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: auto;
            transition: all 0.3s ease;
        }

        .card-link-gold:hover {
            color: #0F172A;
            gap: 0.75rem;
        }

        /* OUR EXPERTISE SECTION - Inspired by "OUR EXPERTISE" 5 Box Grid */
        .section-expertise {
            padding: 90px 0;
            background: var(--bg-navy-dark);
            border-bottom: 1px solid var(--gold-border);
        }

        .expertise-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
        }

        .expertise-item {
            text-align: center;
            padding: 1.5rem 1rem;
            background: rgba(15, 28, 52, 0.6);
            border: 1px solid var(--gold-border);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
        }

        .expertise-item:hover {
            transform: translateY(-5px);
            border-color: var(--gold-primary);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), 0 0 20px var(--gold-glow);
            background: rgba(21, 38, 72, 0.85);
        }

        .expertise-icon-box {
            width: 60px;
            height: 60px;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            border: 1px solid var(--gold-primary);
            background: rgba(212, 175, 55, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-primary);
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .expertise-item:hover .expertise-icon-box {
            background: var(--gold-primary);
            color: var(--bg-navy-dark);
            box-shadow: 0 0 20px var(--gold-glow);
        }

        .expertise-item h4 {
            font-family: var(--font-serif);
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 0.5rem;
        }

        .expertise-item p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.5;
        }

        /* TRUSTED GOVERNANCE & STATS STRIP - Inspired by "Trusted by clients. Driven by results." */
        .section-trust {
            padding: 90px 0;
            background: #FFFFFF;
            color: #0F172A;
            border-bottom: 1px solid #E2E8F0;
        }

        .trust-heading h2 {
            font-family: var(--font-serif);
            font-size: 2.2rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.5rem;
        }

        .trust-heading p {
            color: #64748B;
            font-size: 1rem;
        }

        .stat-box-num {
            font-family: var(--font-serif);
            font-size: 2.5rem;
            font-weight: 700;
            color: #B89350;
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .stat-box-label {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #475569;
        }

        .quote-card {
            background: #F8FAFC;
            border-left: 4px solid #D4AF37;
            padding: 1.75rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            height: 100%;
            border-top: 1px solid #E2E8F0;
            border-right: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
        }

        .quote-card i {
            color: #D4AF37;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .quote-card p {
            font-size: 0.925rem;
            color: #334155;
            line-height: 1.65;
            margin-bottom: 1.25rem;
            font-style: italic;
        }

        .quote-author {
            font-size: 0.825rem;
            font-weight: 700;
            color: #0F172A;
        }

        .quote-role {
            font-size: 0.775rem;
            color: #64748B;
        }


        /* WORKFLOW TIMELINE - Pure White Executive Theme */
        .section-workflow {
            padding: 100px 0;
            background: #FFFFFF;
            border-top: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
        }

        .section-workflow .section-header-center h2 {
            color: #0F172A !important;
        }

        .section-workflow .section-header-center .eyebrow {
            color: #B89350 !important;
        }

        .workflow-steps-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
            position: relative;
            margin-top: 3rem;
        }

        .workflow-steps-container::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 5%;
            right: 5%;
            height: 2px;
            background: rgba(212, 175, 55, 0.3);
            z-index: 1;
        }

        .workflow-step {
            text-align: center;
            position: relative;
            z-index: 2;
            height: 100%;
        }

        .workflow-step-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 1.75rem 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.04);
            height: 100%;
            transition: all 0.3s ease;
        }

        .workflow-step-card:hover {
            transform: translateY(-6px);
            background: #FFFFFF;
            border-color: #D4AF37;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .step-circle {
            width: 52px;
            height: 52px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            background: #FFFFFF;
            border: 2px solid #D4AF37;
            color: #0F172A;
            font-family: var(--font-serif);
            font-weight: 800;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2);
            transition: all 0.3s ease;
        }

        .workflow-step:hover .step-circle {
            background: #D4AF37;
            color: #0F172A;
            transform: scale(1.12);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
        }

        .step-title {
            font-family: var(--font-serif);
            font-size: 0.95rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.5rem;
        }

        .step-desc {
            font-size: 0.775rem;
            color: #64748B;
            line-height: 1.5;
        }

        /* CALL TO ACTION BANNER - Executive Architectural Background */
        .section-cta {
            padding: 90px 0;
            background: #F8FAFC;
            border-top: 1px solid #E2E8F0;
            border-bottom: 1px solid #E2E8F0;
            position: relative;
        }

        .cta-box {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 2.5rem;
            background: 
                radial-gradient(ellipse 80% 60% at 85% 15%, rgba(212, 175, 55, 0.16) 0%, transparent 60%),
                radial-gradient(circle at 15% 85%, rgba(30, 58, 138, 0.35) 0%, transparent 60%),
                linear-gradient(135deg, rgba(7, 14, 27, 0.94) 0%, rgba(15, 28, 52, 0.91) 50%, rgba(7, 14, 27, 0.96) 100%),
                url('assets/images/manila_city_hall.jpg') center/cover no-repeat;
            border: 1px solid rgba(212, 175, 55, 0.32);
            border-radius: 20px;
            padding: 3.5rem 3.5rem;
            box-shadow: 0 25px 60px -10px rgba(5, 11, 20, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.12);
            overflow: hidden;
            z-index: 1;
        }

        /* Top Gold Accent Line */
        .cta-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.4), #FDE68A, #D4AF37, transparent);
            pointer-events: none;
            z-index: 2;
        }

        /* Watermark Seal in Background */
        .cta-box::after {
            content: '';
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 260px;
            height: 260px;
            background: url('assets/images/logo.png') center/contain no-repeat;
            opacity: 0.055;
            filter: grayscale(100%) brightness(250%);
            pointer-events: none;
            z-index: 0;
        }

        .cta-content {
            position: relative;
            z-index: 1;
        }

        .cta-eyebrow {
            font-size: 0.725rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #E5C07B;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cta-title {
            font-family: var(--font-serif);
            font-size: 2.35rem;
            color: #FFFFFF;
            font-weight: 700;
            margin-bottom: 0.65rem;
            letter-spacing: -0.3px;
        }

        .cta-subtitle {
            color: #CBD5E1;
            font-size: 1.05rem;
            max-width: 580px;
            margin-bottom: 0;
            line-height: 1.65;
        }

        .cta-actions {
            position: relative;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .cta-box {
                padding: 2.25rem 1.75rem;
            }
            .cta-title {
                font-size: 1.85rem;
            }
        }

        /* FOOTER - Inspired by UrbanRise Footer */
        .luxury-footer {
            background: var(--bg-navy-dark);
            padding: 3rem 0 2rem;
            color: var(--text-muted);
            font-size: 0.825rem;
        }

        .footer-brand-title {
            font-family: var(--font-serif);
            color: var(--text-heading);
            font-size: 1rem;
            font-weight: 700;
        }

        .footer-links a {
            color: var(--text-muted);
            margin-left: 1.5rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--gold-light);
        }

        /* Responsive Breakpoints */
        @media (max-width: 1400px) {
            .subsystems-5col-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .expertise-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .workflow-steps-container {
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
            }
            .workflow-steps-container::before {
                display: none;
            }
        }

        @media (max-width: 992px) {
            .hero-heading {
                font-size: 3rem;
            }
            .about-white-card {
                padding: 2rem;
            }
            .about-image-card {
                min-height: 400px;
            }
            .expertise-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .hero-heading {
                font-size: 2.4rem;
            }
            .expertise-grid {
                grid-template-columns: 1fr;
            }
            .expertise-item {
                border-right: none;
                border-bottom: 1px solid rgba(212, 175, 55, 0.12);
            }
            .workflow-steps-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGATION BAR -->
<nav class="luxury-navbar navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand navbar-brand-custom" href="index.php">
            <div class="brand-logo-ring">
                <img src="assets/images/logo.png" alt="City Seal">
            </div>
            <div class="brand-text-block">
                <span class="brand-title-main">INTEGRATED LEGISLATIVE SYSTEM</span>
                <span class="brand-subtitle-sub">City Government Portal</span>
            </div>
        </a>

        <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#luxuryNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="luxuryNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link nav-link-custom" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom" href="#mvv">Mission & Vision</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom" href="#subsystems">Subsystems</a></li>
                <li class="nav-item"><a class="nav-link nav-link-custom" href="#workflow">Workflow</a></li>
                <li class="nav-item ms-lg-2">
                    <a class="nav-link nav-link-custom px-3 py-1 text-gold" href="<?= e($portalUrl) ?>/citizen_portal/" style="border: 1px solid var(--gold-border); border-radius: 6px; background: rgba(212,175,55,0.08); font-size: 0.85rem;">
                        <i class="bi bi-person-badge me-1 text-warning"></i>Citizen Portal
                    </a>
                </li>
            </ul>

        </div>
    </div>
</nav>

<main>
    <!-- HERO SECTION -->
    <section class="hero-luxury" id="home">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="hero-eyebrow">BUILDING RESPONSIVE LEGISLATION</div>
                    
                    <h1 class="hero-heading">
                        Better Governance. <br>
                        <span class="highlight-gold">Stronger Futures.</span>
                    </h1>

                    <p class="hero-subtext">
                        Interconnected digital framework for ordinance tracking, agenda scheduling, public hearings, voting, and citizen consultation. Built for transparent municipal administration at Manila City Hall.
                    </p>

                    <div class="d-flex flex-wrap gap-3">
                        <a href="#subsystems" class="btn-gold-action btn-gold-lg">
                            <span>Explore Subsystems</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="<?= e($portalUrl) ?>/citizen_portal/" class="btn-gold-action btn-gold-lg" style="background: rgba(15, 28, 52, 0.85); border: 1px solid var(--gold-primary); color: var(--gold-light);">
                            <i class="bi bi-person-check me-2"></i>
                            <span>Public Citizen Portal</span>
                        </a>
                    </div>
                </div>

                <div class="col-lg-5 text-center">
                    <div class="hero-logo-emblem" style="position: relative; display: inline-block;">
                        <div class="seal-glow-ring" style="position: relative; width: 420px; height: 420px; max-width: 100%; aspect-ratio: 1 / 1; margin: 0 auto; border-radius: 50% !important; padding: 10px;">
                            <img src="assets/images/logo.png" alt="Official Seal of the City of Manila" style="width: 100%; height: 100%; border-radius: 50% !important; object-fit: contain; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.6));">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="section-about" id="about">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <!-- Left White Card -->
                <div class="col-lg-7">
                    <div class="about-white-card">
                        <div class="eyebrow">ABOUT THE PLATFORM</div>
                        <h2>We don't just record policy. We build possibilities.</h2>
                        <p>
                            The Integrated Legislative Management System is a forward-thinking governance framework committed to shaping transparent, accountable, and citizen-first communities. From ordinance drafting to public consultation, we streamline legislative actions into verifiable digital records.
                        </p>
                        <p style="color: #64748B; font-size: 0.95rem; line-height: 1.7; margin-bottom: 2rem;">
                            Engineered for the City Government of Manila, our architecture unifies plenary sessions, committee management, historical archives, and real-time public consultation into an immutable single source of legislative truth.
                        </p>
                        <a href="#subsystems" class="card-link-gold" style="color: #B89350; font-size: 0.85rem;">
                            LEARN MORE ABOUT US &rarr;
                        </a>
                    </div>
                </div>

                <!-- Right Image Card (Beside About) -->
                <div class="col-lg-5">
                    <div class="about-image-card">
                        <img src="assets/images/manila_clock_tower_flag.jpg" alt="Manila City Hall Clock Tower with Philippine Flag" style="object-position: center 30%;">
                        <div class="image-caption-overlay">
                            <span class="caption-badge"><i class="bi bi-geo-alt-fill me-1"></i> MANILA CITY HALL</span>
                            <h5>Icon of Historic Municipal Governance</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- OPEN INSTITUTIONAL CHARTER: MISSION, VISION, VALUES (CONTAINERLESS ARCHITECTURAL REDESIGN) -->
    <section class="section-mvv" id="mvv">
        <div class="container">
            <div class="mvv-header-block">
                <div class="mvv-header-eyebrow">INSTITUTIONAL CHARTER</div>
                <h2 class="mvv-header-title">Mission, Vision & Core Values</h2>
                <p class="mvv-header-desc">
                    Anchored on constitutional stewardship, democratic integrity, and relentless innovation to serve the people of Manila.
                </p>
            </div>

            <div class="row g-4 g-lg-0 align-items-stretch mvv-charter-row">
                <!-- Pillar 1: Mission -->
                <div class="col-lg-4 mvv-pillar-divider">
                    <div class="mvv-pillar mvv-pillar-anim mvv-pillar-left">
                        <div class="mvv-crest-badge">
                            <i class="bi bi-compass"></i>
                        </div>
                        <h3 class="mvv-heading">Our Mission</h3>
                        <div class="mvv-title-line"></div>
                        <p class="mvv-description">
                            To deliver exceptional public consultation tools that enrich civic lives and empower local democracy, transforming municipal ordinances and council resolutions into transparent, verifiable public records.
                        </p>
                    </div>
                </div>

                <!-- Pillar 2: Vision -->
                <div class="col-lg-4 mvv-pillar-divider">
                    <div class="mvv-pillar mvv-pillar-anim mvv-pillar-center">
                        <div class="mvv-crest-badge">
                            <i class="bi bi-eye"></i>
                        </div>
                        <h3 class="mvv-heading">Our Vision</h3>
                        <div class="mvv-title-line"></div>
                        <p class="mvv-description">
                            To be the nation's premier digital legislative framework recognized for institutional transparency, unbroken integrity, civic-first governance, and measurable socioeconomic impact across all districts.
                        </p>
                    </div>
                </div>

                <!-- Pillar 3: Values -->
                <div class="col-lg-4">
                    <div class="mvv-pillar mvv-pillar-anim mvv-pillar-right">
                        <div class="mvv-crest-badge">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3 class="mvv-heading">Core Values</h3>
                        <div class="mvv-title-line"></div>
                        <p class="mvv-description">
                            We are anchored on the non-negotiable principles of municipal governance: absolute integrity, active transparency, civic accountability, public empathy, and continuous technological innovation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURED DEVELOPMENTS / SUBSYSTEMS SECTION -->
    <section class="section-subsystems" id="subsystems" style="overflow: hidden;">
        <div class="container-fluid px-lg-5">
            <div class="section-header-center">
                <div class="eyebrow">FEATURED LEGISLATIVE SUBSYSTEMS</div>
                <h2>Interconnected Systems</h2>
                <p class="text-muted">Each subsystem operates independently while sharing a common legislative database and integrated user security.</p>
            </div>

            <div class="subsystems-5col-grid">
                <?php foreach ($subsystems as $index => $system): ?>
                    <?php 
                        $animClass = ($index < 5) ? 'anim-from-left' : 'anim-from-right';
                        $delay = (($index % 5) * 0.15) . 's';
                    ?>
                    <div class="development-card <?= $animClass ?>" style="--anim-delay: <?= $delay ?>;" data-subsystem-url="<?= e($system['url']) ?>" data-subsystem-code="<?= e($system['folder']) ?>" title="Click to launch <?= e($system['short_title']) ?>">
                        <div class="card-body-content">
                            <div class="card-icon-box mb-3">
                                <i class="bi <?= e($system['icon']) ?>"></i>
                            </div>

                            <div class="card-title-serif"><?= e($system['title']) ?></div>
                            <div class="card-location mb-3 d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <span><?= e($system['location']) ?></span>
                                <span class="badge" style="background: rgba(34, 197, 94, 0.18); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); font-size: 0.68rem; font-weight: 600; padding: 3px 8px; border-radius: 4px;"><i class="bi bi-check-circle-fill me-1"></i>Active</span>
                            </div>

                            <ul class="module-check-list">
                                <?php foreach ($system['modules'] as $mod): ?>
                                    <li>
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span><?= e($mod) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <a href="<?= e($system['url']) ?>" class="card-link-gold mt-auto" data-subsystem-link="true">
                                <span>ACCESS SUBSYSTEM</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- TRUSTED GOVERNANCE & STATS STRIP -->
    <section class="section-trust">
        <div class="container">
            <div class="row g-5 align-items-center">
                <!-- Left Stats Grid -->
                <div class="col-lg-5">
                    <div class="trust-heading mb-4">
                        <h2>Trusted by leaders.<br>Driven by results.</h2>
                    </div>

                    <div class="row g-4">
                        <div class="col-6">
                            <div class="stat-box-num"><?= count($subsystems) ?></div>
                            <div class="stat-box-label">Subsystems Live</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box-num">100%</div>
                            <div class="stat-box-label">Encrypted Records</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box-num">24 / 7</div>
                            <div class="stat-box-label">Public Availability</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box-num">1</div>
                            <div class="stat-box-label">Unified Database</div>
                        </div>
                    </div>
                </div>

                <!-- Right Quote Cards -->
                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="quote-card">
                                <i class="bi bi-quote"></i>
                                <p>"The integrated portal delivered complete transparency for our public consultations. Citizen engagement has never been higher."</p>
                                <div class="quote-author">Hon. Executive Chair</div>
                                <div class="quote-role">Committee on Public Hearings</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="quote-card">
                                <i class="bi bi-quote"></i>
                                <p>"Automating our ordinance life cycle reduced processing time while providing a complete audit trail for all legislative decisions."</p>
                                <div class="quote-author">City Secretariat</div>
                                <div class="quote-role">Legislative Management Division</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- WORKFLOW TIMELINE (01 to 10 IN 5x2 GRID) -->
    <section class="section-workflow" id="workflow" style="overflow: hidden;">
        <div class="container-fluid px-lg-5">
            <div class="section-header-center">
                <div class="eyebrow">OUR LEGISLATIVE PROCESS</div>
                <h2>From Proposal to Enactment</h2>
            </div>

            <div class="workflow-steps-container">
                <div class="workflow-step anim-from-left" style="--anim-delay: 0s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">01</div>
                        <div class="step-title">Research & Policy</div>
                        <div class="step-desc">Policy research, data collection, and impact evaluation.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-left" style="--anim-delay: 0.15s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">02</div>
                        <div class="step-title">Drafting & Filing</div>
                        <div class="step-desc">Ordinance formulation, resolution drafting, and initial filing.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-left" style="--anim-delay: 0.3s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">03</div>
                        <div class="step-title">Records & Control</div>
                        <div class="step-desc">Document repository encoding, version control, and audit security.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-left" style="--anim-delay: 0.45s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">04</div>
                        <div class="step-title">Agenda Scheduling</div>
                        <div class="step-desc">Priority setting, meeting coordination, and calendar scheduling.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-left" style="--anim-delay: 0.6s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">05</div>
                        <div class="step-title">Committee Work</div>
                        <div class="step-desc">Committee formation, member assignment, and scope definition.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-right" style="--anim-delay: 0s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">06</div>
                        <div class="step-title">Public Hearing</div>
                        <div class="step-desc">Stakeholder invitation, registration, attendance, and proceedings.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-right" style="--anim-delay: 0.15s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">07</div>
                        <div class="step-title">Citizen Feedback</div>
                        <div class="step-desc">Public feedback submission, proposal tracking, and moderation.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-right" style="--anim-delay: 0.3s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">08</div>
                        <div class="step-title">Session & Meeting</div>
                        <div class="step-desc">Session scheduling, proceedings documentation, and minutes.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-right" style="--anim-delay: 0.45s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">09</div>
                        <div class="step-title">Quorum & Voting</div>
                        <div class="step-desc">Quorum verification, electronic vote tallying, and validation.</div>
                    </div>
                </div>

                <div class="workflow-step anim-from-right" style="--anim-delay: 0.6s;">
                    <div class="workflow-step-card">
                        <div class="step-circle">10</div>
                        <div class="step-title">Archiving & Records</div>
                        <div class="step-desc">Digital archiving, historical digitization, and retention compliance.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- FOOTER -->
<footer class="luxury-footer">
    <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div>
                <div class="footer-brand-title">INTEGRATED LEGISLATIVE MANAGEMENT SYSTEM</div>
                <div class="mt-1">&copy; <?= date('Y') ?> City Government. All Rights Reserved.</div>
            </div>

            <div class="footer-links">
                <a href="#home">Home</a>
                <a href="#about">About</a>
                <a href="#subsystems">Subsystems</a>
            </div>
        </div>
    </div>
</footer>

<script src="<?= e(vendorAsset('bootstrap/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js')) ?>"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Intersection Observer for Slide Entrance
    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll(".anim-from-left, .anim-from-right, .mvv-pillar-anim, .mvv-card-anim").forEach(el => {
            el.classList.add("in-view");
        });
    } else {
        const observerOptions = {
            root: null,
            rootMargin: "0px 0px -40px 0px",
            threshold: 0.08
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("in-view");
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll(".anim-from-left, .anim-from-right, .mvv-pillar-anim, .mvv-card-anim").forEach(el => {
            observer.observe(el);
        });
    }

    // 2. Fullscreen Card Zoom Animation (3-Second Slow Cinematic Zoom)
    let isCardZooming = false;

    function launchCardFullscreenZoom(cardEl, targetUrl) {
        if (isCardZooming) return;
        isCardZooming = true;

        const rect = cardEl.getBoundingClientRect();

        // Extract subsystem details from card
        const title = cardEl.querySelector('.card-title-serif')?.innerText || 'Legislative Subsystem';
        const location = cardEl.querySelector('.card-location span')?.innerText || 'Active Subsystem';
        const iconClass = cardEl.querySelector('.card-icon-box i')?.className || 'bi bi-shield-check';

        // 1. Dark frosted backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'card-zoom-backdrop';
        document.body.appendChild(backdrop);

        // 2. Clone card for 3-second fullscreen zoom
        const zoomCard = document.createElement('div');
        zoomCard.className = 'development-card card-fullscreen-zoom';
        zoomCard.style.top = rect.top + 'px';
        zoomCard.style.left = rect.left + 'px';
        zoomCard.style.width = rect.width + 'px';
        zoomCard.style.height = rect.height + 'px';
        zoomCard.style.borderRadius = '14px';

        // Fullscreen interior layout with pure white background & big Manila Logo
        zoomCard.innerHTML = `
            <div class="card-body-content" style="position: absolute; inset: 0; padding: 1.5rem; opacity: 1; pointer-events: none; z-index: 0;">
                ${cardEl.querySelector('.card-body-content')?.innerHTML || ''}
            </div>
            <div class="zoom-portal-content">
                <div class="zoom-manila-logo-large">
                    <img src="assets/images/manila.png" alt="City of Manila Seal">
                </div>
                <div class="zoom-city-label">LUNGSOD NG MAYNILA • CITY OF MANILA</div>
                <h2 class="zoom-subsystem-title">${title}</h2>
                <div class="zoom-subsystem-location">
                    <span>${location}</span>
                    <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.4); font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 5px;"><i class="bi bi-check-circle-fill me-1"></i>Active Subsystem</span>
                </div>
                <div class="zoom-progress-container">
                    <div class="zoom-progress-bar"></div>
                </div>
                <div class="zoom-status-text" id="zoom-status-label">INITIALIZING SECURE SESSION...</div>
            </div>
        `;

        document.body.appendChild(zoomCard);

        // Dim original card in grid
        cardEl.style.opacity = '0.2';

        // Force reflow
        void zoomCard.offsetHeight;

        // Animate expansion to full screen in next frame over 3 seconds
        requestAnimationFrame(() => {
            backdrop.classList.add('active');
            zoomCard.classList.add('zoomed-in');
        });

        // Update progress text dynamically across 3 seconds
        const statusLabel = zoomCard.querySelector('#zoom-status-label');
        setTimeout(() => {
            if (statusLabel) statusLabel.innerText = 'AUTHENTICATING LEGISLATIVE ACCESS...';
        }, 1100);

        setTimeout(() => {
            if (statusLabel) statusLabel.innerText = 'CONNECTING TO SUBSYSTEM ENVIRONMENT...';
        }, 2100);

        // At exactly 3 seconds (3000ms), navigate to target URL
        setTimeout(() => {
            window.location.href = targetUrl;
        }, 3000);
    }

    // 3. Attach Click Event to Subsystem Cards and Access Buttons
    document.querySelectorAll('.development-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.which === 2) return;

            const targetUrl = this.getAttribute('data-subsystem-url') || this.querySelector('a.card-link-gold')?.getAttribute('href');
            if (!targetUrl) return;

            e.preventDefault();
            launchCardFullscreenZoom(this, targetUrl);
        });
    });
});
</script>

</body>
</html>