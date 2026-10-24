@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Set up two-factor sign-in')

@section('content')
<div class="tf-wrap">
    <div class="card tf-card">
        <h2 class="tf-h">Set up your authenticator app</h2>
        <p class="tf-sub">Two-factor sign-in adds a 6-digit code from your phone to your password, so a stolen password alone can't open your account.</p>

        <ol class="tf-steps">
            <li>
                <strong>Install Google Authenticator</strong> on your phone (App Store or Google Play), if you don't have it yet.
            </li>
            <li>
                <strong>Scan this code</strong> with the app (tap <em>+</em>, then <em>Scan a QR code</em>).
                <div class="tf-qr">{!! $qr !!}</div>
                <details class="tf-manual">
                    <summary>Can't scan? Enter the key by hand</summary>
                    <p>Choose <em>Enter a setup key</em> in the app, use your email as the account name and type this key (time-based):</p>
                    <code class="tf-key">{{ $secret }}</code>
                </details>
            </li>
            <li>
                <strong>Enter the 6-digit code</strong> the app now shows, to finish.
                <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="tf-form" autocomplete="off">
                    @csrf
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" placeholder="000000" required autofocus>
                    <button type="submit" class="btn btn-primary">Turn on two-factor</button>
                </form>
                @error('code')<small class="tf-err">{{ $message }}</small>@enderror
            </li>
        </ol>
        <p class="tf-foot">Next you'll get 10 one-time <strong>recovery codes</strong>. Keep them somewhere safe (not on the same phone): they are your way back in if you lose the phone.</p>
    </div>
</div>

@push('head')
<style>
    .tf-wrap { max-width:640px; margin:10px auto; }
    .tf-card { padding:28px 30px; }
    .tf-h { margin:0 0 6px; font-size:21px; }
    .tf-sub { margin:0 0 20px; color:var(--pro-muted,#64748b); font-size:14px; line-height:1.55; }
    .tf-steps { margin:0; padding-left:20px; display:grid; gap:20px; font-size:14.5px; line-height:1.55; }
    .tf-qr { display:inline-block; margin:12px 0 4px; padding:12px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; }
    .tf-qr svg { display:block; width:200px; height:200px; }
    .tf-manual { margin-top:8px; font-size:13px; color:var(--pro-muted,#64748b); }
    .tf-manual summary { cursor:pointer; color:#1A6FC4; font-weight:600; }
    .tf-key { display:block; margin-top:8px; padding:10px 12px; background:#f1f5f9; border-radius:10px; font-size:15px; letter-spacing:.08em; word-break:break-all; color:#0f172a; }
    .tf-form { display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap; }
    .tf-form input { width:170px; padding:11px 12px; font-size:20px; letter-spacing:.3em; text-align:center; border:1.5px solid #cbd5e1; border-radius:10px; }
    .tf-err { display:block; margin-top:8px; color:#dc2626; font-size:13px; }
    .tf-foot { margin:22px 0 0; padding:12px 14px; background:#eff6ff; border-radius:12px; font-size:13px; color:#1e3a5f; }
</style>
@endpush
@endsection
