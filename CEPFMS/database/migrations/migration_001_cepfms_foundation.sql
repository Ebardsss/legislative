USE `legislative_management_db`;

-- ============================================================
-- CEPFMS MIGRATION 001
-- Citizen Engagement and Public Feedback Management System
-- Database / Source Alignment + Operational Foundation
-- ============================================================

CREATE TABLE IF NOT EXISTS `cepfms_schema_migrations` (
    `migration_key` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `systems`
(`code`,`name`,`description`,`base_url`,`status`,`created_at`,`updated_at`)
VALUES
(
    'citizen',
    'Citizen Engagement and Public Feedback Management System',
    'Manages centralized citizen feedback, proposals, complaints, moderation, official responses, tracking and engagement analytics.',
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

INSERT INTO `user_system_access`
(`user_id`,`system_id`,`access_level`,`status`,`granted_by`,`granted_at`,`updated_at`)
SELECT
    u.id,
    @cepfms_system_id,
    'Administrator',
    'Active',
    u.id,
    NOW(),
    NOW()
FROM `users` u
JOIN `roles` r ON r.id=u.role_id
WHERE r.name='Administrator'
  AND u.status='Active'
  AND u.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
    `access_level`='Administrator',
    `status`='Active',
    `updated_at`=NOW();

CREATE TABLE IF NOT EXISTS `cef_categories` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(180) NOT NULL,
    `category_type` VARCHAR(30) NOT NULL DEFAULT 'All',
    `description` TEXT DEFAULT NULL,
    `parent_id` BIGINT DEFAULT NULL,
    `default_office_id` INT DEFAULT NULL,
    `default_committee_id` INT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_category_name_type` (`name`,`category_type`),
    KEY `idx_cef_category_parent` (`parent_id`),
    KEY `idx_cef_category_office` (`default_office_id`),
    KEY `idx_cef_category_committee` (`default_committee_id`),
    CONSTRAINT `fk_cef_category_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `cef_categories` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_category_office`
        FOREIGN KEY (`default_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_category_committee`
        FOREIGN KEY (`default_committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_category_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_submissions` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `public_id` CHAR(36) NOT NULL,
    `reference_number` VARCHAR(60) NOT NULL,
    `submission_type` VARCHAR(30) NOT NULL,
    `citizen_user_id` INT DEFAULT NULL,
    `citizen_name` VARCHAR(180) DEFAULT NULL,
    `citizen_email` VARCHAR(180) DEFAULT NULL,
    `citizen_phone` VARCHAR(60) DEFAULT NULL,
    `contact_preference` VARCHAR(30) NOT NULL DEFAULT 'Portal',
    `anonymous_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `privacy_consent` TINYINT(1) NOT NULL DEFAULT 0,
    `consent_at` DATETIME DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `summary` TEXT DEFAULT NULL,
    `details` LONGTEXT NOT NULL,
    `category_id` BIGINT DEFAULT NULL,
    `location_text` VARCHAR(255) DEFAULT NULL,
    `district` VARCHAR(100) DEFAULT NULL,
    `barangay` VARCHAR(150) DEFAULT NULL,
    `priority_level` VARCHAR(30) NOT NULL DEFAULT 'Normal',
    `source_channel` VARCHAR(50) NOT NULL DEFAULT 'Web Portal',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Submitted',
    `moderation_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `tracking_token_hash` CHAR(64) NOT NULL,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `closed_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_submission_public_id` (`public_id`),
    UNIQUE KEY `uq_cef_submission_reference` (`reference_number`),
    KEY `idx_cef_submission_type_status` (`submission_type`,`status`,`created_at`),
    KEY `idx_cef_submission_moderation` (`moderation_status`,`created_at`),
    KEY `idx_cef_submission_category` (`category_id`),
    KEY `idx_cef_submission_location` (`district`,`barangay`),
    KEY `idx_cef_submission_citizen_user` (`citizen_user_id`),
    CONSTRAINT `fk_cef_submission_user`
        FOREIGN KEY (`citizen_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_submission_category`
        FOREIGN KEY (`category_id`) REFERENCES `cef_categories` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_submission_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_feedback_submissions` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `feedback_kind` VARCHAR(60) NOT NULL DEFAULT 'General Feedback',
    `service_area` VARCHAR(180) DEFAULT NULL,
    `desired_outcome` TEXT DEFAULT NULL,
    `citizen_rating` TINYINT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_feedback_submission` (`submission_id`),
    CONSTRAINT `fk_cef_feedback_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_proposals` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `problem_statement` LONGTEXT DEFAULT NULL,
    `proposed_solution` LONGTEXT DEFAULT NULL,
    `expected_public_benefit` LONGTEXT DEFAULT NULL,
    `estimated_scope` VARCHAR(120) DEFAULT NULL,
    `feasibility_status` VARCHAR(50) NOT NULL DEFAULT 'Not Reviewed',
    `disposition` VARCHAR(80) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_proposal_submission` (`submission_id`),
    CONSTRAINT `fk_cef_proposal_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_complaints` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `affected_service` VARCHAR(180) DEFAULT NULL,
    `incident_datetime` DATETIME DEFAULT NULL,
    `urgency_level` VARCHAR(30) NOT NULL DEFAULT 'Normal',
    `acknowledgement_target_at` DATETIME DEFAULT NULL,
    `response_target_at` DATETIME DEFAULT NULL,
    `resolution_target_at` DATETIME DEFAULT NULL,
    `resolution_summary` LONGTEXT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `citizen_confirmation` VARCHAR(40) NOT NULL DEFAULT 'Not Requested',
    `confirmed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_complaint_submission` (`submission_id`),
    KEY `idx_cef_complaint_due` (`resolution_target_at`,`resolved_at`),
    CONSTRAINT `fk_cef_complaint_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_submission_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(120) DEFAULT NULL,
    `file_size` BIGINT DEFAULT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Supporting Evidence',
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_submission_document` (`submission_id`,`uploaded_at`),
    CONSTRAINT `fk_cef_submission_document`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_submission_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_submission_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `public_visible` TINYINT(1) NOT NULL DEFAULT 0,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_submission_history` (`submission_id`,`created_at`),
    CONSTRAINT `fk_cef_submission_history_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_submission_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_moderation_reviews` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `review_round` INT NOT NULL DEFAULT 1,
    `reviewer_id` INT DEFAULT NULL,
    `completeness_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `relevance_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `duplicate_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `identity_status` VARCHAR(40) NOT NULL DEFAULT 'Not Required',
    `content_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `classification_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `decision` VARCHAR(60) NOT NULL DEFAULT 'Pending',
    `duplicate_of_submission_id` BIGINT DEFAULT NULL,
    `notes` LONGTEXT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_moderation_round` (`submission_id`,`review_round`),
    KEY `idx_cef_moderation_queue` (`decision`,`created_at`),
    CONSTRAINT `fk_cef_moderation_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_moderation_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_moderation_duplicate`
        FOREIGN KEY (`duplicate_of_submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_moderation_checklist` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `moderation_review_id` BIGINT NOT NULL,
    `item_code` VARCHAR(80) NOT NULL,
    `item_label` VARCHAR(255) NOT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `notes` TEXT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_moderation_check` (`moderation_review_id`,`item_code`),
    CONSTRAINT `fk_cef_moderation_check_review`
        FOREIGN KEY (`moderation_review_id`) REFERENCES `cef_moderation_reviews` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_moderation_check_user`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_duplicate_matches` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `matched_submission_id` BIGINT NOT NULL,
    `similarity_score` DECIMAL(6,3) DEFAULT NULL,
    `match_source` VARCHAR(40) NOT NULL DEFAULT 'Manual',
    `status` VARCHAR(40) NOT NULL DEFAULT 'Suggested',
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_duplicate_pair` (`submission_id`,`matched_submission_id`),
    KEY `idx_cef_duplicate_status` (`submission_id`,`status`),
    CONSTRAINT `fk_cef_duplicate_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_duplicate_match`
        FOREIGN KEY (`matched_submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_duplicate_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_assignments` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `office_id` INT DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `assigned_user_id` INT DEFAULT NULL,
    `assignment_role` VARCHAR(50) NOT NULL DEFAULT 'Primary',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Assigned',
    `assigned_by` INT DEFAULT NULL,
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_assignment_submission` (`submission_id`,`status`),
    KEY `idx_cef_assignment_due` (`status`,`due_at`),
    CONSTRAINT `fk_cef_assignment_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_assignment_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_assignment_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_assignment_user`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_assignment_by`
        FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_assignment_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `assignment_id` BIGINT NOT NULL,
    `submission_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_assignment_history` (`assignment_id`,`created_at`),
    CONSTRAINT `fk_cef_assignment_history_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `cef_assignments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_assignment_history_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_assignment_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_case_updates` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `assignment_id` BIGINT DEFAULT NULL,
    `update_type` VARCHAR(60) NOT NULL DEFAULT 'Progress Update',
    `update_text` LONGTEXT NOT NULL,
    `public_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_case_update_submission` (`submission_id`,`created_at`),
    CONSTRAINT `fk_cef_case_update_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_case_update_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `cef_assignments` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_case_update_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_escalations` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `assignment_id` BIGINT DEFAULT NULL,
    `escalation_level` VARCHAR(40) NOT NULL DEFAULT 'Attention',
    `reason` LONGTEXT NOT NULL,
    `status` VARCHAR(40) NOT NULL DEFAULT 'Open',
    `escalated_to_user_id` INT DEFAULT NULL,
    `escalated_to_office_id` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cef_escalation_submission` (`submission_id`,`status`,`created_at`),
    CONSTRAINT `fk_cef_escalation_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_escalation_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `cef_assignments` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_escalation_user`
        FOREIGN KEY (`escalated_to_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_escalation_office`
        FOREIGN KEY (`escalated_to_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_escalation_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_escalation_resolver`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_responses` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `assignment_id` BIGINT DEFAULT NULL,
    `response_reference` VARCHAR(60) NOT NULL,
    `response_type` VARCHAR(60) NOT NULL DEFAULT 'Official Response',
    `subject` VARCHAR(255) NOT NULL,
    `body` LONGTEXT NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `drafted_by` INT DEFAULT NULL,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `delivery_channel` VARCHAR(40) DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_response_reference` (`response_reference`),
    KEY `idx_cef_response_submission` (`submission_id`,`status`,`created_at`),
    CONSTRAINT `fk_cef_response_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_response_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `cef_assignments` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_response_drafter`
        FOREIGN KEY (`drafted_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_response_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_response_approver`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_response_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `response_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_response_history` (`response_id`,`created_at`),
    CONSTRAINT `fk_cef_response_history_response`
        FOREIGN KEY (`response_id`) REFERENCES `cef_responses` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_response_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_response_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `response_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Response Attachment',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_response_document` (`response_id`,`uploaded_at`),
    CONSTRAINT `fk_cef_response_document_response`
        FOREIGN KEY (`response_id`) REFERENCES `cef_responses` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_response_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_followups` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `response_id` BIGINT DEFAULT NULL,
    `direction` VARCHAR(40) NOT NULL,
    `sender_user_id` INT DEFAULT NULL,
    `sender_name` VARCHAR(180) DEFAULT NULL,
    `message` LONGTEXT NOT NULL,
    `public_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_followup_submission` (`submission_id`,`created_at`),
    CONSTRAINT `fk_cef_followup_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_followup_response`
        FOREIGN KEY (`response_id`) REFERENCES `cef_responses` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_followup_user`
        FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_notifications` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_reference` VARCHAR(60) NOT NULL,
    `submission_id` BIGINT DEFAULT NULL,
    `response_id` BIGINT DEFAULT NULL,
    `notification_type` VARCHAR(80) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` LONGTEXT NOT NULL,
    `scheduled_at` DATETIME DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Ready',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_notification_reference` (`notification_reference`),
    KEY `idx_cef_notification_status` (`status`,`scheduled_at`),
    CONSTRAINT `fk_cef_notification_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_notification_response`
        FOREIGN KEY (`response_id`) REFERENCES `cef_responses` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_cef_notification_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_notification_recipients` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `recipient_name` VARCHAR(180) DEFAULT NULL,
    `recipient_email` VARCHAR(180) DEFAULT NULL,
    `recipient_phone` VARCHAR(60) DEFAULT NULL,
    `delivery_channel` VARCHAR(40) NOT NULL DEFAULT 'Portal',
    `delivery_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `sent_at` DATETIME DEFAULT NULL,
    `failure_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_notification_recipient` (`notification_id`,`delivery_status`),
    CONSTRAINT `fk_cef_notification_recipient_notification`
        FOREIGN KEY (`notification_id`) REFERENCES `cef_notifications` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_notification_recipient_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_ai_analysis` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `analysis_type` VARCHAR(60) NOT NULL,
    `provider` VARCHAR(60) DEFAULT NULL,
    `model_used` VARCHAR(120) DEFAULT NULL,
    `result_json` LONGTEXT DEFAULT NULL,
    `confidence_score` DECIMAL(6,3) DEFAULT NULL,
    `review_status` VARCHAR(40) NOT NULL DEFAULT 'Unreviewed',
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_ai_submission` (`submission_id`,`analysis_type`,`created_at`),
    CONSTRAINT `fk_cef_ai_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_ai_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_analytics_snapshots` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `snapshot_date` DATE NOT NULL,
    `period_start` DATE DEFAULT NULL,
    `period_end` DATE DEFAULT NULL,
    `metric_key` VARCHAR(120) NOT NULL,
    `dimension_type` VARCHAR(100) DEFAULT NULL,
    `dimension_value` VARCHAR(255) DEFAULT NULL,
    `metric_value` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `metadata_json` LONGTEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cef_analytics_metric` (`metric_key`,`snapshot_date`),
    KEY `idx_cef_analytics_dimension` (`dimension_type`,`dimension_value`,`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cef_legislative_referrals` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `submission_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `referral_type` VARCHAR(80) NOT NULL DEFAULT 'Citizen Engagement Referral',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Referred',
    `notes` TEXT DEFAULT NULL,
    `referred_by` INT DEFAULT NULL,
    `referred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cef_legislative_referral` (`submission_id`,`legislative_item_id`),
    CONSTRAINT `fk_cef_referral_submission`
        FOREIGN KEY (`submission_id`) REFERENCES `cef_submissions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_referral_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_cef_referral_user`
        FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cef_categories`
(`name`,`category_type`,`description`,`is_active`)
VALUES
('General Public Service','All','General concerns and observations regarding public service delivery.',1),
('Infrastructure','All','Roads, drainage, public facilities, utilities and community infrastructure.',1),
('Public Safety','All','Community safety, emergency concerns, enforcement and risk-related issues.',1),
('Environment','All','Waste, pollution, waterways, green spaces and environmental concerns.',1),
('Health and Sanitation','All','Public health, sanitation and cleanliness concerns.',1),
('Transportation and Mobility','All','Traffic, public transport, pedestrian access and mobility concerns.',1),
('Social Services','All','Community assistance, vulnerable groups and social service concerns.',1),
('Governance and Transparency','All','Government processes, transparency, accountability and citizen participation.',1),
('Ordinance and Policy','All','Policy suggestions, ordinance concerns and legislative recommendations.',1),
('Other','All','Citizen engagement records that require manual classification.',1)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`),
    `is_active`=1;

INSERT INTO `cepfms_schema_migrations`
(`migration_key`,`description`)
VALUES
(
    '001_cepfms_foundation',
    'Creates the CEPFMS citizen-engagement foundation for public submissions, proposals, complaints, moderation, assignments, responses, notifications, analytics and legislative referrals.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

-- Verification
SELECT id,code,name,base_url,status
FROM legislative_management_db.systems
WHERE code='citizen';

SELECT COUNT(*) AS cef_foundation_table_count
FROM information_schema.tables
WHERE table_schema='legislative_management_db'
  AND (
    table_name='cepfms_schema_migrations'
    OR table_name LIKE 'cef\_%'
  );

SELECT migration_key,description,applied_at
FROM legislative_management_db.cepfms_schema_migrations
ORDER BY applied_at,migration_key;
