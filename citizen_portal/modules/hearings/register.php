<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('modules/hearings/index.php'));
requireCsrf();

$pdo=db();
$hearingId=(int)($_POST['hearing_id']??0);
$attendance=clean($_POST['attendance_type']??'On-site');

if(!in_array($attendance,['On-site','Online','Hybrid'],true))$attendance='On-site';

try{
    $pdo->beginTransaction();

    $hq=$pdo->prepare(
        'SELECT id,reference_number,title,status,registration_deadline,maximum_participants
         FROM hearings
         WHERE id=:id
           AND visibility="Public"
           AND status="Upcoming"
         FOR UPDATE'
    );
    $hq->execute([':id'=>$hearingId]);
    $hearing=$hq->fetch();
    if(!$hearing)throw new RuntimeException('This hearing is not open for public registration.');

    if($hearing['registration_deadline']&&strtotime($hearing['registration_deadline'])<time()){
        throw new RuntimeException('The registration deadline has already passed.');
    }

    if($hearing['maximum_participants']){
        $cq=$pdo->prepare(
            'SELECT COUNT(*)
             FROM registrations
             WHERE hearing_id=:hearing
               AND registration_status IN ("Pending","Approved")'
        );
        $cq->execute([':hearing'=>$hearingId]);
        if((int)$cq->fetchColumn()>=(int)$hearing['maximum_participants']){
            throw new RuntimeException('The public registration capacity has been reached.');
        }
    }

    $stakeholder=citizenEnsureStakeholder($pdo,currentUserId());

    $existing=$pdo->prepare(
        'SELECT id,registration_status
         FROM registrations
         WHERE stakeholder_id=:stakeholder
           AND hearing_id=:hearing
         LIMIT 1'
    );
    $existing->execute([
        ':stakeholder'=>(int)$stakeholder['id'],
        ':hearing'=>$hearingId,
    ]);
    if($existing->fetch()){
        throw new RuntimeException('You are already registered for this hearing.');
    }

    $code=citizenRegistrationCode();

    $pdo->prepare(
        'INSERT INTO registrations
         (stakeholder_id,hearing_id,registered_at,registration_code,
          registration_status,attendance_type,updated_at)
         VALUES(:stakeholder,:hearing,NOW(),:code,
          "Pending",:attendance,NOW())'
    )->execute([
        ':stakeholder'=>(int)$stakeholder['id'],
        ':hearing'=>$hearingId,
        ':code'=>$code,
        ':attendance'=>$attendance,
    ]);

    $registrationId=(int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO registration_history
         (registration_id,previous_status,new_status,details,changed_by,created_at)
         VALUES(:registration,NULL,"Pending",
          "Citizen submitted a public hearing registration through the Legislative Citizen Portal.",
          :user,NOW())'
    )->execute([
        ':registration'=>$registrationId,
        ':user'=>currentUserId(),
    ]);

    portalLog(
        currentUserId(),
        'Public Hearing Registration',
        ($hearing['reference_number']?:'Hearing #'.$hearingId).' · '.$code
    );

    $pdo->commit();

    setFlash('success','Your hearing registration was submitted. Registration code: '.$code);
    redirect(appUrl('modules/hearings/view.php?id='.$hearingId));
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    setFlash('danger',APP_DEBUG?$e->getMessage():'Unable to submit hearing registration.');
    redirect(appUrl('modules/hearings/view.php?id='.$hearingId));
}
