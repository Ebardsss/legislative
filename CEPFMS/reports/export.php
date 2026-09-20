<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.reports.view');

$pdo=db();$report=clean($_GET['report']??'workflow');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="cepfms_'.$report.'_'.date('Ymd_His').'.csv"');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");

if($report==='submissions'){
    fputcsv($out,['Reference','Type','Title','Category','Priority','Moderation','Status','District','Barangay','Created','Updated']);
    $rows=$pdo->query("SELECT s.*,c.name category_name FROM cef_submissions s LEFT JOIN cef_categories c ON c.id=s.category_id WHERE s.deleted_at IS NULL ORDER BY s.created_at DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['reference_number'],$r['submission_type'],$r['title'],$r['category_name'],$r['priority_level'],$r['moderation_status'],$r['status'],$r['district'],$r['barangay'],$r['created_at'],$r['updated_at']]);
}elseif($report==='moderation'){
    fputcsv($out,['Reference','Type','Title','Moderation Status','Review Round','Decision','Reviewed']);
    $rows=$pdo->query("SELECT s.reference_number,s.submission_type,s.title,s.moderation_status,r.review_round,r.decision,r.reviewed_at FROM cef_submissions s LEFT JOIN cef_moderation_reviews r ON r.id=(SELECT x.id FROM cef_moderation_reviews x WHERE x.submission_id=s.id ORDER BY x.review_round DESC,x.id DESC LIMIT 1) WHERE s.deleted_at IS NULL ORDER BY s.created_at DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['reference_number'],$r['submission_type'],$r['title'],$r['moderation_status'],$r['review_round'],$r['decision'],$r['reviewed_at']]);
}elseif($report==='responses'){
    fputcsv($out,['Response','Submission','Subject','Type','Status','Approved','Delivered']);
    $rows=$pdo->query("SELECT r.response_reference,s.reference_number submission_reference,r.subject,r.response_type,r.status,r.approved_at,r.delivered_at FROM cef_responses r JOIN cef_submissions s ON s.id=r.submission_id ORDER BY r.created_at DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['response_reference'],$r['submission_reference'],$r['subject'],$r['response_type'],$r['status'],$r['approved_at'],$r['delivered_at']]);
}else{
    fputcsv($out,['Submission','Type','Title','Moderation','Assignment','Response','Response Status','Follow-Ups','Referrals','Current Status']);
    $rows=$pdo->query("SELECT s.id,s.reference_number,s.submission_type,s.title,s.moderation_status,s.status,COALESCE(o.name,cm.name,u.full_name) assignment_owner,r.response_reference,r.status response_status,(SELECT COUNT(*) FROM cef_followups f WHERE f.submission_id=s.id) followups,(SELECT COUNT(*) FROM cef_legislative_referrals lr WHERE lr.submission_id=s.id) referrals FROM cef_submissions s LEFT JOIN cef_assignments a ON a.id=(SELECT x.id FROM cef_assignments x WHERE x.submission_id=s.id AND x.status<>'Cancelled' ORDER BY x.assignment_role='Primary' DESC,x.id DESC LIMIT 1) LEFT JOIN offices o ON o.id=a.office_id LEFT JOIN committees cm ON cm.id=a.committee_id LEFT JOIN users u ON u.id=a.assigned_user_id LEFT JOIN cef_responses r ON r.id=(SELECT x.id FROM cef_responses x WHERE x.submission_id=s.id ORDER BY x.created_at DESC,x.id DESC LIMIT 1) WHERE s.deleted_at IS NULL ORDER BY s.created_at DESC");
    while($r=$rows->fetch())fputcsv($out,[$r['reference_number'],$r['submission_type'],$r['title'],$r['moderation_status'],$r['assignment_owner'],$r['response_reference'],$r['response_status'],$r['followups'],$r['referrals'],$r['status']]);
}
fclose($out);
cepfmsLogActivity(currentUserId(),'CEPFMS Report Export','CSV export: '.$report.'.');
exit;
