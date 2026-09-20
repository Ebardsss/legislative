<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/public_data.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT
        d.*,li.reference_number legislative_reference,li.title legislative_title,
        t.reference_number tally_reference,t.result_outcome,t.tally_status,
        ve.reference_number voting_reference,ve.voting_method,ve.vote_visibility,
        s.reference_number session_reference,s.session_title,s.session_date,s.venue
     FROM vqd_decisions d
     LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
     LEFT JOIN vqd_vote_tallies t ON t.id=d.tally_id
     LEFT JOIN vqd_voting_events ve ON ve.id=d.voting_event_id
     LEFT JOIN vqd_sessions s ON s.id=ve.session_id
     WHERE d.id=:id
       AND d.record_status="Published"
       AND d.release_classification="Public"
     LIMIT 1'
);
$q->execute([':id'=>$id]);
$decision=$q->fetch();

if(!$decision){
    http_response_code(404);
    exit('Published voting result not found.');
}

$options=[];
if(!empty($decision['tally_id'])){
    $oq=$pdo->prepare(
        'SELECT option_label,option_category,vote_count,percentage
         FROM vqd_vote_tally_options
         WHERE tally_id=:tally
         ORDER BY sequence_number,id'
    );
    $oq->execute([':tally'=>(int)$decision['tally_id']]);
    $options=$oq->fetchAll();
}

$validation=null;
$vq=$pdo->prepare(
    'SELECT validation_reference,validation_datetime,integrity_score,
            risk_level,challenge_status,validation_outcome,
            executive_summary,certification_statement,
            validation_status,certified_at
     FROM vqd_result_validations
     WHERE decision_id=:decision
       AND release_classification="Public"
       AND validation_status IN ("Validated","Certified","Published")
     ORDER BY certified_at DESC,id DESC
     LIMIT 1'
);
$vq->execute([':decision'=>$id]);
$validation=$vq->fetch()?:null;

portalLogPublicView('VQDSS',$decision['decision_record_number']?:$decision['reference_number']);

$pageTitle=$decision['decision_record_number']?:$decision['reference_number'];
$activeMenu='voting';
$extraCss=[appUrl('assets/css/public-modules.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-shell">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="page-head">
<div>
<a class="small text-decoration-none" href="<?= e(appUrl('modules/voting/index.php')) ?>"><i class="bi bi-arrow-left"></i> Voting Results</a>
<h1><?= e($decision['legislative_title']?:'Official Voting Decision') ?></h1>
<p><?= e($decision['decision_record_number']?:$decision['reference_number']) ?></p>
</div>
<span class="public-status <?= e(portalStatusClass($decision['official_outcome'])) ?>"><?= e($decision['official_outcome']) ?></span>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card portal-card mb-4">
<div class="card-header">Official Decision</div>
<div class="card-body">
<div class="public-detail-grid">
<div><small>Decision Date</small><strong><?= formatDateTime($decision['decision_datetime']) ?></strong></div>
<div><small>Classification</small><strong><?= e($decision['decision_classification']?:'—') ?></strong></div>
<div><small>Required Votes</small><strong><?= (int)$decision['required_votes'] ?></strong></div>
<div><small>Effectivity Date</small><strong><?= formatDate($decision['effectivity_date']) ?></strong></div>
<div><small>Voting Method</small><strong><?= e($decision['voting_method']?:'—') ?></strong></div>
<div><small>Session</small><strong><?= e($decision['session_title']?:'—') ?></strong></div>
</div>
<div class="public-text-block"><h3>Decision Statement</h3><p><?= nl2br(e($decision['decision_statement'])) ?></p></div>
<?php if($decision['legal_basis']): ?><div class="public-text-block"><h3>Legal Basis</h3><p><?= nl2br(e($decision['legal_basis'])) ?></p></div><?php endif; ?>
<?php if($decision['conditions']): ?><div class="public-text-block"><h3>Conditions</h3><p><?= nl2br(e($decision['conditions'])) ?></p></div><?php endif; ?>
</div>
</div>

<div class="card portal-card">
<div class="card-header">Vote Tally</div>
<div class="card-body">
<div class="vote-summary large">
<div class="yes"><strong><?= (int)$decision['affirmative_votes'] ?></strong><span>Affirmative</span></div>
<div class="no"><strong><?= (int)$decision['negative_votes'] ?></strong><span>Negative</span></div>
<div><strong><?= (int)$decision['abstain_votes'] ?></strong><span>Abstain</span></div>
</div>
<?php if($options): ?>
<div class="table-responsive mt-3">
<table class="table public-table mb-0">
<thead><tr><th>Option</th><th class="text-end">Votes</th><th class="text-end">Percentage</th></tr></thead>
<tbody>
<?php foreach($options as $o): ?><tr><td><?= e($o['option_label']) ?></td><td class="text-end"><?= (int)$o['vote_count'] ?></td><td class="text-end"><?= number_format((float)$o['percentage'],2) ?>%</td></tr><?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>
</div>

<div class="col-xl-4">
<div class="card portal-card mb-4">
<div class="card-header">Related References</div>
<div class="card-body public-side-list">
<div><small>Legislative Item</small><strong><?= e($decision['legislative_reference']?:'—') ?></strong></div>
<div><small>Voting Event</small><strong><?= e($decision['voting_reference']?:'—') ?></strong></div>
<div><small>Tally</small><strong><?= e($decision['tally_reference']?:'—') ?></strong></div>
<div><small>Session</small><strong><?= e($decision['session_reference']?:'—') ?></strong></div>
</div>
</div>

<?php if($validation): ?>
<div class="card portal-card">
<div class="card-header">Public Validation</div>
<div class="card-body">
<div class="public-side-list">
<div><small>Validation Reference</small><strong><?= e($validation['validation_reference']) ?></strong></div>
<div><small>Outcome</small><strong><?= e($validation['validation_outcome']) ?></strong></div>
<div><small>Integrity Score</small><strong><?= $validation['integrity_score']!==null?number_format((float)$validation['integrity_score'],2).'%':'—' ?></strong></div>
<div><small>Risk Level</small><strong><?= e($validation['risk_level']?:'—') ?></strong></div>
<div><small>Certified</small><strong><?= formatDateTime($validation['certified_at']) ?></strong></div>
</div>
<?php if($validation['executive_summary']): ?><div class="public-text-block mt-3"><h3>Executive Summary</h3><p><?= nl2br(e($validation['executive_summary'])) ?></p></div><?php endif; ?>
</div>
</div>
<?php endif; ?>
</div>
</div>

</main>
</div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
