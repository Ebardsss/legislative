<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/public_data.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}
requireCsrf();

$pdo = db();
$userId = currentUserId();
$user = currentUser();

$surveyId = (int)($_POST['survey_id'] ?? 0);
$answers = $_POST['q'] ?? [];

$stmt = $pdo->prepare('SELECT * FROM surveys WHERE id = :id');
$stmt->execute([':id' => $surveyId]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$survey) {
    jsonResponse(false, 'Survey not found.');
}
if ($survey['status'] !== 'Active') {
    jsonResponse(false, 'This survey is no longer accepting responses.');
}
if (!empty($survey['opens_at']) && time() < strtotime($survey['opens_at'])) {
    jsonResponse(false, 'This survey has not opened yet.');
}
if (!empty($survey['closes_at']) && time() > strtotime($survey['closes_at'])) {
    jsonResponse(false, 'This survey is already closed.');
}

// Check if user already submitted
$checkStmt = $pdo->prepare('SELECT COUNT(*) FROM survey_submissions WHERE survey_id = :sid AND user_id = :user');
$checkStmt->execute([':sid' => $surveyId, ':user' => $userId]);
if ((int)$checkStmt->fetchColumn() > 0) {
    jsonResponse(false, 'You have already submitted your response for this consultation survey.');
}

// Fetch questions
$qStmt = $pdo->prepare('SELECT q.* FROM survey_questions q WHERE q.survey_id = :id ORDER BY q.sequence_number, q.id');
$qStmt->execute([':id' => $surveyId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// Validate mandatory questions
$errors = [];
foreach ($questions as $q) {
    $value = $answers[$q['id']] ?? null;
    if ((int)$q['is_required'] === 1) {
        $empty = is_array($value)
            ? count(array_filter($value, fn($v) => trim((string)$v) !== '')) === 0
            : trim((string)($value ?? '')) === '';
        if ($empty) {
            $errors[] = 'Please provide an answer for: "' . $q['question_text'] . '"';
        }
    }
}
if ($errors) {
    jsonResponse(false, implode(' ', $errors));
}

// Link stakeholder if user email matches stakeholders directory
$stakeholderId = null;
$userEmail = strtolower(trim((string)($user['email'] ?? '')));
if ($userEmail !== '') {
    $sStmt = $pdo->prepare('SELECT id FROM stakeholders WHERE LOWER(email) = LOWER(:email) LIMIT 1');
    $sStmt->execute([':email' => $userEmail]);
    if ($row = $sStmt->fetch(PDO::FETCH_ASSOC)) {
        $stakeholderId = (int)$row['id'];
    }
}

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO survey_submissions
         (survey_id, stakeholder_id, user_id, respondent_name, respondent_email, submitted_at)
         VALUES (:survey, :stakeholder, :user, :name, :email, NOW())'
    )->execute([
        ':survey' => $surveyId,
        ':stakeholder' => $stakeholderId,
        ':user' => $userId,
        ':name' => $user['full_name'] ?? 'Citizen User',
        ':email' => $userEmail ?: null
    ]);
    $submissionId = (int)$pdo->lastInsertId();

    foreach ($questions as $q) {
        $value = $answers[$q['id']] ?? null;
        if ($value === null || $value === '' || (is_array($value) && !$value)) {
            continue;
        }

        if (in_array($q['question_type'], ['Single Choice', 'Multiple Choice'], true)) {
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $optionId) {
                $optionId = (int)$optionId;
                if ($optionId <= 0) continue;

                $valid = $pdo->prepare('SELECT COUNT(*) FROM survey_question_options WHERE id = :oid AND question_id = :qid');
                $valid->execute([':oid' => $optionId, ':qid' => $q['id']]);
                if ((int)$valid->fetchColumn() === 0) continue;

                $pdo->prepare(
                    'INSERT INTO survey_answers
                     (submission_id, question_id, option_id, answer_text, numeric_value)
                     VALUES (:sid, :qid, :oid, NULL, NULL)'
                )->execute([
                    ':sid' => $submissionId,
                    ':qid' => $q['id'],
                    ':oid' => $optionId
                ]);
            }
        } elseif (in_array($q['question_type'], ['Rating', 'Number'], true)) {
            $pdo->prepare(
                'INSERT INTO survey_answers
                 (submission_id, question_id, option_id, answer_text, numeric_value)
                 VALUES (:sid, :qid, NULL, NULL, :num)'
            )->execute([
                ':sid' => $submissionId,
                ':qid' => $q['id'],
                ':num' => is_numeric($value) ? $value : null
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO survey_answers
                 (submission_id, question_id, option_id, answer_text, numeric_value)
                 VALUES (:sid, :qid, NULL, :text, NULL)'
            )->execute([
                ':sid' => $submissionId,
                ':qid' => $q['id'],
                ':text' => trim((string)$value)
            ]);
        }
    }

    $pdo->commit();

    jsonResponse(true, 'Maraming salamat! Your consultation survey response has been successfully recorded.', [
        'submission_id' => $submissionId
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Citizen portal survey submit] ' . $e->getMessage());
    jsonResponse(false, 'A system error occurred while submitting your survey response. Please try again.');
}
