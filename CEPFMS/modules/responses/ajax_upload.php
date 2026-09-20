<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.responses.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$responseId=(int)($_POST['response_id']??0);
$r=cefResponseRow($pdo,$responseId);
if(!$r)jsonResponse(false,'Response not found.');
if(empty($_FILES['document']))jsonResponse(false,'Choose a file.');

try{
    $up=handleUpload($_FILES['document'],'cepfms/responses');
    if(!$up['success'])jsonResponse(false,$up['message']);

    $visibility=cefDocumentVisibility(clean($_POST['visibility']??'Internal'));
    $mime=null;
    $full=UPLOAD_DIR.$up['file_path'];
    if(function_exists('mime_content_type')&&is_file($full))$mime=@mime_content_type($full)?:null;

    $pdo->prepare(
        'INSERT INTO cef_response_documents
         (response_id,file_name,stored_name,file_path,mime_type,file_size,document_type,visibility,uploaded_by,uploaded_at)
         VALUES(:response,:name,:stored,:path,:mime,:size,:type,:visibility,:user,NOW())'
    )->execute([
        ':response'=>$responseId,
        ':name'=>$up['file_name'],
        ':stored'=>$up['stored_name'],
        ':path'=>$up['file_path'],
        ':mime'=>$mime,
        ':size'=>$up['size'],
        ':type'=>clean($_POST['document_type']??'Response Attachment')?:'Response Attachment',
        ':visibility'=>$visibility,
        ':user'=>currentUserId()
    ]);

    cefResponseHistory($pdo,$responseId,'Document Uploaded',$r['status'],$r['status'],'Response attachment added.');
    jsonResponse(true,'Response attachment uploaded.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to upload response attachment.');
}
