<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/Images/logo.png">
    <link rel="apple-touch-icon" href="/Images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>VA Admin Login - Apex Growth Solutions</title>
    <style>
        :root{
            --blue:#2563eb; --blue-d:#1d4ed8; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0;
        }
        *{ box-sizing:border-box; margin:0; padding:0; }
        html,body{ height:100%; }
        body{
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            color:var(--ink); background:#0b1a33;
        }
        .auth{ display:grid; grid-template-columns:1fr 1.12fr; min-height:100vh; }

        /* ---------- LEFT: form ---------- */
        .pane-form{
            background:#fbfcfe; display:flex; align-items:center; justify-content:center;
            padding:40px 32px; position:relative;
        }
        .form-wrap{ width:100%; max-width:380px; animation:rise .5s cubic-bezier(.22,1,.36,1) both; }
        .brand-logo{ height:52px; width:auto; display:block; margin-bottom:26px; }
        .portal-tag{ color:var(--blue); font-size:12px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; margin-bottom:10px; }
        h1{ font-size:34px; line-height:1.1; letter-spacing:-.02em; margin-bottom:8px; }
        .sub{ color:var(--muted); font-size:15px; margin-bottom:26px; }

        .alert{ background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:11px; padding:11px 13px; font-size:13px; margin-bottom:16px; }
        .alert div+div{ margin-top:3px; }
        .notice{ background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; border-radius:11px; padding:11px 13px; font-size:13px; margin-bottom:16px; display:flex; gap:9px; align-items:flex-start; line-height:1.45; }
        .notice svg{ flex:0 0 auto; width:16px; height:16px; margin-top:1px; }

        .fld{ display:block; margin-bottom:16px; }
        .fld > span{ display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:7px; }
        .fld input{
            width:100%; padding:13px 15px; border:1px solid var(--line); border-radius:11px;
            font-size:15px; background:#fff; color:var(--ink); transition:border-color .15s,box-shadow .15s;
        }
        .fld input::placeholder{ color:#9aa7b8; }
        .fld input:focus{ outline:none; border-color:var(--blue); box-shadow:0 0 0 4px rgba(37,99,235,.14); }
        .pw{ position:relative; }
        .pw input{ padding-right:46px; }
        .pw-eye{ position:absolute; right:6px; top:50%; transform:translateY(-50%); width:34px; height:34px; border:0; background:none; color:#94a3b8; cursor:pointer; display:flex; align-items:center; justify-content:center; border-radius:8px; }
        .pw-eye:hover{ color:var(--blue); background:#f1f5f9; }
        .pw-eye svg{ width:19px; height:19px; }

        .row{ display:flex; align-items:center; justify-content:space-between; margin:6px 0 22px; }
        .remember{ display:flex; align-items:center; gap:9px; font-size:13.5px; color:#475569; cursor:pointer; }
        .remember input{ width:17px; height:17px; accent-color:var(--blue); cursor:pointer; }

        .btn{
            width:100%; padding:15px; border:0; border-radius:11px; cursor:pointer;
            font-size:15px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; gap:9px;
            background:linear-gradient(135deg,var(--blue),var(--blue-d));
            box-shadow:0 12px 26px rgba(37,99,235,.34); transition:transform .12s,box-shadow .18s;
        }
        .btn:hover{ transform:translateY(-1px); box-shadow:0 16px 32px rgba(37,99,235,.44); }
        .btn svg{ width:18px; height:18px; transition:transform .18s; }
        .btn:hover svg{ transform:translateX(3px); }

        .secure{ display:flex; align-items:center; justify-content:center; gap:8px; font-size:13px; color:#7c8aa0; margin-top:22px; }
        .secure svg{ width:15px; height:15px; color:var(--blue); }
        .copy{ text-align:center; font-size:12px; color:#aab6c6; margin-top:30px; padding-top:18px; border-top:1px solid #eef2f7; }

        /* ---------- RIGHT: hero ---------- */
        .pane-hero{
            position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; padding:56px;
            background:
                radial-gradient(1000px circle at 78% 12%, rgba(37,99,235,.35), transparent 46%),
                radial-gradient(760px circle at 12% 92%, rgba(56,189,248,.20), transparent 45%),
                linear-gradient(140deg,#0a1a33 0%,#0d2247 52%,#0a1730 100%);
        }
        .pane-hero::before{ /* faint dot grid */
            content:""; position:absolute; inset:0;
            background-image:radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
            background-size:26px 26px; -webkit-mask-image:linear-gradient(180deg,transparent,#000 30%,#000 70%,transparent);
                    mask-image:linear-gradient(180deg,transparent,#000 30%,#000 70%,transparent);
        }
        .pane-hero::after{ /* rising arrow glow */
            content:""; position:absolute; right:-60px; top:0; bottom:0; width:60%;
            background:linear-gradient(60deg,transparent 40%,rgba(59,130,246,.18) 55%,transparent 62%);
            transform:skewX(-12deg); pointer-events:none;
        }
        .hero-wrap{ position:relative; width:100%; max-width:560px; color:#e8eefb; animation:rise .6s cubic-bezier(.22,1,.36,1) both; }
        .hero-title{ font-size:40px; line-height:1.08; letter-spacing:-.02em; font-weight:800; }
        .hero-title span{ color:#5b9bff; }
        .hero-sub{ margin:16px 0 34px; font-size:15.5px; line-height:1.6; color:#9fb2d4; max-width:460px; }

        .cards{ position:relative; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .cards::before{ content:""; position:absolute; left:8%; right:8%; top:50%; height:2px; transform:translateY(-1px);
            background:linear-gradient(90deg,transparent,rgba(91,155,255,.55),transparent); box-shadow:0 0 12px rgba(59,130,246,.5); }
        .cards::after{ content:""; position:absolute; top:8%; bottom:8%; left:50%; width:2px; transform:translateX(-1px);
            background:linear-gradient(180deg,transparent,rgba(91,155,255,.55),transparent); box-shadow:0 0 12px rgba(59,130,246,.5); }
        .glass{
            position:relative; z-index:1; border-radius:18px; padding:20px;
            background:linear-gradient(160deg,rgba(255,255,255,.08),rgba(255,255,255,.03));
            border:1px solid rgba(148,180,255,.16); backdrop-filter:blur(6px);
            box-shadow:0 18px 40px rgba(3,10,28,.5);
        }
        .glass-top{ display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .glass-top .t{ font-size:14px; font-weight:700; color:#cfe0ff; }
        .glass-top .dots{ color:#5f7bb0; letter-spacing:2px; font-size:15px; }
        .glass-body{ display:flex; align-items:center; gap:14px; }
        .g-ico{ width:52px; height:52px; border-radius:14px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; }
        .g-ico svg{ width:24px; height:24px; color:#fff; }
        .ico-blue{ background:linear-gradient(150deg,#3b82f6,#1d4ed8); box-shadow:0 8px 20px rgba(37,99,235,.5); }
        .ico-cyan{ background:linear-gradient(150deg,#22d3ee,#0891b2); box-shadow:0 8px 20px rgba(6,182,212,.45); }
        .ico-indigo{ background:linear-gradient(150deg,#6366f1,#4338ca); box-shadow:0 8px 20px rgba(79,70,229,.45); }
        .ico-green{ background:linear-gradient(150deg,#34d399,#059669); box-shadow:0 8px 20px rgba(16,185,129,.45); }
        .g-num{ font-size:30px; font-weight:800; line-height:1; color:#fff; }
        .g-num small{ font-size:16px; font-weight:700; color:#9fb2d4; margin-right:2px; }
        .g-lbl{ font-size:12.5px; color:#9fb2d4; margin-top:5px; }

        .pills{ display:flex; gap:12px; margin-top:26px; flex-wrap:wrap; }
        .pill{ display:inline-flex; align-items:center; gap:9px; padding:11px 16px; border-radius:12px; font-size:13.5px; font-weight:600; color:#cfe0ff;
            background:rgba(255,255,255,.05); border:1px solid rgba(148,180,255,.14); }
        .pill svg{ width:16px; height:16px; color:#5b9bff; }

        @keyframes rise{ from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:none; } }

        @media (max-width:980px){
            .auth{ grid-template-columns:1fr; }
            .pane-hero{ display:none; }
            .pane-form{ background:
                radial-gradient(700px circle at 50% 0%, rgba(37,99,235,.06), transparent 55%), #fbfcfe; }
        }
        @media (max-width:420px){ h1{ font-size:29px; } }
    </style>
</head>
<body>
    <div class="auth">
        <!-- LEFT — sign-in form -->
        <div class="pane-form">
            <div class="form-wrap">
                <img class="brand-logo" src="{{ asset('Images/logo.png') }}" alt="Apex Growth Solutions">
                <div class="portal-tag">VA Admin Portal</div>
                <h1>Welcome back</h1>
                <p class="sub">Sign in to your command center.</p>

                @if (session('status'))
                    <div class="notice">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login') }}">
                    @csrf
                    <label class="fld">
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="name@apexgrowthsolution.com" required autofocus>
                    </label>
                    <label class="fld">
                        <span>Password</span>
                        <div class="pw">
                            <input id="pwField" type="password" name="password" placeholder="••••••••••••" required>
                            <button type="button" class="pw-eye" id="pwToggle" aria-label="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </label>
                    <div class="row">
                        <label class="remember"><input type="checkbox" name="remember"> Remember me</label>
                    </div>
                    <button type="submit" class="btn">
                        Sign In
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </form>

                <div class="secure">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Secure access for authorized staff only
                </div>
                <div class="copy">© {{ date('Y') }} Apex Growth Solutions</div>
            </div>
        </div>

        <!-- RIGHT — brand / workspace preview -->
        <div class="pane-hero">
            <div class="hero-wrap">
                <h2 class="hero-title">Every client. Every round. <span>Total control.</span></h2>
                <p class="hero-sub">The command center for your whole team — track every client, dispute round and result in real time.</p>

                <div class="cards">
                    <div class="glass">
                        <div class="glass-top"><span class="t">Client Files</span><span class="dots">•••</span></div>
                        <div class="glass-body">
                            <span class="g-ico ico-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                            <div><div class="g-num">24</div><div class="g-lbl">Active Clients</div></div>
                        </div>
                    </div>
                    <div class="glass">
                        <div class="glass-top"><span class="t">Dispute Rounds</span><span class="dots">•••</span></div>
                        <div class="glass-body">
                            <span class="g-ico ico-cyan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg></span>
                            <div><div class="g-num"><small>Round</small> 2</div><div class="g-lbl">In Progress</div></div>
                        </div>
                    </div>
                    <div class="glass">
                        <div class="glass-top"><span class="t">Tasks</span><span class="dots">•••</span></div>
                        <div class="glass-body">
                            <span class="g-ico ico-indigo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                            <div><div class="g-num">12</div><div class="g-lbl">Tasks Today</div></div>
                        </div>
                    </div>
                    <div class="glass">
                        <div class="glass-top"><span class="t">Results</span><span class="dots">•••</span></div>
                        <div class="glass-body">
                            <span class="g-ico ico-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                            <div><div class="g-num">89</div><div class="g-lbl">Deletions</div></div>
                        </div>
                    </div>
                </div>

                <div class="pills">
                    <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg> Organized</span>
                    <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Real-time</span>
                    <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Secure</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var t = document.getElementById('pwToggle'), f = document.getElementById('pwField');
            if (t && f) t.addEventListener('click', function () {
                var show = f.type === 'password';
                f.type = show ? 'text' : 'password';
                t.style.color = show ? '#2563eb' : '';
            });
        })();
    </script>
</body>
</html>
