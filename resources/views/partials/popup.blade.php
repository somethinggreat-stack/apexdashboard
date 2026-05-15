<!-- ============ FULFILLMENT CALL CTA POPUP ============ -->
<div class="popup-overlay" id="popupOverlay">
  <div class="popup-card" id="popupCard">
    <button class="popup-close" id="popupClose" aria-label="Close">&times;</button>

    <!-- Progress dots -->
    <div class="popup-progress">
      <span class="popup-dot active" data-step="1"></span>
      <span class="popup-dot" data-step="2"></span>
      <span class="popup-dot" data-step="3"></span>
      <span class="popup-dot" data-step="4"></span>
    </div>

    <!-- Step 1: Active Client Load -->
    <div class="popup-step active" data-step="1">
      <div class="popup-badge">Hand off the dispute workload</div>
      <h3>Hand off the dispute workload.</h3>
      <p>Send 5 test clients. We'll run certified letters, bureau follow-up calls, CFPB / FTC documentation where appropriate, response monitoring, and a weekly client status report &mdash; so you can judge the quality before moving your full client base.</p>
      <div class="popup-options">
        <button class="popup-opt" data-value="1-25" onclick="popupSelect(this)">
          <span class="popup-opt-label">1 &ndash; 25 active clients</span>
          <span class="popup-opt-tag low">Lean</span>
        </button>
        <button class="popup-opt" data-value="26-75" onclick="popupSelect(this)">
          <span class="popup-opt-label">26 &ndash; 75 active clients</span>
          <span class="popup-opt-tag med">Growing</span>
        </button>
        <button class="popup-opt" data-value="76-200" onclick="popupSelect(this)">
          <span class="popup-opt-label">76 &ndash; 200 active clients</span>
          <span class="popup-opt-tag high">Scaling</span>
        </button>
        <button class="popup-opt" data-value="200+" onclick="popupSelect(this)">
          <span class="popup-opt-label">200+ active clients</span>
          <span class="popup-opt-tag high">High Volume</span>
        </button>
        <button class="popup-opt" data-value="just-starting" onclick="popupSelect(this)">
          <span class="popup-opt-label">Just starting my credit repair business</span>
          <span class="popup-opt-tag">New</span>
        </button>
      </div>
    </div>

    <!-- Step 2: Where You're Drowning -->
    <div class="popup-step" data-step="2">
      <div class="popup-badge">Where You're Drowning</div>
      <h3>Which part of fulfillment is eating your week?</h3>
      <p>We plug into the workflow you're already running &mdash; pick the bottleneck and we'll scope the handoff.</p>
      <div class="popup-options">
        <button class="popup-opt" data-value="letter-prep" onclick="popupSelect(this)">
          <span class="popup-opt-icon">&#9998;</span>
          <span class="popup-opt-label">Letter prep &amp; Round 2 escalation</span>
        </button>
        <button class="popup-opt" data-value="bureau-calls" onclick="popupSelect(this)">
          <span class="popup-opt-icon">&#9990;</span>
          <span class="popup-opt-label">Bureau follow-up calls (incl. Innovis &amp; small-bureau freezes)</span>
        </button>
        <button class="popup-opt" data-value="cfpb-ftc" onclick="popupSelect(this)">
          <span class="popup-opt-icon">&#9888;</span>
          <span class="popup-opt-label">CFPB / FTC complaint documentation</span>
        </button>
        <button class="popup-opt" data-value="weekly-reporting" onclick="popupSelect(this)">
          <span class="popup-opt-icon">&#9776;</span>
          <span class="popup-opt-label">Weekly client status reporting</span>
        </button>
        <button class="popup-opt" data-value="all-of-it" onclick="popupSelect(this)">
          <span class="popup-opt-icon">&#10070;</span>
          <span class="popup-opt-label">All of it &mdash; full white-label fulfillment</span>
        </button>
      </div>
    </div>

    <!-- Step 3: Timeline -->
    <div class="popup-step" data-step="3">
      <div class="popup-badge">Timeline</div>
      <h3>How fast do you want to start?</h3>
      <p>This helps us schedule the right fulfillment lead for your call.</p>
      <div class="popup-options">
        <button class="popup-opt" data-value="asap" onclick="popupSelect(this)">
          <span class="popup-opt-label">ASAP &mdash; my queue is on fire</span>
        </button>
        <button class="popup-opt" data-value="this-week" onclick="popupSelect(this)">
          <span class="popup-opt-label">This week</span>
        </button>
        <button class="popup-opt" data-value="this-month" onclick="popupSelect(this)">
          <span class="popup-opt-label">This month</span>
        </button>
        <button class="popup-opt" data-value="exploring" onclick="popupSelect(this)">
          <span class="popup-opt-label">Exploring &mdash; comparing fulfillment partners</span>
        </button>
      </div>
    </div>

    <!-- Step 4: Contact -->
    <div class="popup-step" data-step="4">
      <div class="popup-badge">Last Step</div>
      <h3>Where should the fulfillment lead reach you?</h3>
      <p>A fulfillment lead will scope your client load, your CRM, and a clean handoff plan &mdash; typically within one business day.</p>
      <div class="popup-form">
        <div class="popup-form-row">
          <div class="popup-field">
            <label for="firstName">First Name</label>
            <input type="text" id="firstName" name="firstName" placeholder="Your first name" required>
            <span class="popup-field-err">Required</span>
          </div>
          <div class="popup-field">
            <label for="lastName">Last Name</label>
            <input type="text" id="lastName" name="lastName" placeholder="Your last name" required>
            <span class="popup-field-err">Required</span>
          </div>
        </div>
        <div class="popup-field">
          <label for="email">Business Email</label>
          <input type="email" id="email" name="email" placeholder="owner@yourbusiness.com" required>
          <span class="popup-field-err">Enter a valid email</span>
        </div>
        <div class="popup-field">
          <label for="phone">Phone / WhatsApp</label>
          <input type="tel" id="phone" name="phone" placeholder="(555) 123-4567" required>
          <span class="popup-field-err">Enter a valid phone number</span>
        </div>
        <button class="popup-submit" onclick="popupSubmit()">
          <span>Request Fulfillment Call</span>
          <span class="popup-submit-arrow">&rarr;</span>
        </button>
        <div class="popup-trust">
          <span>White-Label Friendly</span>
          <span>&middot;</span>
          <span>US Business Hours</span>
          <span>&middot;</span>
          <span>Weekly Reporting</span>
        </div>
        <p style="font-size:10px;line-height:1.5;color:rgba(15,32,67,0.55);margin-top:12px;">Apex Growth Systems provides administrative credit repair fulfillment support for credit repair businesses. We are not a law firm and do not provide legal advice. Results vary. We do not guarantee score increases or removal of accurate/verifiable information.</p>
      </div>
    </div>

    <!-- Success -->
    <div class="popup-step" data-step="5">
      <div class="popup-success-icon">&#10003;</div>
      <h3>Request received.</h3>
      <p>A fulfillment lead will reach out within one business day to scope your client load, your CRM, and a clean handoff plan. Results vary &mdash; we do not guarantee removal of accurate or verifiable information.</p>
      <button class="popup-submit" onclick="popupDismiss()" style="margin-top: 24px;">
        <span>Maybe later</span>
      </button>
    </div>

  </div>
