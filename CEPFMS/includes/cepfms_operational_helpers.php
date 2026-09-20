<?php
declare(strict_types=1);

require_once __DIR__.'/auth.php';
require_once __DIR__.'/cepfms_helpers.php';

function cefManagementRole(): bool
{
    if(cepfmsFinalRbacInstalled()){
        foreach([
            'cepfms.feedback.manage','cepfms.proposals.manage',
            'cepfms.complaints.manage','cepfms.moderation.manage',
            'cepfms.responses.manage','cepfms.notifications.manage',
            'cepfms.analytics.manage'
        ] as $permission){
            if(cefHasPermission($permission))return true;
        }
        return false;
    }

    return in_array(
        normalizeRole(currentRole()),
        [normalizeRole(ROLE_ADMIN),normalizeRole(ROLE_STAFF)],
        true
    );
}

function cefSubmissionTypes(): array
{
    return ['Feedback','Proposal','Complaint'];
}

function cefSubmissionStatuses(): array
{
    return [
        'Submitted','Under Moderation','Validated','Assigned',
        'In Progress','Awaiting Citizen','Responded','Resolved',
        'Closed','Rejected','Duplicate','Withdrawn'
    ];
}


function cefTicketChatClosed(string $status): bool
{
    return in_array($status,['Resolved','Closed','Withdrawn','Rejected'],true);
}

function cefTicketManagePermission(string $submissionType): string
{
    return match($submissionType){
        'Proposal'=>'cepfms.proposals.manage',
        'Complaint'=>'cepfms.complaints.manage',
        default=>'cepfms.feedback.manage',
    };
}

function cefDocumentVisibility(string $visibility): string
{
    return $visibility==='Public'?'Public':'Internal';
}

function cefEvidenceUrl(?string $fileName): string
{
    $fileName = trim((string)$fileName);
    if ($fileName === '') {
        return '';
    }
    if (str_starts_with($fileName, 'http://') || str_starts_with($fileName, 'https://')) {
        return $fileName;
    }

    $cleanName = ltrim(str_replace(['\\', '/'], '/', $fileName), '/');
    $cepfmsFile = UPLOAD_DIR . $cleanName;

    // Check if exists in canonical CEPFMS uploads
    if (file_exists($cepfmsFile)) {
        return rtrim(UPLOAD_URL, '/') . '/' . $cleanName;
    }

    // Check if misplaced in citizen_portal/CEPFMS/assets/uploads/
    $misplacedRoot = dirname(APP_ROOT) . DIRECTORY_SEPARATOR . 'citizen_portal' . DIRECTORY_SEPARATOR . 'CEPFMS' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $misplacedFile = $misplacedRoot . $cleanName;
    if (file_exists($misplacedFile)) {
        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0777, true);
        }
        @copy($misplacedFile, $cepfmsFile);
        return rtrim(UPLOAD_URL, '/') . '/' . $cleanName;
    }

    // Default fallback
    return rtrim(UPLOAD_URL, '/') . '/' . $cleanName;
}

function cefModerationStatuses(): array
{
    return ['Pending','Under Review','Validated','Needs Clarification','Rejected','Duplicate'];
}

function cefPriorityLevels(): array
{
    return ['Low','Normal','High','Urgent'];
}

function cefSubmissionTypeLabel(string $type): string
{
    return match($type){
        'Proposal'=>'Proposal / Suggestion',
        'Complaint'=>'Complaint / Issue',
        default=>'Public Feedback',
    };
}

function cefStatusClass(string $status): string
{
    return match($status){
        'Validated','Responded','Resolved','Closed'=>'good',
        'Rejected','Duplicate','Withdrawn'=>'bad',
        'Under Moderation','Assigned','In Progress','Awaiting Citizen'=>'warn',
        default=>'',
    };
}

function cefPublicStatus(string $status): string
{
    return match($status){
        'Submitted'=>'Received',
        'Under Moderation'=>'Under Review',
        'Validated'=>'Validated',
        'Assigned'=>'Assigned to Responsible Office',
        'In Progress'=>'Action in Progress',
        'Awaiting Citizen'=>'Waiting for Citizen Information',
        'Responded'=>'Official Response Available',
        'Resolved'=>'Resolved',
        'Closed'=>'Closed',
        'Rejected'=>'Not Accepted',
        'Duplicate'=>'Merged / Duplicate',
        'Withdrawn'=>'Withdrawn',
        default=>$status,
    };
}

function cefRandomToken(int $bytes=16): string
{
    return strtoupper(bin2hex(random_bytes($bytes)));
}

