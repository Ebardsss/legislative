<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.configuration.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.',[],405);
requireCsrf();

$pdo=db();
$id=(int)($_POST['category_id']??0);
$name=clean($_POST['name']??'');
$type=clean($_POST['category_type']??'All');
$description=trim((string)($_POST['description']??''));
$office=(int)($_POST['default_office_id']??0)?:null;
$committee=(int)($_POST['default_committee_id']??0)?:null;
$active=!empty($_POST['is_active'])?1:0;

if($name==='')jsonResponse(false,'Category name is required.');
if(!in_array($type,['All','Feedback','Proposal','Complaint'],true)){
    jsonResponse(false,'Invalid category type.');
}

try{
    if($office){
        $q=$pdo->prepare("SELECT COUNT(*) FROM offices WHERE id=:id AND status='Active'");
        $q->execute([':id'=>$office]);
        if((int)$q->fetchColumn()===0)jsonResponse(false,'Selected office is unavailable.');
    }
    if($committee){
        $q=$pdo->prepare("SELECT COUNT(*) FROM committees WHERE id=:id AND status='Active'");
        $q->execute([':id'=>$committee]);
        if((int)$q->fetchColumn()===0)jsonResponse(false,'Selected committee is unavailable.');
    }

    if($id){
        $q=$pdo->prepare('SELECT * FROM cef_categories WHERE id=:id');
        $q->execute([':id'=>$id]);$old=$q->fetch();
        if(!$old)jsonResponse(false,'Category not found.');

        $dup=$pdo->prepare(
            'SELECT COUNT(*) FROM cef_categories
             WHERE name=:name AND category_type=:type AND id<>:id'
        );
        $dup->execute([':name'=>$name,':type'=>$type,':id'=>$id]);
        if((int)$dup->fetchColumn()>0)jsonResponse(false,'A category with the same name and type already exists.');

        $pdo->prepare(
            'UPDATE cef_categories
             SET name=:name,category_type=:type,description=:description,
                 default_office_id=:office,default_committee_id=:committee,
                 is_active=:active,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':name'=>$name,':type'=>$type,':description'=>$description?:null,
            ':office'=>$office,':committee'=>$committee,':active'=>$active,':id'=>$id
        ]);

        cepfmsLogActivity(currentUserId(),'CEPFMS Category Updated',
            'Category #'.$id.' · '.$old['name'].' -> '.$name.'.');
        jsonResponse(true,'Category updated.');
    }

    $pdo->prepare(
        'INSERT INTO cef_categories
         (name,category_type,description,default_office_id,default_committee_id,
          is_active,created_by,created_at,updated_at)
         VALUES(:name,:type,:description,:office,:committee,:active,:user,NOW(),NOW())'
    )->execute([
        ':name'=>$name,':type'=>$type,':description'=>$description?:null,
        ':office'=>$office,':committee'=>$committee,':active'=>$active,
        ':user'=>currentUserId()
    ]);

    $newId=(int)$pdo->lastInsertId();
    cepfmsLogActivity(currentUserId(),'CEPFMS Category Created',
        'Category #'.$newId.' · '.$name.'.');

    jsonResponse(true,'Category created.');
}catch(PDOException $e){
    if((string)$e->getCode()==='23000')jsonResponse(false,'A category with the same name and type already exists.');
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save category.');
}catch(Throwable $e){
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save category.');
}
