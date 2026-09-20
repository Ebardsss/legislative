USE `legislative_management_db`;

-- ============================================================
-- CEPFMS STEPS 5-7
-- Moderation & Validation
-- Response Management + Citizen Notifications
-- Citizen Engagement Analytics + Workflow Reporting
-- ============================================================

CREATE TABLE IF NOT EXISTS `cef_notification_delivery_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_recipient_id` BIGINT NOT NULL,
    `delivery_channel` VARCHAR(40) NOT NULL,
    `attempt_number` INT NOT NULL DEFAULT 1,
    `status` VARCHAR(40) NOT NULL,
    `provider` VARCHAR(100) DEFAULT NULL,
    `response_code` VARCHAR(100) DEFAULT NULL,
    `response_message` TEXT DEFAULT NULL,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_notification_attempt_recipient`
        (`notification_recipient_id`,`attempted_at`),
    CONSTRAINT `fk_cef_notification_attempt_recipient`
        FOREIGN KEY (`notification_recipient_id`)
        REFERENCES `cef_notification_recipients` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_analytics_alerts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `alert_type` VARCHAR(80) NOT NULL,
    `metric_key` VARCHAR(120) NOT NULL,
    `dimension_type` VARCHAR(100) DEFAULT NULL,
    `dimension_value` VARCHAR(255) DEFAULT NULL,
    `period_start` DATE DEFAULT NULL,
    `period_end` DATE DEFAULT NULL,
    `metric_value` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `threshold_value` DECIMAL(18,4) DEFAULT NULL,
    `severity` VARCHAR(30) NOT NULL DEFAULT 'Attention',
    `status` VARCHAR(30) NOT NULL DEFAULT 'Open',
    `details` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cef_analytics_alert_status` (`status`,`severity`,`created_at`),
    KEY `idx_cef_analytics_alert_dimension`
        (`metric_key`,`dimension_type`,`dimension_value`,`period_start`,`period_end`),
    CONSTRAINT `fk_cef_analytics_alert_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_analytics_alert_resolver`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @moderation_queue_idx := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='cef_submissions'
      AND index_name='idx_cef_submission_moderation_type'
);
SET @sql := IF(
    @moderation_queue_idx=0,
    'ALTER TABLE `cef_submissions`
       ADD KEY `idx_cef_submission_moderation_type`
       (`moderation_status`,`submission_type`,`status`,`created_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @response_queue_idx := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='cef_responses'
      AND index_name='idx_cef_response_status_updated'
);
SET @sql := IF(
    @response_queue_idx=0,
    'ALTER TABLE `cef_responses`
       ADD KEY `idx_cef_response_status_updated`
       (`status`,`updated_at`,`submission_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `cepfms_schema_migrations`
(`migration_key`,`description`)
VALUES
(
    '003_moderation_responses_analytics',
    'Completes moderation/validation, response drafting and controlled delivery, citizen notification audit, follow-up communication, live analytics, recurring-issue alerts and cross-module workflow reporting.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT migration_key,description,applied_at
FROM cepfms_schema_migrations
ORDER BY applied_at,migration_key;