function cefGenerateReference(PDO $pdo,string $prefix='CEF',?string $dateValue=null): string
{
    $year=$dateValue&&strtotime($dateValue)!==false
        ? (int)date('Y',strtotime($dateValue))
        : (int)date('Y');

    if(!tableExists('cepfms_sequences')){
        return cepfmsGenerateReference(
            $pdo,
            'cef_submissions',
            'reference_number',
            $prefix,
            $dateValue
        );
    }

    $key='submission_'.$prefix;

    $pdo->prepare(
        'INSERT IGNORE INTO cepfms_sequences
         (sequence_key,sequence_year,next_value,updated_at)
         VALUES(:key,:year,1,NOW())'
    )->execute([':key'=>$key,':year'=>$year]);

    $q=$pdo->prepare(
        'SELECT next_value
         FROM cepfms_sequences
         WHERE sequence_key=:key
           AND sequence_year=:year
         FOR UPDATE'
    );
    $q->execute([':key'=>$key,':year'=>$year]);
    $next=(int)$q->fetchColumn();
    if($next<1)$next=1;

    $pdo->prepare(
        'UPDATE cepfms_sequences
         SET next_value=:next_value,updated_at=NOW()
         WHERE sequence_key=:key
           AND sequence_year=:year'
    )->execute([
        ':next_value'=>$next+1,
        ':key'=>$key,
        ':year'=>$year
    ]);

    return $prefix.'-'.$year.'-'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function cefTokenHash(string $token): string
{
    return hash('sha256',strtoupper(trim($token)));
}

function cefUuidV4(): string
{
    $data=random_bytes(16);
    $data[6]=chr((ord($data[6])&0x0f)|0x40);
    $data[8]=chr((ord($data[8])&0x3f)|0x80);
    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split(bin2hex($data),4)
    );
}

function cefSubmissionHistory(
    PDO $pdo,int $submissionId,string $action,?string $oldStatus,
    ?string $newStatus,string $details='',bool $publicVisible=false,?int $userId=null
): void {
    $pdo->prepare(
        'INSERT INTO cef_submission_history
         (submission_id,action,previous_status,new_status,details,public_visible,changed_by,created_at)
         VALUES(:submission,:action,:old,:new,:details,:public,:user,NOW())'
    )->execute([
        ':submission'=>$submissionId,
        ':action'=>$action,
        ':old'=>$oldStatus,
        ':new'=>$newStatus,
        ':details'=>$details?:null,
        ':public'=>$publicVisible?1:0,
        ':user'=>$userId??(currentUserId()?:null),
    ]);
}

function cefSubmissionRow(PDO $pdo,int $id): ?array
{
    $q=$pdo->prepare(
        "SELECT s.*,c.name category_name,
                u.full_name citizen_account_name,
                creator.full_name creator_name
         FROM cef_submissions s
         LEFT JOIN cef_categories c ON c.id=s.category_id
         LEFT JOIN users u ON u.id=s.citizen_user_id
         LEFT JOIN users creator ON creator.id=s.created_by
         WHERE s.id=:id AND s.deleted_at IS NULL
         LIMIT 1"
    );
    $q->execute([':id'=>$id]);
    $row=$q->fetch();
    return $row?:null;
}

