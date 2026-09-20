<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/public_data.php';
requireLogin();

$pdo=db();
$pageTitle='Notifications';
$activeMenu='notifications';
$extraCss=[appUrl('assets/css/public-modules.css')];

$shared=$pdo->prepare(
    'SELECT id,title,message,target_url,is_read,created_at,"Shared" source
     FROM notifications
     WHERE user_id=:user
     ORDER BY created_at DESC,id DESC
     LIMIT 100'
);
$shared->execute([':user'=>currentUserId()]);
$rows=$shared->fetchAll();

$cef=$pdo->prepare(
    'SELECT nr.id,cn.subject title,cn.message,
            NULL target_url,
            CASE WHEN nr.delivery_status="Sent" THEN 1 ELSE 0 END is_read,
            cn.created_at,"CEPFMS" source,
            cn.submission_id
     FROM cef_notification_recipients nr
     JOIN cef_notifications cn ON cn.id=nr.notification_id
     WHERE nr.user_id=:user
       AND nr.delivery_channel IN ("Portal","System")
     ORDER BY cn.created_at DESC,nr.id DESC
     LIMIT 100'
);
$cef->execute([':user'=>currentUserId()]);
foreach($cef->fetchAll() as $r){
    if(!empty($r['submission_id'])){
        $r['target_url']=appUrl('modules/engagement/view.php?id='.(int)$r['submission_id']);
    }
    $rows[]=$r;
}

usort($rows,static fn(array $a,array $b): int =>
    strcmp((string)$b['created_at'],(string)$a['created_at'])
);

include __DIR__.'/../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div><span class="eyebrow">MY ACCOUNT</span><h1>Notifications</h1><p>Citizen notifications from the shared legislative platform and CEPFMS.</p></div>
</div>

<div class="card portal-card">
<div class="list-group list-group-flush">
<?php if(!$rows): ?><div class="p-5 text-center text-muted">No notifications yet.</div><?php endif; ?>
<?php foreach($rows as $n): ?>
<?php $url=portalPublicUrl($n['target_url']??null)?:((isset($n['target_url'])&&str_starts_with((string)$n['target_url'],APP_URL))?$n['target_url']:null); ?>
<div class="list-group-item p-3">
<div class="d-flex justify-content-between gap-3">
<div>
<div class="d-flex gap-2 align-items-center">
<strong><?= e($n['title']) ?></strong>
<span class="public-type"><?= e($n['source']) ?></span>
</div>
<div class="text-muted small mt-1"><?= e($n['message']) ?></div>
<?php if($url): ?><a class="small text-decoration-none" href="<?= e($url) ?>">Open related record <i class="bi bi-arrow-right"></i></a><?php endif; ?>
</div>
<small class="text-muted"><?= formatDateTime($n['created_at']) ?></small>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
