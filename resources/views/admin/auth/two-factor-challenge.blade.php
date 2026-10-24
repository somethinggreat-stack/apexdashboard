<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Verify it's you | Apex Growth Solutions</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { color-scheme: light; --ink:#0F2043; --blue:#1A6FC4; --blue2:#2196F3; --text:#334155; --mute:#64748B; --line:#E3EAF6; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family:'Geist',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
    padding:24px; color:var(--ink); background:linear-gradient(180deg,#fff 0%,#F5F9FF 55%,#EAF3FF 100%); -webkit-font-smoothing:antialiased; }
.card { width:100%; max-width:420px; background:#fff; border:1px solid var(--line); border-radius:22px; padding:34px 30px; box-shadow:0 1.4rem 3rem rgba(26,111,196,.12); }
.logo { display:block; height:46px; margin:0 auto 22px; }
h1 { font-size:22px; font-weight:700; text-align:center; letter-spacing:-.02em; }
.sub { margin:10px 0 22px; text-align:center; color:var(--mute); font-size:14.5px; line-height:1.55; }
label { display:block; font-size:13px; font-weight:600; margin-bottom:7px; }
input[type=text] { width:100%; padding:14px; border:1.5px solid var(--line); border-radius:12px; font:inherit; font-size:24px; letter-spacing:.35em; text-align:center; color:var(--ink); }
input.recovery { font-size:17px; letter-spacing:.08em; }
input:focus { outline:none; border-color:var(--blue2); box-shadow:0 0 0 3px rgba(33,150,243,.15); }
button.go { width:100%; margin-top:16px; padding:14px; border:0; border-radius:12px; font:inherit; font-weight:600; font-size:15.5px; color:#fff; cursor:pointer;
    background:linear-gradient(135deg,#1A6FC4,#0F2043); box-shadow:0 .6rem 1.3rem rgba(15,32,67,.25); }
.err { margin-bottom:14px; padding:11px 14px; border-radius:10px; background:#FEE2E2; color:#7F1D1D; font-size:13.5px; }
.alt { margin-top:18px; text-align:center; font-size:13.5px; color:var(--mute); }
.alt a, .alt button { color:var(--blue); background:none; border:0; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
.hide { display:none; }
</style>
</head>
<body>
<div class="card">
    <img class="logo" src="/Images/logo.png" alt="Apex Growth Solutions">
    <h1>Enter your 6-digit code</h1>
    <p class="sub">Open <strong>Google Authenticator</strong> on your phone and type the code shown for Apex Growth Solutions.</p>

    @if ($errors->any())
        <div class="err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.two-factor.verify') }}" autocomplete="off" id="codeForm">
        @csrf
        <div id="codeBox">
            <label for="code">Authenticator code</label>
            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus placeholder="000000">
        </div>
        <div id="recoveryBox" class="hide">
            <label for="recovery_code">Recovery code</label>
            <input type="text" id="recovery_code" name="recovery_code" class="recovery" maxlength="16" autocomplete="off" placeholder="xxxxx-xxxxx" disabled>
        </div>
        <button type="submit" class="go">Verify and sign in</button>
    </form>

    <p class="alt">
        <button type="button" id="toggle">Lost your phone? Use a recovery code</button>
        &nbsp;·&nbsp;
        <a href="{{ route($chat ? 'admin.chat-login' : 'admin.login') }}">Start over</a>
    </p>
</div>
<script>
(function () {
    var useRecovery = {{ $errors->has('recovery_code') ? 'true' : 'false' }};
    var codeBox = document.getElementById('codeBox'), recBox = document.getElementById('recoveryBox');
    var code = document.getElementById('code'), rec = document.getElementById('recovery_code');
    var btn = document.getElementById('toggle');
    function apply() {
        codeBox.classList.toggle('hide', useRecovery); recBox.classList.toggle('hide', !useRecovery);
        code.disabled = useRecovery; rec.disabled = !useRecovery;
        btn.textContent = useRecovery ? 'Use my authenticator app instead' : 'Lost your phone? Use a recovery code';
        (useRecovery ? rec : code).focus();
    }
    btn.addEventListener('click', function () { useRecovery = !useRecovery; apply(); });
    apply();
    // Submit as soon as six digits are typed.
    code.addEventListener('input', function () {
        if (code.value.replace(/\s+/g, '').length === 6) { document.getElementById('codeForm').submit(); }
    });
})();
</script>
</body>
</html>
