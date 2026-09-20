USE `legislative_management_db`;

-- ============================================================
-- CEPFMS MIGRATION 006
-- TICKET CHAT + CITIZEN-VISIBLE ATTACHMENTS
-- ============================================================

-- Submission documents already have a visibility field. Normalize old values.
UPDATE `cef_submission_documents`
SET `visibility`='Internal'
WHERE `visibility` NOT IN ('Internal','Public')
   OR `visibility` IS NULL
   OR TRIM(`visibility`)='';

-- Response attachments now use the same visibility model as submission documents.
ALTER TABLE `cef_response_documents`
    ADD COLUMN IF NOT EXISTS `mime_type` VARCHAR(120) DEFAULT NULL AFTER `file_path`,
    ADD COLUMN IF NOT EXISTS `file_size` BIGINT DEFAULT NULL AFTER `mime_type`,
    ADD COLUMN IF NOT EXISTS `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal' AFTER `document_type`;

UPDATE `cef_response_documents`
SET `visibility`='Internal'
WHERE `visibility` NOT IN ('Internal','Public')
   OR `visibility` IS NULL
   OR TRIM(`visibility`)='';

-- cef_followups already stores the shared citizen/staff ticket conversation.
-- No duplicate chat table is created. Closed/Withdrawn enforcement is handled
-- server-side by both CEPFMS and Citizen Portal.

INSERT INTO `cepfms_schema_migrations`
(`migration_key`,`description`)
VALUES
(
    '006_ticket_chat_citizen_attachments',
    'Adds citizen visibility controls for response attachments and enables the existing cef_followups table to serve as the shared ticket conversation between CEPFMS staff and Citizen Portal users.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    (SELECT COUNT(*) FROM cepfms_schema_migrations
     WHERE migration_key='006_ticket_chat_citizen_attachments') AS migration_installed,
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema=DATABASE()
       AND table_name='cef_response_documents'
       AND column_name='visibility') AS response_visibility_column,
    (SELECT COUNT(*) FROM cef_submission_documents
     WHERE visibility='Public') AS citizen_visible_submission_files,
    (SELECT COUNT(*) FROM cef_response_documents
     WHERE visibility='Public') AS citizen_visible_response_files,
    (SELECT COUNT(*) FROM cef_followups
     WHERE public_visible=1) AS public_ticket_messages;
