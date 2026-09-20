USE `legislative_management_db`;

-- ============================================================
-- CEPFMS STEPS 2-4
-- Public Feedback Submission + Public Citizen Portal
-- Proposal & Suggestion Management
-- Complaint & Issue Tracking
-- ============================================================

CREATE TABLE IF NOT EXISTS `cepfms_sequences` (
    `sequence_key` VARCHAR(80) NOT NULL,
    `sequence_year` INT NOT NULL,
    `next_value` INT NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sequence_key`,`sequence_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_service_levels` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_type` VARCHAR(30) NOT NULL DEFAULT 'Complaint',
    `urgency_level` VARCHAR(30) NOT NULL,
    `acknowledgement_hours` INT NOT NULL,
    `response_hours` INT NOT NULL,
    `resolution_hours` INT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_service_level` (`submission_type`,`urgency_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cef_service_levels`
(`submission_type`,`urgency_level`,`acknowledgement_hours`,`response_hours`,`resolution_hours`,`is_active`)
VALUES
('Complaint','Low',72,120,360,1),
('Complaint','Normal',48,96,240,1),
('Complaint','High',24,48,120,1),
('Complaint','Urgent',4,12,48,1)
ON DUPLICATE KEY UPDATE
    `acknowledgement_hours`=VALUES(`acknowledgement_hours`),
    `response_hours`=VALUES(`response_hours`),
    `resolution_hours`=VALUES(`resolution_hours`),
    `is_active`=1;

SET @submission_category_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='cef_submissions'
      AND index_name='idx_cef_submission_public_tracking'
);
SET @sql := IF(
    @submission_category_index=0,
    'ALTER TABLE `cef_submissions`
       ADD KEY `idx_cef_submission_public_tracking`
       (`reference_number`,`status`,`moderation_status`,`created_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `cepfms_schema_migrations`
(`migration_key`,`description`)
VALUES
(
    '002_intake_proposals_complaints',
    'Completes public feedback intake/tracking, proposal and suggestion management, complaint issue tracking, complaint service-level targets, routing updates and public status visibility.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT migration_key,description,applied_at
FROM cepfms_schema_migrations
ORDER BY applied_at,migration_key;
