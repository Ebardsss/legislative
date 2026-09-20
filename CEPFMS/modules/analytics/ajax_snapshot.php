<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();

try{
    $pdo->beginTransaction();

    $today=date('Y-m-d');
    $periodStart=date('Y-m-d',strtotime('-29 days'));
    $summary=cefAnalyticsSummary($pdo);

    $metrics=[
        'submissions.total'=>$summary['total'],
        'submissions.feedback'=>$summary['feedback'],
        'submissions.proposals'=>$summary['proposals'],
        'submissions.complaints'=>$summary['complaints'],
        'moderation.pending'=>$summary['pending_moderation'],
        'complaints.open'=>$summary['open_complaints'],
        'complaints.overdue'=>$summary['overdue_complaints'],
        'responses.delivered'=>$summary['delivered_responses'],
    ];

    foreach($metrics as $key=>$value){
        $pdo->prepare(
            'INSERT INTO cef_analytics_snapshots
             (snapshot_date,period_start,period_end,metric_key,dimension_type,
              dimension_value,metric_value,metadata_json,created_at)
             VALUES(:snapshot,:start,:end,:key,NULL,NULL,:value,NULL,NOW())'
        )->execute([
            ':snapshot'=>$today,':start'=>$periodStart,':end'=>$today,
            ':key'=>$key,':value'=>$value
        ]);
    }

    $topCategories=$pdo->prepare(
        "SELECT COALESCE(c.name,'Unclassified') dimension_value,COUNT(*) metric_value
         FROM cef_submissions s
         LEFT JOIN cef_categories c ON c.id=s.category_id
         WHERE s.deleted_at IS NULL
           AND s.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
         GROUP BY COALESCE(c.name,'Unclassified')
         HAVING COUNT(*)>=3
         ORDER BY metric_value DESC
         LIMIT 20"
    );
    $topCategories->execute();

    $alertsCreated=0;
    foreach($topCategories->fetchAll() as $row){
        $exists=$pdo->prepare(
            'SELECT COUNT(*)
             FROM cef_analytics_alerts
             WHERE status="Open"
               AND metric_key="submissions.by_category"
               AND dimension_type="Category"
               AND dimension_value=:value
               AND period_end>=:period_start'
        );
        $exists->execute([
            ':value'=>$row['dimension_value'],
            ':period_start'=>$periodStart
        ]);
        if((int)$exists->fetchColumn()>0)continue;

        $value=(float)$row['metric_value'];
        $severity=$value>=10?'High':($value>=5?'Attention':'Monitor');

        $pdo->prepare(
            'INSERT INTO cef_analytics_alerts
             (alert_type,metric_key,dimension_type,dimension_value,period_start,period_end,
              metric_value,threshold_value,severity,status,details,created_by,created_at)
             VALUES("Recurring Civic Concern","submissions.by_category","Category",:dimension,
              :start,:end,:value,3,:severity,"Open",:details,:user,NOW())'
        )->execute([
            ':dimension'=>$row['dimension_value'],':start'=>$periodStart,':end'=>$today,
            ':value'=>$value,':severity'=>$severity,
            ':details'=>$row['dimension_value'].' recorded '.$value.' citizen submission(s) in the last 30 days.',
            ':user'=>currentUserId()
        ]);
        $alertsCreated++;
    }

    $pdo->commit();
    cepfmsLogActivity(currentUserId(),'CEPFMS Analytics Snapshot',"Snapshot {$today}; {$alertsCreated} new recurring-issue alert(s).");
    jsonResponse(true,'Analytics snapshot saved.',['alerts_created'=>$alertsCreated]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save analytics snapshot.');
}
