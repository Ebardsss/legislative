<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/citizen_services.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(appUrl('modules/engagement/index.php'));
requireCsrf();

$pdo=db();

try{
    $pdo->beginTransaction();

    $submissionType = clean($_POST['submission_type'] ?? 'Feedback');
    $locationText = clean($_POST['location_text'] ?? '');
    $district = clean($_POST['district'] ?? '');
    $barangay = clean($_POST['barangay'] ?? '');

    $result=citizenCreateCefSubmission($pdo,currentUserId(),[
        'submission_type'=>$submissionType,
        'category_id'=>(int)($_POST['category_id']??0),
        'priority_level'=>clean($_POST['priority_level']??'Normal'),
        'title'=>clean($_POST['title']??''),
        'summary'=>trim((string)($_POST['summary']??'')),
        'details'=>trim((string)($_POST['details']??'')),
        'location_text'=>$locationText,
        'district'=>$district,
        'barangay'=>$barangay,

        'feedback_kind'=>clean($_POST['feedback_kind']??'General Feedback'),
        'service_area'=>clean($_POST['service_area']??''),
        'desired_outcome'=>trim((string)($_POST['desired_outcome']??'')),
        'citizen_rating'=>(int)($_POST['citizen_rating']??0),
        'rating_speed'=>(int)($_POST['rating_speed']??0),
        'rating_courtesy'=>(int)($_POST['rating_courtesy']??0),
        'rating_facility'=>(int)($_POST['rating_facility']??0),
        'rating_process'=>(int)($_POST['rating_process']??0),

        'problem_statement'=>trim((string)($_POST['problem_statement']??'')),
        'proposed_solution'=>trim((string)($_POST['proposed_solution']??'')),
        'expected_public_benefit'=>trim((string)($_POST['expected_public_benefit']??'')),
        'estimated_scope'=>clean($_POST['estimated_scope']??''),

        'affected_service'=>clean($_POST['affected_service']??''),
        'incident_datetime'=>clean($_POST['incident_datetime']??''),
        'urgency_level'=>clean($_POST['urgency_level']??'Normal'),
    ]);

    $submissionId = (int)$result['id'];

    // Handle evidence image upload (especially for Complaint)
    if(isset($_FILES['evidence']) && ($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file = $_FILES['evidence'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $uploadDir = citizenCefUploadRoot();
            if(!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $storedName = 'evidence_before_' . $submissionId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $storedName;

            if(move_uploaded_file($file['tmp_name'], $targetPath)) {
                // If it's a Complaint, set before_photo_path in cef_complaints
                if($submissionType === 'Complaint') {
                    $pdo->prepare("UPDATE cef_complaints SET before_photo_path = ?, updated_at = NOW() WHERE submission_id = ?")
                        ->execute([$storedName, $submissionId]);
                }

                // Insert into cef_submission_documents
                $pdo->prepare(
                    "INSERT INTO cef_submission_documents 
                     (submission_id, file_name, stored_name, file_path, mime_type, file_size, document_type, visibility, uploaded_by, uploaded_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'Incident Evidence Photo', 'Public', ?, NOW())"
                )->execute([
                    $submissionId,
                    $file['name'],
                    $storedName,
                    $storedName,
                    $file['type'] ?: ('image/' . $ext),
                    $file['size'],
                    currentUserId()
                ]);

                citizenCefHistory($pdo, $submissionId, 'Evidence Uploaded', 'Submitted', 'Submitted', 'Citizen uploaded incident photo: ' . $file['name'], true, currentUserId());
            }
        }
    }

    $pdo->commit();

    setFlash(
        'success',
        'Your submission was received. Reference: '.$result['reference_number']
    );

    redirect(appUrl('modules/engagement/view.php?id='.$submissionId));
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    setFlash('danger',APP_DEBUG?$e->getMessage():'Unable to submit your record right now.');
    redirect(appUrl('modules/engagement/create.php'));
}
