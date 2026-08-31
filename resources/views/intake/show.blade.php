<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/Images/logo.png">
    <link rel="apple-touch-icon" href="/Images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    @php
        // Per-BO branding: opt-in by setting intake_display_name. Any BO without
        // it keeps the generic form exactly as before.
        $brand    = $client->intake_display_name;
        $brandLogo = $client->intakeLogoUrl();
    @endphp
    <title>{{ $brand ? $brand . ' — Client Intake' : 'Secure Client Intake' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin:0; color:#0f172a;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            background:#0b1220;
            background-image:
                radial-gradient(900px circle at 12% 8%, rgba(37,99,235,.22), transparent 42%),
                radial-gradient(800px circle at 88% 92%, rgba(56,189,248,.16), transparent 44%),
                linear-gradient(160deg,#0b1220 0%,#0f2140 55%,#0b1220 100%);
            min-height:100vh;
        }
        .wrap { max-width:760px; margin:0 auto; padding:34px 16px 70px; }

        .head { text-align:center; color:#fff; padding:10px 0 22px; }
        .head-badge { display:inline-block; margin-bottom:14px; padding:7px 16px; border-radius:999px;
            font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
            color:#bfdbfe; background:rgba(37,99,235,.16); border:1px solid rgba(96,165,250,.35); }
        .head h1 { margin:0 0 8px; font-size:30px; letter-spacing:-.02em; }
        .head h1.branded { text-transform:uppercase; letter-spacing:.03em; font-size:27px; font-weight:800; }
        .head p { margin:0; color:#cbd5e1; font-size:14.5px; }
        .brand-logo { display:block; margin:0 auto 16px; max-height:70px; max-width:230px; width:auto;
            filter:drop-shadow(0 8px 20px rgba(0,0,0,.35)); }
        @media (max-width:600px){
            .head h1.branded { font-size:22px; }
            .wrap { padding:24px 13px 56px; }
            /* 16px inputs stop iOS from auto-zooming the whole page on focus. */
            input, select { font-size:16px; }
            .row { gap:0; }
        }

        .trust { display:flex; flex-wrap:wrap; justify-content:center; gap:10px; margin:16px 0 4px; }
        .trust span { display:inline-flex; align-items:center; gap:6px; color:#dbeafe; font-size:11.5px; font-weight:600;
            background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); padding:6px 11px; border-radius:999px; }

        .card { position:relative; background:#fff; border-radius:20px; padding:30px 30px 28px; box-shadow:0 30px 70px rgba(0,0,0,.38); overflow:hidden; }
        .card::before { content:''; position:absolute; top:0; left:0; right:0; height:5px; background:linear-gradient(90deg,#2563eb,#38bdf8,#22d3ee); }

        .sec-title { display:flex; align-items:center; gap:10px; font-size:12.5px; text-transform:uppercase; letter-spacing:.09em;
            color:#1e293b; font-weight:800; margin:28px 0 14px; }
        .sec-title:first-of-type { margin-top:6px; }
        .sec-title::before { content:''; width:20px; height:4px; border-radius:4px; background:linear-gradient(90deg,#2563eb,#38bdf8); }
        .sec-title::after { content:''; flex:1; height:1px; background:#eef2f7; }

        .row { display:flex; gap:14px; flex-wrap:wrap; }
        .fg { margin-bottom:14px; flex:1; min-width:190px; }
        label { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
        label .opt { color:#94a3b8; font-weight:500; }

        input, select {
            width:100%; padding:12px 14px; border:1px solid #cbd5e1; border-radius:11px; font-size:14.5px;
            background:#f8fafc; transition:border-color .15s, box-shadow .15s, background .15s; color:#0f172a;
        }
        input:focus, select:focus { outline:none; border-color:#2563eb; background:#fff; box-shadow:0 0 0 4px rgba(37,99,235,.14); }

        input[type=file] { padding:9px 12px; background:#fff; cursor:pointer; }
        input[type=file]::file-selector-button {
            margin-right:12px; border:0; border-radius:8px; padding:8px 14px; cursor:pointer;
            background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; font-weight:700; font-size:13px;
        }
        input[type=file]::file-selector-button:hover { background:#1d4ed8; }

        .hint { font-size:12px; color:#64748b; margin-top:5px; }
        .impact { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:9px 12px; border-radius:10px; font-size:12.5px; margin-top:7px; }
        .enroll-btn { display:inline-block; margin-top:9px; padding:11px 18px; border-radius:10px; text-decoration:none;
            background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; font-size:14px; font-weight:800; box-shadow:0 8px 18px rgba(22,163,74,.28); }
        .enroll-btn:hover { background:linear-gradient(135deg,#15803d,#16a34a); }

        .errors { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:12px 14px; margin-bottom:18px; font-size:13px; }
        .errors ul { margin:6px 0 0; padding-left:18px; }

        .submit { width:100%; margin-top:26px; padding:15px; border:0; border-radius:12px; cursor:pointer;
            background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; font-size:15.5px; font-weight:800;
            box-shadow:0 12px 26px rgba(37,99,235,.36); transition:transform .1s, box-shadow .15s; }
        .submit:hover { transform:translateY(-1px); box-shadow:0 16px 32px rgba(37,99,235,.46); }
        .secure { text-align:center; color:#94a3b8; font-size:12px; margin-top:16px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        @if ($brandLogo)
            <img src="{{ $brandLogo }}" alt="{{ $brand ?: $client->business_name }}" class="brand-logo">
        @endif
        <div class="head-badge">🔒 Secure Client Onboarding</div>
        @if ($brand)
            <h1 class="branded">{{ $brand }} Client Intake</h1>
            <p>Welcome to {{ $brand }}. Your information is encrypted and used only to work on your credit file.</p>
        @else
            <h1>Secure Client Intake</h1>
            <p>Your information is encrypted and used only to work on your credit file.</p>
        @endif
        <div class="trust">
            <span>🔒 Bank-grade encryption</span>
            <span>🛡️ Private &amp; secure</span>
            <span>📄 Documents stored privately</span>
        </div>
    </div>

    <div class="card">
        @if ($errors->any())
            <div class="errors">
                <strong>Please fix the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('intake.store', ['token' => $token]) }}" enctype="multipart/form-data">
            @csrf

            <div class="sec-title">Your Details</div>
            <div class="row">
                <div class="fg"><label>First Name</label><input type="text" name="first_name" value="{{ old('first_name') }}" required></div>
                <div class="fg"><label>Middle Name <span class="opt">(optional)</span></label><input type="text" name="middle_name" value="{{ old('middle_name') }}"></div>
            </div>
            <div class="row">
                <div class="fg"><label>Last Name</label><input type="text" name="last_name" value="{{ old('last_name') }}" required></div>
                <div class="fg">
                    <label>Suffix <span class="opt">(optional)</span></label>
                    <select name="suffix">
                        @foreach (['None','Jr.','Sr.','I','II','III','IV','V'] as $s)
                            <option value="{{ $s }}" @selected(old('suffix') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="fg"><label>Email Address</label><input type="email" name="email" value="{{ old('email') }}" required></div>
                <div class="fg"><label>Phone</label><input type="text" name="phone" value="{{ old('phone') }}" required></div>
            </div>
            <div class="row">
                <div class="fg"><label>Date of Birth</label><input type="text" name="date_of_birth" id="dobInput" value="{{ old('date_of_birth') }}" inputmode="numeric" autocomplete="bday" placeholder="MM/DD/YYYY" maxlength="10" pattern="(0[1-9]|1[0-2])/(0[1-9]|[12]\d|3[01])/(19|20)\d\d" title="Enter your date of birth as MM/DD/YYYY" required></div>
                <div class="fg"><label>Full SSN</label><input type="text" name="ssn" inputmode="numeric" placeholder="XXX-XX-XXXX" required></div>
            </div>

            <div class="sec-title">Mailing Address</div>
            <div class="row">
                <div class="fg" style="flex:2 1 100%;"><label>Street Address</label><input type="text" name="current_address" value="{{ old('current_address') }}" required></div>
            </div>
            <div class="row">
                <div class="fg"><label>Apt / Suite <span class="opt">(optional)</span></label><input type="text" name="address_line2" value="{{ old('address_line2') }}"></div>
                <div class="fg"><label>City</label><input type="text" name="city" value="{{ old('city') }}" required></div>
            </div>
            <div class="row">
                <div class="fg"><label>State</label><input type="text" name="state" value="{{ old('state') }}" required></div>
                <div class="fg"><label>Zip Code</label><input type="text" name="zipcode" value="{{ old('zipcode') }}" required></div>
            </div>

            <div class="sec-title">Documents</div>
            <div class="fg"><label>Driver's License</label><input type="file" name="drivers_license" accept=".pdf,.jpg,.jpeg,.png,.webp" required><div class="hint">PDF or image, up to 10 MB.</div></div>
            <div class="fg">
                <label>Social Security Card <span class="opt">(optional)</span></label>
                <input type="file" name="ssn_card" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <div class="impact">Providing this helps get stronger results on your file.</div>
            </div>
            <div class="fg"><label>Proof of Address</label><input type="file" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png,.webp" required><div class="hint">Utility bill, bank statement, or lease — up to 10 MB.</div></div>

            <div class="sec-title">Credit Monitoring</div>
            @if ($client->intake_monitoring_provider)
                <input type="hidden" name="credit_monitoring_name" value="{{ $client->intake_monitoring_provider }}">
                <div class="fg">
                    <label>Credit Monitoring Provider</label>
                    <input type="text" value="{{ $client->intake_monitoring_provider }}" readonly style="background:#f1f5f9;">
                    @if ($client->intake_monitoring_enroll_url)
                        <a href="{{ $client->intake_monitoring_enroll_url }}" target="_blank" rel="noopener" class="enroll-btn">Get Credit Monitoring →</a>
                    @endif
                    <div class="hint">Sign up with {{ $client->intake_monitoring_provider }} using the button above, then enter your login email &amp; password below.</div>
                </div>
            @else
                <div class="fg"><label>Credit Monitoring Provider</label><input type="text" name="credit_monitoring_name" value="{{ old('credit_monitoring_name') }}" placeholder="e.g. IdentityIQ, MyScoreIQ, SmartCredit" required></div>
            @endif
            <div class="row">
                <div class="fg"><label>Credit Monitoring Email</label><input type="text" name="credit_monitoring_username" value="{{ old('credit_monitoring_username') }}" required></div>
                <div class="fg"><label>Credit Monitoring Password</label><input type="text" name="credit_monitoring_password" required></div>
            </div>
            @if ($client->intake_security_extra)
                <div class="fg"><label>Security Question</label><input type="text" name="credit_monitoring_security_question" value="{{ old('credit_monitoring_security_question') }}" required></div>
                <div class="fg"><label>Security Answer</label><input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}" required></div>
                <div class="fg"><label>What is your 4-digit PIN?</label><input type="text" name="credit_monitoring_pin" value="{{ old('credit_monitoring_pin') }}" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="0000" required></div>
            @else
                <div class="fg"><label>Security Question Answer <span class="opt">(optional)</span></label><input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}"></div>
            @endif

            <button type="submit" class="submit">Submit Securely</button>
            <div class="secure">🔒 Encrypted submission · Your documents are stored privately.</div>
        </form>
    </div>
</div>
<script>
(function () {
    // Date of Birth: type digits and the slashes appear (MM/DD/YYYY); pasting a
    // slashed date adopts the same format. Value posts as MM/DD/YYYY, which the
    // server parses to a real date.
    var el = document.getElementById('dobInput');
    if (!el) return;
    function fmt() {
        var d = el.value.replace(/\D/g, '').slice(0, 8);   // digits only, MMDDYYYY
        var out = d;
        if (d.length > 4)      out = d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4);
        else if (d.length > 2) out = d.slice(0, 2) + '/' + d.slice(2);
        el.value = out;
    }
    el.addEventListener('input', fmt);
    fmt();   // normalise any pre-filled value
})();
</script>
</body>
</html>
