<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/cepfms_operational_helpers.php';

$categories=[];
if(tableExists('cef_categories')){
    $categories=db()->query(
        "SELECT id,name,category_type
         FROM cef_categories
         WHERE is_active=1
         ORDER BY name"
    )->fetchAll();
}
$foundationReady=cepfmsFoundationReady();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Citizen Portal | CEPFMS</title>
<link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
<link rel="shortcut icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
<link rel="apple-touch-icon" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
<link rel="stylesheet" href="<?= e(vendorAsset('bootstrap/bootstrap.min.css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(vendorAsset('bootstrap-icons/bootstrap-icons.css','https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css')) ?>">

<!-- Premium Google Fonts matching landing page -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(appUrl('assets/css/public-portal.css')) ?>">
<link rel="stylesheet" href="<?= e(appUrl('assets/css/cepfms-operational.css')) ?>">
</head>
<body>

<header class="public-topbar">
<a href="<?= e(appUrl('public/index.php')) ?>" class="public-brand">
<img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City of Manila Seal" class="public-brand-logo">
<div>
    <strong>Manila Citizen Engagement Portal</strong>
    <small>City Council of Manila · Official Civic Feedback Channel</small>
</div>
</a>
<nav>
<a href="#submit">Submit</a><a href="#track">Track</a><a href="#process">How It Works</a>
<a href="<?= e(appUrl('login.php')) ?>" class="public-staff-link"><i class="bi bi-person-lock"></i> Staff Sign In</a>
</nav>
</header>

<main>
<section class="public-hero">
<div class="public-hero-copy">
<div class="public-eyebrow"><i class="bi bi-megaphone"></i> One official channel for civic feedback</div>
<h1>Share concerns, ideas, proposals, and community issues <span class="highlight-gold">in one place.</span></h1>
<p>Submit feedback directly to the legislative management platform and keep your private tracking token so you can follow public status updates without exposing personal information.</p>
<div class="public-hero-actions"><a href="#submit" class="btn btn-warning"><i class="bi bi-send"></i> Start a Submission</a><a href="#track" class="btn btn-outline-light"><i class="bi bi-search"></i> Track a Reference</a></div>
</div>
<div class="public-hero-card">
<div><span><i class="bi bi-shield-check"></i></span><div><strong>Private Tracking</strong><small>Reference number plus private token.</small></div></div>
<div><span><i class="bi bi-person-check"></i></span><div><strong>Accountable</strong><small>Records are categorized and routed.</small></div></div>
<div><span><i class="bi bi-reply-all"></i></span><div><strong>Transparent</strong><small>Public-visible updates are preserved.</small></div></div>
</div>
</section>

<?php if(!$foundationReady): ?>
<section class="public-section"><div class="alert alert-warning"><strong>Citizen submission is temporarily unavailable.</strong> The CEPFMS database foundation has not been fully installed yet.</div></section>
<?php endif; ?>

<section class="public-section" id="submit">
<div class="public-section-heading"><span>Citizen Services</span><h2>Submit feedback, a proposal, or a complaint</h2><p>Choose the correct record type so staff can validate, categorize, route and respond consistently.</p></div>

<div class="public-type-switch">
<button type="button" class="active" data-type="Feedback"><strong><i class="bi bi-chat-square-text"></i> Public Feedback</strong><small>Comments, observations and service feedback.</small></button>
<button type="button" data-type="Proposal"><strong><i class="bi bi-lightbulb"></i> Proposal / Suggestion</strong><small>Policy ideas, projects and improvement proposals.</small></button>
<button type="button" data-type="Complaint"><strong><i class="bi bi-exclamation-diamond"></i> Complaint / Issue</strong><small>Problems requiring assignment, updates and resolution.</small></button>
</div>

<form id="publicSubmissionForm" class="public-form-card" enctype="multipart/form-data">
<?= csrfField() ?>
<input type="hidden" name="submission_type" id="submissionType" value="Feedback">

