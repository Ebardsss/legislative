USE `legislative_management_db`;

-- ============================================================
-- CITIZEN PORTAL - STEP 8/9
-- Account security and session invalidation foundation
-- ============================================================

CREATE TABLE IF NOT EXISTS `citizen_portal_account_security` (
    `user_id` INT NOT NULL,
    `session_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `password_changed_at` DATETIME DEFAULT NULL,
    `last_profile_update_at` DATETIME DEFAULT NULL,
    `portal_access_deactivated_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_citizen_portal_security_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

SET @citizen_portal_system_id := (
    SELECT id
    FROM systems
    WHERE code='public_portal'
    LIMIT 1
);

INSERT IGNORE INTO citizen_portal_account_security
(`user_id`,`session_version`,`created_at`,`updated_at`)
SELECT
    usa.user_id,
    1,
    NOW(),
    NOW()
FROM user_system_access usa
JOIN users u ON u.id=usa.user_id
JOIN roles r ON r.id=u.role_id
WHERE usa.system_id=@citizen_portal_system_id
  AND usa.status='Active'
  AND u.status='Active'
  AND u.deleted_at IS NULL
  AND r.name IN ('Public User','Registered Stakeholder');

INSERT INTO citizen_portal_schema_migrations
(`migration_key`,`description`)
VALUES
(
    '002_account_security',
    'Adds citizen portal session-version security used for password changes, portal access deactivation and session invalidation.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    (SELECT COUNT(*)
     FROM citizen_portal_schema_migrations
     WHERE migration_key='002_account_security') AS migration_installed,
    (SELECT COUNT(*)
     FROM citizen_portal_account_security) AS security_accounts;
