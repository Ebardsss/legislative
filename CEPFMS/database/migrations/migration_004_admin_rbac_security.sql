USE `legislative_management_db`;

-- ============================================================
-- CEPFMS STEPS 8-10
-- Administrative Configuration
-- Activity Logs + Shared User Management
-- Fine-Grained RBAC + Security Hardening
-- ============================================================

CREATE TABLE IF NOT EXISTS `cepfms_login_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cepfms_login_email_time` (`email_hash`,`attempted_at`),
    KEY `idx_cepfms_login_ip_time` (`ip_hash`,`attempted_at`),
    KEY `idx_cepfms_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `systems`
(`code`,`name`,`description`,`base_url`,`status`,`created_at`,`updated_at`)
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
    `name`=VALUES(`name`),
    `description`=VALUES(`description`),
    `base_url`=VALUES(`base_url`),
    `status`='Active',
    `updated_at`=NOW();

SET @cepfms_system_id := (
    SELECT `id`
    FROM `systems`
    WHERE `code`='citizen'
    LIMIT 1
);

INSERT INTO `permissions`
(`system_id`,`code`,`name`,`description`)
VALUES
(@cepfms_system_id,'cepfms.dashboard.view','View CEPFMS Dashboard','View the CEPFMS operational dashboard and civic-engagement queues.'),
(@cepfms_system_id,'cepfms.feedback.view','View Public Feedback','View public-feedback records, supporting evidence and reports.'),
(@cepfms_system_id,'cepfms.feedback.manage','Manage Public Feedback','Classify public feedback, update intake status and upload staff documents.'),
(@cepfms_system_id,'cepfms.proposals.view','View Proposals and Suggestions','View citizen proposals, feasibility state, documents and legislative referrals.'),
(@cepfms_system_id,'cepfms.proposals.manage','Manage Proposals and Suggestions','Assess proposals, manage disposition, upload study documents and create legislative referrals.'),
(@cepfms_system_id,'cepfms.complaints.view','View Complaints and Issues','View complaints, assignments, SLA targets, case updates, escalations and reports.'),
(@cepfms_system_id,'cepfms.complaints.manage','Manage Complaints and Issues','Assign, update, escalate, resolve and close validated complaints.'),
(@cepfms_system_id,'cepfms.moderation.view','View Moderation and Validation','View moderation queues, checklists, duplicate suggestions and review history.'),
(@cepfms_system_id,'cepfms.moderation.manage','Manage Moderation and Validation','Start reviews, update checklists, scan duplicates and save moderation decisions.'),
(@cepfms_system_id,'cepfms.responses.view','View Response Management','View official response drafts, workflow, attachments and citizen follow-ups.'),
(@cepfms_system_id,'cepfms.responses.manage','Manage Response Drafting','Create, edit, review, return, publish eligible response records and manage follow-ups.'),
(@cepfms_system_id,'cepfms.responses.approve','Approve Official Citizen Responses','Approve official citizen responses before public release.'),
(@cepfms_system_id,'cepfms.notifications.view','View Citizen Notifications','View citizen notification queues, recipient state and delivery audit.'),
(@cepfms_system_id,'cepfms.notifications.manage','Manage Citizen Notifications','Create, process, schedule and cancel citizen notifications.'),
(@cepfms_system_id,'cepfms.analytics.view','View Citizen Engagement Analytics','View civic trends, complaint SLA performance, response performance and recurring concerns.'),
(@cepfms_system_id,'cepfms.analytics.manage','Manage Citizen Engagement Analytics','Create analytics snapshots and resolve recurring-concern alerts.'),
(@cepfms_system_id,'cepfms.workflow.view','View Citizen Engagement Workflow','View end-to-end submission, moderation, assignment, response and referral traceability.'),
(@cepfms_system_id,'cepfms.reports.view','View CEPFMS Reports','View, print and export CEPFMS operational and analytics reports.'),
(@cepfms_system_id,'cepfms.configuration.manage','Manage CEPFMS Configuration','Manage citizen categories, default routing and complaint service-level targets.'),
(@cepfms_system_id,'cepfms.activity_logs.view','View CEPFMS Activity Logs','View, filter, print and export the CEPFMS audit trail.'),
(@cepfms_system_id,'cepfms.users.manage','Manage CEPFMS Users','Manage shared user accounts and explicit CEPFMS subsystem access.'),
(@cepfms_system_id,'cepfms.system_health.view','View CEPFMS System Health','View CEPFMS deployment, RBAC, security and integrity readiness.')
ON DUPLICATE KEY UPDATE
    `system_id`=VALUES(`system_id`),
    `name`=VALUES(`name`),
    `description`=VALUES(`description`);

-- Rebuild CEPFMS fine-grained mappings deterministically for shared roles.
DELETE rp
FROM `role_permissions` rp
JOIN `roles` r ON r.id=rp.role_id
JOIN `permissions` p ON p.id=rp.permission_id
WHERE p.system_id=@cepfms_system_id
  AND p.code LIKE 'cepfms.%'
  AND r.name IN (
    'Administrator','Legislative Staff','Committee Member',
    'Registered Stakeholder','Public User'
  );

-- Administrator: every CEPFMS fine-grained permission.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id,p.id
FROM `roles` r
JOIN `permissions` p ON p.system_id=@cepfms_system_id
WHERE r.name='Administrator'
  AND p.code LIKE 'cepfms.%';

-- Legislative Staff: operational lifecycle, notifications, analytics, workflow and reports.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id,p.id
FROM `roles` r
JOIN `permissions` p ON p.system_id=@cepfms_system_id
WHERE r.name='Legislative Staff'
  AND p.code IN (
    'cepfms.dashboard.view',
    'cepfms.feedback.view','cepfms.feedback.manage',
    'cepfms.proposals.view','cepfms.proposals.manage',
    'cepfms.complaints.view','cepfms.complaints.manage',
    'cepfms.moderation.view','cepfms.moderation.manage',
    'cepfms.responses.view','cepfms.responses.manage',
    'cepfms.notifications.view','cepfms.notifications.manage',
    'cepfms.analytics.view','cepfms.analytics.manage',
    'cepfms.workflow.view','cepfms.reports.view'
  );

-- Committee Member: operational read-only access.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id,p.id
FROM `roles` r
JOIN `permissions` p ON p.system_id=@cepfms_system_id
WHERE r.name='Committee Member'
  AND p.code IN (
    'cepfms.dashboard.view',
    'cepfms.feedback.view',
    'cepfms.proposals.view',
    'cepfms.complaints.view',
    'cepfms.moderation.view',
    'cepfms.responses.view',
    'cepfms.notifications.view',
    'cepfms.analytics.view',
    'cepfms.workflow.view',
    'cepfms.reports.view'
  );

-- Ensure every existing shared user has an explicit CEPFMS access row.
-- Existing explicit Inactive access is preserved.
INSERT INTO `user_system_access`
(`user_id`,`system_id`,`access_level`,`status`,`granted_by`,`granted_at`,`updated_at`)
SELECT
    u.id,
    @cepfms_system_id,
    CASE r.name
        WHEN 'Administrator' THEN 'Administrator'
        WHEN 'Legislative Staff' THEN 'Staff'
        WHEN 'Committee Member' THEN 'Committee'
        WHEN 'Registered Stakeholder' THEN 'Stakeholder'
        ELSE 'Standard'
    END,
    CASE
        WHEN r.name IN ('Administrator','Legislative Staff','Committee Member')
         AND u.status='Active'
        THEN 'Active'
        ELSE 'Inactive'
    END,
    (
        SELECT ua.id
        FROM users ua
        JOIN roles ra ON ra.id=ua.role_id
        WHERE ra.name='Administrator'
          AND ua.status='Active'
          AND ua.deleted_at IS NULL
        ORDER BY ua.id
        LIMIT 1
    ),
    NOW(),
    NOW()
FROM `users` u
JOIN `roles` r ON r.id=u.role_id
WHERE u.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
    `access_level`=VALUES(`access_level`),
    `status`=CASE
        WHEN user_system_access.status='Inactive' THEN 'Inactive'
        ELSE VALUES(`status`)
    END,
    `updated_at`=NOW();

INSERT INTO `cepfms_schema_migrations`
(`migration_key`,`description`)
VALUES
(
    '004_admin_rbac_security',
    'Completes CEPFMS configuration administration, audit and shared-user administration, fine-grained RBAC, explicit subsystem access, security headers, session rotation and hashed login-attempt rate limiting.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

DELETE FROM `cepfms_login_attempts`
WHERE attempted_at < DATE_SUB(NOW(),INTERVAL 30 DAY);

SELECT
    (SELECT COUNT(*) FROM permissions
     WHERE system_id=@cepfms_system_id
       AND code LIKE 'cepfms.%') AS cepfms_permission_count,
    (SELECT COUNT(*) FROM role_permissions rp
     JOIN permissions p ON p.id=rp.permission_id
     WHERE p.system_id=@cepfms_system_id) AS role_permission_mappings,
    (SELECT COUNT(*) FROM users u
     JOIN user_system_access usa
       ON usa.user_id=u.id
      AND usa.system_id=@cepfms_system_id
     WHERE u.deleted_at IS NULL) AS explicit_cepfms_access_rows,
    (SELECT COUNT(*) FROM users u
     JOIN roles r ON r.id=u.role_id
     JOIN user_system_access usa
       ON usa.user_id=u.id
      AND usa.system_id=@cepfms_system_id
      AND usa.status='Active'
     WHERE r.name='Administrator'
       AND u.status='Active'
       AND u.deleted_at IS NULL) AS active_cepfms_admins;
