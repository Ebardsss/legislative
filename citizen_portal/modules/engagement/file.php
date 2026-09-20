<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

$pdo=db();
$id=(int)($_GET['id']??0);
$kind=clean($_GET['kind']??'submission');
$mode=clean($_GET['mode']??'view');
$mode=$mode==='download'?'download':'view';

if($kind==='response'){
    $q=$pdo->prepare(
        'SELECT d.id,d.file_name,d.file_path,d.mime_type,d.file_size,
                s.reference_number
         FROM cef_response_documents d
         JOIN cef_responses r ON r.id=d.response_id
         JOIN cef_submissions s ON s.id=r.submission_id
         WHERE d.id=:id
           AND d.visibility="Public"
           AND r.status="Delivered"
           AND s.citizen_user_id=:user
           AND s.deleted_at IS NULL
         LIMIT 1'
    );
}else{
    $q=$pdo->prepare(
        'SELECT d.id,d.file_name,d.file_path,d.mime_type,d.file_size,
                s.reference_number
         FROM cef_submission_documents d
         JOIN cef_submissions s ON s.id=d.submission_id
         WHERE d.id=:id
           AND d.visibility="Public"
           AND s.citizen_user_id=:user
           AND s.deleted_at IS NULL
         LIMIT 1'
    );
}

$q->execute([
    ':id'=>$id,
    ':user'=>currentUserId(),
]);
$doc=$q->fetch();

if(!$doc){
    http_response_code(404);
    exit('Attachment not found.');
}

$path=citizenSafeCefFilePath((string)$doc['file_path']);
if(!$path){
    http_response_code(404);
    exit('Attachment file is unavailable.');
}

$mime=trim((string)($doc['mime_type']??''));
if($mime===''&&function_exists('mime_content_type')){
    $mime=(string)(@mime_content_type($path)?:'');
}
if($mime==='')$mime='application/octet-stream';

$inlineAllowed=(bool)preg_match(
    '#^(image/(png|jpeg|gif|webp)|application/pdf|text/plain)$#i',
    $mime
);
$disposition=($mode==='view'&&$inlineAllowed)?'inline':'attachment';
$filename=basename((string)$doc['file_name']);
$ascii=preg_replace('/[^A-Za-z0-9._-]/','_',$filename)?:'attachment';

portalLog(
    currentUserId(),
    $disposition==='inline'?'Citizen Attachment View':'Citizen Attachment Download',
    $doc['reference_number'].' · '.$filename
);

header('X-Content-Type-Options: nosniff');
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header(
    'Content-Disposition: '.$disposition.'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($filename)
);
header('Cache-Control: private, no-store, max-age=0');

$fp=fopen($path,'rb');
if($fp===false){
    http_response_code(500);
    exit('Unable to read attachment.');
}

fpassthru($fp);
fclose($fp);
exit;