function cefCreateSubmission(PDO $pdo,array $data): array
{
    $type=clean($data['submission_type']??'Feedback');
    if(!in_array($type,cefSubmissionTypes(),true)){
        throw new InvalidArgumentException('Invalid citizen submission type.');
    }

    $title=clean($data['title']??'');
    $details=trim((string)($data['details']??''));
    if($title===''||$details===''){
        throw new InvalidArgumentException('Title and details are required.');
    }

    $anonymous=!empty($data['anonymous_flag']);
    $consent=!empty($data['privacy_consent']);
    if(!$consent){
        throw new InvalidArgumentException('Privacy consent is required before submitting.');
    }

    $citizenName=$anonymous?null:clean($data['citizen_name']??'');
    $citizenEmail=$anonymous?null:clean($data['citizen_email']??'');
    $citizenPhone=$anonymous?null:clean($data['citizen_phone']??'');

    if(!$anonymous&&$citizenEmail!==''&&!filter_var($citizenEmail,FILTER_VALIDATE_EMAIL)){
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    $category=(int)($data['category_id']??0)?:null;
    if($category){
        $cq=$pdo->prepare(
            'SELECT category_type
             FROM cef_categories
             WHERE id=:id AND is_active=1
             LIMIT 1'
        );
        $cq->execute([':id'=>$category]);
        $categoryType=$cq->fetchColumn();
        if($categoryType===false){
            throw new InvalidArgumentException('Selected citizen-engagement category is unavailable.');
        }
        if($categoryType!=='All'&&$categoryType!==$type){
            throw new InvalidArgumentException('Selected category does not apply to this submission type.');
        }
    }

    $priority=clean($data['priority_level']??'Normal');
    if(!in_array($priority,cefPriorityLevels(),true))$priority='Normal';

    $publicId=cefUuidV4();
    $trackingToken=cefRandomToken(12);
    $reference=cefGenerateReference($pdo,'CEF');

    $pdo->prepare(
        'INSERT INTO cef_submissions
         (public_id,reference_number,submission_type,citizen_user_id,citizen_name,citizen_email,
          citizen_phone,contact_preference,anonymous_flag,privacy_consent,consent_at,
          title,summary,details,category_id,location_text,district,barangay,priority_level,
          source_channel,status,moderation_status,tracking_token_hash,acknowledged_at,
          created_by,created_at,updated_at)
         VALUES(:public_id,:reference,:type,:citizen_user,:name,:email,:phone,:contact,
          :anonymous,1,NOW(),:title,:summary,:details,:category,:location,:district,:barangay,
          :priority,:channel,"Submitted","Pending",:token,NOW(),:created,NOW(),NOW())'
    )->execute([
        ':public_id'=>$publicId,
        ':reference'=>$reference,
        ':type'=>$type,
        ':citizen_user'=>(int)($data['citizen_user_id']??0)?:null,
        ':name'=>$citizenName?:null,
        ':email'=>$citizenEmail?:null,
        ':phone'=>$citizenPhone?:null,
        ':contact'=>clean($data['contact_preference']??'Portal')?:'Portal',
        ':anonymous'=>$anonymous?1:0,
        ':title'=>$title,
        ':summary'=>trim((string)($data['summary']??''))?:null,
        ':details'=>$details,
        ':category'=>$category,
        ':location'=>clean($data['location_text']??'')?:null,
        ':district'=>clean($data['district']??'')?:null,
        ':barangay'=>clean($data['barangay']??'')?:null,
        ':priority'=>$priority,
        ':channel'=>clean($data['source_channel']??'Web Portal')?:'Web Portal',
        ':token'=>cefTokenHash($trackingToken),
        ':created'=>(int)($data['created_by']??0)?:null,
    ]);
    $id=(int)$pdo->lastInsertId();

    if($type==='Feedback'){
        $rating=(int)($data['citizen_rating']??0);
        $pdo->prepare(
            'INSERT INTO cef_feedback_submissions
             (submission_id,feedback_kind,service_area,desired_outcome,citizen_rating,created_at,updated_at)
             VALUES(:submission,:kind,:area,:outcome,:rating,NOW(),NOW())'
        )->execute([
            ':submission'=>$id,
            ':kind'=>clean($data['feedback_kind']??'General Feedback')?:'General Feedback',
            ':area'=>clean($data['service_area']??'')?:null,
            ':outcome'=>trim((string)($data['desired_outcome']??''))?:null,
            ':rating'=>$rating>=1&&$rating<=5?$rating:null,
        ]);
    }elseif($type==='Proposal'){
        $pdo->prepare(
            'INSERT INTO cef_proposals
             (submission_id,problem_statement,proposed_solution,expected_public_benefit,
              estimated_scope,feasibility_status,created_at,updated_at)
             VALUES(:submission,:problem,:solution,:benefit,:scope,"Not Reviewed",NOW(),NOW())'
        )->execute([
            ':submission'=>$id,
            ':problem'=>trim((string)($data['problem_statement']??''))?:null,
            ':solution'=>trim((string)($data['proposed_solution']??''))?:null,
            ':benefit'=>trim((string)($data['expected_public_benefit']??''))?:null,
            ':scope'=>clean($data['estimated_scope']??'')?:null,
        ]);
    }else{
        $urgency=clean($data['urgency_level']??$priority);
        if(!in_array($urgency,cefPriorityLevels(),true))$urgency='Normal';

        $ack=48;$response=96;$resolution=240;
        if(tableExists('cef_service_levels')){
            $q=$pdo->prepare(
                'SELECT acknowledgement_hours,response_hours,resolution_hours
                 FROM cef_service_levels
                 WHERE submission_type="Complaint"
                   AND urgency_level=:urgency
                   AND is_active=1
                 LIMIT 1'
            );
            $q->execute([':urgency'=>$urgency]);
            $sla=$q->fetch();
            if($sla){
                $ack=(int)$sla['acknowledgement_hours'];
                $response=(int)$sla['response_hours'];
                $resolution=(int)$sla['resolution_hours'];
            }
        }

        $now=time();
        $ackAt=date('Y-m-d H:i:s',$now+($ack*3600));
        $responseAt=date('Y-m-d H:i:s',$now+($response*3600));
        $resolutionAt=date('Y-m-d H:i:s',$now+($resolution*3600));

        $pdo->prepare(
            'INSERT INTO cef_complaints
             (submission_id,affected_service,incident_datetime,urgency_level,
              acknowledgement_target_at,response_target_at,resolution_target_at,
              citizen_confirmation,created_at,updated_at)
             VALUES(:submission,:service,:incident,:urgency,
              :ack_at,:response_at,:resolution_at,
              "Not Requested",NOW(),NOW())'
        )->execute([
            ':submission'=>$id,
            ':service'=>clean($data['affected_service']??'')?:null,
            ':incident'=>($incident=clean($data['incident_datetime']??''))!==''&&strtotime($incident)!==false
                ? date('Y-m-d H:i:s',strtotime($incident))
                : null,
            ':urgency'=>$urgency,
            ':ack_at'=>$ackAt,
            ':response_at'=>$responseAt,
            ':resolution_at'=>$resolutionAt,
        ]);
    }

    cefSubmissionHistory(
        $pdo,$id,'Citizen Submission',null,'Submitted',
        cefSubmissionTypeLabel($type).' received and assigned reference '.$reference.'.',
        true,(int)($data['created_by']??0)?:null
    );

    return [
        'id'=>$id,
        'reference_number'=>$reference,
        'tracking_token'=>$trackingToken,
        'public_id'=>$publicId,
    ];
}

function cefPublicTrack(PDO $pdo,string $reference,string $token): ?array
{
    $q=$pdo->prepare(
        "SELECT s.id,s.reference_number,s.submission_type,s.title,s.status,
                s.moderation_status,s.priority_level,s.created_at,s.updated_at,
                c.name category_name
         FROM cef_submissions s
         LEFT JOIN cef_categories c ON c.id=s.category_id
         WHERE UPPER(s.reference_number)=UPPER(:reference)
           AND s.tracking_token_hash=:token
           AND s.deleted_at IS NULL
         LIMIT 1"
    );
    $q->execute([
        ':reference'=>trim($reference),
        ':token'=>cefTokenHash($token),
    ]);
    $submission=$q->fetch();
    if(!$submission)return null;

    $h=$pdo->prepare(
        "SELECT action,new_status,details,created_at
         FROM cef_submission_history
         WHERE submission_id=:submission
           AND public_visible=1
         ORDER BY created_at,id"
    );
    $h->execute([':submission'=>$submission['id']]);

    $r=$pdo->prepare(
        "SELECT response_reference,subject,body,status,delivery_channel,delivered_at,approved_at
         FROM cef_responses
         WHERE submission_id=:submission
           AND status='Delivered'
         ORDER BY COALESCE(delivered_at,approved_at,updated_at) DESC,id DESC
         LIMIT 1"
    );
    $r->execute([':submission'=>$submission['id']]);

    return [
        'submission'=>$submission,
        'history'=>$h->fetchAll(),
        'response'=>$r->fetch()?:null,
    ];
}

function cefUploadSubmissionDocument(PDO $pdo,int $submissionId,array $file,string $type='Supporting Evidence',string $visibility='Internal',?int $userId=null): int
{
    $visibility=cefDocumentVisibility($visibility);
    $up=handleUpload($file,'cepfms/submissions');
    if(!$up['success'])throw new RuntimeException($up['message']);

    $mime=null;
    if(function_exists('mime_content_type')){
        $full=UPLOAD_DIR.$up['file_path'];
        if(is_file($full))$mime=@mime_content_type($full)?:null;
    }

    $pdo->prepare(
        'INSERT INTO cef_submission_documents
         (submission_id,file_name,stored_name,file_path,mime_type,file_size,document_type,
          visibility,uploaded_by,uploaded_at)
         VALUES(:submission,:name,:stored,:path,:mime,:size,:type,:visibility,:user,NOW())'
    )->execute([
        ':submission'=>$submissionId,
        ':name'=>$up['file_name'],
        ':stored'=>$up['stored_name'],
        ':path'=>$up['file_path'],
        ':mime'=>$mime,
        ':size'=>$up['size'],
        ':type'=>$type,
        ':visibility'=>$visibility,
        ':user'=>$userId??(currentUserId()?:null),
    ]);

    return (int)$pdo->lastInsertId();
}

function cefCreateOrUpdatePrimaryAssignment(
    PDO $pdo,int $submissionId,?int $officeId,?int $committeeId,?int $userId,
    ?string $dueAt=null,string $notes=''
): int {
    $q=$pdo->prepare(
        'SELECT id,status FROM cef_assignments
         WHERE submission_id=:submission
           AND assignment_role="Primary"
           AND status<>"Cancelled"
         ORDER BY id DESC LIMIT 1'
    );
    $q->execute([':submission'=>$submissionId]);
    $existing=$q->fetch();

    if($existing){
        $pdo->prepare(
            'UPDATE cef_assignments
             SET office_id=:office,committee_id=:committee,assigned_user_id=:user,
                 status="Assigned",due_at=:due,notes=:notes,updated_at=NOW()
             WHERE id=:id'
        )->execute([
            ':office'=>$officeId,':committee'=>$committeeId,':user'=>$userId,
            ':due'=>$dueAt,':notes'=>$notes?:null,':id'=>$existing['id']
        ]);
        $id=(int)$existing['id'];
    }else{
        $pdo->prepare(
            'INSERT INTO cef_assignments
             (submission_id,office_id,committee_id,assigned_user_id,assignment_role,status,
              assigned_by,assigned_at,due_at,notes,updated_at)
             VALUES(:submission,:office,:committee,:user,"Primary","Assigned",
              :by,NOW(),:due,:notes,NOW())'
        )->execute([
            ':submission'=>$submissionId,':office'=>$officeId,':committee'=>$committeeId,
            ':user'=>$userId,':by'=>currentUserId()?:null,':due'=>$dueAt,
            ':notes'=>$notes?:null
        ]);
        $id=(int)$pdo->lastInsertId();
    }

    return $id;
}

function cefProposalRow(PDO $pdo,int $submissionId): ?array
{
    $q=$pdo->prepare(
        'SELECT * FROM cef_proposals WHERE submission_id=:id LIMIT 1'
    );
    $q->execute([':id'=>$submissionId]);
    return $q->fetch()?:null;
}

function cefComplaintRow(PDO $pdo,int $submissionId): ?array
{
    $q=$pdo->prepare(
        'SELECT * FROM cef_complaints WHERE submission_id=:id LIMIT 1'
    );
    $q->execute([':id'=>$submissionId]);
    return $q->fetch()?:null;
}


function cefSequenceReference(
    PDO $pdo,string $sequenceKey,string $prefix,?string $dateValue=null
): string {
    $year=$dateValue&&strtotime($dateValue)!==false
        ? (int)date('Y',strtotime($dateValue))
        : (int)date('Y');

    if(!tableExists('cepfms_sequences')){
        throw new RuntimeException('CEPFMS sequence table is not installed.');
    }

    $pdo->prepare(
        'INSERT IGNORE INTO cepfms_sequences
         (sequence_key,sequence_year,next_value,updated_at)
         VALUES(:key,:year,1,NOW())'
    )->execute([':key'=>$sequenceKey,':year'=>$year]);

    $q=$pdo->prepare(
        'SELECT next_value
         FROM cepfms_sequences
         WHERE sequence_key=:key
           AND sequence_year=:year
         FOR UPDATE'
    );
    $q->execute([':key'=>$sequenceKey,':year'=>$year]);
    $next=max(1,(int)$q->fetchColumn());

    $pdo->prepare(
        'UPDATE cepfms_sequences
         SET next_value=:next_value,updated_at=NOW()
         WHERE sequence_key=:key
           AND sequence_year=:year'
    )->execute([
        ':next_value'=>$next+1,
        ':key'=>$sequenceKey,
        ':year'=>$year
    ]);

    return $prefix.'-'.$year.'-'.str_pad((string)$next,4,'0',STR_PAD_LEFT);
}

function cefModerationChecklistDefaults(): array
{
    return [
        'completeness'=>'Required details are complete and understandable.',
        'relevance'=>'Submission is relevant to City Council / public service processing.',
        'content'=>'Content is not spam, abusive, fraudulent, or clearly non-actionable.',
        'duplicate'=>'Potential duplicate submissions have been checked.',
        'classification'=>'Submission type, category, urgency, and location are reasonably classified.',
        'contact'=>'Citizen contact / anonymity requirements are handled appropriately.',
    ];
}

function cefEnsureModerationReview(PDO $pdo,int $submissionId): array
{
    $q=$pdo->prepare(
        'SELECT *
         FROM cef_moderation_reviews
         WHERE submission_id=:submission
         ORDER BY review_round DESC,id DESC
         LIMIT 1'
    );
    $q->execute([':submission'=>$submissionId]);
    $review=$q->fetch();

    if(!$review || !in_array($review['decision'],['Pending','Needs Clarification'],true)){
        $round=1;
        $r=$pdo->prepare(
            'SELECT COALESCE(MAX(review_round),0)+1
             FROM cef_moderation_reviews
             WHERE submission_id=:submission'
        );
        $r->execute([':submission'=>$submissionId]);
        $round=max(1,(int)$r->fetchColumn());

        $pdo->prepare(
            'INSERT INTO cef_moderation_reviews
             (submission_id,review_round,reviewer_id,completeness_status,relevance_status,
              duplicate_status,identity_status,content_status,classification_status,
              decision,created_at,updated_at)
             VALUES(:submission,:round,:reviewer,"Pending","Pending","Pending",
              "Not Required","Pending","Pending","Pending",NOW(),NOW())'
        )->execute([
            ':submission'=>$submissionId,
            ':round'=>$round,
            ':reviewer'=>currentUserId()?:null
        ]);
        $id=(int)$pdo->lastInsertId();

        foreach(cefModerationChecklistDefaults() as $code=>$label){
            $pdo->prepare(
                'INSERT INTO cef_moderation_checklist
                 (moderation_review_id,item_code,item_label,status,updated_by,updated_at)
                 VALUES(:review,:code,:label,"Pending",:user,NOW())'
            )->execute([
                ':review'=>$id,':code'=>$code,':label'=>$label,
                ':user'=>currentUserId()?:null
            ]);
        }

        $q=$pdo->prepare('SELECT * FROM cef_moderation_reviews WHERE id=:id');
        $q->execute([':id'=>$id]);
        $review=$q->fetch();
    }elseif(!$review['reviewer_id']&&currentUserId()){
        $pdo->prepare(
            'UPDATE cef_moderation_reviews
             SET reviewer_id=:user,updated_at=NOW()
             WHERE id=:id'
        )->execute([':user'=>currentUserId(),':id'=>$review['id']]);
        $review['reviewer_id']=currentUserId();
    }

    return $review;
}

function cefReviewChecklist(PDO $pdo,int $reviewId): array
{
    $q=$pdo->prepare(
        'SELECT *
         FROM cef_moderation_checklist
         WHERE moderation_review_id=:review
         ORDER BY id'
    );
    $q->execute([':review'=>$reviewId]);
    return $q->fetchAll();
}

function cefAllChecklistPassed(PDO $pdo,int $reviewId): bool
{
    $q=$pdo->prepare(
        'SELECT COUNT(*)
         FROM cef_moderation_checklist
         WHERE moderation_review_id=:review
           AND status NOT IN ("Pass","Not Applicable")'
    );
    $q->execute([':review'=>$reviewId]);
    return (int)$q->fetchColumn()===0;
}

function cefResponseRow(PDO $pdo,int $responseId): ?array
{
    $q=$pdo->prepare(
        "SELECT r.*,s.reference_number submission_reference,s.submission_type,
                s.title submission_title,s.status submission_status,
                s.citizen_user_id,s.citizen_name,s.citizen_email,s.citizen_phone,
                s.contact_preference,s.anonymous_flag
         FROM cef_responses r
         JOIN cef_submissions s ON s.id=r.submission_id
         WHERE r.id=:id
         LIMIT 1"
    );
    $q->execute([':id'=>$responseId]);
    return $q->fetch()?:null;
}

function cefResponseHistory(
    PDO $pdo,int $responseId,string $action,?string $old,?string $new,string $details=''
): void {
    $pdo->prepare(
        'INSERT INTO cef_response_history
         (response_id,action,previous_status,new_status,details,changed_by,created_at)
         VALUES(:response,:action,:old,:new,:details,:user,NOW())'
    )->execute([
        ':response'=>$responseId,':action'=>$action,':old'=>$old,':new'=>$new,
        ':details'=>$details?:null,':user'=>currentUserId()?:null
    ]);
}

function cefNotificationRecipientsForSubmission(PDO $pdo,int $submissionId): array
{
    $q=$pdo->prepare(
        'SELECT citizen_user_id,citizen_name,citizen_email,citizen_phone,
                contact_preference,anonymous_flag
         FROM cef_submissions
         WHERE id=:id AND deleted_at IS NULL'
    );
    $q->execute([':id'=>$submissionId]);
    $s=$q->fetch();
    if(!$s)return [];

    $name=(int)$s['anonymous_flag']===1
        ? 'Anonymous Citizen'
        : ($s['citizen_name']?:'Citizen');

    $recipients=[[
        'user_id'=>null,
        'name'=>$name,
        'email'=>null,
        'phone'=>null,
        'channel'=>'Portal'
    ]];

    if((int)$s['anonymous_flag']===1){
        return $recipients;
    }

    if((int)$s['citizen_user_id']>0){
        $recipients[]=[
            'user_id'=>(int)$s['citizen_user_id'],
            'name'=>$name,
            'email'=>null,
            'phone'=>null,
            'channel'=>'System'
        ];
    }

    $preference=clean($s['contact_preference']??'Portal')?:'Portal';

    if($preference==='Email'&&!empty($s['citizen_email'])){
        $recipients[]=[
            'user_id'=>null,
            'name'=>$name,
            'email'=>$s['citizen_email'],
            'phone'=>null,
            'channel'=>'Email'
        ];
    }elseif($preference==='Phone'&&!empty($s['citizen_phone'])){
        $recipients[]=[
            'user_id'=>null,
            'name'=>$name,
            'email'=>null,
            'phone'=>$s['citizen_phone'],
            'channel'=>'Phone'
        ];
    }

    return $recipients;
}

function cefQueueNotification(
    PDO $pdo,int $submissionId,?int $responseId,string $type,
    string $subject,string $message,array $recipients,?string $scheduledAt=null
): int {
    if($scheduledAt!==null&&$scheduledAt!==''){
        if(strtotime($scheduledAt)===false){
            throw new InvalidArgumentException('Invalid notification schedule.');
        }
        $scheduledAt=date('Y-m-d H:i:s',strtotime($scheduledAt));
    }else{
        $scheduledAt=null;
    }

    $reference=cefSequenceReference($pdo,'notification','NTF',$scheduledAt);
    $status=$scheduledAt&&strtotime($scheduledAt)>time()?'Scheduled':'Ready';

    $pdo->prepare(
        'INSERT INTO cef_notifications
         (notification_reference,submission_id,response_id,notification_type,
          subject,message,scheduled_at,status,created_by,created_at,updated_at)
         VALUES(:reference,:submission,:response,:type,:subject,:message,
          :scheduled,:status,:user,NOW(),NOW())'
    )->execute([
        ':reference'=>$reference,':submission'=>$submissionId,':response'=>$responseId,
        ':type'=>$type,':subject'=>$subject,':message'=>$message,
        ':scheduled'=>$scheduledAt,':status'=>$status,':user'=>currentUserId()?:null
    ]);
    $id=(int)$pdo->lastInsertId();

    foreach($recipients as $r){
        $pdo->prepare(
            'INSERT INTO cef_notification_recipients
             (notification_id,user_id,recipient_name,recipient_email,recipient_phone,
              delivery_channel,delivery_status,created_at)
             VALUES(:notification,:user,:name,:email,:phone,:channel,"Pending",NOW())'
        )->execute([
            ':notification'=>$id,':user'=>(int)($r['user_id']??0)?:null,
            ':name'=>clean($r['name']??'')?:null,
            ':email'=>clean($r['email']??'')?:null,
            ':phone'=>clean($r['phone']??'')?:null,
            ':channel'=>clean($r['channel']??'Portal')?:'Portal'
        ]);
    }

    return $id;
}

function cefProcessNotification(PDO $pdo,int $notificationId): array
{
    $q=$pdo->prepare(
        "SELECT *
         FROM cef_notifications
         WHERE id=:id
           AND status IN ('Ready','Scheduled','Partially Sent')
         FOR UPDATE"
    );
    $q->execute([':id'=>$notificationId]);
    $notification=$q->fetch();
    if(!$notification)return ['processed'=>0,'pending_external'=>0,'not_due'=>false];

    if($notification['scheduled_at']&&strtotime($notification['scheduled_at'])>time()){
        return ['processed'=>0,'pending_external'=>0,'not_due'=>true];
    }

    $r=$pdo->prepare(
        "SELECT *
         FROM cef_notification_recipients
         WHERE notification_id=:id
           AND delivery_status='Pending'
         ORDER BY id"
    );
    $r->execute([':id'=>$notificationId]);
    $recipients=$r->fetchAll();

    $processed=0;$pendingExternal=0;

    foreach($recipients as $recipient){
        $a=$pdo->prepare(
            'SELECT COALESCE(MAX(attempt_number),0)+1
             FROM cef_notification_delivery_attempts
             WHERE notification_recipient_id=:recipient'
        );
        $a->execute([':recipient'=>$recipient['id']]);
        $attempt=max(1,(int)$a->fetchColumn());

        $channel=$recipient['delivery_channel'];
        $success=false;$provider='';$code='';$message='';

        if($channel==='Portal'){
            $success=true;$provider='CEPFMS Public Tracking';$code='PUBLISHED';
            $message='Citizen notification is available through secure reference/token tracking.';
        }elseif($recipient['user_id']){
            $systemId=cepfmsSystemId();
            if($systemId&&tableExists('notifications')){
                $exists=$pdo->prepare(
                    'SELECT COUNT(*)
                     FROM notifications
                     WHERE user_id=:user
                       AND system_id=:system
                       AND title=:title
                       AND message=:message'
                );
                $exists->execute([
                    ':user'=>$recipient['user_id'],':system'=>$systemId,
                    ':title'=>$notification['subject'],':message'=>$notification['message']
                ]);
                if((int)$exists->fetchColumn()===0){
                    $pdo->prepare(
                        'INSERT INTO notifications
                         (user_id,system_id,notification_type,title,message,target_url,is_read,created_at)
                         VALUES(:user,:system,:type,:title,:message,:url,0,NOW())'
                    )->execute([
                        ':user'=>$recipient['user_id'],':system'=>$systemId,
                        ':type'=>$notification['notification_type'],
                        ':title'=>$notification['subject'],':message'=>$notification['message'],
                        ':url'=>appUrl('public/index.php#track')
                    ]);
                }
                $success=true;$provider='Shared Notifications';$code='OK';
                $message='Internal shared-system notification created.';
            }
        }

        if(!$success){
            $pendingExternal++;
            $provider=$channel==='Email'?'External SMTP':($channel==='Phone'?'External SMS/Phone':'External Provider');
            $code='NOT_CONFIGURED';
            $message='External delivery provider is not configured. Record remains Pending.';
            $pdo->prepare(
                'UPDATE cef_notification_recipients
                 SET failure_reason=:reason
                 WHERE id=:id'
            )->execute([':reason'=>$message,':id'=>$recipient['id']]);
        }else{
            $pdo->prepare(
                'UPDATE cef_notification_recipients
                 SET delivery_status="Sent",sent_at=NOW(),failure_reason=NULL
                 WHERE id=:id'
            )->execute([':id'=>$recipient['id']]);
            $processed++;
        }

        $pdo->prepare(
            'INSERT INTO cef_notification_delivery_attempts
             (notification_recipient_id,delivery_channel,attempt_number,status,
              provider,response_code,response_message,attempted_at)
             VALUES(:recipient,:channel,:attempt,:status,:provider,:code,:message,NOW())'
        )->execute([
            ':recipient'=>$recipient['id'],':channel'=>$channel,':attempt'=>$attempt,
            ':status'=>$success?'Sent':'Pending',':provider'=>$provider,
            ':code'=>$code,':message'=>$message
        ]);
    }

    $pending=$pdo->prepare(
        "SELECT COUNT(*)
         FROM cef_notification_recipients
         WHERE notification_id=:id
           AND delivery_status='Pending'"
    );
    $pending->execute([':id'=>$notificationId]);
    $remaining=(int)$pending->fetchColumn();

    $pdo->prepare(
        'UPDATE cef_notifications
         SET status=:status,updated_at=NOW()
         WHERE id=:id'
    )->execute([
        ':status'=>$remaining===0?'Sent':'Partially Sent',
        ':id'=>$notificationId
    ]);

    return [
        'processed'=>$processed,
        'pending_external'=>$pendingExternal,
        'not_due'=>false
    ];
}

function cefAnalyticsSummary(PDO $pdo): array
{
    return [
        'total'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions WHERE deleted_at IS NULL")->fetchColumn(),
        'feedback'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions WHERE submission_type='Feedback' AND deleted_at IS NULL")->fetchColumn(),
        'proposals'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions WHERE submission_type='Proposal' AND deleted_at IS NULL")->fetchColumn(),
        'complaints'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions WHERE submission_type='Complaint' AND deleted_at IS NULL")->fetchColumn(),
        'pending_moderation'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions WHERE moderation_status IN ('Pending','Under Review','Needs Clarification') AND deleted_at IS NULL")->fetchColumn(),
        'open_complaints'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions WHERE submission_type='Complaint' AND status NOT IN ('Resolved','Closed','Rejected','Withdrawn') AND deleted_at IS NULL")->fetchColumn(),
        'overdue_complaints'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_submissions s JOIN cef_complaints c ON c.submission_id=s.id WHERE s.submission_type='Complaint' AND s.status NOT IN ('Resolved','Closed','Rejected','Withdrawn') AND c.resolution_target_at<NOW() AND s.deleted_at IS NULL")->fetchColumn(),
        'delivered_responses'=>(int)$pdo->query("SELECT COUNT(*) FROM cef_responses WHERE status='Delivered'")->fetchColumn(),
    ];
}

function cefMonthlyTrend(PDO $pdo,int $months=6): array
{
    $months=max(3,min(24,$months));
    $start=date('Y-m-01',strtotime('-'.($months-1).' months'));

    $q=$pdo->prepare(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') month_key,
                DATE_FORMAT(created_at,'%b %Y') label,
                SUM(submission_type='Feedback') feedback,
                SUM(submission_type='Proposal') proposals,
                SUM(submission_type='Complaint') complaints,
                COUNT(*) total
         FROM cef_submissions
         WHERE deleted_at IS NULL
           AND created_at>=:start
         GROUP BY month_key,label
         ORDER BY month_key"
    );
    $q->execute([':start'=>$start]);
    $actual=[];
    foreach($q->fetchAll() as $row)$actual[$row['month_key']]=$row;

    $out=[];
    for($i=$months-1;$i>=0;$i--){
        $ts=strtotime('-'.$i.' months');
        $key=date('Y-m',$ts);
        $out[]=$actual[$key]??[
            'month_key'=>$key,'label'=>date('M Y',$ts),
            'feedback'=>0,'proposals'=>0,'complaints'=>0,'total'=>0
        ];
    }
    return $out;
}

function cepfmsAiService(): OllamaService
{
    static $service = null;
    if ($service === null) {
        require_once __DIR__ . '/AI/OllamaService.php';
        $service = new OllamaService();
    }
    return $service;
}