<div class="row g-3">
<div class="col-md-8"><label class="form-label">Title / Subject *</label><input class="form-control" name="title" maxlength="255" required></div>
<div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="">Not sure / staff will classify</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" data-category-type="<?= e($c['category_type']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Summary</label><input class="form-control" name="summary" maxlength="500" placeholder="Short summary of your concern or idea"></div>
<div class="col-12"><label class="form-label">Full Details *</label><textarea class="form-control" name="details" rows="6" required></textarea></div>

<div class="col-md-4"><label class="form-label">Location / Landmark</label><input class="form-control" name="location_text"></div>
<div class="col-md-4"><label class="form-label">District</label><input class="form-control" name="district"></div>
<div class="col-md-4"><label class="form-label">Barangay</label><input class="form-control" name="barangay"></div>

<div class="type-fields feedback-fields col-12">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Feedback Kind</label><select class="form-select" name="feedback_kind"><option>General Feedback</option><option>Service Experience</option><option>Policy Comment</option><option>Legislative Comment</option><option>Community Observation</option></select></div>
<div class="col-md-4"><label class="form-label">Service Area</label><input class="form-control" name="service_area"></div>
<div class="col-md-4"><label class="form-label">Optional Rating</label><select class="form-select" name="citizen_rating"><option value="">No rating</option><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Fair</option><option value="2">2 - Poor</option><option value="1">1 - Very Poor</option></select></div>
<div class="col-12"><label class="form-label">Desired Outcome</label><textarea class="form-control" name="desired_outcome" rows="2"></textarea></div>
</div>
</div>

<div class="type-fields proposal-fields col-12 d-none">
<div class="row g-3">
<div class="col-12"><label class="form-label">Problem / Opportunity</label><textarea class="form-control" name="problem_statement" rows="3"></textarea></div>
<div class="col-12"><label class="form-label">Proposed Solution</label><textarea class="form-control" name="proposed_solution" rows="3"></textarea></div>
<div class="col-md-8"><label class="form-label">Expected Public Benefit</label><textarea class="form-control" name="expected_public_benefit" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Estimated Scope</label><select class="form-select" name="estimated_scope"><option value="">Not sure</option><option>Barangay</option><option>District</option><option>Citywide</option><option>Legislative / Policy</option></select></div>
</div>
</div>

<div class="type-fields complaint-fields col-12 d-none">
<div class="row g-3">
<div class="col-md-5"><label class="form-label">Affected Service</label><input class="form-control" name="affected_service"></div>
<div class="col-md-4"><label class="form-label">Incident Date / Time</label><input type="datetime-local" class="form-control" name="incident_datetime"></div>
<div class="col-md-3"><label class="form-label">Urgency</label><select class="form-select" name="urgency_level"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select></div>
</div>
</div>

<div class="col-md-4"><label class="form-label">Your Name</label><input class="form-control citizen-contact" name="citizen_name"></div>
<div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control citizen-contact" name="citizen_email"></div>
<div class="col-md-4"><label class="form-label">Phone</label><input class="form-control citizen-contact" name="citizen_phone"></div>
<div class="col-md-4"><label class="form-label">Contact Preference</label><select class="form-select" name="contact_preference"><option>Portal</option><option>Email</option><option>Phone</option></select></div>
<div class="col-md-8"><label class="form-label">Supporting Evidence</label><input type="file" class="form-control" name="evidence" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.xlsx,.xls,.csv"><div class="form-text">Optional. Maximum 10 MB.</div></div>

<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="anonymous_flag" value="1" id="anonymousFlag"><label class="form-check-label" for="anonymousFlag">Submit anonymously (your name/email/phone will not be stored with this submission)</label></div></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="privacy_consent" value="1" id="privacyConsent" required><label class="form-check-label" for="privacyConsent">I consent to the collection and processing of the information I submit for official government response and record management. *</label></div></div>

<div class="col-12 d-flex justify-content-end"><button type="submit" id="publicSubmitButton" class="btn btn-primary btn-lg" <?= !$foundationReady?'disabled':'' ?>><i class="bi bi-send"></i> Submit to CEPFMS</button></div>
</div>
</form>

<div class="cef-public-success mt-3 d-none" id="submissionSuccess">
<strong>Save these tracking details.</strong>
<p class="mb-2">The private tracking token is shown only after submission. Keep it with your reference number.</p>
<div><small>Reference</small><br><code id="successReference"></code></div>
<div class="mt-2"><small>Private Tracking Token</small><br><code class="cef-token" id="successToken"></code></div>
<a class="btn btn-success btn-sm mt-3" href="#track">Track this submission</a>
</div>
</section>

<section class="public-section public-track-section" id="track">
<div class="public-section-heading"><span>Private Reference Tracking</span><h2>Track the progress of your submission</h2><p>For privacy, tracking requires both the public reference number and the private token issued when the submission was created.</p></div>
<form id="publicTrackForm" class="track-card">
<?= csrfField() ?>
<div class="row g-2">
<div class="col-md-5"><input class="form-control" name="reference_number" id="trackReference" placeholder="CEF-2026-0001" required></div>
<div class="col-md-5"><input class="form-control cef-token" name="tracking_token" id="trackToken" placeholder="Private tracking token" required></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Track</button></div>
</div>
</form>
<div id="trackResult" class="track-card mt-3 d-none"></div>
</section>

<section class="public-section" id="process">
<div class="public-section-heading"><span>How It Works</span><h2>From citizen submission to accountable action</h2></div>
<div class="public-service-grid">
<div class="public-service-card"><span><i class="bi bi-send-check"></i></span><div><strong>1. Submit</strong><p>Receive an official CEPFMS reference and private token.</p></div></div>
<div class="public-service-card"><span><i class="bi bi-shield-check"></i></span><div><strong>2. Validate</strong><p>Staff reviews completeness, relevance and possible duplicates.</p></div></div>
<div class="public-service-card"><span><i class="bi bi-diagram-3"></i></span><div><strong>3. Route & Act</strong><p>Validated records are assigned to the responsible office, committee or staff.</p></div></div>
<div class="public-service-card"><span><i class="bi bi-reply"></i></span><div><strong>4. Respond</strong><p>Public-visible updates and approved official responses become trackable.</p></div></div>
</div>
</section>
</main>

<footer class="public-footer">
<div class="d-flex align-items-center gap-3">
<img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City of Manila Seal" style="width:40px;height:40px;object-fit:contain;">
<div>
<strong class="d-block">City Council of Manila</strong>
<small>Citizen Engagement & Public Feedback Management System</small>
</div>
</div>
<div>&copy; <?= date('Y') ?> City Government of Manila. All rights reserved.</div>
</footer>

<script src="<?= e(vendorAsset('bootstrap/bootstrap.bundle.min.js','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(vendorAsset('sweetalert2/sweetalert2.all.min.js','https://cdn.jsdelivr.net/npm/sweetalert2@11')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('publicSubmissionForm');
 const typeInput=document.getElementById('submissionType');
 const typeButtons=[...document.querySelectorAll('[data-type]')];
 const contact=[...document.querySelectorAll('.citizen-contact')];

 function setType(type){
   typeInput.value=type;
   typeButtons.forEach(b=>b.classList.toggle('active',b.dataset.type===type));
   document.querySelector('.feedback-fields').classList.toggle('d-none',type!=='Feedback');
   document.querySelector('.proposal-fields').classList.toggle('d-none',type!=='Proposal');
   document.querySelector('.complaint-fields').classList.toggle('d-none',type!=='Complaint');

   const categorySelect=form.querySelector('[name=category_id]');
   [...categorySelect.options].forEach((option,index)=>{
     if(index===0)return;
     const categoryType=option.dataset.categoryType||'All';
     option.hidden=categoryType!=='All'&&categoryType!==type;
     option.disabled=option.hidden;
   });
   if(categorySelect.selectedOptions[0]?.disabled)categorySelect.value='';
 }
 typeButtons.forEach(b=>b.onclick=()=>setType(b.dataset.type));
 setType('Feedback');

 document.getElementById('anonymousFlag').onchange=function(){
   contact.forEach(x=>{x.disabled=this.checked;if(this.checked)x.value='';});
 };

 form.onsubmit=async e=>{
   e.preventDefault();

   const button=document.getElementById('publicSubmitButton');
   if(button)button.disabled=true;

   try{
     const response=await fetch('ajax_submit.php',{
       method:'POST',
       headers:{
         'X-Requested-With':'XMLHttpRequest',
         'Accept':'application/json'
       },
       body:new FormData(form)
     });

     const raw=await response.text();
     let r;

     try{
       r=JSON.parse(raw);
     }catch(parseError){
       console.error('CEPFMS submission returned non-JSON output:',raw);
       throw new Error('The server returned an invalid response.');
     }

     if(!response.ok||!r.success){
       Swal.fire(
         'Submission Error',
         r.message||'The submission could not be completed.',
         'error'
       );
       return;
     }

     document.getElementById('successReference').textContent=r.reference_number;
     document.getElementById('successToken').textContent=r.tracking_token;
     document.getElementById('trackReference').value=r.reference_number;
     document.getElementById('trackToken').value=r.tracking_token;
     document.getElementById('submissionSuccess').classList.remove('d-none');

     form.reset();

     // Anonymous submissions disable contact inputs. Re-enable them after reset.
     contact.forEach(x=>{
       x.disabled=false;
       x.value='';
     });

     setType('Feedback');

     window.location.hash='submissionSuccess';

     Swal.fire(
       'Submission Received',
       'Save your reference number and private tracking token.',
       'success'
     );
   }catch(err){
     console.error('CEPFMS public submission error:',err);
     Swal.fire(
       'Submission Error',
       'The submission could not be completed. Check the browser console and CEPFMS logs if this continues.',
       'error'
     );
   }finally{
     if(button)button.disabled=false;
   }
 };

 document.getElementById('publicTrackForm').onsubmit=async e=>{
   e.preventDefault();
   const result=document.getElementById('trackResult');
   try{
     const r=await fetch('ajax_track.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(e.currentTarget)}).then(x=>x.json());
     if(!r.success){result.classList.add('d-none');Swal.fire('Tracking',r.message,'warning');return;}
     const s=r.submission;
     const history=(r.history||[]).map(h=>`<div><strong>${escapeHtml(h.status||h.action)}</strong><small>${escapeHtml(h.details||'')}<br>${escapeHtml(h.created_at||'')}</small></div>`).join('');
     const response=r.response?`<div class="alert alert-success mt-3"><strong>${escapeHtml(r.response.subject)}</strong><p class="mb-1 mt-2">${escapeHtml(r.response.body)}</p><small>${escapeHtml(r.response.reference)} · ${escapeHtml(r.response.date||'')}</small></div>`:'';
     const followup=s.can_followup?`<div class="mt-3 border-top pt-3"><strong class="d-block mb-2">Send Additional Information</strong><textarea class="form-control mb-2" id="citizenFollowupMessage" rows="3" maxlength="5000" placeholder="Use this only for clarification or additional information about this same submission."></textarea><button class="btn btn-outline-primary btn-sm" type="button" id="btnCitizenFollowup">Send Follow-Up</button></div>`:`<div class="alert alert-secondary mt-3 mb-0">This citizen record is closed and no longer accepts additional follow-up.</div>`;
     result.innerHTML=`<div class="d-flex justify-content-between gap-3 flex-wrap"><div><span class="cef-code">${escapeHtml(s.reference_number)}</span><h4 class="mt-1">${escapeHtml(s.title)}</h4><div class="small text-muted">${escapeHtml(s.submission_type)} · ${escapeHtml(s.category)} · ${escapeHtml(s.priority)}</div></div><span class="cef-status good">${escapeHtml(s.status)}</span></div><hr><div class="track-history">${history||'<div><strong>Received</strong><small>No additional public update yet.</small></div>'}</div>${response}${followup}`;
     result.classList.remove('d-none');

     document.getElementById('btnCitizenFollowup')?.addEventListener('click',async()=>{
       const message=document.getElementById('citizenFollowupMessage').value.trim();
       if(!message){Swal.fire('Follow-Up','Enter your additional information first.','warning');return;}
       const fd=new FormData();
       fd.append('csrf_token',document.querySelector('#publicTrackForm [name=csrf_token]').value);
       fd.append('reference_number',document.getElementById('trackReference').value);
       fd.append('tracking_token',document.getElementById('trackToken').value);
       fd.append('message',message);
       const fr=await fetch('ajax_followup.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
       if(fr.success){await Swal.fire('Follow-Up Received',fr.message,'success');document.getElementById('publicTrackForm').dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}));}
       else Swal.fire('Follow-Up',fr.message,'error');
     });
   }catch(err){Swal.fire('Tracking Error','Unable to check this submission right now.','error');}
 };
 function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
});
</script>
</body>
</html>
