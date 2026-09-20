<?php
declare(strict_types=1);

require_once __DIR__.'/public_data.php';

function citizenStakeholder(PDO $pdo,int $userId): ?array
{
    $q=$pdo->prepare(
        'SELECT s.*,sc.name category_name
         FROM stakeholders s
         LEFT JOIN stakeholder_categories sc ON sc.id=s.category_id
         WHERE s.user_id=:user
         ORDER BY s.id
         LIMIT 1'
    );
    $q->execute([':user'=>$userId]);
    $row=$q->fetch();
    return $row?:null;
}

function citizenEnsureStakeholder(PDO $pdo,int $userId): array
{
    $existing=citizenStakeholder($pdo,$userId);
    if($existing)return $existing;

    $q=$pdo->prepare(
        'SELECT u.full_name,u.email,u.phone,cp.address
         FROM users u
         LEFT JOIN citizen_portal_profiles cp ON cp.user_id=u.id
         WHERE u.id=:user
           AND u.status="Active"
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $q->execute([':user'=>$userId]);
    $user=$q->fetch();
    if(!$user)throw new RuntimeException('Citizen account not found.');

    $categoryId=null;
    $cq=$pdo->query(
        "SELECT id
         FROM stakeholder_categories
         WHERE name IN ('Resident / Citizen','Citizen','Resident')
         ORDER BY FIELD(name,'Resident / Citizen','Citizen','Resident')
         LIMIT 1"
    );
    $value=$cq->fetchColumn();
    if($value!==false)$categoryId=(int)$value;

    $pdo->prepare(
        'INSERT INTO stakeholders
         (user_id,full_name,email,phone,organization,category_id,status,
          address,sector,created_at,updated_at)
         VALUES(:user,:name,:email,:phone,NULL,:category,"Pending",
          :address,"Citizen",NOW(),NOW())'
    )->execute([
        ':user'=>$userId,
        ':name'=>$user['full_name'],
        ':email'=>$user['email'],
        ':phone'=>$user['phone']?:null,
        ':category'=>$categoryId,
        ':address'=>$user['address']?:null,
    ]);

    $id=(int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO stakeholder_history
         (stakeholder_id,action,details,changed_by,created_at)
         VALUES(:stakeholder,"Citizen Portal Profile Created",
          "Stakeholder profile created automatically from the citizen account during public hearing registration.",
          :user,NOW())'
    )->execute([
        ':stakeholder'=>$id,
        ':user'=>$userId,
    ]);

    portalLog($userId,'Citizen Stakeholder Profile Created','PHCMS stakeholder #'.$id.' created from citizen account.');

    return citizenStakeholder($pdo,$userId) ?? throw new RuntimeException('Unable to create stakeholder profile.');
}

function citizenRegistrationCode(): string
{
    return 'REG-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(4)));
}

function citizenCefUuidV4(): string
{
    $data=random_bytes(16);
    $data[6]=chr((ord($data[6])&0x0f)|0x40);
    $data[8]=chr((ord($data[8])&0x3f)|0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));
}

