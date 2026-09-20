<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.view');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="cepfms_citizen_engagement_analytics_'.date('Ymd_His').'.csv"');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");

fputcsv($out,[
 'Reference','Type','Title','Category','District','Barangay','Priority',
 'Moderation','Status','Created','Updated'
]);

$rows=db()->query(
 "SELECT s.reference_number,s.submission_type,s.title,c.name category_name,
         s.district,s.barangay,s.priority_level,s.moderation_status,s.status,
         s.created_at,s.updated_at
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  ORDER BY s.created_at DESC"
);
while($r=$rows->fetch()){
    fputcsv($out,[
        $r['reference_number'],$r['submission_type'],$r['title'],
        $r['category_name'],$r['district'],$r['barangay'],$r['priority_level'],
        $r['moderation_status'],$r['status'],$r['created_at'],$r['updated_at']
    ]);
}
fclose($out);
cepfmsLogActivity(currentUserId(),'CEPFMS Analytics Export','Citizen engagement CSV export.');
exit;
