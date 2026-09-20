CEPFMS — COMPLETE NAVIGATION AND CLIENT-DEMO FOUNDATION
=======================================================

SYSTEM NAME
-----------

CIRIZEN ENGAGEMENT AND PUBLIC FEEDBACK MANAGEMENT SYSTEM

The word "CIRIZEN" is retained exactly as provided for the client-facing
system label.


INSTALLATION
------------

Extract the package contents into:

C:\xampp\htdocs\cepfms\

Open:

http://localhost/cepfms/

Shared administrator account:

admin@legislative.local
Admin@123


CURRENT DEVELOPMENT GOAL
------------------------

This package completes the navigation and client-demo foundation only.

Database migrations, CRUD, AJAX endpoints, document uploads, email or
SMS delivery, tracking references, moderation rules, and analytics
calculations are intentionally postponed.


PRIMARY MODULES
---------------

1. Public Feedback Submission Module

   http://localhost/cepfms/modules/feedback/index.php

2. Proposal and Suggestion Management Module

   http://localhost/cepfms/modules/proposals/index.php

3. Complaint and Issue Tracking Module

   http://localhost/cepfms/modules/complaints/index.php

4. Moderation and Validation Module

   http://localhost/cepfms/modules/moderation/index.php

5. Response Management Module

   http://localhost/cepfms/modules/responses/index.php

6. Citizen Engagement Analytics Module

   http://localhost/cepfms/modules/analytics/index.php


PUBLIC CITIZEN PORTAL
---------------------

http://localhost/cepfms/public/index.php

The public portal contains navigation and client-demo forms for:

- public feedback
- proposals and suggestions
- complaints and issues
- reference tracking
- citizen submission workflow

The forms generate browser previews only and do not save records.


SHARED PLATFORM INTEGRATION
---------------------------

Database:

legislative_management_db

Shared session:

lph_session

Cookie path:

/

Idle timeout:

8 hours

The header contains a subsystem switcher for:

- ORLMS
- LACMS
- VQDSS
- LPH
- CEPFMS


FOLDER STRUCTURE
----------------

cepfms/
├── assets/
│   ├── css/
│   ├── images/
│   ├── js/
│   ├── uploads/
│   └── vendor/
├── auth/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── includes/
├── layouts/
├── logs/
├── modules/
│   ├── feedback/
│   ├── proposals/
│   ├── complaints/
│   ├── moderation/
│   ├── responses/
│   └── analytics/
├── pages/
├── public/
├── dashboard.php
├── index.php
├── login.php
└── logout.php


NAVIGATION FEATURES
-------------------

- shared login and session compatibility
- role-based access for Administrator, Staff, and Committee roles
- responsive navy, white, and gold layout
- six primary module links
- previous and next module navigation
- module capabilities and workflow previews
- citizen portal link
- cross-subsystem switcher
- Activity Logs navigation
- Administrator-only User Management navigation
- dashboard workflow and subsystem cards
- safe zero-count checks for future cef_ tables


RECOMMENDED FUTURE TABLE PREFIX
-------------------------------

cef_

Examples:

cef_feedback_submissions
cef_proposals
cef_complaints
cef_moderation_reviews
cef_responses
cef_analytics_snapshots


NEXT PHASE AFTER ALL NAVIGATIONS
--------------------------------

When backend development resumes:

- create the cef_ database schema
- connect AJAX CRUD
- implement public reference generation
- secure file uploads
- consent and privacy controls
- moderation and duplicate detection
- assignment and service-level tracking
- response approval and delivery
- notification integration
- sentiment and engagement analytics
- immutable activity logs
- cross-subsystem legislative referrals
