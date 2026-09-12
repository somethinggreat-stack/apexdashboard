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
        // it keeps a clean, unbranded premium form.
        $brand     = $client->intake_display_name;
        $brandLogo = $client->intakeLogoUrl();
    @endphp
    <title>{{ $brand ? $brand . ' — Client Intake' : 'Secure Client Intake' }}</title>
    <style>
        :root {
            --ink:#0f1729; --muted:#5b6b86; --line:#e7ecf3; --soft:#f6f8fc;
            --accent:#4f46e5; --accent2:#2563eb; --cyan:#22d3ee; --req:#ef4444;
        }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body {
            margin:0; color:var(--ink);
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            background:#080d1a;
            background-image:
                radial-gradient(1100px circle at 10% -5%, rgba(79,70,229,.28), transparent 45%),
                radial-gradient(900px circle at 92% 8%, rgba(34,211,238,.16), transparent 42%),
                radial-gradient(1000px circle at 50% 120%, rgba(37,99,235,.20), transparent 50%),
                linear-gradient(165deg,#080d1a 0%,#0c1730 55%,#080d1a 100%);
            background-attachment:fixed; min-height:100vh;
        }

        /* Live completion bar, pinned to the very top. */
        .progress-top { position:fixed; top:0; left:0; right:0; height:4px; background:rgba(255,255,255,.06); z-index:50; }
        .progress-top-fill { height:100%; width:0; border-radius:0 4px 4px 0;
            background:linear-gradient(90deg,#6366f1,#22d3ee); box-shadow:0 0 14px rgba(99,102,241,.6);
            transition:width .35s cubic-bezier(.4,0,.2,1); }

        .wrap { max-width:780px; margin:0 auto; padding:44px 18px 80px; }

        /* ---------- Hero ---------- */
        .head { text-align:center; color:#fff; padding:6px 0 26px; }
        .brand-logo { display:block; margin:0 auto 20px; max-height:76px; max-width:240px; width:auto;
            filter:drop-shadow(0 10px 26px rgba(0,0,0,.4)); }
        .head-badge { display:inline-flex; align-items:center; gap:8px; margin-bottom:20px; padding:8px 18px; border-radius:999px;
            font-size:11.5px; font-weight:700; letter-spacing:.14em; text-transform:uppercase;
            color:#c7d2fe; background:rgba(99,102,241,.14); border:1px solid rgba(129,140,248,.4);
            backdrop-filter:blur(6px); }
        .head-badge svg { width:13px; height:13px; }
        .head h1 { margin:0 0 12px; font-size:40px; line-height:1.08; letter-spacing:-.025em; font-weight:800; }
        .head h1 .grad { background:linear-gradient(100deg,#818cf8,#22d3ee); -webkit-background-clip:text;
            background-clip:text; -webkit-text-fill-color:transparent; }
        .head h1.branded { font-size:34px; }
        .head-sub { margin:0 auto; max-width:540px; color:#aeb9d4; font-size:15px; line-height:1.55; }
        .head-quote { margin:16px auto 0; max-width:520px; font-size:14px; font-style:italic; color:#8ea0c9;
            letter-spacing:.01em; }
        .trust { display:flex; flex-wrap:wrap; justify-content:center; gap:9px; margin:24px 0 0; }
        .trust span { display:inline-flex; align-items:center; gap:7px; color:#dbeafe; font-size:11.5px; font-weight:600;
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.11); padding:7px 13px; border-radius:999px; }
        .trust svg { width:13px; height:13px; color:#7dd3fc; }

        /* ---------- Card ---------- */
        .card { position:relative; background:#fff; border-radius:24px; padding:8px 34px 30px;
            box-shadow:0 40px 90px rgba(3,7,18,.5), 0 2px 0 rgba(255,255,255,.4) inset; overflow:hidden;
            animation:rise .6s cubic-bezier(.2,.7,.2,1) both; }
        @keyframes rise { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:none; } }
        .card::before { content:''; position:absolute; top:0; left:0; right:0; height:5px;
            background:linear-gradient(90deg,#4f46e5,#2563eb,#22d3ee); }

        .errors { background:#fff1f2; border:1px solid #fecdd3; color:#9f1239; border-radius:14px;
            padding:13px 16px; margin:22px 0 4px; font-size:13px; }
        .errors ul { margin:6px 0 0; padding-left:18px; }

        /* ---------- Section headers (numbered) ---------- */
        .sec { border-top:1px solid var(--line); margin-top:28px; padding-top:24px; }
        .sec:first-of-type { border-top:0; margin-top:14px; padding-top:8px; }
        .sec-head { display:flex; align-items:center; gap:13px; margin-bottom:18px; }
        .sec-num { flex:none; width:34px; height:34px; border-radius:11px; display:flex; align-items:center; justify-content:center;
            font-size:14px; font-weight:800; color:#fff; background:linear-gradient(135deg,#4f46e5,#6366f1);
            box-shadow:0 6px 16px rgba(79,70,229,.32); }
        .sec-title { font-size:16.5px; font-weight:800; letter-spacing:-.01em; color:var(--ink); line-height:1.2; }
        .sec-desc { font-size:12.5px; color:var(--muted); margin-top:1px; }
        .sec-title .opt { color:#94a3b8; font-weight:600; font-size:13px; }

        /* ---------- Fields ---------- */
        .row { display:flex; gap:15px; flex-wrap:wrap; }
        .fg { margin-bottom:15px; flex:1; min-width:200px; }
        .fg.full { flex:1 1 100%; }
        label { display:block; font-size:12.5px; font-weight:700; color:#37476a; margin-bottom:7px; letter-spacing:.01em; }
        label .opt { color:#9aa7bd; font-weight:500; }
        label .req { color:var(--req); margin-left:2px; }

        input, select {
            width:100%; padding:13px 15px; border:1.5px solid var(--line); border-radius:13px; font-size:14.5px;
            background:var(--soft); transition:border-color .15s, box-shadow .15s, background .15s; color:var(--ink);
        }
        input::placeholder { color:#aab4c6; }
        input:focus, select:focus { outline:none; border-color:var(--accent); background:#fff;
            box-shadow:0 0 0 4px rgba(79,70,229,.13); }
        input:not(:placeholder-shown):valid:not([type=file]) { border-color:#c7d2fe; }
        select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.4' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 14px center; padding-right:40px; cursor:pointer; }

        /* File pickers as soft dropzones */
        .file-field { position:relative; }
        input[type=file] { padding:14px 15px; background:#fff; border-style:dashed; border-color:#c9d4e6; cursor:pointer; font-size:13.5px; color:var(--muted); }
        input[type=file]:hover { border-color:var(--accent); background:#fbfcff; }
        input[type=file]::file-selector-button {
            margin-right:14px; border:0; border-radius:9px; padding:9px 15px; cursor:pointer;
            background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; font-weight:700; font-size:13px;
            box-shadow:0 4px 12px rgba(37,99,235,.28); }
        input[type=file]::file-selector-button:hover { filter:brightness(1.06); }

        .hint { font-size:12px; color:var(--muted); margin-top:6px; }
        .hint a { color:var(--accent2); font-weight:600; text-decoration:none; }
        .hint a:hover { text-decoration:underline; }
        .impact { display:flex; align-items:center; gap:8px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;
            padding:10px 13px; border-radius:11px; font-size:12.5px; font-weight:600; margin-top:8px; }
        .impact svg { flex:none; width:15px; height:15px; }

        /* Monitoring explainer */
        .cm-note { margin:2px 0 18px; padding:16px 18px; border:1px solid #dbe4ff; border-radius:16px;
            background:linear-gradient(135deg,#f5f7ff 0%,#eef6ff 55%,#f3f0ff 100%);
            box-shadow:0 8px 22px rgba(79,70,229,.07); color:#22315a; }
        .cm-note-head { display:flex; align-items:center; gap:8px; font-size:12px; font-weight:800;
            letter-spacing:.04em; text-transform:uppercase; color:var(--accent); margin-bottom:9px; }
        .cm-note p { margin:0 0 10px; font-size:13.5px; line-height:1.6; }
        .cm-note ul { margin:0; padding-left:19px; display:flex; flex-direction:column; gap:7px; }
        .cm-note li { font-size:13px; line-height:1.5; }
        .cm-note li::marker { color:#818cf8; }
        .cm-note strong { color:#111d3d; }
        .enroll-btn { display:inline-flex; align-items:center; gap:7px; margin-top:10px; padding:11px 18px; border-radius:11px;
            text-decoration:none; background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; font-size:14px;
            font-weight:800; box-shadow:0 8px 18px rgba(22,163,74,.26); }
        .enroll-btn:hover { filter:brightness(1.05); }
        .readonly-field { background:#eef2f8 !important; color:#475569; font-weight:600; }

        /* Submit */
        .submit-wrap { margin-top:30px; }
        .submit { width:100%; padding:17px; border:0; border-radius:15px; cursor:pointer;
            background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; font-size:16px; font-weight:800;
            letter-spacing:.01em; box-shadow:0 16px 34px rgba(79,70,229,.4); transition:transform .12s, box-shadow .18s, filter .15s;
            display:inline-flex; align-items:center; justify-content:center; gap:10px; }
        .submit:hover { transform:translateY(-2px); box-shadow:0 22px 44px rgba(79,70,229,.5); filter:brightness(1.03); }
        .submit svg { width:18px; height:18px; }
        .secure { display:flex; align-items:center; justify-content:center; gap:7px; text-align:center;
            color:#8592ab; font-size:12px; margin-top:16px; }
        .secure svg { width:13px; height:13px; }

        @media (max-width:600px){
            .wrap { padding:30px 12px 60px; }
            .head h1 { font-size:31px; } .head h1.branded { font-size:26px; }
            .card { padding:6px 18px 24px; border-radius:20px; }
            input, select { font-size:16px; }   /* stop iOS zoom-on-focus */
            .row { gap:0; }
        }
    </style>
</head>
<body>
<div class="progress-top"><div class="progress-top-fill" id="pFill"></div></div>

<div class="wrap">
    <div class="head">
        @if ($brandLogo)
            <img src="{{ $brandLogo }}" alt="{{ $brand ?: $client->business_name }}" class="brand-logo">
        @endif
        <div class="head-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Secure &amp; Private Onboarding
        </div>
        @if ($brand)
            <h1 class="branded">Welcome to <span class="grad">{{ $brand }}</span></h1>
            <p class="head-sub">Complete this secure form and your team starts working on your credit file right away. It only takes a few minutes.</p>
        @else
            <h1>Let&rsquo;s Get to Work on <span class="grad">Your Credit</span></h1>
            <p class="head-sub">Complete this secure form and your team starts working on your file right away. It only takes a few minutes.</p>
        @endif
        <p class="head-quote">&ldquo;The hardest part is starting — and you just did.&rdquo;</p>
        <div class="trust">
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Bank-grade encryption</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Private &amp; secure</span>
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> Documents stored privately</span>
        </div>
    </div>

    <div class="card">
        @if ($errors->any())
            <div class="errors">
                <strong>Please fix the following:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('intake.store', ['token' => $token]) }}" enctype="multipart/form-data" id="intakeForm">
            @csrf

            {{-- 1 · Your Details --}}
            <div class="sec">
                <div class="sec-head">
                    <div class="sec-num">1</div>
                    <div><div class="sec-title">Your Details</div><div class="sec-desc">Tell us who you are.</div></div>
                </div>
                <div class="row">
                    <div class="fg"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="First name" required></div>
                    <div class="fg"><label>Middle Name <span class="opt">(optional)</span></label><input type="text" name="middle_name" value="{{ old('middle_name') }}" placeholder="Middle name"></div>
                </div>
                <div class="row">
                    <div class="fg"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Last name" required></div>
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
                    <div class="fg"><label>Email Address <span class="req">*</span></label><input type="email" name="email" value="{{ old('email') }}" placeholder="you@email.com" required></div>
                    <div class="fg"><label>Phone <span class="req">*</span></label><input type="text" name="phone" value="{{ old('phone') }}" placeholder="(555) 123-4567" required></div>
                </div>
                <div class="row">
                    <div class="fg"><label>Date of Birth <span class="req">*</span></label><input type="text" name="date_of_birth" id="dobInput" value="{{ old('date_of_birth') }}" inputmode="numeric" autocomplete="bday" placeholder="MM/DD/YYYY" maxlength="10" pattern="(0[1-9]|1[0-2])/(0[1-9]|[12]\d|3[01])/(19|20)\d\d" title="Enter your date of birth as MM/DD/YYYY" required></div>
                    <div class="fg"><label>Full SSN <span class="req">*</span></label><input type="text" name="ssn" inputmode="numeric" placeholder="XXX-XX-XXXX" required></div>
                </div>
            </div>

            {{-- 2 · Mailing Address --}}
            <div class="sec">
                <div class="sec-head">
                    <div class="sec-num">2</div>
                    <div><div class="sec-title">Mailing Address</div><div class="sec-desc">Where your mail is delivered.</div></div>
                </div>
                <div class="fg full"><label>Street Address <span class="req">*</span></label><input type="text" name="current_address" value="{{ old('current_address') }}" placeholder="123 Main St" required></div>
                <div class="row">
                    <div class="fg"><label>Apt / Suite <span class="opt">(optional)</span></label><input type="text" name="address_line2" value="{{ old('address_line2') }}" placeholder="Apt, suite, unit"></div>
                    <div class="fg"><label>City <span class="req">*</span></label><input type="text" name="city" value="{{ old('city') }}" placeholder="City" required></div>
                </div>
                <div class="row">
                    <div class="fg"><label>State <span class="req">*</span></label><input type="text" name="state" value="{{ old('state') }}" placeholder="State" required></div>
                    <div class="fg"><label>Zip Code <span class="req">*</span></label><input type="text" name="zipcode" value="{{ old('zipcode') }}" placeholder="ZIP" required></div>
                </div>
            </div>

            {{-- 3 · Documents --}}
            <div class="sec">
                <div class="sec-head">
                    <div class="sec-num">3</div>
                    <div><div class="sec-title">Documents</div><div class="sec-desc">Clear photos or PDFs — up to 10 MB each.</div></div>
                </div>
                <div class="fg file-field"><label>Driver&rsquo;s License <span class="req">*</span></label><input type="file" name="drivers_license" accept=".pdf,.jpg,.jpeg,.png,.webp" required><div class="hint">PDF or image, up to 10 MB.</div></div>
                <div class="fg file-field">
                    <label>Social Security Card <span class="opt">(optional)</span></label>
                    <input type="file" name="ssn_card" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <div class="impact"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Adding this helps get stronger results on your file.</div>
                </div>
                <div class="fg file-field"><label>Proof of Address <span class="req">*</span></label><input type="file" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png,.webp" required><div class="hint">Utility bill, bank statement, or lease — up to 10 MB.</div></div>
            </div>

            {{-- 4 · Credit Monitoring --}}
            <div class="sec">
                <div class="sec-head">
                    <div class="sec-num">4</div>
                    <div><div class="sec-title">Credit Monitoring</div><div class="sec-desc">So we can track your file in real time.</div></div>
                </div>
                <div class="cm-note">
                    <div class="cm-note-head">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"/><line x1="12" y1="11" x2="12" y2="16.5"/><line x1="12" y1="7.5" x2="12" y2="7.5"/></svg>
                        Why we need this
                    </div>
                    <p>To get you real results, we pull a fresh <strong>3&#8209;bureau credit report every 30 days</strong>. That means we need active credit monitoring the whole time we&rsquo;re working for you.</p>
                    <ul>
                        <li><strong>You&rsquo;re not paying us for monitoring.</strong> You pick a provider and they bill you directly &mdash; it&rsquo;s a small, separate cost.</li>
                        <li><strong>Please keep it on</strong> until we tell you you&rsquo;re all set. Canceling early stops our work and slows your results.</li>
                        <li>It lets us watch your <strong>scores, balances, inquiries, and late payments in real time</strong>, so we can move fast and get you the strongest results while we prepare you for funding.</li>
                    </ul>
                </div>
                @if ($client->intake_monitoring_provider)
                    <input type="hidden" name="credit_monitoring_name" value="{{ $client->intake_monitoring_provider }}">
                    <div class="fg">
                        <label>Credit Monitoring Provider</label>
                        <input type="text" value="{{ $client->intake_monitoring_provider }}" class="readonly-field" readonly>
                        @if ($client->intake_monitoring_enroll_url)
                            <a href="{{ $client->intake_monitoring_enroll_url }}" target="_blank" rel="noopener" class="enroll-btn">Get Credit Monitoring &rarr;</a>
                        @endif
                        <div class="hint">Sign up with {{ $client->intake_monitoring_provider }} using the button above, then enter your login email &amp; password below.</div>
                    </div>
                @else
                    <div class="fg"><label>Credit Monitoring Provider <span class="req">*</span></label><input type="text" name="credit_monitoring_name" value="{{ old('credit_monitoring_name') }}" placeholder="e.g. IdentityIQ, MyScoreIQ, SmartCredit" required></div>
                @endif
                <div class="row">
                    <div class="fg"><label>Credit Monitoring Email <span class="req">*</span></label><input type="text" name="credit_monitoring_username" value="{{ old('credit_monitoring_username') }}" placeholder="Login email" required></div>
                    <div class="fg"><label>Credit Monitoring Password <span class="req">*</span></label><input type="text" name="credit_monitoring_password" placeholder="Login password" required></div>
                </div>
                @if ($client->intake_security_extra)
                    <div class="fg"><label>Security Question <span class="req">*</span></label><input type="text" name="credit_monitoring_security_question" value="{{ old('credit_monitoring_security_question') }}" required></div>
                    <div class="fg"><label>Security Answer <span class="req">*</span></label><input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}" required></div>
                    <div class="fg"><label>What is your 4-digit PIN? <span class="req">*</span></label><input type="text" name="credit_monitoring_pin" value="{{ old('credit_monitoring_pin') }}" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="0000" required></div>
                @else
                    <div class="fg"><label>Security Question Answer <span class="opt">(optional)</span></label><input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}" placeholder="Your answer"></div>
                @endif
            </div>

            {{-- 5 · CFPB Logins --}}
            <div class="sec">
                <div class="sec-head">
                    <div class="sec-num">5</div>
                    <div><div class="sec-title">CFPB Logins <span class="opt">(optional)</span></div><div class="sec-desc">Helps us file complaints on your behalf.</div></div>
                </div>
                <div class="fg">
                    <div class="hint">Don&rsquo;t have a CFPB account yet? <a href="https://portal.consumerfinance.gov/consumer/s/login/SelfRegister" target="_blank" rel="noopener">Create one here &rarr;</a></div>
                </div>
                <div class="row">
                    <div class="fg"><label>CFPB Username <span class="opt">(optional)</span></label><input type="text" name="cfpb_email" value="{{ old('cfpb_email') }}" autocomplete="off" placeholder="CFPB login"></div>
                    <div class="fg"><label>CFPB Password <span class="opt">(optional)</span></label><input type="text" name="cfpb_password" autocomplete="off" placeholder="CFPB password"></div>
                </div>
            </div>

            <div class="submit-wrap">
                <button type="submit" class="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Submit Securely
                </button>
                <div class="secure">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Encrypted submission · your documents are stored privately.
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // Date of Birth: type digits and the slashes appear (MM/DD/YYYY). Value posts
    // as MM/DD/YYYY, which the server parses to a real date.
    var el = document.getElementById('dobInput');
    if (el) {
        var fmt = function () {
            var d = el.value.replace(/\D/g, '').slice(0, 8);
            var out = d;
            if (d.length > 4)      out = d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4);
            else if (d.length > 2) out = d.slice(0, 2) + '/' + d.slice(2);
            el.value = out;
        };
        el.addEventListener('input', fmt);
        fmt();
    }

    // Live completion bar — fills as required fields get filled in.
    var form = document.getElementById('intakeForm');
    var fill = document.getElementById('pFill');
    if (form && fill) {
        var required = Array.prototype.slice.call(form.querySelectorAll('[required]'));
        var update = function () {
            if (!required.length) { fill.style.width = '100%'; return; }
            var done = required.filter(function (f) {
                if (f.type === 'file') return f.files && f.files.length > 0;
                return (f.value || '').trim() !== '';
            }).length;
            fill.style.width = Math.round(done / required.length * 100) + '%';
        };
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    }
})();
</script>
</body>
</html>
