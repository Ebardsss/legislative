<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    jsonResponse(false,'Invalid request method.',[],405);
}
requireCsrf();

if(!cepfmsFoundationReady()){
    jsonResponse(false,'The citizen-engagement database foundation is not ready yet.',[],503);
}

$pdo=db();

try{
    $pdo->beginTransaction();

    $data=[
        'submission_type'=>clean($_POST['submission_type']??'Feedback'),
        'citizen_name'=>clean($_POST['citizen_name']??''),
        'citizen_email'=>clean($_POST['citizen_email']??''),
        'citizen_phone'=>clean($_POST['citizen_phone']??''),
        'contact_preference'=>clean($_POST['contact_preference']??'Portal'),
        'anonymous_flag'=>!empty($_POST['anonymous_flag']),
        'privacy_consent'=>!empty($_POST['privacy_consent']),
        'title'=>clean($_POST['title']??''),
        'summary'=>trim((string)($_POST['summary']??'')),
        'details'=>trim((string)($_POST['details']??'')),
        'category_id'=>(int)($_POST['category_id']??0),
        'location_text'=>clean($_POST['location_text']??''),
        'district'=>clean($_POST['district']??''),
        'barangay'=>clean($_POST['barangay']??''),
        'priority_level'=>clean($_POST['priority_level']??'Normal'),
        'source_channel'=>'Web Portal',

        'feedback_kind'=>clean($_POST['feedback_kind']??'General Feedback'),
        'service_area'=>clean($_POST['service_area']??''),
        'desired_outcome'=>trim((string)($_POST['desired_outcome']??'')),
        'citizen_rating'=>(int)($_POST['citizen_rating']??0),

        'problem_statement'=>trim((string)($_POST['problem_statement']??'')),
        'proposed_solution'=>trim((string)($_POST['proposed_solution']??'')),
        'expected_public_benefit'=>trim((string)($_POST['expected_public_benefit']??'')),
        'estimated_scope'=>clean($_POST['estimated_scope']??''),

        'affected_service'=>clean($_POST['affected_service']??''),
        'incident_datetime'=>clean($_POST['incident_datetime']??''),
        'urgency_level'=>clean($_POST['urgency_level']??'Normal'),
    ];

    $result=cefCreateSubmission($pdo,$data);

    if(
        isset($_FILES['evidence']) &&
        ($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE
    ){
        cefUploadSubmissionDocument(
            $pdo,
            (int)$result['id'],
            $_FILES['evidence'],
            'Citizen Supporting Evidence',
            'Internal',
            null
        );
        cefSubmissionHistory(
            $pdo,(int)$result['id'],'Evidence Uploaded',
            'Submitted','Submitted','Citizen supporting evidence received.',
            true,null
        );
    }

    cepfmsLogActivity(
        null,
        'CEPFMS Public Submission',
        $result['reference_number'].' · '.$data['submission_type'].' received from public portal.'
    );

    $pdo->commit();

    jsonResponse(true,'Your submission was received.',[
        'reference_number'=>$result['reference_number'],
        'tracking_token'=>$result['tracking_token'],
        'submission_type'=>$data['submission_type'],
    ]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(
        false,
        APP_DEBUG?$e->getMessage():'Unable to submit your record right now.',
        [],
        422
    );
}
