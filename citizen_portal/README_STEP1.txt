LEGISLATIVE CITIZEN PORTAL
STEP 1 - DATABASE, LANDING PAGE, LOGIN/REGISTER AND PORTAL SHELL
================================================================

SOURCE REVIEW
-------------
This package was built against the uploaded latest:
- legislative_management_db(1).sql
- ORLMS(1).rar
- LACMS(1).rar
- vqdss(1).rar
- lph(1).rar
- CEPFMS(1).rar

Target folder:
C:\xampp\htdocs\citizen_portal\

Target URL:
http://localhost/citizen_portal/

Shared database:
legislative_management_db

DESIGN
------
The citizen portal uses the same City of Manila coastal blue / navy / white /
gold visual direction as the latest LPH/PHCMS source.

The citizen website uses a separate PHP session:
citizen_portal_session

This keeps a public citizen login isolated from the internal legislative
subsystem session while still using the same shared users database.

ACCOUNT DESIGN
--------------
Self-registration creates:
- users row
- role = Public User
- primary user_roles row
- explicit user_system_access row for systems.code = public_portal
- citizen_portal_profiles row

Existing Registered Stakeholder / Public User accounts are granted explicit
Citizen Portal access by Migration 001.

Internal Administrator, Legislative Staff and Committee Member accounts are
not automatically granted citizen-portal login access.

STEP 1 DATABASE TABLES
----------------------
citizen_portal_schema_migrations
citizen_portal_profiles
citizen_portal_login_attempts

Shared system row:
code = public_portal
base URL = http://localhost/citizen_portal

Shared permission:
public_portal.access

PUBLIC VISIBILITY POLICY FOR NEXT STEPS
---------------------------------------
ORLMS
Only:
- legislative item visibility = Public
- publication status = Published
- publication release classification = Public

LACMS
Initial safe public rule:
- finalized/archived agenda-linked information
- calendar status Confirmed / In Progress / Completed
- do not expose internal notes, participants or internal documents
Future portal step can add a dedicated visibility control if needed.

VQDSS
Only:
- decision record status = Published
- release classification = Public

PHCMS / LPH
Only:
- hearing visibility = Public
- public-safe hearing status/data
- only documents marked Public

CEPFMS
Citizen-private rule:
- signed-in citizen sees only records linked to their own user account
- only Delivered official responses are public to that citizen
- never list another citizen's submission/contact information

STEP 1 PAGES
------------
Public landing:
http://localhost/citizen_portal/

Register:
http://localhost/citizen_portal/register.php

Login:
http://localhost/citizen_portal/login.php

Dashboard:
http://localhost/citizen_portal/dashboard.php

My Profile:
http://localhost/citizen_portal/pages/profile.php

Notifications:
http://localhost/citizen_portal/pages/notifications.php

Readiness:
http://localhost/citizen_portal/pages/system_readiness.php

The five subsystem navigation pages are included as Step-1 placeholders so
the final information architecture is already visible.

INSTALLATION
------------
1. Back up legislative_management_db.
2. Extract this package into:
   C:\xampp\htdocs\citizen_portal\
3. In phpMyAdmin select legislative_management_db.
4. Run:
   database/migration_001_citizen_portal_foundation.sql
5. Open:
   http://localhost/citizen_portal/
6. Register a new citizen account.
7. Login using the account.
8. Open the dashboard and profile.
9. Run:
   database/validation_001_citizen_portal_foundation.sql

QUICK STEP 1 TEST
-----------------
1. Open the landing page without login.
   Expected: landing page is accessible.

2. Click Register.
   Expected: citizen registration form opens.

3. Try a password shorter than 10 characters.
   Expected: registration is rejected.

4. Register a valid citizen account.
   Expected: success message then Login page.

5. Try the same email again.
   Expected: duplicate registration is rejected.

6. Login with the registered account.
   Expected: Citizen Dashboard opens.

7. Open My Profile.
   Expected: the registered name/email/contact/location fields appear.

8. Logout.
   Expected: session ends and landing page opens.

9. Login with a normal internal Legislative Staff account that has no
   public_portal access.
   Expected: Citizen Portal login is rejected.

10. Run the validation SQL.
    Expected: rows under
    INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS
    return problem_rows = 0.

NEXT BATCH
----------
Step 2 - Public ORLMS: Published Ordinances & Resolutions
Step 3 - Public LACMS: Agenda & Legislative Calendar
Step 4 - Public VQDSS: Published Voting Results

Then:
Step 5 - Public PHCMS Hearings & Consultation Registration
Step 6 - CEPFMS Citizen Engagement / My Submissions
Step 7 - Unified Citizen Dashboard, Search and Notifications

Final:
Step 8 - Profile/account completion
Step 9 - Security/RBAC hardening
Step 10 - System Health, final validation and complete deployment
