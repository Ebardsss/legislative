<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();
$scope=clean($_POST['scope']??'submission');
$documentId=(int)($_POST['document_id']??0);
$visibility=cefDocumentVisibility(clean($_POST['visibility']??'Internal'));

try{
    if($scope==='response'){
        $q=$pdo->prepare(
            'SELECT d.id,d.file_name,d.visibility,r.submission_id
             FROM cef_response_documents d
             JOIN cef_responses r ON r.id=d.response_id
             WHERE d.id=:id
             LIMIT 1'
        );
        $q->execute([':id'=>$documentId]);
        $doc=$q->fetch();
        if(!$doc)jsonResponse(false,'Response attachment not found.',[],404);
        if(!cefHasPermission('cepfms.responses.manage'))jsonResponse(false,'Permission denied.',[],403);

        $pdo->prepare(
            'UPDATE cef_response_documents
             SET visibility=:visibility
             WHERE id=:id'
        )->execute([':visibility'=>$visibility,':id'=>$documentId]);

        $submissionId=(int)$doc['submission_id'];
    }else{
        $q=$pdo->prepare(
            'SELECT d.id,d.file_name,d.visibility,s.id submission_id,s.submission_type
             FROM cef_submission_documents d
             JOIN cef_submissions s ON s.id=d.submission_id
             WHERE d.id=:id
             LIMIT 1'
        );
        $q->execute([':id'=>$documentId]);
        $doc=$q->fetch();
        if(!$doc)jsonResponse(false,'Ticket attachment not found.',[],404);

        $permission=cefTicketManagePermission((string)$doc['submission_type']);
        if(!cefHasPermission($permission))jsonResponse(false,'Permission denied.',[],403);

        $pdo->prepare(
            'UPDATE cef_submission_documents
             SET visibility=:visibility
             WHERE id=:id'
        )->execute([':visibility'=>$visibility,':id'=>$documentId]);

        $submissionId=(int)$doc['submission_id'];
    }

    $s=cefSubmissionRow($pdo,$submissionId);
    if($s){
        cefSubmissionHistory(
            $pdo,$submissionId,'Attachment Visibility Updated',$s['status'],$s['status'],
            $visibility==='Public'
                ? 'An attachment is now available to the citizen.'
                : 'An attachment was changed to Internal Only.',
            $visibility==='Public'
        );
    }

    jsonResponse(true,$visibility==='Public'?'Attachment is now Citizen Visible.':'Attachment is now Internal Only.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update attachment visibility.',[],500);
}
