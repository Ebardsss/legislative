USE `legislative_management_db`;

SELECT 'STEP 2 - PUBLIC ORLMS' AS section_name;

SELECT
    COUNT(DISTINCT li.id) AS citizen_visible_orlms_records
FROM legislative_items li
JOIN orlms_publications p ON p.legislative_item_id=li.id
WHERE li.deleted_at IS NULL
  AND li.visibility='Public'
  AND p.publication_status='Published'
  AND p.release_classification='Public';

SELECT 'STEP 3 - PUBLIC LACMS' AS section_name;

SELECT
    (SELECT COUNT(*)
     FROM lacms_agendas
     WHERE status IN ('Finalized','Archived')) AS citizen_visible_agendas,
    (SELECT COUNT(*)
     FROM lacms_calendar_events e
     LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
     LEFT JOIN legislative_items li ON li.id=e.legislative_item_id
     WHERE e.status IN ('Confirmed','In Progress','Completed')
       AND (
           (e.agenda_id IS NOT NULL AND a.status IN ('Finalized','Archived'))
           OR
           (e.legislative_item_id IS NOT NULL
            AND li.visibility='Public'
            AND li.deleted_at IS NULL)
       )) AS citizen_visible_calendar_events;

SELECT 'STEP 4 - PUBLIC VQDSS' AS section_name;

SELECT
    COUNT(*) AS citizen_visible_voting_decisions
FROM vqd_decisions
WHERE record_status='Published'
  AND release_classification='Public';

SELECT 'PUBLIC VISIBILITY SAFETY CHECKS - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT 'ORLMS eligible query includes non-Public legislative item' check_name,COUNT(*) problem_rows
FROM legislative_items li
JOIN orlms_publications p ON p.legislative_item_id=li.id
WHERE li.deleted_at IS NULL
  AND p.publication_status='Published'
  AND p.release_classification='Public'
  AND li.visibility<>'Public'

UNION ALL
SELECT 'VQDSS Published record marked non-Public release',COUNT(*)
FROM vqd_decisions
WHERE record_status='Published'
  AND release_classification<>'Public'

UNION ALL
SELECT 'Public validation attached to non-Published decision',COUNT(*)
FROM vqd_result_validations v
JOIN vqd_decisions d ON d.id=v.decision_id
WHERE v.release_classification='Public'
  AND v.validation_status IN ('Validated','Certified','Published')
  AND NOT (
      d.record_status='Published'
      AND d.release_classification='Public'
  );

SELECT 'NOTE' AS section_name;
SELECT
'LACMS currently has no dedicated public/private visibility column. The Citizen Portal therefore exposes only Finalized/Archived agendas and confirmed/completed events that are linked to a finalized agenda or a Public legislative item.' AS public_lacms_rule;