</div>

<style>
/* ============================================
   QUALIFYING POPUP
   ============================================ */
.popup-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 32, 67, 0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.4s cubic-bezier(0.22, 1, 0.36, 1), visibility 0.4s;
}
.popup-overlay.active {
  opacity: 1;
  visibility: visible;
}

.popup-card {
  background: #FFFFFF;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  padding: 40px 36px 36px;
  position: relative;
  box-shadow:
    0 40px 100px rgba(15, 32, 67, 0.25),
    0 0 0 1px rgba(226, 232, 240, 0.5);
  transform: translateY(30px) scale(0.96);
  transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
  overflow: hidden;
  max-height: 90vh;
  overflow-y: auto;
}
.popup-overlay.active .popup-card {
  transform: translateY(0) scale(1);
}

/* Decorative top accent */
.popup-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, #2196F3, #1A6FC4, #0F2043);
}

.popup-close {
  position: absolute;
  top: 16px; right: 16px;
  width: 36px; height: 36px;
  display: grid;
  place-items: center;
  font-size: 22px;
  color: #94A3B8;
  border-radius: 50%;
  transition: all 0.3s;
  background: transparent;
  border: none;
  cursor: pointer;
  z-index: 5;
}
.popup-close:hover {
  background: #F1F5F9;
  color: #0F2043;
}

