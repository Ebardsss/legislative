USE `legislative_management_db`;

-- ============================================================
-- CITIZEN PORTAL - STEP 1
-- Public account, login, profile and subsystem-access foundation
-- ============================================================

CREATE TABLE IF NOT EXISTS `citizen_portal_schema_migrations` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `migration_key` VARCHAR(120) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_citizen_portal_migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `citizen_portal_profiles` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `address` TEXT DEFAULT NULL,
    `district` VARCHAR(100) DEFAULT NULL,
    `barangay` VARCHAR(150) DEFAULT NULL,
    `preferred_contact` VARCHAR(30) NOT NULL DEFAULT 'Portal',
    `privacy_consent_at` DATETIME NOT NULL,
    `terms_accepted_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_citizen_portal_profile_user` (`user_id`),
    CONSTRAINT `fk_citizen_portal_profile_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `citizen_portal_login_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_citizen_portal_login_email_time` (`email_hash`,`attempted_at`),
    KEY `idx_citizen_portal_login_ip_time` (`ip_hash`,`attempted_at`),
    KEY `idx_citizen_portal_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `systems`
(`code`,`name`,`description`,`base_url`,`status`,`created_at`,`updated_at`)
VALUES
(
    'public_portal',
    'Legislative Citizen Portal',
    'Citizen-facing portal for public legislative information from ORLMS, LACMS, VQDSS, PHCMS and CEPFMS.',
    'http://localhost/citizen_portal',
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

SET @citizen_portal_system_id := (
    SELECT id FROM systems WHERE code='public_portal' LIMIT 1
);

INSERT INTO `permissions`
(`system_id`,`code`,`name`,`description`)
VALUES
(
    @citizen_portal_system_id,
    'public_portal.access',
    'Access Legislative Citizen Portal',
    'Allows an eligible citizen or registered stakeholder to sign in to the Legislative Citizen Portal.'
)
ON DUPLICATE KEY UPDATE
    `system_id`=VALUES(`system_id`),
    `name`=VALUES(`name`),
    `description`=VALUES(`description`);

-- Public User and Registered Stakeholder can use the citizen portal.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id,p.id
FROM roles r
JOIN permissions p
  ON p.code='public_portal.access'
WHERE r.name IN ('Public User','Registered Stakeholder');

-- Give existing active citizen/stakeholder accounts explicit portal access.
INSERT INTO `user_system_access`
(`user_id`,`system_id`,`access_level`,`status`,`granted_by`,`granted_at`,`updated_at`)
SELECT
    u.id,
    @citizen_portal_system_id,
    CASE WHEN r.name='Registered Stakeholder' THEN 'Stakeholder' ELSE 'Citizen' END,
    CASE WHEN u.status='Active' THEN 'Active' ELSE 'Inactive' END,
    (
        SELECT a.id
        FROM users a
        JOIN roles ar ON ar.id=a.role_id
        WHERE ar.name='Administrator'
          AND a.status='Active'
          AND a.deleted_at IS NULL
        ORDER BY a.id
        LIMIT 1
    ),
    NOW(),
    NOW()
FROM users u
JOIN roles r ON r.id=u.role_id
WHERE u.deleted_at IS NULL
  AND r.name IN ('Public User','Registered Stakeholder')
ON DUPLICATE KEY UPDATE
    `access_level`=VALUES(`access_level`),
    `updated_at`=NOW();

INSERT INTO `citizen_portal_schema_migrations`
(`migration_key`,`description`)
VALUES
(
    '001_citizen_portal_foundation',
    'Creates citizen portal profiles, secure login-attempt tracking, public_portal system registration, access permission and citizen/stakeholder subsystem access foundation.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

DELETE FROM citizen_portal_login_attempts
WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

SELECT
    @citizen_portal_system_id AS public_portal_system_id,
    (SELECT COUNT(*) FROM citizen_portal_schema_migrations
     WHERE migration_key='001_citizen_portal_foundation') AS migration_installed,
    (SELECT COUNT(*) FROM permissions
     WHERE code='public_portal.access') AS portal_permission_count,
    (SELECT COUNT(*) FROM user_system_access
     WHERE system_id=@citizen_portal_system_id
       AND status='Active') AS active_portal_accounts;
