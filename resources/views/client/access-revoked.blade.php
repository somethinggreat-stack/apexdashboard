<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Access Suspended — Apex Growth Solutions</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
            color: #0f172a;
            background:
                radial-gradient(900px circle at 15% 15%, rgba(239,68,68,.20), transparent 45%),
                radial-gradient(800px circle at 85% 85%, rgba(37,99,235,.16), transparent 45%),
                linear-gradient(135deg, #0b1220 0%, #1a0f14 55%, #0b1220 100%);
        }
        .shell { width: 100%; max-width: 480px; }
        .card {
            background: #fff; border-radius: 18px; padding: 40px 36px 34px;
            box-shadow: 0 24px 60px rgba(0,0,0,.4); text-align: center;
        }
        .logo { max-height: 62px; width: auto; display: block; margin: 0 auto 22px; }
        .ico {
            width: 68px; height: 68px; border-radius: 50%; margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center;
            background: #fee2e2; color: #dc2626;
        }
        .ico svg { width: 34px; height: 34px; }
        h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 8px; letter-spacing: -.4px; }
        .sub { font-size: 14px; color: #b91c1c; font-weight: 600; margin-bottom: 20px; }
        .amount-box {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
            padding: 16px; margin-bottom: 18px;
        }
        .amount-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #b91c1c; }
        .amount-val { font-size: 34px; font-weight: 800; color: #dc2626; line-height: 1.1; margin-top: 4px; }
        .msg { font-size: 13.5px; color: #475569; line-height: 1.6; margin-bottom: 8px; }
        .note { font-size: 12.5px; color: #94a3b8; margin-bottom: 22px; }
        .btn {
            display: inline-block; width: 100%; padding: 12px 16px; border: 0; cursor: pointer;
            background: #0f172a; color: #fff; font-size: 14px; font-weight: 700; border-radius: 10px;
            font-family: inherit;
        }
        .btn:hover { background: #1e293b; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            <picture>
                <source srcset="/Images/logo.webp" type="image/webp">
                <img src="/Images/logo.png" alt="Apex Growth Solutions" class="logo">
            </picture>

            <div class="ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>

            <h1>Dashboard Access Suspended</h1>
            <div class="sub">Due to non-payment of your invoice</div>

            <div class="amount-box">
                <div class="amount-label">Amount Due</div>
                <div class="amount-val">${{ number_format($outstanding, 2) }}</div>
            </div>

            <p class="msg">
                @if (!empty($client->access_revoked_message))
                    {{ $client->access_revoked_message }}
                @else
                    Your access to the dashboard has been temporarily suspended because of an outstanding balance.
                    Please settle the amount above to restore full access to your account.
                @endif
            </p>
            <p class="note">Once payment is received, access is restored right away. Contact your account manager for help.</p>

            <form method="POST" action="{{ route('client.logout') }}">
                @csrf
                <button type="submit" class="btn">Log Out</button>
            </form>
        </div>
    </div>
</body>
</html>
