USE `legislative_management_db`;

SET @portal_system_id := (
    SELECT id
    FROM systems
    WHERE code='public_portal'
    LIMIT 1
);

SELECT 'CITIZEN PORTAL - FINAL FOUNDATION' AS section_name;

SELECT
    (SELECT COUNT(*)
     FROM citizen_portal_schema_migrations
     WHERE migration_key='001_citizen_portal_foundation') AS migration_001,
    (SELECT COUNT(*)
     FROM citizen_portal_schema_migrations
     WHERE migration_key='002_account_security') AS migration_002,
    (SELECT COUNT(*)
     FROM systems
     WHERE code='public_portal'
       AND status='Active') AS portal_system_active,
    (SELECT COUNT(*)
     FROM permissions
     WHERE system_id=@portal_system_id
       AND code='public_portal.access') AS portal_permission_ready;

SELECT 'ACCOUNT / ACCESS INTEGRITY - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT
    'Eligible active portal access missing security record' check_name,
    COUNT(*) problem_rows
FROM user_system_access usa
JOIN users u ON u.id=usa.user_id
JOIN roles r ON r.id=u.role_id
LEFT JOIN citizen_portal_account_security cs ON cs.user_id=usa.user_id
WHERE usa.system_id=@portal_system_id
  AND usa.status='Active'
  AND u.status='Active'
  AND u.deleted_at IS NULL
  AND r.name IN ('Public User','Registered Stakeholder')
  AND cs.user_id IS NULL

UNION ALL

SELECT
    'Active portal access belongs to non-citizen role',
    COUNT(*)
FROM user_system_access usa
JOIN users u ON u.id=usa.user_id
JOIN roles r ON r.id=u.role_id
WHERE usa.system_id=@portal_system_id
  AND usa.status='Active'
  AND r.name NOT IN ('Public User','Registered Stakeholder')

UNION ALL

SELECT
    'Citizen profile references missing user',
    COUNT(*)
FROM citizen_portal_profiles cp
LEFT JOIN users u ON u.id=cp.user_id
WHERE u.id IS NULL

UNION ALL

SELECT
    'Citizen security record references missing user',
    COUNT(*)
FROM citizen_portal_account_security cs
LEFT JOIN users u ON u.id=cs.user_id
WHERE u.id IS NULL

UNION ALL

SELECT
    'Citizen-linked stakeholder references missing user',
    COUNT(*)
FROM stakeholders s
LEFT JOIN users u ON u.id=s.user_id
WHERE s.user_id IS NOT NULL
  AND u.id IS NULL

UNION ALL

SELECT
    'Citizen-linked CEPFMS submission references missing user',
    COUNT(*)
FROM cef_submissions s
LEFT JOIN users u ON u.id=s.citizen_user_id
WHERE s.citizen_user_id IS NOT NULL
  AND u.id IS NULL;

SELECT 'PUBLIC VISIBILITY CHECKS - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT
    'Published/Public ORLMS publication attached to non-Public item' check_name,
    COUNT(*) problem_rows
FROM legislative_items li
JOIN orlms_publications p ON p.legislative_item_id=li.id
WHERE p.publication_status='Published'
  AND p.release_classification='Public'
  AND (
      li.visibility<>'Public'
      OR li.deleted_at IS NOT NULL
  )

UNION ALL

SELECT
    'Published VQDSS decision marked non-Public release',
    COUNT(*)
FROM vqd_decisions
WHERE record_status='Published'
  AND release_classification<>'Public'

UNION ALL

SELECT
    'Public PHCMS hearing has unsupported public status',
    COUNT(*)
FROM hearings
WHERE visibility='Public'
  AND status NOT IN ('Upcoming','Ongoing','Completed','Cancelled')

UNION ALL

SELECT
    'Public CEPFMS follow-up references missing submission',
    COUNT(*)
FROM cef_followups f
LEFT JOIN cef_submissions s ON s.id=f.submission_id
WHERE f.public_visible=1
  AND s.id IS NULL

UNION ALL

SELECT
    'Delivered CEPFMS response references missing submission',
    COUNT(*)
FROM cef_responses r
LEFT JOIN cef_submissions s ON s.id=r.submission_id
WHERE r.status='Delivered'
  AND s.id IS NULL;

SELECT 'CITIZEN-VISIBLE COUNTS' AS section_name;

SELECT
    (SELECT COUNT(DISTINCT li.id)
     FROM legislative_items li
     JOIN orlms_publications p ON p.legislative_item_id=li.id
     WHERE li.deleted_at IS NULL
       AND li.visibility='Public'
       AND p.publication_status='Published'
       AND p.release_classification='Public') AS public_orlms,

    (SELECT COUNT(*)
     FROM lacms_agendas
     WHERE status IN ('Finalized','Archived')) AS public_lacms_agendas,

    (SELECT COUNT(*)
     FROM vqd_decisions
     WHERE record_status='Published'
       AND release_classification='Public') AS public_vqdss,

    (SELECT COUNT(*)
     FROM hearings
     WHERE visibility='Public'
       AND status IN ('Upcoming','Ongoing','Completed')) AS public_phcms,

    (SELECT COUNT(*)
     FROM cef_submissions
     WHERE citizen_user_id IS NOT NULL
       AND deleted_at IS NULL) AS citizen_linked_cefpms;

SELECT 'FINAL NOTE' AS section_name;
SELECT
'All rows under ACCOUNT / ACCESS INTEGRITY and PUBLIC VISIBILITY CHECKS should normally have problem_rows = 0. Citizen-visible counts may legitimately be 0 when the internal subsystem has not yet published public test data.' AS expected_result;
