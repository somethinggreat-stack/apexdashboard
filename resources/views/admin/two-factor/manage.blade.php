@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Two-factor sign-in')

@section('content')
<div class="tf-wrap">
    <div class="card tf-card">
        <h2 class="tf-h">Two-factor sign-in</h2>

        @if (session('status'))<div class="tf-ok">{{ session('status') }}</div>@endif

        @if (! $enabled)
            <p class="tf-sub">Two-factor sign-in is switched off for the whole system right now.</p>
        @elseif ($admin->hasTwoFactor())
            <p class="tf-state on">✔ On since {{ $admin->two_factor_confirmed_at->format('M j, Y') }}</p>
            <p class="tf-sub">Signing in needs your password and a 6-digit code from Google Authenticator. You have <strong>{{ $admin->recoveryCodesLeft() }}</strong> recovery code{{ $admin->recoveryCodesLeft() === 1 ? '' : 's' }} left.</p>

            <h3 class="tf-h3">Make new recovery codes</h3>
            <p class="tf-sub">This replaces your old codes. Enter your password and a current code to confirm.</p>
            <form method="POST" action="{{ route('admin.two-factor.regenerate') }}" class="tf-form" autocomplete="off">
                @csrf
                <input type="password" name="password" placeholder="Your password" required autocomplete="current-password">
                <input type="text" name="code" inputmode="numeric" maxlength="7" placeholder="6-digit code" required autocomplete="one-time-code">
                <button class="btn">Make new codes</button>
            </form>

            @if (! $required)
                <h3 class="tf-h3">Turn off</h3>
                <form method="POST" action="{{ route('admin.two-factor.disable') }}" class="tf-form" autocomplete="off"
                      onsubmit="return confirm('Turn off two-factor sign-in for your account?');">
                    @csrf
                    <input type="password" name="password" placeholder="Your password" required autocomplete="current-password">
                    <input type="text" name="code" inputmode="numeric" maxlength="7" placeholder="6-digit code" required autocomplete="one-time-code">
                    <button class="btn btn-danger">Turn off</button>
                </form>
            @else
                <p class="tf-note">Two-factor sign-in is required for your role, so it can't be turned off here.</p>
            @endif

            @if ($errors->any())<div class="tf-err">{{ $errors->first() }}</div>@endif
        @else
            <p class="tf-state off">Not set up</p>
            <p class="tf-sub">{{ $required ? 'Your role requires it.' : 'Add a second step to your sign-in.' }} It takes about a minute with Google Authenticator.</p>
            <a href="{{ route('admin.two-factor.setup') }}" class="btn btn-primary">Set up now</a>
        @endif
    </div>
</div>

@push('head')
<style>
    .tf-wrap { max-width:640px; margin:10px auto; }
    .tf-card { padding:28px 30px; }
    .tf-h { margin:0 0 10px; font-size:21px; }
    .tf-h3 { margin:26px 0 4px; font-size:15px; }
    .tf-sub { margin:0 0 14px; color:var(--pro-muted,#64748b); font-size:14px; line-height:1.55; }
    .tf-state { display:inline-block; margin:0 0 10px; padding:4px 12px; border-radius:999px; font-size:13px; font-weight:700; }
    .tf-state.on { background:#dcfce7; color:#166534; } .tf-state.off { background:#fef3c7; color:#92400e; }
    .tf-form { display:flex; gap:10px; flex-wrap:wrap; }
    .tf-form input { padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:14px; min-width:150px; }
    .tf-ok { margin-bottom:14px; padding:10px 14px; background:#dcfce7; color:#14532d; border-radius:10px; font-size:13.5px; }
    .tf-err { margin-top:14px; padding:10px 14px; background:#fee2e2; color:#7f1d1d; border-radius:10px; font-size:13.5px; }
    .tf-note { margin-top:22px; font-size:13px; color:var(--pro-muted,#64748b); }
</style>
@endpush
@endsection
