<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="alternate icon" href="/Images/logo.png">
<link rel="apple-touch-icon" href="/Images/logo.png">
<title>Business Owner Login · Apex Growth Solutions</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --ink:#0e1b38;
  --muted:#7b8aa6;
  --line:#e3e8f2;
  --blue:#1d5ff5;
  --blue-dark:#1443c9;
  --blue-soft:#3b82f6;
  --sky:#5aa2ff;

  --nav-0:#03081a;
  --nav-1:#071230;
  --nav-2:#0b1c4a;

  --card-line:rgba(96,164,255,.28);
  --card-glow:rgba(58,130,246,.16);

  --r:14px;
  --font:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
}

*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:var(--font);
  color:var(--ink);
  background:#eef1f8;
  -webkit-font-smoothing:antialiased;
}

.shell{
  display:flex;
  min-height:100vh;
  overflow:hidden;
  position:relative;
}

/* ---------- LEFT : form ---------- */
.pane{
  width:46%;
  position:relative;
  background:
    radial-gradient(120% 90% at 0% 0%, #ffffff 0%, #f2f5fb 55%, #e9eef8 100%);
  display:flex;
  align-items:center;
  justify-content:center;
  padding:56px 6% 40px;
}
.pane::before,
.pane::after{
  content:"";
  position:absolute;
  pointer-events:none;
}
.pane::before{
  inset:auto -10% 8% -22%;
  height:46%;
  background:linear-gradient(180deg,rgba(255,255,255,.9),rgba(255,255,255,0));
  transform:skewY(-12deg);
  opacity:.75;
}
.pane::after{
  top:-14%;left:-30%;
  width:70%;height:70%;
  background:linear-gradient(140deg,rgba(255,255,255,.95),rgba(255,255,255,0) 70%);
  transform:rotate(-8deg);
}
.rail{
  position:absolute;left:2.4%;top:32%;
  width:3px;height:26%;
  background-image:radial-gradient(circle, #c3cee2 1.4px, transparent 1.6px);
  background-size:3px 22px;
  opacity:.9;
}

.form-col{
  position:relative;
  z-index:2;
  width:100%;
  max-width:410px;
}

.logo-img{height:54px;width:auto;display:block;margin-bottom:40px}

.eyebrow{
  font-size:12.5px;font-weight:700;letter-spacing:.14em;
  color:var(--blue);text-transform:uppercase;margin:0 0 10px;
}
h1{
  margin:0 0 8px;
  font-size:clamp(30px,3.2vw,40px);
  font-weight:800;letter-spacing:-.025em;line-height:1.05;
}
.lede{margin:0 0 30px;color:var(--muted);font-size:15px}

.alert{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:11px 13px;font-size:13px;margin:0 0 18px}
.alert div+div{margin-top:3px}
.notice{background:#eef4ff;border:1px solid #cdddff;color:#1e40af;border-radius:10px;padding:11px 13px;font-size:13px;margin:0 0 18px;display:flex;gap:9px;align-items:flex-start;line-height:1.45}
.notice svg{flex:0 0 auto;width:16px;height:16px;margin-top:1px}

label{
  display:block;
  font-size:12.5px;font-weight:600;
  color:#41506e;margin:0 0 7px;
}
.field{position:relative;margin-bottom:18px}
input[type=email],
input[type=password],
input[type=text]{
  width:100%;
  height:52px;
  padding:0 48px 0 16px;
  font:500 15px/1 var(--font);
  color:var(--ink);
  background:#fff;
  border:1px solid var(--line);
  border-radius:10px;
  box-shadow:0 1px 2px rgba(16,32,72,.04);
  transition:border-color .16s, box-shadow .16s;
}
input::placeholder{color:#a9b4c8;font-weight:400}
input:focus{
  outline:none;
  border-color:var(--blue-soft);
  box-shadow:0 0 0 4px rgba(59,130,246,.14);
}
.peek{
  position:absolute;right:8px;top:auto;bottom:8px;
  width:36px;height:36px;
  display:grid;place-items:center;
  border:0;border-radius:8px;background:transparent;
  color:#3b82f6;cursor:pointer;
}
.peek:hover{background:rgba(59,130,246,.09)}
.peek:focus-visible{outline:2px solid var(--blue);outline-offset:2px}

.row{display:flex;align-items:center;justify-content:space-between;margin:4px 0 24px}
.check{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#54627e;cursor:pointer}
.check input{
  appearance:none;-webkit-appearance:none;
  width:17px;height:17px;margin:0;
  border:1.5px solid #c4cee0;border-radius:5px;background:#fff;cursor:pointer;
  display:grid;place-items:center;transition:.14s;
}
.check input:checked{background:var(--blue);border-color:var(--blue)}
.check input:checked::after{
  content:"";width:9px;height:5px;
  border:2px solid #fff;border-top:0;border-right:0;
  transform:rotate(-45deg) translateY(-1px);
}
.check input:focus-visible{outline:2px solid var(--blue);outline-offset:2px}

.submit{
  width:100%;height:56px;
  display:flex;align-items:center;justify-content:center;gap:12px;
  font:700 16px/1 var(--font);color:#fff;
  background:linear-gradient(180deg,#2a6bf7,#1442cf);
  border:0;border-radius:11px;cursor:pointer;
  box-shadow:0 10px 22px -8px rgba(20,66,207,.65);
  transition:transform .12s, box-shadow .16s, filter .16s;
}
.submit:hover{filter:brightness(1.05);box-shadow:0 14px 28px -10px rgba(20,66,207,.7)}
.submit:active{transform:translateY(1px)}
.submit:focus-visible{outline:3px solid rgba(59,130,246,.5);outline-offset:3px}
.submit svg{transition:transform .18s}
.submit:hover svg{transform:translateX(4px)}

.note{
  display:flex;align-items:center;justify-content:center;gap:8px;
  margin:22px 0 0;font-size:13px;color:#8592ab;
}
.note svg{color:var(--blue-soft)}
.foot{
  margin-top:30px;padding-top:22px;
  border-top:1px solid var(--line);
  text-align:center;font-size:12.5px;color:#9aa6bd;
}
.foot a{color:var(--blue);font-weight:600;text-decoration:none}
.foot a:hover{text-decoration:underline}

/* ---------- RIGHT : stage ---------- */
.stage-wrap{
  flex:1;
  position:relative;
  margin-left:-2.5%;
  background:linear-gradient(180deg,#cfe0ff,#eef3ff);
  clip-path:polygon(13% 0, 0 50%, 13% 100%, 100% 100%, 100% 0);
}
.stage{
  position:absolute;inset:0;
  transform:translateX(4px);
  clip-path:polygon(13% 0, 0 50%, 13% 100%, 100% 100%, 100% 0);
  background:
    radial-gradient(70% 55% at 62% 52%, #123a86 0%, rgba(11,28,74,0) 62%),
    radial-gradient(120% 90% at 100% 0%, #0d2158 0%, rgba(4,9,26,0) 60%),
    linear-gradient(160deg,var(--nav-1) 0%, var(--nav-0) 55%, #050f2b 100%);
  display:flex;flex-direction:column;justify-content:center;
  padding:60px 6% 56px 14%;
  overflow:hidden;
}
.stage::before{
  content:"";position:absolute;inset:0;
  background-image:
    radial-gradient(circle, rgba(140,190,255,.55) .9px, transparent 1.1px),
    radial-gradient(circle, rgba(120,170,255,.28) .8px, transparent 1px);
  background-size:64px 64px, 27px 27px;
  background-position:0 0, 13px 9px;
  opacity:.5;
}
.stage::after{
  content:"";position:absolute;
  left:62%;top:52%;width:1100px;height:1100px;
  transform:translate(-50%,-50%);
  background:repeating-radial-gradient(circle, rgba(96,160,255,.075) 0 1px, transparent 1px 92px);
  opacity:.85;pointer-events:none;
}

.stage-inner{position:relative;z-index:2;max-width:820px;margin:0 auto;width:100%}

.stage h2{
  margin:0 0 14px;
  font-size:clamp(28px,3.4vw,46px);
  font-weight:800;letter-spacing:-.03em;line-height:1.08;
  color:#fff;
}
.stage h2 em{font-style:normal;color:#4c94ff;display:block}
.stage p.sub{
  margin:0 0 46px;
  font-size:clamp(14px,1.1vw,16.5px);line-height:1.6;
  color:#a9bfe4;max-width:520px;
}

.viz{position:relative;padding:6px 0 4px}
.grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  column-gap:26%;
  row-gap:44px;
}
.stat{
  position:relative;
  display:flex;align-items:center;gap:16px;
  padding:18px 20px;
  border:1px solid var(--card-line);
  border-radius:18px;
  background:linear-gradient(150deg, rgba(24,54,120,.55), rgba(8,20,52,.42));
  box-shadow:0 0 0 1px rgba(6,15,40,.5) inset, 0 18px 40px -26px rgba(0,0,0,.9);
  backdrop-filter:blur(2px);
  transition:border-color .2s, transform .2s;
}
.stat:hover{border-color:rgba(120,185,255,.55);transform:translateY(-2px)}
.stat .tile{
  flex:0 0 auto;
  width:52px;height:52px;border-radius:13px;
  display:grid;place-items:center;color:#fff;
  box-shadow:0 8px 18px -8px rgba(0,0,0,.8);
}
.t-blue{background:linear-gradient(160deg,#3b82f6,#1d4ed8)}
.t-teal{background:linear-gradient(160deg,#22b8b0,#0d7d84)}
.t-violet{background:linear-gradient(160deg,#8b5cf6,#5b34d1)}
.t-green{background:linear-gradient(160deg,#22c55e,#12813e)}
.stat .k{font-size:13.5px;font-weight:700;color:#e8f0ff;letter-spacing:.005em}
.stat .v{
  margin:2px 0 1px;
  font-size:34px;font-weight:800;letter-spacing:-.03em;color:#fff;line-height:1;
}
.stat .v small{font-size:17px;font-weight:700;letter-spacing:0;margin-right:6px;color:#fff}
.stat .m{font-size:12.5px;color:#9db4dc}

.link{position:absolute;border:1px solid rgba(92,158,255,.5);border-radius:0}
.link.tl{left:44%;right:50%;top:22%;bottom:50%;border-width:1px 1px 0 0;border-top-right-radius:16px}
.link.tr{left:50%;right:44%;top:22%;bottom:50%;border-width:1px 0 0 1px;border-top-left-radius:16px}
.link.bl{left:44%;right:50%;top:50%;bottom:22%;border-width:0 1px 1px 0;border-bottom-right-radius:16px}
.link.br{left:50%;right:44%;top:50%;bottom:22%;border-width:0 0 1px 1px;border-bottom-left-radius:16px}
.node{
  position:absolute;width:9px;height:9px;border-radius:50%;
  background:#6fb2ff;
  box-shadow:0 0 10px 2px rgba(90,160,255,.85);
  transform:translate(-50%,-50%);
}
.n1{left:44%;top:22%} .n2{left:56%;top:22%}
.n3{left:44%;top:78%} .n4{left:56%;top:78%}

.core{
  position:absolute;left:50%;top:50%;
  width:180px;height:180px;transform:translate(-50%,-50%);
  border-radius:50%;
  display:grid;place-items:center;
  background:radial-gradient(circle, rgba(56,132,255,.42) 0%, rgba(20,60,150,.18) 40%, rgba(4,10,30,0) 68%);
}
.ring{position:absolute;border-radius:50%;border:1px solid rgba(110,175,255,.28)}
.ring.a{inset:6px} .ring.b{inset:30px;border-color:rgba(120,185,255,.4)}
.ring.c{inset:54px;border-color:rgba(150,205,255,.55)}
.spark{
  width:22px;height:22px;border-radius:50%;
  background:radial-gradient(circle,#ffffff 0%,#8cc4ff 38%,rgba(60,140,255,0) 72%);
  box-shadow:0 0 26px 10px rgba(70,150,255,.75);
  animation:pulse 3.2s ease-in-out infinite;
}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.22);opacity:.82}}

.chips{display:flex;flex-wrap:wrap;gap:16px;justify-content:center;margin-top:52px}
.chip{
  display:flex;align-items:center;gap:10px;
  padding:14px 24px;border-radius:13px;
  border:1px solid var(--card-line);
  background:linear-gradient(150deg, rgba(24,54,120,.5), rgba(8,20,52,.35));
  color:#fff;font-size:15px;font-weight:700;letter-spacing:-.01em;
}
.chip svg{color:#6fb2ff}

/* ---------- responsive ---------- */
@media (max-width:1180px){
  .grid{column-gap:20%}
  .stat .v{font-size:29px}
  .core{width:150px;height:150px}
}
@media (max-width:980px){
  .shell{flex-direction:column}
  .pane{width:100%;padding:48px 24px 40px}
  .form-col{max-width:440px}
  .stage-wrap{margin:0;clip-path:none;background:none}
  .stage{position:relative;inset:auto;transform:none;clip-path:none;padding:56px 24px 64px}
  .stage-inner{max-width:560px}
  .stage p.sub br{display:none}
  .grid{grid-template-columns:1fr;column-gap:0;row-gap:16px}
  .link,.node,.core{display:none}
  .chips{margin-top:34px;gap:12px}
  .chip{padding:12px 18px;font-size:14px}
}
@media (max-width:480px){
  .pane{padding:38px 20px 34px}
  .logo-img{height:46px;margin-bottom:30px}
  .stage{padding:44px 20px 54px}
  .stat{padding:15px 16px}
  .stat .v{font-size:27px}
  .chips{justify-content:flex-start}
}
@media (prefers-reduced-motion:reduce){
  *{animation:none!important;transition:none!important}
}
</style>
</head>
<body>

<div class="shell">

  <!-- ============ LEFT : sign-in ============ -->
  <section class="pane">
    <span class="rail" aria-hidden="true"></span>

    <div class="form-col">

      <img class="logo-img" src="{{ asset('Images/logo.png') }}" alt="Apex Growth Solutions">

      <p class="eyebrow">Business Owner Portal</p>
      <h1>Welcome back</h1>
      <p class="lede">Sign in to track your clients.</p>

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

      <form method="POST" action="{{ route('client.login') }}">
        @csrf
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="username"
                 value="{{ old('email') }}" placeholder="name@company.com" required autofocus>
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
          <label class="check"><input type="checkbox" name="remember"> Remember me</label>
        </div>

        <button class="submit" type="submit">
          Sign In
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>
          </svg>
        </button>
      </form>

      <p class="note">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3 4.5 6v6c0 4.6 3.2 8.3 7.5 9.4 4.3-1.1 7.5-4.8 7.5-9.4V6L12 3Z"/>
        </svg>
        Secure, encrypted login
      </p>

      <p class="foot">© {{ date('Y') }} Apex Growth Solutions &nbsp;·&nbsp; <a href="{{ route('home') }}">Back to homepage</a></p>
    </div>
  </section>

  <!-- ============ RIGHT : stage ============ -->
  <section class="stage-wrap" aria-hidden="true">
    <div class="stage">
      <div class="stage-inner">

        <h2>Watch your clients climb.<em>Round by round.</em></h2>
        <p class="sub">See exactly where every client stands, what's due,<br>and what got removed this round — in real time.</p>

        <div class="viz">
          <span class="link tl"></span>
          <span class="link tr"></span>
          <span class="link bl"></span>
          <span class="link br"></span>
          <span class="node n1"></span>
          <span class="node n2"></span>
          <span class="node n3"></span>
          <span class="node n4"></span>

          <div class="core">
            <span class="ring a"></span><span class="ring b"></span><span class="ring c"></span>
            <span class="spark"></span>
          </div>

          <div class="grid">
            <div class="stat">
              <span class="tile t-blue">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3 2.7-5 6-5s6 2 6 5"/>
                  <path d="M16.5 5.6a3 3 0 0 1 0 5.5M18 14.4c2 .8 3 2.4 3 4.6"/>
                </svg>
              </span>
              <div>
                <div class="k">Client Files</div>
                <div class="v">24</div>
                <div class="m">Active Clients</div>
              </div>
            </div>

            <div class="stat">
              <span class="tile t-teal">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 11a8 8 0 0 0-14-5"/><path d="M4 13a8 8 0 0 0 14 5"/>
                  <path d="M6 2v4.5h4.5"/><path d="M18 22v-4.5h-4.5"/>
                </svg>
              </span>
              <div>
                <div class="k">Dispute Rounds</div>
                <div class="v"><small>Round</small>2</div>
                <div class="m">In Progress</div>
              </div>
            </div>

            <div class="stat">
              <span class="tile t-violet">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 12.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>
                  <path d="m8.5 11.5 3 3 8-8.5"/>
                </svg>
              </span>
              <div>
                <div class="k">Tasks</div>
                <div class="v">12</div>
                <div class="m">Tasks Today</div>
              </div>
            </div>

            <div class="stat">
              <span class="tile t-green">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M7 16v-4"/><path d="M12 16V8"/><path d="M17 16v-6"/>
                  <rect x="3" y="3" width="18" height="18" rx="4"/>
                </svg>
              </span>
              <div>
                <div class="k">Results</div>
                <div class="v">89</div>
                <div class="m">Deletions</div>
              </div>
            </div>
          </div>
        </div>

        <div class="chips">
          <span class="chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/></svg>
            Organized
          </span>
          <span class="chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h4l2.5-7 5 14L16 12h6"/></svg>
            Real-time
          </span>
          <span class="chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4.5 6v6c0 4.6 3.2 8.3 7.5 9.4 4.3-1.1 7.5-4.8 7.5-9.4V6L12 3Z"/></svg>
            Secure
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
