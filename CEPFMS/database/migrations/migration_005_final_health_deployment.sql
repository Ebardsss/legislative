USE `legislative_management_db`;

-- ============================================================
-- CEPFMS STEP 11
-- FINAL SYSTEM HEALTH / DEPLOYMENT ALIGNMENT
-- ============================================================

INSERT INTO systems
(code,name,description,base_url,status,created_at,updated_at)
VALUES
(
    'citizen',
    'Citizen Engagement and Public Feedback Management System',
    'Manages public feedback, citizen proposals, complaints, moderation, official responses, public tracking and engagement analytics.',
    'http://localhost/cepfms',
    'Active',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description),
    base_url=VALUES(base_url),
    status='Active',
    updated_at=NOW();

SET @cefp_system_id := (
    SELECT id
    FROM systems
    WHERE code='citizen'
    LIMIT 1
);

SET @cefp_admin_id := (
    SELECT u.id
    FROM users u
    JOIN roles r ON r.id=u.role_id
    WHERE r.name='Administrator'
      AND u.status='Active'
      AND u.deleted_at IS NULL
    ORDER BY u.id
    LIMIT 1
);

-- Keep the shared primary-role bridge consistent with users.role_id.
UPDATE user_roles ur
JOIN users u ON u.id=ur.user_id
SET ur.is_primary=0
WHERE ur.is_primary=1
  AND ur.role_id<>u.role_id
  AND u.deleted_at IS NULL;

INSERT INTO user_roles
(user_id,role_id,is_primary,assigned_by,assigned_at)
SELECT
    u.id,
    u.role_id,
    1,
    COALESCE(@cefp_admin_id,u.id),
    NOW()
FROM users u
WHERE u.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
    is_primary=1,
    assigned_by=VALUES(assigned_by),
    assigned_at=NOW();

-- Final retention cleanup for the security-attempt table.
DELETE FROM cepfms_login_attempts
WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY);

INSERT INTO cepfms_schema_migrations
(migration_key,description)
VALUES
(
    '005_final_health_deployment',
    'Final CEPFMS deployment alignment: active system registration, shared primary-role synchronization, security-attempt retention cleanup and final health readiness marker.'
)
ON DUPLICATE KEY UPDATE
    description=VALUES(description);

SELECT
    (SELECT COUNT(*) FROM cepfms_schema_migrations
     WHERE migration_key IN (
       '001_cepfms_foundation',
       '002_intake_proposals_complaints',
       '003_moderation_responses_analytics',
       '004_admin_rbac_security',
       '005_final_health_deployment'
     )) AS installed_cepfms_migrations,
    (SELECT COUNT(*) FROM permissions
     WHERE system_id=@cefp_system_id
       AND code LIKE 'cepfms.%') AS fine_grained_permissions,
    (SELECT COUNT(*) FROM users u
     LEFT JOIN user_roles ur
       ON ur.user_id=u.id
      AND ur.role_id=u.role_id
      AND ur.is_primary=1
     WHERE u.deleted_at IS NULL
       AND ur.user_id IS NULL) AS users_missing_primary_role_bridge,
    (SELECT COUNT(*) FROM users u
     JOIN roles r ON r.id=u.role_id
     JOIN user_system_access usa
       ON usa.user_id=u.id
      AND usa.system_id=@cefp_system_id
      AND usa.status='Active'
     WHERE r.name='Administrator'
       AND u.status='Active'
       AND u.deleted_at IS NULL) AS active_cepfms_administrators;
