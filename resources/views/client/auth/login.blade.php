<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Owner Login - Apex Growth Solutions</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
            color: #0f172a;
            background: #0b1220;
            background-image:
                radial-gradient(900px circle at 15% 15%, rgba(37,99,235,.28), transparent 45%),
                radial-gradient(800px circle at 85% 85%, rgba(56,189,248,.20), transparent 45%),
                linear-gradient(135deg, #0b1220 0%, #0f2140 55%, #0b1220 100%);
        }
        .shell { width: 100%; max-width: 430px; }
        .brand { text-align: center; margin-bottom: 22px; color: #fff; }
        .brand .logo { max-height: 84px; max-width: 240px; width: auto; margin: 0 auto 12px; display: block; }
        .brand span { font-size: 12px; color: #93c5fd; letter-spacing: .12em; text-transform: uppercase; }
        .card {
            background: #fff; border-radius: 18px; padding: 46px 32px 42px; min-height: 560px;
            display: flex; flex-direction: column; justify-content: center;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .card h2 { font-size: 21px; margin-bottom: 4px; }
        .card .sub { color: #64748b; font-size: 13.5px; margin-bottom: 20px; }
        .alert {
            background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
            border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 16px;
        }
        .field { margin-bottom: 15px; }
        .field label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .field input[type=email], .field input[type=password] {
            width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-size: 14.5px; background: #f8fafc; transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .field input:focus {
            outline: none; border-color: #2563eb; background: #fff;
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
        }
        .row { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 20px; }
        .remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; cursor: pointer; }
        .remember input { width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer; }
        .btn {
            width: 100%; padding: 13px; border: 0; border-radius: 10px; cursor: pointer;
            font-size: 15px; font-weight: 700; color: #fff;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 10px 22px rgba(37,99,235,.35); transition: transform .1s, box-shadow .15s;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 14px 28px rgba(37,99,235,.45); }
        .secure { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 16px; }
        .foot { text-align: center; margin-top: 18px; }
        .foot a { color: #cbd5e1; font-size: 13px; text-decoration: none; }
        .foot a:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">
            <img src="{{ asset('Images/logo.png') }}" alt="Apex Growth Solutions" class="logo">
            <span>Business Owner Portal</span>
        </div>

        <div class="card">
            <h2>Welcome back</h2>
            <div class="sub">Sign in to manage and review your credit repair clients.</div>

            @if ($errors->any())
                <div class="alert">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('client.login') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="row">
                    <label class="remember"><input type="checkbox" name="remember"> Remember me</label>
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>

            <div class="secure">🔒 Secure, encrypted login</div>
        </div>

        <div class="foot">
            <a href="{{ route('home') }}">&larr; Back to homepage</a>
        </div>
    </div>
</body>
</html>
