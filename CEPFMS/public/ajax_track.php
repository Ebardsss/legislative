<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    jsonResponse(false,'Invalid request method.',[],405);
}
requireCsrf();

$reference=clean($_POST['reference_number']??'');
$token=clean($_POST['tracking_token']??'');

if($reference===''||$token===''){
    jsonResponse(false,'Enter both the reference number and private tracking token.');
}

try{
    $result=cefPublicTrack(db(),$reference,$token);

    if(!$result){
        jsonResponse(false,'No matching submission was found. Check both the reference number and tracking token.',[],404);
    }

    $s=$result['submission'];
    $history=array_map(
        static fn(array $row): array => [
            'action'=>$row['action'],
            'status'=>cefPublicStatus((string)($row['new_status']??'')),
            'details'=>$row['details'],
            'created_at'=>formatDateTime($row['created_at']),
        ],
        $result['history']
    );

    $response=null;
    if($result['response']){
        $response=[
            'reference'=>$result['response']['response_reference'],
            'subject'=>$result['response']['subject'],
            'body'=>$result['response']['body'],
            'status'=>$result['response']['status'],
            'delivery_channel'=>$result['response']['delivery_channel'],
            'date'=>formatDateTime(
                $result['response']['delivered_at']
                ?: $result['response']['approved_at']
            ),
        ];
    }

    jsonResponse(true,'Submission found.',[
        'submission'=>[
            'reference_number'=>$s['reference_number'],
            'submission_type'=>cefSubmissionTypeLabel($s['submission_type']),
            'title'=>$s['title'],
            'status'=>cefPublicStatus($s['status']),
            'category'=>$s['category_name']?:'Not yet classified',
            'priority'=>$s['priority_level'],
            'submitted_at'=>formatDateTime($s['created_at']),
            'updated_at'=>formatDateTime($s['updated_at']),
            'can_followup'=>!in_array($s['status'],['Closed','Rejected','Duplicate','Withdrawn'],true),
        ],
        'history'=>$history,
        'response'=>$response,
    ]);
}catch(Throwable $e){
    jsonResponse(
        false,
        APP_DEBUG?$e->getMessage():'Unable to track the submission right now.',
        [],
        500
    );
}
