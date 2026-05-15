<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Client Intake Form</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: #0F2043;
    line-height: 1.6;
    min-height: 100vh;
    padding: 32px 16px;
}
.intake-shell {
    max-width: 720px;
    margin: 0 auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(15,32,67,0.08), 0 0 0 1px rgba(226,232,240,0.6);
    overflow: hidden;
}
.intake-head {
    text-align: center;
    padding: 40px 32px 32px;
    border-bottom: 1px solid #E2E8F0;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}
.intake-logo { max-height: 90px; max-width: 320px; width: auto; margin: 0 auto 14px; display: block; }
.intake-brand { font-size: 22px; font-weight: 600; letter-spacing: -0.01em; color: #0F2043; margin-bottom: 6px; }
.intake-subtitle { font-size: 13px; color: #64748b; letter-spacing: 0.01em; }

.intake-body { padding: 32px; }

.intake-section { margin-bottom: 28px; }
.intake-section + .intake-section { padding-top: 24px; border-top: 1px solid #E2E8F0; }
.intake-section h3 {
    font-size: 12px; font-weight: 600; letter-spacing: 0.18em;
    text-transform: uppercase; color: #1A6FC4;
    margin-bottom: 16px;
}

.field { margin-bottom: 16px; }
.field label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #1E3A5F; }
.field label .req { color: #DC2626; margin-left: 2px; }
.field input[type=text],
.field input[type=email],
.field input[type=tel],
.field input[type=date],
.field input[type=password],
.field select {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
    color: #0F2043;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.field input:focus, .field select:focus {
    outline: none;
    border-color: #1A6FC4;
    box-shadow: 0 0 0 3px rgba(26,111,196,0.12);
}
.field input[type=file] {
    width: 100%;
    padding: 10px;
    border: 1px dashed #CBD5E1;
    border-radius: 8px;
    background: #f8fafc;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
}
.field .hint { font-size: 11px; color: #94A3B8; margin-top: 4px; }
.field .err { font-size: 12px; color: #DC2626; margin-top: 4px; }

.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
@media (max-width: 560px) { .row-2, .row-3 { grid-template-columns: 1fr; } }

.submit-row { margin-top: 24px; }
.submit-btn {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 50%, #0F2043 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    font: inherit;
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 12px 32px rgba(26,111,196,0.28);
    transition: transform 0.2s, box-shadow 0.2s;
}
.submit-btn:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(26,111,196,0.36); }
.submit-btn:disabled { opacity: 0.6; cursor: wait; transform: none; }

.alert {
    margin-bottom: 20px;
    padding: 12px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    border-radius: 8px;
    font-size: 13px;
}
.alert ul { margin: 0; padding-left: 18px; }

.intake-footer {
    text-align: center;
    padding: 18px 32px 28px;
    font-size: 11px;
    color: #94A3B8;
    line-height: 1.7;
    background: #f8fafc;
    border-top: 1px solid #E2E8F0;
}
.intake-secure {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; color: #16A34A; font-weight: 500;
    margin-bottom: 6px;
    letter-spacing: 0.04em;
}
.intake-secure::before {
    content: ''; width: 8px; height: 8px; border-radius: 50%; background: #16A34A;
    box-shadow: 0 0 8px rgba(22,163,74,0.4);
}
</style>
</head>
<body>
<main class="intake-shell">

    <header class="intake-head">
        @if ($logoUrl = $client->intakeLogoUrl())
            <img src="{{ $logoUrl }}" alt="" class="intake-logo">
        @endif
        <div class="intake-brand">{{ $client->intakeDisplayName() }}</div>
        <div class="intake-subtitle">Secure Client Intake Form</div>
    </header>

    <div class="intake-body">

        @if ($errors->any())
            <div class="alert">
                <strong>Please correct the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('intake.store', $client->intake_token) }}" enctype="multipart/form-data" autocomplete="off" id="intakeForm">
            @csrf

            <div class="intake-section">
                <h3>Personal Information</h3>
                <div class="row-3">
                    <div class="field">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100">
                    </div>
                    <div class="field">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="{{ old('middle_name') }}" maxlength="100">
                    </div>
                    <div class="field">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100">
                    </div>
                </div>
                <div class="row-2">
                    <div class="field">
                        <label>Suffix <span class="req">*</span></label>
                        <select name="suffix" required>
                            @foreach (['None','Jr.','Sr.','I','II','III','IV','V'] as $s)
                                <option value="{{ $s }}" @selected(old('suffix') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Date of Birth <span class="req">*</span></label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                    </div>
                </div>
                <div class="row-2">
                    <div class="field">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255">
                    </div>
                    <div class="field">
                        <label>Phone Number <span class="req">*</span></label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30">
                    </div>
                </div>
                <div class="field">
                    <label>Social Security Number <span class="req">*</span></label>
                    <input type="text" name="ssn" value="{{ old('ssn') }}" required maxlength="32" placeholder="XXX-XX-XXXX" autocomplete="off">
                    <div class="hint">Stored encrypted at rest.</div>
                </div>
            </div>

            <div class="intake-section">
                <h3>Identity Documents</h3>
                <div class="field">
                    <label>Government-Issued Photo ID <span class="req">*</span></label>
                    <input type="file" name="photo_id" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="hint">PDF, JPG, or PNG up to 10 MB.</div>
                </div>
                <div class="field">
                    <label>Proof of Address <span class="req">*</span></label>
                    <input type="file" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="hint">Utility bill, lease, or bank statement.</div>
                </div>
                <div class="field">
                    <label>SSN Picture (optional)</label>
                    <input type="file" name="ssn_picture" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="hint">Optional. Only if your provider requested it.</div>
                </div>
            </div>

            <div class="intake-section">
                <h3>Credit Monitoring</h3>
                <div class="row-2">
                    <div class="field">
                        <label>Service Name <span class="req">*</span></label>
                        <input type="text" name="credit_monitoring_name" value="{{ old('credit_monitoring_name') }}" required maxlength="100" placeholder="e.g. IdentityIQ, SmartCredit">
                    </div>
                    <div class="field">
                        <label>Username / Email <span class="req">*</span></label>
                        <input type="text" name="credit_monitoring_username" value="{{ old('credit_monitoring_username') }}" required maxlength="255" autocomplete="off">
                    </div>
                </div>
                <div class="row-2">
                    <div class="field">
                        <label>Password <span class="req">*</span></label>
                        <input type="text" name="credit_monitoring_password" value="{{ old('credit_monitoring_password') }}" required maxlength="255" autocomplete="off">
                        <div class="hint">Stored encrypted at rest.</div>
                    </div>
                    <div class="field">
                        <label>Security Question Answer <span class="req">*</span></label>
                        <input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}" required maxlength="255" autocomplete="off">
                        <div class="hint">Stored encrypted at rest.</div>
                    </div>
                </div>
            </div>

            <div class="submit-row">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span>Submit Intake Form</span>
                </button>
            </div>
        </form>
    </div>

    <footer class="intake-footer">
        <div class="intake-secure">SECURE · ENCRYPTED · NEVER SHARED</div>
        <div>Sensitive fields are encrypted at rest. Documents are stored in private storage and only accessible by the team handling your file.</div>
    </footer>
</main>

<script>
(function () {
    var form = document.getElementById('intakeForm');
    var btn  = document.getElementById('submitBtn');
    if (!form || !btn) return;
    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.innerHTML = '<span>Submitting&hellip;</span>';
    });
})();
</script>
</body>
</html>
