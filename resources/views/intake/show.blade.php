<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Secure Client Intake</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; background:#0f172a; color:#0f172a; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
        .wrap { max-width:720px; margin:0 auto; padding:28px 16px 60px; }
        .head { text-align:center; color:#fff; padding:24px 0 20px; }
        .head h1 { margin:0 0 6px; font-size:24px; }
        .head p { margin:0; color:#cbd5e1; font-size:14px; }
        .card { background:#fff; border-radius:14px; padding:22px; box-shadow:0 10px 30px rgba(0,0,0,.25); }
        .sec-title { font-size:13px; text-transform:uppercase; letter-spacing:.08em; color:#2563eb; font-weight:700; margin:22px 0 10px; border-bottom:1px solid #e2e8f0; padding-bottom:6px; }
        .sec-title:first-of-type { margin-top:0; }
        .row { display:flex; gap:12px; flex-wrap:wrap; }
        .fg { margin-bottom:12px; flex:1; min-width:180px; }
        label { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:5px; }
        label .opt { color:#94a3b8; font-weight:500; }
        input, select { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; }
        input:focus, select:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
        .hint { font-size:12px; color:#64748b; margin-top:4px; }
        .impact { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:8px 10px; border-radius:8px; font-size:12.5px; margin-top:6px; }
        .enroll-btn { display:inline-block; margin-top:8px; padding:10px 16px; background:#16a34a; color:#fff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700; }
        .enroll-btn:hover { background:#15803d; }
        .errors { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:13px; }
        .errors ul { margin:6px 0 0; padding-left:18px; }
        .submit { width:100%; margin-top:22px; padding:14px; background:#2563eb; color:#fff; border:0; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; }
        .submit:hover { background:#1d4ed8; }
        .secure { text-align:center; color:#94a3b8; font-size:12px; margin-top:14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>Secure Client Intake</h1>
        <p>Your information is encrypted and used only to work on your credit file.</p>
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
                <div class="fg"><label>Date of Birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required></div>
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
            <div class="fg"><label>Security Question Answer <span class="opt">(optional)</span></label><input type="text" name="credit_monitoring_security_answer" value="{{ old('credit_monitoring_security_answer') }}"></div>

            <button type="submit" class="submit">Submit Securely</button>
            <div class="secure">🔒 Encrypted submission · Your documents are stored privately.</div>
        </form>
    </div>
</div>
</body>
</html>
