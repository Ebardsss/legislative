<?php
declare(strict_types=1);

$activeMenu=$activeMenu??'';

$mainItems=[
    [
        'key'=>'dashboard','label'=>'Dashboard','icon'=>'bi-speedometer2',
        'href'=>appUrl('dashboard.php'),'permission'=>'cepfms.dashboard.view'
    ],
    [
        'key'=>'citizen_portal','label'=>'Citizen Portal','icon'=>'bi-globe2',
        'href'=>citizenPortalUrl('index.php'),'external'=>true,'permission'=>null
    ],
    [
        'key'=>'engagement_workflow','label'=>'Engagement Workflow','icon'=>'bi-diagram-3',
        'href'=>appUrl('pages/engagement_workflow.php'),'permission'=>'cepfms.workflow.view'
    ],
    [
        'key'=>'reports','label'=>'Reports & Workflow','icon'=>'bi-bar-chart-line',
        'href'=>appUrl('reports/index.php'),'permission'=>'cepfms.reports.view'
    ],
];

$moduleItems=[
    ['key'=>'feedback','label'=>'Public Feedback Submission','icon'=>'bi-chat-square-text','href'=>appUrl('modules/feedback/index.php'),'permission'=>'cepfms.feedback.view'],
    ['key'=>'proposals','label'=>'Proposal & Suggestion Management','icon'=>'bi-lightbulb','href'=>appUrl('modules/proposals/index.php'),'permission'=>'cepfms.proposals.view'],
    ['key'=>'complaints','label'=>'Complaint & Issue Tracking','icon'=>'bi-exclamation-diamond','href'=>appUrl('modules/complaints/index.php'),'permission'=>'cepfms.complaints.view'],
    ['key'=>'moderation','label'=>'Moderation & Validation','icon'=>'bi-shield-check','href'=>appUrl('modules/moderation/index.php'),'permission'=>'cepfms.moderation.view'],
    ['key'=>'responses','label'=>'Response Management','icon'=>'bi-reply-all','href'=>appUrl('modules/responses/index.php'),'permission'=>'cepfms.responses.view'],
    ['key'=>'analytics','label'=>'Citizen Engagement Analytics','icon'=>'bi-graph-up-arrow','href'=>appUrl('modules/analytics/index.php'),'permission'=>'cepfms.analytics.view'],
];

$adminItems=[
    ['key'=>'configuration','label'=>'CEPFMS Configuration','icon'=>'bi-sliders','href'=>appUrl('pages/configuration.php'),'permission'=>'cepfms.configuration.manage'],
    ['key'=>'activity_logs','label'=>'Activity Logs','icon'=>'bi-clock-history','href'=>appUrl('pages/activity_logs.php'),'permission'=>'cepfms.activity_logs.view'],
    ['key'=>'users','label'=>'User Management','icon'=>'bi-people-fill','href'=>appUrl('pages/users.php'),'permission'=>'cepfms.users.manage'],
    ['key'=>'system_health','label'=>'System Health','icon'=>'bi-heart-pulse','href'=>appUrl('pages/system_health.php'),'permission'=>'cepfms.system_health.view'],
    ['key'=>'ai_health','label'=>'Ollama AI Health','icon'=>'bi-cpu','href'=>appUrl('pages/ai_health.php'),'permission'=>'cepfms.system_health.view'],
];

$canShow=static function(array $item): bool {
    return empty($item['permission'])||cefHasPermission($item['permission']);
};
?>
<aside class="orlms-sidebar sidebar" id="orlmsSidebar">
<div class="orlms-sidebar-brand sidebar-brand">
    <img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City of Manila Seal" class="orlms-sidebar-logo sidebar-logo" style="width:60px !important;height:60px !important;max-width:60px !important;max-height:60px !important;object-fit:contain;">
    <div><strong>CEPFMS</strong><small>Citizen Engagement & Public Feedback</small></div>
</div>

<nav class="orlms-sidebar-nav sidebar-navigation">
<div class="orlms-sidebar-section sidebar-section-label">Overview</div>
<?php foreach($mainItems as $item): if(!$canShow($item))continue; ?>
<a href="<?= e($item['href']) ?>" class="orlms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>" <?= !empty($item['external'])?'target="_blank"':'' ?>>
<i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
<?php if($activeMenu===$item['key']):?><b></b><?php endif;?>
</a>
<?php endforeach; ?>

<div class="orlms-sidebar-section sidebar-section-label">Citizen Engagement</div>
<?php foreach($moduleItems as $item): if(!$canShow($item))continue; ?>
<a href="<?= e($item['href']) ?>" class="orlms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span><?php if($activeMenu===$item['key']):?><b></b><?php endif;?></a>
<?php endforeach; ?>

<?php $visibleAdmin=array_values(array_filter($adminItems,$canShow)); ?>
<?php if($visibleAdmin): ?>
<div class="orlms-sidebar-section sidebar-section-label">Administration</div>
<?php foreach($visibleAdmin as $item): ?>
<a href="<?= e($item['href']) ?>" class="orlms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span><?php if($activeMenu===$item['key']):?><b></b><?php endif;?></a>
<?php endforeach; ?>
<?php endif; ?>
</nav>

<div class="orlms-sidebar-footer sidebar-session-card" title="Shared Session Enabled"><i class="bi bi-shield-check"></i><div><strong>Shared Access Enabled</strong><small>Session: <?= e(SESSION_NAME) ?></small></div></div>
</aside>
<div class="orlms-sidebar-backdrop sidebar-backdrop" id="orlmsSidebarBackdrop"></div>