/* Progress dots */
.popup-progress {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-bottom: 28px;
}
.popup-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #E2E8F0;
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.popup-dot.active {
  background: #1A6FC4;
  width: 24px;
  border-radius: 4px;
  box-shadow: 0 0 10px rgba(26, 111, 196, 0.3);
}
.popup-dot.done {
  background: #1A6FC4;
}

/* Steps */
.popup-step {
  display: none;
  animation: popupStepIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
.popup-step.active {
  display: block;
}
@keyframes popupStepIn {
  from { opacity: 0; transform: translateX(20px); }
  to { opacity: 1; transform: translateX(0); }
}

.popup-badge {
  display: inline-block;
  font-family: 'IBM Plex Mono', 'SF Mono', monospace;
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #1A6FC4;
  background: #DBEAFE;
  padding: 5px 12px;
  border-radius: 100px;
  margin-bottom: 16px;
}

.popup-step h3 {
  font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 22px;
  font-weight: 600;
  color: #0F2043;
  letter-spacing: -0.02em;
  margin-bottom: 8px;
  line-height: 1.25;
}

.popup-step p {
  font-size: 14px;
  color: #64748B;
  margin-bottom: 24px;
  line-height: 1.6;
}

/* Option buttons */
.popup-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.popup-opt {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
  padding: 14px 18px;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  text-align: left;
  font-family: inherit;
  color: #0F2043;
}
.popup-opt:hover {
  border-color: #1A6FC4;
  background: #FFFFFF;
  box-shadow: 0 4px 16px rgba(26, 111, 196, 0.1);
  transform: translateX(4px);
}
.popup-opt.selected {
  border-color: #1A6FC4;
  background: #EFF6FF;
  box-shadow: 0 0 0 3px rgba(26, 111, 196, 0.12);
}

.popup-opt-icon {
  width: 36px; height: 36px;
  display: grid;
  place-items: center;
  background: #DBEAFE;
  color: #1A6FC4;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  flex-shrink: 0;
}

.popup-opt-label {
  font-size: 14px;
  font-weight: 500;
  flex: 1;
}

.popup-opt-tag {
  font-family: 'IBM Plex Mono', 'SF Mono', monospace;
  font-size: 10px;
  letter-spacing: 0.05em;
  padding: 3px 8px;
  border-radius: 4px;
  background: #F1F5F9;
  color: #64748B;
  font-weight: 500;
}
.popup-opt-tag.high { background: #FEF2F2; color: #DC2626; }
.popup-opt-tag.med { background: #FFFBEB; color: #D97706; }
.popup-opt-tag.low { background: #F0FDF4; color: #16A34A; }

/* Form */
.popup-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.popup-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.popup-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  position: relative;
}
.popup-field label {
  font-family: 'IBM Plex Mono', 'SF Mono', monospace;
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #64748B;
}
.popup-field input {
  width: 100%;
  padding: 13px 16px;
  font-family: 'Geist', -apple-system, sans-serif;
  font-size: 14px;
  color: #0F2043;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  outline: none;
  transition: border-color 0.3s, box-shadow 0.3s;
  -webkit-appearance: none;
}
.popup-field input:focus {
  border-color: #1A6FC4;
  box-shadow: 0 0 0 3px rgba(26, 111, 196, 0.12);
  background: #FFFFFF;
}
.popup-field input::placeholder { color: #94A3B8; }
.popup-field-err {
  font-size: 11px;
  color: #DC2626;
  display: none;
  font-weight: 500;
}
.popup-field.has-error input {
  border-color: #DC2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
}
.popup-field.has-error .popup-field-err { display: block; }

@keyframes popupShake {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-5px); }
  40% { transform: translateX(5px); }
  60% { transform: translateX(-3px); }
  80% { transform: translateX(3px); }
}
.popup-field.shake { animation: popupShake 0.4s ease; }

/* Submit button */
.popup-submit {
  width: 100%;
  padding: 16px 24px;
  background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 50%, #0F2043 100%);
  color: #FFFFFF;
  border: none;
  border-radius: 10px;
  font-family: 'Geist', -apple-system, sans-serif;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.02em;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
  box-shadow: 0 8px 24px rgba(26, 111, 196, 0.25);
  position: relative;
  overflow: hidden;
}
.popup-submit::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.6s ease;
}
.popup-submit:hover::before { left: 100%; }
.popup-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 40px rgba(26, 111, 196, 0.35);
}
.popup-submit-arrow {
  transition: transform 0.3s ease;
}
.popup-submit:hover .popup-submit-arrow {
  transform: translateX(4px);
}

