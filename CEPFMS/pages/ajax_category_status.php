<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.configuration.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.',[],405);
requireCsrf();

$pdo=db();$id=(int)($_POST['category_id']??0);
$active=(int)($_POST['is_active']??0)===1?1:0;

try{
    $q=$pdo->prepare('SELECT name,is_active FROM cef_categories WHERE id=:id');
    $q->execute([':id'=>$id]);$c=$q->fetch();
    if(!$c)jsonResponse(false,'Category not found.');

    $pdo->prepare(
        'UPDATE cef_categories
         SET is_active=:active,updated_at=NOW()
         WHERE id=:id'
    )->execute([':active'=>$active,':id'=>$id]);

    cepfmsLogActivity(
        currentUserId(),
        $active?'CEPFMS Category Activated':'CEPFMS Category Deactivated',
        'Category #'.$id.' · '.$c['name'].'.'
    );
    jsonResponse(true,$active?'Category activated.':'Category deactivated.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to update category.');
}
