<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$responseId=(int)($_POST['response_id']??0);
$submissionId=(int)($_POST['submission_id']??0);
$type=clean($_POST['response_type']??'Official Response');
$subject=clean($_POST['subject']??'');
$body=trim((string)($_POST['body']??''));
$assignment=(int)($_POST['assignment_id']??0)?:null;

if($subject===''||$body==='')jsonResponse(false,'Response subject and body are required.');
if(!in_array($type,['Acknowledgement','Progress Update','Clarification Request','Official Response','Resolution Notice','Final Response'],true)){
    jsonResponse(false,'Invalid response type.');
}

try{
    $pdo->beginTransaction();

    $s=cefSubmissionRow($pdo,$submissionId);
    if(!$s){$pdo->rollBack();jsonResponse(false,'Submission not found.');}
    if($s['moderation_status']!=='Validated'){
        $pdo->rollBack();
        jsonResponse(false,'Only validated citizen submissions may enter official response drafting.');
    }
    if(in_array($s['status'],['Rejected','Duplicate','Withdrawn','Closed'],true)){
        $pdo->rollBack();jsonResponse(false,'This submission cannot receive an official response.');
    }

    if($assignment){
        $a=$pdo->prepare(
            'SELECT id FROM cef_assignments
             WHERE id=:id AND submission_id=:submission'
        );
        $a->execute([':id'=>$assignment,':submission'=>$submissionId]);
        if(!$a->fetchColumn()){$pdo->rollBack();jsonResponse(false,'Selected assignment does not belong to this submission.');}
    }

    if($responseId){
        $q=$pdo->prepare(
            'SELECT * FROM cef_responses
             WHERE id=:id AND submission_id=:submission
             FOR UPDATE'
        );
        $q->execute([':id'=>$responseId,':submission'=>$submissionId]);
        $old=$q->fetch();
        if(!$old){$pdo->rollBack();jsonResponse(false,'Response draft not found.');}
        if(!in_array($old['status'],['Draft','Returned'],true)){
            $pdo->rollBack();jsonResponse(false,'Only Draft or Returned responses can be edited.');
        }

        $pdo->prepare(
            'UPDATE cef_responses
             SET assignment_id=:assignment,response_type=:type,subject=:subject,body=:body,
                 status="Draft",reviewed_by=NULL,reviewed_at=NULL,approved_by=NULL,approved_at=NULL,
                 updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':assignment'=>$assignment,':type'=>$type,':subject'=>$subject,
            ':body'=>$body,':id'=>$responseId
        ]);
        cefResponseHistory($pdo,$responseId,'Edit Draft',$old['status'],'Draft','Official response draft updated.');
        $message='Response draft updated.';
    }else{
        $reference=cefSequenceReference($pdo,'response','RSP');
        $pdo->prepare(
            'INSERT INTO cef_responses
             (submission_id,assignment_id,response_reference,response_type,subject,body,status,
              drafted_by,created_at,updated_at)
             VALUES(:submission,:assignment,:reference,:type,:subject,:body,"Draft",:user,NOW(),NOW())'
        )->execute([
            ':submission'=>$submissionId,':assignment'=>$assignment,':reference'=>$reference,
            ':type'=>$type,':subject'=>$subject,':body'=>$body,':user'=>currentUserId()
        ]);
        $responseId=(int)$pdo->lastInsertId();
        cefResponseHistory($pdo,$responseId,'Create Draft',null,'Draft','Official response draft created.');
        $message='Response draft created.';
    }

    cepfmsLogActivity(currentUserId(),'CEPFMS Response Draft',"Submission {$s['reference_number']} · response #{$responseId}.");
    $pdo->commit();
    jsonResponse(true,$message,['response_id'=>$responseId]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save response draft.');
}
