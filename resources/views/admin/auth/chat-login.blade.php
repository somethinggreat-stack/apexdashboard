<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="alternate icon" href="/Images/logo.png">
<link rel="apple-touch-icon" href="/Images/logo.png">
<title>Apex Team Chat · Sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#0e1b38; --muted:#7b8aa6; --line:#e3e8f2;
  --blue:#4f46e5; --blue-dark:#4338ca; --blue-soft:#6366f1; --sky:#818cf8;
  --nav-0:#0a0a1f; --nav-1:#141033; --nav-2:#1e1b4b;
  --card-line:rgba(129,140,248,.28); --r:14px;
  --font:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:var(--font);color:var(--ink);background:#eef1f8;-webkit-font-smoothing:antialiased;}
.shell{display:flex;min-height:100vh;overflow:hidden;position:relative;}

/* ---------- LEFT : form ---------- */
.pane{width:46%;position:relative;background:radial-gradient(120% 90% at 0% 0%, #ffffff 0%, #f2f2fb 55%, #eae9f8 100%);display:flex;align-items:center;justify-content:center;padding:56px 6% 40px;}
.pane::after{content:"";position:absolute;pointer-events:none;top:-14%;left:-30%;width:70%;height:70%;background:linear-gradient(140deg,rgba(255,255,255,.95),rgba(255,255,255,0) 70%);transform:rotate(-8deg);}
.rail{position:absolute;left:2.4%;top:32%;width:3px;height:26%;background-image:radial-gradient(circle,#c9cbe6 1.4px,transparent 1.6px);background-size:3px 22px;opacity:.9;}
.form-col{position:relative;z-index:2;width:100%;max-width:410px;}
.logo-img{height:52px;width:auto;display:block;margin-bottom:26px}
.brand-row{display:flex;align-items:center;gap:11px;margin-bottom:38px;}
.brand-badge{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(160deg,#6366f1,#4338ca);box-shadow:0 8px 20px -8px rgba(79,70,229,.7);flex:0 0 auto;}
.brand-badge svg{width:24px;height:24px;color:#fff}
.brand-name{font-weight:800;font-size:18px;letter-spacing:-.02em;line-height:1.1}
.brand-name span{display:block;font-weight:600;font-size:12px;color:var(--muted);letter-spacing:.02em}
.eyebrow{font-size:12.5px;font-weight:700;letter-spacing:.14em;color:var(--blue);text-transform:uppercase;margin:0 0 10px;}
h1{margin:0 0 8px;font-size:clamp(30px,3.2vw,40px);font-weight:800;letter-spacing:-.025em;line-height:1.05;}
.lede{margin:0 0 30px;color:var(--muted);font-size:15px}
.alert{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:11px 13px;font-size:13px;margin:0 0 18px}
.alert div+div{margin-top:3px}
.notice{background:#eef0ff;border:1px solid #d3d6ff;color:#3730a3;border-radius:10px;padding:11px 13px;font-size:13px;margin:0 0 18px;display:flex;gap:9px;align-items:flex-start;line-height:1.45}
.notice svg{flex:0 0 auto;width:16px;height:16px;margin-top:1px}
label{display:block;font-size:12.5px;font-weight:600;color:#41506e;margin:0 0 7px;}
.field{position:relative;margin-bottom:18px}
input[type=email],input[type=password],input[type=text]{width:100%;height:52px;padding:0 48px 0 16px;font:500 15px/1 var(--font);color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 1px 2px rgba(16,32,72,.04);transition:border-color .16s, box-shadow .16s;}
input::placeholder{color:#a9b4c8;font-weight:400}
input:focus{outline:none;border-color:var(--blue-soft);box-shadow:0 0 0 4px rgba(99,102,241,.16);}
.peek{position:absolute;right:8px;bottom:8px;width:36px;height:36px;display:grid;place-items:center;border:0;border-radius:8px;background:transparent;color:var(--blue-soft);cursor:pointer;}
.peek:hover{background:rgba(99,102,241,.09)}
.peek:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
.row{display:flex;align-items:center;justify-content:space-between;margin:4px 0 24px}
.check{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#54627e;cursor:pointer}
.check input{appearance:none;-webkit-appearance:none;width:17px;height:17px;margin:0;border:1.5px solid #c4cae0;border-radius:5px;background:#fff;cursor:pointer;display:grid;place-items:center;transition:.14s;}
.check input:checked{background:var(--blue);border-color:var(--blue)}
.check input:checked::after{content:"";width:9px;height:5px;border:2px solid #fff;border-top:0;border-right:0;transform:rotate(-45deg) translateY(-1px);}
.check input:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
.submit{width:100%;height:56px;display:flex;align-items:center;justify-content:center;gap:12px;font:700 16px/1 var(--font);color:#fff;background:linear-gradient(180deg,#6366f1,#4338ca);border:0;border-radius:11px;cursor:pointer;box-shadow:0 10px 22px -8px rgba(67,56,201,.65);transition:transform .12s, box-shadow .16s, filter .16s;}
.submit:hover{filter:brightness(1.06);box-shadow:0 14px 28px -10px rgba(67,56,201,.7)}
.submit:active{transform:translateY(1px)}
.submit:focus-visible{outline:3px solid rgba(99,102,241,.5);outline-offset:3px}
.submit svg{transition:transform .18s}
.submit:hover svg{transform:translateX(4px)}
.note{display:flex;align-items:center;justify-content:center;gap:8px;margin:22px 0 0;font-size:13px;color:#8592ab;}
.note svg{color:var(--blue-soft)}
.foot{margin-top:30px;padding-top:22px;border-top:1px solid var(--line);text-align:center;font-size:12.5px;color:#9aa6bd;}

/* ---------- RIGHT : chat stage ---------- */
.stage-wrap{flex:1;position:relative;margin-left:-2.5%;background:linear-gradient(180deg,#dcdcff,#eef0ff);clip-path:polygon(13% 0, 0 50%, 13% 100%, 100% 100%, 100% 0);}
.stage{position:absolute;inset:0;transform:translateX(4px);clip-path:polygon(13% 0, 0 50%, 13% 100%, 100% 100%, 100% 0);background:radial-gradient(70% 55% at 62% 45%, #312e81 0%, rgba(20,16,51,0) 62%),radial-gradient(120% 90% at 100% 0%, #1e1b4b 0%, rgba(10,10,31,0) 60%),linear-gradient(160deg,var(--nav-1) 0%, var(--nav-0) 55%, #050418 100%);display:flex;flex-direction:column;justify-content:center;padding:60px 6% 56px 14%;overflow:hidden;}
.stage::before{content:"";position:absolute;inset:0;background-image:radial-gradient(circle, rgba(150,150,255,.5) .9px, transparent 1.1px),radial-gradient(circle, rgba(130,130,255,.26) .8px, transparent 1px);background-size:64px 64px, 27px 27px;background-position:0 0, 13px 9px;opacity:.45;}
.stage-inner{position:relative;z-index:2;max-width:560px;margin:0 auto;width:100%}
.stage h2{margin:0 0 14px;font-size:clamp(28px,3.2vw,44px);font-weight:800;letter-spacing:-.03em;line-height:1.08;color:#fff;}
.stage h2 em{font-style:normal;color:#a5b4fc;display:block}
.stage p.sub{margin:0 0 40px;font-size:clamp(14px,1.1vw,16.5px);line-height:1.6;color:#c7c9f0;max-width:480px;}

/* mock chat window */
.chatcard{border:1px solid rgba(129,140,248,.32);border-radius:20px;background:linear-gradient(150deg, rgba(40,36,110,.6), rgba(14,12,40,.5));box-shadow:0 0 0 1px rgba(120,130,255,.18),0 30px 60px -30px rgba(0,0,0,.9);overflow:hidden;backdrop-filter:blur(4px);}
.chatcard-head{display:flex;align-items:center;gap:12px;padding:15px 18px;border-bottom:1px solid rgba(129,140,248,.2);}
.cc-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(160deg,#818cf8,#4f46e5);display:grid;place-items:center;color:#fff;font-weight:700;font-size:15px;flex:0 0 auto;position:relative;}
.cc-av .on{position:absolute;right:-1px;bottom:-1px;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #171338;}
.cc-name{font-weight:700;color:#fff;font-size:15px}
.cc-status{font-size:12px;color:#86efac}
.chatcard-body{padding:20px 18px;display:flex;flex-direction:column;gap:12px;}
.bub{max-width:74%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.45;}
.bub.them{align-self:flex-start;background:#26224d;color:#e6e6fb;border-bottom-left-radius:5px;}
.bub.me{align-self:flex-end;background:linear-gradient(160deg,#6366f1,#4f46e5);color:#fff;border-bottom-right-radius:5px;}
.bub .rx{display:inline-block;margin-top:6px;font-size:12px;background:rgba(255,255,255,.14);border-radius:999px;padding:2px 8px;}
.chatcard-foot{display:flex;align-items:center;gap:10px;padding:12px 16px;border-top:1px solid rgba(129,140,248,.2);}
.cc-input{flex:1;height:38px;border-radius:999px;background:rgba(255,255,255,.07);border:1px solid rgba(129,140,248,.25);color:#9ea0d6;display:flex;align-items:center;padding:0 16px;font-size:13px;}
.cc-send{width:38px;height:38px;border-radius:50%;background:linear-gradient(160deg,#6366f1,#4f46e5);display:grid;place-items:center;color:#fff;flex:0 0 auto;}
.cc-send svg{width:17px;height:17px}

.chips{display:flex;flex-wrap:wrap;gap:14px;margin-top:34px}
.chip{display:flex;align-items:center;gap:9px;padding:12px 20px;border-radius:13px;border:1px solid var(--card-line);background:linear-gradient(150deg, rgba(40,36,110,.5), rgba(14,12,40,.35));color:#fff;font-size:14.5px;font-weight:700;letter-spacing:-.01em;}
.chip svg{color:#a5b4fc}

@media (max-width:980px){
  .shell{flex-direction:column}
  .pane{width:100%;padding:48px 24px 40px}
  .form-col{max-width:440px}
  .stage-wrap{margin:0;clip-path:none;background:none}
  .stage{position:relative;inset:auto;transform:none;clip-path:none;padding:48px 24px 60px}
  .stage-inner{max-width:520px}
  .chips{gap:12px}
}
@media (max-width:480px){
  .pane{padding:38px 20px 34px}
  .stage{padding:40px 20px 50px}
  .chip{padding:11px 16px;font-size:13.5px}
}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<div class="shell">

  <!-- ============ LEFT : sign-in ============ -->
  <section class="pane">
    <span class="rail" aria-hidden="true"></span>

    <div class="form-col">

      <div class="brand-row">
        <span class="brand-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        </span>
        <span class="brand-name">Apex Team Chat<span>Apex Growth Solutions</span></span>
      </div>

      <p class="eyebrow">Team Chat</p>
      <h1>Welcome back</h1>
      <p class="lede">Sign in to your team's chat.</p>

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

      <form method="POST" action="{{ route('admin.chat-login.attempt') }}">
        @csrf
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="username"
                 value="{{ old('email') }}" placeholder="you@apexgrowthsolution.com" required autofocus>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password"
                 placeholder="••••••••••••" required>
          <button type="button" class="peek" id="peek" aria-label="Show password" aria-pressed="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12Z"/>
              <circle cx="12" cy="12" r="3.2"/>
            </svg>
          </button>
        </div>

        <div class="row">
          <label class="check"><input type="checkbox" name="remember" checked> Keep me signed in</label>
        </div>

        <button class="submit" type="submit">
          Open Team Chat
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>
          </svg>
        </button>
      </form>

      <p class="note">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3 4.5 6v6c0 4.6 3.2 8.3 7.5 9.4 4.3-1.1 7.5-4.8 7.5-9.4V6L12 3Z"/>
        </svg>
        Private team chat — authorized staff only
      </p>

      <p class="foot">© {{ date('Y') }} Apex Growth Solutions</p>
    </div>
  </section>

  <!-- ============ RIGHT : chat stage ============ -->
  <section class="stage-wrap" aria-hidden="true">
    <div class="stage">
      <div class="stage-inner">

        <h2>Your whole team.<em>One conversation.</em></h2>
        <p class="sub">Direct messages, groups, files and reactions — in real time, right on your desktop.</p>

        <div class="chatcard">
          <div class="chatcard-head">
            <span class="cc-av">A<span class="on"></span></span>
            <div>
              <div class="cc-name">Apex Teammate</div>
              <div class="cc-status">Online</div>
            </div>
          </div>
          <div class="chatcard-body">
            <div class="bub them">Sent the new dispute round — need a review 👀</div>
            <div class="bub me">On it. Nice work today 🔥<span class="rx">👍 3</span></div>
            <div class="bub them">📎 Round2.pdf</div>
          </div>
          <div class="chatcard-foot">
            <span class="cc-input">Message your team…</span>
            <span class="cc-send"><svg viewBox="0 0 24 24" fill="currentColor"><path d="m3 3 18 9-18 9 4-9-4-9z"/></svg></span>
          </div>
        </div>

        <div class="chips">
          <span class="chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Messages
          </span>
          <span class="chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Files
          </span>
          <span class="chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3 2.7-5 6-5s6 2 6 5"/><circle cx="18" cy="9" r="2.2"/></svg>
            Presence
          </span>
        </div>

      </div>
    </div>
  </section>

</div>

<script>
  (function(){
    var pw = document.getElementById('password');
    var peek = document.getElementById('peek');
    if (pw && peek) peek.addEventListener('click', function(){
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      peek.setAttribute('aria-pressed', String(show));
      peek.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  })();
</script>
</body>
</html>