function citizenCefReference(PDO $pdo): string
{
    $year=(int)date('Y');
    $key='submission_CEF';

    $pdo->prepare(
        'INSERT IGNORE INTO cepfms_sequences
         (sequence_key,sequence_year,next_value,updated_at)
         VALUES(:sequence_key,:sequence_year,1,NOW())'
    )->execute([
        ':sequence_key'=>$key,
        ':sequence_year'=>$year,
    ]);

    $q=$pdo->prepare(
        'SELECT next_value
         FROM cepfms_sequences
         WHERE sequence_key=:sequence_key
           AND sequence_year=:sequence_year
         FOR UPDATE'
    );
    $q->execute([
        ':sequence_key'=>$key,
        ':sequence_year'=>$year,
    ]);

    $next=max(1,(int)$q->fetchColumn());

    $pdo->prepare(
        'UPDATE cepfms_sequences
         SET next_value=:next_value,updated_at=NOW()
         WHERE sequence_key=:sequence_key
           AND sequence_year=:sequence_year'
    )->execute([
        ':next_value'=>$next+1,
        ':sequence_key'=>$key,
        ':sequence_year'=>$year,
    ]);

    return 'CEF-'.$year.'-'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function citizenCefHistory(
    PDO $pdo,int $submissionId,string $action,?string $oldStatus,
    ?string $newStatus,string $details,bool $publicVisible,int $userId
): void {
    $pdo->prepare(
        'INSERT INTO cef_submission_history
         (submission_id,action,previous_status,new_status,details,
          public_visible,changed_by,created_at)
         VALUES(:submission,:action,:old_status,:new_status,:details,
          :public_visible,:changed_by,NOW())'
    )->execute([
        ':submission'=>$submissionId,
        ':action'=>$action,
        ':old_status'=>$oldStatus,
        ':new_status'=>$newStatus,
        ':details'=>$details?:null,
        ':public_visible'=>$publicVisible?1:0,
        ':changed_by'=>$userId,
    ]);
}

function citizenCreateCefSubmission(PDO $pdo,int $userId,array $data): array
{
    $type=clean($data['submission_type']??'Feedback');
    if(!in_array($type,['Feedback','Proposal','Complaint'],true)){
        throw new InvalidArgumentException('Invalid submission type.');
    }

    $title=clean($data['title']??'');
    $details=trim((string)($data['details']??''));
    if($title===''||$details===''){
        throw new InvalidArgumentException('Title and details are required.');
    }

    $q=$pdo->prepare(
        'SELECT u.full_name,u.email,u.phone,
                cp.address,cp.district,cp.barangay,cp.preferred_contact
         FROM users u
         LEFT JOIN citizen_portal_profiles cp ON cp.user_id=u.id
         WHERE u.id=:user
           AND u.status="Active"
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $q->execute([':user'=>$userId]);
    $citizen=$q->fetch();
    if(!$citizen)throw new RuntimeException('Citizen account not found.');

    $category=(int)($data['category_id']??0)?:null;
    if($category){
        $cq=$pdo->prepare(
            'SELECT category_type
             FROM cef_categories
             WHERE id=:id
               AND is_active=1
             LIMIT 1'
        );
        $cq->execute([':id'=>$category]);
        $categoryType=$cq->fetchColumn();
        if($categoryType===false){
            throw new InvalidArgumentException('Selected category is unavailable.');
        }
        if($categoryType!=='All'&&$categoryType!==$type){
            throw new InvalidArgumentException('Selected category does not apply to this submission type.');
        }
    }

    $priority=clean($data['priority_level']??'Normal');
    if(!in_array($priority,['Low','Normal','High','Urgent'],true))$priority='Normal';

    $publicId=citizenCefUuidV4();
    $reference=citizenCefReference($pdo);
    $trackingToken=strtoupper(bin2hex(random_bytes(12)));

    $pdo->prepare(
        'INSERT INTO cef_submissions
         (public_id,reference_number,submission_type,citizen_user_id,
          citizen_name,citizen_email,citizen_phone,contact_preference,
          anonymous_flag,privacy_consent,consent_at,title,summary,details,
          category_id,location_text,district,barangay,priority_level,
          source_channel,status,moderation_status,tracking_token_hash,
          acknowledged_at,created_by,created_at,updated_at)
         VALUES(:public_id,:reference,:type,:citizen_user,
          :citizen_name,:citizen_email,:citizen_phone,:contact_preference,
          0,1,NOW(),:title,:summary,:details,
          :category,:location_text,:district,:barangay,:priority,
          "Citizen Portal","Submitted","Pending",:tracking_hash,
          NOW(),:created_by,NOW(),NOW())'
    )->execute([
        ':public_id'=>$publicId,
        ':reference'=>$reference,
        ':type'=>$type,
        ':citizen_user'=>$userId,
        ':citizen_name'=>$citizen['full_name'],
        ':citizen_email'=>$citizen['email'],
        ':citizen_phone'=>$citizen['phone']?:null,
        ':contact_preference'=>$citizen['preferred_contact']?:'Portal',
        ':title'=>$title,
        ':summary'=>trim((string)($data['summary']??''))?:null,
        ':details'=>$details,
        ':category'=>$category,
        ':location_text'=>clean($data['location_text']??'')?:($citizen['address']?:null),
        ':district'=>clean($data['district']??'')?:($citizen['district']?:null),
        ':barangay'=>clean($data['barangay']??'')?:($citizen['barangay']?:null),
        ':priority'=>$priority,
        ':tracking_hash'=>hash('sha256',$trackingToken),
        ':created_by'=>$userId,
    ]);

    $id=(int)$pdo->lastInsertId();

    if($type==='Feedback'){
        $rating=(int)($data['citizen_rating']??0);
        $pdo->prepare(
            'INSERT INTO cef_feedback_submissions
             (submission_id,feedback_kind,service_area,desired_outcome,
              citizen_rating,created_at,updated_at)
             VALUES(:submission,:kind,:service_area,:desired_outcome,
              :rating,NOW(),NOW())'
        )->execute([
            ':submission'=>$id,
            ':kind'=>clean($data['feedback_kind']??'General Feedback')?:'General Feedback',
            ':service_area'=>clean($data['service_area']??'')?:null,
            ':desired_outcome'=>trim((string)($data['desired_outcome']??''))?:null,
            ':rating'=>$rating>=1&&$rating<=5?$rating:null,
        ]);
    }elseif($type==='Proposal'){
        $pdo->prepare(
            'INSERT INTO cef_proposals
             (submission_id,problem_statement,proposed_solution,
              expected_public_benefit,estimated_scope,feasibility_status,
              created_at,updated_at)
             VALUES(:submission,:problem_statement,:proposed_solution,
              :expected_public_benefit,:estimated_scope,"Not Reviewed",
              NOW(),NOW())'
        )->execute([
            ':submission'=>$id,
            ':problem_statement'=>trim((string)($data['problem_statement']??''))?:null,
            ':proposed_solution'=>trim((string)($data['proposed_solution']??''))?:null,
            ':expected_public_benefit'=>trim((string)($data['expected_public_benefit']??''))?:null,
            ':estimated_scope'=>clean($data['estimated_scope']??'')?:null,
        ]);
    }else{
        $urgency=clean($data['urgency_level']??$priority);
        if(!in_array($urgency,['Low','Normal','High','Urgent'],true))$urgency='Normal';

        $ack=48;$response=96;$resolution=240;
        $sq=$pdo->prepare(
            'SELECT acknowledgement_hours,response_hours,resolution_hours
             FROM cef_service_levels
             WHERE submission_type="Complaint"
               AND urgency_level=:urgency
               AND is_active=1
             LIMIT 1'
        );
        $sq->execute([':urgency'=>$urgency]);
        $sla=$sq->fetch();
        if($sla){
            $ack=(int)$sla['acknowledgement_hours'];
            $response=(int)$sla['response_hours'];
            $resolution=(int)$sla['resolution_hours'];
        }

        $now=time();
        $incident=clean($data['incident_datetime']??'');
        $pdo->prepare(
            'INSERT INTO cef_complaints
             (submission_id,affected_service,incident_datetime,urgency_level,
              acknowledgement_target_at,response_target_at,resolution_target_at,
              citizen_confirmation,created_at,updated_at)
             VALUES(:submission,:affected_service,:incident_datetime,:urgency,
              :ack_target,:response_target,:resolution_target,
              "Not Requested",NOW(),NOW())'
        )->execute([
            ':submission'=>$id,
            ':affected_service'=>clean($data['affected_service']??'')?:null,
            ':incident_datetime'=>$incident!==''&&strtotime($incident)!==false
                ? date('Y-m-d H:i:s',strtotime($incident))
                : null,
            ':urgency'=>$urgency,
            ':ack_target'=>date('Y-m-d H:i:s',$now+($ack*3600)),
            ':response_target'=>date('Y-m-d H:i:s',$now+($response*3600)),
            ':resolution_target'=>date('Y-m-d H:i:s',$now+($resolution*3600)),
        ]);
    }

    citizenCefHistory(
        $pdo,$id,'Citizen Submission',null,'Submitted',
        $type.' received through the Legislative Citizen Portal.',
        true,$userId
    );

    portalLog($userId,'Citizen Engagement Submission',$reference.' · '.$type.' submitted.');

    return [
        'id'=>$id,
        'reference_number'=>$reference,
        'tracking_token'=>$trackingToken,
    ];
}

function citizenTicketChatClosed(string $status): bool
{
    return in_array($status,['Resolved','Closed','Withdrawn','Rejected'],true);
}

function citizenCefUploadRoot(): string
{
    $base=dirname(APP_ROOT).DIRECTORY_SEPARATOR;
    foreach(['cepfms','CEPFMS'] as $folder){
        $candidate=$base.$folder.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR;
        if(is_dir($candidate))return $candidate;
    }

    return $base.'cepfms'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR;
}

function citizenSafeCefFilePath(string $relativePath): ?string
{
    $root=citizenCefUploadRoot();
    $rootReal=realpath($root);
    if($rootReal===false)return null;

    $relativePath=str_replace(['\\','/'],DIRECTORY_SEPARATOR,$relativePath);
    $candidate=realpath($rootReal.DIRECTORY_SEPARATOR.ltrim($relativePath,DIRECTORY_SEPARATOR));
    if($candidate===false||!is_file($candidate))return null;

    $rootPrefix=rtrim($rootReal,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if(strncmp($candidate,$rootPrefix,strlen($rootPrefix))!==0)return null;

    return $candidate;
}

function citizenHumanFileSize(?int $bytes): string
{
    $bytes=max(0,(int)$bytes);
    if($bytes<1024)return $bytes.' B';
    if($bytes<1024*1024)return number_format($bytes/1024,1).' KB';
    return number_format($bytes/(1024*1024),1).' MB';
}

