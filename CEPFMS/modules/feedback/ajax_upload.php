<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.feedback.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['submission_id']??0);
$s=cefSubmissionRow($pdo,$id);
if(!$s||$s['submission_type']!=='Feedback')jsonResponse(false,'Feedback record not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a file.');

try{
    cefUploadSubmissionDocument(
        $pdo,$id,$_FILES['document'],
        clean($_POST['document_type']??'Internal Feedback Document'),
        clean($_POST['visibility']??'Internal')
    );
    cefSubmissionHistory($pdo,$id,'Document Uploaded',$s['status'],$s['status'],'Staff document added.',false);
    jsonResponse(true,'Document uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload document.');
}
