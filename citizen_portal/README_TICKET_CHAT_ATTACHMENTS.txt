CEPFMS + CITIZEN PORTAL
TICKET ATTACHMENT AND CONVERSATION ENHANCEMENT
================================================

WHAT CHANGED
------------
1. CEPFMS staff can mark ticket/submission attachments as:
   - Internal Only
   - Citizen Visible

2. CEPFMS response attachments now also have the same visibility setting.

3. Citizen Portal > Feedback & Engagement > Ticket Detail now shows:
   - Citizen-visible ticket attachments
   - Citizen-visible Delivered response attachments
   - View button
   - Download button

4. Ticket Conversation now uses the existing shared cef_followups table.
   No duplicate chat table was created.

5. The same public conversation appears in:
   - Citizen Portal ticket detail
   - CEPFMS Feedback ticket detail
   - CEPFMS Proposal ticket detail
   - CEPFMS Complaint ticket detail

6. When ticket status becomes:
   - Closed
   - Withdrawn
   the conversation becomes read-only on both websites.
   Previous messages and attachments remain visible.

DATABASE STEP
-------------
Run this ONCE in phpMyAdmin on legislative_management_db:

CEPFMS/database/migrations/migration_006_ticket_chat_citizen_attachments.sql

This adds to cef_response_documents:
- mime_type
- file_size
- visibility

Existing response attachments remain Internal by default for safety.

HOW STAFF SHARES A FILE WITH CITIZEN
------------------------------------
Inside CEPFMS ticket:
1. Choose file.
2. Enter document type.
3. Select Citizen Visible.
4. Upload.

For an already uploaded submission document, use the:
Make Citizen Visible
button below the attachment.

For response attachments, the same visibility option/toggle is available.
The Citizen Portal only shows a response attachment when:
- attachment visibility = Public / Citizen Visible
- response status = Delivered

CHAT TEST
---------
1. Login as citizen.
2. Open Feedback & Engagement > ticket.
3. Send message.
Expected: message appears in Ticket Conversation.

4. Login to CEPFMS staff.
5. Open the same Feedback/Proposal/Complaint ticket.
Expected: citizen message appears.

6. Staff replies.
Expected: reply appears in Citizen Portal after refresh.

7. Change ticket status to Closed or Withdrawn.
Expected on both systems:
- old messages remain visible
- message form is disabled
- server rejects direct POST attempts to send a new message

ATTACHMENT SECURITY
-------------------
Citizen Portal does not link directly to the CEPFMS upload path.
It uses a secure file endpoint that checks:
- signed-in citizen
- ticket ownership
- attachment visibility = Public
- response status = Delivered for response attachments
- file path remains inside cefpms/assets/uploads

VALIDATION
----------
CEPFMS:
database/validation_006_ticket_chat_citizen_attachments.sql

Citizen Portal:
database/validation_004_ticket_chat_attachments.sql

Expected integrity result:
problem_rows = 0
