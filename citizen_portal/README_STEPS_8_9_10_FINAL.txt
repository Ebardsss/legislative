LEGISLATIVE CITIZEN PORTAL
FINAL BATCH - STEPS 8, 9 AND 10
========================================

TARGET
------
C:\xampp\htdocs\citizen_portal\

URL
---
http://localhost/citizen_portal/

STEP 8 - CITIZEN PROFILE / ACCOUNT MANAGEMENT
----------------------------------------------
Pages:
- pages/profile.php
- pages/account_security.php

Completed:
- edit full name
- edit username
- edit email
- edit phone
- edit address
- edit district
- edit barangay
- preferred contact method
- duplicate username/email protection
- synchronized linked PHCMS stakeholder contact details
- password change
- portal-only access deactivation

Existing CEPFMS submissions keep their original contact snapshot. Updating the
profile does not rewrite old submissions.

STEP 9 - SECURITY / ACCESS HARDENING
------------------------------------
New database table:
citizen_portal_account_security

New migration:
database/migration_002_account_security.sql

Security improvements:
- session security version
- password changes invalidate older Citizen Portal sessions
- portal-access deactivation invalidates current/older sessions
- account status/role/access revalidated on protected requests
- current display identity refreshed from the database
- POST + CSRF logout
- POST + CSRF profile/password/deactivation writes
- login rate limiting retained
- password hashing retained
- session fixation protection retained
- periodic session ID rotation retained
- 8-hour inactivity timeout retained
- HttpOnly / SameSite cookies retained
- Secure cookie automatically used on HTTPS
- authenticated pages send no-store cache headers
- Content-Security-Policy
- X-Frame-Options
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy
- HSTS automatically enabled only when HTTPS is actually used
- directory browsing disabled
- SQL/log/config/readme/manifest file types blocked through root .htaccess

IMPORTANT:
The Citizen Portal still accepts only:
- Public User
- Registered Stakeholder

Administrator, Legislative Staff and Committee Member accounts are denied by
the Citizen Portal even if someone manually attempts to use an internal
account.

STEP 10 - FINAL SYSTEM HEALTH / VALIDATION
------------------------------------------
System Health:
http://localhost/citizen_portal/pages/system_health.php

The page checks:
- shared database connection
- PHP runtime
- PDO MySQL
- Citizen Portal system registration
- Citizen Portal permission
- account tables
- ORLMS source tables
- LACMS source tables
- VQDSS source tables
- PHCMS source tables
- CEPFMS source tables
- writable log folder
- current citizen-visible record counts

Final SQL:
database/final_validation.sql

INSTALLATION
------------
1. Back up the current:
   C:\xampp\htdocs\citizen_portal\

2. In phpMyAdmin select:
   legislative_management_db

3. Run FIRST:
   database/migration_002_account_security.sql

4. Extract:
   citizen_portal_steps8_9_10_final_bundle.zip

5. Merge/replace the files in:
   C:\xampp\htdocs\citizen_portal\

6. Press Ctrl+F5.

7. Login using a Public User or Registered Stakeholder account.

8. Open:
   pages/profile.php

9. Open:
   pages/account_security.php

10. Open:
    pages/system_health.php

11. Run:
    database/final_validation.sql

EXPECTED FINAL SQL RESULT
-------------------------
Under:
ACCOUNT / ACCESS INTEGRITY - EXPECT ZERO PROBLEM ROWS

all problem_rows should be 0.

Under:
PUBLIC VISIBILITY CHECKS - EXPECT ZERO PROBLEM ROWS

all problem_rows should normally be 0.

Citizen-visible counts can be 0 when no internal record has been published
for public viewing yet.

FINAL CITIZEN PORTAL MODULES
----------------------------
1. Landing Page
2. Register / Login
3. Citizen Dashboard
4. Unified Public Search
5. ORLMS - Published Ordinances & Resolutions
6. LACMS - Finalized Agenda & Calendar
7. VQDSS - Published Voting Results
8. PHCMS - Public Hearings & Consultation Registration
9. CEPFMS - Feedback / Proposal / Complaint / Follow-up
10. Notifications
11. My Profile
12. Account Security
13. System Health

FINAL END-TO-END TEST
---------------------
A. ACCOUNT
1. Register a new citizen.
Expected:
Public User account is created.

2. Login.
Expected:
Citizen Dashboard opens.

3. Update name, phone and address.
Expected:
Profile saves and new values display.

4. If the citizen already has a PHCMS stakeholder record, check PHCMS.
Expected:
Name/contact/address are synchronized.

5. Change password.
Expected:
Success message and current browser remains signed in.

6. Open an older Citizen Portal session from another browser.
Expected:
It is forced to sign in again because the security version changed.

B. ORLMS
7. Publish one Public ordinance/resolution internally.
Expected:
It appears in Citizen Portal.

8. Change it to Internal.
Expected:
It disappears and the old detail URL no longer opens.

C. LACMS
9. Finalize an agenda and use a safe confirmed event.
Expected:
Citizen Agenda & Calendar shows it.

D. VQDSS
10. Publish a Public decision.
Expected:
Voting Results shows the decision and totals.

E. PHCMS
11. Create an Upcoming Public hearing with a future registration deadline.
Expected:
Citizen sees the hearing.

12. Register as citizen.
Expected:
Pending stakeholder/registration workflow is created for PHCMS review.

F. CEPFMS
13. Create Feedback.
Expected:
CEF reference is generated and appears in My Engagement.

14. Create Proposal.
Expected:
Proposal details are stored.

15. Create Complaint.
Expected:
Complaint is stored with SLA target dates.

16. Deliver an official CEPFMS response internally.
Expected:
Citizen sees the Delivered response.

17. Send a citizen follow-up.
Expected:
The public follow-up is saved and visible.

G. SEARCH / NOTIFICATIONS
18. Search published records from each connected system.
Expected:
Only citizen-safe records are returned.

19. Open Notifications.
Expected:
Only notifications linked to the signed-in citizen appear.

H. ACCESS CONTROL
20. Try a Legislative Staff/Admin account.
Expected:
Citizen Portal login is denied.

21. Change an engagement view ID to another citizen's record.
Expected:
Not found.

22. Deactivate Citizen Portal access from Account Security.
Expected:
Portal access becomes Inactive and the user is signed out.
The shared account and records remain in the database.

I. FINAL HEALTH
23. Open System Health.
Expected:
All required service checks show READY.

24. Run database/final_validation.sql.
Expected:
All problem_rows are 0.

DEPLOYMENT NOTE
---------------
APP_DEBUG remains OFF by default.

For temporary local troubleshooting only:
set environment variable:
CITIZEN_PORTAL_DEBUG=1

Do not leave debug mode enabled for normal use.