.popup-trust {
  display: flex;
  justify-content: center;
  gap: 8px;
  font-size: 11px;
  color: #94A3B8;
  margin-top: 4px;
}

/* Success state */
.popup-success-icon {
  width: 64px; height: 64px;
  margin: 0 auto 20px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #2196F3, #1A6FC4);
  color: #FFFFFF;
  border-radius: 50%;
  font-size: 28px;
  font-weight: 700;
  box-shadow: 0 8px 30px rgba(26, 111, 196, 0.25);
  animation: popupSuccessPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes popupSuccessPop {
  from { transform: scale(0); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

/* Responsive */
@media (max-width: 580px) {
  .popup-card { padding: 32px 24px 28px; max-width: 100%; border-radius: 12px; }
  .popup-step h3 { font-size: 19px; }
  .popup-form-row { grid-template-columns: 1fr; }
  .popup-trust { flex-wrap: wrap; justify-content: center; }
  .popup-opt { padding: 12px 14px; }
}
</style>

<script>
(function(){
  /* ============================================
     POPUP CONTROLLER
     ============================================ */
  var overlay = document.getElementById('popupOverlay');
  var card = document.getElementById('popupCard');
  var currentStep = 1;
  var totalSteps = 4;
  var popupData = {};
  var popupShown = false;
  var POPUP_STORAGE_KEY = 'apex_popup_shown';

  // Don't show if already submitted in this session
  if (sessionStorage.getItem(POPUP_STORAGE_KEY)) return;

  // Show popup after 8 seconds OR on scroll past 40%
  var delayTimer = setTimeout(showPopup, 8000);

  function onScrollTrigger() {
    var scrollPct = window.scrollY / (document.body.scrollHeight - window.innerHeight);
    if (scrollPct > 0.35 && !popupShown) {
      showPopup();
    }
  }
  window.addEventListener('scroll', onScrollTrigger, { passive: true });

  // Exit intent (desktop only)
  if (window.matchMedia('(hover: hover)').matches) {
    document.addEventListener('mouseout', function(e) {
      if (e.clientY < 5 && !popupShown) {
        showPopup();
      }
    });
  }

  function showPopup() {
    if (popupShown) return;
    popupShown = true;
    clearTimeout(delayTimer);
    window.removeEventListener('scroll', onScrollTrigger);
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  window.popupDismiss = function() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  };

  // Close button
  document.getElementById('popupClose').addEventListener('click', popupDismiss);

  // Click outside
  overlay.addEventListener('click', function(e) {
    if (e.target === overlay) popupDismiss();
  });

  // Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && popupShown) popupDismiss();
  });

  /* ============================================
     STEP NAVIGATION
     ============================================ */
  function goToStep(step) {
    // Hide current
    var currentEl = card.querySelector('.popup-step.active');
    if (currentEl) currentEl.classList.remove('active');

    // Show new
    var nextEl = card.querySelector('.popup-step[data-step="' + step + '"]');
    if (nextEl) {
      nextEl.classList.remove('active');
      // Force reflow for animation
      void nextEl.offsetWidth;
      nextEl.classList.add('active');
    }

    // Update dots
    card.querySelectorAll('.popup-dot').forEach(function(dot) {
      var s = parseInt(dot.dataset.step);
      dot.classList.remove('active', 'done');
      if (s === step) dot.classList.add('active');
      else if (s < step) dot.classList.add('done');
    });

    currentStep = step;
  }

  /* ============================================
     OPTION SELECT (auto-advance)
     ============================================ */
  window.popupSelect = function(btn) {
    var step = parseInt(btn.closest('.popup-step').dataset.step);
    var value = btn.dataset.value;

    // Mark selected
    btn.closest('.popup-options').querySelectorAll('.popup-opt').forEach(function(o) {
      o.classList.remove('selected');
    });
    btn.classList.add('selected');

    // Save data
    if (step === 1) popupData.score = value;
    if (step === 2) popupData.goal = value;
    if (step === 3) popupData.urgency = value;

    // Auto-advance after short delay
    setTimeout(function() {
      goToStep(step + 1);
    }, 300);
  };

  /* ============================================
     FORM SUBMIT
     ============================================ */
  window.popupSubmit = function() {
    var valid = true;

    var firstName = document.getElementById('firstName');
    var lastName = document.getElementById('lastName');
    var email = document.getElementById('email');
    var phone = document.getElementById('phone');

    // Reset errors
    [firstName, lastName, email, phone].forEach(function(el) {
      el.closest('.popup-field').classList.remove('has-error', 'shake');
    });

    if (!firstName.value.trim()) {
      setFieldError(firstName);
      valid = false;
    }
    if (!lastName.value.trim()) {
      setFieldError(lastName);
      valid = false;
    }
    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      setFieldError(email);
      valid = false;
    }
    var digits = phone.value.replace(/\D/g, '');
    if (digits.length < 10) {
      setFieldError(phone);
      valid = false;
    }

    if (!valid) return;

    popupData.firstName = firstName.value.trim();
    popupData.lastName = lastName.value.trim();
    popupData.email = email.value.trim();
    popupData.phone = phone.value.trim();
    popupData.source_page = window.location.pathname;

    var submitBtn = card.querySelector('.popup-submit');
    var originalLabel = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Saving&hellip;</span>';

    fetch('{{ route('leads.store') }}', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(popupData)
    }).then(function(res) {
      if (!res.ok) throw new Error('Request failed: ' + res.status);
      return res.json();
    }).then(function() {
      sessionStorage.setItem(POPUP_STORAGE_KEY, '1');
      goToStep(5);
    }).catch(function(err) {
      console.error('Lead submit failed', err);
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
      var emailField = email.closest('.popup-field');
      emailField.classList.add('shake');
      setTimeout(function() { emailField.classList.remove('shake'); }, 400);
    });
  };

  function setFieldError(input) {
    var field = input.closest('.popup-field');
    field.classList.add('has-error', 'shake');
    setTimeout(function() { field.classList.remove('shake'); }, 400);
  }

  // Clear errors on input
  card.querySelectorAll('.popup-field input').forEach(function(input) {
    input.addEventListener('input', function() {
      this.closest('.popup-field').classList.remove('has-error');
    });
  });
})();
</script>
