LEGISLATIVE CITIZEN PORTAL
STEPS 2, 3 AND 4
========================================

PREREQUISITE
------------
Install Citizen Portal Step 1 first.

Target:
C:\xampp\htdocs\citizen_portal\

URL:
http://localhost/citizen_portal/

No new database tables are required for Steps 2-4.
These steps are read-only public views over the existing shared subsystem data.

STEP 2 - PUBLIC ORLMS
---------------------
URL:
http://localhost/citizen_portal/modules/ordinances/index.php

Completed:
- published Ordinance/Resolution registry
- search by reference/title/subject
- Ordinance/Resolution filter
- pagination
- public detail page
- summary
- subject
- category
- policy area
- purpose
- explanatory note
- legal basis
- fiscal/resource impact
- legislative body text
- enactment summary when available
- official publication history
- external official public URL when present

Hard visibility rule:
- legislative_items.visibility = Public
- orlms_publications.publication_status = Published
- orlms_publications.release_classification = Public

An internal/draft measure is not returned by the citizen query.

STEP 3 - PUBLIC LACMS
---------------------
URL:
http://localhost/citizen_portal/modules/calendar/index.php

Two public views:
- Calendar Events
- Finalized Agendas

Completed:
- search
- pagination
- confirmed/in-progress/completed calendar events
- finalized/archived agendas
- agenda item listing
- schedule/venue
- committee and office
- meeting link when a valid HTTP/HTTPS link exists
- safe related agenda information

IMPORTANT LACMS RULE:
The current LACMS schema does not have an explicit visibility column on
lacms_agendas or lacms_calendar_events.

Because of that, the Citizen Portal does NOT show every LACMS event.

An event is shown only when:
- status is Confirmed / In Progress / Completed
AND
- it is linked to a Finalized/Archived agenda
OR
- it is linked to a shared legislative item marked Public

Agendas are shown only when:
- Finalized
OR
- Archived

Internal notes, participant lists, conflict notes and internal documents are
not displayed in the citizen portal.

STEP 4 - PUBLIC VQDSS
---------------------
URL:
http://localhost/citizen_portal/modules/voting/index.php

Completed:
- published voting-result registry
- decision search
- outcome filter
- pagination
- official decision detail
- affirmative / negative / abstain counts
- vote-option breakdown when available
- decision statement
- legal basis
- conditions
- effectivity
- session/voting/tally references
- public validation summary when the validation itself is Public

Hard visibility rule:
- vqd_decisions.record_status = Published
- vqd_decisions.release_classification = Public

Only validations with:
- release_classification = Public
- validation_status = Validated / Certified / Published
are shown.

INSTALL
-------
1. Back up:
   C:\xampp\htdocs\citizen_portal\

2. Extract:
   citizen_portal_steps2_3_4_complete_bundle.zip

3. Merge / Replace into:
   C:\xampp\htdocs\citizen_portal\

4. No new migration is required.

5. Log in using a citizen account.

6. Test:
   /modules/ordinances/index.php
   /modules/calendar/index.php
   /modules/voting/index.php

7. Run:
   database/validation_002_public_orlms_lacms_vqdss.sql

IMPORTANT TEST DATA NOTE
------------------------
If a module says there are 0 public records, this does not automatically mean
the portal is broken.

ORLMS needs a measure that is:
- Public
- Published
- Public release classification

LACMS needs:
- Finalized/Archived agenda
or
- Confirmed/Completed event linked to a finalized agenda/Public item

VQDSS needs a decision that is:
- Published
- Public release classification

QUICK TEST
----------
ORLMS
1. Publish one Ordinance/Resolution in ORLMS with Public visibility.
2. Login to Citizen Portal.
3. Open Ordinances & Resolutions.
Expected:
The published record appears.

4. Open the record.
Expected:
Public legislative and publication information appears.

LACMS
5. Finalize one agenda.
6. Confirm a linked calendar event.
7. Open Agenda & Calendar.
Expected:
The agenda and safe public event appear.

8. Open the agenda.
Expected:
Agenda date, venue and active agenda items appear.

VQDSS
9. Publish one VQDSS decision with release classification Public.
10. Open Voting Results.
Expected:
Decision appears with vote totals.

11. Open the result.
Expected:
Official decision statement and tally details appear.

SECURITY TEST
-------------
12. Try to change an ORLMS item to Internal.
Expected:
It disappears from Citizen Portal.

13. Change a VQDSS decision release classification to Internal.
Expected:
It disappears from Citizen Portal.

14. Try opening the old citizen detail URL directly.
Expected:
The page returns not found because server-side visibility checks still apply.

NEXT BATCH
----------
Step 5 - Public PHCMS / Hearing and Consultation services
Step 6 - CEPFMS My Engagement / submission integration
Step 7 - Unified search, citizen dashboard and notifications
