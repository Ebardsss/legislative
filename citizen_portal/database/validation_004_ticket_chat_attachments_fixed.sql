USE `legislative_management_db`;

-- ============================================================
-- CITIZEN PORTAL VALIDATION 004
-- Run only AFTER CEPFMS migration 006 / repair SQL.
-- ============================================================

SELECT 'CITIZEN PORTAL - TICKET CHAT / ATTACHMENTS' AS section_name;

SELECT
    (SELECT COUNT(*)
     FROM `information_schema`.`columns`
     WHERE `table_schema` = DATABASE()
       AND `table_name` = 'cef_response_documents'
       AND `column_name` = 'visibility')
        AS response_visibility_ready,

    (SELECT COUNT(*)
     FROM `cef_submission_documents`
     WHERE `visibility` = 'Public')
        AS public_ticket_attachments,

    (SELECT COUNT(*)
     FROM `cef_response_documents` d
     JOIN `cef_responses` r
       ON r.`id` = d.`response_id`
     WHERE d.`visibility` = 'Public'
       AND r.`status` = 'Delivered')
        AS public_response_attachments,

    (SELECT COUNT(*)
     FROM `cef_followups`
     WHERE `public_visible` = 1)
        AS shared_ticket_messages;

SELECT 'INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT
    'Citizen-visible submission attachment missing ticket' AS check_name,
    COUNT(*) AS problem_rows
FROM `cef_submission_documents` d
LEFT JOIN `cef_submissions` s
  ON s.`id` = d.`submission_id`
WHERE d.`visibility` = 'Public'
  AND s.`id` IS NULL

UNION ALL

SELECT
    'Citizen-visible response attachment missing response',
    COUNT(*)
FROM `cef_response_documents` d
LEFT JOIN `cef_responses` r
  ON r.`id` = d.`response_id`
WHERE d.`visibility` = 'Public'
  AND r.`id` IS NULL

UNION ALL

SELECT
    'Public ticket message references missing ticket',
    COUNT(*)
FROM `cef_followups` f
LEFT JOIN `cef_submissions` s
  ON s.`id` = f.`submission_id`
WHERE f.`public_visible` = 1
  AND s.`id` IS NULL;
