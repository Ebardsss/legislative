USE `legislative_management_db`;

SET @portal_system_id := (
    SELECT id FROM systems WHERE code='public_portal' LIMIT 1
);

SELECT 'CITIZEN PORTAL STEP 1' AS section_name;

SELECT
    (SELECT COUNT(*) FROM citizen_portal_schema_migrations
     WHERE migration_key='001_citizen_portal_foundation') migration_installed,
    (SELECT COUNT(*) FROM systems
     WHERE code='public_portal'
       AND status='Active'
       AND TRIM(TRAILING '/' FROM base_url)='http://localhost/citizen_portal') system_ready,
    (SELECT COUNT(*) FROM permissions
     WHERE system_id=@portal_system_id
       AND code='public_portal.access') permission_ready;

SELECT 'INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT 'Public/stakeholder account missing explicit portal access' check_name,COUNT(*) problem_rows
FROM users u
JOIN roles r ON r.id=u.role_id
LEFT JOIN user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=@portal_system_id
WHERE u.deleted_at IS NULL
  AND r.name IN ('Public User','Registered Stakeholder')
  AND usa.id IS NULL

UNION ALL
SELECT 'Portal profile references missing shared user',COUNT(*)
FROM citizen_portal_profiles cp
LEFT JOIN users u ON u.id=cp.user_id
WHERE u.id IS NULL

UNION ALL
SELECT 'Duplicate citizen portal profile for same user',COUNT(*)
FROM (
    SELECT user_id
    FROM citizen_portal_profiles
    GROUP BY user_id
    HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Portal access active for inactive/deleted shared user',COUNT(*)
FROM user_system_access usa
JOIN users u ON u.id=usa.user_id
WHERE usa.system_id=@portal_system_id
  AND usa.status='Active'
  AND (u.status<>'Active' OR u.deleted_at IS NOT NULL)

UNION ALL
SELECT 'Portal access active for non-citizen/non-stakeholder role',COUNT(*)
FROM user_system_access usa
JOIN users u ON u.id=usa.user_id
JOIN roles r ON r.id=u.role_id
WHERE usa.system_id=@portal_system_id
  AND usa.status='Active'
  AND r.name NOT IN ('Public User','Registered Stakeholder');
