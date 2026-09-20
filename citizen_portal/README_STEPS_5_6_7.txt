LEGISLATIVE CITIZEN PORTAL
STEPS 5, 6 AND 7
========================================

PREREQUISITE
------------
Citizen Portal Steps 1-4 installed.

No new Citizen Portal database table is required in this batch.
The new functions use the existing PHCMS and CEPFMS operational tables.

STEP 5 - PHCMS PUBLIC HEARINGS
------------------------------
URL:
http://localhost/citizen_portal/modules/hearings/index.php

Completed:
- public hearing registry
- search
- status filter
- public hearing detail
- hearing type
- responsible committee
- date/time/venue
- registration deadline
- capacity
- public meeting link
- public document metadata
- public related legislative item
- citizen hearing registration
- My Registration status

Visibility:
- hearings.visibility = Public
- status Upcoming / Ongoing / Completed

Hearing registration:
1. Citizen opens an Upcoming public hearing.
2. Portal checks deadline and maximum participant capacity.
3. If the citizen has no PHCMS stakeholder record, the portal creates one
   linked to users.id.
4. New stakeholder record starts as Pending.
5. Registration is created as Pending.
6. PHCMS staff can verify stakeholder / approve registration internally.

This preserves the internal PHCMS approval workflow.

STEP 6 - CEPFMS CITIZEN ENGAGEMENT
----------------------------------
URL:
http://localhost/citizen_portal/modules/engagement/index.php

Completed:
- citizen sees ONLY cef_submissions where citizen_user_id = current user
- create Feedback
- create Proposal
- create Complaint
- category filtering
- priority
- subtype-specific fields
- Complaint SLA targets use current cef_service_levels
- citizen account/contact/location linked automatically
- public status/history
- Delivered official responses
- citizen follow-up messages

Security:
Changing the URL to another citizen's submission ID returns not found because
the query always requires:
citizen_user_id = current signed-in user

Internal moderation notes are not shown.
Only cef_submission_history.public_visible = 1 is shown.
Only cef_responses.status = Delivered is shown.
Only cef_followups.public_visible = 1 is shown.

STEP 7 - UNIFIED SEARCH / DASHBOARD / NOTIFICATIONS
---------------------------------------------------
Search:
http://localhost/citizen_portal/pages/search.php

Searches:
- Public ORLMS records
- Finalized LACMS agendas
- Published/Public VQDSS results
- Public PHCMS hearings
- current citizen's own CEPFMS records

The search never queries unrestricted internal lists.

Dashboard:
http://localhost/citizen_portal/dashboard.php

Now includes:
- live counts from all five systems
- full citizen service shortcuts
- upcoming public hearings
- Unified Search shortcut

Notifications:
http://localhost/citizen_portal/pages/notifications.php

Shows:
- shared notifications for the signed-in user
- CEPFMS Portal/System recipient notifications for the signed-in user
- related CEPFMS submission link when available

INSTALL
-------
1. Back up:
   C:\xampp\htdocs\citizen_portal\

2. Extract:
   citizen_portal_steps5_6_7_complete_bundle.zip

3. Merge / Replace.

4. No SQL migration is required.

5. Login as a citizen.

6. Test:
   modules/hearings/index.php
   modules/engagement/index.php
   pages/search.php
   pages/notifications.php
   dashboard.php

7. Run:
   database/validation_003_public_hearings_engagement_search.sql

QUICK TEST
----------
PHCMS
1. Create an Upcoming hearing in LPH/PHCMS.
2. Set visibility = Public.
3. Set a future registration deadline.
4. Citizen opens Hearings & Consultations.
Expected:
The hearing appears.

5. Citizen opens hearing and registers.
Expected:
A Pending registration code is created.

6. Open internal PHCMS Stakeholders/Registration.
Expected:
The citizen appears as a linked stakeholder/registration for staff review.

CEPFMS
7. Citizen creates Feedback.
Expected:
CEF reference created and record appears in My Engagement.

8. Create Proposal.
Expected:
Proposal subtype data is stored.

9. Create Complaint.
Expected:
Complaint is created with SLA targets.

10. Try opening another citizen's engagement view ID.
Expected:
Not found.

11. Deliver an official response from internal CEPFMS.
Expected:
It appears in the citizen's engagement detail.

12. Citizen sends a follow-up.
Expected:
Public follow-up appears in both the portal and CEPFMS workflow.

SEARCH
13. Search a known published Ordinance.
Expected:
ORLMS result appears.

14. Search a known Finalized Agenda.
Expected:
LACMS result appears.

15. Search a Published/Public voting decision.
Expected:
VQDSS result appears.

16. Search a Public hearing.
Expected:
PHCMS result appears.

17. Search the citizen's CEF reference.
Expected:
Only that signed-in citizen's matching CEPFMS record appears.

NEXT / FINAL BATCH
------------------
Step 8 - Profile and citizen account management
Step 9 - Security, access hardening and account controls
Step 10 - System Health, final validation and complete deployment
