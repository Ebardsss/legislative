USE `legislative_management_db`;

SELECT 'STEP 5 - PHCMS PUBLIC HEARINGS' AS section_name;

SELECT COUNT(*) public_hearings
FROM hearings
WHERE visibility='Public'
  AND status IN ('Upcoming','Ongoing','Completed');

SELECT COUNT(*) citizen_linked_stakeholders
FROM stakeholders
WHERE user_id IS NOT NULL;

SELECT 'STEP 6 - CEPFMS CITIZEN ACCOUNT LINKING' AS section_name;

SELECT
    COUNT(*) citizen_linked_cefpms_submissions
FROM cef_submissions
WHERE citizen_user_id IS NOT NULL
  AND deleted_at IS NULL;

SELECT
    COUNT(*) delivered_responses
FROM cef_responses
WHERE status='Delivered';

SELECT 'STEP 7 - PUBLIC SAFETY CHECKS - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT 'Public hearing query contains Internal hearing' check_name,COUNT(*) problem_rows
FROM hearings
WHERE status IN ('Upcoming','Ongoing','Completed')
  AND visibility<>'Public'

UNION ALL
SELECT 'Citizen registration linked to missing stakeholder',COUNT(*)
FROM registrations r
LEFT JOIN stakeholders s ON s.id=r.stakeholder_id
WHERE s.id IS NULL

UNION ALL
SELECT 'Citizen-linked stakeholder points to missing user',COUNT(*)
FROM stakeholders s
LEFT JOIN users u ON u.id=s.user_id
WHERE s.user_id IS NOT NULL
  AND u.id IS NULL

UNION ALL
SELECT 'CEPFMS citizen submission points to missing user',COUNT(*)
FROM cef_submissions s
LEFT JOIN users u ON u.id=s.citizen_user_id
WHERE s.citizen_user_id IS NOT NULL
  AND u.id IS NULL

UNION ALL
SELECT 'Delivered response references missing submission',COUNT(*)
FROM cef_responses r
LEFT JOIN cef_submissions s ON s.id=r.submission_id
WHERE r.status='Delivered'
  AND s.id IS NULL

UNION ALL
SELECT 'Public CEPFMS follow-up references missing submission',COUNT(*)
FROM cef_followups f
LEFT JOIN cef_submissions s ON s.id=f.submission_id
WHERE f.public_visible=1
  AND s.id IS NULL;

SELECT 'NOTE' AS section_name;
SELECT
'Citizen Portal hearing registration creates/uses a stakeholder record linked by stakeholders.user_id. New stakeholder profiles begin as Pending so PHCMS staff can verify them through the internal subsystem.' AS registration_rule;
